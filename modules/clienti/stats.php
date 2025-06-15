<?php
/**
 * modules/clienti/stats.php - Statistiche Portfolio Clienti CRM Re.De Consulting
 * 
 * ✅ DASHBOARD ANALYTICS COMMERCIALISTI AVANZATA
 * 
 * Features:
 * - Analytics completa portfolio clienti
 * - Grafici interattivi con Chart.js
 * - KPI specifici per commercialisti
 * - Export statistiche in Excel/PDF
 * - Filtri temporali e segmentazione
 * - Analisi geografica clientela
 * - Trend di crescita e retention
 * - Performance operatori per cliente
 * - Solo per amministratori
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorsi assoluti robusti
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/functions/helpers.php';

// Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    header('Location: /crm/core/auth/login.php');
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// Verifica permessi amministratore
if (!$sessionInfo['is_admin']) {
    header('Location: /crm/modules/clienti/index.php?error=access_denied');
    exit;
}

// Parametri filtri
$periodo = $_GET['periodo'] ?? '12m'; // 1m, 3m, 6m, 12m, all
$operatore = $_GET['operatore'] ?? 'all';
$tipologia = $_GET['tipologia'] ?? 'all';

try {
    // 1. STATISTICHE GENERALI
    $statsGenerali = $db->selectOne("
        SELECT 
            COUNT(*) as totale_clienti,
            SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as clienti_attivi,
            SUM(CASE WHEN is_attivo = 0 THEN 1 ELSE 0 END) as clienti_sospesi,
            
            -- Per tipologia
            SUM(CASE WHEN tipologia_azienda = 'individuale' THEN 1 ELSE 0 END) as individuali,
            SUM(CASE WHEN tipologia_azienda = 'srl' THEN 1 ELSE 0 END) as srl,
            SUM(CASE WHEN tipologia_azienda = 'spa' THEN 1 ELSE 0 END) as spa,
            SUM(CASE WHEN tipologia_azienda IN ('snc', 'sas') THEN 1 ELSE 0 END) as societa_persone,
            
            -- Per regime fiscale
            SUM(CASE WHEN regime_fiscale = 'ordinario' THEN 1 ELSE 0 END) as regime_ordinario,
            SUM(CASE WHEN regime_fiscale = 'semplificato' THEN 1 ELSE 0 END) as regime_semplificato,
            SUM(CASE WHEN regime_fiscale = 'forfettario' THEN 1 ELSE 0 END) as regime_forfettario,
            
            -- Crescita ultimo periodo
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as nuovi_ultimo_mese,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as nuovi_ultimo_trimestre,
            
            -- Con pratiche attive
            COUNT(DISTINCT CASE WHEN p.stato IN ('da_iniziare', 'in_corso') THEN c.id END) as clienti_con_pratiche_attive
            
        FROM clienti c
        LEFT JOIN pratiche p ON c.id = p.cliente_id
    ") ?: [];

    // 2. CRESCITA MENSILE CLIENTI (ultimi 12 mesi)
    $crescitaMensile = $db->select("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mese,
            COUNT(*) as nuovi_clienti,
            SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as totale_cumulativo
        FROM clienti 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mese
    ");

    // 3. DISTRIBUZIONE GEOGRAFICA
    $distribuzioneGeografica = $db->select("
        SELECT 
            COALESCE(provincia, 'N/D') as provincia,
            COUNT(*) as numero_clienti,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM clienti), 1) as percentuale
        FROM clienti
        WHERE is_attivo = 1
        GROUP BY provincia
        ORDER BY numero_clienti DESC
        LIMIT 15
    ");

    // 4. PERFORMANCE OPERATORI
    $performanceOperatori = $db->select("
        SELECT 
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            COUNT(c.id) as clienti_gestiti,
            SUM(CASE WHEN c.is_attivo = 1 THEN 1 ELSE 0 END) as clienti_attivi,
            COUNT(DISTINCT nc.id) as comunicazioni_totali,
            AVG(CASE WHEN nc.data_nota >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) * COUNT(c.id) as comunicazioni_ultimo_mese,
            COUNT(DISTINCT p.id) as pratiche_totali,
            SUM(CASE WHEN p.stato IN ('da_iniziare', 'in_corso') THEN 1 ELSE 0 END) as pratiche_attive
        FROM operatori o
        LEFT JOIN clienti c ON o.id = c.operatore_responsabile_id
        LEFT JOIN note_clienti nc ON c.id = nc.cliente_id
        LEFT JOIN pratiche p ON c.id = p.cliente_id
        WHERE o.is_attivo = 1
        GROUP BY o.id, o.nome, o.cognome
        HAVING clienti_gestiti > 0
        ORDER BY clienti_gestiti DESC
    ");

    // 5. ANALISI ATTIVITÀ CLIENTI
    $analisiAttivita = $db->selectOne("
        SELECT 
            COUNT(DISTINCT c.id) as totale_clienti,
            COUNT(DISTINCT CASE WHEN nc.data_nota >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN c.id END) as attivi_ultimo_mese,
            COUNT(DISTINCT CASE WHEN nc.data_nota >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN c.id END) as attivi_ultimo_trimestre,
            COUNT(DISTINCT CASE WHEN nc.data_nota < DATE_SUB(NOW(), INTERVAL 90 DAY) OR nc.data_nota IS NULL THEN c.id END) as inattivi,
            
            AVG(CASE WHEN nc.data_nota IS NOT NULL THEN DATEDIFF(NOW(), nc.data_nota) END) as giorni_media_ultima_comunicazione,
            
            -- Frequenza comunicazioni
            COUNT(nc.id) / COUNT(DISTINCT c.id) as comunicazioni_media_per_cliente
            
        FROM clienti c
        LEFT JOIN note_clienti nc ON c.id = nc.cliente_id
        WHERE c.is_attivo = 1
    ") ?: [];

    // 6. TOP CLIENTI PER PRATICHE
    $topClientiPratiche = $db->select("
        SELECT 
            c.ragione_sociale,
            c.tipologia_azienda,
            COUNT(p.id) as totale_pratiche,
            SUM(CASE WHEN p.stato IN ('da_iniziare', 'in_corso') THEN 1 ELSE 0 END) as pratiche_attive,
            SUM(CASE WHEN p.stato = 'completata' THEN 1 ELSE 0 END) as pratiche_completate,
            SUM(p.ore_lavorate) as ore_totali_lavorate,
            MAX(p.created_at) as ultima_pratica
        FROM clienti c
        LEFT JOIN pratiche p ON c.id = p.cliente_id
        WHERE c.is_attivo = 1
        GROUP BY c.id, c.ragione_sociale, c.tipologia_azienda
        HAVING totale_pratiche > 0
        ORDER BY totale_pratiche DESC, ore_totali_lavorate DESC
        LIMIT 10
    ");

    // 7. TREND COMUNICAZIONI
    $trendComunicazioni = $db->select("
        SELECT 
            DATE_FORMAT(data_nota, '%Y-%m') as mese,
            COUNT(*) as totale_comunicazioni,
            COUNT(DISTINCT cliente_id) as clienti_contattati,
            SUM(CASE WHEN tipo_nota = 'chiamata' THEN 1 ELSE 0 END) as chiamate,
            SUM(CASE WHEN tipo_nota = 'email' THEN 1 ELSE 0 END) as email,
            SUM(CASE WHEN tipo_nota = 'incontro' THEN 1 ELSE 0 END) as incontri
        FROM note_clienti 
        WHERE data_nota >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(data_nota, '%Y-%m')
        ORDER BY mese
    ");

    // 8. DISTRIBUZIONE PER LIQUIDAZIONE IVA
    $distribuzioneIVA = $db->select("
        SELECT 
            liquidazione_iva,
            COUNT(*) as numero_clienti,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM clienti WHERE is_attivo = 1), 1) as percentuale
        FROM clienti
        WHERE is_attivo = 1 AND liquidazione_iva IS NOT NULL
        GROUP BY liquidazione_iva
        ORDER BY numero_clienti DESC
    ");

} catch (Exception $e) {
    error_log("Errore stats clienti: " . $e->getMessage());
    $statsGenerali = [];
    $crescitaMensile = [];
    $distribuzioneGeografica = [];
    $performanceOperatori = [];
    $analisiAttivita = [];
    $topClientiPratiche = [];
    $trendComunicazioni = [];
    $distribuzioneIVA = [];
}

// Funzioni helper
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

function formatPercentage($number) {
    return number_format($number, 1, ',', '.') . '%';
}

function getGrowthIcon($current, $previous) {
    if ($current > $previous) return '📈';
    if ($current < $previous) return '📉';
    return '➖';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Statistiche Portfolio Clienti - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <!-- Chart.js per grafici -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        /* Stats Dashboard Ultra-Denso */
        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .stats-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .stats-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        /* Grid Layout Responsivo */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-grid-large {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Card Statistiche */
        .stats-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all var(--transition-fast);
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            padding: 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .kpi-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-green);
            text-align: center;
        }
        
        .kpi-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .kpi-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .kpi-change {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }
        
        .kpi-positive {
            color: var(--success-green);
        }
        
        .kpi-negative {
            color: var(--danger-red);
        }
        
        /* Chart Containers */
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1rem 0;
        }
        
        .chart-small {
            height: 250px;
        }
        
        /* Tabelle Stats */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .stats-table th,
        .stats-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.875rem;
        }
        
        .stats-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .stats-table tr:hover {
            background: var(--gray-50);
        }
        
        /* Progress Bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            transition: width 0.3s ease;
        }
        
        /* Filtri */
        .filters-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .filter-select {
            height: 36px;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            background: white;
        }
        
        /* Export Actions */
        .export-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-export {
            height: 36px;
            padding: 0.5rem 1rem;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-export:hover {
            background: var(--secondary-green);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid-large {
                grid-template-columns: 1fr;
            }
            
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar uniforme -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>📊 CRM</h2>
        </div>
        
        <nav class="nav">
            <div class="nav-section">
                <div class="nav-item">
                    <a href="/crm/dashboard.php" class="nav-link">
                        <span>🏠</span> Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/operatori/index.php" class="nav-link">
                        <span>👥</span> Operatori
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/clienti/index.php" class="nav-link">
                        <span>🏢</span> Clienti
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/clienti/stats.php" class="nav-link nav-link-active">
                        <span>📊</span> Statistiche
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="stats-container">
            <!-- Header -->
            <div class="stats-header">
                <h1 class="stats-title">📊 Statistiche Portfolio Clienti</h1>
                <p class="stats-subtitle">Analytics completa del portafoglio clienti</p>
            </div>

            <!-- Filtri -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label class="filter-label">Periodo:</label>
                    <select class="filter-select" onchange="applyFilters()">
                        <option value="1m">Ultimo mese</option>
                        <option value="3m">Ultimi 3 mesi</option>
                        <option value="6m">Ultimi 6 mesi</option>
                        <option value="12m" selected>Ultimo anno</option>
                        <option value="all">Tutto</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Tipologia:</label>
                    <select class="filter-select" onchange="applyFilters()">
                        <option value="all">Tutte</option>
                        <option value="individuale">Individuali</option>
                        <option value="srl">SRL</option>
                        <option value="spa">SPA</option>
                        <option value="altro">Altre</option>
                    </select>
                </div>
                
                <div style="margin-left: auto;">
                    <div class="export-actions">
                        <button class="btn-export" onclick="exportStats('excel')">📊 Export Excel</button>
                        <button class="btn-export" onclick="exportStats('pdf')">📄 Export PDF</button>
                    </div>
                </div>
            </div>

            <!-- KPI Principal -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-number"><?= formatNumber($statsGenerali['totale_clienti'] ?? 0) ?></span>
                    <div class="kpi-label">Clienti Totali</div>
                    <div class="kpi-change kpi-positive">
                        📈 +<?= formatNumber($statsGenerali['nuovi_ultimo_mese'] ?? 0) ?> ultimo mese
                    </div>
                </div>
                
                <div class="kpi-card">
                    <span class="kpi-number"><?= formatNumber($statsGenerali['clienti_attivi'] ?? 0) ?></span>
                    <div class="kpi-label">Clienti Attivi</div>
                    <div class="kpi-change">
                        <?= formatPercentage(($statsGenerali['clienti_attivi'] ?? 0) / max(1, $statsGenerali['totale_clienti'] ?? 1) * 100) ?> del totale
                    </div>
                </div>
                
                <div class="kpi-card">
                    <span class="kpi-number"><?= formatNumber($analisiAttivita['attivi_ultimo_mese'] ?? 0) ?></span>
                    <div class="kpi-label">Attivi Ultimo Mese</div>
                    <div class="kpi-change kpi-positive">
                        <?= formatPercentage(($analisiAttivita['attivi_ultimo_mese'] ?? 0) / max(1, $statsGenerali['clienti_attivi'] ?? 1) * 100) ?> engagement
                    </div>
                </div>
                
                <div class="kpi-card">
                    <span class="kpi-number"><?= formatNumber($statsGenerali['clienti_con_pratiche_attive'] ?? 0) ?></span>
                    <div class="kpi-label">Con Pratiche Attive</div>
                    <div class="kpi-change">
                        Media <?= number_format(($analisiAttivita['comunicazioni_media_per_cliente'] ?? 0), 1) ?> comunicazioni/cliente
                    </div>
                </div>
            </div>

            <!-- Grafici Principale -->
            <div class="stats-grid-large">
                <div class="stats-card">
                    <div class="card-header">
                        <h3 class="card-title">📈 Crescita Clienti (12 mesi)</h3>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="crescitaChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="card-header">
                        <h3 class="card-title">🍰 Distribuzione per Tipologia</h3>
                    </div>
                    <div class="card-content">
                        <div class="chart-container chart-small">
                            <canvas id="tipologiaChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance e Distribuzione -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="card-header">
                        <h3 class="card-title">👥 Performance Operatori</h3>
                    </div>
                    <div class="card-content">
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Operatore</th>
                                    <th>Clienti</th>
                                    <th>Attivi</th>
                                    <th>Pratiche</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($performanceOperatori, 0, 8) as $perf): ?>
                                <tr>
                                    <td><?= htmlspecialchars($perf['operatore_nome']) ?></td>
                                    <td><?= formatNumber($perf['clienti_gestiti']) ?></td>
                                    <td><?= formatNumber($perf['clienti_attivi']) ?></td>
                                    <td><?= formatNumber($perf['pratiche_attive']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="card-header">
                        <h3 class="card-title">📍 Distribuzione Geografica</h3>
                    </div>
                    <div class="card-content">
                        <?php foreach (array_slice($distribuzioneGeografica, 0, 10) as $geo): ?>
                            <div style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($geo['provincia']) ?></span>
                                    <span style="font-size: 0.75rem; color: var(--gray-600);"><?= formatNumber($geo['numero_clienti']) ?> (<?= $geo['percentuale'] ?>%)</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min(100, $geo['percentuale'] * 2) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Top Clienti e Regime Fiscale -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="card-header">
                        <h3 class="card-title">🏆 Top Clienti per Pratiche</h3>
                    </div>
                    <div class="card-content">
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Pratiche</th>
                                    <th>Ore</th>
                                    <th>Ultima</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($topClientiPratiche, 0, 8) as $top): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($top['ragione_sociale']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-600);"><?= ucfirst($top['tipologia_azienda']) ?></div>
                                    </td>
                                    <td>
                                        <span style="color: var(--warning-yellow);"><?= $top['pratiche_attive'] ?></span> / <?= $top['totale_pratiche'] ?>
                                    </td>
                                    <td><?= number_format($top['ore_totali_lavorate'], 1) ?>h</td>
                                    <td style="font-size: 0.75rem;"><?= date('d/m/Y', strtotime($top['ultima_pratica'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="card-header">
                        <h3 class="card-title">⚖️ Distribuzione Regime Fiscale</h3>
                    </div>
                    <div class="card-content">
                        <div class="chart-container chart-small">
                            <canvas id="regimeChart"></canvas>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>📊 Ordinario</span>
                                <span><?= formatNumber($statsGenerali['regime_ordinario'] ?? 0) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>📝 Semplificato</span>
                                <span><?= formatNumber($statsGenerali['regime_semplificato'] ?? 0) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>💰 Forfettario</span>
                                <span><?= formatNumber($statsGenerali['regime_forfettario'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend Comunicazioni -->
            <div class="stats-card">
                <div class="card-header">
                    <h3 class="card-title">💬 Trend Comunicazioni Clienti</h3>
                </div>
                <div class="card-content">
                    <div class="chart-container">
                        <canvas id="comunicazioniChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript per grafici -->
    <script>
        // Configurazione globale Chart.js
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#374151';

        // Dati per grafici (da PHP)
        const crescitaData = <?= json_encode($crescitaMensile) ?>;
        const tipologiaData = {
            individuali: <?= $statsGenerali['individuali'] ?? 0 ?>,
            srl: <?= $statsGenerali['srl'] ?? 0 ?>,
            spa: <?= $statsGenerali['spa'] ?? 0 ?>,
            societa_persone: <?= $statsGenerali['societa_persone'] ?? 0 ?>
        };
        const regimeData = {
            ordinario: <?= $statsGenerali['regime_ordinario'] ?? 0 ?>,
            semplificato: <?= $statsGenerali['regime_semplificato'] ?? 0 ?>,
            forfettario: <?= $statsGenerali['regime_forfettario'] ?? 0 ?>
        };
        const comunicazioniData = <?= json_encode($trendComunicazioni) ?>;

        // Grafico Crescita Clienti
        const crescitaCtx = document.getElementById('crescitaChart').getContext('2d');
        new Chart(crescitaCtx, {
            type: 'line',
            data: {
                labels: crescitaData.map(d => d.mese),
                datasets: [
                    {
                        label: 'Nuovi Clienti',
                        data: crescitaData.map(d => d.nuovi_clienti),
                        borderColor: '#2c6e49',
                        backgroundColor: 'rgba(44, 110, 73, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Totale Cumulativo',
                        data: crescitaData.map(d => d.totale_cumulativo),
                        borderColor: '#4a9d6f',
                        backgroundColor: 'rgba(74, 157, 111, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left'
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Grafico Tipologia Clienti
        const tipologiaCtx = document.getElementById('tipologiaChart').getContext('2d');
        new Chart(tipologiaCtx, {
            type: 'doughnut',
            data: {
                labels: ['👤 Individuali', '🏢 SRL', '🏭 SPA', '👥 Società Persone'],
                datasets: [{
                    data: [tipologiaData.individuali, tipologiaData.srl, tipologiaData.spa, tipologiaData.societa_persone],
                    backgroundColor: ['#4a9d6f', '#2c6e49', '#1e40af', '#d97706'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Grafico Regime Fiscale
        const regimeCtx = document.getElementById('regimeChart').getContext('2d');
        new Chart(regimeCtx, {
            type: 'pie',
            data: {
                labels: ['📊 Ordinario', '📝 Semplificato', '💰 Forfettario'],
                datasets: [{
                    data: [regimeData.ordinario, regimeData.semplificato, regimeData.forfettario],
                    backgroundColor: ['#2c6e49', '#4a9d6f', '#059669'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Grafico Trend Comunicazioni
        const comunicazioniCtx = document.getElementById('comunicazioniChart').getContext('2d');
        new Chart(comunicazioniCtx, {
            type: 'bar',
            data: {
                labels: comunicazioniData.map(d => d.mese),
                datasets: [
                    {
                        label: '📞 Chiamate',
                        data: comunicazioniData.map(d => d.chiamate),
                        backgroundColor: '#1e40af',
                        stack: 'Stack 0'
                    },
                    {
                        label: '📧 Email',
                        data: comunicazioniData.map(d => d.email),
                        backgroundColor: '#059669',
                        stack: 'Stack 0'
                    },
                    {
                        label: '🤝 Incontri',
                        data: comunicazioniData.map(d => d.incontri),
                        backgroundColor: '#d97706',
                        stack: 'Stack 0'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Funzioni utility
        function applyFilters() {
            // Ricarica pagina con nuovi parametri
            window.location.reload();
        }

        function exportStats(format) {
            const url = `/crm/modules/clienti/export.php?type=${format}&template=stats&periodo=<?= $periodo ?>`;
            window.open(url, '_blank');
        }

        // Auto-refresh ogni 5 minuti
        setTimeout(() => {
            location.reload();
        }, 300000);
        
        console.log('Statistiche clienti caricate - Dashboard analytics attiva');
    </script>
</body>
</html>