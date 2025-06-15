<?php
/**
 * modules/operatori/stats.php - Statistiche Team CRM Re.De Consulting
 * 
 * ✅ LAYOUT ULTRA-DENSO UNIFORME v2.0 - OPERATORI-FRIENDLY
 * 
 * Features:
 * - Layout identico alla dashboard per uniformità totale
 * - Statistiche complete del team con visualizzazioni dense
 * - Dashboard performance orientata agli operatori
 * - MANTENIMENTO TOTALE della logica esistente
 * - Design system Datev Koinos compliant
 */

// **LOGICA ESISTENTE MANTENUTA** - Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorsi assoluti per evitare problemi
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/functions/helpers.php';

// **LOGICA ESISTENTE MANTENUTA** - Verifica autenticazione e permessi admin
if (!AuthSystem::isAuthenticated()) {
    header('Location: /crm/core/auth/login.php');
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
if (!$sessionInfo['is_admin']) {
    header('Location: /crm/modules/operatori/?error=permissions');
    exit;
}

$db = Database::getInstance();

// **LOGICA ESISTENTE MANTENUTA** - Periodo di analisi (default: ultimo mese)
$periodo = $_GET['periodo'] ?? '30';
$validPeriods = ['7', '30', '90', '365'];
if (!in_array($periodo, $validPeriods)) {
    $periodo = '30';
}

// **LOGICA ESISTENTE MANTENUTA** - Calcolo statistiche complete del team
try {
    // Statistiche generali team
    $statsGenerali = $db->selectOne("
        SELECT 
            COUNT(*) as totale_operatori,
            SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as operatori_attivi,
            SUM(CASE WHEN is_amministratore = 1 THEN 1 ELSE 0 END) as amministratori,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY) THEN 1 END) as nuovi_operatori
        FROM operatori
    ") ?: ['totale_operatori' => 0, 'operatori_attivi' => 0, 'amministratori' => 0, 'nuovi_operatori' => 0];

    // Statistiche sessioni periodo selezionato
    $statsSessioni = $db->selectOne("
        SELECT 
            COUNT(*) as sessioni_totali,
            COUNT(DISTINCT operatore_id) as operatori_con_sessioni,
            COALESCE(SUM(
                CASE 
                    WHEN logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                    ELSE 0
                END
            ), 0) as ore_totali,
            COALESCE(AVG(
                CASE 
                    WHEN logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                    ELSE NULL
                END
            ), 0) as media_ore_sessione
        FROM sessioni_lavoro 
        WHERE DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
    ") ?: ['sessioni_totali' => 0, 'operatori_con_sessioni' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0];

    // Performance individuali operatori (ultimi 30 giorni)
    $performanceOperatori = $db->select("
        SELECT 
            o.id,
            o.cognome,
            o.nome,
            o.email,
            o.is_attivo,
            o.is_amministratore,
            COUNT(s.id) as sessioni_count,
            COUNT(DISTINCT DATE(s.login_timestamp)) as giorni_lavorati,
            COALESCE(SUM(
                CASE 
                    WHEN s.logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, s.login_timestamp, s.logout_timestamp) / 60.0
                    ELSE 0
                END
            ), 0) as ore_totali,
            COALESCE(AVG(
                CASE 
                    WHEN s.logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, s.login_timestamp, s.logout_timestamp) / 60.0
                    ELSE NULL
                END
            ), 0) as media_ore_sessione,
            COALESCE(SUM(
                CASE 
                    WHEN s.logout_timestamp IS NOT NULL 
                    AND TIMESTAMPDIFF(MINUTE, s.login_timestamp, s.logout_timestamp) > 480
                    THEN (TIMESTAMPDIFF(MINUTE, s.login_timestamp, s.logout_timestamp) - 480) / 60.0
                    ELSE 0
                END
            ), 0) as ore_extra,
            COUNT(CASE WHEN s.logout_timestamp IS NULL AND DATE(s.login_timestamp) = CURDATE() THEN 1 END) as sessioni_attive,
            MAX(s.login_timestamp) as ultimo_accesso
        FROM operatori o
        LEFT JOIN sessioni_lavoro s ON o.id = s.operatore_id 
            AND DATE(s.login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        WHERE o.is_attivo = 1
        GROUP BY o.id, o.cognome, o.nome, o.email, o.is_attivo, o.is_amministratore
        ORDER BY ore_totali DESC
    ");

    // Distribuzione ore per giorno della settimana
    $distribuzioneGiorni = $db->select("
        SELECT 
            DAYNAME(login_timestamp) as giorno,
            DAYOFWEEK(login_timestamp) as giorno_num,
            COUNT(*) as sessioni,
            COALESCE(SUM(
                CASE 
                    WHEN logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                    ELSE 0
                END
            ), 0) as ore_totali
        FROM sessioni_lavoro 
        WHERE DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        GROUP BY DAYNAME(login_timestamp), DAYOFWEEK(login_timestamp)
        ORDER BY giorno_num
    ");

    // Top performer (ore totali)
    $topPerformer = !empty($performanceOperatori) ? $performanceOperatori[0] : null;

    // Operatore più puntuale (media sessioni/giorno)
    $puntualita = $db->selectOne("
        SELECT 
            o.cognome, o.nome,
            COUNT(s.id) / GREATEST(COUNT(DISTINCT DATE(s.login_timestamp)), 1) as media_sessioni_giorno
        FROM operatori o
        JOIN sessioni_lavoro s ON o.id = s.operatore_id
        WHERE DATE(s.login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        AND o.is_attivo = 1
        GROUP BY o.id, o.cognome, o.nome
        ORDER BY media_sessioni_giorno DESC
        LIMIT 1
    ");

} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    // Valori di fallback
    $statsGenerali = ['totale_operatori' => 0, 'operatori_attivi' => 0, 'amministratori' => 0, 'nuovi_operatori' => 0];
    $statsSessioni = ['sessioni_totali' => 0, 'operatori_con_sessioni' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0];
    $performanceOperatori = [];
    $distribuzioneGiorni = [];
    $topPerformer = null;
    $puntualita = null;
}

// **LOGICA ESISTENTE MANTENUTA** - Calcolo percentuali e metriche derivate
$percentualeAttivi = $statsGenerali['totale_operatori'] > 0 ? 
    round(($statsGenerali['operatori_attivi'] / $statsGenerali['totale_operatori']) * 100, 1) : 0;

$percentualeConSessioni = $statsGenerali['operatori_attivi'] > 0 ? 
    round(($statsSessioni['operatori_con_sessioni'] / $statsGenerali['operatori_attivi']) * 100, 1) : 0;

$oreMedieOperatore = $statsSessioni['operatori_con_sessioni'] > 0 ? 
    round($statsSessioni['ore_totali'] / $statsSessioni['operatori_con_sessioni'], 1) : 0;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche Team - CRM Re.De Consulting</title>
    
    <!-- CSS Sistema Uniforme -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* === LAYOUT ULTRA-DENSO UNIFORME - IDENTICO DASHBOARD === */
        
        /* Variabili CSS Uniformi - IDENTICHE */
        :root {
            --sidebar-width: 200px;
            --header-height: 60px;
            --primary-green: #2c6e49;
            --secondary-green: #4a9d6f;
            --accent-blue: #1e40af;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --danger-red: #dc2626;
            --warning-yellow: #d97706;
            --success-green: #059669;
            --radius-md: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
        }

        /* Layout Principale Identico */
        .app-layout {
            display: flex;
            min-height: 100vh;
            background: var(--gray-50);
        }

        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--gray-200);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 10;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1rem;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--gray-700);
            text-decoration: none;
            font-size: var(--font-size-sm);
            border-radius: 0;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            background: var(--gray-50);
            color: var(--primary-green);
        }

        .nav-link.active {
            background: var(--primary-green);
            color: white;
        }

        .nav-icon {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            background: var(--accent-blue);
            color: white;
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            margin-left: auto;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .app-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            padding: 0.5rem;
        }

        .page-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .work-timer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-green);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }

        .timer-icon {
            font-size: 1rem;
        }

        .time-display {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .user-menu {
            position: relative;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--gray-600);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            font-size: var(--font-size-sm);
        }

        /* Content Layout */
        .content-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        /* Stats Header */
        .stats-header {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-title h1 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-800);
            font-size: 1.75rem;
        }

        .stats-title p {
            margin: 0;
            color: var(--gray-500);
        }

        .period-selector {
            display: flex;
            gap: 0.5rem;
            background: var(--gray-100);
            padding: 0.25rem;
            border-radius: var(--radius-lg);
        }

        .period-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--gray-600);
        }

        .period-btn.active {
            background: var(--primary-green);
            color: white;
        }

        .period-btn:hover:not(.active) {
            background: var(--gray-200);
        }

        /* Statistiche Overview Ultra-Compatte */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: var(--font-size-sm);
            margin-bottom: 0.5rem;
        }

        .stat-change {
            font-size: var(--font-size-xs);
            font-weight: 500;
        }

        .stat-change.positive {
            color: var(--success-green);
        }

        .stat-change.negative {
            color: var(--danger-red);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Performance Table Ultra-Densa */
        .performance-table {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .table-header {
            background: var(--gray-50);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 120px;
            gap: 1rem;
            padding: 1rem 1.5rem;
            align-items: center;
            border-bottom: 1px solid var(--gray-100);
            font-size: var(--font-size-sm);
        }

        .performance-grid:last-child {
            border-bottom: none;
        }

        .performance-grid.header {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            padding: 1rem 1.5rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .operator-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .operator-avatar {
            width: 32px;
            height: 32px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--font-size-xs);
        }

        .operator-details h4 {
            margin: 0;
            font-weight: 600;
            color: var(--gray-800);
            font-size: var(--font-size-sm);
        }

        .operator-details p {
            margin: 0;
            color: var(--gray-500);
            font-size: var(--font-size-xs);
        }

        .metric-value {
            font-weight: 600;
            color: var(--gray-800);
        }

        .metric-sublabel {
            font-size: var(--font-size-xs);
            color: var(--gray-500);
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
            font-weight: 500;
        }

        .status-online {
            background: var(--success-green);
            color: white;
        }

        .status-offline {
            background: var(--gray-400);
            color: white;
        }

        /* Widget Cards */
        .widget {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .widget-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .widget-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .widget-content {
            padding: 1.5rem;
        }

        /* Highlights */
        .highlight-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .highlight-item:last-child {
            border-bottom: none;
        }

        .highlight-label {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        .highlight-value {
            color: var(--gray-800);
            font-weight: 600;
            font-size: var(--font-size-sm);
        }

        /* Distribution Chart */
        .distribution-chart {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .chart-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .bar-label {
            min-width: 80px;
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }

        .bar-container {
            flex: 1;
            height: 20px;
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .bar-fill {
            height: 100%;
            background: var(--primary-green);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .bar-value {
            min-width: 60px;
            text-align: right;
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 2rem;
            font-size: var(--font-size-sm);
            color: var(--gray-500);
        }

        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary-green);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .performance-grid {
                grid-template-columns: 2fr 1fr 1fr 100px;
            }

            .performance-grid .metric-sublabel {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .stats-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }

            .performance-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .content-container {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Uniforme -->
        <aside class="sidebar">
            <nav class="nav">
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="/crm/dashboard.php" class="nav-link">
                            <span class="nav-icon">🏠</span>
                            <span>Dashboard</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/operatori/" class="nav-link active">
                            <span class="nav-icon">👥</span>
                            <span>Operatori</span>
                            <span class="nav-badge">Admin</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="/crm/modules/clienti/" class="nav-link">
                            <span class="nav-icon">🏢</span>
                            <span>Clienti</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/reports/" class="nav-link">
                            <span class="nav-icon">📈</span>
                            <span>Reports</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/admin/" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span>Amministrazione</span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header Uniforme -->
            <header class="app-header">
                <div class="header-left">
                    <button class="sidebar-toggle" type="button">
                        <span style="font-size: 1.25rem;">☰</span>
                    </button>
                    <h1 class="page-title">Statistiche Team</h1>
                </div>
                
                <div class="header-right">
                    <!-- Timer Lavoro -->
                    <div class="work-timer work-timer-display">
                        <span class="timer-icon">⏱️</span>
                        <span class="time-display">00:00:00</span>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="user-menu">
                        <div class="user-avatar" data-tooltip="<?= htmlspecialchars($sessionInfo['nome_completo']) ?>">
                            <?= substr($sessionInfo['nome_completo'], 0, 1) ?>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content-container">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="/crm/dashboard.php">Dashboard</a>
                    <span> > </span>
                    <a href="/crm/modules/operatori/">Operatori</a>
                    <span> > </span>
                    <span style="color: var(--gray-800); font-weight: 500;">Statistiche Team</span>
                </div>
                
                <!-- Header Statistiche -->
                <div class="stats-header">
                    <div class="stats-title">
                        <h1>📊 Statistiche Team</h1>
                        <p>Analisi performance operatori - Aggiornato al <?= date('d/m/Y H:i') ?></p>
                    </div>
                    
                    <div class="period-selector">
                        <a href="?periodo=7" class="period-btn <?= $periodo === '7' ? 'active' : '' ?>">7 giorni</a>
                        <a href="?periodo=30" class="period-btn <?= $periodo === '30' ? 'active' : '' ?>">30 giorni</a>
                        <a href="?periodo=90" class="period-btn <?= $periodo === '90' ? 'active' : '' ?>">90 giorni</a>
                        <a href="?periodo=365" class="period-btn <?= $periodo === '365' ? 'active' : '' ?>">1 anno</a>
                    </div>
                </div>

                <!-- Statistiche Overview Ultra-Compatte -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-value"><?= $statsGenerali['totale_operatori'] ?></div>
                        <div class="stat-label">Operatori Totali</div>
                        <div class="stat-change positive">
                            <?= $percentualeAttivi ?>% attivi
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-value"><?= $statsSessioni['sessioni_totali'] ?></div>
                        <div class="stat-label">Sessioni (<?= $periodo ?> giorni)</div>
                        <div class="stat-change">
                            <?= $percentualeConSessioni ?>% operatori attivi
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-value"><?= number_format($statsSessioni['ore_totali'], 0) ?>h</div>
                        <div class="stat-label">Ore Totali Lavorate</div>
                        <div class="stat-change">
                            ⌀ <?= $oreMedieOperatore ?>h per operatore
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⌀</div>
                        <div class="stat-value"><?= number_format($statsSessioni['media_ore_sessione'], 1) ?>h</div>
                        <div class="stat-label">Media Ore/Sessione</div>
                        <div class="stat-change">
                            Standard: 8h
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🔧</div>
                        <div class="stat-value"><?= $statsGenerali['amministratori'] ?></div>
                        <div class="stat-label">Amministratori</div>
                        <div class="stat-change">
                            <?= $statsGenerali['totale_operatori'] > 0 ? round(($statsGenerali['amministratori'] / $statsGenerali['totale_operatori']) * 100, 1) : 0 ?>% del team
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🆕</div>
                        <div class="stat-value"><?= $statsGenerali['nuovi_operatori'] ?></div>
                        <div class="stat-label">Nuovi Operatori</div>
                        <div class="stat-change">
                            Ultimi <?= $periodo ?> giorni
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Performance Operatori -->
                    <div class="performance-table">
                        <div class="table-header">
                            <h3 class="table-title">🏆 Performance Operatori</h3>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Ultimi <?= $periodo ?> giorni
                            </div>
                        </div>
                        
                        <!-- Header Tabella -->
                        <div class="performance-grid header">
                            <div>Operatore</div>
                            <div>Sessioni</div>
                            <div>Ore Totali</div>
                            <div>Ore Extra</div>
                            <div>Media Ore</div>
                            <div>Giorni Attivi</div>
                            <div>Status</div>
                        </div>
                        
                        <!-- Righe Performance -->
                        <?php if (!empty($performanceOperatori)): ?>
                            <?php foreach ($performanceOperatori as $operatore): ?>
                            <div class="performance-grid">
                                <div class="operator-info">
                                    <div class="operator-avatar">
                                        <?= substr($operatore['cognome'], 0, 1) ?>
                                    </div>
                                    <div class="operator-details">
                                        <h4><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></h4>
                                        <p><?= htmlspecialchars($operatore['email']) ?></p>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="metric-value"><?= $operatore['sessioni_count'] ?></div>
                                    <div class="metric-sublabel"><?= $operatore['giorni_lavorati'] ?> giorni</div>
                                </div>
                                
                                <div>
                                    <div class="metric-value"><?= number_format($operatore['ore_totali'], 1) ?>h</div>
                                    <div class="metric-sublabel">
                                        ⌀ <?= number_format($operatore['media_ore_sessione'], 1) ?>h/sessione
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="metric-value <?= $operatore['ore_extra'] > 0 ? 'text-warning' : '' ?>">
                                        <?= number_format($operatore['ore_extra'], 1) ?>h
                                    </div>
                                    <div class="metric-sublabel">
                                        <?php 
                                        $percExtra = $operatore['ore_totali'] > 0 ? 
                                            round(($operatore['ore_extra'] / $operatore['ore_totali']) * 100, 1) : 0;
                                        ?>
                                        <?= $percExtra ?>% del totale
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="metric-value"><?= number_format($operatore['media_ore_sessione'], 1) ?>h</div>
                                </div>
                                
                                <div>
                                    <div class="metric-value"><?= $operatore['giorni_lavorati'] ?></div>
                                </div>
                                
                                <div>
                                    <div class="status-indicator <?= $operatore['sessioni_attive'] > 0 ? 'status-online' : 'status-offline' ?>">
                                        <?= $operatore['sessioni_attive'] > 0 ? '🟢 Online' : '⚫ Offline' ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                                Nessun dato disponibile per il periodo selezionato
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar Insights -->
                    <div>
                        <!-- Top Performer -->
                        <?php if ($topPerformer): ?>
                        <div class="widget">
                            <div class="widget-header">
                                <h3 class="widget-title">🏆 Top Performer</h3>
                            </div>
                            <div class="widget-content">
                                <div style="text-align: center; margin-bottom: 1rem;">
                                    <div class="operator-avatar" style="width: 60px; height: 60px; font-size: 1.5rem; margin: 0 auto;">
                                        <?= substr($topPerformer['cognome'], 0, 1) ?>
                                    </div>
                                    <h4 style="margin: 0.5rem 0; color: var(--gray-800);">
                                        <?= htmlspecialchars($topPerformer['cognome'] . ' ' . $topPerformer['nome']) ?>
                                    </h4>
                                    <p style="margin: 0; color: var(--gray-500); font-size: var(--font-size-sm);">
                                        <?= number_format($topPerformer['ore_totali'], 1) ?>h lavorate
                                    </p>
                                </div>
                                
                                <div class="highlight-item">
                                    <span class="highlight-label">Sessioni</span>
                                    <span class="highlight-value"><?= $topPerformer['sessioni_count'] ?></span>
                                </div>
                                <div class="highlight-item">
                                    <span class="highlight-label">Media/Sessione</span>
                                    <span class="highlight-value"><?= number_format($topPerformer['media_ore_sessione'], 1) ?>h</span>
                                </div>
                                <div class="highlight-item">
                                    <span class="highlight-label">Giorni Attivi</span>
                                    <span class="highlight-value"><?= $topPerformer['giorni_lavorati'] ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Distribuzione per Giorno -->
                        <?php if (!empty($distribuzioneGiorni)): ?>
                        <div class="widget" style="margin-top: 2rem;">
                            <div class="widget-header">
                                <h3 class="widget-title">📅 Distribuzione Settimanale</h3>
                            </div>
                            <div class="widget-content">
                                <div class="distribution-chart">
                                    <?php 
                                    $maxOre = max(array_column($distribuzioneGiorni, 'ore_totali')) ?: 1;
                                    $giorni_it = [
                                        'Monday' => 'Lunedì',
                                        'Tuesday' => 'Martedì', 
                                        'Wednesday' => 'Mercoledì',
                                        'Thursday' => 'Giovedì',
                                        'Friday' => 'Venerdì',
                                        'Saturday' => 'Sabato',
                                        'Sunday' => 'Domenica'
                                    ];
                                    ?>
                                    <?php foreach ($distribuzioneGiorni as $giorno): ?>
                                    <div class="chart-bar">
                                        <div class="bar-label">
                                            <?= $giorni_it[$giorno['giorno']] ?? $giorno['giorno'] ?>
                                        </div>
                                        <div class="bar-container">
                                            <div class="bar-fill" style="width: <?= ($giorno['ore_totali'] / $maxOre) * 100 ?>%"></div>
                                        </div>
                                        <div class="bar-value">
                                            <?= number_format($giorno['ore_totali'], 1) ?>h
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Insights Aggiuntivi -->
                        <div class="widget" style="margin-top: 2rem;">
                            <div class="widget-header">
                                <h3 class="widget-title">💡 Insights</h3>
                            </div>
                            <div class="widget-content">
                                <div class="highlight-item">
                                    <span class="highlight-label">Efficienza Team</span>
                                    <span class="highlight-value">
                                        <?= $statsGenerali['operatori_attivi'] > 0 ? 
                                            round(($statsSessioni['operatori_con_sessioni'] / $statsGenerali['operatori_attivi']) * 100) : 0 ?>%
                                    </span>
                                </div>
                                
                                <?php if ($puntualita): ?>
                                <div class="highlight-item">
                                    <span class="highlight-label">Più Puntuale</span>
                                    <span class="highlight-value">
                                        <?= htmlspecialchars($puntualita['nome'] . ' ' . $puntualita['cognome']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="highlight-item">
                                    <span class="highlight-label">Ore Medie/Giorno</span>
                                    <span class="highlight-value">
                                        <?= number_format($statsSessioni['ore_totali'] / max($periodo, 1), 1) ?>h
                                    </span>
                                </div>
                                
                                <div class="highlight-item">
                                    <span class="highlight-label">Periodo Analisi</span>
                                    <span class="highlight-value"><?= $periodo ?> giorni</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="/crm/assets/js/microinteractions.js"></script>
    <script>
        // **LOGICA ESISTENTE MANTENUTA** - Timer lavoro
        let timerInterval;
        let startTime = localStorage.getItem('workStartTime');
        let isPaused = localStorage.getItem('workPaused') === 'true';

        function updateTimer() {
            if (!startTime || isPaused) return;
            
            const now = new Date().getTime();
            const elapsed = now - parseInt(startTime);
            const hours = Math.floor(elapsed / (1000 * 60 * 60));
            const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
            
            const display = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            document.querySelector('.time-display').textContent = display;
        }

        if (startTime && !isPaused) {
            timerInterval = setInterval(updateTimer, 1000);
            updateTimer();
        }

        // Toggle sidebar mobile
        document.querySelector('.sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });

        // Animazioni barre distribuzione
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.bar-fill');
            bars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, index * 100);
            });
        });
    </script>
</body>
</html>