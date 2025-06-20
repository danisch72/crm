<?php
/**
 * modules/pratiche/index_list.php - Lista Pratiche + Vista Kanban
 * 
 * ✅ GESTIONE PRATICHE CON VISTA KANBAN E LISTA
 * ✅ CSS COMPLETAMENTE CENTRALIZZATO
 * ✅ ZERO STILI INLINE
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router disponibili:
// $sessionInfo, $db, $currentUser, $error_message, $success_message

// Parametri filtro
$filterCliente = $_GET['cliente'] ?? '';
$filterTipo = $_GET['tipo'] ?? '';
$filterStato = $_GET['stato'] ?? '';
$filterOperatore = $_GET['operatore'] ?? '';
$viewMode = $_GET['mode'] ?? PRATICHE_UI_CONFIG['default_view'];

// Costruisci query base
$whereConditions = ["1=1"];
$params = [];

if ($filterCliente) {
    $whereConditions[] = "c.ragione_sociale LIKE ?";
    $params[] = "%$filterCliente%";
}

if ($filterTipo) {
    $whereConditions[] = "p.tipo_pratica = ?";
    $params[] = $filterTipo;
}

if ($filterStato && $filterStato !== 'all') {
    if ($filterStato === 'active') {
        $whereConditions[] = "p.stato NOT IN ('completata', 'fatturata', 'archiviata')";
    } else {
        $whereConditions[] = "p.stato = ?";
        $params[] = $filterStato;
    }
} else if (!PRATICHE_UI_CONFIG['show_archived']) {
    $whereConditions[] = "p.stato != 'archiviata'";
}

// Se non admin, mostra solo le proprie pratiche
if (!$currentUser['is_admin']) {
    $whereConditions[] = "p.operatore_assegnato_id = ?";
    $params[] = $currentUser['id'];
} else if ($filterOperatore) {
    $whereConditions[] = "p.operatore_assegnato_id = ?";
    $params[] = $filterOperatore;
}

$whereClause = implode(" AND ", $whereConditions);

// Carica pratiche con informazioni complete
$query = "
    SELECT 
        p.*,
        c.ragione_sociale as cliente_nome,
        c.codice_fiscale as cliente_cf,
        CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
        COUNT(DISTINCT t.id) as totale_task,
        COUNT(DISTINCT CASE WHEN t.stato = 'completato' THEN t.id END) as task_completati,
        COALESCE(p.ore_lavorate, 0) as ore_totali_lavorate,
        DATEDIFF(p.data_scadenza, CURDATE()) as giorni_scadenza
    FROM pratiche p
    LEFT JOIN clienti c ON p.cliente_id = c.id
    LEFT JOIN operatori o ON p.operatore_assegnato_id = o.id
    LEFT JOIN task t ON p.id = t.pratica_id
    WHERE $whereClause
    GROUP BY p.id
    ORDER BY 
        FIELD(p.priorita, 'urgente', 'alta', 'media', 'bassa'),
        p.data_scadenza ASC
";

$pratiche = $db->select($query, $params);

// Calcola progress per ogni pratica
foreach ($pratiche as &$pratica) {
    if ($pratica['totale_task'] > 0) {
        $pratica['progress'] = round(($pratica['task_completati'] / $pratica['totale_task']) * 100);
    } else {
        $pratica['progress'] = 0;
    }
    
    // Aggiungi configurazioni tipo e stato
    $pratica['tipo_config'] = getPraticaType($pratica['tipo_pratica']);
    $pratica['stato_config'] = getPraticaStato($pratica['stato']);
    $pratica['priorita_config'] = PRATICHE_PRIORITA[$pratica['priorita']] ?? PRATICHE_PRIORITA['media'];
}

// Se vista kanban, raggruppa per stato
$praticheByStato = [];
if ($viewMode === 'kanban') {
    foreach (PRATICHE_STATI as $stato => $config) {
        $praticheByStato[$stato] = [];
    }
    
    foreach ($pratiche as $pratica) {
        $stato = $pratica['stato'];
        if (isset($praticheByStato[$stato])) {
            $praticheByStato[$stato][] = $pratica;
        }
    }
}

// Statistiche
$stats = [
    'totali' => count($pratiche),
    'urgenti' => count(array_filter($pratiche, fn($p) => $p['priorita'] === 'urgente')),
    'scadute' => count(array_filter($pratiche, fn($p) => $p['giorni_scadenza'] < 0)),
    'in_scadenza' => count(array_filter($pratiche, fn($p) => $p['giorni_scadenza'] >= 0 && $p['giorni_scadenza'] <= 3))
];

// Carica operatori per filtro (solo admin)
$operatori = [];
if ($currentUser['is_admin']) {
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo 
        FROM operatori 
        WHERE is_attivo = 1 
        ORDER BY cognome, nome
    ");
}

// Imposta variabili per header
$pageTitle = 'Gestione Pratiche';
$pageIcon = '📋';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Pratiche - CRM Re.De Consulting</title>
    
    <!-- SOLO CSS UNIFICATO - NIENTE STILI INLINE! -->
    <link rel="stylesheet" href="/crm/assets/css/datev-koinos-unified.css">
</head>
<body>
    <div class="app-layout">
        <?php 
        // SIDEBAR (barra laterale sinistra)
        include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/sidebar.php'; 
        ?>
        
        <div class="content-wrapper">
            <?php 
            // HEADER (barra orizzontale in alto)
            include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; 
            ?>
            
            <main class="main-content">
                <div class="container">
                    <!-- Messaggi flash -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success animate-fade-in">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger animate-fade-in">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Header con statistiche -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h1 class="h3 m-0">📋 Gestione Pratiche</h1>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-secondary btn-sm" onclick="toggleView()" title="Cambia vista">
                                        <?= $viewMode === 'kanban' ? '📋 Vista Lista' : '🎯 Vista Kanban' ?>
                                    </button>
                                    <a href="/crm/?action=pratiche&view=create" class="btn btn-primary btn-sm">
                                        ➕ Nuova Pratica
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Statistiche -->
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-value"><?= $stats['totali'] ?></div>
                                        <div class="stat-label">Pratiche Totali</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card text-danger">
                                        <div class="stat-value"><?= $stats['urgenti'] ?></div>
                                        <div class="stat-label">Urgenti</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card text-danger">
                                        <div class="stat-value"><?= $stats['scadute'] ?></div>
                                        <div class="stat-label">Scadute</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card text-warning">
                                        <div class="stat-value"><?= $stats['in_scadenza'] ?></div>
                                        <div class="stat-label">In Scadenza</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtri -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" action="/crm/" class="row g-2">
                                <input type="hidden" name="action" value="pratiche">
                                <input type="hidden" name="mode" value="<?= $viewMode ?>">
                                
                                <div class="col-md-3">
                                    <input type="text" 
                                           name="cliente" 
                                           class="form-control form-control-sm" 
                                           placeholder="🔍 Cerca cliente..."
                                           value="<?= htmlspecialchars($filterCliente) ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <select name="tipo" class="form-select form-select-sm">
                                        <option value="">Tutti i tipi</option>
                                        <?php foreach (PRATICHE_TYPES as $key => $tipo): ?>
                                        <option value="<?= $key ?>" <?= $filterTipo === $key ? 'selected' : '' ?>>
                                            <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <select name="stato" class="form-select form-select-sm">
                                        <option value="">Tutti gli stati</option>
                                        <option value="active" <?= $filterStato === 'active' ? 'selected' : '' ?>>
                                            🟢 Pratiche Attive
                                        </option>
                                        <optgroup label="Stati specifici">
                                            <?php foreach (PRATICHE_STATI as $key => $stato): ?>
                                            <option value="<?= $key ?>" <?= $filterStato === $key ? 'selected' : '' ?>>
                                                <?= $stato['icon'] ?> <?= $stato['label'] ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                
                                <?php if ($currentUser['is_admin'] && !empty($operatori)): ?>
                                <div class="col-md-2">
                                    <select name="operatore" class="form-select form-select-sm">
                                        <option value="">Tutti gli operatori</option>
                                        <?php foreach ($operatori as $op): ?>
                                        <option value="<?= $op['id'] ?>" <?= $filterOperatore == $op['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($op['nome_completo']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        🔍 Filtra
                                    </button>
                                </div>
                                
                                <div class="col-auto">
                                    <a href="/crm/?action=pratiche&mode=<?= $viewMode ?>" class="btn btn-secondary btn-sm">
                                        ✖ Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Vista contenuto -->
                    <?php if ($viewMode === 'kanban'): ?>
                        <!-- Vista Kanban - USANDO SOLO CLASSI CSS -->
                        <div class="kanban-board">
                            <?php foreach (PRATICHE_STATI as $stato => $statoConfig): ?>
                            <div class="kanban-column" data-stato="<?= $stato ?>">
                                <div class="kanban-header">
                                    <h4 class="kanban-title">
                                        <?= $statoConfig['icon'] ?> <?= $statoConfig['label'] ?>
                                    </h4>
                                    <span class="badge bg-secondary"><?= count($praticheByStato[$stato] ?? []) ?></span>
                                </div>
                                
                                <div class="kanban-cards" id="kanban-<?= $stato ?>">
                                    <?php foreach ($praticheByStato[$stato] ?? [] as $pratica): ?>
                                    <div class="kanban-card" 
                                         data-id="<?= $pratica['id'] ?>"
                                         data-tipo="<?= $pratica['tipo_pratica'] ?>"
                                         data-priorita="<?= $pratica['priorita'] ?>"
                                         draggable="true"
                                         onclick="viewPratica(<?= $pratica['id'] ?>)">
                                        
                                        <div class="kanban-card-header">
                                            <span class="badge badge-tipo-<?= $pratica['tipo_pratica'] ?>">
                                                <?= $pratica['tipo_config']['icon'] ?> <?= $pratica['tipo_config']['label'] ?>
                                            </span>
                                            <span class="badge badge-priorita-<?= $pratica['priorita'] ?>">
                                                <?= $pratica['priorita_config']['label'] ?>
                                            </span>
                                        </div>
                                        
                                        <div class="kanban-card-body">
                                            <h6 class="kanban-card-title"><?= htmlspecialchars($pratica['cliente_nome']) ?></h6>
                                            <p class="kanban-card-subtitle"><?= htmlspecialchars($pratica['titolo'] ?? 'Pratica #' . $pratica['id']) ?></p>
                                        </div>
                                        
                                        <div class="kanban-card-footer">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?= $pratica['operatore_nome'] ?>
                                                </small>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar" data-progress="<?= $pratica['progress'] ?>"></div>
                                                </div>
                                            </div>
                                            <?php if ($pratica['giorni_scadenza'] <= 7): ?>
                                            <div class="mt-1">
                                                <span class="badge <?= $pratica['giorni_scadenza'] < 0 ? 'badge-danger' : 'badge-warning' ?>">
                                                    <?= $pratica['giorni_scadenza'] < 0 ? 'Scaduta da ' . abs($pratica['giorni_scadenza']) . 'gg' : 'Scade tra ' . $pratica['giorni_scadenza'] . 'gg' ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Vista Lista -->
                        <div class="card">
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="20%">Cliente</th>
                                            <th width="15%">Tipo</th>
                                            <th width="10%">Stato</th>
                                            <th width="10%">Priorità</th>
                                            <th width="10%">Scadenza</th>
                                            <th width="10%">Progress</th>
                                            <th width="15%">Operatore</th>
                                            <th width="5%">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pratiche as $pratica): ?>
                                        <tr>
                                            <td><?= str_pad($pratica['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong><?= htmlspecialchars($pratica['cliente_nome']) ?></strong>
                                                    <small class="text-muted"><?= htmlspecialchars($pratica['titolo'] ?? 'Pratica #' . $pratica['id']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-tipo-<?= $pratica['tipo_pratica'] ?>">
                                                    <?= $pratica['tipo_config']['icon'] ?> <?= $pratica['tipo_config']['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-stato-<?= $pratica['stato'] ?>">
                                                    <?= $pratica['stato_config']['icon'] ?> <?= $pratica['stato_config']['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-priorita-<?= $pratica['priorita'] ?>">
                                                    <?= $pratica['priorita_config']['label'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($pratica['giorni_scadenza'] < 0): ?>
                                                    <span class="text-danger">
                                                        <strong>Scaduta da <?= abs($pratica['giorni_scadenza']) ?>gg</strong>
                                                    </span>
                                                <?php elseif ($pratica['giorni_scadenza'] <= 7): ?>
                                                    <span class="text-warning">
                                                        <strong>Tra <?= $pratica['giorni_scadenza'] ?>gg</strong>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar" data-progress="<?= $pratica['progress'] ?>">
                                                        <?= $pratica['progress'] ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($pratica['operatore_nome']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" 
                                                       class="btn btn-sm btn-primary" 
                                                       title="Visualizza">
                                                        👁️
                                                    </a>
                                                    <?php if ($pratica['stato_config']['can_edit']): ?>
                                                    <a href="/crm/?action=pratiche&view=edit&id=<?= $pratica['id'] ?>" 
                                                       class="btn btn-sm btn-secondary" 
                                                       title="Modifica">
                                                        ✏️
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Script SENZA CSS INLINE -->
    <script>
    // Inizializza progress bars
    document.addEventListener('DOMContentLoaded', function() {
        // Progress bars
        document.querySelectorAll('.progress-bar[data-progress]').forEach(bar => {
            const progress = bar.dataset.progress;
            bar.style.width = progress + '%';
        });
        
        // Kanban drag & drop
        if ('<?= $viewMode ?>' === 'kanban') {
            initKanbanDragDrop();
        }
    });
    
    function toggleView() {
        const currentMode = '<?= $viewMode ?>';
        const newMode = currentMode === 'kanban' ? 'list' : 'kanban';
        window.location.href = `/crm/?action=pratiche&mode=${newMode}`;
    }
    
    function viewPratica(id) {
        window.location.href = `/crm/?action=pratiche&view=view&id=${id}`;
    }
    
    // Kanban Drag & Drop
    function initKanbanDragDrop() {
        const cards = document.querySelectorAll('.kanban-card');
        const columns = document.querySelectorAll('.kanban-cards');
        
        cards.forEach(card => {
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
        });
        
        columns.forEach(column => {
            column.addEventListener('dragover', handleDragOver);
            column.addEventListener('drop', handleDrop);
            column.addEventListener('dragenter', handleDragEnter);
            column.addEventListener('dragleave', handleDragLeave);
        });
    }
    
    let draggedCard = null;
    
    function handleDragStart(e) {
        draggedCard = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
    }
    
    function handleDragEnd(e) {
        this.classList.remove('dragging');
    }
    
    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }
    
    function handleDragEnter(e) {
        this.classList.add('drag-over');
    }
    
    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }
    
    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        const column = this;
        column.classList.remove('drag-over');
        
        if (draggedCard && draggedCard !== this) {
            const praticaId = draggedCard.dataset.id;
            const nuovoStato = column.id.replace('kanban-', '');
            
            // Aggiorna stato via AJAX
            fetch('/crm/modules/pratiche/api/workflow.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_stato',
                    pratica_id: praticaId,
                    nuovo_stato: nuovoStato
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    column.appendChild(draggedCard);
                    updateKanbanCounters();
                } else {
                    alert(data.message || 'Errore nel cambio stato');
                    location.reload();
                }
            });
        }
        
        return false;
    }
    
    function updateKanbanCounters() {
        document.querySelectorAll('.kanban-column').forEach(column => {
            const count = column.querySelectorAll('.kanban-card').length;
            const badge = column.querySelector('.badge');
            if (badge) {
                badge.textContent = count;
            }
        });
    }
    </script>
</body>
</html>