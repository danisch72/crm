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

<!-- Container principale -->
<div class="px-3 py-2">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche">Pratiche</a></li>
                    <li class="breadcrumb-item active">Gestione Template</li>
                </ol>
            </nav>
            <h4 class="mb-0">
                <i class="bi bi-file-earmark-text text-primary"></i> Gestione Template
            </h4>
        </div>
        
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.history.back()">
                <i class="bi bi-arrow-left"></i> Indietro
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                <i class="bi bi-plus-circle"></i> Nuovo Template
            </button>
        </div>
    </div>
    
    <div class="row">
        <!-- Lista template (sinistra) -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Template Disponibili</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach (PRATICHE_TYPES as $tipo => $config): ?>
                            <?php if (isset($templatesByType[$tipo]) && count($templatesByType[$tipo]) > 0): ?>
                                <div class="list-group-item bg-light">
                                    <small class="text-muted fw-bold">
                                        <?= $config['icon'] ?> <?= $config['label'] ?>
                                    </small>
                                </div>
                                
                                <?php foreach ($templatesByType[$tipo] as $template): ?>
                                    <a href="?action=pratiche&view=templates&edit=<?= $template['id'] ?>" 
                                       class="list-group-item list-group-item-action <?= $selectedTemplate && $selectedTemplate['id'] == $template['id'] ? 'active' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($template['nome']) ?></h6>
                                                <small class="text-muted">
                                                    <?= $template['task_count'] ?> task • 
                                                    <?= $template['ore_totali_stimate'] ?>h • 
                                                    <?= $template['utilizzi_count'] ?> utilizzi
                                                </small>
                                            </div>
                                            <?php if (!$template['is_attivo']): ?>
                                                <span class="badge bg-secondary">Disattivo</span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if (empty($templates)): ?>
                            <div class="list-group-item text-center text-muted py-5">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-2">Nessun template creato</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dettaglio template (destra) -->
        <div class="col-md-8">
            <?php if ($selectedTemplate): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?= htmlspecialchars($selectedTemplate['nome']) ?>
                            </h5>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editTemplateModal">
                                    <i class="bi bi-pencil"></i> Modifica
                                </button>
                                
                                <form method="POST" class="d-inline" 
                                      onsubmit="return confirm('Disattivare questo template?')">
                                    <input type="hidden" name="action" value="toggle_template">
                                    <input type="hidden" name="template_id" value="<?= $selectedTemplate['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-toggle-<?= $selectedTemplate['is_attivo'] ? 'on' : 'off' ?>"></i>
                                        <?= $selectedTemplate['is_attivo'] ? 'Disattiva' : 'Attiva' ?>
                                    </button>
                                </form>
                                
                                <?php if ($selectedTemplate['utilizzi_count'] == 0): ?>
                                    <form method="POST" class="d-inline" 
                                          onsubmit="return confirm('Eliminare definitivamente questo template?')">
                                        <input type="hidden" name="action" value="delete_template">
                                        <input type="hidden" name="template_id" value="<?= $selectedTemplate['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Elimina
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Info template -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="text-muted small">Tipo Pratica</label>
                                <p class="mb-0">
                                    <?= PRATICHE_TYPES[$selectedTemplate['tipo_pratica']]['icon'] ?>
                                    <?= PRATICHE_TYPES[$selectedTemplate['tipo_pratica']]['label'] ?>
                                </p>
                            </div>
                            <div class="col-md-2">
                                <label class="text-muted small">Ore Totali</label>
                                <p class="mb-0 fw-bold"><?= $selectedTemplate['ore_totali_stimate'] ?>h</p>
                            </div>
                            <div class="col-md-2">
                                <label class="text-muted small">Tariffa</label>
                                <p class="mb-0">€ <?= number_format($selectedTemplate['tariffa_consigliata'], 2, ',', '.') ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="text-muted small">Giorni</label>
                                <p class="mb-0"><?= $selectedTemplate['giorni_completamento'] ?>gg</p>
                            </div>
                            <div class="col-md-2">
                                <label class="text-muted small">Utilizzi</label>
                                <p class="mb-0"><?= $selectedTemplate['utilizzi_count'] ?></p>
                            </div>
                        </div>
                        
                        <?php if ($selectedTemplate['descrizione']): ?>
                            <div class="mb-4">
                                <label class="text-muted small">Descrizione</label>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($selectedTemplate['descrizione'])) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Lista task -->
                        <h6 class="mb-3">Task del Template</h6>
                        
                        <?php if (count($templateTasks) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th width="40">#</th>
                                            <th>Titolo Task</th>
                                            <th width="100">Ore Stimate</th>
                                            <th width="120">Dopo (giorni)</th>
                                            <th width="100">Obbligatorio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templateTasks as $task): ?>
                                            <tr>
                                                <td class="text-muted"><?= $task['ordine'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($task['titolo']) ?></strong>
                                                    <?php if ($task['descrizione']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($task['descrizione']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $task['ore_stimate'] ?>h</td>
                                                <td>+<?= $task['giorni_dopo_inizio'] ?></td>
                                                <td>
                                                    <?php if ($task['is_obbligatorio']): ?>
                                                        <span class="badge bg-success">Sì</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold">
                                            <td colspan="2">Totale</td>
                                            <td><?= array_sum(array_column($templateTasks, 'ore_stimate')) ?>h</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                Nessun task definito per questo template
                            </div>
                        <?php endif; ?>
                        
                        <!-- Note operative -->
                        <?php if ($selectedTemplate['note_operative']): ?>
                            <div class="mt-4">
                                <h6>Note Operative</h6>
                                <div class="alert alert-info">
                                    <?= nl2br(htmlspecialchars($selectedTemplate['note_operative'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-arrow-left display-4 text-muted"></i>
                        <p class="mt-3 text-muted">Seleziona un template dalla lista per visualizzarne i dettagli</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nuovo Template -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="createTemplateForm">
                <input type="hidden" name="action" value="create_template">
                
                <div class="modal-header">
                    <h5 class="modal-title">Nuovo Template Pratica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nome Template *</label>
                                <input type="text" name="nome" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tipo Pratica *</label>
                                <select name="tipo_pratica" class="form-select" required>
                                    <option value="">-- Seleziona --</option>
                                    <?php foreach (PRATICHE_TYPES as $tipo => $config): ?>
                                        <option value="<?= $tipo ?>">
                                            <?= $config['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tariffa consigliata (€)</label>
                                <input type="number" name="tariffa_consigliata" class="form-control" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Giorni completamento</label>
                                <input type="number" name="giorni_completamento" class="form-control" 
                                       value="30" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Task del template -->
                    <h6 class="mt-4 mb-3">Task del Template</h6>
                    
                    <div id="tasks-container">
                        <!-- Template task row -->
                        <div class="task-row mb-2">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="tasks[0][titolo]" 
                                           class="form-control form-control-sm" 
                                           placeholder="Titolo task">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="tasks[0][ore_stimate]" 
                                           class="form-control form-control-sm" 
                                           placeholder="Ore" step="0.5" min="0">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="tasks[0][ordine]" 
                                           class="form-control form-control-sm" 
                                           value="1" min="1">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-danger remove-task">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-task-btn">
                        <i class="bi bi-plus"></i> Aggiungi Task
                    </button>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Template (se selezionato) -->
<?php if ($selectedTemplate): ?>
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_template">
                <input type="hidden" name="template_id" value="<?= $selectedTemplate['id'] ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nome Template *</label>
                                <input type="text" name="nome" class="form-control" 
                                       value="<?= htmlspecialchars($selectedTemplate['nome']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tipo Pratica *</label>
                                <select name="tipo_pratica" class="form-select" required>
                                    <?php foreach (PRATICHE_TYPES as $tipo => $config): ?>
                                        <option value="<?= $tipo ?>" 
                                                <?= $selectedTemplate['tipo_pratica'] == $tipo ? 'selected' : '' ?>>
                                            <?= $config['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="2"><?= htmlspecialchars($selectedTemplate['descrizione'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Task esistenti -->
                    <h6 class="mt-4 mb-3">Task del Template</h6>
                    
                    <div id="edit-tasks-container">
                        <?php foreach ($templateTasks as $index => $task): ?>
                            <div class="task-row mb-2">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="text" name="tasks[<?= $index ?>][titolo]" 
                                               class="form-control form-control-sm" 
                                               value="<?= htmlspecialchars($task['titolo']) ?>"
                                               placeholder="Titolo task">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="tasks[<?= $index ?>][ore_stimate]" 
                                               class="form-control form-control-sm" 
                                               value="<?= $task['ore_stimate'] ?>"
                                               placeholder="Ore" step="0.5" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="tasks[<?= $index ?>][ordine]" 
                                               class="form-control form-control-sm" 
                                               value="<?= $task['ordine'] ?>" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-danger remove-task">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-edit-task-btn">
                        <i class="bi bi-plus"></i> Aggiungi Task
                    </button>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript -->
<script>
// Gestione aggiunta/rimozione task dinamici
document.addEventListener('DOMContentLoaded', function() {
    let taskIndex = 1;
    let editTaskIndex = <?= count($templateTasks) ?>;
    
    // Nuovo template
    document.getElementById('add-task-btn')?.addEventListener('click', function() {
        const container = document.getElementById('tasks-container');
        const newRow = createTaskRow(taskIndex++);
        container.appendChild(newRow);
    });
    
    // Modifica template
    document.getElementById('add-edit-task-btn')?.addEventListener('click', function() {
        const container = document.getElementById('edit-tasks-container');
        const newRow = createTaskRow(editTaskIndex++);
        container.appendChild(newRow);
    });
    
    // Rimozione task
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-task') || e.target.closest('.remove-task')) {
            const row = e.target.closest('.task-row');
            if (row) {
                row.remove();
            }
        }
    });
    
    function createTaskRow(index) {
        const div = document.createElement('div');
        div.className = 'task-row mb-2';
        div.innerHTML = `
            <div class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="tasks[${index}][titolo]" 
                           class="form-control form-control-sm" 
                           placeholder="Titolo task">
                </div>
                <div class="col-md-2">
                    <input type="number" name="tasks[${index}][ore_stimate]" 
                           class="form-control form-control-sm" 
                           placeholder="Ore" step="0.5" min="0">
                </div>
                <div class="col-md-2">
                    <input type="number" name="tasks[${index}][ordine]" 
                           class="form-control form-control-sm" 
                           value="${index + 1}" min="1">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-danger remove-task">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        return div;
    }
});
</script>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/footer.php';

// Funzioni helper per gestione template
function createTemplate($data, $db) {
    try {
        $db->beginTransaction();
        
        // Inserisci template
        $templateId = $db->insert('pratiche_template', [
            'nome' => $data['nome'],
            'tipo_pratica' => $data['tipo_pratica'],
            'descrizione' => $data['descrizione'] ?? '',
            'tariffa_consigliata' => floatval($data['tariffa_consigliata'] ?? 0),
            'giorni_completamento' => intval($data['giorni_completamento'] ?? 30),
            'is_attivo' => 1,
            'created_by' => $_SESSION['user_id'] ?? null
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
            ['ore_totali_stimate' => $oreTotali],
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
            ['ore_totali_stimate' => $oreTotali],
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
        // Recupera stato attuale
        $template = $db->selectOne("SELECT is_attivo FROM pratiche_template WHERE id = ?", [$templateId]);
        
        if (!$template) {
            $_SESSION['error_message'] = '⚠️ Template non trovato';
            return;
        }
        
        // Inverti stato
        $nuovoStato = !$template['is_attivo'];
        
        $db->update('pratiche_template', 
            ['is_attivo' => $nuovoStato],
            'id = ?',
            [$templateId]
        );
        
        $_SESSION['success_message'] = $nuovoStato ? 
            '✅ Template attivato' : 
            '✅ Template disattivato';
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = '❌ Errore durante la modifica dello stato';
        error_log("Errore toggle template: " . $e->getMessage());
    }
}
?>