<?php
/**
 * 📊 DASHBOARD CRM - INTEGRATO CON MODULO OPERATORI
 * File: /dashboard.php
 * 
 * Dashboard principale con integrazione del modulo 3 (operatori)
 * CRM Rede Consulting - Versione integrata
 */

// Verifica inizializzazione sistema
if (!defined('CRM_INIT')) {
    die('❌ Accesso non autorizzato - Sistema non inizializzato');
}

// Verifica autenticazione
if (!function_exists('isAuthenticated') || !isAuthenticated()) {
    header('Location: ?action=login');
    exit;
}

require_once __DIR__ . '/AuthIntegration.php';
/**
 * 🔧 Inizializza dati dashboard con integrazione modulo operatori
 */
function initializeDashboardData() {
    global $database;
    
    $data = [];
    
    try {
        if (isset($database)) {
            // === STATISTICHE OPERATORI (MODULO 3) ===
            $stmt = $database->prepare("
                SELECT 
                    COUNT(*) as total_operators,
                    SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as active_operators,
                    SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_operators,
                    SUM(CASE WHEN created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_operators_month,
                    ROUND(AVG(performance_score), 1) as avg_performance
                FROM crm_operators 
                WHERE is_active = 1
            ");
            $stmt->execute();
            $data['operators'] = $stmt->fetch() ?: [
                'total_operators' => 0, 'active_operators' => 0, 
                'online_operators' => 0, 'new_operators_month' => 0, 'avg_performance' => 0
            ];
            
            // === SESSIONI OPERATORI ATTIVE ===
            $stmt = $database->prepare("
                SELECT 
                    COUNT(DISTINCT operator_id) as operators_with_sessions,
                    COUNT(*) as total_active_sessions,
                    ROUND(AVG(TIMESTAMPDIFF(MINUTE, start_time, NOW())), 0) as avg_session_duration
                FROM crm_operator_sessions 
                WHERE end_time IS NULL 
                AND start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $data['sessions'] = $stmt->fetch() ?: [
                'operators_with_sessions' => 0, 'total_active_sessions' => 0, 'avg_session_duration' => 0
            ];
            
            // === STATISTICHE CLIENTI ===
            $stmt = $database->prepare("
                SELECT 
                    COUNT(*) as total_clients,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_clients,
                    SUM(CASE WHEN created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_clients_week
                FROM crm_clients 
                WHERE is_deleted = 0
            ");
            $stmt->execute();
            $data['clients'] = $stmt->fetch() ?: ['total_clients' => 0, 'active_clients' => 0, 'new_clients_week' => 0];
            
            // === SCADENZE ===
            $stmt = $database->prepare("
                SELECT 
                    COUNT(*) as total_deadlines,
                    SUM(CASE WHEN due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as urgent_deadlines,
                    SUM(CASE WHEN due_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as critical_deadlines
                FROM crm_deadlines 
                WHERE status != 'completed'
            ");
            $stmt->execute();
            $data['deadlines'] = $stmt->fetch() ?: ['total_deadlines' => 0, 'urgent_deadlines' => 0, 'critical_deadlines' => 0];
        }
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Dashboard data error: " . $e->getMessage());
        return [
            'operators' => ['total_operators' => 0, 'active_operators' => 0, 'online_operators' => 0, 'new_operators_month' => 0, 'avg_performance' => 0],
            'sessions' => ['operators_with_sessions' => 0, 'total_active_sessions' => 0, 'avg_session_duration' => 0],
            'clients' => ['total_clients' => 0, 'active_clients' => 0, 'new_clients_week' => 0],
            'deadlines' => ['total_deadlines' => 0, 'urgent_deadlines' => 0, 'critical_deadlines' => 0]
        ];
    }
}

/**
 * 📊 Ottieni statistiche rapide per widgets
 */
function getQuickStats() {
    $data = initializeDashboardData();
    
    return [
        [
            'title' => 'Operatori Totali',
            'value' => $data['operators']['total_operators'],
            'subtitle' => $data['operators']['active_operators'] . ' attivi ora',
            'color' => 'primary',
            'icon' => '👥',
            'link' => '?action=operators'
        ],
        [
            'title' => 'Operatori Online',
            'value' => $data['operators']['online_operators'],
            'subtitle' => $data['sessions']['total_active_sessions'] . ' sessioni attive',
            'color' => 'success',
            'icon' => '🟢',
            'link' => '?action=operators&view=sessions'
        ],
        [
            'title' => 'Performance Media',
            'value' => $data['operators']['avg_performance'] . '%',
            'subtitle' => 'Score operatori',
            'color' => $data['operators']['avg_performance'] >= 80 ? 'success' : ($data['operators']['avg_performance'] >= 60 ? 'warning' : 'danger'),
            'icon' => '📊',
            'link' => '?action=operators&view=stats'
        ],
        [
            'title' => 'Clienti Attivi',
            'value' => $data['clients']['active_clients'],
            'subtitle' => $data['clients']['new_clients_week'] . ' nuovi questa settimana',
            'color' => 'info',
            'icon' => '🏢',
            'link' => '?action=clients'
        ],
        [
            'title' => 'Scadenze Urgenti',
            'value' => $data['deadlines']['urgent_deadlines'],
            'subtitle' => $data['deadlines']['critical_deadlines'] . ' entro 24h',
            'color' => $data['deadlines']['critical_deadlines'] > 0 ? 'danger' : 'warning',
            'icon' => '⚠️',
            'link' => '?action=deadlines'
        ],
        [
            'title' => 'Nuovi Operatori',
            'value' => $data['operators']['new_operators_month'],
            'subtitle' => 'questo mese',
            'color' => 'secondary',
            'icon' => '🆕',
            'link' => '?action=operators&view=recent'
        ]
    ];
}

/**
 * ⚠️ Ottieni alert critici
 */
function getCriticalAlerts() {
    $alerts = [];
    $data = initializeDashboardData();
    
    // Alert scadenze critiche
    if ($data['deadlines']['critical_deadlines'] > 0) {
        $alerts[] = [
            'type' => 'danger',
            'title' => 'Scadenze Critiche',
            'message' => $data['deadlines']['critical_deadlines'] . ' scadenze entro 24 ore',
            'action' => '?action=deadlines&filter=critical'
        ];
    }
    
    // Alert performance operatori
    if ($data['operators']['avg_performance'] < 60) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Performance Bassa',
            'message' => 'Performance media operatori: ' . $data['operators']['avg_performance'] . '%',
            'action' => '?action=operators&view=training'
        ];
    }
    
    // Alert operatori inattivi
    $inactive_ratio = $data['operators']['total_operators'] > 0 
        ? round((($data['operators']['total_operators'] - $data['operators']['active_operators']) / $data['operators']['total_operators']) * 100)
        : 0;
    
    if ($inactive_ratio > 50) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Operatori Inattivi',
            'message' => $inactive_ratio . '% operatori non attivi nell\'ultima ora',
            'action' => '?action=operators&view=inactive'
        ];
    }
    
    return $alerts;
}

/**
 * 👤 Ottieni dati utente corrente
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    // Nuovo sistema integrato (priorità)
    if (class_exists('AuthIntegration')) {
        $auth = AuthIntegration::getInstance();
        $userData = $auth->getUserData();
        if ($userData) {
            return $userData;
        }
    }
    
    // Fallback sistema esistente
    return [
        'id' => $_SESSION['user_id'] ?? $_SESSION['operatore_id'] ?? 0,
        'username' => $_SESSION['user_data']['username'] ?? 'Unknown',
        'first_name' => $_SESSION['user_data']['first_name'] ?? ($_SESSION['operatore_nome'] ?? 'Utente'),
        'last_name' => $_SESSION['user_data']['last_name'] ?? ($_SESSION['operatore_cognome'] ?? ''),
        'email' => $_SESSION['user_data']['email'] ?? '',
        'role' => $_SESSION['user_data']['role'] ?? 'viewer',
        'permissions' => $_SESSION['user_data']['permissions'] ?? []
    ];
}

// ================================================================
// ESECUZIONE PRINCIPALE DASHBOARD
// ================================================================

try {
    // Inizializza dati
    $dashboardData = initializeDashboardData();
    $stats_cards = getQuickStats();
    $criticalAlerts = getCriticalAlerts();
    $user = getCurrentUser();
    
    // Preparazione dati per template
    $page_title = 'Dashboard - CRM Rede Consulting';
    $last_updated = date('H:i:s');
    
} catch (Exception $e) {
    error_log("Dashboard execution error: " . $e->getMessage());
    $stats_cards = [];
    $criticalAlerts = [];
    $user = ['first_name' => 'Utente'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- CSS Esistenti -->
    <link rel="stylesheet" href="/assets/css/datev-style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    
    <style>
    /* CSS Dashboard Integrato - Preserva layout esistente */
    :root {
        --primary-bg: #e0dfe4;
        --text-primary: #262827;
        --accent-primary: #304444;
        --accent-secondary: #386868;
        --accent-light: #8ebab5;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
        --secondary: #6c757d;
        --white: #ffffff;
        --light-gray: #f8f9fa;
        --medium-gray: #e9ecef;
        --dark-gray: #6c757d;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
        background: var(--primary-bg);
        min-height: 100vh;
    }

    .dashboard-header {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(48, 68, 68, 0.1);
        padding: 24px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .welcome-section h1 {
        color: var(--accent-primary);
        margin: 0 0 8px 0;
        font-size: 1.5rem;
    }

    .welcome-section p {
        color: var(--dark-gray);
        margin: 0;
    }

    .dashboard-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .last-update {
        color: var(--dark-gray);
        font-size: 0.9rem;
    }

    .refresh-btn {
        background: var(--accent-primary);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .refresh-btn:hover {
        background: var(--accent-secondary);
        transform: translateY(-1px);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--white);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(48, 68, 68, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 16px rgba(48, 68, 68, 0.15);
        text-decoration: none;
        color: inherit;
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .stat-title {
        font-size: 0.9rem;
        color: var(--dark-gray);
        font-weight: 500;
    }

    .stat-icon {
        font-size: 1.2rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 8px;
    }

    .stat-subtitle {
        font-size: 0.85rem;
        color: var(--dark-gray);
    }

    .stat-card.primary .stat-value { color: var(--accent-primary); }
    .stat-card.success .stat-value { color: var(--success); }
    .stat-card.warning .stat-value { color: var(--warning); }
    .stat-card.danger .stat-value { color: var(--danger); }
    .stat-card.info .stat-value { color: var(--info); }
    .stat-card.secondary .stat-value { color: var(--secondary); }

    .alerts-container {
        margin-bottom: 24px;
    }

    .alert {
        background: var(--white);
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        border-left: 4px solid;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .alert.danger { border-left-color: var(--danger); }
    .alert.warning { border-left-color: var(--warning); }
    .alert.info { border-left-color: var(--info); }

    .alert-title {
        font-weight: bold;
        margin-bottom: 4px;
    }

    .alert-message {
        color: var(--dark-gray);
        font-size: 0.9rem;
    }

    .quick-actions {
        background: var(--white);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(48, 68, 68, 0.1);
        margin-bottom: 24px;
    }

    .quick-actions h3 {
        color: var(--accent-primary);
        margin: 0 0 16px 0;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: var(--light-gray);
        border: none;
        border-radius: 8px;
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .action-btn:hover {
        background: var(--accent-light);
        color: white;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .fade-in-up {
        animation: fadeInUp 0.6s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .dashboard-actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        
        <!-- Header Dashboard -->
        <div class="dashboard-header fade-in-up">
            <div class="welcome-section">
                <h1>Benvenuto, <?= htmlspecialchars($user['first_name']) ?>! 👋</h1>
                <p>Panoramica CRM - <?= date('l d F Y') ?></p>
            </div>
            <div class="dashboard-actions">
                <div class="last-update">
                    Aggiornato: <span id="lastUpdateTime"><?= $last_updated ?></span>
                </div>
                <button class="refresh-btn" onclick="refreshDashboard()" id="refreshBtn">
                    🔄 Aggiorna
                </button>
            </div>
        </div>
        
        <!-- Alert Critici -->
        <?php if (!empty($criticalAlerts)): ?>
        <div class="alerts-container fade-in-up">
            <?php foreach ($criticalAlerts as $alert): ?>
                <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>">
                    <div class="alert-title"><?= htmlspecialchars($alert['title']) ?></div>
                    <div class="alert-message">
                        <?= htmlspecialchars($alert['message']) ?>
                        <?php if (isset($alert['action'])): ?>
                            <a href="<?= htmlspecialchars($alert['action']) ?>" style="margin-left: 8px;">→ Vai</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistiche Principali -->
        <div class="stats-grid fade-in-up" id="statsContainer">
            <?php if (!empty($stats_cards)): ?>
                <?php foreach ($stats_cards as $card): ?>
                    <a href="<?= htmlspecialchars($card['link'] ?? '#') ?>" class="stat-card <?= htmlspecialchars($card['color']) ?>">
                        <div class="stat-header">
                            <span class="stat-title"><?= htmlspecialchars($card['title']) ?></span>
                            <div class="stat-icon"><?= $card['icon'] ?></div>
                        </div>
                        <div class="stat-value"><?= htmlspecialchars($card['value']) ?></div>
                        <div class="stat-subtitle"><?= htmlspecialchars($card['subtitle']) ?></div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-value">⚠️</div>
                    <div class="stat-subtitle">Nessun dato disponibile</div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Azioni Rapide -->
        <div class="quick-actions fade-in-up">
            <h3>🚀 Azioni Rapide</h3>
            <div class="actions-grid">
                <a href="?action=operators&view=create" class="action-btn">
                    👤 Nuovo Operatore
                </a>
                <a href="?action=operators&view=sessions" class="action-btn">
                    📊 Sessioni Attive
                </a>
                <a href="?action=operators&view=stats" class="action-btn">
                    📈 Performance
                </a>
                <a href="?action=clients" class="action-btn">
                    🏢 Gestione Clienti
                </a>
                <a href="?action=deadlines" class="action-btn">
                    ⏰ Scadenze
                </a>
                <a href="?action=reports" class="action-btn">
                    📋 Report
                </a>
            </div>
        </div>
        
    </div>
    
    <!-- JavaScript Dashboard -->
    <script src="/assets/js/microinteractions.js"></script>
    <script>
    // Dashboard Manager Integrato
    class DashboardManager {
        constructor() {
            this.autoRefreshEnabled = true;
            this.refreshInterval = 300000; // 5 minuti
            this.intervalId = null;
            this.init();
        }
        
        init() {
            this.setupAutoRefresh();
            this.setupAnimations();
            console.log('Dashboard Manager initialized');
        }
        
        setupAutoRefresh() {
            if (this.autoRefreshEnabled) {
                this.intervalId = setInterval(() => {
                    this.refreshStats();
                }, this.refreshInterval);
            }
        }
        
        async refreshStats() {
            try {
                const refreshBtn = document.getElementById('refreshBtn');
                refreshBtn.textContent = '🔄 Aggiornando...';
                refreshBtn.disabled = true;
                
                // Simula refresh (in futuro sarà una chiamata AJAX)
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Aggiorna timestamp
                document.getElementById('lastUpdateTime').textContent = new Date().toLocaleTimeString('it-IT');
                
                refreshBtn.textContent = '🔄 Aggiorna';
                refreshBtn.disabled = false;
                
                // Refresh completo pagina per ora
                window.location.reload();
                
            } catch (error) {
                console.error('Errore refresh dashboard:', error);
            }
        }
        
        setupAnimations() {
            // Anima le card al passaggio del mouse
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-3px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0) scale(1)';
                });
            });
        }
    }
    
    // Funzione globale per refresh manuale
    function refreshDashboard() {
        if (window.dashboardManager) {
            window.dashboardManager.refreshStats();
        }
    }
    
    // Inizializza al caricamento pagina
    document.addEventListener('DOMContentLoaded', function() {
        window.dashboardManager = new DashboardManager();
    });
    </script>
</body>
</html>