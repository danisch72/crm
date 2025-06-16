<?php
/**
 * modules/operatori/stats.php - Statistiche Team CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON ROUTER
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message

$pageTitle = 'Statistiche Team';

// Verifica permessi admin (già controllato dal router, ma doppio check)
if (!$sessionInfo['is_admin']) {
    header('Location: /crm/?action=operatori&error=permissions');
    exit;
}

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

    // Top 5 operatori per ore lavorate
    $topOperatori = $db->select("
        SELECT 
            o.id,
            o.nome,
            o.cognome,
            COUNT(s.id) as sessioni,
            COALESCE(SUM(
                CASE 
                    WHEN s.logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, s.login_timestamp, s.logout_timestamp) / 60.0
                    ELSE 0
                END
            ), 0) as ore_totali
        FROM operatori o
        LEFT JOIN sessioni_lavoro s ON o.id = s.operatore_id 
            AND DATE(s.login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        WHERE o.is_attivo = 1
        GROUP BY o.id
        ORDER BY ore_totali DESC
        LIMIT 5
    ");

    // Distribuzione ore per giorno della settimana
    $orePerGiorno = $db->select("
        SELECT 
            DAYOFWEEK(login_timestamp) as giorno_settimana,
            DAYNAME(login_timestamp) as nome_giorno,
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
        GROUP BY DAYOFWEEK(login_timestamp)
        ORDER BY DAYOFWEEK(login_timestamp)
    ");

    // Trend settimanale ore lavorate
    $trendSettimanale = $db->select("
        SELECT 
            YEARWEEK(login_timestamp) as settimana,
            MIN(DATE(login_timestamp)) as inizio_settimana,
            COUNT(DISTINCT operatore_id) as operatori_attivi,
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
        GROUP BY YEARWEEK(login_timestamp)
        ORDER BY settimana DESC
        LIMIT 12
    ");

    // Operatori senza sessioni nel periodo
    $operatoriInattivi = $db->select("
        SELECT o.id, o.nome, o.cognome, o.email
        FROM operatori o
        WHERE o.is_attivo = 1
        AND o.id NOT IN (
            SELECT DISTINCT operatore_id 
            FROM sessioni_lavoro 
            WHERE DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        )
    ");

} catch (Exception $e) {
    error_log("Errore calcolo statistiche team: " . $e->getMessage());
    // Valori di default in caso di errore
    $statsGenerali = ['totale_operatori' => 0, 'operatori_attivi' => 0, 'amministratori' => 0, 'nuovi_operatori' => 0];
    $statsSessioni = ['sessioni_totali' => 0, 'operatori_con_sessioni' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0];
    $topOperatori = [];
    $orePerGiorno = [];
    $trendSettimanale = [];
    $operatoriInattivi = [];
}

// Funzioni helper
function getPeriodoLabel($periodo) {
    $labels = [
        '7' => 'Ultima Settimana',
        '30' => 'Ultimo Mese',
        '90' => 'Ultimi 3 Mesi',
        '365' => 'Ultimo Anno'
    ];
    return $labels[$periodo] ?? 'Periodo Personalizzato';
}

function getGiornoItaliano($dayNumber) {
    $giorni = [
        1 => 'Domenica',
        2 => 'Lunedì',
        3 => 'Martedì',
        4 => 'Mercoledì',
        5 => 'Giovedì',
        6 => 'Venerdì',
        7 => 'Sabato'
    ];
    return $giorni[$dayNumber] ?? '';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De Consulting</title>
    
    <style>
        /* Design System Datev Koinos Compliant */
        :root {
            --primary-green: #00A86B;
            --secondary-green: #2E7D32;
            --accent-orange: #FF6B35;
            --danger-red: #DC3545;
            --warning-yellow: #FFC107;
            --gray-50: #F8F9FA;
            --gray-100: #E9ECEF;
            --gray-200: #DEE2E6;
            --gray-300: #CED4DA;
            --gray-400: #ADB5BD;
            --gray-500: #6C757D;
            --gray-600: #495057;
            --gray-700: #343A40;
            --gray-800: #212529;
            --font-base: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --transition-fast: all 0.15s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-base);
            font-size: 14px;
            color: var(--gray-800);
            background: #f5f5f5;
            line-height: 1.4;
        }
        
        /* Layout Container */
        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            padding: 0.5rem 0;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Header */
        .page-header {
            background: white;
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            color: var(--gray-800);
            margin: 0;
            font-weight: 600;
        }
        
        /* Period Selector */
        .period-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .period-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .period-buttons {
            display: flex;
            gap: 0.25rem;
        }
        
        .period-btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            text-decoration: none;
            transition: var(--transition-fast);
        }
        
        .period-btn:hover {
            background: var(--gray-50);
        }
        
        .period-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Bottoni */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .btn-outline {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
        }
        
        /* Stats Grid Overview */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card.highlight {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        /* Widget */
        .widget {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .widget-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .widget-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .widget-content {
            padding: 1.5rem;
        }
        
        /* Top List */
        .top-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .top-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }
        
        .top-position {
            width: 32px;
            height: 32px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .top-position.gold {
            background: #FFD700;
            color: var(--gray-800);
        }
        
        .top-position.silver {
            background: #C0C0C0;
            color: var(--gray-800);
        }
        
        .top-position.bronze {
            background: #CD7F32;
            color: white;
        }
        
        .top-info {
            flex: 1;
        }
        
        .top-name {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.125rem;
        }
        
        .top-stats {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .top-value {
            font-weight: 600;
            font-size: 1rem;
            color: var(--primary-green);
        }
        
        /* Chart Container */
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Distribuzione Ore */
        .ore-bars {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
            height: 200px;
            margin-bottom: 1rem;
        }
        
        .ore-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        
        .ore-fill {
            background: var(--primary-green);
            width: 100%;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            position: relative;
            min-height: 4px;
        }
        
        .ore-value {
            position: absolute;
            top: -20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .ore-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
        }
        
        /* Inactive List */
        .inactive-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .inactive-item {
            padding: 0.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
        }
        
        .inactive-name {
            font-weight: 500;
        }
        
        .inactive-email {
            color: var(--gray-600);
            font-size: 0.75rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .period-selector {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            .period-buttons {
                width: 100%;
            }
            
            .period-btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="stats-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span>Statistiche Team</span>
        </div>
        
        <!-- Header -->
        <header class="page-header">
            <div class="header-left">
                <h1 class="page-title">📊 Statistiche Team</h1>
                <div class="period-selector">
                    <span class="period-label">Periodo:</span>
                    <div class="period-buttons">
                        <a href="/crm/?action=operatori&view=stats&periodo=7" 
                           class="period-btn <?= $periodo == '7' ? 'active' : '' ?>">7 giorni</a>
                        <a href="/crm/?action=operatori&view=stats&periodo=30" 
                           class="period-btn <?= $periodo == '30' ? 'active' : '' ?>">30 giorni</a>
                        <a href="/crm/?action=operatori&view=stats&periodo=90" 
                           class="period-btn <?= $periodo == '90' ? 'active' : '' ?>">3 mesi</a>
                        <a href="/crm/?action=operatori&view=stats&periodo=365" 
                           class="period-btn <?= $periodo == '365' ? 'active' : '' ?>">1 anno</a>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="/crm/?action=operatori" class="btn btn-secondary">
                    ← Torna alla Lista
                </a>
                <a href="/crm/?action=dashboard" class="btn btn-outline">
                    🏠 Dashboard
                </a>
            </div>
        </header>
        
        <!-- Stats Grid Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $statsGenerali['operatori_attivi'] ?></div>
                        <div class="stat-label">Operatori Attivi</div>
                    </div>
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-change">
                    Su <?= $statsGenerali['totale_operatori'] ?> totali
                </div>
            </div>
            
            <div class="stat-card highlight">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($statsSessioni['ore_totali'], 0) ?>h</div>
                        <div class="stat-label">Ore Lavorate</div>
                    </div>
                    <div class="stat-icon">⏰</div>
                </div>
                <div class="stat-change">
                    <?= getPeriodoLabel($periodo) ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $statsSessioni['sessioni_totali'] ?></div>
                        <div class="stat-label">Sessioni Totali</div>
                    </div>
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-change">
                    Media <?= number_format($statsSessioni['media_ore_sessione'], 1) ?>h/sessione
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $statsSessioni['operatori_con_sessioni'] ?></div>
                        <div class="stat-label">Operatori Produttivi</div>
                    </div>
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-change">
                    <?= $statsGenerali['nuovi_operatori'] ?> nuovi nel periodo
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Top Operatori -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">🏆 Top Operatori per Ore Lavorate</h3>
                </div>
                <div class="widget-content">
                    <?php if (!empty($topOperatori)): ?>
                        <div class="top-list">
                            <?php foreach ($topOperatori as $index => $op): ?>
                                <div class="top-item">
                                    <div class="top-position <?= $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : '')) ?>">
                                        <?= $index + 1 ?>
                                    </div>
                                    <div class="top-info">
                                        <div class="top-name">
                                            <?= htmlspecialchars($op['cognome'] . ' ' . $op['nome']) ?>
                                        </div>
                                        <div class="top-stats">
                                            <?= $op['sessioni'] ?> sessioni
                                        </div>
                                    </div>
                                    <div class="top-value">
                                        <?= number_format($op['ore_totali'], 1) ?>h
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Nessun dato disponibile per il periodo selezionato</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Operatori Inattivi -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">⚠️ Operatori Senza Sessioni</h3>
                </div>
                <div class="widget-content">
                    <?php if (!empty($operatoriInattivi)): ?>
                        <div class="inactive-list">
                            <?php foreach ($operatoriInattivi as $op): ?>
                                <div class="inactive-item">
                                    <div>
                                        <div class="inactive-name">
                                            <?= htmlspecialchars($op['cognome'] . ' ' . $op['nome']) ?>
                                        </div>
                                        <div class="inactive-email">
                                            <?= htmlspecialchars($op['email']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>✅ Tutti gli operatori hanno effettuato almeno una sessione</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Distribuzione Ore per Giorno -->
        <div class="widget">
            <div class="widget-header">
                <h3 class="widget-title">📅 Distribuzione Ore per Giorno della Settimana</h3>
            </div>
            <div class="widget-content">
                <?php if (!empty($orePerGiorno)): ?>
                    <?php 
                    // Trova il valore massimo per la scala
                    $maxOre = max(array_column($orePerGiorno, 'ore_totali'));
                    ?>
                    <div class="ore-bars">
                        <?php for ($i = 2; $i <= 6; $i++): // Lun-Ven ?>
                            <?php
                            $dayData = null;
                            foreach ($orePerGiorno as $data) {
                                if ($data['giorno_settimana'] == $i) {
                                    $dayData = $data;
                                    break;
                                }
                            }
                            $ore = $dayData ? $dayData['ore_totali'] : 0;
                            $height = $maxOre > 0 ? ($ore / $maxOre * 100) : 0;
                            ?>
                            <div class="ore-bar">
                                <div class="ore-fill" style="height: <?= $height ?>%">
                                    <div class="ore-value"><?= number_format($ore, 0) ?>h</div>
                                </div>
                                <div class="ore-label"><?= substr(getGiornoItaliano($i), 0, 3) ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Nessun dato disponibile per il periodo selezionato</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>