<?php
/**
 * 📊 MODULO DASHBOARD OTTIMIZZATA - CRM RE.DE CONSULTING
 * File: /modules/dashboard/index.php
 * 
 * ✅ VERSIONE CON LAYOUT COMPATTO E PIÙ CONTENUTI
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

/**
 * 📊 Ottieni attività recenti REALI dal database
 */
function getRecentActivities($limit = 10) {
    $activities = [];
    
    try {
        $db = Database::getInstance();
        
        // Query unificata per diverse attività
        $sql = "
            (SELECT 
                'cliente' as tipo,
                CONCAT('🆕 Nuovo cliente: ', ragione_sociale) as descrizione,
                created_at as data_attivita,
                NULL as operatore
             FROM clienti 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY created_at DESC
             LIMIT 5)
            
            UNION ALL
            
            (SELECT 
                'operatore' as tipo,
                CONCAT('👤 Nuovo operatore: ', nome, ' ', cognome) as descrizione,
                created_at as data_attivita,
                NULL as operatore
             FROM operatori
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY created_at DESC
             LIMIT 5)
            
            ORDER BY data_attivita DESC
            LIMIT ?
        ";
        
        $stmt = $db->db->prepare($sql);
        $stmt->execute([$limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Recent activities error: " . $e->getMessage());
        // Fallback su dati demo
        $activities = [
            ['tipo' => 'demo', 'descrizione' => '🆕 Nuovo cliente registrato: Esempio SRL', 'data_attivita' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['tipo' => 'demo', 'descrizione' => '📋 Pratica completata per Cliente ABC', 'data_attivita' => date('Y-m-d H:i:s', strtotime('-4 hours'))],
            ['tipo' => 'demo', 'descrizione' => '📧 Email inviata a 15 clienti', 'data_attivita' => date('Y-m-d H:i:s', strtotime('-1 day'))]
        ];
    }
    
    return $activities;
}

/**
 * 📈 Ottieni statistiche per mini grafici
 */
function getMiniStats() {
    return [
        'clienti_per_mese' => [
            'labels' => ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu'],
            'data' => [12, 19, 3, 5, 2, 3]
        ],
        'fatturato_per_mese' => [
            'labels' => ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu'],
            'data' => [25000, 30000, 28000, 35000, 32000, 40000]
        ]
    ];
}

// ================================================================
// ESECUZIONE PRINCIPALE DASHBOARD
// ================================================================

try {
    $stats_cards = getQuickStats();
    $criticalAlerts = getCriticalAlerts();
    $recentActivities = getRecentActivities();
    $miniStats = getMiniStats();
    $user = getCurrentUser();
    
    $page_title = 'Dashboard - CRM Re.De Consulting';
    $last_updated = date('H:i:s');
    
} catch (Exception $e) {
    error_log("Dashboard execution error: " . $e->getMessage());
    $stats_cards = [];
    $criticalAlerts = [];
    $recentActivities = [];
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

                    <!-- Stats Grid - 4 colonne -->
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

                    <!-- Layout ottimizzato con griglia 2 colonne -->
                    <div class="dashboard-grid">
                        <!-- Colonna principale -->
                        <div>
                            <!-- Attività Recenti -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 class="card-title">📊 Attività Recenti</h3>
                                    <a href="?action=activities" class="text-primary" style="font-size: 0.875rem;">
                                        Vedi tutte →
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($recentActivities)): ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <div><?= htmlspecialchars($activity['descrizione']) ?></div>
                                                    <div class="activity-time">
                                                        <?= formatTimeAgo($activity['data_attivita']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Nessuna attività recente</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Mini Widgets -->
                            <div class="mini-widgets">
                                <!-- Scadenze Imminenti -->
                                <div class="mini-widget">
                                    <h4 class="mini-widget-title">⏰ Scadenze Questa Settimana</h4>
                                    <div class="quick-stats">
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value">3</div>
                                            <div class="quick-stat-label">Oggi</div>
                                        </div>
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value">8</div>
                                            <div class="quick-stat-label">Settimana</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Performance -->
                                <div class="mini-widget">
                                    <h4 class="mini-widget-title">📈 Performance Mese</h4>
                                    <div class="quick-stats">
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value">+12%</div>
                                            <div class="quick-stat-label">Clienti</div>
                                        </div>
                                        <div class="quick-stat-item">
                                            <div class="quick-stat-value">+8%</div>
                                            <div class="quick-stat-label">Pratiche</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonna laterale -->
                        <div>
                            <!-- Azioni Rapide -->
                            <div class="card mb-3">
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
                                            📋 Nuova Pratica
                                        </a>
                                        <a href="?action=reports" class="btn btn-secondary">
                                            📊 Report e Statistiche
                                        </a>
                                        <a href="?action=settings" class="btn btn-secondary">
                                            ⚙️ Impostazioni
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Promemoria -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">📌 Promemoria</h3>
                                </div>
                                <div class="card-body">
                                    <ul style="list-style: none; padding: 0; margin: 0;">
                                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                            <div style="font-size: 0.875rem;">
                                                <strong>Chiamare Mario Rossi</strong>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    Oggi alle 15:00
                                                </div>
                                            </div>
                                        </li>
                                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                                            <div style="font-size: 0.875rem;">
                                                <strong>Riunione team</strong>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    Domani alle 10:00
                                                </div>
                                            </div>
                                        </li>
                                        <li style="padding: 0.5rem 0;">
                                            <div style="font-size: 0.875rem;">
                                                <strong>Scadenza F24</strong>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    Venerdì 16:00
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
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

<?php
// Helper function per PHP
function formatTimeAgo($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->d == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) return 'Adesso';
            return $diff->i . ' minuti fa';
        }
        return $diff->h . ' ore fa';
    }
    if ($diff->d < 7) return $diff->d . ' giorni fa';
    
    return $date->format('d/m/Y');
}
?>