<?php
/**
 * modules/pratiche/api/task_api.php - API Gestione Task
 * 
 * ✅ API REST PER OPERAZIONI SUI TASK
 * 
 * Endpoints:
 * - start_task: Avvia un task
 * - complete_task: Completa un task  
 * - pause_task: Mette in pausa un task
 * - update_task: Aggiorna informazioni task
 * - delete_task: Elimina un task
 * - reassign_task: Riassegna task con conferma
 * - reorder_tasks: Riordina i task
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
    case 'start_task':
        startTask($input, $db, $currentUser);
        break;
        
    case 'complete_task':
        completeTask($input, $db, $currentUser);
        break;
        
    case 'pause_task':
        pauseTask($input, $db, $currentUser);
        break;
        
    case 'update_task':
        updateTask($input, $db, $currentUser);
        break;
        
    case 'delete_task':
        deleteTask($input, $db, $currentUser);
        break;
        
    case 'reassign_task':
        reassignTask($input, $db, $currentUser);
        break;
        
    case 'reorder_tasks':
        reorderTasks($input, $db, $currentUser);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

/**
 * Avvia un task
 */
function startTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        // Carica task
        $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Verifica permessi
        if (!$user['is_admin'] && $task['operatore_assegnato_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi per questo task']);
            return;
        }
        
        // Verifica dipendenze
        if ($task['dipende_da_task_id']) {
            $dipendenza = $db->selectOne(
                "SELECT stato FROM task WHERE id = ?", 
                [$task['dipende_da_task_id']]
            );
            
            if ($dipendenza && $dipendenza['stato'] !== 'completato') {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Completa prima il task precedente'
                ]);
                return;
            }
        }
        
        // Verifica stato attuale
        if ($task['stato'] === 'completato') {
            echo json_encode(['success' => false, 'message' => 'Task già completato']);
            return;
        }
        
        $db->beginTransaction();
        
        // Aggiorna stato task
        $db->update('task', [
            'stato' => 'in_corso',
            'data_inizio' => $task['data_inizio'] ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
        // Se è il primo task iniziato, aggiorna anche la pratica
        $pratica = $db->selectOne(
            "SELECT stato FROM pratiche WHERE id = ?", 
            [$task['pratica_id']]
        );
        
        if ($pratica && $pratica['stato'] === 'da_iniziare') {
            $db->update('pratiche', [
                'stato' => 'in_corso',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$task['pratica_id']]);
        }
        
        // Crea sessione di tracking
        $trackingId = $db->insert('tracking_task', [
            'task_id' => $taskId,
            'operatore_id' => $user['id'],
            'data_lavoro' => date('Y-m-d'),
            'ora_inizio' => date('Y-m-d H:i:s'),
            'is_completato' => 0
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task avviato con successo',
            'tracking_id' => $trackingId
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore start task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'avvio del task']);
    }
}

/**
 * Completa un task
 */
function completeTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $note = $input['note'] ?? '';
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        // Carica task
        $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Verifica permessi
        if (!$user['is_admin'] && $task['operatore_assegnato_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi per questo task']);
            return;
        }
        
        $db->beginTransaction();
        
        // Chiudi eventuali tracking aperti
        $activeTracking = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE task_id = ? AND ora_fine IS NULL 
             ORDER BY id DESC LIMIT 1",
            [$taskId]
        );
        
        if ($activeTracking) {
            $oraFine = date('Y-m-d H:i:s');
            $durata = (strtotime($oraFine) - strtotime($activeTracking['ora_inizio'])) / 60;
            
            $db->update('tracking_task', [
                'ora_fine' => $oraFine,
                'ore_lavorate' => round($durata / 60, 2),
                'note' => $note,
                'is_completato' => 1
            ], 'id = ?', [$activeTracking['id']]);
        }
        
        // Calcola ore totali lavorate
        $oreTotali = $db->selectOne(
            "SELECT SUM(ore_lavorate) as totale 
             FROM tracking_task 
             WHERE task_id = ?",
            [$taskId]
        );
        
        // Aggiorna task
        $db->update('task', [
            'stato' => 'completato',
            'data_completamento' => date('Y-m-d'),
            'ore_lavorate' => $oreTotali['totale'] ?? 0,
            'percentuale_completamento' => 100,
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
            'ore_lavorate' => round($durata / 60, 2),
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
 * Aggiorna informazioni task
 */
function updateTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? '';
    
    if (!$taskId || !$field) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    // Campi modificabili
    $allowedFields = [
        'titolo', 'descrizione', 'ore_stimate', 
        'data_scadenza', 'priorita', 'percentuale_completamento'
    ];
    
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'message' => 'Campo non modificabile']);
        return;
    }
    
    try {
        // Verifica permessi
        $task = $db->selectOne(
            "SELECT t.*, p.operatore_responsabile_id 
             FROM task t
             JOIN pratiche p ON t.pratica_id = p.id
             WHERE t.id = ?",
            [$taskId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        if (!$user['is_admin'] && 
            $task['operatore_assegnato_id'] != $user['id'] &&
            $task['operatore_responsabile_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi']);
            return;
        }
        
        // Aggiorna
        $db->update('task', [
            $field => $value,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task aggiornato'
        ]);
        
    } catch (Exception $e) {
        error_log("Errore update task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento']);
    }
}

/**
 * Elimina un task
 */
function deleteTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        // Verifica permessi
        $task = $db->selectOne(
            "SELECT t.*, p.operatore_responsabile_id 
             FROM task t
             JOIN pratiche p ON t.pratica_id = p.id
             WHERE t.id = ?",
            [$taskId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        if (!$user['is_admin'] && $task['operatore_responsabile_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Solo admin o responsabile possono eliminare']);
            return;
        }
        
        // Non eliminare se è l'unico task obbligatorio
        if ($task['is_obbligatorio']) {
            $altriObbligatori = $db->selectOne(
                "SELECT COUNT(*) as count 
                 FROM task 
                 WHERE pratica_id = ? 
                 AND id != ? 
                 AND is_obbligatorio = 1",
                [$task['pratica_id'], $taskId]
            );
            
            if ($altriObbligatori['count'] == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Non puoi eliminare l\'unico task obbligatorio'
                ]);
                return;
            }
        }
        
        $db->beginTransaction();
        
        // Elimina task (tracking eliminati a cascata)
        $db->delete('task', 'id = ?', [$taskId]);
        
        // Aggiorna progress pratica
        updatePraticaProgress($task['pratica_id'], $db);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task eliminato'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore delete task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione']);
    }
}

/**
 * Riassegna task con richiesta conferma
 */
function reassignTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $nuovoOperatoreId = $input['nuovo_operatore_id'] ?? 0;
    $motivo = $input['motivo'] ?? '';
    $confermaRichiesta = $input['conferma'] ?? false;
    
    if (!$taskId || !$nuovoOperatoreId) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    try {
        $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Se non è ancora stata richiesta conferma
        if (!$confermaRichiesta) {
            // Aggiorna task con richiesta di conferma
            $db->update('task', [
                'richiede_conferma' => 1,
                'conferma_richiesta_a' => $nuovoOperatoreId,
                'conferma_richiesta_da' => $user['id'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$taskId]);
            
            // TODO: Invia notifica al nuovo operatore
            
            echo json_encode([
                'success' => true,
                'message' => 'Richiesta di riassegnazione inviata',
                'requires_confirmation' => true
            ]);
            
        } else {
            // Conferma ricevuta, procedi con riassegnazione
            if ($user['id'] != $task['conferma_richiesta_a']) {
                echo json_encode(['success' => false, 'message' => 'Solo l\'operatore designato può confermare']);
                return;
            }
            
            $db->update('task', [
                'operatore_assegnato_id' => $nuovoOperatoreId,
                'richiede_conferma' => 0,
                'conferma_richiesta_a' => null,
                'conferma_richiesta_da' => null,
                'conferma_data' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$taskId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Task riassegnato con successo'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Errore reassign task: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante la riassegnazione']);
    }
}

/**
 * Riordina i task
 */
function reorderTasks($input, $db, $user) {
    $praticaId = $input['pratica_id'] ?? 0;
    $taskOrder = $input['task_order'] ?? [];
    
    if (!$praticaId || empty($taskOrder)) {
        echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
        return;
    }
    
    try {
        // Verifica permessi sulla pratica
        $pratica = $db->selectOne(
            "SELECT operatore_responsabile_id FROM pratiche WHERE id = ?",
            [$praticaId]
        );
        
        if (!$pratica) {
            echo json_encode(['success' => false, 'message' => 'Pratica non trovata']);
            return;
        }
        
        if (!$user['is_admin'] && $pratica['operatore_responsabile_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'Non hai i permessi']);
            return;
        }
        
        $db->beginTransaction();
        
        // Aggiorna ordine di ogni task
        foreach ($taskOrder as $index => $taskId) {
            $db->update('task', [
                'ordine' => $index,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ? AND pratica_id = ?', [$taskId, $praticaId]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ordine aggiornato'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore reorder tasks: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante il riordino']);
    }
}

/**
 * Helper: Aggiorna progress pratica
 */
function updatePraticaProgress($praticaId, $db) {
    $stats = $db->selectOne("
        SELECT 
            COUNT(*) as totali,
            COUNT(CASE WHEN stato = 'completato' THEN 1 END) as completati,
            SUM(ore_stimate) as ore_stimate,
            SUM(ore_lavorate) as ore_lavorate
        FROM task 
        WHERE pratica_id = ?
    ", [$praticaId]);
    
    $progress = $stats['totali'] > 0 
        ? round(($stats['completati'] / $stats['totali']) * 100) 
        : 0;
    
    $db->update('pratiche', [
        'progress_percentage' => $progress,
        'totale_ore_stimate' => $stats['ore_stimate'] ?? 0,
        'totale_ore_lavorate' => $stats['ore_lavorate'] ?? 0,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$praticaId]);
}