<?php
/**
 * modules/pratiche/index_list.php - Lista Pratiche + Vista Kanban
 * 
 * ✅ GESTIONE PRATICHE CON VISTA KANBAN E LISTA
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
    $whereConditions[] = "p.operatore_responsabile_id = ?";
    $params[] = $currentUser['id'];
} else if ($filterOperatore) {
    $whereConditions[] = "p.operatore_responsabile_id = ?";
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
        COALESCE(SUM(t.ore_lavorate), 0) as ore_totali_lavorate,
        DATEDIFF(p.data_scadenza, CURDATE()) as giorni_scadenza
    FROM pratiche p
    LEFT JOIN clienti c ON p.cliente_id = c.id
    LEFT JOIN operatori o ON p.operatore_responsabile_id = o.id
    LEFT JOIN task t ON p.id = t.pratica_id
    WHERE $whereClause
    GROUP BY p.id
    ORDER BY 
        FIELD(p.stato, 'urgente', 'alta', 'media', 'bassa'),
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

// Per vista Kanban, raggruppa per stato
$praticheByStato = [];
if ($viewMode === 'kanban') {
    foreach (PRATICHE_UI_CONFIG['kanban_columns'] as $stato) {
        $praticheByStato[$stato] = array_filter($pratiche, function($p) use ($stato) {
            return $p['stato'] === $stato;
        });
    }
}

// Carica operatori per filtro (se admin)
$operatori = [];
if ($currentUser['is_admin']) {
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo 
        FROM operatori 
        WHERE is_attivo = 1 
        ORDER BY cognome, nome
    ");
}

// Carica clienti per filtro
$clienti = [];
if ($currentUser['is_admin']) {
    $clienti = $db->select("
        SELECT id, ragione_sociale 
        FROM clienti 
        WHERE stato = 'attivo'
        ORDER BY ragione_sociale
    ");
} else {
    // Operatore vede solo i suoi clienti
    $clienti = $db->select("
        SELECT DISTINCT c.id, c.ragione_sociale 
        FROM clienti c
        INNER JOIN pratiche p ON c.id = p.cliente_id
        WHERE p.operatore_responsabile_id = ? AND c.stato = 'attivo'
        ORDER BY c.ragione_sociale
    ", [$currentUser['id']]);
}

// Statistiche rapide
$stats = [
    'totali' => count($pratiche),
    'urgenti' => count(array_filter($pratiche, fn($p) => $p['priorita'] === 'urgente')),
    'in_scadenza' => count(array_filter($pratiche, fn($p) => $p['giorni_scadenza'] >= 0 && $p['giorni_scadenza'] <= 7)),
    'completate_mese' => 0 // TODO: implementare
];

// Helper functions per UI
function getScadenzaClass($giorni) {
    if ($giorni < 0) return 'scaduta';
    if ($giorni <= 3) return 'urgente';
    if ($giorni <= 7) return 'prossima';
    return 'normale';
}

function formatScadenza($giorni) {
    if ($giorni < 0) return abs($giorni) . ' giorni fa';
    if ($giorni == 0) return 'Oggi';
    if ($giorni == 1) return 'Domani';
    return "tra $giorni giorni";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Pratiche - CRM Re.De Consulting</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    
    <style>
        /* Layout compatto Datev Koinos */
        :root {
            --primary-green: #007849;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.08);
            --border-radius: 6px;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 0.75rem;
            --spacing-lg: 1rem;
        }
        
        /* Container principale */
        .pratiche-container {
            padding: var(--spacing-lg);
            background: #f8f9fa;
            min-height: calc(100vh - 64px);
        }
        
        /* Header con filtri */
        .pratiche-header {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        /* Bottoni compatti */
        .btn {
            padding: var(--spacing-xs) var(--spacing-md);
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,120,73,0.2);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-sm {
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
        }
        
        /* Filtri */
        .filters-row {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }
        
        .filter-label {
            font-size: 0.6875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .filter-select {
            padding: 0.25rem 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: var(--border-radius);
            font-size: 0.8125rem;
            background: white;
            min-width: 120px;
        }
        
        /* Stats rapidi */
        .stats-mini {
            display: flex;
            gap: var(--spacing-md);
            margin-left: auto;
        }
        
        .stat-mini {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.75rem;
        }
        
        .stat-mini-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .stat-mini-label {
            color: #6b7280;
        }
        
        /* Vista Kanban */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-lg);
        }
        
        .kanban-column {
            background: #f3f4f6;
            border-radius: var(--border-radius);
            padding: var(--spacing-md);
            min-height: 400px;
        }
        
        .kanban-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 2px solid #e5e7eb;
        }
        
        .kanban-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .kanban-count {
            background: white;
            color: #6b7280;
            padding: 0.125rem 0.375rem;
            border-radius: 999px;
            font-size: 0.6875rem;
            font-weight: 500;
        }
        
        /* Card pratica */
        .pratica-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            cursor: move;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        
        .pratica-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-1px);
            border-color: var(--primary-green);
        }
        
        .pratica-card.dragging {
            opacity: 0.5;
        }
        
        .pratica-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-xs);
        }
        
        .pratica-tipo {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .pratica-priorita {
            padding: 0.125rem 0.375rem;
            border-radius: 999px;
            font-size: 0.625rem;
            font-weight: 600;
        }
        
        .priorita-urgente {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .priorita-alta {
            background: #fed7aa;
            color: #ea580c;
        }
        
        .priorita-media {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .priorita-bassa {
            background: #e5e7eb;
            color: #6b7280;
        }
        
        .pratica-cliente {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.125rem;
        }
        
        .pratica-numero {
            font-size: 0.6875rem;
            color: #6b7280;
        }
        
        .pratica-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--spacing-xs);
            padding-top: var(--spacing-xs);
            border-top: 1px solid #f3f4f6;
        }
        
        .pratica-scadenza {
            font-size: 0.6875rem;
            display: flex;
            align-items: center;
            gap: 0.125rem;
        }
        
        .scadenza-urgente {
            color: #dc2626;
            font-weight: 600;
        }
        
        .scadenza-prossima {
            color: #ea580c;
        }
        
        .scadenza-normale {
            color: #6b7280;
        }
        
        .scadenza-scaduta {
            color: #dc2626;
            font-weight: 600;
            background: #fee2e2;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
        }
        
        .pratica-progress {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.6875rem;
        }
        
        .progress-bar {
            width: 60px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            transition: width 0.3s ease;
        }
        
        .pratica-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--spacing-xs);
        }
        
        .pratica-operatore {
            font-size: 0.6875rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.125rem;
        }
        
        .pratica-actions {
            display: flex;
            gap: 0.125rem;
        }
        
        .btn-icon {
            padding: 0.125rem;
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: #f3f4f6;
            color: var(--primary-green);
        }
        
        /* Vista Lista (alternativa) */
        .pratiche-list {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .list-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 120px;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.6875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .list-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 120px;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
            transition: all 0.2s;
        }
        
        .list-row:hover {
            background: #f9fafb;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .kanban-board {
                grid-template-columns: 1fr;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .stats-mini {
                display: none;
            }
        }
        
        /* Loading */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            color: #6b7280;
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
        
        .empty-state-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .empty-state-text {
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="pratiche-container">
                    <!-- Messaggi flash -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                    <?php endif; ?>
                    
                    <!-- Header con filtri -->
                    <div class="pratiche-header">
                        <div class="header-top">
                            <h1 class="page-title">📋 Gestione Pratiche</h1>
                            <div class="header-actions">
                                <button class="btn btn-secondary" onclick="toggleView()">
                                    <?= $viewMode === 'kanban' ? '📋 Vista Lista' : '📊 Vista Kanban' ?>
                                </button>
                                <a href="/crm/?action=pratiche&view=create" class="btn btn-primary">
                                    ➕ Nuova Pratica
                                </a>
                            </div>
                        </div>
                        
                        <div class="filters-row">
                            <div class="filter-group">
                                <label class="filter-label">Cliente</label>
                                <select class="filter-select" id="filterCliente" onchange="applyFilters()">
                                    <option value="">Tutti i clienti</option>
                                    <?php foreach ($clienti as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>" <?= $filterCliente == $cliente['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Tipo</label>
                                <select class="filter-select" id="filterTipo" onchange="applyFilters()">
                                    <option value="">Tutti i tipi</option>
                                    <?php foreach (PRATICHE_TYPES as $key => $tipo): ?>
                                        <option value="<?= $key ?>" <?= $filterTipo === $key ? 'selected' : '' ?>>
                                            <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Stato</label>
                                <select class="filter-select" id="filterStato" onchange="applyFilters()">
                                    <option value="active" <?= $filterStato === 'active' ? 'selected' : '' ?>>Attive</option>
                                    <option value="all">Tutte</option>
                                    <?php foreach (PRATICHE_STATI as $key => $stato): ?>
                                        <option value="<?= $key ?>" <?= $filterStato === $key ? 'selected' : '' ?>>
                                            <?= $stato['icon'] ?> <?= $stato['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($currentUser['is_admin']): ?>
                                <div class="filter-group">
                                    <label class="filter-label">Operatore</label>
                                    <select class="filter-select" id="filterOperatore" onchange="applyFilters()">
                                        <option value="">Tutti</option>
                                        <?php foreach ($operatori as $op): ?>
                                            <option value="<?= $op['id'] ?>" <?= $filterOperatore == $op['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($op['nome_completo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="stats-mini">
                                <div class="stat-mini">
                                    <span class="stat-mini-value"><?= $stats['totali'] ?></span>
                                    <span class="stat-mini-label">pratiche</span>
                                </div>
                                <?php if ($stats['urgenti'] > 0): ?>
                                    <div class="stat-mini">
                                        <span class="stat-mini-value" style="color: #dc2626;">
                                            <?= $stats['urgenti'] ?>
                                        </span>
                                        <span class="stat-mini-label">urgenti</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($stats['in_scadenza'] > 0): ?>
                                    <div class="stat-mini">
                                        <span class="stat-mini-value" style="color: #ea580c;">
                                            <?= $stats['in_scadenza'] ?>
                                        </span>
                                        <span class="stat-mini-label">in scadenza</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vista Kanban -->
                    <?php if ($viewMode === 'kanban'): ?>
                        <div class="kanban-board">
                            <?php foreach (PRATICHE_UI_CONFIG['kanban_columns'] as $stato): ?>
                                <?php 
                                $statoConfig = getPraticaStato($stato);
                                $praticheDiStato = $praticheByStato[$stato] ?? [];
                                ?>
                                <div class="kanban-column" data-stato="<?= $stato ?>">
                                    <div class="kanban-header">
                                        <h3 class="kanban-title">
                                            <?= $statoConfig['icon'] ?> <?= $statoConfig['label'] ?>
                                        </h3>
                                        <span class="kanban-count"><?= count($praticheDiStato) ?></span>
                                    </div>
                                    
                                    <div class="kanban-cards">
                                        <?php foreach ($praticheDiStato as $pratica): ?>
                                            <div class="pratica-card" 
                                                 data-id="<?= $pratica['id'] ?>"
                                                 draggable="true"
                                                 onclick="viewPratica(<?= $pratica['id'] ?>)">
                                                
                                                <div class="pratica-header">
                                                    <div class="pratica-tipo" style="color: <?= $pratica['tipo_config']['color'] ?>">
                                                        <?= $pratica['tipo_config']['icon'] ?>
                                                        <?= $pratica['tipo_config']['label'] ?>
                                                    </div>
                                                    <span class="pratica-priorita priorita-<?= $pratica['priorita'] ?>">
                                                        <?= $pratica['priorita_config']['label'] ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="pratica-cliente">
                                                    <?= htmlspecialchars($pratica['cliente_nome']) ?>
                                                </div>
                                                <div class="pratica-numero">
                                                    #<?= htmlspecialchars($pratica['numero_pratica']) ?>
                                                </div>
                                                
                                                <div class="pratica-info">
                                                    <div class="pratica-scadenza <?= getScadenzaClass($pratica['giorni_scadenza']) ?>">
                                                        📅 <?= formatScadenza($pratica['giorni_scadenza']) ?>
                                                    </div>
                                                    <div class="pratica-progress">
                                                        <div class="progress-bar">
                                                            <div class="progress-fill" style="width: <?= $pratica['progress'] ?>%"></div>
                                                        </div>
                                                        <span><?= $pratica['progress'] ?>%</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="pratica-footer">
                                                    <div class="pratica-operatore">
                                                        👤 <?= htmlspecialchars($pratica['operatore_nome']) ?>
                                                    </div>
                                                    <div class="pratica-actions">
                                                        <button class="btn-icon" onclick="event.stopPropagation(); editPratica(<?= $pratica['id'] ?>)">
                                                            ✏️
                                                        </button>
                                                        <button class="btn-icon" onclick="event.stopPropagation(); viewTasks(<?= $pratica['id'] ?>)">
                                                            📋
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (empty($praticheDiStato)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-text">
                                                Nessuna pratica
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Vista Lista -->
                        <div class="pratiche-list">
                            <div class="list-header">
                                <div>Cliente / Pratica</div>
                                <div>Tipo</div>
                                <div>Stato</div>
                                <div>Scadenza</div>
                                <div>Progress</div>
                                <div>Operatore</div>
                                <div>Azioni</div>
                            </div>
                            
                            <?php foreach ($pratiche as $pratica): ?>
                                <div class="list-row">
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.875rem;">
                                            <?= htmlspecialchars($pratica['cliente_nome']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">
                                            #<?= htmlspecialchars($pratica['numero_pratica']) ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                                        <span style="color: <?= $pratica['tipo_config']['color'] ?>">
                                            <?= $pratica['tipo_config']['icon'] ?>
                                        </span>
                                        <span style="font-size: 0.75rem;">
                                            <?= $pratica['tipo_config']['label'] ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="badge" style="background: <?= $pratica['stato_config']['color'] ?>20; color: <?= $pratica['stato_config']['color'] ?>">
                                            <?= $pratica['stato_config']['icon'] ?> <?= $pratica['stato_config']['label'] ?>
                                        </span>
                                    </div>
                                    <div class="<?= getScadenzaClass($pratica['giorni_scadenza']) ?>" style="font-size: 0.8125rem;">
                                        <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?>
                                        <div style="font-size: 0.6875rem;">
                                            <?= formatScadenza($pratica['giorni_scadenza']) ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="progress-bar" style="width: 80px;">
                                            <div class="progress-fill" style="width: <?= $pratica['progress'] ?>%"></div>
                                        </div>
                                        <span style="font-size: 0.75rem;"><?= $pratica['progress'] ?>%</span>
                                    </div>
                                    <div style="font-size: 0.8125rem; color: #6b7280;">
                                        <?= htmlspecialchars($pratica['operatore_nome']) ?>
                                    </div>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-sm btn-secondary">
                                            👁️ Apri
                                        </a>
                                        <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-sm btn-secondary">
                                            📋 Task
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($pratiche)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <div class="empty-state-title">Nessuna pratica trovata</div>
                                    <div class="empty-state-text">
                                        Modifica i filtri o crea una nuova pratica
                                    </div>
                                    <a href="/crm/?action=pratiche&view=create" class="btn btn-primary">
                                        ➕ Crea Prima Pratica
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Gestione filtri
        function applyFilters() {
            const params = new URLSearchParams(window.location.search);
            
            params.set('cliente', document.getElementById('filterCliente').value);
            params.set('tipo', document.getElementById('filterTipo').value);
            params.set('stato', document.getElementById('filterStato').value);
            
            <?php if ($currentUser['is_admin']): ?>
            params.set('operatore', document.getElementById('filterOperatore').value);
            <?php endif; ?>
            
            window.location.href = '?' + params.toString();
        }
        
        // Toggle vista
        function toggleView() {
            const params = new URLSearchParams(window.location.search);
            const currentMode = params.get('mode') || '<?= PRATICHE_UI_CONFIG['default_view'] ?>';
            params.set('mode', currentMode === 'kanban' ? 'list' : 'kanban');
            window.location.href = '?' + params.toString();
        }
        
        // Navigazione
        function viewPratica(id) {
            window.location.href = `/crm/?action=pratiche&view=view&id=${id}`;
        }
        
        function editPratica(id) {
            window.location.href = `/crm/?action=pratiche&view=edit&id=${id}`;
        }
        
        function viewTasks(id) {
            window.location.href = `/crm/?action=pratiche&view=task_manager&id=${id}`;
        }
        
        // Drag & Drop per Kanban
        <?php if ($viewMode === 'kanban'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.pratica-card');
            const columns = document.querySelectorAll('.kanban-column');
            
            let draggedCard = null;
            
            cards.forEach(card => {
                card.addEventListener('dragstart', function(e) {
                    draggedCard = this;
                    this.classList.add('dragging');
                });
                
                card.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                });
            });
            
            columns.forEach(column => {
                const cardsContainer = column.querySelector('.kanban-cards');
                
                cardsContainer.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    const afterElement = getDragAfterElement(cardsContainer, e.clientY);
                    if (afterElement == null) {
                        cardsContainer.appendChild(draggedCard);
                    } else {
                        cardsContainer.insertBefore(draggedCard, afterElement);
                    }
                });
                
                cardsContainer.addEventListener('drop', function(e) {
                    e.preventDefault();
                    const newStato = column.dataset.stato;
                    const praticaId = draggedCard.dataset.id;
                    
                    // Aggiorna stato via AJAX
                    updatePraticaStato(praticaId, newStato);
                });
            });
            
            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.pratica-card:not(.dragging)')];
                
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
            
            function updatePraticaStato(praticaId, newStato) {
                fetch('/crm/modules/pratiche/api/workflow.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_stato',
                        pratica_id: praticaId,
                        nuovo_stato: newStato
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Aggiorna counter
                        updateKanbanCounters();
                    } else {
                        alert('Errore: ' + data.message);
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore durante l\'aggiornamento');
                    location.reload();
                });
            }
            
            function updateKanbanCounters() {
                columns.forEach(column => {
                    const count = column.querySelectorAll('.pratica-card').length;
                    column.querySelector('.kanban-count').textContent = count;
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>