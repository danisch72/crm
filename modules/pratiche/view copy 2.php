<?php
/**
 * modules/pratiche/view.php - Dashboard Pratica
 * Design System Standard CRM Re.De Consulting
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

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

// Carica info cliente
$cliente = $db->selectOne("
    SELECT c.*, CONCAT(o.nome, ' ', o.cognome) as operatore_nome
    FROM clienti c
    LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
    WHERE c.id = ?
", [$pratica['cliente_id']]);

// Helper functions
function getStatoTaskBadge($stato) {
    $config = [
        'da_iniziare' => ['color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => 'Da fare'],
        'in_corso' => ['color' => '#d97706', 'bg' => '#fef3c7', 'label' => 'In corso'],
        'completato' => ['color' => '#059669', 'bg' => '#d1fae5', 'label' => 'Completato'],
        'bloccato' => ['color' => '#dc2626', 'bg' => '#fee2e2', 'label' => 'Bloccato']
    ][$stato] ?? ['color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => 'Da fare'];
    
    return sprintf(
        '<span class="badge" style="background: %s; color: %s; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">%s</span>',
        $config['bg'],
        $config['color'],
        $config['label']
    );
}

$pageTitle = 'Pratica #' . str_pad($pratica['id'], 4, '0', STR_PAD_LEFT);
$pageIcon = '📋';

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pratica['titolo']) ?> - CRM Re.De</title>
    
    <!-- CSS Standard del progetto -->
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <!-- Content -->
        <main class="main-content">
            <div class="content-inner">
                <!-- Breadcrumb -->
                <nav class="breadcrumb">
                    <a href="/crm/?action=dashboard">Dashboard</a>
                    <span class="separator">›</span>
                    <a href="/crm/?action=pratiche">Pratiche</a>
                    <span class="separator">›</span>
                    <span class="current"><?= htmlspecialchars($pratica['titolo']) ?></span>
                </nav>

                <!-- Cliente Box -->
                <div class="card mb-4" style="background: #f0fdf4; border: 1px solid #86efac;">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="font-size: 2rem;">🏢</div>
                                <div>
                                    <h3 style="margin: 0; color: #1f2937; font-size: 1.125rem;">
                                        <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                    </h3>
                                    <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">
                                        <?php if ($cliente['partita_iva']): ?>
                                            P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?>
                                        <?php endif; ?>
                                        <?php if ($cliente['codice_fiscale']): ?>
                                            • CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="/crm/?action=clienti&view=view&id=<?= $pratica['cliente_id'] ?>" 
                                   class="btn btn-secondary btn-sm">
                                    Scheda Cliente
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header Pratica -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h1 style="font-size: 1.5rem; margin: 0 0 0.5rem 0; color: #1f2937;">
                                    <?= htmlspecialchars($pratica['titolo']) ?>
                                </h1>
                                <div style="display: flex; gap: 1rem; color: #6b7280; font-size: 0.875rem;">
                                    <span>Pratica #<?= str_pad($pratica['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                    <span>•</span>
                                    <span>Stato: <?= ucfirst($pratica['stato']) ?></span>
                                    <span>•</span>
                                    <span>Scadenza: <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?></span>
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 600; color: #007849;">
                                    <?= $stats['progress'] ?>%
                                </div>
                                <div style="font-size: 0.75rem; color: #6b7280;">
                                    <?= $stats['task_completati'] ?>/<?= $stats['task_totali'] ?> task
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid Layout -->
                <div class="row">
                    <!-- Colonna principale -->
                    <div class="col-lg-8">
                        <!-- Task List -->
                        <div class="card">
                            <div class="card-header">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <h3 class="card-title" style="margin: 0;">📋 Task e Attività</h3>
                                    <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-primary btn-sm">
                                        ➕ Gestisci Task
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($tasks)): ?>
                                    <div style="text-align: center; padding: 3rem 0; color: #9ca3af;">
                                        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                                        <p>Nessun task presente</p>
                                        <a href="/crm/?action=pratiche&view=task_manager&id=<?= $pratica['id'] ?>" 
                                           class="btn btn-primary btn-sm">
                                            Aggiungi primo task
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="task-list">
                                        <?php foreach ($tasks as $task): ?>
                                            <div class="task-item" style="padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 0.75rem; background: #f9fafb;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                                    <div style="flex: 1;">
                                                        <h4 style="margin: 0 0 0.25rem 0; font-size: 0.875rem; font-weight: 600; color: #1f2937;">
                                                            <?= htmlspecialchars($task['titolo']) ?>
                                                        </h4>
                                                        <?php if ($task['descrizione']): ?>
                                                            <p style="margin: 0; font-size: 0.8125rem; color: #6b7280;">
                                                                <?= htmlspecialchars($task['descrizione']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?= getStatoTaskBadge($task['stato']) ?>
                                                    </div>
                                                </div>
                                                
                                                <div style="display: flex; gap: 1rem; font-size: 0.75rem; color: #6b7280;">
                                                    <span>👤 <?= htmlspecialchars($task['operatore_nome'] ?? 'Non assegnato') ?></span>
                                                    <?php if ($task['data_scadenza']): ?>
                                                        <span>📅 <?= date('d/m/Y', strtotime($task['data_scadenza'])) ?></span>
                                                    <?php endif; ?>
                                                    <span>⏱️ <?= number_format($task['ore_stimate'], 1) ?>h stimate</span>
                                                    <?php if ($task['minuti_tracciati'] > 0): ?>
                                                        <span>✅ <?= round($task['minuti_tracciati'] / 60, 1) ?>h lavorate</span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($task['stato'] !== 'completato'): ?>
                                                    <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem;">
                                                        <?php if ($task['stato'] === 'da_iniziare'): ?>
                                                            <button class="btn btn-sm btn-primary" onclick="startTask(<?= $task['id'] ?>)">
                                                                ▶️ Inizia
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="/crm/?action=pratiche&view=tracking&id=<?= $pratica['id'] ?>&task=<?= $task['id'] ?>" 
                                                               class="btn btn-sm btn-secondary">
                                                                ⏱️ Tracking
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
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
                                <h3 class="card-title" style="margin: 0;">📊 Statistiche</h3>
                            </div>
                            <div class="card-body">
                                <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                                    <div style="text-align: center; padding: 1rem; background: #f3f4f6; border-radius: 8px;">
                                        <div style="font-size: 1.5rem; font-weight: 600; color: #1f2937;">
                                            <?= $stats['ore_lavorate'] ?>h
                                        </div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">Ore Lavorate</div>
                                    </div>
                                    <div style="text-align: center; padding: 1rem; background: #f3f4f6; border-radius: 8px;">
                                        <div style="font-size: 1.5rem; font-weight: 600; color: #1f2937;">
                                            <?= $stats['ore_stimate'] ?>h
                                        </div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">Ore Stimate</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Azioni -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title" style="margin: 0;">⚡ Azioni Rapide</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <a href="/crm/?action=pratiche&view=edit&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-secondary btn-sm">✏️ Modifica Pratica</a>
                                    <a href="/crm/?action=pratiche&view=workflow&id=<?= $pratica['id'] ?>" 
                                       class="btn btn-secondary btn-sm">🔄 Cambia Stato</a>
                                    <a href="mailto:<?= $cliente['email'] ?>" 
                                       class="btn btn-secondary btn-sm">📧 Email Cliente</a>
                                    <?php if ($pratica['stato'] === 'completata'): ?>
                                        <a href="#" class="btn btn-primary btn-sm">💰 Genera Fattura</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function startTask(taskId) {
        if (confirm('Iniziare questo task?')) {
            // Chiamata AJAX per iniziare il task
            fetch('/crm/modules/pratiche/api/task_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
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
                    alert(data.message || 'Errore nell\'avvio del task');
                }
            });
        }
    }
    </script>
</body>
</html>