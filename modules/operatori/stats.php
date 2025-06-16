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

// Verifica permessi admin (doppio controllo)
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

    // Top operatori per ore lavorate
    $topOperatoriOre = $db->select("
        SELECT 
            o.id,
            o.nome,
            o.cognome,
            o.codice_operatore,
            COUNT(sl.id) as sessioni,
            COALESCE(SUM(
                CASE 
                    WHEN sl.logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, sl.login_timestamp, sl.logout_timestamp) / 60.0
                    ELSE 0
                END
            ), 0) as ore_totali,
            COALESCE(AVG(
                CASE 
                    WHEN sl.logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, sl.login_timestamp, sl.logout_timestamp) / 60.0
                    ELSE NULL
                END
            ), 0) as media_ore_sessione
        FROM operatori o
        LEFT JOIN sessioni_lavoro sl ON o.id = sl.operatore_id 
            AND DATE(sl.login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        WHERE o.is_attivo = 1
        GROUP BY o.id
        ORDER BY ore_totali DESC
        LIMIT 10
    ");

    // Statistiche per giorno della settimana
    $statsByDayOfWeek = $db->select("
        SELECT 
            DAYNAME(login_timestamp) as giorno,
            DAYOFWEEK(login_timestamp) as giorno_numero,
            COUNT(*) as sessioni,
            COUNT(DISTINCT operatore_id) as operatori_unici,
            COALESCE(AVG(
                CASE 
                    WHEN logout_timestamp IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                    ELSE NULL
                END
            ), 0) as media_ore
        FROM sessioni_lavoro
        WHERE DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        GROUP BY DAYOFWEEK(login_timestamp)
        ORDER BY DAYOFWEEK(login_timestamp)
    ");

    // Statistiche per fascia oraria
    $statsByHour = $db->select("
        SELECT 
            HOUR(login_timestamp) as ora,
            COUNT(*) as sessioni,
            COUNT(DISTINCT operatore_id) as operatori_unici
        FROM sessioni_lavoro
        WHERE DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL {$periodo} DAY)
        GROUP BY HOUR(login_timestamp)
        ORDER BY HOUR(login_timestamp)
    ");

    // Statistiche clienti per operatore
    $statsClienti = $db->select("
        SELECT 
            o.id,
            o.nome,
            o.cognome,
            COUNT(c.id) as clienti_totali,
            COUNT(CASE WHEN c.is_attivo = 1 THEN 1 END) as clienti_attivi
        FROM operatori o
        LEFT JOIN clienti c ON o.id = c.operatore_responsabile_id
        WHERE o.is_attivo = 1
        GROUP BY o.id
        HAVING clienti_totali > 0
        ORDER BY clienti_totali DESC
    ");

    // Calcolo produttività media
    $produttivitaMedia = 0;
    if ($statsSessioni['operatori_con_sessioni'] > 0) {
        $produttivitaMedia = $statsSessioni['ore_totali'] / $statsSessioni['operatori_con_sessioni'];
    }

} catch (Exception $e) {
    error_log("Errore caricamento statistiche team: " . $e->getMessage());
    // Inizializza con valori vuoti in caso di errore
    $statsGenerali = ['totale_operatori' => 0, 'operatori_attivi' => 0, 'amministratori' => 0, 'nuovi_operatori' => 0];
    $statsSessioni = ['sessioni_totali' => 0, 'operatori_con_sessioni' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0];
    $topOperatoriOre = [];
    $statsByDayOfWeek = [];
    $statsByHour = [];
    $statsClienti = [];
    $produttivitaMedia = 0;
}

// Funzioni helper
function formatHours($hours) {
    return number_format($hours, 1) . 'h';
}

// Prepara dati per grafici
$chartDataDays = [];
$chartDataHours = [];

// Mappa giorni italiano
$giorniItaliano = [
    'Monday' => 'Lunedì',
    'Tuesday' => 'Martedì', 
    'Wednesday' => 'Mercoledì',
    'Thursday' => 'Giovedì',
    'Friday' => 'Venerdì',
    'Saturday' => 'Sabato',
    'Sunday' => 'Domenica'
];

foreach ($statsByDayOfWeek as $day) {
    $chartDataDays[] = [
        'giorno' => $giorniItaliano[$day['giorno']] ?? $day['giorno'],
        'sessioni' => $day['sessioni'],
        'ore_medie' => round($day['media_ore'], 1)
    ];
}

foreach ($statsByHour as $hour) {
    $chartDataHours[] = [
        'ora' => sprintf('%02d:00', $hour['ora']),
        'sessioni' => $hour['sessioni']
    ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche Team - CRM Re.De Consulting</title>
    
    <style>
        :root {
            --primary-blue: #194F8B;
            --secondary-green: #97BC5B;
            --accent-orange: #FF7F41;
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
            --success-green: #22c55e;
            --warning-yellow: #f59e0b;
            --danger-red: #ef4444;
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --transition-fast: 150ms ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        /* Container principale */
        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-blue);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Header */
        .main-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }
        
        .header-left p {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Bottoni */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all var(--transition-fast);
            border: 1px solid transparent;
            cursor: pointer;
            background: white;
        }
        
        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }
        
        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
        }
        
        /* Period Selector */
        .period-selector {
            background: white;
            border-radius: var(--radius-lg);
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .period-selector label {
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .period-button {
            padding: 0.375rem 0.75rem;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        
        .period-button:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .period-button.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        
        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--gray-100);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--gray-900);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.75rem;
            color: var(--success-green);
            margin-top: 0.25rem;
        }
        
        .stat-change.negative {
            color: var(--danger-red);
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        /* Widget */
        .widget {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .widget-header {
            padding: 1rem 1.25rem;
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
            padding: 1.25rem;
        }
        
        /* Table */
        .stats-table {
            width: 100%;
            font-size: 0.875rem;
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 0.5rem;
            padding: 0.75rem 0;
            border-bottom: 2px solid var(--gray-200);
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 0.5rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
            align-items: center;
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .operator-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .operator-avatar {
            width: 32px;
            height: 32px;
            background: var(--gray-200);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
        }
        
        .operator-name {
            font-weight: 500;
            color: var(--gray-900);
        }
        
        .operator-code {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* Charts */
        .chart-container {
            height: 300px;
            position: relative;
            margin-top: 1rem;
        }
        
        .chart-bar {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 0.5rem;
            padding: 0 0.5rem;
        }
        
        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }
        
        .bar {
            width: 100%;
            background: var(--primary-blue);
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            transition: all var(--transition-fast);
            position: relative;
        }
        
        .bar:hover {
            background: var(--accent-orange);
        }
        
        .bar-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
            text-align: center;
        }
        
        .bar-value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        /* Progress bars */
        .progress-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .progress-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
        }
        
        .progress-bar {
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-blue);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .period-selector {
                flex-wrap: wrap;
            }
            
            .table-header,
            .table-row {
                grid-template-columns: 2fr 1fr 1fr;
            }
            
            .table-header > div:nth-child(4),
            .table-header > div:nth-child(5),
            .table-row > div:nth-child(4),
            .table-row > div:nth-child(5) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="stats-container">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span>Statistiche Team</span>
        </div>

        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <h1>📊 Statistiche Team</h1>
                <p>Analisi performance del team operatori</p>
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

        <!-- Period Selector -->
        <div class="period-selector">
            <label>Periodo di analisi:</label>
            <a href="/crm/?action=operatori&view=stats&periodo=7" 
               class="period-button <?= $periodo == '7' ? 'active' : '' ?>">
                Ultima settimana
            </a>
            <a href="/crm/?action=operatori&view=stats&periodo=30" 
               class="period-button <?= $periodo == '30' ? 'active' : '' ?>">
                Ultimo mese
            </a>
            <a href="/crm/?action=operatori&view=stats&periodo=90" 
               class="period-button <?= $periodo == '90' ? 'active' : '' ?>">
                Ultimi 3 mesi
            </a>
            <a href="/crm/?action=operatori&view=stats&periodo=365" 
               class="period-button <?= $periodo == '365' ? 'active' : '' ?>">
                Ultimo anno
            </a>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= $statsGenerali['operatori_attivi'] ?></div>
                        <div class="stat-label">Operatori Attivi</div>
                        <div class="stat-change">
                            su <?= $statsGenerali['totale_operatori'] ?> totali
                        </div>
                    </div>
                    <div class="stat-icon">👥</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatHours($statsSessioni['ore_totali']) ?></div>
                        <div class="stat-label">Ore Lavorate Totali</div>
                        <div class="stat-change">
                            <?= $statsSessioni['sessioni_totali'] ?> sessioni
                        </div>
                    </div>
                    <div class="stat-icon">⏰</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatHours($produttivitaMedia) ?></div>
                        <div class="stat-label">Media Ore/Operatore</div>
                        <div class="stat-change">
                            <?= $statsSessioni['operatori_con_sessioni'] ?> operatori attivi
                        </div>
                    </div>
                    <div class="stat-icon">📈</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatHours($statsSessioni['media_ore_sessione']) ?></div>
                        <div class="stat-label">Durata Media Sessione</div>
                        <div class="stat-change">
                            per sessione completata
                        </div>
                    </div>
                    <div class="stat-icon">🕐</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Top Operatori -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">🏆 Top Operatori per Ore Lavorate</h3>
                </div>
                <div class="widget-content">
                    <?php if (empty($topOperatoriOre)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <p>Nessun dato disponibile per il periodo selezionato</p>
                        </div>
                    <?php else: ?>
                        <div class="stats-table">
                            <div class="table-header">
                                <div>Operatore</div>
                                <div>Sessioni</div>
                                <div>Ore Tot.</div>
                                <div>Media/Sess.</div>
                                <div>Posizione</div>
                            </div>
                            <?php 
                            $position = 1;
                            foreach ($topOperatoriOre as $operatore): 
                            ?>
                                <div class="table-row">
                                    <div class="operator-info">
                                        <div class="operator-avatar">
                                            <?= strtoupper(substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="operator-name">
                                                <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                                            </div>
                                            <div class="operator-code">
                                                <?= htmlspecialchars($operatore['codice_operatore']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div><?= $operatore['sessioni'] ?></div>
                                    <div><strong><?= formatHours($operatore['ore_totali']) ?></strong></div>
                                    <div><?= formatHours($operatore['media_ore_sessione']) ?></div>
                                    <div>
                                        <?php if ($position <= 3): ?>
                                            <?= $position == 1 ? '🥇' : ($position == 2 ? '🥈' : '🥉') ?>
                                        <?php else: ?>
                                            #<?= $position ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                            $position++;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistiche Laterali -->
            <div>
                <!-- Distribuzione per Giorno -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">📅 Attività per Giorno</h3>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($chartDataDays)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📊</div>
                                <p>Nessun dato</p>
                            </div>
                        <?php else: ?>
                            <div class="chart-bar">
                                <?php 
                                $maxSessions = max(array_column($chartDataDays, 'sessioni'));
                                foreach ($chartDataDays as $day): 
                                    $height = $maxSessions > 0 ? ($day['sessioni'] / $maxSessions * 100) : 0;
                                ?>
                                    <div class="bar-item">
                                        <div class="bar" style="height: <?= $height ?>%;">
                                            <span class="bar-value"><?= $day['sessioni'] ?></span>
                                        </div>
                                        <div class="bar-label"><?= substr($day['giorno'], 0, 3) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Distribuzione Clienti -->
                <div class="widget" style="margin-top: 1.5rem;">
                    <div class="widget-header">
                        <h3 class="widget-title">🏢 Distribuzione Clienti</h3>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($statsClienti)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">🏢</div>
                                <p>Nessun cliente assegnato</p>
                            </div>
                        <?php else: ?>
                            <div class="progress-list">
                                <?php 
                                $maxClienti = max(array_column($statsClienti, 'clienti_totali'));
                                foreach (array_slice($statsClienti, 0, 5) as $op): 
                                    $percentage = $maxClienti > 0 ? ($op['clienti_totali'] / $maxClienti * 100) : 0;
                                ?>
                                    <div class="progress-item">
                                        <div class="progress-header">
                                            <span><?= htmlspecialchars($op['cognome'] . ' ' . substr($op['nome'], 0, 1) . '.') ?></span>
                                            <span><?= $op['clienti_totali'] ?> clienti</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $percentage ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiche Orarie -->
        <div class="widget" style="margin-top: 1.5rem;">
            <div class="widget-header">
                <h3 class="widget-title">🕐 Distribuzione Oraria Accessi</h3>
            </div>
            <div class="widget-content">
                <?php if (empty($chartDataHours)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <p>Nessun dato disponibile</p>
                    </div>
                <?php else: ?>
                    <div class="chart-bar">
                        <?php 
                        // Crea array completo 24 ore
                        $hoursData = array_fill(0, 24, 0);
                        foreach ($statsByHour as $hour) {
                            $hoursData[$hour['ora']] = $hour['sessioni'];
                        }
                        
                        $maxHourSessions = max($hoursData);
                        for ($h = 0; $h < 24; $h++): 
                            $height = $maxHourSessions > 0 ? ($hoursData[$h] / $maxHourSessions * 100) : 0;
                            if ($h < 6 || $h > 20) continue; // Mostra solo ore lavorative
                        ?>
                            <div class="bar-item">
                                <div class="bar" style="height: <?= $height ?>%; background: <?= $h >= 9 && $h <= 18 ? 'var(--primary-blue)' : 'var(--gray-400)' ?>;">
                                    <?php if ($hoursData[$h] > 0): ?>
                                        <span class="bar-value"><?= $hoursData[$h] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bar-label"><?= $h ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <p style="text-align: center; margin-top: 1rem; font-size: 0.75rem; color: var(--gray-500);">
                        Orario (fascia 6:00 - 21:00)
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Animazione progress bars
        document.addEventListener('DOMContentLoaded', () => {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>