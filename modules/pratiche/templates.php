<?php
/**
 * modules/pratiche/templates.php - Gestione Template Pratiche
 * 
 * ✅ INTERFACCIA ADMIN PER TEMPLATE PRATICHE
 * 
 * Features:
 * - CRUD template pratiche
 * - Definizione task predefiniti
 * - Assegnazione a tipi pratica
 * - Statistiche utilizzo
 * - Import/Export template
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Solo admin possono accedere (già verificato dal router)

// Gestione form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_template':
            createTemplate($_POST, $db);
            break;
            
        case 'update_template':
            updateTemplate($_POST, $db);
            break;
            
        case 'delete_template':
            deleteTemplate($_POST['template_id'], $db);
            break;
            
        case 'toggle_template':
            toggleTemplate($_POST['template_id'], $db);
            break;
    }
    
    // Redirect per evitare resubmit
    header('Location: /crm/?action=pratiche&view=templates');
    exit;
}

// Carica tutti i template
$templates = $db->select("
    SELECT 
        t.*,
        COUNT(DISTINCT p.id) as utilizzi_count,
        COUNT(DISTINCT tt.id) as task_count
    FROM pratiche_template t
    LEFT JOIN pratiche p ON p.template_id = t.id
    LEFT JOIN pratiche_template_task tt ON tt.template_id = t.id
    GROUP BY t.id
    ORDER BY t.tipo_pratica, t.nome
");

// Raggruppa per tipo
$templatesByType = [];
foreach ($templates as $template) {
    $tipo = $template['tipo_pratica'];
    if (!isset($templatesByType[$tipo])) {
        $templatesByType[$tipo] = [];
    }
    $templatesByType[$tipo][] = $template;
}

// Template selezionato per modifica
$selectedTemplate = null;
$templateTasks = [];

if (isset($_GET['edit'])) {
    $templateId = (int)$_GET['edit'];
    $selectedTemplate = $db->selectOne(
        "SELECT * FROM pratiche_template WHERE id = ?",
        [$templateId]
    );
    
    if ($selectedTemplate) {
        $templateTasks = $db->select(
            "SELECT * FROM pratiche_template_task 
             WHERE template_id = ? 
             ORDER BY ordine",
            [$templateId]
        );
    }
}

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Template Pratiche - CRM</title>
    
    <style>
        /* Layout principale */
        .templates-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        .templates-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .templates-main {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .templates-main {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        /* Template groups */
        .template-group {
            margin-bottom: 2rem;
        }
        
        .template-group-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .template-group-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
        }
        
        .template-group-icon {
            font-size: 1.25rem;
        }
        
        /* Template items */
        .template-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .template-item:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .template-item.inactive {
            opacity: 0.6;
        }
        
        .template-info {
            flex: 1;
        }
        
        .template-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.875rem;
        }
        
        .template-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .template-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            padding: 0.375rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 0.875rem;
        }
        
        .btn-icon:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        /* Form template */
        .template-form {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            min-height: 80px;
            resize: vertical;
        }
        
        /* Task builder */
        .task-builder {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .task-builder-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .task-list {
            margin-bottom: 1rem;
        }
        
        .task-item {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: move;
        }
        
        .task-item.dragging {
            opacity: 0.5;
        }
        
        .task-handle {
            color: #9ca3af;
            cursor: move;
        }
        
        .task-input {
            flex: 1;
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 0.5rem;
        }
        
        .task-input input {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .task-remove {
            padding: 0.375rem;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.75rem;
        }
        
        .task-remove:hover {
            background: #fecaca;
        }
        
        .add-task-btn {
            width: 100%;
            padding: 0.75rem;
            border: 2px dashed #d1d5db;
            background: white;
            border-radius: 6px;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 0.875rem;
        }
        
        .add-task-btn:hover {
            border-color: #9ca3af;
            color: #374151;
        }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
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
        
        /* Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007849;
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,120,73,0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <div class="templates-container">
                <!-- Header -->
                <div class="templates-header">
                    <div>
                        <h1 style="font-size: 1.5rem; font-weight: 600; color: #1f2937;">
                            Gestione Template Pratiche
                        </h1>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">
                            Definisci template riutilizzabili per velocizzare la creazione delle pratiche
                        </p>
                    </div>
                    
                    <div>
                        <button class="btn btn-primary" onclick="showNewTemplateForm()">
                            + Nuovo Template
                        </button>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($templates) ?></div>
                        <div class="stat-label">Template totali</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= count(array_filter($templates, fn($t) => $t['is_attivo'])) ?>
                        </div>
                        <div class="stat-label">Template attivi</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= array_sum(array_column($templates, 'utilizzi_count')) ?>
                        </div>
                        <div class="stat-label">Utilizzi totali</div>
                    </div>
                </div>
                
                <!-- Main content -->
                <div class="templates-main">
                    <!-- Templates list -->
                    <div>
                        <?php if (empty($templates)): ?>
                            <div class="card">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">
                                        Nessun template creato
                                    </h3>
                                    <p>Crea il tuo primo template per velocizzare la creazione delle pratiche</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach (PRATICHE_TYPES as $typeKey => $typeConfig): ?>
                                <?php if (isset($templatesByType[$typeKey]) && !empty($templatesByType[$typeKey])): ?>
                                    <div class="template-group">
                                        <div class="template-group-header">
                                            <span class="template-group-icon"><?= $typeConfig['icon'] ?></span>
                                            <h3 class="template-group-title"><?= $typeConfig['label'] ?></h3>
                                        </div>
                                        
                                        <?php foreach ($templatesByType[$typeKey] as $template): ?>
                                            <div class="template-item <?= !$template['is_attivo'] ? 'inactive' : '' ?>">
                                                <div class="template-info">
                                                    <div class="template-name">
                                                        <?= htmlspecialchars($template['nome']) ?>
                                                    </div>
                                                    <div class="template-meta">
                                                        <span>📋 <?= $template['task_count'] ?> task</span>
                                                        <span>🔄 <?= $template['utilizzi_count'] ?> utilizzi</span>
                                                        <span>⏱️ <?= $template['ore_stimate_totali'] ?>h stimate</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="template-actions">
                                                    <button class="btn-icon" 
                                                            onclick="editTemplate(<?= $template['id'] ?>)"
                                                            title="Modifica">
                                                        ✏️
                                                    </button>
                                                    
                                                    <button class="btn-icon" 
                                                            onclick="toggleTemplate(<?= $template['id'] ?>, <?= $template['is_attivo'] ?>)"
                                                            title="<?= $template['is_attivo'] ? 'Disattiva' : 'Attiva' ?>">
                                                        <?= $template['is_attivo'] ? '⏸️' : '▶️' ?>
                                                    </button>
                                                    
                                                    <?php if ($template['utilizzi_count'] == 0): ?>
                                                        <button class="btn-icon" 
                                                                onclick="deleteTemplate(<?= $template['id'] ?>)"
                                                                title="Elimina"
                                                                style="color: #ef4444;">
                                                            🗑️
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Form template -->
                    <div>
                        <div class="template-form" id="templateForm" style="<?= $selectedTemplate ? '' : 'display: none;' ?>">
                            <h3 class="card-title">
                                <?= $selectedTemplate ? 'Modifica Template' : 'Nuovo Template' ?>
                            </h3>
                            
                            <form method="POST" action="" id="templateFormElement">
                                <input type="hidden" name="action" value="<?= $selectedTemplate ? 'update_template' : 'create_template' ?>">
                                <?php if ($selectedTemplate): ?>
                                    <input type="hidden" name="template_id" value="<?= $selectedTemplate['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label for="nome">Nome template *</label>
                                    <input type="text" 
                                           id="nome" 
                                           name="nome" 
                                           value="<?= $selectedTemplate ? htmlspecialchars($selectedTemplate['nome']) : '' ?>"
                                           required
                                           placeholder="es. Dichiarazione Redditi Standard">
                                </div>
                                
                                <div class="form-group">
                                    <label for="tipo_pratica">Tipo pratica *</label>
                                    <select id="tipo_pratica" name="tipo_pratica" required>
                                        <option value="">-- Seleziona tipo --</option>
                                        <?php foreach (PRATICHE_TYPES as $key => $type): ?>
                                            <option value="<?= $key ?>" 
                                                    <?= $selectedTemplate && $selectedTemplate['tipo_pratica'] == $key ? 'selected' : '' ?>>
                                                <?= $type['icon'] ?> <?= $type['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="descrizione">Descrizione</label>
                                    <textarea id="descrizione" 
                                              name="descrizione" 
                                              placeholder="Descrivi quando utilizzare questo template..."><?= $selectedTemplate ? htmlspecialchars($selectedTemplate['descrizione']) : '' ?></textarea>
                                </div>
                                
                                <!-- Task builder -->
                                <div class="task-builder">
                                    <h4 class="task-builder-title">Task del template</h4>
                                    
                                    <div class="task-list" id="taskList">
                                        <?php if ($templateTasks): ?>
                                            <?php foreach ($templateTasks as $index => $task): ?>
                                                <div class="task-item" draggable="true">
                                                    <span class="task-handle">≡</span>
                                                    <div class="task-input">
                                                        <input type="text" 
                                                               name="tasks[<?= $index ?>][titolo]" 
                                                               value="<?= htmlspecialchars($task['titolo']) ?>"
                                                               placeholder="Titolo task" 
                                                               required>
                                                        <input type="number" 
                                                               name="tasks[<?= $index ?>][ore_stimate]" 
                                                               value="<?= $task['ore_stimate'] ?>"
                                                               placeholder="Ore" 
                                                               step="0.5" 
                                                               min="0.5">
                                                        <input type="hidden" 
                                                               name="tasks[<?= $index ?>][ordine]" 
                                                               value="<?= $index ?>">
                                                    </div>
                                                    <button type="button" class="task-remove" onclick="removeTask(this)">
                                                        ✕
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" class="add-task-btn" onclick="addTask()">
                                        + Aggiungi task
                                    </button>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <?= $selectedTemplate ? 'Salva modifiche' : 'Crea template' ?>
                                    </button>
                                    
                                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                                        Annulla
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let taskIndex = <?= count($templateTasks) ?>;
        
        // Show new template form
        function showNewTemplateForm() {
            document.getElementById('templateForm').style.display = 'block';
            document.getElementById('templateFormElement').reset();
            document.getElementById('taskList').innerHTML = '';
            taskIndex = 0;
            
            // Aggiungi un task di default
            addTask();
        }
        
        // Edit template
        function editTemplate(id) {
            window.location.href = '?action=pratiche&view=templates&edit=' + id;
        }
        
        // Toggle template
        function toggleTemplate(id, currentState) {
            if (confirm(currentState ? 'Disattivare questo template?' : 'Attivare questo template?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_template">
                    <input type="hidden" name="template_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Delete template
        function deleteTemplate(id) {
            if (confirm('Eliminare definitivamente questo template?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_template">
                    <input type="hidden" name="template_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Cancel edit
        function cancelEdit() {
            if (<?= $selectedTemplate ? 'true' : 'false' ?>) {
                window.location.href = '?action=pratiche&view=templates';
            } else {
                document.getElementById('templateForm').style.display = 'none';
            }
        }
        
        // Add task
        function addTask() {
            const taskList = document.getElementById('taskList');
            const taskHtml = `
                <div class="task-item" draggable="true">
                    <span class="task-handle">≡</span>
                    <div class="task-input">
                        <input type="text" 
                               name="tasks[${taskIndex}][titolo]" 
                               placeholder="Titolo task" 
                               required>
                        <input type="number" 
                               name="tasks[${taskIndex}][ore_stimate]" 
                               placeholder="Ore" 
                               step="0.5" 
                               min="0.5">
                        <input type="hidden" 
                               name="tasks[${taskIndex}][ordine]" 
                               value="${taskIndex}">
                    </div>
                    <button type="button" class="task-remove" onclick="removeTask(this)">
                        ✕
                    </button>
                </div>
            `;
            
            taskList.insertAdjacentHTML('beforeend', taskHtml);
            taskIndex++;
            
            // Re-init drag and drop
            initDragAndDrop();
        }
        
        // Remove task
        function removeTask(button) {
            button.closest('.task-item').remove();
            updateTaskOrder();
        }
        
        // Update task order
        function updateTaskOrder() {
            const tasks = document.querySelectorAll('.task-item');
            tasks.forEach((task, index) => {
                const orderInput = task.querySelector('input[name*="[ordine]"]');
                if (orderInput) {
                    orderInput.value = index;
                }
            });
        }
        
        // Drag and drop
        function initDragAndDrop() {
            const tasks = document.querySelectorAll('.task-item');
            let draggedElement = null;
            
            tasks.forEach(task => {
                task.addEventListener('dragstart', function(e) {
                    draggedElement = this;
                    this.classList.add('dragging');
                });
                
                task.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                });
                
                task.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    const afterElement = getDragAfterElement(this.parentNode, e.clientY);
                    if (afterElement == null) {
                        this.parentNode.appendChild(draggedElement);
                    } else {
                        this.parentNode.insertBefore(draggedElement, afterElement);
                    }
                });
            });
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
        
        // Init on load
        document.addEventListener('DOMContentLoaded', function() {
            initDragAndDrop();
        });
        
        // Update order on form submit
        document.getElementById('templateFormElement').addEventListener('submit', function(e) {
            updateTaskOrder();
        });
    </script>
</body>
</html>

<?php
// Helper functions

function createTemplate($data, $db) {
    try {
        $db->beginTransaction();
        
        // Inserisci template
        $templateId = $db->insert('pratiche_template', [
            'nome' => $data['nome'],
            'tipo_pratica' => $data['tipo_pratica'],
            'descrizione' => $data['descrizione'] ?? '',
            'ore_stimate_totali' => 0, // Calcolato dopo
            'is_attivo' => 1
        ]);
        
        // Inserisci task
        $oreTotali = 0;
        if (isset($data['tasks']) && is_array($data['tasks'])) {
            foreach ($data['tasks'] as $task) {
                if (!empty($task['titolo'])) {
                    $db->insert('pratiche_template_task', [
                        'template_id' => $templateId,
                        'titolo' => $task['titolo'],
                        'ore_stimate' => floatval($task['ore_stimate'] ?? 0),
                        'ordine' => intval($task['ordine'] ?? 0)
                    ]);
                    
                    $oreTotali += floatval($task['ore_stimate'] ?? 0);
                }
            }
        }
        
        // Aggiorna ore totali
        $db->update('pratiche_template', 
            ['ore_stimate_totali' => $oreTotali],
            'id = ?',
            [$templateId]
        );
        
        $db->commit();
        $_SESSION['success_message'] = '✅ Template creato con successo';
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error_message'] = '❌ Errore durante la creazione del template';
        error_log("Errore creazione template: " . $e->getMessage());
    }
}

function updateTemplate($data, $db) {
    try {
        $db->beginTransaction();
        
        $templateId = $data['template_id'];
        
        // Aggiorna template
        $db->update('pratiche_template', [
            'nome' => $data['nome'],
            'tipo_pratica' => $data['tipo_pratica'],
            'descrizione' => $data['descrizione'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$templateId]);
        
        // Elimina task esistenti
        $db->delete('pratiche_template_task', 'template_id = ?', [$templateId]);
        
        // Inserisci nuovi task
        $oreTotali = 0;
        if (isset($data['tasks']) && is_array($data['tasks'])) {
            foreach ($data['tasks'] as $task) {
                if (!empty($task['titolo'])) {
                    $db->insert('pratiche_template_task', [
                        'template_id' => $templateId,
                        'titolo' => $task['titolo'],
                        'ore_stimate' => floatval($task['ore_stimate'] ?? 0),
                        'ordine' => intval($task['ordine'] ?? 0)
                    ]);
                    
                    $oreTotali += floatval($task['ore_stimate'] ?? 0);
                }
            }
        }
        
        // Aggiorna ore totali
        $db->update('pratiche_template', 
            ['ore_stimate_totali' => $oreTotali],
            'id = ?',
            [$templateId]
        );
        
        $db->commit();
        $_SESSION['success_message'] = '✅ Template aggiornato con successo';
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error_message'] = '❌ Errore durante l\'aggiornamento del template';
        error_log("Errore update template: " . $e->getMessage());
    }
}

function deleteTemplate($templateId, $db) {
    try {
        $db->beginTransaction();
        
        // Verifica che non sia utilizzato
        $utilizzi = $db->selectOne(
            "SELECT COUNT(*) as count FROM pratiche WHERE template_id = ?",
            [$templateId]
        );
        
        if ($utilizzi['count'] > 0) {
            $_SESSION['error_message'] = '⚠️ Template in uso, impossibile eliminare';
            return;
        }
        
        // Elimina task
        $db->delete('pratiche_template_task', 'template_id = ?', [$templateId]);
        
        // Elimina template
        $db->delete('pratiche_template', 'id = ?', [$templateId]);
        
        $db->commit();
        $_SESSION['success_message'] = '✅ Template eliminato con successo';
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error_message'] = '❌ Errore durante l\'eliminazione del template';
        error_log("Errore delete template: " . $e->getMessage());
    }
}

function toggleTemplate($templateId, $db) {
    try {
        $template = $db->selectOne(
            "SELECT is_attivo FROM pratiche_template WHERE id = ?",
            [$templateId]
        );
        
        if ($template) {
            $newStatus = !$template['is_attivo'];
            $db->update('pratiche_template',
                ['is_attivo' => $newStatus],
                'id = ?',
                [$templateId]
            );
            
            $_SESSION['success_message'] = $newStatus ? 
                '✅ Template attivato' : 
                '✅ Template disattivato';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = '❌ Errore durante l\'aggiornamento dello stato';
        error_log("Errore toggle template: " . $e->getMessage());
    }
}
?>