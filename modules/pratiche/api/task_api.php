<?php
/**
 * modules/pratiche/api/task_api.php - API Task Management COMPLETA
 * 
 * ✅ GESTIONE COMPLETA TASK VIA API
 * 
 * Endpoints:
 * - create: Crea nuovo task
 * - update_status: Aggiorna stato task
 * - update_field: Aggiorna campo singolo
 * - update_order: Aggiorna ordinamento
 * - start_tracking: Avvia tracking temporale
 * - complete_task: Completa task
 * - pause_task: Mette in pausa
 * - assign_task: Assegna/riassegna operatore
 * - delete: Elimina task
 * - export: Esporta task pratica
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

// Ottieni dati
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? '';
}

// Carica database
loadDatabase();
$db = Database::getInstance();
$currentUser = getCurrentUser();

// Carica config modulo
require_once dirname(__DIR__) . '/config.php';

// Router azioni
switch ($action) {
    case 'create':
        createTask($input, $db, $currentUser);
        break;
        
    case 'update_status':
        updateTaskStatus($input, $db, $currentUser);
        break;
        
    case 'update_field':
        updateTaskField($input, $db, $currentUser);
        break;
        
    case 'update_order':
        updateTaskOrder($input, $db);
        break;
        
    case 'start_tracking':
        startTracking($input, $db, $currentUser);
        break;
        
    case 'complete_task':
        completeTask($input, $db, $currentUser);
        break;
        
    case 'pause_task':
        pauseTask($input, $db, $currentUser);
        break;
        
    case 'assign_task':
        assignTask($input, $db, $currentUser);
        break;
        
    case 'delete':
        deleteTask($input, $db, $currentUser);
        break;
        
    case 'export':
        exportTasks($_GET, $db, $currentUser);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

/**
 * CREA NUOVO TASK
 */
function createTask($input, $db, $user) {
    try {
        // Validazione campi obbligatori
        if (empty($input['pratica_id']) || empty($input['titolo'])) {
            echo json_encode(['success' => false, 'message' => 'Campi obbligatori mancanti']);
            return;
        }
        
        // Verifica che la pratica esista e l'utente abbia accesso
        $pratica = $db->selectOne(
            "SELECT p.*, c.ragione_sociale as cliente_nome
             FROM pratiche p
             LEFT JOIN clienti c ON p.cliente_id = c.id
             WHERE p.id = ?",
            [$input['pratica_id']]
        );
        
        if (!$pratica) {
            echo json_encode(['success' => false, 'message' => 'Pratica non trovata']);
            return;
        }
        
        // Verifica permessi (solo admin o operatore assegnato)
        if (!$user['is_admin'] && $pratica['operatore_assegnato_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi per questa pratica']);
            return;
        }
        
        // Ottieni il prossimo ordine
        $maxOrdine = $db->selectOne(
            "SELECT MAX(ordine) as max_ordine FROM task WHERE pratica_id = ?",
            [$input['pratica_id']]
        );
        $nuovoOrdine = ($maxOrdine['max_ordine'] ?? 0) + 1;
        
        // Prepara dati per inserimento
        $taskData = [
            'pratica_id' => $input['pratica_id'],
            'titolo' => trim($input['titolo']),
            'descrizione' => trim($input['descrizione'] ?? ''),
            'stato' => 'da_iniziare',
            'operatore_assegnato_id' => $input['operatore_assegnato_id'] ?: null,
            'ore_stimate' => floatval($input['ore_stimate'] ?? 0),
            'data_scadenza' => $input['data_scadenza'] ?: null,
            'is_obbligatorio' => isset($input['is_obbligatorio']) && $input['is_obbligatorio'] ? 1 : 0,
            'dipende_da_task_id' => $input['dipende_da_task_id'] ?: null,
            'ordine' => $nuovoOrdine,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $user['id']
        ];
        
        // Inserisci task
        $taskId = $db->insert('task', $taskData);
        
        if ($taskId) {
            // Log attività
            logActivity('task_created', $taskId, $user['id'], $db);
            
            // Aggiorna progress pratica
            updatePraticaProgress($input['pratica_id'], $db);
            
            // Se assegnato, notifica operatore
            if ($taskData['operatore_assegnato_id'] && $taskData['operatore_assegnato_id'] != $user['id']) {
                notifyOperatorAssignment($taskId, $taskData['operatore_assegnato_id'], $db);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Task creato con successo',
                'task_id' => $taskId
            ]);
            
        } else {
            throw new Exception('Errore durante l\'inserimento del task');
        }
        
    } catch (Exception $e) {
        error_log("Errore creazione task: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Errore durante la creazione del task'
        ]);
    }
}

/**
 * Aggiorna stato task
 */
function updateTaskStatus($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $nuovoStato = $input['stato'] ?? '';
    
    if (!$taskId || !$nuovoStato) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    // Verifica stati validi
    if (!isset(TASK_STATI[$nuovoStato])) {
        echo json_encode(['success' => false, 'message' => 'Stato non valido']);
        return;
    }
    
    try {
        // Carica task
        $task = $db->selectOne(
            "SELECT t.*, p.operatore_assegnato_id as pratica_operatore_id 
             FROM task t 
             INNER JOIN pratiche p ON t.pratica_id = p.id 
             WHERE t.id = ?",
            [$taskId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Verifica permessi
        if (!$user['is_admin'] && 
            $task['operatore_assegnato_id'] != $user['id'] && 
            $task['pratica_operatore_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi']);
            return;
        }
        
        // Verifica dipendenze se si sta iniziando
        if ($nuovoStato === 'in_corso' && $task['dipende_da_task_id']) {
            $dipendenza = $db->selectOne(
                "SELECT stato FROM task WHERE id = ?",
                [$task['dipende_da_task_id']]
            );
            
            if ($dipendenza && $dipendenza['stato'] !== 'completato') {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Il task dipendente non è ancora completato'
                ]);
                return;
            }
        }
        
        // Aggiorna stato
        $updateData = [
            'stato' => $nuovoStato,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Se completato, registra data
        if ($nuovoStato === 'completato') {
            $updateData['data_completamento'] = date('Y-m-d');
        }
        
        $db->update('task', $updateData, 'id = ?', [$taskId]);
        
        // Log attività
        logActivity('task_status_changed', $taskId, $user['id'], $db, [
            'old_status' => $task['stato'],
            'new_status' => $nuovoStato
        ]);
        
        // Aggiorna progress pratica
        updatePraticaProgress($task['pratica_id'], $db);
        
        echo json_encode([
            'success' => true,
            'message' => 'Stato aggiornato con successo'
        ]);
        
    } catch (Exception $e) {
        error_log("Errore update stato task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
}

/**
 * Aggiorna campo singolo
 */
function updateTaskField($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? '';
    
    // Campi modificabili
    $allowedFields = [
        'titolo', 'descrizione', 'ore_stimate', 
        'data_scadenza', 'is_obbligatorio'
    ];
    
    if (!$taskId || !in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'message' => 'Parametri non validi']);
        return;
    }
    
    try {
        // Sanitizza valore
        switch ($field) {
            case 'ore_stimate':
                $value = floatval($value);
                break;
            case 'is_obbligatorio':
                $value = $value ? 1 : 0;
                break;
            case 'data_scadenza':
                $value = $value ?: null;
                break;
        }
        
        $db->update('task', [
            $field => $value,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Campo aggiornato'
        ]);
        
    } catch (Exception $e) {
        error_log("Errore update field: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
}

/**
 * Aggiorna ordine task (drag & drop)
 */
function updateTaskOrder($input, $db) {
    $tasks = $input['tasks'] ?? [];
    
    if (empty($tasks)) {
        echo json_encode(['success' => false, 'message' => 'Nessun task da ordinare']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        foreach ($tasks as $task) {
            $db->update('task', 
                ['ordine' => intval($task['ordine'])],
                'id = ?',
                [$task['id']]
            );
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ordine aggiornato'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore update order: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
}

/**
 * Avvia tracking temporale
 */
function startTracking($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        // Verifica se c'è già una sessione attiva per l'utente
        $activeSession = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE operatore_id = ? 
             AND ora_fine IS NULL",
            [$user['id']]
        );
        
        if ($activeSession) {
            echo json_encode([
                'success' => false, 
                'message' => 'Hai già una sessione di tracking attiva'
            ]);
            return;
        }
        
        // Carica task
        $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Se il task non è in corso, aggiornalo
        if ($task['stato'] === 'da_iniziare') {
            $db->update('task', ['stato' => 'in_corso'], 'id = ?', [$taskId]);
        }
        
        // Crea sessione tracking
        $sessionId = $db->insert('tracking_task', [
            'task_id' => $taskId,
            'operatore_id' => $user['id'],
            'ora_inizio' => date('Y-m-d H:i:s'),
            'is_attivo' => 1
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tracking avviato',
            'session_id' => $sessionId
        ]);
        
    } catch (Exception $e) {
        error_log("Errore start tracking: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'avvio del tracking']);
    }
}

/**
 * Completa task
 */
function completeTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Carica task
        $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Chiudi eventuali tracking attivi
        $activeTracking = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE task_id = ? 
             AND operatore_id = ? 
             AND ora_fine IS NULL",
            [$taskId, $user['id']]
        );
        
        if ($activeTracking) {
            $oraFine = date('Y-m-d H:i:s');
            $durata = (strtotime($oraFine) - strtotime($activeTracking['ora_inizio'])) / 60;
            
            $db->update('tracking_task', [
                'ora_fine' => $oraFine,
                'durata_minuti' => round($durata),
                'is_completato' => 1
            ], 'id = ?', [$activeTracking['id']]);
        }
        
        // Aggiorna task
        $db->update('task', [
            'stato' => 'completato',
            'data_completamento' => date('Y-m-d'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
        // Verifica se tutti i task obbligatori sono completati
        $taskIncompleti = $db->selectOne(
            "SELECT COUNT(*) as count 
             FROM task 
             WHERE pratica_id = ? 
             AND is_obbligatorio = 1 
             AND stato != 'completato'",
            [$task['pratica_id']]
        );
        
        // Se tutti completati, aggiorna pratica
        if ($taskIncompleti && $taskIncompleti['count'] == 0) {
            $db->update('pratiche', [
                'stato' => 'completata',
                'data_completamento' => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$task['pratica_id']]);
        }
        
        // Calcola e aggiorna progress pratica
        updatePraticaProgress($task['pratica_id'], $db);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task completato con successo'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore complete task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante il completamento']);
    }
}

/**
 * Mette in pausa un task
 */
function pauseTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $motivo = $input['motivo'] ?? '';
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        // Trova tracking attivo
        $activeTracking = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE task_id = ? 
             AND operatore_id = ?
             AND ora_fine IS NULL 
             ORDER BY id DESC LIMIT 1",
            [$taskId, $user['id']]
        );
        
        if (!$activeTracking) {
            echo json_encode(['success' => false, 'message' => 'Nessuna sessione attiva']);
            return;
        }
        
        // Chiudi sessione corrente
        $oraFine = date('Y-m-d H:i:s');
        $durata = (strtotime($oraFine) - strtotime($activeTracking['ora_inizio'])) / 60;
        
        $db->update('tracking_task', [
            'ora_fine' => $oraFine,
            'durata_minuti' => round($durata),
            'note' => 'Pausa: ' . $motivo,
            'is_completato' => 0
        ], 'id = ?', [$activeTracking['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task messo in pausa',
            'durata_sessione' => round($durata, 0)
        ]);
        
    } catch (Exception $e) {
        error_log("Errore pause task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante la pausa']);
    }
}

/**
 * Assegna/riassegna task
 */
function assignTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $operatoreId = $input['operatore_id'] ?? 0;
    
    if (!$taskId || !$operatoreId) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    try {
        // Verifica che l'operatore esista
        $operatore = $db->selectOne(
            "SELECT * FROM operatori WHERE id = ? AND is_attivo = 1",
            [$operatoreId]
        );
        
        if (!$operatore) {
            echo json_encode(['success' => false, 'message' => 'Operatore non valido']);
            return;
        }
        
        // Carica task precedente per log
        $oldTask = $db->selectOne("SELECT operatore_assegnato_id FROM task WHERE id = ?", [$taskId]);
        
        // Aggiorna assegnazione
        $db->update('task', [
            'operatore_assegnato_id' => $operatoreId,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
        // Log cambio assegnazione
        logActivity('task_reassigned', $taskId, $user['id'], $db, [
            'old_operator' => $oldTask['operatore_assegnato_id'],
            'new_operator' => $operatoreId
        ]);
        
        // Notifica nuovo operatore
        if ($operatoreId != $user['id']) {
            notifyOperatorAssignment($taskId, $operatoreId, $db);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Task riassegnato con successo'
        ]);
        
    } catch (Exception $e) {
        error_log("Errore assign task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'assegnazione']);
    }
}

/**
 * Elimina task
 */
function deleteTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Carica task per verifica permessi
        $task = $db->selectOne(
            "SELECT t.*, p.operatore_assegnato_id as pratica_operatore_id 
             FROM task t 
             INNER JOIN pratiche p ON t.pratica_id = p.id 
             WHERE t.id = ?",
            [$taskId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Solo admin o operatore responsabile pratica possono eliminare
        if (!$user['is_admin'] && $task['pratica_operatore_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi per eliminare']);
            return;
        }
        
        // Verifica che non ci siano dipendenze
        $dipendenze = $db->selectOne(
            "SELECT COUNT(*) as count FROM task WHERE dipende_da_task_id = ?",
            [$taskId]
        );
        
        if ($dipendenze['count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Impossibile eliminare: altri task dipendono da questo'
            ]);
            return;
        }
        
        // Elimina tracking associati
        $db->delete('tracking_task', 'task_id = ?', [$taskId]);
        
        // Elimina task
        $db->delete('task', 'id = ?', [$taskId]);
        
        // Riordina task rimanenti
        $db->execute(
            "UPDATE task SET ordine = ordine - 1 
             WHERE pratica_id = ? AND ordine > ?",
            [$task['pratica_id'], $task['ordine']]
        );
        
        // Aggiorna progress pratica
        updatePraticaProgress($task['pratica_id'], $db);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task eliminato con successo'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore delete task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione']);
    }
}

/**
 * Esporta task pratica
 */
function exportTasks($params, $db, $user) {
    $praticaId = $params['pratica_id'] ?? 0;
    
    if (!$praticaId) {
        echo json_encode(['success' => false, 'message' => 'Pratica ID mancante']);
        return;
    }
    
    try {
        // Carica pratica e task
        $pratica = $db->selectOne(
            "SELECT p.*, c.ragione_sociale 
             FROM pratiche p 
             LEFT JOIN clienti c ON p.cliente_id = c.id 
             WHERE p.id = ?",
            [$praticaId]
        );
        
        if (!$pratica) {
            echo json_encode(['success' => false, 'message' => 'Pratica non trovata']);
            return;
        }
        
        $tasks = $db->select("
            SELECT 
                t.*,
                CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
                (SELECT SUM(durata_minuti) FROM tracking_task WHERE task_id = t.id) as minuti_totali
            FROM task t
            LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
            WHERE t.pratica_id = ?
            ORDER BY t.ordine
        ", [$praticaId]);
        
        // Genera CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="task_pratica_' . $pratica['numero_pratica'] . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM per Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'ID', 'Titolo', 'Descrizione', 'Stato', 
            'Operatore', 'Ore Stimate', 'Ore Lavorate', 
            'Data Scadenza', 'Completato', 'Obbligatorio'
        ], ';');
        
        // Dati
        foreach ($tasks as $task) {
            fputcsv($output, [
                $task['id'],
                $task['titolo'],
                $task['descrizione'],
                TASK_STATI[$task['stato']]['label'],
                $task['operatore_nome'] ?? 'Non assegnato',
                $task['ore_stimate'],
                round(($task['minuti_totali'] ?? 0) / 60, 2),
                $task['data_scadenza'] ? date('d/m/Y', strtotime($task['data_scadenza'])) : '',
                $task['data_completamento'] ? date('d/m/Y', strtotime($task['data_completamento'])) : '',
                $task['is_obbligatorio'] ? 'Sì' : 'No'
            ], ';');
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log("Errore export tasks: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'export']);
    }
}

// ============================================
// FUNZIONI HELPER
// ============================================

/**
 * Aggiorna progress pratica
 */
function updatePraticaProgress($praticaId, $db) {
    try {
        // Calcola statistiche
        $stats = $db->selectOne("
            SELECT 
                COUNT(*) as totali,
                COUNT(CASE WHEN stato = 'completato' THEN 1 END) as completati,
                SUM(ore_stimate) as ore_stimate_totali,
                SUM(CASE WHEN stato = 'completato' THEN ore_stimate ELSE 0 END) as ore_completate
            FROM task
            WHERE pratica_id = ?
        ", [$praticaId]);
        
        $progress = 0;
        if ($stats['totali'] > 0) {
            $progress = round(($stats['completati'] / $stats['totali']) * 100);
        }
        
        // Aggiorna pratica
        $db->update('pratiche', [
            'progress_percentuale' => $progress,
            'task_totali' => $stats['totali'],
            'task_completati' => $stats['completati'],
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$praticaId]);
        
    } catch (Exception $e) {
        error_log("Errore update progress: " . $e->getMessage());
    }
}

/**
 * Log attività
 */
function logActivity($action, $taskId, $userId, $db, $data = []) {
    try {
        // TODO: Implementare tabella activity_log
        error_log("Activity: $action on task $taskId by user $userId");
    } catch (Exception $e) {
        error_log("Errore log activity: " . $e->getMessage());
    }
}

/**
 * Notifica assegnazione operatore
 */
function notifyOperatorAssignment($taskId, $operatoreId, $db) {
    try {
        // TODO: Implementare sistema notifiche
        error_log("Notifica assegnazione task $taskId a operatore $operatoreId");
    } catch (Exception $e) {
        error_log("Errore notifica: " . $e->getMessage());
    }
}
?>