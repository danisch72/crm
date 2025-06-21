<?php
/**
 * modules/pratiche/index_list.php - Lista Pratiche con Design Professionale
 * 
 * ✅ DESIGN DATEV KOINOS PROFESSIONALE
 * ✅ LAYOUT ULTRA-COMPATTO E ORGANIZZATO
 * ✅ VISUALIZZAZIONE LISTA E KANBAN
 * ✅ FILTRI OTTIMIZZATI
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Parametri filtro
$filterCliente = $_GET['cliente'] ?? '';
$filterTipo = $_GET['tipo'] ?? '';
$filterStato = $_GET['stato'] ?? '';
$filterOperatore = $_GET['operatore'] ?? '';
$viewMode = $_GET['mode'] ?? 'list'; // Default vista lista

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
} else {
    // Default: non mostrare archiviate
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
    'scadute' => count(array_filter($pratiche, fn($p) => $p['giorni_scadenza'] < 0 && $p['stato'] !== 'completata')),
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
    
    <!-- CSS Unificato Datev Koinos -->
    <link rel="stylesheet" href="/crm/assets/css/datev-koinos-unified.css">
    
    <!-- CSS Specifico Pratiche -->
    <style>
        /* Layout Pratiche Ottimizzato */
        .pratiche-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header Stats Compatto */
        .stats-header {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            border-radius: var(--border-radius-md);
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            transition: all 0.2s;
        }
        
        .stat-item:hover {
            border-color: var(--primary-green);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1;
        }
        
        .stat-item.danger .stat-number {
            color: var(--danger);
        }
        
        .stat-item.warning .stat-number {
            color: var(--warning);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
            font-weight: 500;
        }
        
        /* Toolbar con Filtri */
        .toolbar {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .toolbar-content {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters-group {
            flex: 1;
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-item {
            flex: 1;
            min-width: 180px;
            max-width: 250px;
        }
        
        .filter-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            font-size: 0.875rem;
            background: white;
            transition: all 0.2s;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0,120,73,0.1);
        }
        
        .filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1.2rem;
            padding-right: 2rem;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Lista Pratiche Professionale */
        .pratiche-list {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .list-header {
            display: grid;
            grid-template-columns: 2.5fr 1fr 1fr 1fr 1fr 1fr 100px;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .list-item {
            display: grid;
            grid-template-columns: 2.5fr 1fr 1fr 1fr 1fr 1fr 100px;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .list-item:hover {
            background: var(--primary-lighter);
        }
        
        .pratica-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .pratica-cliente {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.875rem;
        }
        
        .pratica-numero {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-family: var(--font-mono);
        }
        
        .tipo-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stato-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        /* Stati colorati */
        .stato-bozza {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .stato-da_iniziare {
            background: #fef3c7;
            color: #92400e;
        }
        
        .stato-in_corso {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .stato-in_attesa_cliente {
            background: #e0e7ff;
            color: #4c1d95;
        }
        
        .stato-in_revisione {
            background: #fed7aa;
            color: #c2410c;
        }
        
        .stato-completata {
            background: #d1fae5;
            color: #065f46;
        }
        
        .stato-fatturata {
            background: #cffafe;
            color: #0e7490;
        }
        
        .stato-archiviata {
            background: var(--gray-200);
            color: var(--gray-600);
        }
        
        .scadenza-info {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
        }
        
        .scadenza-urgente {
            color: var(--danger);
            font-weight: 600;
        }
        
        .scadenza-prossima {
            color: var(--warning);
            font-weight: 500;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        .operatore-info {
            font-size: 0.875rem;
            color: var(--gray-700);
        }
        
        .actions-cell {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-md);
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .action-view {
            background: var(--primary-light);
            color: var(--primary-green);
        }
        
        .action-view:hover {
            background: var(--primary-green);
            color: white;
        }
        
        /* Vista Kanban */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .kanban-column {
            background: var(--gray-50);
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            min-height: 400px;
        }
        
        .kanban-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .kanban-title {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-800);
        }
        
        .kanban-count {
            background: var(--gray-700);
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .kanban-card {
            background: white;
            border-radius: var(--border-radius-md);
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: var(--shadow-xs);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .kanban-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .filter-item {
                min-width: 150px;
            }
            
            .list-header,
            .list-item {
                grid-template-columns: 2fr 1fr 1fr 1fr 100px;
            }
            
            .list-header > *:nth-child(5),
            .list-item > *:nth-child(5),
            .list-header > *:nth-child(6),
            .list-item > *:nth-child(6) {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .toolbar-content {
                flex-direction: column;
            }
            
            .filters-group {
                width: 100%;
            }
            
            .kanban-board {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php 
    // NAVIGATION.PHP include sia sidebar che header
    include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; 
    ?>
    
    <!-- Main Content (dopo navigation) -->
    <main class="main-content">
        <div class="pratiche-container">
                    <!-- Messaggi Flash -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Header Statistiche -->
                    <div class="stats-header">
                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="stat-number"><?= $stats['totali'] ?></div>
                                <div class="stat-label">Pratiche Totali</div>
                            </div>
                            <div class="stat-item danger">
                                <div class="stat-number"><?= $stats['urgenti'] ?></div>
                                <div class="stat-label">Urgenti</div>
                            </div>
                            <div class="stat-item warning">
                                <div class="stat-number"><?= $stats['scadute'] ?></div>
                                <div class="stat-label">Scadute</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $stats['in_scadenza'] ?></div>
                                <div class="stat-label">In Scadenza</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toolbar con Filtri -->
                    <div class="toolbar">
                        <form class="toolbar-content" method="GET">
                            <input type="hidden" name="action" value="pratiche">
                            <input type="hidden" name="mode" value="<?= $viewMode ?>">
                            
                            <div class="filters-group">
                                <div class="filter-item">
                                    <input type="text" 
                                           name="cliente" 
                                           class="filter-input" 
                                           placeholder="🔍 Cerca cliente..."
                                           value="<?= htmlspecialchars($filterCliente) ?>">
                                </div>
                                
                                <div class="filter-item">
                                    <select name="tipo" class="filter-input filter-select">
                                        <option value="">Tutti i tipi</option>
                                        <?php foreach (PRATICHE_TYPES as $key => $tipo): ?>
                                        <option value="<?= $key ?>" <?= $filterTipo === $key ? 'selected' : '' ?>>
                                            <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-item">
                                    <select name="stato" class="filter-input filter-select">
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
                                <div class="filter-item">
                                    <select name="operatore" class="filter-input filter-select">
                                        <option value="">Tutti gli operatori</option>
                                        <?php foreach ($operatori as $op): ?>
                                        <option value="<?= $op['id'] ?>" <?= $filterOperatore == $op['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($op['nome_completo']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="toolbar-actions">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    🔍 Filtra
                                </button>
                                <a href="/crm/?action=pratiche&mode=<?= $viewMode === 'kanban' ? 'list' : 'kanban' ?>" 
                                   class="btn btn-secondary btn-sm">
                                    <?= $viewMode === 'kanban' ? '📋 Vista Lista' : '🎯 Vista Kanban' ?>
                                </a>
                                <a href="/crm/?action=pratiche&view=create" class="btn btn-primary btn-sm">
                                    ➕ Nuova Pratica
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Vista Pratiche -->
                    <?php if ($viewMode === 'list'): ?>
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
                            
                            <?php if (empty($pratiche)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <h3>Nessuna pratica trovata</h3>
                                <p>Prova a modificare i filtri o crea una nuova pratica</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($pratiche as $pratica): ?>
                                <div class="list-item" onclick="window.location.href='/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>'">
                                    <div class="pratica-info">
                                        <div class="pratica-cliente">
                                            <?= htmlspecialchars($pratica['cliente_nome']) ?>
                                        </div>
                                        <div class="pratica-numero">
                                            #<?= str_pad($pratica['id'], 6, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($pratica['numero_pratica']) ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <span class="tipo-badge">
                                            <?= $pratica['tipo_config']['icon'] ?> 
                                            <?= $pratica['tipo_config']['label'] ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <span class="stato-badge stato-<?= $pratica['stato'] ?>">
                                            <?= $pratica['stato_config']['icon'] ?>
                                            <?= $pratica['stato_config']['label'] ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <?php if ($pratica['data_scadenza']): ?>
                                            <?php 
                                            $giorni = $pratica['giorni_scadenza'];
                                            $classScadenza = '';
                                            if ($giorni < 0) $classScadenza = 'scadenza-urgente';
                                            elseif ($giorni <= 3) $classScadenza = 'scadenza-prossima';
                                            ?>
                                            <div class="scadenza-info <?= $classScadenza ?>">
                                                📅 <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?>
                                                <?php if ($giorni < 0): ?>
                                                    <small>(scaduta)</small>
                                                <?php elseif ($giorni == 0): ?>
                                                    <small>(oggi)</small>
                                                <?php elseif ($giorni <= 3): ?>
                                                    <small>(<?= $giorni ?> gg)</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $pratica['progress'] ?>%"></div>
                                        </div>
                                        <div class="progress-text">
                                            <?= $pratica['progress'] ?>% (<?= $pratica['task_completati'] ?>/<?= $pratica['totale_task'] ?>)
                                        </div>
                                    </div>
                                    
                                    <div class="operatore-info">
                                        👤 <?= htmlspecialchars($pratica['operatore_nome'] ?? 'Non assegnato') ?>
                                    </div>
                                    
                                    <div class="actions-cell" onclick="event.stopPropagation()">
                                        <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" 
                                           class="action-btn action-view">
                                            👁️ Apri
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Vista Kanban -->
                        <div class="kanban-board">
                            <?php foreach (PRATICHE_STATI as $stato => $config): ?>
                            <div class="kanban-column">
                                <div class="kanban-header">
                                    <div class="kanban-title">
                                        <?= $config['icon'] ?> <?= $config['label'] ?>
                                    </div>
                                    <div class="kanban-count">
                                        <?= count($praticheByStato[$stato]) ?>
                                    </div>
                                </div>
                                
                                <div class="kanban-cards">
                                    <?php foreach ($praticheByStato[$stato] as $pratica): ?>
                                    <div class="kanban-card" onclick="window.location.href='/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>'">
                                        <div class="pratica-cliente">
                                            <?= htmlspecialchars($pratica['cliente_nome']) ?>
                                        </div>
                                        <div class="pratica-numero">
                                            #<?= htmlspecialchars($pratica['numero_pratica']) ?>
                                        </div>
                                        
                                        <div style="margin-top: 0.5rem;">
                                            <span class="tipo-badge">
                                                <?= $pratica['tipo_config']['icon'] ?> 
                                                <?= $pratica['tipo_config']['label'] ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($pratica['data_scadenza']): ?>
                                        <div class="scadenza-info" style="margin-top: 0.5rem;">
                                            📅 <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div style="margin-top: 0.75rem;">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $pratica['progress'] ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($praticheByStato[$stato])): ?>
                                    <div class="empty-state" style="padding: 1rem;">
                                        <p style="margin: 0; font-size: 0.875rem;">Nessuna pratica</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
        
        // Debounce per input search
        let searchTimeout;
        const searchInput = document.querySelector('input[name="cliente"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.closest('form').submit();
                }, 500);
            });
        }
    </script>
</body>
</html>