<?php
/**
 * modules/pratiche/task_manager.php - Gestione Task Pratica
 * 
 * ✅ INTERFACCIA GESTIONE TASK CON DRAG & DROP
 * 
 * Features:
 * - Vista kanban task per stato
 * - Drag & drop per cambio stato
 * - Tracking ore su task
 * - Gestione dipendenze
 * - Note e allegati task
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata dal router)

// Gestione azioni AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'update_task_status':
                $taskId = (int)$_POST['task_id'];
                $newStatus = $_POST['status'];
                
                $db->update('task', [
                    'stato' => $newStatus,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ? AND pratica_id = ?', [$taskId, $pratica['id']]);
                
                $response['success'] = true;
                $response['message'] = 'Stato aggiornato';
                break;
                
            case 'update_task_order':
                $taskId = (int)$_POST['task_id'];
                $newOrder = (int)$_POST['order'];
                
                $db->update('task', [
                    'ordine' => $newOrder,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ? AND pratica_id = ?', [$taskId, $pratica['id']]);
                
                $response['success'] = true;
                break;
                
            case 'quick_add_task':
                $taskId = $db->insert('task', [
                    'pratica_id' => $pratica['id'],
                    'titolo' => $_POST['titolo'],
                    'stato' => $_POST['stato'] ?? 'da_fare',
                    'ore_stimate' => floatval($_POST['ore_stimate'] ?? 0),
                    'ordine' => 999,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $response['success'] = true;
                $response['task_id'] = $taskId;
                break;
        }
    } catch (Exception $e) {
        $response['message'] = 'Errore: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Carica task della pratica
$tasks = $db->select("
    SELECT 
        t.*,
        CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
        COUNT(DISTINCT tn.id) as note_count,
        SUM(tt.ore_lavorate) as ore_totali_tracked
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    LEFT JOIN task_note tn ON t.id = tn.task_id
    LEFT JOIN tracking_task tt ON t.id = tt.task_id
    WHERE t.pratica_id = ?
    GROUP BY t.id
    ORDER BY t.ordine, t.id
", [$pratica['id']]);

// Raggruppa task per stato
$tasksByStatus = [
    'da_fare' => [],
    'in_corso' => [],
    'completato' => [],
    'bloccato' => []
];

foreach ($tasks as $task) {
    $stato = $task['stato'];
    if (isset($tasksByStatus[$stato])) {
        $tasksByStatus[$stato][] = $task;
    }
}

// Carica info pratica completa
$praticaInfo = getPraticaCompleta($pratica['id']);

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Task - <?= htmlspecialchars($pratica['titolo']) ?> - CRM Re.De</title>
    
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    
    <style>
        /* Container principale */
        .task-manager-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header compatto */
        .tm-header {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tm-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .tm-subtitle {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .tm-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Kanban Board */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 1200px) {
            .kanban-board {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .kanban-board {
                grid-template-columns: 1fr;
            }
        }
        
        .kanban-column {
            background: #f9fafb;
            border-radius: 8px;
            padding: 0.75rem;
            min-height: 400px;
        }
        
        .kanban-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
        }
        
        .kanban-title {
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .kanban-count {
            background: white;
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Task Cards */
        .task-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            cursor: move;
            transition: all 0.2s;
        }
        
        .task-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        
        .task-card.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
        }
        
        .task-title {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .task-meta {
            display: flex;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .task-priority {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 0.25rem;
        }
        
        .priority-alta { background: #ef4444; }
        .priority-media { background: #f59e0b; }
        .priority-bassa { background: #10b981; }
        
        /* Quick Add */
        .quick-add-form {
            background: white;
            border: 2px dashed #d1d5db;
            border-radius: 6px;
            padding: 0.5rem;
            margin-top: 0.5rem;
            display: none;
        }
        
        .quick-add-form.active {
            display: block;
        }
        
        .quick-add-input {
            width: 100%;
            padding: 0.375rem 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.8125rem;
        }
        
        /* Stati colonne */
        .kanban-da_fare { background: #fef3c7; }
        .kanban-in_corso { background: #dbeafe; }
        .kanban-completato { background: #d1fae5; }
        .kanban-bloccato { background: #fee2e2; }
        
        /* Progress bar */
        .progress-section {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            transition: width 0.3s ease;
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        /* Modal task detail */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: relative;
            background: white;
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.5;
        }
        
        .modal-close:hover {
            opacity: 1;
        }
        
        /* Buttons */
        .btn {
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 4px;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-link {
            background: none;
            color: #6b7280;
            text-decoration: none;
        }
        
        .btn-link:hover {
            color: #374151;
        }
        
        /* Bulk actions */
        .bulk-actions {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: none;
        }
        
        .bulk-actions.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="main-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="task-manager-container">
                    <!-- Header -->
                    <div class="tm-header">
                        <div>
                            <h1 class="tm-title">
                                📋 Gestione Task - <?= htmlspecialchars($pratica['titolo']) ?>
                            </h1>
                            <div class="tm-subtitle">
                                <?= htmlspecialchars($praticaInfo['cliente_nome']) ?> • 
                                Scadenza: <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?>
                            </div>
                        </div>
                        <div class="tm-actions">
                            <button class="btn btn-secondary btn-sm" onclick="showBulkAdd()">
                                ➕ Aggiungi Multipli
                            </button>
                            <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" 
                               class="btn btn-secondary btn-sm">
                                ◀ Torna a Pratica
                            </a>
                        </div>
                    </div>
                    
                    <!-- Progress -->
                    <?php 
                    $totalTasks = count($tasks);
                    $completedTasks = count($tasksByStatus['completato']);
                    $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
                    ?>
                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?= $completedTasks ?> di <?= $totalTasks ?> task completati</span>
                            <span><?= $progress ?>% completo</span>
                        </div>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <div class="bulk-actions" id="bulkActions">
                        <h3 style="font-size: 0.875rem; margin-bottom: 1rem;">➕ Aggiungi Task Multipli</h3>
                        <div id="bulkTaskContainer">
                            <div class="bulk-task-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Titolo task..."
                                       style="flex: 1; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 0.8125rem;">
                                <input type="number" 
                                       class="form-control" 
                                       placeholder="Ore"
                                       min="0" 
                                       step="0.5"
                                       style="width: 80px; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 0.8125rem;">
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                            <button class="btn btn-secondary btn-sm" onclick="addBulkTaskRow()">
                                + Altra riga
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="saveBulkTasks()">
                                💾 Salva tutti
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="hideBulkAdd()">
                                ✖ Annulla
                            </button>
                        </div>
                    </div>
                    
                    <!-- Kanban Board -->
                    <div class="kanban-board">
                        <?php foreach (TASK_STATI as $stato => $config): ?>
                        <div class="kanban-column kanban-<?= $stato ?>" data-status="<?= $stato ?>">
                            <div class="kanban-header">
                                <div class="kanban-title">
                                    <span><?= $config['icon'] ?></span>
                                    <span><?= $config['label'] ?></span>
                                </div>
                                <span class="kanban-count"><?= count($tasksByStatus[$stato]) ?></span>
                            </div>
                            
                            <div class="kanban-cards" id="column-<?= $stato ?>">
                                <?php foreach ($tasksByStatus[$stato] as $task): ?>
                                <div class="task-card" 
                                     draggable="true" 
                                     data-task-id="<?= $task['id'] ?>"
                                     onclick="viewTaskDetails(<?= $task['id'] ?>)">
                                    <div class="task-title">
                                        <?= htmlspecialchars($task['titolo']) ?>
                                    </div>
                                    <div class="task-meta">
                                        <?php if ($task['ore_stimate'] > 0): ?>
                                        <span>⏱️ <?= $task['ore_stimate'] ?>h</span>
                                        <?php endif; ?>
                                        <?php if ($task['operatore_nome']): ?>
                                        <span>👤 <?= htmlspecialchars($task['operatore_nome']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($task['note_count'] > 0): ?>
                                        <span>💬 <?= $task['note_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Quick Add -->
                            <button class="btn btn-link btn-sm" 
                                    onclick="toggleQuickAdd('<?= $stato ?>')"
                                    style="width: 100%; margin-top: 0.5rem;">
                                + Aggiungi task
                            </button>
                            
                            <div class="quick-add-form" id="quick-add-<?= $stato ?>">
                                <input type="text" 
                                       class="quick-add-input" 
                                       placeholder="Titolo task..."
                                       onkeypress="handleQuickAdd(event, '<?= $stato ?>')">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Task Detail -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeTaskModal()">&times;</span>
            <div id="taskModalContent">
                <!-- Contenuto dinamico -->
            </div>
        </div>
    </div>
    
    <script src="/crm/assets/js/pratiche.js"></script>
    <script>
        // Drag & Drop
        let draggedElement = null;
        
        document.querySelectorAll('.task-card').forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedElement = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
            });
            
            card.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
            });
        });
        
        document.querySelectorAll('.kanban-cards').forEach(zone => {
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                const afterElement = getDragAfterElement(zone, e.clientY);
                if (afterElement == null) {
                    zone.appendChild(draggedElement);
                } else {
                    zone.insertBefore(draggedElement, afterElement);
                }
            });
            
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                
                const taskId = draggedElement.dataset.taskId;
                const newStatus = this.parentElement.dataset.status;
                
                // Update via AJAX
                updateTaskStatus(taskId, newStatus);
                
                // Update counters
                updateColumnCounts();
            });
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging)')];
            
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
        
        // AJAX Functions
        function updateTaskStatus(taskId, status) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=1&action=update_task_status&task_id=${taskId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Errore aggiornamento stato');
                    location.reload();
                }
            });
        }
        
        function updateColumnCounts() {
            document.querySelectorAll('.kanban-column').forEach(column => {
                const count = column.querySelectorAll('.task-card').length;
                column.querySelector('.kanban-count').textContent = count;
            });
            
            // Update progress
            const total = document.querySelectorAll('.task-card').length;
            const completed = document.querySelectorAll('.kanban-completato .task-card').length;
            const progress = total > 0 ? Math.round((completed / total) * 100) : 0;
            
            document.querySelector('.progress-fill').style.width = progress + '%';
            document.querySelector('.progress-stats').innerHTML = 
                `<span>${completed} di ${total} task completati</span><span>${progress}% completo</span>`;
        }
        
        // Quick Add
        function toggleQuickAdd(status) {
            const form = document.getElementById(`quick-add-${status}`);
            form.classList.toggle('active');
            if (form.classList.contains('active')) {
                form.querySelector('input').focus();
            }
        }
        
        function handleQuickAdd(event, status) {
            if (event.key === 'Enter') {
                const input = event.target;
                const titolo = input.value.trim();
                
                if (titolo) {
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax=1&action=quick_add_task&titolo=${encodeURIComponent(titolo)}&stato=${status}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
                }
            }
        }
        
        // Bulk Add
        function showBulkAdd() {
            document.getElementById('bulkActions').classList.add('active');
        }
        
        function hideBulkAdd() {
            document.getElementById('bulkActions').classList.remove('active');
        }
        
        function addBulkTaskRow() {
            const container = document.getElementById('bulkTaskContainer');
            const newRow = container.children[0].cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            container.appendChild(newRow);
        }
        
        function saveBulkTasks() {
            const rows = document.querySelectorAll('#bulkTaskContainer .bulk-task-item');
            const tasks = [];
            
            rows.forEach(row => {
                const titolo = row.querySelector('input[type="text"]').value.trim();
                const ore = row.querySelector('input[type="number"]').value || 0;
                
                if (titolo) {
                    tasks.push({ titolo, ore_stimate: ore });
                }
            });
            
            if (tasks.length > 0) {
                // In produzione, fare chiamata AJAX per salvare tutti i task
                console.log('Salvando task:', tasks);
                alert('Funzionalità in sviluppo');
            }
        }
        
        // Task Details Modal
        function viewTaskDetails(taskId) {
            // Previeni propagazione click
            event.stopPropagation();
            
            // In produzione, caricare dettagli via AJAX
            document.getElementById('taskModal').style.display = 'block';
            document.getElementById('taskModalContent').innerHTML = `
                <h2 style="margin-top: 0;">📋 Dettagli Task #${taskId}</h2>
                <p>Caricamento dettagli...</p>
            `;
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').style.display = 'none';
        }
        
        // Click outside modal to close
        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>