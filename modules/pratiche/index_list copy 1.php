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

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Pratiche - CRM Re.De Consulting</title>
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <style>
        /* Container principale */
        .pratiche-container {
            padding: 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* Header con statistiche */
        .pratiche-header {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Statistiche */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .stat-card {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #007849;
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .stat-card.danger .stat-value { color: #dc2626; }
        .stat-card.warning .stat-value { color: #f59e0b; }
        
        /* Filtri */
        .filters-bar {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .filters-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.8125rem;
        }
        
        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1em 1em;
            padding-right: 2rem;
        }
        
        /* Vista Lista */
        .pratiche-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .list-header {
            display: grid;
            grid-template-columns: 3fr 2fr 1fr 1fr 1fr 120px;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .list-item {
            display: grid;
            grid-template-columns: 3fr 2fr 1fr 1fr 1fr 120px;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            align-items: center;
            transition: background 0.15s;
        }
        
        .list-item:hover {
            background: #f9fafb;
        }
        
        .pratica-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .tipo-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 1.25rem;
        }
        
        .pratica-details {
            flex: 1;
            min-width: 0;
        }
        
        .pratica-title {
            font-weight: 500;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.875rem;
        }
        
        .pratica-meta {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.125rem;
        }
        
        /* Vista Kanban */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            align-items: flex-start;
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
            color: #374151;
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
            color: #6b7280;
        }
        
        .kanban-cards {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .pratica-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.75rem;
            cursor: move;
            transition: all 0.2s;
        }
        
        .pratica-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        
        .pratica-card.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .card-tipo {
            font-size: 1.25rem;
        }
        
        .card-priorita {
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .priorita-urgente { background: #fee2e2; color: #dc2626; }
        .priorita-alta { background: #fed7aa; color: #ea580c; }
        .priorita-media { background: #fef3c7; color: #d97706; }
        .priorita-bassa { background: #d1fae5; color: #059669; }
        
        .card-title {
            font-weight: 500;
            font-size: 0.8125rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .card-cliente {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .card-meta {
            display: flex;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .card-progress {
            margin-top: 0.5rem;
            background: #e5e7eb;
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: #10b981;
            transition: width 0.3s;
        }
        
        /* Badge stati */
        .badge {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 500;
            border-radius: 12px;
        }
        
        .stato-bozza { background: #f3f4f6; color: #6b7280; }
        .stato-da_iniziare { background: #fef3c7; color: #92400e; }
        .stato-in_corso { background: #dbeafe; color: #1e40af; }
        .stato-in_attesa { background: #e9d5ff; color: #6b21a8; }
        .stato-in_revisione { background: #fce7f3; color: #be185d; }
        .stato-completata { background: #d1fae5; color: #065f46; }
        .stato-fatturata { background: #cffafe; color: #155e75; }
        .stato-archiviata { background: #e5e7eb; color: #374151; }
        
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
        
        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        .btn-primary {
            background: #007849;
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,120,73,0.2);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-icon {
            padding: 0.375rem;
            background: transparent;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 1rem;
            border-radius: 4px;
            transition: all 0.15s;
        }
        
        .btn-icon:hover {
            background: #f3f4f6;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="main-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="pratiche-container">
                    <!-- Header con statistiche -->
                    <div class="pratiche-header">
                        <div class="header-top">
                            <h1 class="page-title">📋 Gestione Pratiche</h1>
                            <div class="header-actions">
                                <button class="btn-icon" onclick="toggleView()" title="Cambia vista">
                                    <?= $viewMode === 'kanban' ? '📋' : '🎯' ?>
                                </button>
                                <a href="/crm/?action=pratiche&view=create" class="btn btn-primary">
                                    ➕ Nuova Pratica
                                </a>
                            </div>
                        </div>
                        
                        <div class="stats-row">
                            <div class="stat-card">
                                <span class="stat-value"><?= $stats['totali'] ?></span>
                                <span class="stat-label">Pratiche Totali</span>
                            </div>
                            <div class="stat-card danger">
                                <span class="stat-value"><?= $stats['urgenti'] ?></span>
                                <span class="stat-label">Urgenti</span>
                            </div>
                            <div class="stat-card danger">
                                <span class="stat-value"><?= $stats['scadute'] ?></span>
                                <span class="stat-label">Scadute</span>
                            </div>
                            <div class="stat-card warning">
                                <span class="stat-value"><?= $stats['in_scadenza'] ?></span>
                                <span class="stat-label">In Scadenza</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messaggi flash -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger" style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filtri -->
                    <div class="filters-bar">
                        <form class="filters-form" method="GET">
                            <input type="hidden" name="action" value="pratiche">
                            <input type="hidden" name="mode" value="<?= $viewMode ?>">
                            
                            <div class="filter-group">
                                <input type="text" 
                                       name="cliente" 
                                       id="filterCliente"
                                       class="filter-input" 
                                       placeholder="🔍 Cerca cliente..."
                                       value="<?= htmlspecialchars($filterCliente) ?>">
                            </div>
                            
                            <div class="filter-group">
                                <select name="tipo" id="filterTipo" class="filter-input filter-select">
                                    <option value="">Tutti i tipi</option>
                                    <?php foreach (PRATICHE_TYPES as $key => $tipo): ?>
                                    <option value="<?= $key ?>" <?= $filterTipo === $key ? 'selected' : '' ?>>
                                        <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <select name="stato" id="filterStato" class="filter-input filter-select">
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
                            <div class="filter-group">
                                <select name="operatore" id="filterOperatore" class="filter-input filter-select">
                                    <option value="">Tutti gli operatori</option>
                                    <?php foreach ($operatori as $op): ?>
                                    <option value="<?= $op['id'] ?>" <?= $filterOperatore == $op['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($op['nome_completo']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary btn-sm">
                                🔍 Filtra
                            </button>
                            
                            <a href="/crm/?action=pratiche&mode=<?= $viewMode ?>" class="btn btn-secondary btn-sm">
                                ✖ Reset
                            </a>
                        </form>
                    </div>
                    
                    <!-- Vista contenuto -->
                    <?php if ($viewMode === 'kanban'): ?>
                        <!-- Vista Kanban -->
                        <div class="kanban-board">
                            <?php foreach (PRATICHE_STATI as $stato => $statoConfig): ?>
                            <div class="kanban-column" data-stato="<?= $stato ?>">
                                <div class="kanban-header">
                                    <div class="kanban-title">
                                        <span><?= $statoConfig['icon'] ?></span>
                                        <span><?= $statoConfig['label'] ?></span>
                                    </div>
                                    <span class="kanban-count"><?= count($praticheByStato[$stato] ?? []) ?></span>
                                </div>
                                
                                <div class="kanban-cards">
                                    <?php foreach (($praticheByStato[$stato] ?? []) as $pratica): ?>
                                    <div class="pratica-card" 
                                         draggable="true" 
                                         data-pratica-id="<?= $pratica['id'] ?>"
                                         onclick="viewPratica(<?= $pratica['id'] ?>)">
                                        <div class="card-header">
                                            <span class="card-tipo" style="color: <?= $pratica['tipo_config']['color'] ?>">
                                                <?= $pratica['tipo_config']['icon'] ?>
                                            </span>
                                            <span class="card-priorita priorita-<?= $pratica['priorita'] ?>">
                                                <?= $pratica['priorita_config']['label'] ?>
                                            </span>
                                        </div>
                                        
                                        <div class="card-title" title="<?= htmlspecialchars($pratica['titolo']) ?>">
                                            <?= htmlspecialchars($pratica['titolo']) ?>
                                        </div>
                                        
                                        <div class="card-cliente">
                                            <?= htmlspecialchars($pratica['cliente_nome']) ?>
                                        </div>
                                        
                                        <div class="card-meta">
                                            <?php if ($pratica['giorni_scadenza'] < 0): ?>
                                                <span style="color: #dc2626;">⚠️ Scaduta</span>
                                            <?php elseif ($pratica['giorni_scadenza'] <= 3): ?>
                                                <span style="color: #f59e0b;">⏰ <?= $pratica['giorni_scadenza'] ?>gg</span>
                                            <?php else: ?>
                                                <span>📅 <?= date('d/m', strtotime($pratica['data_scadenza'])) ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($pratica['totale_task'] > 0): ?>
                                                <span>📋 <?= $pratica['task_completati'] ?>/<?= $pratica['totale_task'] ?></span>
                                            <?php endif; ?>
                                            
                                            <span>👤 <?= explode(' ', $pratica['operatore_nome'])[0] ?></span>
                                        </div>
                                        
                                        <?php if ($pratica['totale_task'] > 0): ?>
                                        <div class="card-progress">
                                            <div class="progress-bar" style="width: <?= $pratica['progress'] ?>%"></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php else: ?>
                        <!-- Vista Lista -->
                        <div class="pratiche-list">
                            <?php if (!empty($pratiche)): ?>
                                <div class="list-header">
                                    <div>Pratica</div>
                                    <div>Cliente</div>
                                    <div>Stato</div>
                                    <div>Scadenza</div>
                                    <div>Progress</div>
                                    <div>Azioni</div>
                                </div>
                                
                                <?php foreach ($pratiche as $pratica): ?>
                                <div class="list-item">
                                    <div class="pratica-info">
                                        <div class="tipo-icon" style="background: <?= $pratica['tipo_config']['color'] ?>20; color: <?= $pratica['tipo_config']['color'] ?>">
                                            <?= $pratica['tipo_config']['icon'] ?>
                                        </div>
                                        <div class="pratica-details">
                                            <div class="pratica-title">
                                                <?= htmlspecialchars($pratica['titolo']) ?>
                                            </div>
                                            <div class="pratica-meta">
                                                <?= $pratica['tipo_config']['label'] ?> • 
                                                <span class="badge priorita-<?= $pratica['priorita'] ?>" style="padding: 0.125rem 0.375rem;">
                                                    <?= $pratica['priorita_config']['label'] ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div style="font-size: 0.875rem; color: #1f2937;">
                                            <?= htmlspecialchars($pratica['cliente_nome']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">
                                            <?= htmlspecialchars($pratica['cliente_cf'] ?? '') ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <span class="badge stato-<?= $pratica['stato'] ?>">
                                            <?= $pratica['stato_config']['icon'] ?> <?= $pratica['stato_config']['label'] ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <?php 
                                        $scadenzaInfo = getScadenzaInfo($pratica['data_scadenza']);
                                        ?>
                                        <span class="<?= $scadenzaInfo['class'] ?>" style="font-size: 0.875rem;">
                                            <?= $scadenzaInfo['icon'] ?> <?= $scadenzaInfo['text'] ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <?php if ($pratica['totale_task'] > 0): ?>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="flex: 1; background: #e5e7eb; height: 6px; border-radius: 3px; overflow: hidden;">
                                                    <div style="width: <?= $pratica['progress'] ?>%; height: 100%; background: #10b981;"></div>
                                                </div>
                                                <span style="font-size: 0.75rem; color: #6b7280; min-width: 35px; text-align: right;">
                                                    <?= $pratica['progress'] ?>%
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="display: flex; gap: 0.375rem;">
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
                            <?php endif; ?>
                            
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
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.innerHTML);
                });
                
                card.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                });
            });
            
            columns.forEach(column => {
                const cardsContainer = column.querySelector('.kanban-cards');
                
                cardsContainer.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                });
                
                cardsContainer.addEventListener('drop', function(e) {
                    e.preventDefault();
                    
                    const newStato = column.dataset.stato;
                    const praticaId = draggedCard.dataset.praticaId;
                    
                    // In produzione, fare chiamata AJAX per aggiornare stato
                    console.log(`Cambiando pratica ${praticaId} a stato ${newStato}`);
                    
                    // Per ora, ricarica pagina
                    alert('Funzionalità in sviluppo - il cambio stato verrà salvato');
                    // window.location.reload();
                });
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>