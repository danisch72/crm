<?php
/**
 * modules/operatori/stats.php - Statistiche Team CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON COMPONENTI CENTRALIZZATI
 * ✅ SIDEBAR E HEADER INCLUSI COME DA ARCHITETTURA
 * ✅ DESIGN DATEV PROFESSIONAL ULTRA-COMPRESSO
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Variabili per i componenti (OBBLIGATORIE)
$pageTitle = 'Statistiche Team';
$pageIcon = '📊';

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
    $topOperatori = [];
    $orePerGiorno = [];
    $trendSettimanale = [];
    $operatoriInattivi = [];
    $produttivitaMedia = 0;
}

// Helper functions
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

foreach ($orePerGiorno as $day) {
    $chartDataDays[] = [
        'giorno' => $giorniItaliano[$day['nome_giorno']] ?? $day['nome_giorno'],
        'ore' => round($day['ore_totali'], 1)
    ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De</title>
    
    <!-- CSS nell'ordine corretto -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-professional.css">
    <link rel="stylesheet" href="/crm/assets/css/operatori.css">
    
    <style>
        /* Container principale */
        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Header pagina */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .header-left h1 {
            font-size: 1.5rem;
            color: var(--gray-900);
            margin: 0;
        }
        
        .header-left p {
            color: var(--gray-600);
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        /* Period Selector */
        .period-selector {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .period-selector label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        
        .period-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .period-button {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
        }
        
        .period-button:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .period-button.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        /* Stats Overview Cards */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }
        
        .stat-change {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.1;
            position: absolute;
            right: 1rem;
            bottom: 1rem;
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
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .widget-header {
            padding: 1rem 1.25rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .widget-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        
        .widget-content {
            padding: 1.25rem;
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
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
        }
        
        .top-item:hover {
            background: var(--gray-100);
            transform: translateX(4px);
        }
        
        .top-position {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border-radius: 50%;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }
        
        .top-position.gold {
            background: #ffd700;
            color: #7c6200;
        }
        
        .top-position.silver {
            background: #c0c0c0;
            color: #5a5a5a;
        }
        
        .top-position.bronze {
            background: #cd7f32;
            color: #5d3a16;
        }
        
        .top-info {
            flex: 1;
        }
        
        .top-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.875rem;
        }
        
        .top-details {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.125rem;
        }
        
        .top-value {
            text-align: right;
            font-weight: 600;
            color: var(--primary-green);
            font-size: 0.875rem;
        }
        
        /* Chart Container */
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .chart-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray-400);
            font-size: 0.875rem;
            text-align: center;
        }
        
        /* Trend Table */
        .trend-table {
            width: 100%;
            font-size: 0.8125rem;
        }
        
        .trend-table th {
            text-align: left;
            padding: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 2px solid var(--gray-200);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .trend-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .trend-table tr:hover td {
            background: var(--gray-50);
        }
        
        /* Inactive List */
        .inactive-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .inactive-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            font-size: 0.8125rem;
        }
        
        .inactive-name {
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .inactive-email {
            color: var(--gray-600);
            font-size: 0.75rem;
            margin-top: 0.125rem;
        }
        
        /* Empty State */
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
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .period-selector {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .period-buttons {
                width: 100%;
                flex-wrap: wrap;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .top-item {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body class="datev-compact">
    <div class="app-layout">
        <!-- ✅ COMPONENTE SIDEBAR (OBBLIGATORIO) -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <!-- ✅ COMPONENTE HEADER (OBBLIGATORIO) -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="stats-container">
                    <!-- Header -->
                    <header class="page-header">
                        <div class="header-left">
                            <h1>📊 Statistiche Team</h1>
                            <p>Analisi performance del team operatori</p>
                        </div>
                        <div class="header-actions">
                            <a href="/crm/?action=operatori" class="btn btn-secondary">
                                ← Torna alla Lista
                            </a>
                            <a href="/crm/?action=dashboard" class="btn btn-secondary">
                                🏠 Dashboard
                            </a>
                        </div>
                    </header>
                    
                    <!-- Period Selector -->
                    <div class="period-selector">
                        <label>Periodo di analisi:</label>
                        <div class="period-buttons">
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
                    </div>
                    
                    <!-- Stats Overview -->
                    <div class="stats-overview">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value"><?= $statsGenerali['totale_operatori'] ?></div>
                                    <div class="stat-label">Operatori Totali</div>
                                    <div class="stat-change">
                                        <?= $statsGenerali['operatori_attivi'] ?> attivi
                                    </div>
                                </div>
                                <div class="stat-icon">👥</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value"><?= formatHours($statsSessioni['ore_totali']) ?></div>
                                    <div class="stat-label">Ore Totali</div>
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
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value"><?= $statsSessioni['operatori_con_sessioni'] ?></div>
                                    <div class="stat-label">Operatori Produttivi</div>
                                    <div class="stat-change">
                                        <?= $statsGenerali['nuovi_operatori'] ?> nuovi nel periodo
                                    </div>
                                </div>
                                <div class="stat-icon">✅</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dashboard Grid -->
                    <div class="dashboard-grid">
                        <!-- Colonna Principale -->
                        <div>
                            <!-- Top Operatori -->
                            <div class="widget">
                                <div class="widget-header">
                                    <h3 class="widget-title">🏆 Top Operatori per Ore Lavorate</h3>
                                </div>
                                <div class="widget-content">
                                    <?php if (empty($topOperatori)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📊</div>
                                            <p>Nessun dato disponibile per il periodo selezionato</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="top-list">
                                            <?php 
                                            $position = 1;
                                            foreach ($topOperatori as $op): 
                                            ?>
                                                <div class="top-item">
                                                    <div class="top-position <?= $position == 1 ? 'gold' : ($position == 2 ? 'silver' : ($position == 3 ? 'bronze' : '')) ?>">
                                                        <?= $position ?>
                                                    </div>
                                                    <div class="top-info">
                                                        <div class="top-name">
                                                            <?= htmlspecialchars($op['cognome'] . ' ' . $op['nome']) ?>
                                                        </div>
                                                        <div class="top-details">
                                                            <?= $op['sessioni'] ?> sessioni • 
                                                            Media: <?= $op['sessioni'] > 0 ? formatHours($op['ore_totali'] / $op['sessioni']) : '0h' ?>
                                                        </div>
                                                    </div>
                                                    <div class="top-value">
                                                        <?= formatHours($op['ore_totali']) ?>
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
                            
                            <!-- Distribuzione Ore per Giorno -->
                            <div class="widget" style="margin-top: 1.5rem;">
                                <div class="widget-header">
                                    <h3 class="widget-title">📅 Distribuzione Ore per Giorno</h3>
                                </div>
                                <div class="widget-content">
                                    <div class="chart-container">
                                        <div class="chart-placeholder">
                                            <?php if (!empty($chartDataDays)): ?>
                                                <!-- Grafico semplificato con barre CSS -->
                                                <div style="width: 100%; height: 100%;">
                                                    <?php 
                                                    $maxOre = max(array_column($chartDataDays, 'ore'));
                                                    foreach ($chartDataDays as $day): 
                                                        $percentage = $maxOre > 0 ? ($day['ore'] / $maxOre * 100) : 0;
                                                    ?>
                                                        <div style="display: flex; align-items: center; margin-bottom: 0.75rem;">
                                                            <div style="width: 80px; font-size: 0.75rem; color: var(--gray-600);">
                                                                <?= substr($day['giorno'], 0, 3) ?>
                                                            </div>
                                                            <div style="flex: 1; height: 24px; background: var(--gray-100); border-radius: 4px; position: relative;">
                                                                <div style="height: 100%; width: <?= $percentage ?>%; background: var(--primary-green); border-radius: 4px;"></div>
                                                            </div>
                                                            <div style="width: 50px; text-align: right; font-size: 0.75rem; font-weight: 600; color: var(--gray-700);">
                                                                <?= $day['ore'] ?>h
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p>Nessun dato disponibile</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Trend Settimanale -->
                            <div class="widget" style="margin-top: 1.5rem;">
                                <div class="widget-header">
                                    <h3 class="widget-title">📈 Trend Settimanale</h3>
                                </div>
                                <div class="widget-content">
                                    <?php if (empty($trendSettimanale)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📈</div>
                                            <p>Nessun dato disponibile</p>
                                        </div>
                                    <?php else: ?>
                                        <table class="trend-table">
                                            <thead>
                                                <tr>
                                                    <th>Settimana</th>
                                                    <th>Operatori</th>
                                                    <th>Sessioni</th>
                                                    <th style="text-align: right;">Ore Totali</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_reverse($trendSettimanale) as $week): ?>
                                                    <tr>
                                                        <td>
                                                            <?= date('d/m', strtotime($week['inizio_settimana'])) ?>
                                                        </td>
                                                        <td><?= $week['operatori_attivi'] ?></td>
                                                        <td><?= $week['sessioni'] ?></td>
                                                        <td style="text-align: right; font-weight: 600;">
                                                            <?= formatHours($week['ore_totali']) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonna Laterale -->
                        <div>
                            <!-- Operatori Inattivi -->
                            <div class="widget">
                                <div class="widget-header">
                                    <h3 class="widget-title">⚠️ Operatori Senza Sessioni</h3>
                                </div>
                                <div class="widget-content">
                                    <?php if (empty($operatoriInattivi)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">✅</div>
                                            <p>Tutti gli operatori attivi hanno registrato sessioni</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="inactive-list">
                                            <?php foreach ($operatoriInattivi as $op): ?>
                                                <div class="inactive-item">
                                                    <div class="inactive-name">
                                                        <?= htmlspecialchars($op['cognome'] . ' ' . $op['nome']) ?>
                                                    </div>
                                                    <div class="inactive-email">
                                                        <?= htmlspecialchars($op['email']) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="text-align: center; margin-top: 1rem;">
                                            <p style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?= count($operatoriInattivi) ?> operatori senza sessioni
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Riepilogo Rapido -->
                            <div class="widget" style="margin-top: 1.5rem;">
                                <div class="widget-header">
                                    <h3 class="widget-title">📊 Riepilogo Rapido</h3>
                                </div>
                                <div class="widget-content">
                                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100);">
                                            <span style="color: var(--gray-600); font-size: 0.875rem;">Periodo analisi</span>
                                            <span style="font-weight: 600; font-size: 0.875rem;">
                                                <?= $periodo == '7' ? 'Ultima settimana' : 
                                                    ($periodo == '30' ? 'Ultimo mese' : 
                                                    ($periodo == '90' ? 'Ultimi 3 mesi' : 'Ultimo anno')) ?>
                                            </span>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100);">
                                            <span style="color: var(--gray-600); font-size: 0.875rem;">Tasso attività</span>
                                            <span style="font-weight: 600; font-size: 0.875rem;">
                                                <?= $statsGenerali['operatori_attivi'] > 0 ? 
                                                    round($statsSessioni['operatori_con_sessioni'] / $statsGenerali['operatori_attivi'] * 100) : 0 ?>%
                                            </span>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100);">
                                            <span style="color: var(--gray-600); font-size: 0.875rem;">Media giornaliera</span>
                                            <span style="font-weight: 600; font-size: 0.875rem;">
                                                <?= formatHours($statsSessioni['ore_totali'] / max($periodo, 1)) ?>
                                            </span>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                                            <span style="color: var(--gray-600); font-size: 0.875rem;">Amministratori</span>
                                            <span style="font-weight: 600; font-size: 0.875rem;">
                                                <?= $statsGenerali['amministratori'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Export Actions -->
                            <div class="widget" style="margin-top: 1.5rem;">
                                <div class="widget-header">
                                    <h3 class="widget-title">📥 Esporta Dati</h3>
                                </div>
                                <div class="widget-content">
                                    <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1rem;">
                                        Esporta i dati statistici per analisi esterne
                                    </p>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <button class="btn btn-secondary btn-block" onclick="alert('Funzionalità export in sviluppo')">
                                            📊 Export Excel
                                        </button>
                                        <button class="btn btn-secondary btn-block" onclick="alert('Funzionalità export in sviluppo')">
                                            📄 Report PDF
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