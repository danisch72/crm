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

// Include i componenti UI
require_once CRM_PATH . '/components/header.php';
require_once CRM_PATH . '/components/navigation.php';
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
        /* Task Manager Styles */
        .task-manager-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
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
            color: #ef4444;
            margin-top: 0.5rem;
            padding: 0.25rem 0.5rem;
            background: #fee2e2;
            border-radius: 4px;
            display: inline-block;
        }
        
        /* Progress Bar */
        .task-progress {
            margin-top: 0.75rem;
        }
        
        .progress-bar {
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #10b981;
            transition: width 0.3s ease;
        }
        
        /* Sidebar */
        .tm-sidebar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 1rem;
        }
        
        .sidebar-section {
            margin-bottom: 2rem;
        }
        
        .sidebar-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        /* Quick Actions */
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
            font-size: 0.875rem;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .quick-action-btn:hover {
            background: #f9fafb;
            border-color: #007849;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
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
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        /* Stato badges */
        .stato-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stato-da_iniziare {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .stato-in_corso {
            background: #fef3c7;
            color: #d97706;
        }
        
        .stato-completato {
            background: #d1fae5;
            color: #065f46;
        }
        
        .stato-bloccato {
            background: #fee2e2;
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include CRM_PATH . '/components/navigation.php'; ?>
        
        <div class="main-wrapper">
            <?php include CRM_PATH . '/components/header.php'; ?>
            
            <main class="main-content">
                <div class="task-manager-container">
                    <!-- Header -->
                    <div class="tm-header">
                        <div class="tm-header-top">
                            <h1 class="tm-title">📋 Gestione Task</h1>
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
                            
                            <!-- Legend -->
                            <div class="sidebar-section">
                                <h3 class="sidebar-title">📋 Legenda Stati</h3>
                                <div style="font-size: 0.813rem;">
                                    <div style="margin-bottom: 0.5rem;">
                                        <span class="stato-badge stato-da_iniziare">⏳ Da Iniziare</span>
                                    </div>
                                    <div style="margin-bottom: 0.5rem;">
                                        <span class="stato-badge stato-in_corso">🔄 In Corso</span>
                                    </div>
                                    <div style="margin-bottom: 0.5rem;">
                                        <span class="stato-badge stato-completato">✅ Completato</span>
                                    </div>
                                    <div style="margin-bottom: 0.5rem;">
                                        <span class="stato-badge stato-bloccato">🚫 Bloccato</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Help -->
                            <div class="sidebar-section">
                                <h3 class="sidebar-title">💡 Suggerimenti</h3>
                                <p style="font-size: 0.813rem; color: #6b7280; line-height: 1.5;">
                                    • Trascina i task per riordinarli<br>
                                    • Clicca su ⚡ per azioni rapide<br>
                                    • I task con dipendenze non possono essere avviati finché non si completa il task precedente
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Nuovo Task -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">➕ Nuovo Task</h3>
                <button class="modal-close" onclick="closeModal('addTaskModal')">×</button>
            </div>
            
            <form id="addTaskForm" onsubmit="submitNewTask(event)">
                <div class="form-group">
                    <label class="form-label required">Titolo</label>
                    <input type="text" name="titolo" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrizione</label>
                    <textarea name="descrizione" class="form-control form-textarea"></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Ore stimate</label>
                        <input type="number" name="ore_stimate" class="form-control" value="1" step="0.5" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assegna a</label>
                        <select name="operatore_assegnato_id" class="form-control">
                            <option value="">Seleziona operatore</option>
                            <?php foreach ($operatori as $op): ?>
                                <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['nome_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dipende da</label>
                    <select name="dipende_da_task_id" class="form-control">
                        <option value="">Nessuna dipendenza</option>
                        <?php foreach ($tasks as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['titolo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Crea Task</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Annulla</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Azioni Task -->
    <div id="taskActionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚡ Azioni Task</h3>
                <button class="modal-close" onclick="closeModal('taskActionsModal')">×</button>
            </div>
            
            <div id="taskActionsContent">
                <!-- Contenuto dinamico -->
            </div>
        </div>
    </div>
    
    <script>
        // Variabili globali
        let currentFilter = 'all';
        let selectedTaskId = null;
        
        // Helper functions
        function getTaskStatoIcon(stato) {
            const icons = {
                'da_iniziare': '⏳',
                'in_corso': '🔄',
                'completato': '✅',
                'bloccato': '🚫'
            };
            return icons[stato] || '❓';
        }
        
        function getTaskStatoLabel(stato) {
            const labels = {
                'da_iniziare': 'Da Iniziare',
                'in_corso': 'In Corso',
                'completato': 'Completato',
                'bloccato': 'Bloccato'
            };
            return labels[stato] || stato;
        }
        
        // Drag & Drop
        document.addEventListener('DOMContentLoaded', function() {
            const taskItems = document.querySelectorAll('.task-item');
            let draggedItem = null;
            
            taskItems.forEach(item => {
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('drop', handleDrop);
            });
        });
        
        function handleDragStart(e) {
            draggedItem = this;
            this.classList.add('dragging');
        }
        
        function handleDragEnd(e) {
            this.classList.remove('dragging');
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(document.querySelector('.task-list'), e.clientY);
            if (afterElement == null) {
                document.querySelector('.task-list').appendChild(draggedItem);
            } else {
                document.querySelector('.task-list').insertBefore(draggedItem, afterElement);
            }
        }
        
        function handleDrop(e) {
            e.preventDefault();
            saveTaskOrder();
        }
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.task-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        // Salva nuovo ordine
        function saveTaskOrder() {
            const taskItems = document.querySelectorAll('.task-item');
            const taskOrder = [];
            
            taskItems.forEach(item => {
                taskOrder.push(item.dataset.taskId);
            });
            
            fetch('/crm/modules/pratiche/api/task_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'reorder_tasks',
                    pratica_id: <?= $pratica['id'] ?>,
                    task_order: taskOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Ordine task aggiornato', 'success');
                }
            });
        }
        
        // Filtri
        function filterTasks(stato) {
            currentFilter = stato;
            const taskItems = document.querySelectorAll('.task-item');
            const filterBtns = document.querySelectorAll('.filter-btn');
            
            // Update active button
            filterBtns.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(stato) || 
                    (stato === 'all' && btn.textContent === 'Tutti')) {
                    btn.classList.add('active');
                }
            });
            
            // Filter tasks
            taskItems.forEach(item => {
                if (stato === 'all' || item.dataset.stato === stato) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Modal functions
        function showAddTaskModal() {
            document.getElementById('addTaskModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function showTaskActions(taskId) {
            selectedTaskId = taskId;
            const modal = document.getElementById('taskActionsModal');
            const content = document.getElementById('taskActionsContent');
            
            // Genera contenuto azioni
            content.innerHTML = `
                <div class="quick-actions">
                    <button class="quick-action-btn" onclick="startTask(${taskId})">
                        ▶️ Avvia Task
                    </button>
                    <button class="quick-action-btn" onclick="completeTask(${taskId})">
                        ✅ Completa Task
                    </button>
                    <button class="quick-action-btn" onclick="showAssignModal(${taskId})">
                        👥 Riassegna Task
                    </button>
                    <button class="quick-action-btn" onclick="editTask(${taskId})">
                        ✏️ Modifica Task
                    </button>
                    <button class="quick-action-btn" onclick="deleteTask(${taskId})">
                        🗑️ Elimina Task
                    </button>
                </div>
            `;
            
            modal.classList.add('active');
        }
        
        // Task actions
        function startTask(taskId) {
            fetch('/crm/modules/pratiche/api/task_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'start_task',
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Task avviato', 'success');
                    location.reload();
                } else {
                    showNotification(data.message, 'error');
                }
            });
        }
        
        function completeTask(taskId) {
            if (confirm('Completare questo task?')) {
                fetch('/crm/modules/pratiche/api/task_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'complete_task',
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Task completato', 'success');
                        location.reload();
                    } else {
                        showNotification(data.message, 'error');
                    }
                });
            }
        }
        
        function deleteTask(taskId) {
            if (confirm('Eliminare questo task?')) {
                fetch('/crm/modules/pratiche/api/task_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_task',
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Task eliminato', 'success');
                        location.reload();
                    } else {
                        showNotification(data.message, 'error');
                    }
                });
            }
        }
        
        // Submit nuovo task
        function submitNewTask(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            // Aggiungi dati mancanti
            formData.append('pratica_id', <?= $pratica['id'] ?>);
            formData.append('cliente_id', <?= $pratica['cliente_id'] ?>);
            
            // Invia al server (da implementare endpoint create_task)
            // Per ora solo messaggio
            showNotification('Funzionalità da completare', 'info');
            closeModal('addTaskModal');
        }
        
        // Notifiche
        function showNotification(message, type = 'info') {
            // Implementazione semplice alert
            // In produzione usare sistema notifiche migliore
            alert(message);
        }
        
        // Quick actions
        function assignAllTasks() {
            showNotification('Funzionalità da implementare', 'info');
        }
        
        function recalculateProgress() {
            showNotification('Ricalcolo in corso...', 'info');
            location.reload();
        }
        
        function exportTasks() {
            window.location.href = `/crm/modules/pratiche/api/export.php?pratica_id=<?= $pratica['id'] ?>`;
        }
        
        // Inizializzazione
        window.addEventListener('load', function() {
            // Setup eventuali listener aggiuntivi
        });
    </script>
</body>
</html>

<?php
// Helper functions PHP
function getTaskStatoIcon($stato) {
    $icons = [
        'da_iniziare' => '⏳',
        'in_corso' => '🔄',
        'completato' => '✅',
        'bloccato' => '🚫'
    ];
    return $icons[$stato] ?? '❓';
}

function getTaskStatoLabel($stato) {
    $labels = [
        'da_iniziare' => 'Da Iniziare',
        'in_corso' => 'In Corso',
        'completato' => 'Completato', 
        'bloccato' => 'Bloccato'
    ];
    return $labels[$stato] ?? $stato;
}
?>