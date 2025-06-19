<?php
/**
 * modules/pratiche/task_manager.php - Gestione Task Pratica
 * 
 * ✅ INTERFACCIA DRAG & DROP PER GESTIONE TASK
 * 
 * Features:
 * - Lista task con drag & drop per riordinamento
 * - Gestione dipendenze tra task
 * - Assegnazione operatori
 * - Progress tracking visuale
 * - Checklist subtask
 * - Note e allegati per task
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata dal router)

// Carica task della pratica con info complete
$tasks = $db->select("
    SELECT 
        t.*,
        CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
        o.email as operatore_email,
        (SELECT COUNT(*) FROM task_documenti WHERE task_id = t.id) as documenti_count,
        (SELECT COUNT(*) FROM task_note WHERE task_id = t.id) as note_count,
        (SELECT SUM(durata_minuti) FROM tracking_task WHERE task_id = t.id) as minuti_tracciati
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    WHERE t.pratica_id = ?
    ORDER BY t.ordine, t.id
", [$pratica['id']]);

// Carica operatori per assegnazione
$operatori = $db->select("
    SELECT id, CONCAT(nome, ' ', cognome) as nome_completo, email
    FROM operatori
    WHERE is_attivo = 1
    ORDER BY nome, cognome
");

// Calcola statistiche
$stats = [
    'totali' => count($tasks),
    'completati' => count(array_filter($tasks, fn($t) => $t['stato'] === 'completato')),
    'in_corso' => count(array_filter($tasks, fn($t) => $t['stato'] === 'in_corso')),
    'da_fare' => count(array_filter($tasks, fn($t) => $t['stato'] === 'da_fare')),
    'ore_stimate' => array_sum(array_column($tasks, 'ore_stimate')),
    'ore_lavorate' => round(array_sum(array_map(fn($t) => ($t['minuti_tracciati'] ?? 0) / 60, $tasks)), 2)
];

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_task':
            $taskId = $db->insert('task', [
                'pratica_id' => $pratica['id'],
                'cliente_id' => $pratica['cliente_id'],
                'titolo' => $_POST['titolo'],
                'descrizione' => $_POST['descrizione'] ?? '',
                'ore_stimate' => floatval($_POST['ore_stimate'] ?? 0),
                'operatore_assegnato_id' => $_POST['operatore_id'] ?: null,
                'data_scadenza' => $_POST['data_scadenza'] ?: null,
                'stato' => 'da_fare',
                'ordine' => count($tasks) + 1
            ]);
            
            $_SESSION['success_message'] = '✅ Task creato con successo';
            header('Location: /crm/?action=pratiche&view=task_manager&id=' . $pratica['id']);
            exit;
            break;
            
        case 'update_task':
            $taskId = (int)$_POST['task_id'];
            $db->update('task', [
                'titolo' => $_POST['titolo'],
                'descrizione' => $_POST['descrizione'],
                'ore_stimate' => floatval($_POST['ore_stimate']),
                'operatore_assegnato_id' => $_POST['operatore_id'] ?: null,
                'data_scadenza' => $_POST['data_scadenza'] ?: null,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ? AND pratica_id = ?', [$taskId, $pratica['id']]);
            
            $_SESSION['success_message'] = '✅ Task aggiornato';
            header('Location: /crm/?action=pratiche&view=task_manager&id=' . $pratica['id']);
            exit;
            break;
            
        case 'change_stato':
            $taskId = (int)$_POST['task_id'];
            $nuovoStato = $_POST['stato'];
            
            $db->update('task', [
                'stato' => $nuovoStato,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ? AND pratica_id = ?', [$taskId, $pratica['id']]);
            
            // Se completato, segna percentuale 100%
            if ($nuovoStato === 'completato') {
                $db->update('task', 
                    ['percentuale_completamento' => 100], 
                    'id = ?', [$taskId]
                );
            }
            
            echo json_encode(['success' => true]);
            exit;
            break;
            
        case 'reorder_tasks':
            $order = json_decode($_POST['order'], true);
            foreach ($order as $index => $taskId) {
                $db->update('task', 
                    ['ordine' => $index + 1], 
                    'id = ? AND pratica_id = ?', 
                    [$taskId, $pratica['id']]
                );
            }
            echo json_encode(['success' => true]);
            exit;
            break;
            
        case 'delete_task':
            $taskId = (int)$_POST['task_id'];
            
            // Verifica che non ci siano dipendenze
            $dipendenze = $db->selectOne(
                "SELECT COUNT(*) as count FROM task WHERE dipende_da_task_id = ?",
                [$taskId]
            );
            
            if ($dipendenze['count'] > 0) {
                $_SESSION['error_message'] = '⚠️ Impossibile eliminare: altri task dipendono da questo';
            } else {
                $db->delete('task', 'id = ? AND pratica_id = ?', [$taskId, $pratica['id']]);
                $_SESSION['success_message'] = '✅ Task eliminato';
            }
            
            header('Location: /crm/?action=pratiche&view=task_manager&id=' . $pratica['id']);
            exit;
            break;
    }
}

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
?>

<!-- Container principale con padding ridotto -->
<div class="px-3 py-2">
    <!-- Header compatto -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche">Pratiche</a></li>
                    <li class="breadcrumb-item"><a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>"><?= htmlspecialchars($pratica['titolo']) ?></a></li>
                    <li class="breadcrumb-item active">Gestione Task</li>
                </ol>
            </nav>
            <h4 class="mb-0">
                <i class="bi bi-list-task text-primary"></i> Gestione Task
            </h4>
        </div>
        
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.history.back()">
                <i class="bi bi-arrow-left"></i> Indietro
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-circle"></i> Nuovo Task
            </button>
        </div>
    </div>
    
    <!-- Stats cards compatte -->
    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="h5 mb-0"><?= $stats['totali'] ?></div>
                    <small class="text-muted">Totali</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="h5 mb-0 text-success"><?= $stats['completati'] ?></div>
                    <small class="text-muted">Completati</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="h5 mb-0 text-warning"><?= $stats['in_corso'] ?></div>
                    <small class="text-muted">In Corso</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="h5 mb-0 text-info"><?= $stats['da_fare'] ?></div>
                    <small class="text-muted">Da Fare</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="h5 mb-0"><?= $stats['ore_stimate'] ?>h</div>
                    <small class="text-muted">Ore Stimate</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="h5 mb-0 <?= $stats['ore_lavorate'] > $stats['ore_stimate'] ? 'text-danger' : '' ?>">
                        <?= $stats['ore_lavorate'] ?>h
                    </div>
                    <small class="text-muted">Ore Lavorate</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista task kanban style -->
    <div class="row g-2">
        <!-- Colonna DA FARE -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0"><i class="bi bi-circle text-info"></i> Da Fare</h6>
                </div>
                <div class="card-body p-2 task-column" data-stato="da_fare" style="min-height: 400px;">
                    <?php foreach ($tasks as $task): ?>
                        <?php if ($task['stato'] === 'da_fare'): ?>
                            <?php include __DIR__ . '/components/task_card.php'; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Colonna IN CORSO -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-warning bg-opacity-10 py-2">
                    <h6 class="mb-0"><i class="bi bi-arrow-repeat text-warning"></i> In Corso</h6>
                </div>
                <div class="card-body p-2 task-column" data-stato="in_corso" style="min-height: 400px;">
                    <?php foreach ($tasks as $task): ?>
                        <?php if ($task['stato'] === 'in_corso'): ?>
                            <?php include __DIR__ . '/components/task_card.php'; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Colonna COMPLETATI -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success bg-opacity-10 py-2">
                    <h6 class="mb-0"><i class="bi bi-check-circle text-success"></i> Completati</h6>
                </div>
                <div class="card-body p-2 task-column" data-stato="completato" style="min-height: 400px;">
                    <?php foreach ($tasks as $task): ?>
                        <?php if ($task['stato'] === 'completato'): ?>
                            <?php include __DIR__ . '/components/task_card.php'; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuovo Task -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_task">
                
                <div class="modal-header">
                    <h5 class="modal-title">Nuovo Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titolo *</label>
                        <input type="text" name="titolo" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ore stimate</label>
                                <input type="number" name="ore_stimate" class="form-control" 
                                       step="0.5" min="0" value="1">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Scadenza</label>
                                <input type="date" name="data_scadenza" class="form-control"
                                       min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assegna a</label>
                        <select name="operatore_id" class="form-select">
                            <option value="">Non assegnato</option>
                            <?php foreach ($operatori as $op): ?>
                                <option value="<?= $op['id'] ?>" 
                                        <?= $op['id'] == $currentUser['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CSS Specifico -->
<style>
.task-card {
    cursor: move;
    transition: all 0.2s;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.task-card.dragging {
    opacity: 0.5;
}

.task-column {
    background: #f8f9fa;
    border-radius: 0.375rem;
}

.task-column.drag-over {
    background: #e9ecef;
    border: 2px dashed #6c757d;
}

.task-progress {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.task-progress-bar {
    height: 100%;
    background: #0d6efd;
    transition: width 0.3s;
}
</style>

<!-- JavaScript per Drag & Drop -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inizializza drag & drop
    initDragAndDrop();
    
    // Gestione cambio stato rapido
    document.querySelectorAll('.task-stato-select').forEach(select => {
        select.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            const nuovoStato = this.value;
            
            fetch(/crm/modules/pratiche/task_manager&id=<?= $pratica['id'] ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=change_stato&task_id=${taskId}&stato=${nuovoStato}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
    });
});

function initDragAndDrop() {
    const taskCards = document.querySelectorAll('.task-card');
    const columns = document.querySelectorAll('.task-column');
    
    taskCards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragleave', handleDragLeave);
    });
}

let draggedElement = null;

function handleDragStart(e) {
    draggedElement = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    
    document.querySelectorAll('.task-column').forEach(column => {
        column.classList.remove('drag-over');
    });
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    
    e.dataTransfer.dropEffect = 'move';
    this.classList.add('drag-over');
    
    return false;
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    const targetColumn = this;
    const nuovoStato = targetColumn.dataset.stato;
    const taskId = draggedElement.dataset.taskId;
    
    // Aggiorna stato nel backend
    fetch(/crm/modules/pratiche/task_manager&id=<?= $pratica['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=change_stato&task_id=${taskId}&stato=${nuovoStato}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            targetColumn.appendChild(draggedElement);
            draggedElement.querySelector('.task-stato-select').value = nuovoStato;
        }
    });
    
    return false;
}
</script>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/footer.php';
?>