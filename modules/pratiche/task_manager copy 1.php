<?php
/**
 * modules/pratiche/task_manager.php - Gestione Task Pratica
 * 
 * ✅ INTERFACCIA AVANZATA GESTIONE TASK
 * 
 * Features:
 * - Vista completa tutti i task della pratica
 * - Drag & drop per riordinare
 * - Gestione dipendenze tra task
 * - Assegnazione/riassegnazione operatori
 * - Progress tracking visuale
 * - Azioni rapide su ogni task
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata dal router)

// Carica tutti i task della pratica con info complete
$tasks = $db->select("
    SELECT 
        t.*,
        CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
        o.email as operatore_email,
        (SELECT titolo FROM task WHERE id = t.dipende_da_task_id) as dipende_da_titolo,
        (SELECT SUM(ore_lavorate) FROM tracking_task WHERE task_id = t.id) as ore_tracking,
        (SELECT COUNT(*) FROM tracking_task WHERE task_id = t.id AND ora_fine IS NULL) as tracking_attivo
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    WHERE t.pratica_id = ?
    ORDER BY t.ordine, t.id
", [$pratica['id']]);

// Carica operatori disponibili per assegnazione
$operatori = $db->select("
    SELECT id, CONCAT(nome, ' ', cognome) as nome_completo, email
    FROM operatori
    WHERE is_attivo = 1
    ORDER BY cognome, nome
");

// Statistiche
$stats = [
    'totali' => count($tasks),
    'completati' => count(array_filter($tasks, fn($t) => $t['stato'] === 'completato')),
    'in_corso' => count(array_filter($tasks, fn($t) => $t['stato'] === 'in_corso')),
    'da_fare' => count(array_filter($tasks, fn($t) => $t['stato'] === 'da_iniziare')),
    'bloccati' => count(array_filter($tasks, fn($t) => $t['stato'] === 'bloccato')),
    'ore_totali' => array_sum(array_column($tasks, 'ore_stimate')),
    'ore_lavorate' => array_sum(array_column($tasks, 'ore_tracking'))
];

// ❌ RIMUOVI GLI INCLUDE DUPLICATI QUI
// require_once CRM_PATH . '/components/header.php';
// require_once CRM_PATH . '/components/navigation.php';

// Funzioni helper
function getTaskStatoIcon($stato) {
    $icons = [
        'da_iniziare' => '⏳',
        'in_corso' => '🔄',
        'completato' => '✅',
        'bloccato' => '🚫'
    ];
    return $icons[$stato] ?? '📋';
}

function getTaskStatoLabel($stato) {
    $labels = [
        'da_iniziare' => 'Da iniziare',
        'in_corso' => 'In corso',
        'completato' => 'Completato',
        'bloccato' => 'Bloccato'
    ];
    return $labels[$stato] ?? $stato;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Task - <?= htmlspecialchars($pratica['titolo']) ?> - CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/crm/assets/css/style.css">
    <style>
        /* IMPORTANTE: Fix per il layout */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px; /* Spazio per la sidebar */
            padding: 0;
            background: #f3f4f6;
            min-height: 100vh;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 60px; /* Quando sidebar è collassata */
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Task Manager Styles */
        .task-manager-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        /* Header */
        .tm-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .tm-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .tm-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .tm-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        /* Stats */
        .tm-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Main Layout */
        .tm-main {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
        }
        
        @media (max-width: 1024px) {
            .tm-main {
                grid-template-columns: 1fr;
            }
            
            .tm-sidebar {
                display: none;
            }
        }
        
        /* Task List */
        .task-list-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .task-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .task-list-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
        }
        
        .task-filters {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .filter-btn:hover {
            background: #f9fafb;
        }
        
        .filter-btn.active {
            background: #007849;
            color: white;
            border-color: #007849;
        }
        
        /* Task Items */
        .task-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .task-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: move;
            transition: all 0.2s;
            position: relative;
        }
        
        .task-item.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }
        
        .task-item:hover {
            border-color: #007849;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .task-item.completed {
            opacity: 0.7;
            background: #e6f4ea;
        }
        
        .task-item.in-progress {
            border-color: #f59e0b;
            background: #fef3c7;
        }
        
        .task-item.blocked {
            border-color: #ef4444;
            background: #fee2e2;
        }
        
        /* Task Content */
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .task-info {
            flex: 1;
        }
        
        .task-title {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }
        
        .task-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .task-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .task-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        /* Dependencies */
        .task-dependency {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
            padding: 0.25rem 0.5rem;
            background: #e5e7eb;
            border-radius: 4px;
            display: inline-block;
        }
        
        /* Progress */
        .task-progress {
            margin-top: 0.75rem;
        }
        
        .progress-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #007849;
            transition: width 0.3s;
        }
        
        /* Sidebar */
        .tm-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .quick-action-btn {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            font-size: 0.813rem;
            cursor: pointer;
            transition: all 0.15s;
            text-align: left;
        }
        
        .quick-action-btn:hover {
            background: #f9fafb;
            border-color: #007849;
        }
        
        /* Stati Badge */
        .stato-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stato-da_iniziare {
            background: #e5e7eb;
            color: #374151;
        }
        
        .stato-in_corso {
            background: #fef3c7;
            color: #92400e;
        }
        
        .stato-completato {
            background: #d1fae5;
            color: #065f46;
        }
        
        .stato-bloccato {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php 
        // ✅ INCLUDE CORRETTI - usa require_once con percorso corretto
        require_once dirname(dirname(__DIR__)) . '/components/navigation.php'; 
        ?>
        
        <div class="main-content">
            <?php 
            // ✅ INCLUDE HEADER CORRETTO
            require_once dirname(dirname(__DIR__)) . '/components/header.php'; 
            ?>
            
            <div class="task-manager-container">
                <!-- Header -->
                <div class="tm-header">
                    <div class="tm-header-top">
                        <h1 class="tm-title">📋 Gestione Task - <?= htmlspecialchars($pratica['titolo']) ?></h1>
                        <div class="tm-actions">
                            <button class="btn btn-secondary" onclick="showAddTaskModal()">
                                ➕ Nuovo Task
                            </button>
                            <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" class="btn btn-secondary">
                                ↩️ Torna alla Pratica
                            </a>
                        </div>
                    </div>
                    
                    <div class="tm-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $stats['totali'] ?></div>
                            <div class="stat-label">Task Totali</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: #10b981;"><?= $stats['completati'] ?></div>
                            <div class="stat-label">Completati</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: #f59e0b;"><?= $stats['in_corso'] ?></div>
                            <div class="stat-label">In Corso</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: #6b7280;"><?= $stats['da_fare'] ?></div>
                            <div class="stat-label">Da Fare</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['ore_totali'], 1) ?>h</div>
                            <div class="stat-label">Ore Stimate</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['ore_lavorate'], 1) ?>h</div>
                            <div class="stat-label">Ore Lavorate</div>
                        </div>
                    </div>
                </div>
                
                <div class="tm-main">
                    <!-- Task List -->
                    <div class="task-list-container">
                        <div class="task-list-header">
                            <h2 class="task-list-title">Lista Task</h2>
                            <div class="task-filters">
                                <button class="filter-btn active" onclick="filterTasks('all')">Tutti</button>
                                <button class="filter-btn" onclick="filterTasks('da_iniziare')">Da Fare</button>
                                <button class="filter-btn" onclick="filterTasks('in_corso')">In Corso</button>
                                <button class="filter-btn" onclick="filterTasks('completato')">Completati</button>
                            </div>
                        </div>
                        
                        <ul class="task-list" id="taskList">
                            <?php foreach ($tasks as $task): ?>
                                <li class="task-item <?= $task['stato'] === 'completato' ? 'completed' : '' ?> 
                                           <?= $task['stato'] === 'in_corso' ? 'in-progress' : '' ?>
                                           <?= $task['stato'] === 'bloccato' ? 'blocked' : '' ?>"
                                    data-task-id="<?= $task['id'] ?>"
                                    data-stato="<?= $task['stato'] ?>"
                                    draggable="true">
                                    
                                    <div class="task-header">
                                        <div class="task-info">
                                            <div class="task-title">
                                                <?= htmlspecialchars($task['titolo']) ?>
                                            </div>
                                            <div class="task-meta">
                                                <div class="task-meta-item">
                                                    👤 <?= htmlspecialchars($task['operatore_nome'] ?? 'Non assegnato') ?>
                                                </div>
                                                <div class="task-meta-item">
                                                    ⏱️ <?= $task['ore_stimate'] ?>h stimate
                                                </div>
                                                <?php if ($task['ore_tracking'] > 0): ?>
                                                    <div class="task-meta-item">
                                                        ✅ <?= number_format($task['ore_tracking'], 1) ?>h lavorate
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($task['data_scadenza']): ?>
                                                    <div class="task-meta-item">
                                                        📅 <?= date('d/m/Y', strtotime($task['data_scadenza'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="task-actions">
                                            <div class="stato-badge stato-<?= str_replace(' ', '_', $task['stato']) ?>">
                                                <?= getTaskStatoIcon($task['stato']) ?> <?= getTaskStatoLabel($task['stato']) ?>
                                            </div>
                                            
                                            <?php if ($task['tracking_attivo']): ?>
                                                <button class="task-btn" style="background: #fef3c7;">
                                                    ⏱️ In tracking
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="task-btn" onclick="showTaskActions(<?= $task['id'] ?>)">
                                                ⚡
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php if ($task['dipende_da_titolo']): ?>
                                        <div class="task-dependency">
                                            🔗 Dipende da: <?= htmlspecialchars($task['dipende_da_titolo']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['descrizione']): ?>
                                        <div style="font-size: 0.813rem; color: #6b7280; margin-top: 0.5rem;">
                                            <?= nl2br(htmlspecialchars($task['descrizione'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['percentuale_completamento'] > 0): ?>
                                        <div class="task-progress">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $task['percentuale_completamento'] ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="tm-sidebar">
                        <!-- Quick Actions -->
                        <div class="sidebar-section">
                            <h3 class="sidebar-title">⚡ Azioni Rapide</h3>
                            <div class="quick-actions">
                                <button class="quick-action-btn" onclick="assignAllTasks()">
                                    👥 Assegna tutti i task
                                </button>
                                <button class="quick-action-btn" onclick="recalculateProgress()">
                                    📊 Ricalcola progressi
                                </button>
                                <button class="quick-action-btn" onclick="exportTasks()">
                                    📥 Esporta task
                                </button>
                            </div>
                        </div>
                        
                        <!-- Legenda Stati -->
                        <div class="sidebar-section">
                            <h3 class="sidebar-title">📊 Legenda Stati</h3>
                            <div class="quick-actions">
                                <div class="stato-badge stato-da_iniziare">⏳ Da iniziare</div>
                                <div class="stato-badge stato-in_corso">🔄 In corso</div>
                                <div class="stato-badge stato-completato">✅ Completato</div>
                                <div class="stato-badge stato-bloccato">🚫 Bloccato</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JavaScript -->
    <script src="/crm/assets/js/microinteractions.js"></script>
    <script src="/crm/modules/pratiche/assets/js/pratiche.js"></script>
    
    <script>
        // Variabili globali
        let selectedTaskId = null;
        
        // Inizializza drag & drop
        document.addEventListener('DOMContentLoaded', function() {
            if (window.PraticheManager) {
                // Il drag & drop è già gestito da pratiche.js
                console.log('Task Manager ready');
            }
        });
        
        // Filter tasks
        function filterTasks(stato) {
            const tasks = document.querySelectorAll('.task-item');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter tasks
            tasks.forEach(task => {
                if (stato === 'all' || task.dataset.stato === stato) {
                    task.style.display = 'block';
                } else {
                    task.style.display = 'none';
                }
            });
        }
        
        // Show task actions
        function showTaskActions(taskId) {
            selectedTaskId = taskId;
            
            if (window.PraticheManager) {
                window.PraticheManager.showNotification('Azioni task disponibili nel menu contestuale', 'info');
            }
        }
        
        // Quick actions
        function assignAllTasks() {
            if (confirm('Assegnare tutti i task non assegnati a te?')) {
                // Implementare assegnazione bulk
                console.log('Assegnazione bulk task...');
            }
        }
        
        function recalculateProgress() {
            // Implementare ricalcolo progressi
            console.log('Ricalcolo progressi...');
            location.reload();
        }
        
        function exportTasks() {
            // Implementare export
            window.location.href = '/crm/modules/pratiche/export.php?pratica_id=<?= $pratica['id'] ?>&type=tasks';
        }
        
        function showAddTaskModal() {
            // Usa il sistema di notifiche o apri un modal
            if (window.PraticheManager) {
                window.PraticheManager.showNotification('Funzione in sviluppo', 'info');
            }
        }
    </script>
</body>
</html>