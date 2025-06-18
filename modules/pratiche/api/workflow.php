<?php
/**
 * modules/pratiche/api/workflow.php - API Gestione Workflow Pratiche
 * 
 * ✅ GESTIONE CAMBIO STATI E WORKFLOW
 * 
 * Endpoints:
 * - update_stato: Cambio stato pratica
 * - check_dependencies: Verifica dipendenze task
 * - bulk_update: Aggiornamento multiplo
 */

// Include bootstrap per autenticazione
require_once dirname(dirname(dirname(__DIR__))) . '/core/bootstrap.php';

// Verifica autenticazione
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

// Headers JSON
header('Content-Type: application/json');

// Ottieni dati POST
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Carica database
loadDatabase();
$db = Database::getInstance();
$currentUser = getCurrentUser();

// Carica config modulo
require_once dirname(__DIR__) . '/config.php';

// Router azioni
switch ($action) {
    case 'update_stato':
        updateStatoPratica($input, $db, $currentUser);
        break;
        
    case 'check_dependencies':
        checkTaskDependencies($input, $db);
        break;
        
    case 'get_workflow_info':
        getWorkflowInfo($input, $db);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

/**
 * Aggiorna stato pratica
 */
function updateStatoPratica($input, $db, $user) {
    $praticaId = $input['pratica_id'] ?? 0;
    $nuovoStato = $input['nuovo_stato'] ?? '';
    
    if (!$praticaId || !$nuovoStato) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    // Verifica che lo stato sia valido
    if (!isset(PRATICHE_STATI[$nuovoStato])) {
        echo json_encode(['success' => false, 'message' => 'Stato non valido']);
        return;
    }
    
    try {
        // Carica pratica
        $pratica = $db->selectOne(
            "SELECT * FROM pratiche WHERE id = ?",
            [$praticaId]
        );
        
        if (!$pratica) {
            echo json_encode(['success' => false, 'message' => 'Pratica non trovata']);
            return;
        }
        
        // Verifica permessi
        if (!$user['is_admin'] && $pratica['operatore_responsabile_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi']);
            return;
        }
        
        // Verifica se il cambio di stato è permesso
        $statoAttuale = $pratica['stato'];
        if (!canChangeState($statoAttuale, $nuovoStato, $pratica, $db)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cambio di stato non permesso. Verifica che tutti i task obbligatori siano completati.'
            ]);
            return;
        }
        
        // Inizia transazione
        $db->beginTransaction();
        
        // Aggiorna stato
        $updateData = [
            'stato' => $nuovoStato,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Se completata, salva data completamento
        if ($nuovoStato === 'completata' && !$pratica['data_completamento']) {
            $updateData['data_completamento'] = date('Y-m-d');
        }
        
        $updated = $db->update(
            'pratiche',
            $updateData,
            'id = ?',
            [$praticaId]
        );
        
        if (!$updated) {
            throw new Exception('Errore aggiornamento pratica');
        }
        
        // Log cambio stato
        logStatoChange($praticaId, $statoAttuale, $nuovoStato, $user['id'], $db);
        
        // Se pratica completata, completa tutti i task non completati
        if ($nuovoStato === 'completata') {
            $db->query(
                "UPDATE task 
                 SET stato = 'completato', 
                     percentuale_completamento = 100,
                     data_completamento = NOW()
                 WHERE pratica_id = ? AND stato != 'completato'",
                [$praticaId]
            );
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stato aggiornato con successo',
            'nuovo_stato' => $nuovoStato
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore workflow API: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
}

/**
 * Verifica se è possibile cambiare stato
 */
function canChangeState($from, $to, $pratica, $db) {
    // Regole di transizione
    $transitions = [
        'bozza' => ['attiva', 'sospesa'],
        'attiva' => ['in_corso', 'sospesa', 'completata'],
        'in_corso' => ['completata', 'sospesa'],
        'sospesa' => ['attiva', 'in_corso'],
        'completata' => ['fatturata', 'archiviata'],
        'fatturata' => ['archiviata'],
        'archiviata' => [] // Stato finale
    ];
    
    // Verifica transizione permessa
    if (!in_array($to, $transitions[$from] ?? [])) {
        return false;
    }
    
    // Per passare a completata, tutti i task obbligatori devono essere completati
    if ($to === 'completata') {
        $taskIncompleti = $db->selectOne(
            "SELECT COUNT(*) as count 
             FROM task 
             WHERE pratica_id = ? 
             AND is_obbligatorio = 1 
             AND stato != 'completato'",
            [$pratica['id']]
        );
        
        if ($taskIncompleti['count'] > 0) {
            return false;
        }
    }
    
    // Per passare a in_corso, almeno un task deve essere iniziato
    if ($to === 'in_corso' && $from === 'attiva') {
        $taskIniziati = $db->selectOne(
            "SELECT COUNT(*) as count 
             FROM task 
             WHERE pratica_id = ? 
             AND stato IN ('in_corso', 'completato')",
            [$pratica['id']]
        );
        
        if ($taskIniziati['count'] == 0) {
            return false;
        }
    }
    
    return true;
}

/**
 * Log cambio stato
 */
function logStatoChange($praticaId, $from, $to, $userId, $db) {
    // TODO: Implementare tabella log_pratiche per audit trail
    error_log("Pratica $praticaId: stato cambiato da $from a $to da utente $userId");
}

/**
 * Verifica dipendenze task
 */
function checkTaskDependencies($input, $db) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    // Carica task
    $task = $db->selectOne(
        "SELECT * FROM task WHERE id = ?",
        [$taskId]
    );
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task non trovato']);
        return;
    }
    
    // Se ha dipendenze, verifica che siano completate
    if ($task['dipende_da_task_id']) {
        $dipendenza = $db->selectOne(
            "SELECT * FROM task WHERE id = ?",
            [$task['dipende_da_task_id']]
        );
        
        if ($dipendenza && $dipendenza['stato'] !== 'completato') {
            echo json_encode([
                'success' => false,
                'can_start' => false,
                'message' => 'Il task dipende da "' . $dipendenza['titolo'] . '" che non è ancora completato'
            ]);
            return;
        }
    }
    
    echo json_encode([
        'success' => true,
        'can_start' => true,
        'message' => 'Task può essere iniziato'
    ]);
}

/**
 * Ottieni informazioni workflow
 */
function getWorkflowInfo($input, $db) {
    $praticaId = $input['pratica_id'] ?? 0;
    
    if (!$praticaId) {
        echo json_encode(['success' => false, 'message' => 'Pratica ID mancante']);
        return;
    }
    
    // Carica pratica con task
    $pratica = $db->selectOne(
        "SELECT p.*, 
                COUNT(t.id) as totale_task,
                COUNT(CASE WHEN t.stato = 'completato' THEN 1 END) as task_completati,
                COUNT(CASE WHEN t.is_obbligatorio = 1 AND t.stato != 'completato' THEN 1 END) as task_obbligatori_mancanti
         FROM pratiche p
         LEFT JOIN task t ON p.id = t.pratica_id
         WHERE p.id = ?
         GROUP BY p.id",
        [$praticaId]
    );
    
    if (!$pratica) {
        echo json_encode(['success' => false, 'message' => 'Pratica non trovata']);
        return;
    }
    
    // Calcola progress
    $progress = 0;
    if ($pratica['totale_task'] > 0) {
        $progress = round(($pratica['task_completati'] / $pratica['totale_task']) * 100);
    }
    
    // Stati disponibili per transizione
    $statiDisponibili = getAvailableStates($pratica['stato']);
    
    // Verifica possibilità cambio stato
    $canComplete = $pratica['task_obbligatori_mancanti'] == 0;
    
    echo json_encode([
        'success' => true,
        'workflow' => [
            'stato_attuale' => $pratica['stato'],
            'stati_disponibili' => $statiDisponibili,
            'progress' => $progress,
            'task_totali' => $pratica['totale_task'],
            'task_completati' => $pratica['task_completati'],
            'task_obbligatori_mancanti' => $pratica['task_obbligatori_mancanti'],
            'can_complete' => $canComplete,
            'messages' => getWorkflowMessages($pratica)
        ]
    ]);
}

/**
 * Ottieni stati disponibili per transizione
 */
function getAvailableStates($currentState) {
    $transitions = [
        'bozza' => ['attiva', 'sospesa'],
        'attiva' => ['in_corso', 'sospesa'],
        'in_corso' => ['completata', 'sospesa'],
        'sospesa' => ['attiva', 'in_corso'],
        'completata' => ['fatturata'],
        'fatturata' => ['archiviata'],
        'archiviata' => []
    ];
    
    $available = [];
    foreach ($transitions[$currentState] ?? [] as $stato) {
        $config = PRATICHE_STATI[$stato];
        $available[] = [
            'value' => $stato,
            'label' => $config['label'],
            'icon' => $config['icon'],
            'color' => $config['color']
        ];
    }
    
    return $available;
}

/**
 * Messaggi workflow
 */
function getWorkflowMessages($pratica) {
    $messages = [];
    
    if ($pratica['stato'] === 'attiva' && $pratica['totale_task'] == 0) {
        $messages[] = [
            'type' => 'warning',
            'text' => 'Nessun task definito. Aggiungi almeno un task per procedere.'
        ];
    }
    
    if ($pratica['task_obbligatori_mancanti'] > 0 && in_array($pratica['stato'], ['in_corso', 'attiva'])) {
        $messages[] = [
            'type' => 'info',
            'text' => "Ci sono {$pratica['task_obbligatori_mancanti']} task obbligatori da completare."
        ];
    }
    
    if ($pratica['stato'] === 'completata' && !$pratica['data_fatturazione']) {
        $messages[] = [
            'type' => 'success',
            'text' => 'Pratica completata! Puoi procedere con la fatturazione.'
        ];
    }
    
    return $messages;
}