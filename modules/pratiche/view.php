<?php
/**
 * modules/pratiche/view.php - Dashboard Pratica Ultra Compatto
 * 
 * ✅ DESIGN OTTIMIZZATO PER USO INTENSIVO (8h/giorno)
 * ✅ LAYOUT COMPATTO STILE DATEV OPTIMAL
 * ✅ INTEGRAZIONE PERFETTA CON SIDEBAR E HEADER
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Carica config se non già caricata
if (!defined('PRATICHE_TYPES')) {
    require_once __DIR__ . '/config.php';
}

// Variabili dal router: $sessionInfo, $db, $currentUser, $pratica

// Carica dati completi pratica con JOIN
$pratica_completa = $db->selectOne("
    SELECT p.*, 
           c.ragione_sociale as cliente_nome,
           c.codice_fiscale,
           c.partita_iva,
           c.telefono as cliente_telefono,
           c.email as cliente_email,
           c.indirizzo as cliente_indirizzo,
           c.citta as cliente_citta,
           CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
           o.email as operatore_email
    FROM pratiche p
    LEFT JOIN clienti c ON p.cliente_id = c.id
    LEFT JOIN operatori o ON p.operatore_assegnato_id = o.id
    WHERE p.id = ?
", [$pratica['id']]);

if (!$pratica_completa) {
    $_SESSION['error_message'] = '⚠️ Errore caricamento pratica';
    header('Location: /crm/?action=pratiche');
    exit;
}

// Carica task con tracking inline
$tasks = $db->select("
    SELECT t.*, 
           CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
           (SELECT SUM(durata_minuti) FROM tracking_task WHERE task_id = t.id) as minuti_tracciati,
           (SELECT COUNT(*) FROM tracking_task WHERE task_id = t.id AND DATE(created_at) = CURDATE()) as tracking_oggi
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    WHERE t.pratica_id = ?
    ORDER BY t.ordine, t.id
", [$pratica['id']]);

// Calcola statistiche compatte
$stats = [
    'completati' => count(array_filter($tasks, fn($t) => $t['stato'] === 'completato')),
    'totali' => count($tasks),
    'ore_lavorate' => round(array_sum(array_map(fn($t) => ($t['minuti_tracciati'] ?? 0) / 60, $tasks)), 1),
    'ore_stimate' => array_sum(array_column($tasks, 'ore_stimate'))
];

// Carica ultimi documenti (max 5)
$documenti = $db->select("
    SELECT d.*, CONCAT(o.nome, ' ', o.cognome) as operatore_nome
    FROM documenti_pratiche d
    LEFT JOIN operatori o ON d.operatore_id = o.id
    WHERE d.pratica_id = ?
    ORDER BY d.data_upload DESC
    LIMIT 5
", [$pratica['id']]);

// Helper functions
function getStatoTaskBadge($stato) {
    $config = TASK_STATI[$stato] ?? TASK_STATI['da_fare'];
    return sprintf(
        '<span class="task-stato-badge stato-%s">%s %s</span>',
        $stato,
        $config['icon'],
        $config['label']
    );
}

function getScadenzaInfo($data_scadenza) {
    if (!$data_scadenza) return ['class' => '', 'text' => ''];
    $giorni = (strtotime($data_scadenza) - strtotime('today')) / 86400;
    
    if ($giorni < 0) return ['class' => 'scadenza-scaduta', 'text' => 'SCADUTA'];
    if ($giorni == 0) return ['class' => 'scadenza-oggi', 'text' => 'OGGI'];
    if ($giorni == 1) return ['class' => 'scadenza-domani', 'text' => 'DOMANI'];
    if ($giorni <= 3) return ['class' => 'scadenza-urgente', 'text' => "tra {$giorni}gg"];
    if ($giorni <= 7) return ['class' => 'scadenza-prossima', 'text' => "tra {$giorni}gg"];
    return ['class' => '', 'text' => date('d/m', strtotime($data_scadenza))];
}

// Configurazione pagina
$pageTitle = 'Pratica #' . str_pad($pratica_completa['numero_pratica'], 4, '0', STR_PAD_LEFT);
$pageSubtitle = $pratica_completa['nome'];
$pageIcon = PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['icon'] ?? '📋';

// Breadcrumb
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/crm/?action=dashboard'],
    ['label' => 'Pratiche', 'url' => '/crm/?action=pratiche'],
    ['label' => $pratica_completa['cliente_nome'], 'url' => '/crm/?action=clienti&view=view&id=' . $pratica_completa['cliente_id']],
    ['label' => 'Pratica #' . str_pad($pratica_completa['numero_pratica'], 4, '0', STR_PAD_LEFT), 'active' => true]
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($pratica_completa['nome']) ?> - CRM Re.De</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    
    <style>
        /* Layout Ultra Compatto */
        .pratica-container {
            padding: 1rem;
            background: var(--gray-50);
            min-height: calc(100vh - 64px);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
        }
        
        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-green);
        }
        
        .breadcrumb .separator {
            color: var(--gray-400);
        }
        
        .breadcrumb .active {
            color: var(--gray-900);
            font-weight: 500;
        }
        
        /* Box Cliente Prominente */
        .cliente-highlight-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 120, 73, 0.1);
        }
        
        .cliente-info {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .cliente-icon {
            font-size: 2rem;
            background: white;
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .cliente-details h2 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }
        
        .cliente-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8125rem;
            color: var(--gray-600);
        }
        
        .cliente-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Header Pratica Compatto */
        .pratica-header {
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pratica-title-group {
            flex: 1;
        }
        
        .pratica-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .pratica-subtitle {
            display: flex;
            gap: 1rem;
            font-size: 0.8125rem;
            color: var(--gray-500);
        }
        
        /* Progress Bar Minimalista */
        .progress-mini {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--gray-50);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
        }
        
        .progress-bar-mini {
            width: 120px;
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
            font-weight: 600;
            color: var(--gray-700);
        }
        
        /* Grid Layout 2 colonne */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1rem;
        }
        
        /* Cards Compatte */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
        }
        
        .card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        
        .card-body {
            padding: 0.75rem;
        }
        
        /* Task List Compatta con Time Tracking */
        .task-item {
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            position: relative;
        }
        
        .task-item:hover {
            border-color: var(--primary-green);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.375rem;
        }
        
        .task-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-900);
            flex: 1;
        }
        
        .task-cliente-badge {
            position: absolute;
            top: 0.375rem;
            right: 0.375rem;
            font-size: 0.625rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
        }
        
        .task-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .task-meta-left {
            display: flex;
            gap: 0.75rem;
        }
        
        .task-actions {
            display: flex;
            gap: 0.375rem;
        }
        
        /* Time Tracking Inline */
        .time-tracker {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .btn-track {
            padding: 0.25rem 0.5rem;
            font-size: 0.6875rem;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-track:hover {
            background: #005a37;
        }
        
        .btn-track.tracking {
            background: #dc2626;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .time-display {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        /* Task Stati */
        .task-stato-badge {
            font-size: 0.6875rem;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-weight: 500;
        }
        
        .stato-da_fare {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .stato-in_corso {
            background: #fed7aa;
            color: #c2410c;
        }
        
        .stato-completato {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* Scadenze */
        .scadenza-scaduta {
            color: #dc2626;
            font-weight: 600;
        }
        
        .scadenza-oggi,
        .scadenza-domani {
            color: #ea580c;
            font-weight: 600;
        }
        
        .scadenza-urgente {
            color: #d97706;
        }
        
        /* Sidebar Info */
        .info-section {
            margin-bottom: 0.75rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.375rem 0;
            font-size: 0.8125rem;
        }
        
        .info-label {
            color: var(--gray-500);
        }
        
        .info-value {
            color: var(--gray-900);
            font-weight: 500;
        }
        
        /* Stats mini */
        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .stat-mini {
            text-align: center;
            padding: 0.5rem;
            background: var(--gray-50);
            border-radius: 6px;
        }
        
        .stat-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.6875rem;
            color: var(--gray-500);
        }
        
        /* Quick Actions Flottanti */
        .quick-actions-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            z-index: 100;
        }
        
        .fab {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        .fab-secondary {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        /* Bottoni standard */
        .btn {
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
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
            background: #005a37;
        }
        
        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-secondary:hover {
            background: var(--gray-50);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .cliente-highlight-box {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .quick-actions-float {
                bottom: 1rem;
                right: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .pratica-container {
                padding: 0.75rem;
            }
            
            .cliente-info {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .task-cliente-badge {
                display: none;
            }
        }
    </style>
</head>
<body class="datev-compact">
    <div class="app-layout">
        <!-- Sidebar -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <!-- Header con Timer -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="pratica-container">
                    <!-- Breadcrumb -->
                    <nav class="breadcrumb">
                        <?php foreach ($breadcrumb as $index => $item): ?>
                            <?php if (isset($item['active'])): ?>
                                <span class="active"><?= htmlspecialchars($item['label']) ?></span>
                            <?php else: ?>
                                <a href="<?= $item['url'] ?>"><?= htmlspecialchars($item['label']) ?></a>
                                <span class="separator">›</span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                    
                    <!-- Box Cliente Prominente -->
                    <div class="cliente-highlight-box">
                        <div class="cliente-info">
                            <div class="cliente-icon">🏢</div>
                            <div class="cliente-details">
                                <h2><?= htmlspecialchars($pratica_completa['cliente_nome']) ?></h2>
                                <div class="cliente-meta">
                                    <?php if ($pratica_completa['partita_iva']): ?>
                                        <span>P.IVA: <?= htmlspecialchars($pratica_completa['partita_iva']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($pratica_completa['codice_fiscale']): ?>
                                        <span>CF: <?= htmlspecialchars($pratica_completa['codice_fiscale']) ?></span>
                                    <?php endif; ?>
                                    <span>📞 <?= htmlspecialchars($pratica_completa['cliente_telefono'] ?? 'N/D') ?></span>
                                    <span>📧 <?= htmlspecialchars($pratica_completa['cliente_email'] ?? 'N/D') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="cliente-actions">
                            <a href="/crm/?action=clienti&view=view&id=<?= $pratica_completa['cliente_id'] ?>" 
                               class="btn btn-secondary btn-sm">
                                Scheda Cliente
                            </a>
                            <a href="mailto:<?= $pratica_completa['cliente_email'] ?>" 
                               class="btn btn-primary btn-sm">
                                📧 Email
                            </a>
                        </div>
                    </div>
                    
                    <!-- Header Pratica -->
                    <div class="pratica-header">
                        <div class="pratica-title-group">
                            <h1 class="pratica-title">
                                <span style="color: <?= PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['color'] ?>">
                                    <?= PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['icon'] ?>
                                </span>
                                <?= htmlspecialchars($pratica_completa['nome']) ?>
                            </h1>
                            <div class="pratica-subtitle">
                                <span>Pratica #<?= str_pad($pratica_completa['numero_pratica'], 4, '0', STR_PAD_LEFT) ?></span>
                                <span>•</span>
                                <span><?= PRATICHE_STATI[$pratica_completa['stato']]['label'] ?></span>
                                <span>•</span>
                                <span>Operatore: <?= htmlspecialchars($pratica_completa['operatore_nome']) ?></span>
                                <?php if ($pratica_completa['data_scadenza']): ?>
                                    <?php $scadenza = getScadenzaInfo($pratica_completa['data_scadenza']); ?>
                                    <span>•</span>
                                    <span class="<?= $scadenza['class'] ?>">
                                        Scadenza: <?= $scadenza['text'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="progress-mini">
                            <div class="progress-bar-mini">
                                <div class="progress-fill" style="width: <?= $stats['totali'] > 0 ? round(($stats['completati'] / $stats['totali']) * 100) : 0 ?>%"></div>
                            </div>
                            <span class="progress-text">
                                <?= $stats['completati'] ?>/<?= $stats['totali'] ?> task
                            </span>
                        </div>
                    </div>
                    
                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- Colonna principale -->
                        <div>
                            <!-- Task List con Time Tracking -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <span>📋</span>
                                        <span>Task e Attività</span>
                                    </h3>
                                    <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-primary btn-sm">
                                        ➕ Nuovo Task
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($tasks)): ?>
                                        <p style="text-align: center; color: var(--gray-500); padding: 2rem 0;">
                                            Nessun task presente. 
                                            <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>">
                                                Crea il primo task
                                            </a>
                                        </p>
                                    <?php else: ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <div class="task-item">
                                                <div class="task-cliente-badge">
                                                    🏢 <?= htmlspecialchars(substr($pratica_completa['cliente_nome'], 0, 20)) ?>
                                                </div>
                                                
                                                <div class="task-header">
                                                    <div class="task-title"><?= htmlspecialchars($task['titolo']) ?></div>
                                                    <?= getStatoTaskBadge($task['stato']) ?>
                                                </div>
                                                
                                                <div class="task-meta">
                                                    <div class="task-meta-left">
                                                        <span>👤 <?= htmlspecialchars($task['operatore_nome'] ?? 'Non assegnato') ?></span>
                                                        <?php if ($task['data_scadenza']): ?>
                                                            <?php $scadenza = getScadenzaInfo($task['data_scadenza']); ?>
                                                            <span class="<?= $scadenza['class'] ?>">📅 <?= $scadenza['text'] ?></span>
                                                        <?php endif; ?>
                                                        <span>⏱️ <?= number_format($task['ore_stimate'], 1) ?>h stimate</span>
                                                    </div>
                                                    
                                                    <div class="task-actions">
                                                        <div class="time-tracker">
                                                            <button class="btn-track" 
                                                                    data-task-id="<?= $task['id'] ?>"
                                                                    data-tracking="<?= $task['tracking_oggi'] > 0 ? 'true' : 'false' ?>">
                                                                <?= $task['tracking_oggi'] > 0 ? '⏸️' : '▶️' ?>
                                                            </button>
                                                            <span class="time-display">
                                                                <?= round(($task['minuti_tracciati'] ?? 0) / 60, 1) ?>h
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Documenti -->
                            <div class="card" style="margin-top: 1rem;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <span>📁</span>
                                        <span>Documenti Recenti</span>
                                    </h3>
                                    <a href="#" class="btn btn-secondary btn-sm">
                                        Tutti i documenti
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($documenti)): ?>
                                        <p style="text-align: center; color: var(--gray-500); padding: 1rem 0;">
                                            Nessun documento caricato
                                        </p>
                                    <?php else: ?>
                                        <?php foreach ($documenti as $doc): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100);">
                                                <div>
                                                    <div style="font-size: 0.875rem; color: var(--gray-900);">
                                                        📄 <?= htmlspecialchars($doc['nome_file']) ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                        <?= date('d/m/Y H:i', strtotime($doc['data_upload'])) ?> • <?= htmlspecialchars($doc['operatore_nome']) ?>
                                                    </div>
                                                </div>
                                                <a href="#" class="btn btn-sm">⬇️</a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar -->
                        <div>
                            <!-- Stats compatte -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="stats-mini">
                                        <div class="stat-mini">
                                            <div class="stat-value"><?= $stats['ore_lavorate'] ?>h</div>
                                            <div class="stat-label">Ore Lavorate</div>
                                        </div>
                                        <div class="stat-mini">
                                            <div class="stat-value"><?= $stats['ore_stimate'] ?>h</div>
                                            <div class="stat-label">Ore Stimate</div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-section">
                                        <div class="info-row">
                                            <span class="info-label">Tipo:</span>
                                            <span class="info-value"><?= PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['label'] ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Priorità:</span>
                                            <span class="info-value"><?= ucfirst($pratica_completa['priorita'] ?? 'media') ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Creata il:</span>
                                            <span class="info-value"><?= date('d/m/Y', strtotime($pratica_completa['created_at'])) ?></span>
                                        </div>
                                        <?php if ($pratica_completa['tariffa_oraria']): ?>
                                        <div class="info-row">
                                            <span class="info-label">Tariffa:</span>
                                            <span class="info-value">€<?= number_format($pratica_completa['tariffa_oraria'], 0) ?>/h</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Azioni rapide -->
                            <div class="card" style="margin-top: 0.75rem;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <span>⚡</span>
                                        <span>Azioni Rapide</span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <a href="/crm/?action=pratiche&view=edit&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary btn-sm" style="width: 100%;">
                                            ✏️ Modifica Pratica
                                        </a>
                                        <a href="/crm/?action=pratiche&view=workflow&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary btn-sm" style="width: 100%;">
                                            🔄 Cambia Stato
                                        </a>
                                        <a href="#" class="btn btn-secondary btn-sm" style="width: 100%;">
                                            📧 Email Cliente
                                        </a>
                                        <?php if ($pratica_completa['stato'] === 'completata'): ?>
                                        <a href="#" class="btn btn-primary btn-sm" style="width: 100%;">
                                            💰 Genera Fattura
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Note (se presenti) -->
                            <?php if (!empty($pratica_completa['descrizione'])): ?>
                            <div class="card" style="margin-top: 0.75rem;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <span>📝</span>
                                        <span>Note</span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <p style="margin: 0; font-size: 0.8125rem; line-height: 1.4; color: var(--gray-700);">
                                        <?= nl2br(htmlspecialchars($pratica_completa['descrizione'])) ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Quick Actions Flottanti -->
    <div class="quick-actions-float">
        <button class="fab fab-secondary" onclick="window.print()" title="Stampa">
            🖨️
        </button>
        <button class="fab" onclick="location.href='/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>'" title="Time Tracking">
            ⏱️
        </button>
    </div>
    
    <!-- Scripts -->
    <script>
        // Time tracking inline
        document.querySelectorAll('.btn-track').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.dataset.taskId;
                const isTracking = this.dataset.tracking === 'true';
                
                if (isTracking) {
                    // Stop tracking
                    if (confirm('Fermare il tracking del tempo?')) {
                        // TODO: chiamata AJAX per fermare tracking
                        this.textContent = '▶️';
                        this.classList.remove('tracking');
                        this.dataset.tracking = 'false';
                    }
                } else {
                    // Start tracking
                    // TODO: chiamata AJAX per iniziare tracking
                    this.textContent = '⏸️';
                    this.classList.add('tracking');
                    this.dataset.tracking = 'true';
                }
            });
        });
        
        // Auto-update timer displays
        setInterval(() => {
            // TODO: aggiornare display timer via AJAX
        }, 60000); // ogni minuto
    </script>
</body>
</html>