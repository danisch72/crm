<?php
/**
 * api/task.php - API REST per gestione task
 * 
 * Endpoint:
 * - POST /api/task.php?action=create
 * - PUT /api/task.php?action=update
 * - DELETE /api/task.php?action=delete
 * - POST /api/task.php?action=reorder
 * - POST /api/task.php?action=change_state
 */

// Include bootstrap - siamo in modules/pratiche/api/
require_once dirname(dirname(dirname(__DIR__))) . '/core/bootstrap.php';

// Verifica autenticazione
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

// Headers JSON
header('Content-Type: application/json');

// Ottieni user e database
$currentUser = getCurrentUser();
loadDatabase();
$db = Database::getInstance();

// Ottieni action e method
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Gestione input JSON
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Router API
try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            createTask($input, $db, $currentUser);
            break;
            
        case 'update':
            if ($method !== 'PUT' && $method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            updateTask($input, $db, $currentUser);
            break;
            
        case 'delete':
            if ($method !== 'DELETE' && $method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            deleteTask($input, $db, $currentUser);
            break;
            
        case 'reorder':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            reorderTasks($input, $db, $currentUser);
            break;
            
        case 'change_state':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            changeTaskState($input, $db, $currentUser);
            break;
            
        case 'assign':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            assignTask($input, $db, $currentUser);
            break;
            
        case 'complete':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            completeTask($input, $db, $currentUser);
            break;
            
        default:
            throw new Exception('Azione non valida', 400);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

/**
 * Crea nuovo task
 */
function createTask($input, $db, $user) {
    // Validazione
    $required = ['pratica_id', 'titolo'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo $field obbligatorio", 400);
        }
    }
    
    // Verifica pratica
    $pratica = $db->selectOne(
        "SELECT * FROM pratiche WHERE id = ?",
        [$input['pratica_id']]
    );
    
    if (!$pratica) {
        throw new Exception('Pratica non trovata', 404);
    }
    
    // Conta task esistenti per ordine
    $maxOrdine = $db->selectOne(
        "SELECT MAX(ordine) as max_ordine FROM task WHERE pratica_id = ?",
        [$pratica['id']]
    );
    
    // Inserisci task
    $taskId = $db->insert('task', [
        'pratica_id' => $pratica['id'],
        'cliente_id' => $pratica['cliente_id'],
        'titolo' => $input['titolo'],
        'descrizione' => $input['descrizione'] ?? '',
        'ore_stimate' => floatval($input['ore_stimate'] ?? 0),
        'operatore_assegnato_id' => $input['operatore_id'] ?? null,
        'data_scadenza' => $input['data_scadenza'] ?? null,
        'stato' => 'da_fare',
        'ordine' => ($maxOrdine['max_ordine'] ?? 0) + 1
    ]);
    
    // Log attività
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $pratica['id'],
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'action' => 'create_task',
        'entity_type' => 'task',
        'entity_id' => $taskId,
        'new_value' => $input['titolo']
    ]);
    
    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'message' => 'Task creato con successo'
    ]);
}

/**
 * Aggiorna task
 */
function updateTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        throw new Exception('Task ID mancante', 400);
    }
    
    // Verifica task
    $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    // Prepara campi da aggiornare
    $updates = [];
    $allowedFields = ['titolo', 'descrizione', 'ore_stimate', 'data_scadenza', 'operatore_assegnato_id'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[$field] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        throw new Exception('Nessun campo da aggiornare', 400);
    }
    
    $updates['updated_at'] = date('Y-m-d H:i:s');
    
    // Aggiorna
    $db->update('task', $updates, 'id = ?', [$taskId]);
    
    // Log
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $task['pratica_id'],
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'action' => 'update_task',
        'entity_type' => 'task',
        'entity_id' => $taskId,
        'metadata' => json_encode($updates)
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Task aggiornato con successo'
    ]);
}

/**
 * Elimina task
 */
function deleteTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        throw new Exception('Task ID mancante', 400);
    }
    
    // Verifica task
    $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    // Verifica dipendenze
    $dipendenze = $db->selectOne(
        "SELECT COUNT(*) as count FROM task WHERE dipende_da_task_id = ?",
        [$taskId]
    );
    
    if ($dipendenze['count'] > 0) {
        throw new Exception('Impossibile eliminare: altri task dipendono da questo', 400);
    }
    
    // Log prima di eliminare
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $task['pratica_id'],
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'action' => 'delete_task',
        'entity_type' => 'task',
        'entity_id' => $taskId,
        'old_value' => $task['titolo']
    ]);
    
    // Elimina
    $db->delete('task', 'id = ?', [$taskId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Task eliminato con successo'
    ]);
}

/**
 * Riordina task
 */
function reorderTasks($input, $db, $user) {
    $praticaId = $input['pratica_id'] ?? 0;
    $order = $input['order'] ?? [];
    
    if (!$praticaId || empty($order)) {
        throw new Exception('Dati mancanti', 400);
    }
    
    try {
        $db->beginTransaction();
        
        foreach ($order as $index => $taskId) {
            $db->update('task', 
                ['ordine' => $index + 1],
                'id = ? AND pratica_id = ?',
                [$taskId, $praticaId]
            );
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ordine aggiornato'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Errore durante il riordino', 500);
    }
}

/**
 * Cambia stato task
 */
function changeTaskState($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $nuovoStato = $input['stato'] ?? '';
    
    if (!$taskId || !$nuovoStato) {
        throw new Exception('Dati mancanti', 400);
    }
    
    // Stati validi
    $statiValidi = ['da_fare', 'in_corso', 'completato', 'bloccato'];
    if (!in_array($nuovoStato, $statiValidi)) {
        throw new Exception('Stato non valido', 400);
    }
    
    // Verifica task
    $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    $vecchioStato = $task['stato'];
    
    // Aggiorna stato
    $updates = [
        'stato' => $nuovoStato,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Se completato, imposta percentuale 100%
    if ($nuovoStato === 'completato') {
        $updates['percentuale_completamento'] = 100;
    }
    
    $db->update('task', $updates, 'id = ?', [$taskId]);
    
    // Log
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $task['pratica_id'],
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'action' => 'cambio_stato_task',
        'entity_type' => 'task',
        'entity_id' => $taskId,
        'old_value' => $vecchioStato,
        'new_value' => $nuovoStato
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Stato aggiornato',
        'old_state' => $vecchioStato,
        'new_state' => $nuovoStato
    ]);
}

/**
 * Assegna task a operatore
 */
function assignTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $operatoreId = $input['operatore_id'] ?? null;
    
    if (!$taskId) {
        throw new Exception('Task ID mancante', 400);
    }
    
    // Verifica task
    $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    // Se operatore specificato, verifica che esista
    if ($operatoreId) {
        $operatore = $db->selectOne(
            "SELECT id FROM operatori WHERE id = ? AND is_attivo = 1",
            [$operatoreId]
        );
        
        if (!$operatore) {
            throw new Exception('Operatore non valido', 400);
        }
    }
    
    // Aggiorna assegnazione
    $db->update('task', [
        'operatore_assegnato_id' => $operatoreId,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$taskId]);
    
    // Log
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $task['pratica_id'],
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'action' => 'assign_task',
        'entity_type' => 'task',
        'entity_id' => $taskId,
        'old_value' => $task['operatore_assegnato_id'],
        'new_value' => $operatoreId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Task assegnato con successo'
    ]);
}

/**
 * Completa task
 */
function completeTask($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    $note = $input['note'] ?? '';
    
    if (!$taskId) {
        throw new Exception('Task ID mancante', 400);
    }
    
    // Verifica task
    $task = $db->selectOne("SELECT * FROM task WHERE id = ?", [$taskId]);
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    if ($task['stato'] === 'completato') {
        throw new Exception('Task già completato', 400);
    }
    
    try {
        $db->beginTransaction();
        
        // Aggiorna task
        $db->update('task', [
            'stato' => 'completato',
            'percentuale_completamento' => 100,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
        // Chiudi eventuali tracking attivi
        $db->update('tracking_task', [
            'ora_fine' => date('Y-m-d H:i:s'),
            'is_completato' => 1
        ], 'task_id = ? AND ora_fine IS NULL', [$taskId]);
        
        // Log
        $db->insert('pratiche_activity_log', [
            'pratica_id' => $task['pratica_id'],
            'task_id' => $taskId,
            'operatore_id' => $user['id'],
            'action' => 'complete_task',
            'entity_type' => 'task',
            'entity_id' => $taskId,
            'metadata' => json_encode(['note' => $note])
        ]);
        
        // Verifica se tutti i task della pratica sono completati
        $taskIncompleti = $db->selectOne(
            "SELECT COUNT(*) as count FROM task 
             WHERE pratica_id = ? AND stato != 'completato'",
            [$task['pratica_id']]
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task completato con successo',
            'all_completed' => $taskIncompleti['count'] == 0
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Errore durante il completamento', 500);
    }
}
?>