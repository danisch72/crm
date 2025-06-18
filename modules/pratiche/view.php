<?php
/**
 * modules/pratiche/view.php - Dashboard Pratica con Timeline
 * 
 * ✅ VISUALIZZAZIONE DETTAGLIATA PRATICA E TASK
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata dal router)

// Carica task della pratica
$tasks = $db->select("
    SELECT t.*, 
           CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
           (SELECT SUM(durata_minuti) FROM tracking_task WHERE task_id = t.id) as minuti_tracciati
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    WHERE t.pratica_id = ?
    ORDER BY t.ordine, t.id
", [$pratica['id']]);

// Calcola statistiche
$stats = [
    'task_totali' => count($tasks),
    'task_completati' => count(array_filter($tasks, fn($t) => $t['stato'] === 'completato')),
    'ore_stimate' => array_sum(array_column($tasks, 'ore_stimate')),
    'ore_lavorate' => array_sum(array_map(fn($t) => ($t['minuti_tracciati'] ?? 0) / 60, $tasks)),
    'progress' => 0
];

if ($stats['task_totali'] > 0) {
    $stats['progress'] = round(($stats['task_completati'] / $stats['task_totali']) * 100);
}

// Carica documenti
$documenti = []; // TODO: implementare gestione documenti

// Carica comunicazioni recenti
$comunicazioni = $db->select("
    SELECT cc.*, CONCAT(o.nome, ' ', o.cognome) as operatore_nome
    FROM comunicazioni_clienti cc
    LEFT JOIN operatori o ON cc.operatore_id = o.id
    WHERE cc.cliente_id = ?
    ORDER BY cc.data_contatto DESC
    LIMIT 5
", [$pratica['cliente_id']]);

// Helper functions
function getStatoTaskBadge($stato) {
    $config = TASK_STATI[$stato] ?? TASK_STATI['da_fare'];
    return sprintf(
        '<span class="badge" style="background: %s20; color: %s">%s %s</span>',
        $config['color'],
        $config['color'],
        $config['icon'],
        $config['label']
    );
}

function formatDuration($minuti) {
    if ($minuti < 60) {
        return $minuti . ' min';
    }
    $ore = floor($minuti / 60);
    $min = $minuti % 60;
    return $ore . 'h ' . ($min > 0 ? $min . 'min' : '');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pratica['titolo']) ?> - Pratica #<?= htmlspecialchars($pratica['numero_pratica']) ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    
    <style>
        /* Layout Dashboard Pratica */
        .pratica-dashboard {
            padding: 1.5rem;
            background: #f8f9fa;
            min-height: calc(100vh - 64px);
        }
        
        /* Header Pratica */
        .pratica-header {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .pratica-title-section {
            flex: 1;
        }
        
        .pratica-numero {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .pratica-titolo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.5rem 0;
        }
        
        .pratica-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            color: #6b7280;
        }
        
        .meta-item strong {
            color: #374151;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a37;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        /* Progress Bar */
        .progress-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .progress-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }
        
        .progress-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary-green);
        }
        
        .progress-bar {
            width: 100%;
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
        
        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Timeline Task */
        .task-timeline {
            position: relative;
        }
        
        .task-timeline::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .task-item {
            position: relative;
            padding-left: 48px;
            margin-bottom: 1.5rem;
        }
        
        .task-item:last-child {
            margin-bottom: 0;
        }
        
        .task-marker {
            position: absolute;
            left: 8px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e5e7eb;
        }
        
        .task-item.completed .task-marker {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .task-item.in-progress .task-marker {
            background: #f59e0b;
            border-color: #f59e0b;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        .task-content {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
            border: 1px solid #e5e7eb;
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .task-title {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.875rem;
            flex: 1;
        }
        
        .task-actions {
            display: flex;
            gap: 0.25rem;
        }
        
        .btn-task {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }
        
        .task-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 6px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .stat-icon {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        
        /* Info Box */
        .info-box {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.8125rem;
        }
        
        .info-label {
            color: #6b7280;
        }
        
        .info-value {
            font-weight: 500;
            color: #1f2937;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        
        /* Badge styles */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Status specific */
        .stato-badge {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        /* Quick actions */
        .quick-actions {
            display: grid;
            gap: 0.5rem;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
            font-size: 0.8125rem;
            transition: all 0.2s;
        }
        
        .quick-action:hover {
            background: #f3f4f6;
            color: var(--primary-green);
        }
        
        /* Documents */
        .document-list {
            display: grid;
            gap: 0.5rem;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f9fafb;
            border-radius: 4px;
            font-size: 0.8125rem;
        }
        
        .document-icon {
            font-size: 1rem;
        }
        
        /* Timeline comunicazioni */
        .comunicazione-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .comunicazione-item:last-child {
            border-bottom: none;
        }
        
        .comunicazione-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .comunicazione-tipo {
            font-weight: 500;
            font-size: 0.8125rem;
            color: #1f2937;
        }
        
        .comunicazione-data {
            font-size: 0.6875rem;
            color: #6b7280;
        }
        
        .comunicazione-content {
            font-size: 0.75rem;
            color: #4b5563;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="pratica-dashboard">
                    <!-- Header Pratica -->
                    <div class="pratica-header">
                        <div class="header-top">
                            <div class="pratica-title-section">
                                <div class="pratica-numero">
                                    Pratica #<?= htmlspecialchars($pratica['numero_pratica']) ?>
                                </div>
                                <h1 class="pratica-titolo">
                                    <?= htmlspecialchars($pratica['titolo']) ?>
                                </h1>
                                <div class="pratica-meta">
                                    <div class="meta-item">
                                        <span><?= getPraticaType($pratica['tipo_pratica'])['icon'] ?></span>
                                        <span><?= getPraticaType($pratica['tipo_pratica'])['label'] ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span>🏢</span>
                                        <strong><?= htmlspecialchars($pratica['cliente_nome']) ?></strong>
                                    </div>
                                    <div class="meta-item">
                                        <span>👤</span>
                                        <span><?= htmlspecialchars($pratica['operatore_nome']) ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span>📅</span>
                                        <span>Scade il <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?></span>
                                        <?php if ($pratica['giorni_scadenza'] < 0): ?>
                                            <span style="color: #dc2626; font-weight: 600;">(Scaduta)</span>
                                        <?php elseif ($pratica['giorni_scadenza'] <= 7): ?>
                                            <span style="color: #f59e0b; font-weight: 600;">(<?= $pratica['giorni_scadenza'] ?> giorni)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="header-actions">
                                <div class="stato-badge">
                                    <?= getPraticaStato($pratica['stato'])['icon'] ?>
                                    <?= getPraticaStato($pratica['stato'])['label'] ?>
                                </div>
                                <?php if (getPraticaStato($pratica['stato'])['can_edit']): ?>
                                    <a href="/crm/?action=pratiche&view=edit&id=<?= $pratica['id'] ?>" class="btn btn-secondary">
                                        ✏️ Modifica
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div class="progress-section">
                            <div class="progress-header">
                                <span class="progress-label">Avanzamento pratica</span>
                                <span class="progress-value"><?= $stats['progress'] ?>% completato</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $stats['progress'] ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📋</div>
                            <div class="stat-value"><?= $stats['task_completati'] ?>/<?= $stats['task_totali'] ?></div>
                            <div class="stat-label">Task completati</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">⏱️</div>
                            <div class="stat-value"><?= number_format($stats['ore_lavorate'], 1, ',', '.') ?>h</div>
                            <div class="stat-label">Ore lavorate</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📊</div>
                            <div class="stat-value"><?= number_format($stats['ore_stimate'], 1, ',', '.') ?>h</div>
                            <div class="stat-label">Ore stimate</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">💰</div>
                            <div class="stat-value">€<?= number_format($pratica['valore_pratica'], 0, ',', '.') ?></div>
                            <div class="stat-label">Valore pratica</div>
                        </div>
                    </div>
                    
                    <!-- Main Grid -->
                    <div class="dashboard-grid">
                        <!-- Colonna principale -->
                        <div>
                            <!-- Task Timeline -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        📋 Task e Attività
                                        <span style="font-weight: normal; font-size: 0.875rem; color: #6b7280;">
                                            (<?= $stats['task_totali'] ?> totali)
                                        </span>
                                    </h3>
                                    <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-sm btn-primary">
                                        ➕ Gestisci Task
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($tasks)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📋</div>
                                            <p>Nessun task definito</p>
                                            <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                               class="btn btn-primary btn-sm">
                                                Aggiungi Task
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="task-timeline">
                                            <?php foreach ($tasks as $task): ?>
                                                <div class="task-item <?= $task['stato'] === 'completato' ? 'completed' : ($task['stato'] === 'in_corso' ? 'in-progress' : '') ?>">
                                                    <div class="task-marker"></div>
                                                    <div class="task-content">
                                                        <div class="task-header">
                                                            <div class="task-title">
                                                                <?= htmlspecialchars($task['titolo']) ?>
                                                            </div>
                                                            <div class="task-actions">
                                                                <?= getStatoTaskBadge($task['stato']) ?>
                                                                <?php if ($task['stato'] === 'da_fare'): ?>
                                                                    <button class="btn btn-task btn-primary" 
                                                                            onclick="startTask(<?= $task['id'] ?>)">
                                                                        ▶️ Inizia
                                                                    </button>
                                                                <?php elseif ($task['stato'] === 'in_corso'): ?>
                                                                    <a href="/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>&task=<?= $task['id'] ?>" 
                                                                       class="btn btn-task btn-secondary">
                                                                        ⏱️ Tracking
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($task['descrizione']): ?>
                                                            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                                                <?= nl2br(htmlspecialchars($task['descrizione'])) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="task-meta">
                                                            <div class="task-meta-item">
                                                                <span>👤</span>
                                                                <span><?= htmlspecialchars($task['operatore_nome']) ?></span>
                                                            </div>
                                                            <div class="task-meta-item">
                                                                <span>⏱️</span>
                                                                <span>
                                                                    <?= formatDuration($task['minuti_tracciati'] ?? 0) ?> 
                                                                    / <?= number_format($task['ore_stimate'], 1, ',', '.') ?>h
                                                                </span>
                                                            </div>
                                                            <?php if ($task['stato'] === 'completato' && $task['data_completamento']): ?>
                                                                <div class="task-meta-item">
                                                                    <span>✅</span>
                                                                    <span><?= date('d/m/Y', strtotime($task['data_completamento'])) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Descrizione pratica (se presente) -->
                            <?php if (!empty($pratica['descrizione'])): ?>
                                <div class="card" style="margin-top: 1.5rem;">
                                    <div class="card-header">
                                        <h3 class="card-title">📝 Descrizione</h3>
                                    </div>
                                    <div class="card-body">
                                        <p style="margin: 0; font-size: 0.875rem; line-height: 1.6; color: #374151;">
                                            <?= nl2br(htmlspecialchars($pratica['descrizione'])) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Colonna laterale -->
                        <div>
                            <!-- Info pratica -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">ℹ️ Informazioni</h3>
                                </div>
                                <div class="card-body">
                                    <div class="info-box">
                                        <div class="info-row">
                                            <span class="info-label">Priorità:</span>
                                            <span class="info-value">
                                                <?= PRATICHE_PRIORITA[$pratica['priorita']]['label'] ?? 'Media' ?>
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Creata il:</span>
                                            <span class="info-value">
                                                <?= date('d/m/Y', strtotime($pratica['data_apertura'])) ?>
                                            </span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Scadenza:</span>
                                            <span class="info-value">
                                                <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?>
                                            </span>
                                        </div>
                                        <?php if ($pratica['data_completamento']): ?>
                                            <div class="info-row">
                                                <span class="info-label">Completata:</span>
                                                <span class="info-value">
                                                    <?= date('d/m/Y', strtotime($pratica['data_completamento'])) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <a href="/crm/?action=clienti&view=view&id=<?= $pratica['cliente_id'] ?>" 
                                           class="btn btn-secondary btn-sm" style="width: 100%;">
                                            🏢 Vai al cliente
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Azioni rapide -->
                            <div class="card" style="margin-top: 1rem;">
                                <div class="card-header">
                                    <h3 class="card-title">⚡ Azioni Rapide</h3>
                                </div>
                                <div class="card-body">
                                    <div class="quick-actions">
                                        <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                           class="quick-action">
                                            <span>📋</span>
                                            <span>Gestisci Task</span>
                                        </a>
                                        <a href="/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>" 
                                           class="quick-action">
                                            <span>⏱️</span>
                                            <span>Time Tracking</span>
                                        </a>
                                        <a href="/crm/?action=pratiche&view=documenti&id=<?= $pratica['id'] ?>" 
                                           class="quick-action">
                                            <span>📁</span>
                                            <span>Documenti</span>
                                        </a>
                                        <?php if ($pratica['stato'] === 'completata'): ?>
                                            <a href="/crm/?action=pratiche&view=fattura&id=<?= $pratica['id'] ?>" 
                                               class="quick-action">
                                                <span>💰</span>
                                                <span>Genera Fattura</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Documenti -->
                            <div class="card" style="margin-top: 1rem;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        📁 Documenti
                                        <span style="font-weight: normal; font-size: 0.875rem; color: #6b7280;">
                                            (<?= count($documenti) ?>)
                                        </span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($documenti)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📁</div>
                                            <p style="font-size: 0.875rem;">Nessun documento</p>
                                            <a href="/crm/?action=pratiche&view=documenti&id=<?= $pratica['id'] ?>" 
                                               class="btn btn-primary btn-sm">
                                                Carica Documento
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="document-list">
                                            <!-- TODO: Lista documenti -->
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Comunicazioni recenti -->
                            <div class="card" style="margin-top: 1rem;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        💬 Comunicazioni Recenti
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($comunicazioni)): ?>
                                        <p style="text-align: center; color: #6b7280; font-size: 0.875rem;">
                                            Nessuna comunicazione
                                        </p>
                                    <?php else: ?>
                                        <?php foreach ($comunicazioni as $com): ?>
                                            <div class="comunicazione-item">
                                                <div class="comunicazione-header">
                                                    <span class="comunicazione-tipo">
                                                        <?= getTipoComunicazioneIcon($com['tipo']) ?> 
                                                        <?= ucfirst($com['tipo']) ?>
                                                    </span>
                                                    <span class="comunicazione-data">
                                                        <?= date('d/m H:i', strtotime($com['data_contatto'])) ?>
                                                    </span>
                                                </div>
                                                <div class="comunicazione-content">
                                                    <?= htmlspecialchars($com['oggetto'] ?: 'Nessun oggetto') ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Start task
        function startTask(taskId) {
            if (confirm('Vuoi iniziare questo task?')) {
                fetch('/crm/modules/pratiche/api/task_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start_task',
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Errore: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Errore durante l\'avvio del task');
                });
            }
        }
        
        // Helper per icone comunicazioni
        function getTipoComunicazioneIcon($tipo) {
            const icons = {
                'email': '📧',
                'telefono': '📞',
                'incontro': '🤝',
                'nota': '📝'
            };
            return icons[$tipo] || '💬';
        }
    </script>
</body>
</html>