<?php
/**
 * modules/pratiche/tracking.php - Sistema Time Tracking
 * 
 * ✅ INTERFACCIA AVANZATA PER TRACKING TEMPORALE
 * 
 * Features:
 * - Timer in tempo reale con start/stop/pause
 * - Gestione interruzioni categorizzate
 * - Report sessioni di lavoro
 * - Export dati tracking
 * - Statistiche dettagliate
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata dal router)

// Carica task se specificato
$taskId = isset($_GET['task']) ? (int)$_GET['task'] : 0;
$task = null;

if ($taskId) {
    $task = $db->selectOne("
        SELECT t.*, 
               CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM task t
        LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
        WHERE t.id = ? AND t.pratica_id = ?
    ", [$taskId, $pratica['id']]);
    
    if (!$task) {
        $_SESSION['error_message'] = '⚠️ Task non trovato';
        header('Location: /crm/?action=pratiche&view=view&id=' . $pratica['id']);
        exit;
    }
}

// Verifica se c'è una sessione attiva
$activeSession = $db->selectOne("
    SELECT * FROM tracking_task 
    WHERE operatore_id = ? 
    AND ora_fine IS NULL 
    ORDER BY id DESC LIMIT 1
", [$currentUser['id']]);

// Carica sessioni recenti
$sessions = $db->select("
    SELECT 
        tt.*,
        t.titolo as task_titolo,
        p.titolo as pratica_titolo,
        p.numero_pratica
    FROM tracking_task tt
    INNER JOIN task t ON tt.task_id = t.id
    INNER JOIN pratiche p ON t.pratica_id = p.id
    WHERE tt.operatore_id = ?
    AND DATE(tt.ora_inizio) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY tt.ora_inizio DESC
    LIMIT 20
", [$currentUser['id']]);

// Statistiche giornaliere
$statsToday = $db->selectOne("
    SELECT 
        COUNT(*) as sessioni,
        COALESCE(SUM(durata_minuti), 0) as minuti_totali,
        COUNT(DISTINCT task_id) as task_diversi,
        COUNT(CASE WHEN tipo_interruzione IS NOT NULL THEN 1 END) as interruzioni
    FROM tracking_task
    WHERE operatore_id = ?
    AND DATE(ora_inizio) = CURDATE()
", [$currentUser['id']]);

// Carica tutti i task della pratica per selezione
$allTasks = $db->select("
    SELECT id, titolo, stato
    FROM task
    WHERE pratica_id = ?
    AND stato NOT IN ('completato', 'annullato')
    ORDER BY ordine, id
", [$pratica['id']]);

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start_tracking':
            // Chiudi eventuali sessioni aperte
            if ($activeSession) {
                $durataMinuti = round((time() - strtotime($activeSession['ora_inizio'])) / 60);
                $db->update('tracking_task', [
                    'ora_fine' => date('Y-m-d H:i:s'),
                    'durata_minuti' => $durataMinuti,
                    'note' => 'Chiuso automaticamente per nuova sessione'
                ], 'id = ?', [$activeSession['id']]);
            }
            
            // Avvia nuova sessione
            $trackingId = $db->insert('tracking_task', [
                'task_id' => $_POST['task_id'],
                'operatore_id' => $currentUser['id'],
                'ora_inizio' => date('Y-m-d H:i:s'),
                'data_lavoro' => date('Y-m-d')
            ]);
            
            echo json_encode(['success' => true, 'tracking_id' => $trackingId]);
            exit;
            break;
            
        case 'pause_tracking':
            $trackingId = (int)$_POST['tracking_id'];
            $motivo = $_POST['motivo'] ?? '';
            
            // Calcola durata
            $session = $db->selectOne("SELECT * FROM tracking_task WHERE id = ?", [$trackingId]);
            $durataMinuti = round((time() - strtotime($session['ora_inizio'])) / 60);
            
            $db->update('tracking_task', [
                'ora_fine' => date('Y-m-d H:i:s'),
                'durata_minuti' => $durataMinuti,
                'tipo_interruzione' => $motivo ?: null,
                'note' => $_POST['note'] ?? ''
            ], 'id = ? AND operatore_id = ?', [$trackingId, $currentUser['id']]);
            
            // Aggiorna ore lavorate nel task
            if ($session) {
                $db->query("
                    UPDATE task 
                    SET ore_lavorate = ore_lavorate + ? 
                    WHERE id = ?
                ", [$durataMinuti / 60, $session['task_id']]);
            }
            
            echo json_encode(['success' => true]);
            exit;
            break;
            
        case 'stop_tracking':
            $trackingId = (int)$_POST['tracking_id'];
            
            // Calcola durata
            $session = $db->selectOne("SELECT * FROM tracking_task WHERE id = ?", [$trackingId]);
            $durataMinuti = round((time() - strtotime($session['ora_inizio'])) / 60);
            
            $db->update('tracking_task', [
                'ora_fine' => date('Y-m-d H:i:s'),
                'durata_minuti' => $durataMinuti,
                'note' => $_POST['note'] ?? ''
            ], 'id = ? AND operatore_id = ?', [$trackingId, $currentUser['id']]);
            
            // Aggiorna ore lavorate nel task
            if ($session) {
                $db->query("
                    UPDATE task 
                    SET ore_lavorate = ore_lavorate + ? 
                    WHERE id = ?
                ", [$durataMinuti / 60, $session['task_id']]);
            }
            
            echo json_encode(['success' => true]);
            exit;
            break;
            
        case 'manual_entry':
            $taskId = (int)$_POST['task_id'];
            $data = $_POST['data'];
            $oraInizio = $_POST['ora_inizio'];
            $oraFine = $_POST['ora_fine'];
            $note = $_POST['note'] ?? '';
            
            // Calcola durata
            $inizio = new DateTime($data . ' ' . $oraInizio);
            $fine = new DateTime($data . ' ' . $oraFine);
            $durataMinuti = round($fine->getTimestamp() - $inizio->getTimestamp()) / 60;
            
            $db->insert('tracking_task', [
                'task_id' => $taskId,
                'operatore_id' => $currentUser['id'],
                'data_lavoro' => $data,
                'ora_inizio' => $data . ' ' . $oraInizio,
                'ora_fine' => $data . ' ' . $oraFine,
                'durata_minuti' => $durataMinuti,
                'note' => $note,
                'is_manuale' => 1
            ]);
            
            // Aggiorna ore task
            $db->query("
                UPDATE task 
                SET ore_lavorate = ore_lavorate + ? 
                WHERE id = ?
            ", [$durataMinuti / 60, $taskId]);
            
            $_SESSION['success_message'] = '✅ Tempo registrato correttamente';
            header('Location: /crm/?action=pratiche&view=tracking&id=' . $pratica['id']);
            exit;
            break;
    }
}

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
?>

<!-- Container principale -->
<div class="px-3 py-2">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche">Pratiche</a></li>
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>"><?= htmlspecialchars($pratica['titolo']) ?></a></li>
                    <li class="breadcrumb-item active">Time Tracking</li>
                </ol>
            </nav>
            <h4 class="mb-0">
                <i class="bi bi-stopwatch text-primary"></i> Time Tracking
            </h4>
        </div>
        
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.history.back()">
                <i class="bi bi-arrow-left"></i> Indietro
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manualEntryModal">
                <i class="bi bi-pencil-square"></i> Inserimento Manuale
            </button>
        </div>
    </div>
    
    <!-- Timer principale -->
    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm <?= $activeSession ? 'border-warning' : '' ?>">
                <div class="card-body">
                    <?php if ($activeSession): ?>
                        <!-- Timer attivo -->
                        <div class="text-center">
                            <h2 class="display-3 mb-3" id="timer-display">00:00:00</h2>
                            <p class="mb-3">
                                <strong>Task:</strong> 
                                <?php
                                $activeTask = $db->selectOne("SELECT titolo FROM task WHERE id = ?", [$activeSession['task_id']]);
                                echo htmlspecialchars($activeTask['titolo']);
                                ?>
                            </p>
                            <div class="d-flex justify-content-center gap-2">
                                <button class="btn btn-warning" onclick="pauseTracking()">
                                    <i class="bi bi-pause-fill"></i> Pausa
                                </button>
                                <button class="btn btn-danger" onclick="stopTracking()">
                                    <i class="bi bi-stop-fill"></i> Stop
                                </button>
                            </div>
                        </div>
                        
                        <script>
                        // Timer JavaScript
                        const startTime = new Date('<?= $activeSession['ora_inizio'] ?>').getTime();
                        
                        function updateTimer() {
                            const now = new Date().getTime();
                            const elapsed = now - startTime;
                            
                            const hours = Math.floor(elapsed / (1000 * 60 * 60));
                            const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
                            
                            document.getElementById('timer-display').textContent = 
                                String(hours).padStart(2, '0') + ':' +
                                String(minutes).padStart(2, '0') + ':' +
                                String(seconds).padStart(2, '0');
                        }
                        
                        setInterval(updateTimer, 1000);
                        updateTimer(); // Prima chiamata immediata
                        </script>
                        
                    <?php else: ?>
                        <!-- Nessun timer attivo -->
                        <div class="text-center py-4">
                            <i class="bi bi-stopwatch display-1 text-muted mb-3"></i>
                            <h5>Nessun timer attivo</h5>
                            <p class="text-muted mb-4">Seleziona un task per iniziare il tracking</p>
                            
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <select class="form-select mb-3" id="task-select">
                                        <option value="">-- Seleziona un task --</option>
                                        <?php foreach ($allTasks as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $taskId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($t['titolo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button class="btn btn-success btn-lg" onclick="startTracking()" id="start-btn" disabled>
                                        <i class="bi bi-play-fill"></i> Avvia Timer
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Statistiche laterali -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-graph-up"></i> Statistiche Oggi</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <div class="h4 mb-0"><?= round($statsToday['minuti_totali'] / 60, 1) ?>h</div>
                                <small class="text-muted">Ore Totali</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <div class="h4 mb-0"><?= $statsToday['sessioni'] ?></div>
                                <small class="text-muted">Sessioni</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <div class="h4 mb-0"><?= $statsToday['task_diversi'] ?></div>
                                <small class="text-muted">Task</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <div class="h4 mb-0"><?= $statsToday['interruzioni'] ?></div>
                                <small class="text-muted">Interruzioni</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-lightning"></i> Interruzioni Rapide</h6>
                </div>
                <div class="card-body p-2">
                    <div class="d-grid gap-2">
                        <button class="btn btn-sm btn-outline-warning" onclick="quickPause('chiamata_cliente')">
                            <i class="bi bi-telephone"></i> Chiamata Cliente
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="quickPause('pausa_pranzo')">
                            <i class="bi bi-cup-hot"></i> Pausa Pranzo
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="quickPause('emergenza')">
                            <i class="bi bi-exclamation-triangle"></i> Emergenza
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Storico sessioni -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Sessioni Recenti</h6>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTracking()">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pratica/Task</th>
                            <th>Inizio</th>
                            <th>Fine</th>
                            <th>Durata</th>
                            <th>Note</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($session['data_lavoro'])) ?></td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($session['numero_pratica']) ?></small><br>
                                    <?= htmlspecialchars($session['task_titolo']) ?>
                                </td>
                                <td><?= date('H:i', strtotime($session['ora_inizio'])) ?></td>
                                <td><?= $session['ora_fine'] ? date('H:i', strtotime($session['ora_fine'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= sprintf('%dh %dm', floor($session['durata_minuti'] / 60), $session['durata_minuti'] % 60) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($session['tipo_interruzione']): ?>
                                        <span class="badge bg-warning text-dark">
                                            <?= ucfirst(str_replace('_', ' ', $session['tipo_interruzione'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($session['note'] ?? '') ?>
                                </td>
                                <td>
                                    <?php if ($session['is_manuale']): ?>
                                        <span class="badge bg-secondary" title="Inserimento manuale">M</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Inserimento Manuale -->
<div class="modal fade" id="manualEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="manual_entry">
                
                <div class="modal-header">
                    <h5 class="modal-title">Inserimento Manuale Tempo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task *</label>
                        <select name="task_id" class="form-select" required>
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($allTasks as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= htmlspecialchars($t['titolo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data *</label>
                        <input type="date" name="data" class="form-control" 
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ora inizio *</label>
                                <input type="time" name="ora_inizio" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ora fine *</label>
                                <input type="time" name="ora_fine" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Registra Tempo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Variabili globali
const activeSessionId = <?= $activeSession ? $activeSession['id'] : 'null' ?>;

// Enable/disable start button
document.getElementById('task-select')?.addEventListener('change', function() {
    document.getElementById('start-btn').disabled = !this.value;
});

// Start tracking
function startTracking() {
    const taskId = document.getElementById('task-select').value;
    if (!taskId) return;
    
    fetch('/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=start_tracking&task_id=${taskId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Pause tracking
function pauseTracking() {
    const motivo = prompt('Motivo della pausa (opzionale):');
    
    fetch('/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=pause_tracking&tracking_id=${activeSessionId}&motivo=${encodeURIComponent(motivo || '')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Stop tracking
function stopTracking() {
    const note = prompt('Note sessione (opzionale):');
    
    fetch('/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=stop_tracking&tracking_id=${activeSessionId}&note=${encodeURIComponent(note || '')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Quick pause
function quickPause(tipo) {
    if (!activeSessionId) return;
    
    fetch('/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=pause_tracking&tracking_id=${activeSessionId}&motivo=${tipo}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Export tracking data
function exportTracking() {
    window.location.href = '/crm/api/export.php?type=tracking&pratica_id=<?= $pratica['id'] ?>';
}
</script>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/footer.php';
?>