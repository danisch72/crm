<?php
/**
 * api/tracking.php - API REST per time tracking
 * 
 * Endpoint:
 * - POST /api/tracking.php?action=start
 * - POST /api/tracking.php?action=pause
 * - POST /api/tracking.php?action=stop
 * - GET /api/tracking.php?action=current
 * - POST /api/tracking.php?action=manual_entry
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
        case 'start':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            startTracking($input, $db, $currentUser);
            break;
            
        case 'pause':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            pauseTracking($input, $db, $currentUser);
            break;
            
        case 'stop':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            stopTracking($input, $db, $currentUser);
            break;
            
        case 'current':
            if ($method !== 'GET') {
                throw new Exception('Metodo non permesso', 405);
            }
            getCurrentTracking($db, $currentUser);
            break;
            
        case 'manual_entry':
            if ($method !== 'POST') {
                throw new Exception('Metodo non permesso', 405);
            }
            manualEntry($input, $db, $currentUser);
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
 * Avvia nuova sessione di tracking
 */
function startTracking($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        throw new Exception('Task ID mancante', 400);
    }
    
    // Verifica task
    $task = $db->selectOne(
        "SELECT t.*, p.titolo as pratica_titolo 
         FROM task t
         JOIN pratiche p ON t.pratica_id = p.id
         WHERE t.id = ?",
        [$taskId]
    );
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    // Verifica se c'è già una sessione attiva
    $activeSession = $db->selectOne(
        "SELECT * FROM tracking_task 
         WHERE operatore_id = ? 
         AND ora_fine IS NULL",
        [$user['id']]
    );
    
    if ($activeSession) {
        // Chiudi sessione precedente
        $durataMinuti = round((time() - strtotime($activeSession['ora_inizio'])) / 60);
        
        $db->update('tracking_task', [
            'ora_fine' => date('Y-m-d H:i:s'),
            'durata_minuti' => $durataMinuti,
            'note' => 'Chiusa automaticamente per nuova sessione'
        ], 'id = ?', [$activeSession['id']]);
    }
    
    // Crea nuova sessione
    $sessionId = $db->insert('tracking_task', [
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'ora_inizio' => date('Y-m-d H:i:s'),
        'data_lavoro' => date('Y-m-d'),
        'tipo_attivita' => 'task_work',
        'is_attivo' => 1
    ]);
    
    // Se il task non è in corso, aggiornalo
    if ($task['stato'] === 'da_fare') {
        $db->update('task', 
            ['stato' => 'in_corso'], 
            'id = ?', 
            [$taskId]
        );
    }
    
    // Log attività
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $task['pratica_id'],
        'task_id' => $taskId,
        'operatore_id' => $user['id'],
        'action' => 'start_tracking',
        'entity_type' => 'tracking',
        'entity_id' => $sessionId
    ]);
    
    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'task' => [
            'id' => $task['id'],
            'titolo' => $task['titolo'],
            'pratica' => $task['pratica_titolo']
        ],
        'start_time' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Metti in pausa tracking
 */
function pauseTracking($input, $db, $user) {
    $sessionId = $input['session_id'] ?? 0;
    $motivo = $input['motivo'] ?? '';
    $note = $input['note'] ?? '';
    
    if (!$sessionId) {
        throw new Exception('Session ID mancante', 400);
    }
    
    // Verifica sessione
    $session = $db->selectOne(
        "SELECT * FROM tracking_task 
         WHERE id = ? 
         AND operatore_id = ? 
         AND ora_fine IS NULL",
        [$sessionId, $user['id']]
    );
    
    if (!$session) {
        throw new Exception('Sessione non trovata o già chiusa', 404);
    }
    
    // Calcola durata
    $durataMinuti = round((time() - strtotime($session['ora_inizio'])) / 60);
    
    // Aggiorna sessione
    $db->update('tracking_task', [
        'ora_fine' => date('Y-m-d H:i:s'),
        'durata_minuti' => $durataMinuti,
        'tipo_interruzione' => $motivo ?: null,
        'note' => $note,
        'is_attivo' => 0
    ], 'id = ?', [$sessionId]);
    
    // Aggiorna ore lavorate nel task
    $db->query(
        "UPDATE task 
         SET ore_lavorate = ore_lavorate + ? 
         WHERE id = ?",
        [$durataMinuti / 60, $session['task_id']]
    );
    
    echo json_encode([
        'success' => true,
        'duration_minutes' => $durataMinuti,
        'duration_formatted' => sprintf('%dh %dm', floor($durataMinuti / 60), $durataMinuti % 60)
    ]);
}

/**
 * Ferma tracking
 */
function stopTracking($input, $db, $user) {
    $sessionId = $input['session_id'] ?? 0;
    $note = $input['note'] ?? '';
    
    if (!$sessionId) {
        throw new Exception('Session ID mancante', 400);
    }
    
    // Verifica sessione
    $session = $db->selectOne(
        "SELECT * FROM tracking_task 
         WHERE id = ? 
         AND operatore_id = ? 
         AND ora_fine IS NULL",
        [$sessionId, $user['id']]
    );
    
    if (!$session) {
        throw new Exception('Sessione non trovata o già chiusa', 404);
    }
    
    // Calcola durata
    $durataMinuti = round((time() - strtotime($session['ora_inizio'])) / 60);
    
    // Aggiorna sessione
    $db->update('tracking_task', [
        'ora_fine' => date('Y-m-d H:i:s'),
        'durata_minuti' => $durataMinuti,
        'note' => $note,
        'is_attivo' => 0,
        'is_completato' => 1
    ], 'id = ?', [$sessionId]);
    
    // Aggiorna ore lavorate nel task
    $db->query(
        "UPDATE task 
         SET ore_lavorate = ore_lavorate + ? 
         WHERE id = ?",
        [$durataMinuti / 60, $session['task_id']]
    );
    
    // Log attività
    $task = $db->selectOne("SELECT pratica_id FROM task WHERE id = ?", [$session['task_id']]);
    
    $db->insert('pratiche_activity_log', [
        'pratica_id' => $task['pratica_id'],
        'task_id' => $session['task_id'],
        'operatore_id' => $user['id'],
        'action' => 'stop_tracking',
        'entity_type' => 'tracking',
        'entity_id' => $sessionId,
        'metadata' => json_encode([
            'durata_minuti' => $durataMinuti,
            'note' => $note
        ])
    ]);
    
    echo json_encode([
        'success' => true,
        'duration_minutes' => $durataMinuti,
        'duration_formatted' => sprintf('%dh %dm', floor($durataMinuti / 60), $durataMinuti % 60)
    ]);
}

/**
 * Ottieni sessione tracking attiva
 */
function getCurrentTracking($db, $user) {
    $session = $db->selectOne(
        "SELECT 
            tt.*,
            t.titolo as task_titolo,
            p.titolo as pratica_titolo,
            p.numero_pratica
         FROM tracking_task tt
         JOIN task t ON tt.task_id = t.id
         JOIN pratiche p ON t.pratica_id = p.id
         WHERE tt.operatore_id = ? 
         AND tt.ora_fine IS NULL
         ORDER BY tt.id DESC 
         LIMIT 1",
        [$user['id']]
    );
    
    if ($session) {
        // Calcola durata corrente
        $durataCorrente = round((time() - strtotime($session['ora_inizio'])) / 60);
        
        echo json_encode([
            'active' => true,
            'session' => [
                'id' => $session['id'],
                'task_id' => $session['task_id'],
                'task_titolo' => $session['task_titolo'],
                'pratica_titolo' => $session['pratica_titolo'],
                'numero_pratica' => $session['numero_pratica'],
                'start_time' => $session['ora_inizio'],
                'duration_minutes' => $durataCorrente,
                'duration_formatted' => sprintf('%dh %dm', floor($durataCorrente / 60), $durataCorrente % 60)
            ]
        ]);
    } else {
        echo json_encode([
            'active' => false
        ]);
    }
}

/**
 * Inserimento manuale tempo
 */
function manualEntry($input, $db, $user) {
    // Validazione
    $required = ['task_id', 'data', 'ora_inizio', 'ora_fine'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo $field obbligatorio", 400);
        }
    }
    
    $taskId = $input['task_id'];
    $data = $input['data'];
    $oraInizio = $input['ora_inizio'];
    $oraFine = $input['ora_fine'];
    $note = $input['note'] ?? '';
    
    // Verifica task
    $task = $db->selectOne(
        "SELECT t.*, p.id as pratica_id 
         FROM task t
         JOIN pratiche p ON t.pratica_id = p.id
         WHERE t.id = ?",
        [$taskId]
    );
    
    if (!$task) {
        throw new Exception('Task non trovato', 404);
    }
    
    // Calcola durata
    $inizio = new DateTime($data . ' ' . $oraInizio);
    $fine = new DateTime($data . ' ' . $oraFine);
    
    if ($fine <= $inizio) {
        throw new Exception('Ora fine deve essere successiva a ora inizio', 400);
    }
    
    $durataMinuti = round(($fine->getTimestamp() - $inizio->getTimestamp()) / 60);
    
    // Verifica sovrapposizioni
    $sovrapposizioni = $db->select(
        "SELECT * FROM tracking_task 
         WHERE operatore_id = ?
         AND data_lavoro = ?
         AND (
            (? >= ora_inizio AND ? < ora_fine) OR
            (? > ora_inizio AND ? <= ora_fine) OR
            (? <= ora_inizio AND ? >= ora_fine)
         )",
        [
            $user['id'], $data,
            $inizio->format('Y-m-d H:i:s'), $inizio->format('Y-m-d H:i:s'),
            $fine->format('Y-m-d H:i:s'), $fine->format('Y-m-d H:i:s'),
            $inizio->format('Y-m-d H:i:s'), $fine->format('Y-m-d H:i:s')
        ]
    );
    
    if (count($sovrapposizioni) > 0) {
        throw new Exception('Sovrapposizione con altre sessioni di tracking', 400);
    }
    
    try {
        $db->beginTransaction();
        
        // Inserisci tracking
        $trackingId = $db->insert('tracking_task', [
            'task_id' => $taskId,
            'operatore_id' => $user['id'],
            'data_lavoro' => $data,
            'ora_inizio' => $inizio->format('Y-m-d H:i:s'),
            'ora_fine' => $fine->format('Y-m-d H:i:s'),
            'durata_minuti' => $durataMinuti,
            'tipo_attivita' => 'task_work',
            'note' => $note,
            'is_manuale' => 1,
            'is_completato' => 1
        ]);
        
        // Aggiorna ore task
        $db->query(
            "UPDATE task 
             SET ore_lavorate = ore_lavorate + ? 
             WHERE id = ?",
            [$durataMinuti / 60, $taskId]
        );
        
        // Log attività
        $db->insert('pratiche_activity_log', [
            'pratica_id' => $task['pratica_id'],
            'task_id' => $taskId,
            'operatore_id' => $user['id'],
            'action' => 'manual_tracking',
            'entity_type' => 'tracking',
            'entity_id' => $trackingId,
            'metadata' => json_encode([
                'data' => $data,
                'durata_minuti' => $durataMinuti
            ])
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'tracking_id' => $trackingId,
            'duration_minutes' => $durataMinuti,
            'duration_formatted' => sprintf('%dh %dm', floor($durataMinuti / 60), $durataMinuti % 60)
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Errore durante l\'inserimento', 500);
    }
}
?>