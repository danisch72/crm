<?php
/**
 * modules/operatori/view.php - Visualizzazione Operatore CRM Re.De Consulting
 * 
 * ✅ LAYOUT ULTRA-DENSO UNIFORME v2.0 - OPERATORI-FRIENDLY
 * 
 * Features:
 * - Layout identico alla dashboard per uniformità totale
 * - Vista dettagliata ottimizzata per consultazione rapida
 * - Dashboard operatore con statistiche e attività
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

// **LOGICA ESISTENTE MANTENUTA** - Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    header('Location: /crm/core/auth/login.php');
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// **LOGICA ESISTENTE MANTENUTA** - Recupera ID operatore da visualizzare
$operatoreId = $_GET['id'] ?? null;
if (!$operatoreId) {
    header('Location: /crm/modules/operatori/?error=missing_id');
    exit;
}

// Recupera dati operatore
$operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
if (!$operatore) {
    header('Location: /crm/modules/operatori/?error=not_found');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Controllo permessi: admin o auto-view
$canView = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
$isAdminView = $sessionInfo['is_admin'] && $sessionInfo['operatore_id'] != $operatoreId;
$isSelfView = $sessionInfo['operatore_id'] == $operatoreId;

if (!$canView) {
    header('Location: /crm/modules/operatori/?error=permissions');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Calcolo statistiche operatore
try {
    // Statistiche sessioni di lavoro
    $statsLavoro = $db->selectOne("
        SELECT 
            COUNT(*) as sessioni_totali,
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
            ), 0) as media_ore_sessione,
            COUNT(CASE WHEN DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as sessioni_ultimo_mese
        FROM sessioni_lavoro 
        WHERE operatore_id = ?
    ", [$operatoreId]) ?: ['sessioni_totali' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0, 'sessioni_ultimo_mese' => 0];

    // Ultimo accesso
    $ultimoAccesso = $db->selectOne("
        SELECT login_timestamp, logout_timestamp
        FROM sessioni_lavoro 
        WHERE operatore_id = ? 
        ORDER BY login_timestamp DESC 
        LIMIT 1
    ", [$operatoreId]);

    // Sessioni recenti (ultime 10)
    $sessioniRecenti = $db->select("
        SELECT 
            login_timestamp,
            logout_timestamp,
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE NULL
            END as durata_ore
        FROM sessioni_lavoro 
        WHERE operatore_id = ? 
        ORDER BY login_timestamp DESC 
        LIMIT 10
    ", [$operatoreId]);

    // Ore lavorate questa settimana
    $oreSettimana = $db->selectOne("
        SELECT COALESCE(SUM(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE 0
            END
        ), 0) as ore_settimana
        FROM sessioni_lavoro 
        WHERE operatore_id = ? 
        AND YEARWEEK(login_timestamp, 1) = YEARWEEK(CURDATE(), 1)
    ", [$operatoreId])['ore_settimana'] ?? 0;

    // Ore lavorate questo mese
    $oreMese = $db->selectOne("
        SELECT COALESCE(SUM(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE 0
            END
        ), 0) as ore_mese
        FROM sessioni_lavoro 
        WHERE operatore_id = ? 
        AND MONTH(login_timestamp) = MONTH(CURDATE()) 
        AND YEAR(login_timestamp) = YEAR(CURDATE())
    ", [$operatoreId])['ore_mese'] ?? 0;

} catch (Exception $e) {
    error_log("View operator stats error: " . $e->getMessage());
    // Valori di fallback
    $statsLavoro = ['sessioni_totali' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0, 'sessioni_ultimo_mese' => 0];
    $ultimoAccesso = null;
    $sessioniRecenti = [];
    $oreSettimana = 0;
    $oreMese = 0;
}

// Decode qualifiche
$qualifiche = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dettagli Operatore - CRM Re.De Consulting</title>
    
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
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Operator Header */
        .operator-header {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            position: relative;
        }

        .operator-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .operator-avatar-large {
            width: 80px;
            height: 80px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 2rem;
        }

        .operator-details h1 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-800);
            font-size: 1.75rem;
        }

        .operator-meta {
            display: flex;
            gap: 2rem;
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            position: absolute;
            top: 2rem;
            right: 2rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
        }

        .status-active {
            background: var(--success-green);
            color: white;
        }

        .status-inactive {
            background: var(--gray-400);
            color: white;
        }

        .operator-actions {
            position: absolute;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            gap: 1rem;
        }

        /* Dashboard Grid */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
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

        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: var(--font-size-sm);
        }

        /* Info Sections */
        .info-section {
            margin-bottom: 1.5rem;
        }

        .info-section h4 {
            color: var(--gray-700);
            margin-bottom: 1rem;
            font-size: var(--font-size-lg);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        .info-value {
            color: var(--gray-800);
            font-weight: 500;
            font-size: var(--font-size-sm);
        }

        /* Qualifiche */
        .qualifiche-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .qualifica-badge {
            background: var(--primary-green);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
        }

        /* Sessioni Recenti */
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .session-item:last-child {
            border-bottom: none;
        }

        .session-date {
            color: var(--gray-800);
            font-weight: 500;
            font-size: var(--font-size-sm);
        }

        .session-duration {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
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
        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }

            .stats-overview {
                grid-template-columns: 1fr 1fr;
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

            .operator-info {
                flex-direction: column;
                text-align: center;
            }

            .operator-meta {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .operator-actions {
                position: static;
                margin-top: 2rem;
                justify-content: center;
            }

            .stats-overview {
                grid-template-columns: 1fr;
            }

            .content-container {
                padding: 1rem;
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
                            <?php if ($sessionInfo['is_admin']): ?>
                                <span class="nav-badge">Admin</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                
                <?php if ($sessionInfo['is_admin']): ?>
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
                <?php endif; ?>
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
                    <h1 class="page-title">Dettagli Operatore</h1>
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
                    <span style="color: var(--gray-800); font-weight: 500;">
                        <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                    </span>
                </div>
                
                <!-- Header Operatore -->
                <div class="operator-header">
                    <div class="operator-info">
                        <div class="operator-avatar-large">
                            <?= substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1) ?>
                        </div>
                        
                        <div class="operator-details">
                            <h1><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></h1>
                            
                            <div class="operator-meta">
                                <div class="meta-item">
                                    <span>📧</span>
                                    <span><?= htmlspecialchars($operatore['email']) ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span>👔</span>
                                    <span><?= $operatore['is_amministratore'] ? 'Amministratore' : 'Operatore' ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span>⏰</span>
                                    <span><?= ucfirst($operatore['tipo_contratto'] ?? 'Non specificato') ?></span>
                                </div>
                                
                                <?php if ($ultimoAccesso): ?>
                                <div class="meta-item">
                                    <span>🕐</span>
                                    <span>Ultimo accesso: <?= date('d/m/Y H:i', strtotime($ultimoAccesso['login_timestamp'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Badge -->
                    <div class="status-badge <?= $operatore['is_attivo'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                    </div>
                    
                    <!-- Actions -->
                    <div class="operator-actions">
                        <?php if ($canView): ?>
                            <a href="edit.php?id=<?= $operatoreId ?>" class="btn btn-primary">
                                ✏️ Modifica
                            </a>
                        <?php endif; ?>
                        
                        <a href="/crm/modules/operatori/" class="btn btn-secondary">
                            ← Torna alla Lista
                        </a>
                    </div>
                </div>

                <!-- Statistiche Overview -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-value"><?= $statsLavoro['sessioni_totali'] ?></div>
                        <div class="stat-label">Sessioni Totali</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-value"><?= number_format($statsLavoro['ore_totali'], 1) ?>h</div>
                        <div class="stat-label">Ore Lavorate</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-value"><?= number_format($oreSettimana, 1) ?>h</div>
                        <div class="stat-label">Questa Settimana</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📆</div>
                        <div class="stat-value"><?= number_format($oreMese, 1) ?>h</div>
                        <div class="stat-label">Questo Mese</div>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div class="dashboard-content">
                    <!-- Colonna Principale -->
                    <div>
                        <!-- Informazioni Dettagliate -->
                        <div class="widget">
                            <div class="widget-header">
                                <h3 class="widget-title">📋 Informazioni Dettagliate</h3>
                            </div>
                            <div class="widget-content">
                                <!-- Dati Anagrafici -->
                                <div class="info-section">
                                    <h4>👤 Dati Anagrafici</h4>
                                    <div class="info-item">
                                        <span class="info-label">Nome Completo</span>
                                        <span class="info-value"><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Email</span>
                                        <span class="info-value"><?= htmlspecialchars($operatore['email']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Creato il</span>
                                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($operatore['created_at'])) ?></span>
                                    </div>
                                    <?php if ($operatore['updated_at']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Ultimo Aggiornamento</span>
                                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($operatore['updated_at'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Orari di Lavoro -->
                                <div class="info-section">
                                    <h4>⏰ Orari di Lavoro</h4>
                                    <div class="info-item">
                                        <span class="info-label">Tipo Contratto</span>
                                        <span class="info-value"><?= ucfirst($operatore['tipo_contratto'] ?? 'Non specificato') ?></span>
                                    </div>
                                    
                                    <?php if ($operatore['tipo_contratto'] === 'spezzato'): ?>
                                        <div class="info-item">
                                            <span class="info-label">Mattino</span>
                                            <span class="info-value">
                                                <?= htmlspecialchars($operatore['orario_mattino_inizio'] ?? 'N/A') ?> - 
                                                <?= htmlspecialchars($operatore['orario_mattino_fine'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Pomeriggio</span>
                                            <span class="info-value">
                                                <?= htmlspecialchars($operatore['orario_pomeriggio_inizio'] ?? 'N/A') ?> - 
                                                <?= htmlspecialchars($operatore['orario_pomeriggio_fine'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    <?php elseif ($operatore['tipo_contratto'] === 'continuato'): ?>
                                        <div class="info-item">
                                            <span class="info-label">Orario Continuato</span>
                                            <span class="info-value">
                                                <?= htmlspecialchars($operatore['orario_continuato_inizio'] ?? 'N/A') ?> - 
                                                <?= htmlspecialchars($operatore['orario_continuato_fine'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Qualifiche -->
                                <?php if (!empty($qualifiche)): ?>
                                <div class="info-section">
                                    <h4>🎓 Qualifiche Professionali</h4>
                                    <div class="qualifiche-list">
                                        <?php foreach ($qualifiche as $qualifica): ?>
                                            <div class="qualifica-badge"><?= htmlspecialchars($qualifica) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Colonna Laterale -->
                    <div>
                        <!-- Statistiche Lavoro -->
                        <div class="widget">
                            <div class="widget-header">
                                <h3 class="widget-title">📈 Statistiche Lavoro</h3>
                            </div>
                            <div class="widget-content">
                                <div class="info-item">
                                    <span class="info-label">Media Ore/Sessione</span>
                                    <span class="info-value"><?= number_format($statsLavoro['media_ore_sessione'], 1) ?>h</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Sessioni Ultimo Mese</span>
                                    <span class="info-value"><?= $statsLavoro['sessioni_ultimo_mese'] ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status Operatore</span>
                                    <span class="info-value">
                                        <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Privilegi</span>
                                    <span class="info-value">
                                        <?= $operatore['is_amministratore'] ? '🔧 Admin' : '👤 Operatore' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Sessioni Recenti -->
                        <div class="widget" style="margin-top: 2rem;">
                            <div class="widget-header">
                                <h3 class="widget-title">🕐 Sessioni Recenti</h3>
                            </div>
                            <div class="widget-content">
                                <?php if (!empty($sessioniRecenti)): ?>
                                    <?php foreach ($sessioniRecenti as $sessione): ?>
                                    <div class="session-item">
                                        <div class="session-date">
                                            <?= date('d/m/Y', strtotime($sessione['login_timestamp'])) ?>
                                        </div>
                                        <div class="session-duration">
                                            <?php if ($sessione['durata_ore'] !== null): ?>
                                                <?= number_format($sessione['durata_ore'], 1) ?>h
                                            <?php else: ?>
                                                In corso...
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align: center; color: var(--gray-500); padding: 1rem;">
                                        Nessuna sessione registrata
                                    </div>
                                <?php endif; ?>
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
        // **LOGICA ESISTENTE MANTENUTA** - Timer lavoro - logica esistente
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
    </script>
</body>
</html>