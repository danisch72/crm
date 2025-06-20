<?php
/**
 * modules/pratiche/view.php - Dashboard Pratica Ottimizzata
 * ✅ VERSIONE PULITA CON CSS UNIFICATO
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Carica pratica completa con statistiche
$pratica_completa = getPraticaCompleta($pratica['id']);

if (!$pratica_completa) {
    $_SESSION['error_message'] = '⚠️ Errore nel caricamento della pratica';
    header('Location: /crm/?action=pratiche');
    exit;
}

// Carica task della pratica
$tasks = $db->select("
    SELECT 
        t.*,
        CONCAT(o.nome, ' ', o.cognome) as assegnato_a
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    WHERE t.pratica_id = ?
    ORDER BY t.ordine, t.id
", [$pratica['id']]);

// Calcola statistiche
$stats = [
    'totali' => count($tasks),
    'completati' => 0,
    'in_corso' => 0,
    'da_fare' => 0,
    'ore_stimate' => 0,
    'ore_lavorate' => 0
];

foreach ($tasks as $task) {
    $stato = $task['stato'] ?? 'da_fare';
    if(isset($stats[$stato])) {
        $stats[$stato]++;
    }
    $stats['ore_stimate'] += floatval($task['ore_stimate']);
    $stats['ore_lavorate'] += floatval($task['ore_lavorate']);
}

$progressPercentuale = $stats['totali'] > 0 
    ? round(($stats['completati'] / $stats['totali']) * 100) 
    : 0;

// Carica attività recenti
$activities = $db->select("
    SELECT 
        'task' as tipo,
        t.titolo,
        t.updated_at as data,
        CONCAT(o.nome, ' ', o.cognome) as operatore
    FROM task t
    LEFT JOIN operatori o ON t.operatore_assegnato_id = o.id
    WHERE t.pratica_id = ?
    ORDER BY t.updated_at DESC
    LIMIT 10
", [$pratica['id']]);

// Imposta variabili per header
$pageTitle = $pratica_completa['titolo'];
$pageIcon = '📋';

// Funzione helper per badge stato task
function getStatoTaskBadge($stato) {
    $config = [
        'da_fare' => ['class' => 'badge-secondary', 'label' => 'Da fare'],
        'in_corso' => ['class' => 'badge-warning', 'label' => 'In corso'],
        'completato' => ['class' => 'badge-success', 'label' => 'Completato'],
        'bloccato' => ['class' => 'badge-danger', 'label' => 'Bloccato']
    ];
    
    $stateConfig = $config[$stato] ?? $config['da_fare'];
    return '<span class="badge ' . $stateConfig['class'] . '">' . $stateConfig['label'] . '</span>';
}

// Funzione helper per priorità
function getPrioritaBadge($priorita) {
    $config = [
        'bassa' => ['class' => 'badge-info', 'icon' => '🔵'],
        'media' => ['class' => 'badge-warning', 'icon' => '🟡'],
        'alta' => ['class' => 'badge-danger', 'icon' => '🔴'],
        'urgente' => ['class' => 'badge-danger', 'icon' => '🚨']
    ];
    
    $prioConfig = $config[$priorita] ?? $config['media'];
    return '<span class="badge ' . $prioConfig['class'] . '">' . $prioConfig['icon'] . ' ' . ucfirst($priorita) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pratica_completa['titolo']) ?> - CRM Re.De</title>
    
    <!-- ✅ CSS UNIFICATO -->
    <link rel="stylesheet" href="/crm/assets/css/datev-koinos-unified.css">
</head>
<body>
    <div class="app-layout">
        <!-- ✅ SIDEBAR CORRETTA -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <!-- ✅ HEADER -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="container">
                    <!-- Breadcrumb -->
                    <nav class="mb-3" style="font-size: 0.875rem;">
                        <a href="/crm/?action=dashboard" class="text-muted">Dashboard</a> /
                        <a href="/crm/?action=pratiche" class="text-muted">Pratiche</a> /
                        <span class="text-primary"><?= htmlspecialchars($pratica_completa['titolo']) ?></span>
                    </nav>

                    <!-- Header Pratica -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h1 class="h3 mb-2"><?= htmlspecialchars($pratica_completa['titolo']) ?></h1>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="text-muted">Pratica #<?= str_pad($pratica['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                        <?= getPrioritaBadge($pratica_completa['priorita']) ?>
                                        <span class="badge badge-info">
                                            <?= htmlspecialchars($pratica_completa['tipo_pratica']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="/crm/?action=pratiche&view=edit&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-secondary btn-sm">
                                        ✏️ Modifica
                                    </a>
                                    <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-primary btn-sm">
                                        📋 Gestione Task
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="font-weight-600">Avanzamento</span>
                                <span class="text-primary font-weight-600"><?= $progressPercentuale ?>%</span>
                            </div>
                            <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= $progressPercentuale ?>%; height: 100%; background: var(--primary-green); transition: width 0.3s;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2 text-muted" style="font-size: 0.75rem;">
                                <span><?= $stats['completati'] ?> completati</span>
                                <span><?= $stats['totali'] ?> task totali</span>
                            </div>
                        </div>
                    </div>

                    <!-- Main Grid -->
                    <div class="row">
                        <!-- Colonna principale -->
                        <div class="col-lg-8">
                            <!-- Info Cliente -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">🏢 Cliente</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1">
                                                <strong>Ragione Sociale:</strong><br>
                                                <?= htmlspecialchars($pratica_completa['cliente_nome']) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1">
                                                <strong>Codice Fiscale:</strong><br>
                                                <?= htmlspecialchars($pratica_completa['cliente_cf'] ?? 'N/D') ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Lista Task -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 class="card-title">📋 Task (<?= count($tasks) ?>)</h3>
                                    <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-primary btn-sm">
                                        ➕ Aggiungi Task
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($tasks)): ?>
                                        <p class="text-muted text-center">Nessun task presente</p>
                                    <?php else: ?>
                                        <div class="table-container">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Task</th>
                                                        <th>Assegnato a</th>
                                                        <th>Stato</th>
                                                        <th>Ore</th>
                                                        <th width="100">Azioni</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($tasks as $task): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($task['titolo']) ?></strong>
                                                                <?php if ($task['descrizione']): ?>
                                                                    <br><small class="text-muted">
                                                                        <?= htmlspecialchars(substr($task['descrizione'], 0, 100)) ?>...
                                                                    </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($task['assegnato_a'] ?? 'Non assegnato') ?></td>
                                                            <td><?= getStatoTaskBadge($task['stato']) ?></td>
                                                            <td>
                                                                <small>
                                                                    <?= number_format($task['ore_lavorate'], 1) ?> / 
                                                                    <?= number_format($task['ore_stimate'], 1) ?>h
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>&task_id=<?= $task['id'] ?>" 
                                                                   class="btn btn-sm btn-secondary">
                                                                    👁️
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Attività Recenti -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">📊 Attività Recenti</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($activities)): ?>
                                        <p class="text-muted text-center">Nessuna attività recente</p>
                                    <?php else: ?>
                                        <div class="activity-list">
                                            <?php foreach ($activities as $activity): ?>
                                                <div class="activity-item">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <strong><?= htmlspecialchars($activity['titolo']) ?></strong>
                                                            <?php if ($activity['operatore']): ?>
                                                                <br><small class="text-muted">
                                                                    da <?= htmlspecialchars($activity['operatore']) ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?= date('d/m/Y H:i', strtotime($activity['data'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="col-lg-4">
                            <!-- Statistiche -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">📊 Statistiche</h3>
                                </div>
                                <div class="card-body">
                                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value"><?= $stats['totali'] ?></div>
                                            <div class="quick-stat-label">Task Totali</div>
                                        </div>
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value"><?= $stats['completati'] ?></div>
                                            <div class="quick-stat-label">Completati</div>
                                        </div>
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value">
                                                <?= number_format($stats['ore_stimate'], 1) ?>h
                                            </div>
                                            <div class="quick-stat-label">Ore Stimate</div>
                                        </div>
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value">
                                                <?= number_format($stats['ore_lavorate'], 1) ?>h
                                            </div>
                                            <div class="quick-stat-label">Ore Lavorate</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Pratica -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">ℹ️ Dettagli Pratica</h3>
                                </div>
                                <div class="card-body">
                                    <dl class="mb-0">
                                        <dt>Data Apertura</dt>
                                        <dd class="mb-2"><?= date('d/m/Y', strtotime($pratica_completa['data_apertura'])) ?></dd>
                                        
                                        <?php if ($pratica_completa['data_scadenza']): ?>
                                            <dt>Data Scadenza</dt>
                                            <dd class="mb-2">
                                                <?= date('d/m/Y', strtotime($pratica_completa['data_scadenza'])) ?>
                                                <?php
                                                $giorni = (strtotime($pratica_completa['data_scadenza']) - time()) / 86400;
                                                if ($giorni < 0): ?>
                                                    <span class="badge badge-danger">Scaduta</span>
                                                <?php elseif ($giorni <= 3): ?>
                                                    <span class="badge badge-warning">In scadenza</span>
                                                <?php endif; ?>
                                            </dd>
                                        <?php endif; ?>
                                        
                                        <dt>Operatore Responsabile</dt>
                                        <dd class="mb-2"><?= htmlspecialchars($pratica_completa['operatore_nome'] ?? 'Non assegnato') ?></dd>
                                        
                                        <dt>Stato</dt>
                                        <dd><?= getStatoTaskBadge($pratica_completa['stato']) ?></dd>
                                    </dl>
                                </div>
                            </div>

                            <!-- Azioni Rapide -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">⚡ Azioni Rapide</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary">
                                            ⏱️ Tracking Tempo
                                        </a>
                                        <a href="/crm/?action=pratiche&view=workflow&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary">
                                            🔄 Cambia Stato
                                        </a>
                                        <button onclick="window.print()" class="btn btn-secondary">
                                            🖨️ Stampa Pratica
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>