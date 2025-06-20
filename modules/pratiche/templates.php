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
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php';

// Funzioni per gestione template
function createTemplate($data, $db) {
    try {
        $templateId = $db->insert('pratiche_template', [
            'nome' => $data['nome'],
            'tipo_pratica' => $data['tipo_pratica'],
            'descrizione' => $data['descrizione'] ?? '',
            'ore_totali_stimate' => floatval($data['ore_totali'] ?? 0),
            'tariffa_consigliata' => floatval($data['tariffa'] ?? 0),
            'giorni_completamento' => intval($data['giorni'] ?? 30),
            'is_attivo' => 1,
            'created_by' => $_SESSION['user_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $_SESSION['success_message'] = '✅ Template creato con successo';
        return $templateId;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Errore nella creazione del template';
        return false;
    }
}

function updateTemplate($data, $db) {
    try {
        $db->update('pratiche_template', [
            'nome' => $data['nome'],
            'tipo_pratica' => $data['tipo_pratica'],
            'descrizione' => $data['descrizione'] ?? '',
            'ore_totali_stimate' => floatval($data['ore_totali'] ?? 0),
            'tariffa_consigliata' => floatval($data['tariffa'] ?? 0),
            'giorni_completamento' => intval($data['giorni'] ?? 30),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$data['template_id']]);
        
        $_SESSION['success_message'] = '✅ Template aggiornato con successo';
        return true;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Errore nell\'aggiornamento del template';
        return false;
    }
}

function deleteTemplate($templateId, $db) {
    try {
        $db->delete('pratiche_template', 'id = ?', [$templateId]);
        $_SESSION['success_message'] = '✅ Template eliminato con successo';
        return true;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Errore nell\'eliminazione del template';
        return false;
    }
}

function toggleTemplate($templateId, $db) {
    try {
        $db->query(
            "UPDATE pratiche_template SET is_attivo = NOT is_attivo WHERE id = ?",
            [$templateId]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Template Pratiche - CRM Re.De</title>
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <style>
        /* Container principale */
        .templates-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Layout due colonne */
        .templates-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 1200px) {
            .templates-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        /* Template list */
        .templates-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .templates-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .tipo-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .templates-list {
            padding: 0.75rem;
        }
        
        .template-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
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
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .task-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            background: white;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .task-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e5e7eb;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }
        
        .add-task-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 2px dashed #d1d5db;
            border-radius: 6px;
            background: white;
            color: #6b7280;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.75rem;
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
        
        <div class="main-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="templates-container">
                    <!-- Header -->
                    <div class="page-header">
                        <h1 class="page-title">🎯 Gestione Template Pratiche</h1>
                        <button class="btn btn-primary" onclick="showNewTemplateForm()">
                            ➕ Nuovo Template
                        </button>
                    </div>
                    
                    <!-- Statistiche -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= count($templates) ?></div>
                            <div class="stat-label">Template Totali</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?= count(array_filter($templates, fn($t) => $t['is_attivo'])) ?>
                            </div>
                            <div class="stat-label">Template Attivi</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?= array_sum(array_column($templates, 'utilizzi_count')) ?>
                            </div>
                            <div class="stat-label">Pratiche Create</div>
                        </div>
                    </div>
                    
                    <!-- Layout due colonne -->
                    <div class="templates-layout">
                        <!-- Lista Template -->
                        <div>
                            <?php foreach (PRATICHE_TYPES as $tipo => $config): ?>
                                <?php if (!isset($templatesByType[$tipo]) || empty($templatesByType[$tipo])) continue; ?>
                                
                                <div class="templates-card" style="margin-bottom: 1rem;">
                                    <div class="templates-header">
                                        <div class="tipo-title">
                                            <span style="color: <?= $config['color'] ?>"><?= $config['icon'] ?></span>
                                            <?= $config['label'] ?>
                                            <span style="color: #6b7280; font-weight: normal;">
                                                (<?= count($templatesByType[$tipo]) ?>)
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="templates-list">
                                        <?php foreach ($templatesByType[$tipo] as $template): ?>
                                        <div class="template-item <?= !$template['is_attivo'] ? 'inactive' : '' ?>">
                                            <div class="template-info">
                                                <div class="template-name">
                                                    <?= htmlspecialchars($template['nome']) ?>
                                                    <?php if (!$template['is_attivo']): ?>
                                                        <span style="color: #dc2626; font-size: 0.75rem;">(Disattivo)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="template-meta">
                                                    <span>📋 <?= $template['task_count'] ?> task</span>
                                                    <span>⏱️ <?= $template['ore_totali_stimate'] ?>h</span>
                                                    <span>💰 €<?= number_format($template['tariffa_consigliata'], 0) ?></span>
                                                    <span>📊 Usato <?= $template['utilizzi_count'] ?>x</span>
                                                </div>
                                            </div>
                                            <div class="template-actions">
                                                <button class="btn-icon" 
                                                        onclick="editTemplate(<?= $template['id'] ?>)"
                                                        title="Modifica">
                                                    ✏️
                                                </button>
                                                <button class="btn-icon" 
                                                        onclick="toggleTemplate(<?= $template['id'] ?>)"
                                                        title="<?= $template['is_attivo'] ? 'Disattiva' : 'Attiva' ?>">
                                                    <?= $template['is_attivo'] ? '🔓' : '🔒' ?>
                                                </button>
                                                <?php if ($template['utilizzi_count'] == 0): ?>
                                                <button class="btn-icon" 
                                                        onclick="deleteTemplate(<?= $template['id'] ?>)"
                                                        title="Elimina">
                                                    🗑️
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($templates)): ?>
                            <div class="templates-card">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <p>Nessun template creato</p>
                                    <button class="btn btn-primary" onclick="showNewTemplateForm()">
                                        Crea il primo template
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Form Template -->
                        <div>
                            <form class="template-form" method="POST" id="templateForm">
                                <input type="hidden" name="action" value="<?= $selectedTemplate ? 'update_template' : 'create_template' ?>">
                                <?php if ($selectedTemplate): ?>
                                    <input type="hidden" name="template_id" value="<?= $selectedTemplate['id'] ?>">
                                <?php endif; ?>
                                
                                <h3 style="margin-top: 0;">
                                    <?= $selectedTemplate ? '✏️ Modifica Template' : '➕ Nuovo Template' ?>
                                </h3>
                                
                                <div class="form-group">
                                    <label>Nome Template</label>
                                    <input type="text" 
                                           name="nome" 
                                           value="<?= htmlspecialchars($selectedTemplate['nome'] ?? '') ?>"
                                           placeholder="es. Dichiarazione IVA Standard"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Tipo Pratica</label>
                                    <select name="tipo_pratica" required>
                                        <option value="">Seleziona tipo...</option>
                                        <?php foreach (PRATICHE_TYPES as $key => $tipo): ?>
                                        <option value="<?= $key ?>" 
                                                <?= ($selectedTemplate['tipo_pratica'] ?? '') === $key ? 'selected' : '' ?>>
                                            <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Descrizione</label>
                                    <textarea name="descrizione" 
                                              rows="3"
                                              placeholder="Descrizione template..."><?= htmlspecialchars($selectedTemplate['descrizione'] ?? '') ?></textarea>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label>Ore Totali</label>
                                        <input type="number" 
                                               name="ore_totali" 
                                               value="<?= $selectedTemplate['ore_totali_stimate'] ?? '' ?>"
                                               min="0" 
                                               step="0.5"
                                               placeholder="0">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Tariffa (€)</label>
                                        <input type="number" 
                                               name="tariffa" 
                                               value="<?= $selectedTemplate['tariffa_consigliata'] ?? '' ?>"
                                               min="0" 
                                               step="10"
                                               placeholder="0">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Giorni</label>
                                        <input type="number" 
                                               name="giorni" 
                                               value="<?= $selectedTemplate['giorni_completamento'] ?? 30 ?>"
                                               min="1"
                                               placeholder="30">
                                    </div>
                                </div>
                                
                                <?php if ($selectedTemplate): ?>
                                <!-- Task Builder -->
                                <div class="task-builder">
                                    <h4 class="task-builder-title">Task del Template</h4>
                                    
                                    <div class="task-list" id="templateTaskList">
                                        <?php foreach ($templateTasks as $index => $task): ?>
                                        <div class="task-item">
                                            <div class="task-controls">
                                                <span class="task-number"><?= $index + 1 ?></span>
                                                <input type="text" 
                                                       value="<?= htmlspecialchars($task['titolo']) ?>"
                                                       style="flex: 1; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px;">
                                                <input type="number" 
                                                       value="<?= $task['ore_stimate'] ?>"
                                                       placeholder="Ore"
                                                       min="0" 
                                                       step="0.5"
                                                       style="width: 80px; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px;">
                                                <button type="button" class="btn-icon" onclick="removeTemplateTask(this)">
                                                    🗑️
                                                </button>
                                            </div>
                                            <textarea rows="2" 
                                                      placeholder="Descrizione task..."
                                                      style="width: 100%; margin-top: 0.5rem; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px;"><?= htmlspecialchars($task['descrizione'] ?? '') ?></textarea>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button type="button" class="add-task-btn" onclick="addTemplateTask()">
                                        ➕ Aggiungi Task
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="form-actions">
                                    <?php if ($selectedTemplate): ?>
                                        <a href="/crm/?action=pratiche&view=templates" class="btn btn-secondary">
                                            Annulla
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary">
                                        💾 <?= $selectedTemplate ? 'Salva Modifiche' : 'Crea Template' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function showNewTemplateForm() {
            document.getElementById('templateForm').reset();
            document.querySelector('[name="action"]').value = 'create_template';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function editTemplate(id) {
            window.location.href = `/crm/?action=pratiche&view=templates&edit=${id}`;
        }
        
        function toggleTemplate(id) {
            if (confirm('Vuoi cambiare lo stato di questo template?')) {
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
        
        function addTemplateTask() {
            const taskList = document.getElementById('templateTaskList');
            const taskCount = taskList.children.length;
            
            const newTask = document.createElement('div');
            newTask.className = 'task-item';
            newTask.innerHTML = `
                <div class="task-controls">
                    <span class="task-number">${taskCount + 1}</span>
                    <input type="text" 
                           placeholder="Titolo task..."
                           style="flex: 1; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px;">
                    <input type="number" 
                           placeholder="Ore"
                           min="0" 
                           step="0.5"
                           style="width: 80px; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px;">
                    <button type="button" class="btn-icon" onclick="removeTemplateTask(this)">
                        🗑️
                    </button>
                </div>
                <textarea rows="2" 
                          placeholder="Descrizione task..."
                          style="width: 100%; margin-top: 0.5rem; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px;"></textarea>
            `;
            
            taskList.appendChild(newTask);
        }
        
        function removeTemplateTask(button) {
            button.closest('.task-item').remove();
            // Rinumera task
            document.querySelectorAll('#templateTaskList .task-number').forEach((num, idx) => {
                num.textContent = idx + 1;
            });
        }
    </script>
</body>
</html>