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

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Tracking - <?= htmlspecialchars($pratica['titolo']) ?></title>
    
    <style>
        /* Layout principale */
        .tracking-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        .tracking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tracking-main {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .tracking-main {
                grid-template-columns: 1fr;
            }
        }
        
        /* Timer principale */
        .timer-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .timer-display {
            font-size: 4rem;
            font-weight: 300;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
            color: #1f2937;
            margin: 1rem 0;
            letter-spacing: 0.1em;
        }
        
        .timer-display.active {
            color: #10b981;
        }
        
        .timer-display.paused {
            color: #f59e0b;
        }
        
        .timer-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .timer-btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .timer-btn.primary {
            background: #007849;
            color: white;
        }
        
        .timer-btn.primary:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,120,73,0.3);
        }
        
        .timer-btn.secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .timer-btn.secondary:hover {
            background: #e5e7eb;
        }
        
        .timer-btn.danger {
            background: #ef4444;
            color: white;
        }
        
        .timer-btn.danger:hover {
            background: #dc2626;
        }
        
        /* Task selector */
        .task-selector {
            margin: 2rem 0;
            text-align: left;
        }
        
        .task-selector label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .task-selector select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: white;
        }
        
        /* Interruzioni rapide */
        .interruptions-panel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .interruptions-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .interruption-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        
        .interruption-btn {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }
        
        .interruption-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-1px);
        }
        
        /* Sessioni recenti */
        .sessions-list {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-top: 2rem;
        }
        
        .sessions-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .session-item:last-child {
            border-bottom: none;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-task {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.875rem;
        }
        
        .session-time {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .session-duration {
            font-weight: 600;
            color: #10b981;
            font-size: 0.875rem;
        }
        
        /* Statistiche */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #007849;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Modal interruzione */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <div class="tracking-container">
                <!-- Header -->
                <div class="tracking-header">
                    <div>
                        <h1 style="font-size: 1.5rem; font-weight: 600; color: #1f2937;">
                            Time Tracking
                        </h1>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">
                            <?= htmlspecialchars($pratica['titolo']) ?> - 
                            #<?= htmlspecialchars($pratica['numero_pratica']) ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" 
                           class="timer-btn secondary">
                            ← Torna alla pratica
                        </a>
                        <button class="timer-btn secondary" onclick="exportTracking()">
                            📊 Export
                        </button>
                    </div>
                </div>
                
                <!-- Main content -->
                <div class="tracking-main">
                    <!-- Left column -->
                    <div>
                        <!-- Timer card -->
                        <div class="timer-card">
                            <div class="timer-display <?= $activeSession ? 'active' : '' ?>" id="timerDisplay">
                                00:00:00
                            </div>
                            
                            <!-- Task selector -->
                            <div class="task-selector">
                                <label for="taskSelect">Task da tracciare:</label>
                                <select id="taskSelect" class="form-control" <?= $activeSession ? 'disabled' : '' ?>>
                                    <option value="">-- Seleziona un task --</option>
                                    <?php foreach ($allTasks as $t): ?>
                                        <option value="<?= $t['id'] ?>" 
                                                <?= ($task && $task['id'] == $t['id']) || 
                                                    ($activeSession && $activeSession['task_id'] == $t['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['titolo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Timer controls -->
                            <div class="timer-controls">
                                <?php if (!$activeSession): ?>
                                    <button class="timer-btn primary" onclick="startTracking()" id="startBtn">
                                        ▶️ Inizia
                                    </button>
                                <?php else: ?>
                                    <button class="timer-btn secondary" onclick="pauseTracking()">
                                        ⏸️ Pausa
                                    </button>
                                    <button class="timer-btn danger" onclick="stopTracking()">
                                        ⏹️ Stop
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sessioni recenti -->
                        <div class="sessions-list">
                            <h3 class="sessions-title">Sessioni Recenti</h3>
                            
                            <?php if (empty($sessions)): ?>
                                <p style="text-align: center; color: #6b7280; padding: 2rem 0;">
                                    Nessuna sessione registrata
                                </p>
                            <?php else: ?>
                                <?php foreach ($sessions as $session): ?>
                                    <div class="session-item">
                                        <div class="session-info">
                                            <div class="session-task">
                                                <?= htmlspecialchars($session['task_titolo']) ?>
                                            </div>
                                            <div class="session-time">
                                                <?= date('d/m H:i', strtotime($session['ora_inizio'])) ?> - 
                                                <?= $session['ora_fine'] ? date('H:i', strtotime($session['ora_fine'])) : 'In corso' ?>
                                            </div>
                                        </div>
                                        <div class="session-duration">
                                            <?= formatDuration($session['durata_minuti']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right column -->
                    <div>
                        <!-- Statistiche giornaliere -->
                        <div class="stats-card">
                            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">
                                Statistiche Oggi
                            </h3>
                            
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?= formatDuration($statsToday['minuti_totali']) ?>
                                    </div>
                                    <div class="stat-label">Tempo totale</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?= $statsToday['sessioni'] ?>
                                    </div>
                                    <div class="stat-label">Sessioni</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?= $statsToday['task_diversi'] ?>
                                    </div>
                                    <div class="stat-label">Task diversi</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?= $statsToday['interruzioni'] ?>
                                    </div>
                                    <div class="stat-label">Interruzioni</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Interruzioni rapide -->
                        <div class="interruptions-panel" style="margin-top: 1.5rem;">
                            <h3 class="interruptions-title">Interruzioni Rapide</h3>
                            
                            <div class="interruption-buttons">
                                <?php foreach (TRACKING_CONFIG['interruption_types'] as $key => $label): ?>
                                    <button class="interruption-btn" 
                                            onclick="quickInterruption('<?= $key ?>')"
                                            <?= !$activeSession ? 'disabled' : '' ?>>
                                        <?= getInterruptionIcon($key) ?> <?= $label ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal interruzione -->
    <div id="interruptionModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header">Registra Interruzione</h2>
            
            <form id="interruptionForm">
                <input type="hidden" id="interruptionType" name="tipo">
                
                <div class="form-group">
                    <label for="interruptionClient">Cliente (opzionale):</label>
                    <select id="interruptionClient" name="cliente_id" class="form-control">
                        <option value="">-- Seleziona cliente --</option>
                        <?php
                        $clienti = $db->select("
                            SELECT id, ragione_sociale 
                            FROM clienti 
                            WHERE is_attivo = 1 
                            ORDER BY ragione_sociale
                        ");
                        foreach ($clienti as $cliente):
                        ?>
                            <option value="<?= $cliente['id'] ?>">
                                <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="interruptionNote">Note:</label>
                    <textarea id="interruptionNote" name="note" 
                              placeholder="Descrivi brevemente l'interruzione..."
                              required></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="crea_appuntamento" id="createAppointment">
                        Crea appuntamento di follow-up
                    </label>
                </div>
                
                <div id="appointmentFields" style="display: none;">
                    <div class="form-group">
                        <label for="appointmentDate">Data appuntamento:</label>
                        <input type="datetime-local" id="appointmentDate" name="data_appuntamento">
                    </div>
                    
                    <div class="form-group">
                        <label for="appointmentNote">Note appuntamento:</label>
                        <textarea id="appointmentNote" name="note_appuntamento" 
                                  placeholder="Note per l'appuntamento..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="timer-btn secondary" onclick="closeModal()">
                        Annulla
                    </button>
                    <button type="submit" class="timer-btn primary">
                        Salva interruzione
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Variabili globali
        let timerInterval = null;
        let startTime = null;
        let activeSessionId = <?= $activeSession ? $activeSession['id'] : 'null' ?>;
        
        // Inizializza timer se sessione attiva
        <?php if ($activeSession): ?>
            startTime = new Date('<?= $activeSession['ora_inizio'] ?>').getTime();
            startTimer();
        <?php endif; ?>
        
        // Funzioni timer
        function startTimer() {
            timerInterval = setInterval(updateTimer, 1000);
            updateTimer();
        }
        
        function updateTimer() {
            if (!startTime) return;
            
            const now = Date.now();
            const elapsed = now - startTime;
            
            const hours = Math.floor(elapsed / 3600000);
            const minutes = Math.floor((elapsed % 3600000) / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            
            const display = 
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
            
            document.getElementById('timerDisplay').textContent = display;
            
            // Titolo pagina con timer
            document.title = display + ' - Time Tracking';
        }
        
        // Start tracking
        async function startTracking() {
            const taskId = document.getElementById('taskSelect').value;
            
            if (!taskId) {
                alert('Seleziona un task prima di iniziare il tracking');
                return;
            }
            
            try {
                const response = await fetch('/crm/modules/pratiche/api/task_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'start_tracking',
                        task_id: taskId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    activeSessionId = data.session_id;
                    startTime = Date.now();
                    startTimer();
                    
                    // Aggiorna UI
                    document.getElementById('timerDisplay').classList.add('active');
                    document.getElementById('taskSelect').disabled = true;
                    location.reload(); // Ricarica per aggiornare i controlli
                } else {
                    alert(data.message || 'Errore durante l\'avvio del tracking');
                }
            } catch (error) {
                console.error('Errore:', error);
                alert('Errore di connessione');
            }
        }
        
        // Pause tracking
        async function pauseTracking() {
            const motivo = prompt('Motivo della pausa (opzionale):');
            
            try {
                const response = await fetch('/crm/modules/pratiche/api/task_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'pause_task',
                        task_id: document.getElementById('taskSelect').value,
                        motivo: motivo || ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    clearInterval(timerInterval);
                    location.reload();
                } else {
                    alert(data.message || 'Errore durante la pausa');
                }
            } catch (error) {
                console.error('Errore:', error);
                alert('Errore di connessione');
            }
        }
        
        // Stop tracking
        async function stopTracking() {
            if (!confirm('Vuoi davvero fermare il tracking?')) {
                return;
            }
            
            try {
                const response = await fetch('/crm/modules/pratiche/api/task_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'complete_task',
                        task_id: document.getElementById('taskSelect').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    clearInterval(timerInterval);
                    location.reload();
                } else {
                    alert(data.message || 'Errore durante lo stop');
                }
            } catch (error) {
                console.error('Errore:', error);
                alert('Errore di connessione');
            }
        }
        
        // Quick interruption
        function quickInterruption(type) {
            if (!activeSessionId) {
                alert('Nessuna sessione attiva');
                return;
            }
            
            document.getElementById('interruptionType').value = type;
            document.getElementById('interruptionModal').classList.add('active');
        }
        
        // Modal functions
        function closeModal() {
            document.getElementById('interruptionModal').classList.remove('active');
            document.getElementById('interruptionForm').reset();
        }
        
        // Toggle appointment fields
        document.getElementById('createAppointment').addEventListener('change', function(e) {
            document.getElementById('appointmentFields').style.display = 
                e.target.checked ? 'block' : 'none';
        });
        
        // Handle interruption form
        document.getElementById('interruptionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            data.session_id = activeSessionId;
            
            try {
                const response = await fetch('/crm/modules/pratiche/api/tracking_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'register_interruption',
                        ...data
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(result.message || 'Errore durante il salvataggio');
                }
            } catch (error) {
                console.error('Errore:', error);
                alert('Errore di connessione');
            }
        });
        
        // Export tracking data
        function exportTracking() {
            const params = new URLSearchParams({
                action: 'export',
                pratica_id: <?= $pratica['id'] ?>,
                format: 'excel'
            });
            
            window.open('/crm/modules/pratiche/api/tracking_api.php?' + params.toString());
        }
    </script>
</body>
</html>

<?php
// Helper functions
function formatDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h ' . ($mins > 0 ? $mins . 'min' : '');
}

function getInterruptionIcon($type) {
    $icons = [
        'chiamata_cliente' => '📞',
        'chiamata_interna' => '☎️',
        'pausa_pranzo' => '🍽️',
        'emergenza' => '🚨',
        'altro' => '💭'
    ];
    return $icons[$type] ?? '💭';
}
?>