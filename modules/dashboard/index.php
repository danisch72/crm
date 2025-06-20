<?php
/**
 * 📊 MODULO DASHBOARD - CRM RE.DE CONSULTING
 * File: /modules/dashboard/index.php
 * 
 * Dashboard principale come modulo
 * ✅ VERSIONE CON COMPONENTI CENTRALIZZATI
 */

// ================================================================
// CARICA BOOTSTRAP (gestisce autenticazione e inizializzazione)
// ================================================================
require_once dirname(dirname(__DIR__)) . '/core/bootstrap.php';

// Bootstrap ha già verificato l'autenticazione
// e caricato tutti i componenti necessari

// ================================================================
// CARICA DATABASE SE NECESSARIO
// ================================================================
loadDatabase(); // Funzione da bootstrap.php

// Prepara variabili per i componenti
$pageTitle = 'Dashboard';
$pageIcon = '🏠';

// Prepara sessionInfo per i componenti
$currentUser = getCurrentUser();
$sessionInfo = [
    'operatore_id' => $currentUser['id'],
    'nome' => $currentUser['nome'],
    'cognome' => $currentUser['cognome'],
    'email' => $currentUser['email'],
    'nome_completo' => $currentUser['nome'] . ' ' . $currentUser['cognome'],
    'is_admin' => $currentUser['is_admin']
];

/**
 * 🔧 Inizializza dati dashboard
 */
function initializeDashboardData() {
    // Prova a ottenere istanza database se disponibile
    $database = null;
    if (class_exists('Database')) {
        $database = Database::getInstance();
    }
    
    $data = [
        'operators' => ['total_operators' => 0, 'active_operators' => 0, 'online_operators' => 0],
        'sessions' => ['operators_with_sessions' => 0, 'total_active_sessions' => 0],
        'clients' => ['total_clients' => 0, 'active_clients' => 0, 'new_clients_week' => 0],
        'practices' => ['pending_practices' => 0, 'urgent_practices' => 0],
        'tasks' => ['pending_tasks' => 0, 'tasks_today' => 0, 'overdue_tasks' => 0]
    ];
    
    try {
        if ($database && $database->db) {
            // Statistiche operatori
            $stmt = $database->db->prepare("
                SELECT 
                    COUNT(*) as total_operators,
                    SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as active_operators
                FROM operatori
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $data['operators']['total_operators'] = $result['total_operators'];
                $data['operators']['active_operators'] = $result['active_operators'];
            }
            
            // Statistiche clienti
            $stmt = $database->db->prepare("
                SELECT 
                    COUNT(*) as total_clients,
                    SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as active_clients,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_clients_week
                FROM clienti
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $data['clients'] = $result;
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard data error: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * 📊 Ottieni statistiche rapide per cards
 */
function getQuickStats() {
    $dashboardData = initializeDashboardData();
    
    return [
        [
            'title' => 'Operatori Attivi',
            'value' => $dashboardData['operators']['active_operators'],
            'total' => $dashboardData['operators']['total_operators'],
            'icon' => '👥',
            'color' => 'primary',
            'link' => '?action=operatori'
        ],
        [
            'title' => 'Clienti Attivi',
            'value' => $dashboardData['clients']['active_clients'],
            'total' => $dashboardData['clients']['total_clients'],
            'icon' => '🏢',
            'color' => 'success',
            'link' => '?action=clienti'
        ],
        [
            'title' => 'Nuovi Clienti',
            'value' => $dashboardData['clients']['new_clients_week'],
            'subtitle' => 'Ultimi 7 giorni',
            'icon' => '📈',
            'color' => 'info',
            'link' => '?action=clienti&filter=new'
        ],
        [
            'title' => 'Attività Oggi',
            'value' => 0,
            'subtitle' => 'Da completare',
            'icon' => '📋',
            'color' => 'warning',
            'link' => '?action=tasks'
        ]
    ];
}

/**
 * 🚨 Ottieni alert critici
 */
function getCriticalAlerts() {
    $alerts = [];
    
    // Qui puoi aggiungere logica per alert
    // es: scadenze imminenti, pratiche urgenti, ecc.
    
    return $alerts;
}

// ================================================================
// ESECUZIONE PRINCIPALE DASHBOARD
// ================================================================

try {
    $stats_cards = getQuickStats();
    $criticalAlerts = getCriticalAlerts();
    $user = getCurrentUser(); // Funzione da bootstrap.php
    
    $page_title = 'Dashboard - CRM Re.De Consulting';
    $last_updated = date('H:i:s');
    
} catch (Exception $e) {
    error_log("Dashboard execution error: " . $e->getMessage());
    $stats_cards = [];
    $criticalAlerts = [];
    $user = ['nome' => 'Utente'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- CSS Unificato -->
    <link rel="stylesheet" href="/crm/assets/css/datev-koinos-unified.css">
</head>
<body>
    <div class="app-layout">
        <!-- ✅ COMPONENTE SIDEBAR (OBBLIGATORIO) -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <!-- ✅ COMPONENTE HEADER (OBBLIGATORIO) -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="container">
                    <!-- Welcome Section -->
                    <div class="mb-3">
                        <h1 class="text-2xl font-bold">Benvenuto, <?= htmlspecialchars($user['nome'] ?? 'Utente') ?>!</h1>
                        <p class="text-muted">Ultimo aggiornamento: <?= $last_updated ?></p>
                    </div>

                    <!-- Stats Grid -->
                    <div class="row mb-3">
                        <?php foreach ($stats_cards as $stat): ?>
                            <div class="col-md-3 mb-2">
                                <a href="<?= htmlspecialchars($stat['link']) ?>" class="text-decoration-none">
                                    <div class="stat-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="stat-value">
                                                    <?= htmlspecialchars($stat['value']) ?>
                                                    <?php if (isset($stat['total'])): ?>
                                                        <span class="text-muted" style="font-size: 0.875rem;">
                                                            / <?= htmlspecialchars($stat['total']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="stat-label"><?= htmlspecialchars($stat['title']) ?></div>
                                                <?php if (isset($stat['subtitle'])): ?>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <?= htmlspecialchars($stat['subtitle']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 2rem;"><?= $stat['icon'] ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Alerts -->
                    <?php if (!empty($criticalAlerts)): ?>
                        <div class="mb-3">
                            <?php foreach ($criticalAlerts as $alert): ?>
                                <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>">
                                    <strong><?= htmlspecialchars($alert['title']) ?>:</strong>
                                    <?= htmlspecialchars($alert['message']) ?>
                                    <?php if (isset($alert['action'])): ?>
                                        <a href="<?= htmlspecialchars($alert['action']) ?>" style="margin-left: auto;">
                                            Visualizza →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Main Content Grid -->
                    <div class="row">
                        <!-- Recent Activities -->
                        <div class="col-md-8 mb-3">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">📊 Attività Recenti</h3>
                                </div>
                                <div class="card-body">
                                    <div class="activity-list">
                                        <div class="p-3 mb-2 bg-light rounded">
                                            <div class="d-flex justify-content-between">
                                                <div>🆕 Nuovo cliente registrato: Esempio SRL</div>
                                                <div class="text-muted">2 ore fa</div>
                                            </div>
                                        </div>
                                        <div class="p-3 mb-2 bg-light rounded">
                                            <div class="d-flex justify-content-between">
                                                <div>📋 Pratica completata per Cliente ABC</div>
                                                <div class="text-muted">4 ore fa</div>
                                            </div>
                                        </div>
                                        <div class="p-3 mb-2 bg-light rounded">
                                            <div class="d-flex justify-content-between">
                                                <div>📧 Email inviata a 15 clienti</div>
                                                <div class="text-muted">Ieri alle 18:30</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">⚡ Azioni Rapide</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="?action=clienti&view=create" class="btn btn-primary">
                                            ➕ Nuovo Cliente
                                        </a>
                                        <a href="?action=operatori&view=create" class="btn btn-secondary">
                                            👥 Nuovo Operatore
                                        </a>
                                        <a href="?action=pratiche&view=create" class="btn btn-secondary">
                                            📁 Nuova Pratica
                                        </a>
                                        <hr>
                                        <a href="?action=reports" class="btn btn-sm btn-secondary">
                                            📊 Report e Statistiche
                                        </a>
                                        <a href="?action=settings" class="btn btn-sm btn-secondary">
                                            ⚙️ Impostazioni
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript per microinterazioni -->
    <script>
        // Auto-refresh dashboard ogni 5 minuti
        setTimeout(() => {
            location.reload();
        }, 300000);

        // Animazione contatori
        document.querySelectorAll('.stat-value').forEach(element => {
            const text = element.textContent;
            const match = text.match(/^(\d+)/);
            if (match) {
                const value = parseInt(match[1]);
                let current = 0;
                const increment = value / 20;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= value) {
                        current = value;
                        clearInterval(timer);
                    }
                    const parts = text.split('/');
                    if (parts.length > 1) {
                        element.innerHTML = Math.floor(current) + ' <span class="text-muted" style="font-size: 0.875rem;">/ ' + parts[1].trim() + '</span>';
                    } else {
                        element.textContent = Math.floor(current) + text.substring(match[0].length);
                    }
                }, 50);
            }
        });
    </script>
</body>
</html>