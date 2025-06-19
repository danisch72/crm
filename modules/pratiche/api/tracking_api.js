<?php
/**
 * modules/pratiche/api/tracking_api.php - API Time Tracking
 * 
 * ✅ GESTIONE TRACKING TEMPORALE E INTERRUZIONI
 * 
 * Endpoints:
 * - start_session: Avvia sessione tracking
 * - stop_session: Ferma sessione
 * - pause_session: Pausa sessione
 * - register_interruption: Registra interruzione
 * - get_active_session: Ottieni sessione attiva
 * - get_sessions: Lista sessioni
 * - get_stats: Statistiche tracking
 * - manual_entry: Inserimento manuale tempo
 * - export: Export dati tracking
 */

// Include bootstrap per autenticazione
require_once dirname(dirname(dirname(__DIR__))) . '/core/bootstrap.php';

// Verifica autenticazione
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

// Headers
$isExport = isset($_GET['action']) && $_GET['action'] === 'export';
if (!$isExport) {
    header('Content-Type: application/json');
}

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
    case 'start_session':
        startSession($input, $db, $currentUser);
        break;
        
    case 'stop_session':
        stopSession($input, $db, $currentUser);
        break;
        
    case 'pause_session':
        pauseSession($input, $db, $currentUser);
        break;
        
    case 'register_interruption':
        registerInterruption($input, $db, $currentUser);
        break;
        
    case 'get_active_session':
        getActiveSession($db, $currentUser);
        break;
        
    case 'get_sessions':
        getSessions($input, $db, $currentUser);
        break;
        
    case 'get_stats':
        getTrackingStats($input, $db, $currentUser);
        break;
        
    case 'manual_entry':
        manualTimeEntry($input, $db, $currentUser);
        break;
        
    case 'export':
        exportTracking($_GET, $db, $currentUser);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

/**
 * Avvia nuova sessione tracking
 */
function startSession($input, $db, $user) {
    $taskId = $input['task_id'] ?? 0;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID mancante']);
        return;
    }
    
    try {
        // Verifica se c'è già una sessione attiva
        $activeSession = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE operatore_id = ? 
             AND ora_fine IS NULL",
            [$user['id']]
        );
        
        if ($activeSession) {
            // Auto-chiudi sessione precedente
            stopSession(['session_id' => $activeSession['id']], $db, $user);
        }
        
        // Carica task
        $task = $db->selectOne(
            "SELECT t.*, p.cliente_id 
             FROM task t
             INNER JOIN pratiche p ON t.pratica_id = p.id
             WHERE t.id = ?",
            [$taskId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Aggiorna stato task se necessario
        if ($task['stato'] === 'da_iniziare') {
            $db->update('task', [
                'stato' => 'in_corso',
                'data_inizio' => date('Y-m-d')
            ], 'id = ?', [$taskId]);
        }
        
        // Crea nuova sessione
        $sessionId = $db->insert('tracking_task', [
            'task_id' => $taskId,
            'operatore_id' => $user['id'],
            'cliente_id' => $task['cliente_id'],
            'ora_inizio' => date('Y-m-d H:i:s'),
            'is_attivo' => 1,
            'tipo_attivita' => 'task_work'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sessione tracking avviata',
            'session_id' => $sessionId,
            'start_time' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Errore start session: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'avvio']);
    }
}

/**
 * Ferma sessione tracking
 */
function stopSession($input, $db, $user) {
    $sessionId = $input['session_id'] ?? 0;
    
    if (!$sessionId) {
        // Trova sessione attiva
        $activeSession = $db->selectOne(
            "SELECT id FROM tracking_task 
             WHERE operatore_id = ? 
             AND ora_fine IS NULL 
             ORDER BY id DESC LIMIT 1",
            [$user['id']]
        );
        
        if (!$activeSession) {
            echo json_encode(['success' => false, 'message' => 'Nessuna sessione attiva']);
            return;
        }
        
        $sessionId = $activeSession['id'];
    }
    
    try {
        // Carica sessione
        $session = $db->selectOne(
            "SELECT * FROM tracking_task WHERE id = ? AND operatore_id = ?",
            [$sessionId, $user['id']]
        );
        
        if (!$session || $session['ora_fine']) {
            echo json_encode(['success' => false, 'message' => 'Sessione non valida']);
            return;
        }
        
        // Calcola durata
        $oraFine = date('Y-m-d H:i:s');
        $durata = (strtotime($oraFine) - strtotime($session['ora_inizio'])) / 60;
        
        // Valida durata minima
        if ($durata < TRACKING_CONFIG['min_session_duration'] / 60) {
            echo json_encode([
                'success' => false, 
                'message' => 'Sessione troppo breve (minimo ' . TRACKING_CONFIG['min_session_duration'] . ' secondi)'
            ]);
            return;
        }
        
        // Note obbligatorie per sessioni lunghe
        $note = $input['note'] ?? '';
        if ($durata > TRACKING_CONFIG['require_notes_for_long_sessions'] / 60 && empty($note)) {
            echo json_encode([
                'success' => false,
                'message' => 'Note obbligatorie per sessioni superiori a 4 ore',
                'require_note' => true
            ]);
            return;
        }
        
        // Aggiorna sessione
        $updateData = [
            'ora_fine' => $oraFine,
            'durata_minuti' => round($durata),
            'is_attivo' => 0,
            'is_completato' => 1
        ];
        
        if (!empty($note)) {
            $updateData['note'] = $note;
        }
        
        $db->update('tracking_task', $updateData, 'id = ?', [$sessionId]);
        
        // Aggiorna ore lavorate sul task
        updateTaskHours($session['task_id'], $db);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sessione chiusa',
            'duration_minutes' => round($durata),
            'duration_formatted' => formatDuration(round($durata))
        ]);
        
    } catch (Exception $e) {
        error_log("Errore stop session: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante la chiusura']);
    }
}

/**
 * Pausa sessione
 */
function pauseSession($input, $db, $user) {
    $motivo = $input['motivo'] ?? 'Pausa';
    
    try {
        // Trova sessione attiva
        $activeSession = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE operatore_id = ? 
             AND ora_fine IS NULL 
             ORDER BY id DESC LIMIT 1",
            [$user['id']]
        );
        
        if (!$activeSession) {
            echo json_encode(['success' => false, 'message' => 'Nessuna sessione attiva']);
            return;
        }
        
        // Chiudi sessione corrente con nota pausa
        $oraFine = date('Y-m-d H:i:s');
        $durata = (strtotime($oraFine) - strtotime($activeSession['ora_inizio'])) / 60;
        
        $db->update('tracking_task', [
            'ora_fine' => $oraFine,
            'durata_minuti' => round($durata),
            'note' => 'PAUSA: ' . $motivo,
            'is_attivo' => 0,
            'is_completato' => 0 // Non completato perché in pausa
        ], 'id = ?', [$activeSession['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sessione messa in pausa',
            'paused_minutes' => round($durata)
        ]);
        
    } catch (Exception $e) {
        error_log("Errore pause session: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante la pausa']);
    }
}

/**
 * Registra interruzione
 */
function registerInterruption($input, $db, $user) {
    $tipo = $input['tipo'] ?? '';
    $clienteId = $input['cliente_id'] ?? null;
    $note = $input['note'] ?? '';
    
    if (!isset(TRACKING_CONFIG['interruption_types'][$tipo])) {
        echo json_encode(['success' => false, 'message' => 'Tipo interruzione non valido']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Se c'è una sessione attiva, mettila in pausa
        $activeSession = $db->selectOne(
            "SELECT * FROM tracking_task 
             WHERE operatore_id = ? 
             AND ora_fine IS NULL",
            [$user['id']]
        );
        
        if ($activeSession) {
            pauseSession(['motivo' => TRACKING_CONFIG['interruption_types'][$tipo]], $db, $user);
        }
        
        // Registra interruzione
        $interruptionId = $db->insert('tracking_interruzioni', [
            'operatore_id' => $user['id'],
            'tipo_interruzione' => $tipo,
            'cliente_id' => $clienteId,
            'ora_inizio' => date('Y-m-d H:i:s'),
            'note' => $note
        ]);
        
        // Se richiesto, crea appuntamento
        if (isset($input['crea_appuntamento']) && $input['crea_appuntamento']) {
            $appointmentData = [
                'operatore_id' => $user['id'],
                'cliente_id' => $clienteId,
                'data_appuntamento' => $input['data_appuntamento'],
                'ora_inizio' => date('H:i', strtotime($input['data_appuntamento'])),
                'ora_fine' => date('H:i', strtotime($input['data_appuntamento']) + 3600),
                'oggetto' => 'Follow-up ' . TRACKING_CONFIG['interruption_types'][$tipo],
                'note' => $input['note_appuntamento'] ?? '',
                'created_by' => $user['id']
            ];
            
            $db->insert('appuntamenti', $appointmentData);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Interruzione registrata',
            'interruption_id' => $interruptionId
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore register interruption: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante la registrazione']);
    }
}

/**
 * Ottieni sessione attiva
 */
function getActiveSession($db, $user) {
    try {
        $session = $db->selectOne("
            SELECT 
                tt.*,
                t.titolo as task_titolo,
                p.titolo as pratica_titolo
            FROM tracking_task tt
            INNER JOIN task t ON tt.task_id = t.id
            INNER JOIN pratiche p ON t.pratica_id = p.id
            WHERE tt.operatore_id = ? 
            AND tt.ora_fine IS NULL
            ORDER BY tt.id DESC LIMIT 1
        ", [$user['id']]);
        
        if ($session) {
            // Calcola durata corrente
            $duration = (time() - strtotime($session['ora_inizio'])) / 60;
            $session['current_duration'] = round($duration);
            $session['duration_formatted'] = formatDuration(round($duration));
            
            echo json_encode([
                'success' => true,
                'has_active' => true,
                'session' => $session
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_active' => false
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Errore get active session: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore recupero sessione']);
    }
}

/**
 * Lista sessioni
 */
function getSessions($input, $db, $user) {
    $filters = [
        'task_id' => $input['task_id'] ?? null,
        'pratica_id' => $input['pratica_id'] ?? null,
        'date_from' => $input['date_from'] ?? date('Y-m-01'),
        'date_to' => $input['date_to'] ?? date('Y-m-d'),
        'operatore_id' => $input['operatore_id'] ?? $user['id']
    ];
    
    try {
        $whereConditions = ["tt.operatore_id = ?"];
        $params = [$filters['operatore_id']];
        
        if ($filters['task_id']) {
            $whereConditions[] = "tt.task_id = ?";
            $params[] = $filters['task_id'];
        }
        
        if ($filters['pratica_id']) {
            $whereConditions[] = "t.pratica_id = ?";
            $params[] = $filters['pratica_id'];
        }
        
        $whereConditions[] = "DATE(tt.ora_inizio) >= ?";
        $params[] = $filters['date_from'];
        
        $whereConditions[] = "DATE(tt.ora_inizio) <= ?";
        $params[] = $filters['date_to'];
        
        $whereClause = implode(" AND ", $whereConditions);
        
        $sessions = $db->select("
            SELECT 
                tt.*,
                t.titolo as task_titolo,
                p.titolo as pratica_titolo,
                p.numero_pratica,
                c.ragione_sociale as cliente_nome
            FROM tracking_task tt
            INNER JOIN task t ON tt.task_id = t.id
            INNER JOIN pratiche p ON t.pratica_id = p.id
            LEFT JOIN clienti c ON tt.cliente_id = c.id
            WHERE $whereClause
            ORDER BY tt.ora_inizio DESC
        ", $params);
        
        // Formatta sessioni
        foreach ($sessions as &$session) {
            $session['duration_formatted'] = formatDuration($session['durata_minuti'] ?? 0);
            $session['ora_inizio_formatted'] = date('d/m/Y H:i', strtotime($session['ora_inizio']));
            $session['ora_fine_formatted'] = $session['ora_fine'] ? 
                date('H:i', strtotime($session['ora_fine'])) : 'In corso';
        }
        
        echo json_encode([
            'success' => true,
            'sessions' => $sessions,
            'total' => count($sessions),
            'total_minutes' => array_sum(array_column($sessions, 'durata_minuti'))
        ]);
        
    } catch (Exception $e) {
        error_log("Errore get sessions: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore recupero sessioni']);
    }
}

/**
 * Statistiche tracking
 */
function getTrackingStats($input, $db, $user) {
    $period = $input['period'] ?? 'today'; // today, week, month, year
    $operatoreId = $input['operatore_id'] ?? $user['id'];
    
    // Solo admin può vedere stats di altri
    if ($operatoreId != $user['id'] && !$user['is_admin']) {
        $operatoreId = $user['id'];
    }
    
    try {
        // Calcola date range
        switch ($period) {
            case 'today':
                $dateFrom = date('Y-m-d');
                $dateTo = date('Y-m-d');
                break;
            case 'week':
                $dateFrom = date('Y-m-d', strtotime('monday this week'));
                $dateTo = date('Y-m-d');
                break;
            case 'month':
                $dateFrom = date('Y-m-01');
                $dateTo = date('Y-m-d');
                break;
            case 'year':
                $dateFrom = date('Y-01-01');
                $dateTo = date('Y-m-d');
                break;
            default:
                $dateFrom = date('Y-m-d');
                $dateTo = date('Y-m-d');
        }
        
        // Stats generali
        $generalStats = $db->selectOne("
            SELECT 
                COUNT(DISTINCT tt.id) as sessioni_totali,
                COUNT(DISTINCT tt.task_id) as task_diversi,
                COUNT(DISTINCT t.pratica_id) as pratiche_diverse,
                COUNT(DISTINCT tt.cliente_id) as clienti_diversi,
                COALESCE(SUM(tt.durata_minuti), 0) as minuti_totali,
                COUNT(CASE WHEN tt.tipo_interruzione IS NOT NULL THEN 1 END) as interruzioni
            FROM tracking_task tt
            LEFT JOIN task t ON tt.task_id = t.id
            WHERE tt.operatore_id = ?
            AND DATE(tt.ora_inizio) >= ?
            AND DATE(tt.ora_inizio) <= ?
        ", [$operatoreId, $dateFrom, $dateTo]);
        
        // Stats per tipo pratica
        $statsByType = $db->select("
            SELECT 
                p.tipo_pratica,
                COUNT(DISTINCT tt.id) as sessioni,
                COALESCE(SUM(tt.durata_minuti), 0) as minuti_totali
            FROM tracking_task tt
            INNER JOIN task t ON tt.task_id = t.id
            INNER JOIN pratiche p ON t.pratica_id = p.id
            WHERE tt.operatore_id = ?
            AND DATE(tt.ora_inizio) >= ?
            AND DATE(tt.ora_inizio) <= ?
            GROUP BY p.tipo_pratica
        ", [$operatoreId, $dateFrom, $dateTo]);
        
        // Stats giornaliere (ultimi 7 giorni)
        $dailyStats = $db->select("
            SELECT 
                DATE(ora_inizio) as data,
                COUNT(*) as sessioni,
                COALESCE(SUM(durata_minuti), 0) as minuti_totali
            FROM tracking_task
            WHERE operatore_id = ?
            AND DATE(ora_inizio) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(ora_inizio)
            ORDER BY data DESC
        ", [$operatoreId]);
        
        // Top clienti
        $topClients = $db->select("
            SELECT 
                c.ragione_sociale,
                COUNT(DISTINCT tt.id) as sessioni,
                COALESCE(SUM(tt.durata_minuti), 0) as minuti_totali
            FROM tracking_task tt
            INNER JOIN clienti c ON tt.cliente_id = c.id
            WHERE tt.operatore_id = ?
            AND DATE(tt.ora_inizio) >= ?
            AND DATE(tt.ora_inizio) <= ?
            GROUP BY c.id
            ORDER BY minuti_totali DESC
            LIMIT 5
        ", [$operatoreId, $dateFrom, $dateTo]);
        
        // Formatta risultati
        $generalStats['ore_totali'] = round($generalStats['minuti_totali'] / 60, 2);
        $generalStats['media_sessione'] = $generalStats['sessioni_totali'] > 0 ? 
            round($generalStats['minuti_totali'] / $generalStats['sessioni_totali']) : 0;
        
        echo json_encode([
            'success' => true,
            'period' => $period,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'general' => $generalStats,
            'by_type' => $statsByType,
            'daily' => $dailyStats,
            'top_clients' => $topClients
        ]);
        
    } catch (Exception $e) {
        error_log("Errore get stats: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore calcolo statistiche']);
    }
}

/**
 * Inserimento manuale tempo
 */
function manualTimeEntry($input, $db, $user) {
    if (!TRACKING_CONFIG['allow_manual_entry']) {
        echo json_encode(['success' => false, 'message' => 'Inserimento manuale non permesso']);
        return;
    }
    
    $required = ['task_id', 'data', 'ora_inizio', 'ora_fine', 'descrizione'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo $field obbligatorio"]);
            return;
        }
    }
    
    try {
        // Verifica task
        $task = $db->selectOne(
            "SELECT t.*, p.cliente_id 
             FROM task t
             INNER JOIN pratiche p ON t.pratica_id = p.id
             WHERE t.id = ?",
            [$input['task_id']]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task non trovato']);
            return;
        }
        
        // Calcola durata
        $inizio = strtotime($input['data'] . ' ' . $input['ora_inizio']);
        $fine = strtotime($input['data'] . ' ' . $input['ora_fine']);
        
        if ($fine <= $inizio) {
            echo json_encode(['success' => false, 'message' => 'Ora fine deve essere dopo ora inizio']);
            return;
        }
        
        $durata = ($fine - $inizio) / 60;
        
        // Verifica overlapping
        $overlap = $db->selectOne("
            SELECT COUNT(*) as count
            FROM tracking_task
            WHERE operatore_id = ?
            AND DATE(ora_inizio) = ?
            AND (
                (? >= ora_inizio AND ? < ora_fine) OR
                (? > ora_inizio AND ? <= ora_fine) OR
                (? <= ora_inizio AND ? >= ora_fine)
            )
        ", [
            $user['id'],
            $input['data'],
            date('Y-m-d H:i:s', $inizio),
            date('Y-m-d H:i:s', $inizio),
            date('Y-m-d H:i:s', $fine),
            date('Y-m-d H:i:s', $fine),
            date('Y-m-d H:i:s', $inizio),
            date('Y-m-d H:i:s', $fine)
        ]);
        
        if ($overlap['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Sovrapposizione con altra sessione']);
            return;
        }
        
        // Inserisci sessione manuale
        $sessionId = $db->insert('tracking_task', [
            'task_id' => $input['task_id'],
            'operatore_id' => $user['id'],
            'cliente_id' => $task['cliente_id'],
            'ora_inizio' => date('Y-m-d H:i:s', $inizio),
            'ora_fine' => date('Y-m-d H:i:s', $fine),
            'durata_minuti' => round($durata),
            'note' => 'MANUALE: ' . $input['descrizione'],
            'is_manuale' => 1,
            'is_completato' => 1,
            'tipo_attivita' => 'task_work'
        ]);
        
        // Aggiorna ore task
        updateTaskHours($input['task_id'], $db);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tempo registrato',
            'session_id' => $sessionId,
            'duration_minutes' => round($durata)
        ]);
        
    } catch (Exception $e) {
        error_log("Errore manual entry: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'inserimento']);
    }
}

/**
 * Export tracking data
 */
function exportTracking($params, $db, $user) {
    $praticaId = $params['pratica_id'] ?? 0;
    $format = $params['format'] ?? 'excel';
    
    if (!$praticaId) {
        echo json_encode(['success' => false, 'message' => 'Pratica ID mancante']);
        return;
    }
    
    try {
        // Carica dati
        $tracking = $db->select("
            SELECT 
                tt.*,
                t.titolo as task_titolo,
                CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
                c.ragione_sociale as cliente_nome
            FROM tracking_task tt
            INNER JOIN task t ON tt.task_id = t.id
            INNER JOIN operatori o ON tt.operatore_id = o.id
            LEFT JOIN clienti c ON tt.cliente_id = c.id
            WHERE t.pratica_id = ?
            ORDER BY tt.ora_inizio
        ", [$praticaId]);
        
        if ($format === 'excel') {
            exportToExcel($tracking);
        } else {
            exportToCSV($tracking);
        }
        
    } catch (Exception $e) {
        error_log("Errore export: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'export']);
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Aggiorna ore lavorate su task
 */
function updateTaskHours($taskId, $db) {
    try {
        $totale = $db->selectOne(
            "SELECT COALESCE(SUM(durata_minuti), 0) as minuti_totali 
             FROM tracking_task 
             WHERE task_id = ? 
             AND is_completato = 1",
            [$taskId]
        );
        
        $oreLavorate = round($totale['minuti_totali'] / 60, 2);
        
        $db->update('task', [
            'ore_lavorate' => $oreLavorate,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$taskId]);
        
    } catch (Exception $e) {
        error_log("Errore update task hours: " . $e->getMessage());
    }
}

/**
 * Formatta durata in formato leggibile
 */
function formatDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($mins > 0) {
        return $hours . 'h ' . $mins . 'min';
    }
    
    return $hours . 'h';
}

/**
 * Export to Excel-compatible CSV
 */
function exportToExcel($data) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tracking_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM per Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Data', 'Ora Inizio', 'Ora Fine', 'Durata',
        'Task', 'Cliente', 'Operatore', 'Note', 'Tipo'
    ], ';');
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['ora_inizio'])),
            date('H:i', strtotime($row['ora_inizio'])),
            $row['ora_fine'] ? date('H:i', strtotime($row['ora_fine'])) : 'In corso',
            formatDuration($row['durata_minuti'] ?? 0),
            $row['task_titolo'],
            $row['cliente_nome'] ?? '',
            $row['operatore_nome'],
            $row['note'] ?? '',
            $row['is_manuale'] ? 'Manuale' : 'Automatico'
        ], ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Export to standard CSV
 */
function exportToCSV($data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tracking_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, array_keys($data[0] ?? []));
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
?>