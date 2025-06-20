<?php
/**
 * modules/pratiche/view.php - Dashboard Pratica
 * USA SOLO CLASSI ESISTENTI IN DATEV-OPTIMAL.CSS
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
    if(isset($stats[$task['stato']])) {
        $stats[$task['stato']]++;
    }
    $stats['ore_stimate'] += $task['ore_stimate'];
    $stats['ore_lavorate'] += $task['ore_lavorate'];
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
$pageIcon = PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['icon'] ?? '📋';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pratica_completa['titolo']) ?> - CRM Re.De</title>
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
</head>
<body>
    <div class="app-layout">
        <?php 
        // Include sidebar (barra laterale sinistra)
        include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/sidebar.php'; 
        ?>
        
        <div class="content-wrapper">
            <?php 
            // Include header (barra orizzontale in alto)
            include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; 
            ?>
            
            <main class="main-content">
                <div class="container">
                    <!-- Header Cliente -->
                    <div class="card mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-3 align-items-center">
                                <div style="width: 40px; height: 40px; background: var(--primary-green); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    👤
                                </div>
                                <div>
                                    <h2 style="font-size: 1.125rem; font-weight: 600; margin: 0;">
                                        <?= htmlspecialchars($pratica_completa['cliente_nome']) ?>
                                    </h2>
                                    <div class="text-muted" style="font-size: 0.8125rem;">
                                        <span>📞 <?= htmlspecialchars($pratica_completa['cliente_telefono'] ?? 'N/D') ?></span>
                                        <span style="margin-left: 1rem;">📧 <?= htmlspecialchars($pratica_completa['cliente_email'] ?? 'N/D') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="/crm/?action=clienti&view=view&id=<?= $pratica_completa['cliente_id'] ?>" 
                                   class="btn btn-secondary">
                                    Scheda Cliente
                                </a>
                                <a href="mailto:<?= $pratica_completa['cliente_email'] ?>" 
                                   class="btn btn-primary">
                                    📧 Email
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Header Pratica -->
                    <div class="card mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h1 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 0.25rem 0;">
                                    <span style="color: <?= PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['color'] ?>">
                                        <?= PRATICHE_TYPES[$pratica_completa['tipo_pratica']]['icon'] ?>
                                    </span>
                                    <?= htmlspecialchars($pratica_completa['titolo']) ?>
                                </h1>
                                <div class="text-muted" style="font-size: 0.8125rem;">
                                    <span>Pratica #<?= str_pad($pratica_completa['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                    <span style="margin: 0 0.5rem;">•</span>
                                    <span><?= PRATICHE_STATI[$pratica_completa['stato']]['label'] ?></span>
                                    <span style="margin: 0 0.5rem;">•</span>
                                    <span>Operatore: <?= htmlspecialchars($pratica_completa['operatore_nome']) ?></span>
                                    <?php if ($pratica_completa['data_scadenza']): ?>
                                        <?php $scadenza = getScadenzaInfo($pratica_completa['data_scadenza']); ?>
                                        <span style="margin: 0 0.5rem;">•</span>
                                        <span class="<?= $scadenza['class'] ?>">
                                            Scadenza: <?= $scadenza['text'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2" style="background: var(--gray-50); padding: 0.5rem 0.75rem; border-radius: 6px;">
                                <div style="width: 120px; height: 6px; background: var(--gray-200); border-radius: 3px; overflow: hidden;">
                                    <div style="height: 100%; background: var(--primary-green); width: <?= $progressPercentuale ?>%; transition: width 0.3s ease;"></div>
                                </div>
                                <span style="font-size: 0.8125rem; font-weight: 500;"><?= $progressPercentuale ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Layout 3 colonne -->
                    <div class="d-grid gap-3" style="grid-template-columns: 1fr 2fr 1fr;">
                        <!-- Colonna Sinistra: Statistiche e Azioni -->
                        <div>
                            <!-- Statistiche -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">📊 Statistiche</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2" style="grid-template-columns: repeat(2, 1fr);">
                                        <div class="text-center" style="padding: 0.75rem; background: var(--gray-50); border-radius: 6px;">
                                            <span class="d-block" style="font-size: 1.25rem; font-weight: 600; color: var(--primary-green);">
                                                <?= $stats['totali'] ?>
                                            </span>
                                            <span class="d-block text-muted" style="font-size: 0.75rem;">Task Totali</span>
                                        </div>
                                        <div class="text-center" style="padding: 0.75rem; background: var(--gray-50); border-radius: 6px;">
                                            <span class="d-block" style="font-size: 1.25rem; font-weight: 600; color: var(--primary-green);">
                                                <?= $stats['completati'] ?>
                                            </span>
                                            <span class="d-block text-muted" style="font-size: 0.75rem;">Completati</span>
                                        </div>
                                        <div class="text-center" style="padding: 0.75rem; background: var(--gray-50); border-radius: 6px;">
                                            <span class="d-block" style="font-size: 1.25rem; font-weight: 600; color: var(--primary-green);">
                                                <?= number_format($stats['ore_stimate'], 1, ',', '.') ?>h
                                            </span>
                                            <span class="d-block text-muted" style="font-size: 0.75rem;">Ore Stimate</span>
                                        </div>
                                        <div class="text-center" style="padding: 0.75rem; background: var(--gray-50); border-radius: 6px;">
                                            <span class="d-block" style="font-size: 1.25rem; font-weight: 600; color: var(--primary-green);">
                                                <?= number_format($stats['ore_lavorate'], 1, ',', '.') ?>h
                                            </span>
                                            <span class="d-block text-muted" style="font-size: 0.75rem;">Ore Lavorate</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Azioni Rapide -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">⚡ Azioni Rapide</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-primary text-center">
                                            📋 Gestisci Task
                                        </a>
                                        <a href="/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary text-center">
                                            ⏱️ Time Tracking
                                        </a>
                                        <a href="/crm/?action=pratiche&view=edit&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary text-center">
                                            ✏️ Modifica Pratica
                                        </a>
                                        <a href="/crm/?action=pratiche&view=workflow&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-secondary text-center">
                                            🔄 Cambia Stato
                                        </a>
                                        <a href="#" class="btn btn-secondary text-center">
                                            📧 Email Cliente
                                        </a>
                                        <?php if ($pratica_completa['stato'] === 'completata'): ?>
                                        <a href="#" class="btn btn-primary text-center">
                                            💰 Genera Fattura
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Note (se presenti) -->
                            <?php if (!empty($pratica_completa['descrizione'])): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">📝 Note</h3>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-0" style="font-size: 0.8125rem;">
                                        <?= nl2br(htmlspecialchars($pratica_completa['descrizione'])) ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Colonna Centrale: Task -->
                        <div>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">📋 Task (<?= count($tasks) ?>)</h3>
                                    <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-primary">
                                        Gestisci
                                    </a>
                                </div>
                                <div class="card-body" style="padding: 0;">
                                    <div style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($tasks as $task): ?>
                                        <div style="padding: 0.625rem 0.75rem; border-bottom: 1px solid var(--gray-100); display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; flex-shrink: 0; background: <?= $task['stato'] == 'completato' ? '#d1fae5' : ($task['stato'] == 'in_corso' ? '#dbeafe' : '#fef3c7') ?>;">
                                                <?= TASK_STATI[$task['stato']]['icon'] ?>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-size: 0.8125rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?= htmlspecialchars($task['titolo']) ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?php if ($task['ore_stimate'] > 0): ?>
                                                        ⏱️ <?= number_format($task['ore_stimate'], 1, ',', '.') ?>h
                                                    <?php endif; ?>
                                                    <?php if ($task['assegnato_a']): ?>
                                                        • 👤 <?= htmlspecialchars($task['assegnato_a']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <?php if ($task['stato'] !== 'completato'): ?>
                                                <button class="btn btn-secondary" 
                                                        onclick="startTask(<?= $task['id'] ?>)"
                                                        title="Inizia Task"
                                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                    ▶️
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($tasks)): ?>
                                        <div class="text-center" style="padding: 2rem;">
                                            <p class="text-muted">Nessun task presente</p>
                                            <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                               class="btn btn-primary">
                                                ➕ Aggiungi Task
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonna Destra: Timeline e Documenti -->
                        <div>
                            <!-- Timeline Attività -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">🕒 Attività Recenti</h3>
                                </div>
                                <div class="card-body">
                                    <div style="position: relative; padding-left: 1.5rem;">
                                        <?php foreach (array_slice($activities, 0, 5) as $index => $activity): ?>
                                        <div style="position: relative; padding-bottom: 0.75rem;">
                                            <div style="position: absolute; left: -18px; top: 0.25rem; width: 12px; height: 12px; background: white; border: 2px solid var(--primary-green); border-radius: 50%;"></div>
                                            <?php if ($index < count($activities) - 1): ?>
                                            <div style="position: absolute; left: -12px; top: 1rem; bottom: 0; width: 2px; background: var(--gray-200);"></div>
                                            <?php endif; ?>
                                            <div style="font-size: 0.75rem; color: var(--gray-600);">
                                                <?= htmlspecialchars($activity['titolo']) ?>
                                                <div style="font-size: 0.6875rem; color: var(--gray-400);">
                                                    <?= date('d/m H:i', strtotime($activity['data'])) ?>
                                                    <?php if ($activity['operatore']): ?>
                                                        • <?= htmlspecialchars($activity['operatore']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($activities)): ?>
                                        <p class="text-center text-muted">
                                            Nessuna attività recente
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Documenti -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">📎 Documenti</h3>
                                    <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        ➕ Upload
                                    </button>
                                </div>
                                <div class="card-body">
                                    <p class="text-center text-muted">
                                        Nessun documento caricato
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Info Pratica -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0;">ℹ️ Informazioni</h3>
                                </div>
                                <div class="card-body">
                                    <dl style="font-size: 0.75rem; margin: 0;">
                                        <dt style="font-weight: 500; color: var(--gray-600);">Creata il:</dt>
                                        <dd style="margin: 0 0 0.5rem 0; color: var(--gray-900);">
                                            <?= date('d/m/Y H:i', strtotime($pratica_completa['created_at'])) ?>
                                        </dd>
                                        
                                        <?php if ($pratica_completa['template_id']): ?>
                                        <dt style="font-weight: 500; color: var(--gray-600);">Template:</dt>
                                        <dd style="margin: 0 0 0.5rem 0; color: var(--gray-900);">
                                            Sì (ID: <?= $pratica_completa['template_id'] ?>)
                                        </dd>
                                        <?php endif; ?>
                                        
                                        <dt style="font-weight: 500; color: var(--gray-600);">Priorità:</dt>
                                        <dd style="margin: 0;">
                                            <span class="status-badge status-<?= $pratica_completa['priorita'] === 'alta' ? 'warning' : 'active' ?>">
                                                <?= PRATICHE_PRIORITA[$pratica_completa['priorita']]['label'] ?>
                                            </span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function startTask(taskId) {
            window.location.href = `/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>&task=${taskId}`;
        }
        
        setTimeout(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>