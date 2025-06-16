<?php
/**
 * modules/operatori/view.php - Visualizzazione Operatore CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON ROUTER
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Recupera ID operatore da visualizzare (già validato dal router)
$operatoreId = $_GET['id'];

// Recupera dati operatore
$operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
if (!$operatore) {
    header('Location: /crm/?action=operatori&error=not_found');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Controllo permessi: admin o auto-view
$canView = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
$isAdminView = $sessionInfo['is_admin'] && $sessionInfo['operatore_id'] != $operatoreId;
$isSelfView = $sessionInfo['operatore_id'] == $operatoreId;

if (!$canView) {
    header('Location: /crm/?action=operatori&error=permissions');
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
    ", [$operatoreId]) ?: [
        'sessioni_totali' => 0,
        'ore_totali' => 0,
        'media_ore_sessione' => 0,
        'sessioni_ultimo_mese' => 0
    ];
    
    // Statistiche per questa settimana
    $oreSettimana = $db->selectOne("
        SELECT COALESCE(SUM(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE 0
            END
        ), 0) as ore
        FROM sessioni_lavoro 
        WHERE operatore_id = ? 
        AND YEARWEEK(login_timestamp, 1) = YEARWEEK(CURDATE(), 1)
    ", [$operatoreId])['ore'] ?? 0;
    
    // Statistiche per questo mese
    $oreMese = $db->selectOne("
        SELECT COALESCE(SUM(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE 0
            END
        ), 0) as ore
        FROM sessioni_lavoro 
        WHERE operatore_id = ? 
        AND YEAR(login_timestamp) = YEAR(CURDATE())
        AND MONTH(login_timestamp) = MONTH(CURDATE())
    ", [$operatoreId])['ore'] ?? 0;
    
    // Ultime sessioni di lavoro
    $ultimeSessioni = $db->select("
        SELECT 
            login_timestamp,
            logout_timestamp,
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE NULL
            END as durata_ore,
            ip_address
        FROM sessioni_lavoro 
        WHERE operatore_id = ?
        ORDER BY login_timestamp DESC
        LIMIT 10
    ", [$operatoreId]);
    
    // Statistiche clienti (se operatore responsabile)
    $statsClienti = $db->selectOne("
        SELECT 
            COUNT(*) as clienti_totali,
            COUNT(CASE WHEN is_attivo = 1 THEN 1 END) as clienti_attivi
        FROM clienti 
        WHERE operatore_responsabile_id = ?
    ", [$operatoreId]) ?: ['clienti_totali' => 0, 'clienti_attivi' => 0];
    
} catch (Exception $e) {
    error_log("Errore caricamento statistiche operatore: " . $e->getMessage());
    $statsLavoro = ['sessioni_totali' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0, 'sessioni_ultimo_mese' => 0];
    $oreSettimana = 0;
    $oreMese = 0;
    $ultimeSessioni = [];
    $statsClienti = ['clienti_totali' => 0, 'clienti_attivi' => 0];
}

// Decodifica qualifiche
$qualifiche = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];

// Funzioni helper
function formatDuration($hours) {
    if (!$hours) return '-';
    
    $h = floor($hours);
    $m = round(($hours - $h) * 60);
    
    return sprintf('%dh %02dm', $h, $m);
}

function formatSessionTime($timestamp) {
    if (!$timestamp) return '-';
    
    $date = new DateTime($timestamp);
    $now = new DateTime();
    
    if ($date->format('Y-m-d') == $now->format('Y-m-d')) {
        return 'Oggi alle ' . $date->format('H:i');
    } elseif ($date->format('Y-m-d') == $now->modify('-1 day')->format('Y-m-d')) {
        return 'Ieri alle ' . $date->format('H:i');
    } else {
        return $date->format('d/m/Y H:i');
    }
}

// Calcola stato attività
$isOnline = false;
$lastActivity = null;

if (!empty($ultimeSessioni)) {
    $lastSession = $ultimeSessioni[0];
    if (!$lastSession['logout_timestamp']) {
        $isOnline = true;
    }
    $lastActivity = $lastSession['login_timestamp'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($operatore['nome'] . ' ' . $operatore['cognome']) ?> - CRM Re.De Consulting</title>
    
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
        .view-container {
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
        
        /* Header Operatore */
        .operator-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .operator-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .operator-main {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .operator-avatar-large {
            width: 80px;
            height: 80px;
            background: var(--primary-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .operator-details h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .operator-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-online {
            background: #dbeafe;
            color: #2563eb;
        }
        
        /* Actions */
        .operator-actions {
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
        
        .btn-primary {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        
        .btn-primary:hover {
            background: #16406e;
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
        
        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        /* Dashboard Content Grid */
        .dashboard-content {
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
        
        /* Info Sections */
        .info-section {
            margin-bottom: 1.5rem;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
        }
        
        .info-section h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .info-value {
            font-size: 0.875rem;
            color: var(--gray-900);
            font-weight: 500;
        }
        
        /* Qualifiche List */
        .qualifiche-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .qualifica-badge {
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border-radius: var(--radius-md);
            font-size: 0.8125rem;
        }
        
        /* Sessions Table */
        .sessions-table {
            font-size: 0.8125rem;
        }
        
        .session-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .session-row:last-child {
            border-bottom: none;
        }
        
        .session-time {
            color: var(--gray-900);
        }
        
        .session-duration {
            color: var(--gray-600);
        }
        
        .session-status {
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .session-status.active {
            background: #dbeafe;
            color: #2563eb;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
        }
        
        .empty-state-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.5;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            gap: 0.75rem;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--gray-700);
            transition: all var(--transition-fast);
        }
        
        .quick-action:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }
        
        .quick-action-icon {
            width: 32px;
            height: 32px;
            background: white;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .operator-info {
                flex-direction: column;
                gap: 1rem;
            }
            
            .operator-main {
                flex-direction: column;
                text-align: center;
            }
            
            .operator-actions {
                width: 100%;
                justify-content: center;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="view-container">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span><?= htmlspecialchars($operatore['nome'] . ' ' . $operatore['cognome']) ?></span>
        </div>

        <!-- Header Operatore -->
        <div class="operator-header">
            <div class="operator-info">
                <div class="operator-main">
                    <!-- Avatar -->
                    <div class="operator-avatar-large">
                        <?= strtoupper(substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1)) ?>
                    </div>
                    
                    <!-- Dettagli -->
                    <div class="operator-details">
                        <h1><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></h1>
                        <div class="operator-meta">
                            <div class="meta-item">
                                <span>📧</span>
                                <span><?= htmlspecialchars($operatore['email']) ?></span>
                            </div>
                            <?php if ($operatore['telefono']): ?>
                                <div class="meta-item">
                                    <span>📞</span>
                                    <span><?= htmlspecialchars($operatore['telefono']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="meta-item">
                                <span>🆔</span>
                                <span><?= htmlspecialchars($operatore['codice_operatore']) ?></span>
                            </div>
                            <?php if ($operatore['is_amministratore']): ?>
                                <div class="meta-item">
                                    <span>👑</span>
                                    <span>Amministratore</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: right;">
                    <!-- Status -->
                    <div class="status-badge <?= $operatore['is_attivo'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                    </div>
                    
                    <?php if ($isOnline): ?>
                        <div class="status-badge status-online" style="margin-top: 0.5rem;">
                            🟢 Online ora
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="operator-actions">
                <?php if ($canView): ?>
                    <a href="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>" class="btn btn-primary">
                        ✏️ Modifica
                    </a>
                <?php endif; ?>
                
                <a href="/crm/?action=operatori" class="btn btn-secondary">
                    ← Torna alla Lista
                </a>
                
                <a href="/crm/?action=dashboard" class="btn btn-secondary">
                    🏠 Dashboard
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
            
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-value"><?= $statsClienti['clienti_attivi'] ?>/<?= $statsClienti['clienti_totali'] ?></div>
                <div class="stat-label">Clienti Gestiti</div>
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
                            <?php if ($operatore['telefono']): ?>
                                <div class="info-item">
                                    <span class="info-label">Telefono</span>
                                    <span class="info-value"><?= htmlspecialchars($operatore['telefono']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Informazioni Contrattuali -->
                        <div class="info-section">
                            <h4>📄 Informazioni Contrattuali</h4>
                            <div class="info-item">
                                <span class="info-label">Tipo Contratto</span>
                                <span class="info-value">
                                    <?php
                                    $tipiContratto = [
                                        'indeterminato' => 'Tempo Indeterminato',
                                        'determinato' => 'Tempo Determinato',
                                        'partita_iva' => 'Partita IVA',
                                        'apprendistato' => 'Apprendistato',
                                        'stage' => 'Stage/Tirocinio'
                                    ];
                                    echo $tipiContratto[$operatore['tipo_contratto']] ?? 'Non specificato';
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Data Registrazione</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($operatore['created_at'])) ?></span>
                            </div>
                        </div>
                        
                        <!-- Qualifiche -->
                        <?php if (!empty($qualifiche)): ?>
                            <div class="info-section">
                                <h4>🎯 Qualifiche e Competenze</h4>
                                <div class="qualifiche-list">
                                    <?php foreach ($qualifiche as $qualifica): ?>
                                        <span class="qualifica-badge"><?= htmlspecialchars($qualifica) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Orari di Lavoro -->
                        <div class="info-section">
                            <h4>🕐 Orari di Lavoro</h4>
                            <?php if ($operatore['orario_continuato_inizio'] && $operatore['orario_continuato_fine']): ?>
                                <div class="info-item">
                                    <span class="info-label">Orario Continuato</span>
                                    <span class="info-value">
                                        <?= substr($operatore['orario_continuato_inizio'], 0, 5) ?> - <?= substr($operatore['orario_continuato_fine'], 0, 5) ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <?php if ($operatore['orario_mattino_inizio'] && $operatore['orario_mattino_fine']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Mattino</span>
                                        <span class="info-value">
                                            <?= substr($operatore['orario_mattino_inizio'], 0, 5) ?> - <?= substr($operatore['orario_mattino_fine'], 0, 5) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($operatore['orario_pomeriggio_inizio'] && $operatore['orario_pomeriggio_fine']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Pomeriggio</span>
                                        <span class="info-value">
                                            <?= substr($operatore['orario_pomeriggio_inizio'], 0, 5) ?> - <?= substr($operatore['orario_pomeriggio_fine'], 0, 5) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Ultime Sessioni -->
                <div class="widget" style="margin-top: 1.5rem;">
                    <div class="widget-header">
                        <h3 class="widget-title">🕐 Ultime Sessioni di Lavoro</h3>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($ultimeSessioni)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📅</div>
                                <p>Nessuna sessione registrata</p>
                            </div>
                        <?php else: ?>
                            <div class="sessions-table">
                                <?php foreach ($ultimeSessioni as $sessione): ?>
                                    <div class="session-row">
                                        <div class="session-time">
                                            <?= formatSessionTime($sessione['login_timestamp']) ?>
                                        </div>
                                        <div class="session-duration">
                                            <?= $sessione['durata_ore'] ? formatDuration($sessione['durata_ore']) : '-' ?>
                                        </div>
                                        <div class="session-status <?= !$sessione['logout_timestamp'] ? 'active' : '' ?>">
                                            <?= !$sessione['logout_timestamp'] ? 'In corso' : 'Completata' ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Colonna Laterale -->
            <div>
                <!-- Azioni Rapide -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">⚡ Azioni Rapide</h3>
                    </div>
                    <div class="widget-content">
                        <div class="quick-actions">
                            <?php if ($operatore['email']): ?>
                                <a href="mailto:<?= htmlspecialchars($operatore['email']) ?>" class="quick-action">
                                    <div class="quick-action-icon">📧</div>
                                    <div>
                                        <div style="font-weight: 500;">Invia Email</div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?= htmlspecialchars($operatore['email']) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($operatore['telefono']): ?>
                                <a href="tel:<?= htmlspecialchars($operatore['telefono']) ?>" class="quick-action">
                                    <div class="quick-action-icon">📞</div>
                                    <div>
                                        <div style="font-weight: 500;">Chiama</div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?= htmlspecialchars($operatore['telefono']) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($sessionInfo['is_admin']): ?>
                                <a href="/crm/?action=clienti&operatore=<?= $operatoreId ?>" class="quick-action">
                                    <div class="quick-action-icon">🏢</div>
                                    <div>
                                        <div style="font-weight: 500;">Vedi Clienti</div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?= $statsClienti['clienti_totali'] ?> clienti gestiti
                                        </div>
                                    </div>
                                </a>
                                
                                <a href="/crm/?action=operatori&view=stats" class="quick-action">
                                    <div class="quick-action-icon">📊</div>
                                    <div>
                                        <div style="font-weight: 500;">Statistiche Team</div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">
                                            Confronta performance
                                        </div>
                                    </div>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Info Sistema -->
                <div class="widget" style="margin-top: 1.5rem;">
                    <div class="widget-header">
                        <h3 class="widget-title">🔧 Informazioni Sistema</h3>
                    </div>
                    <div class="widget-content">
                        <div class="info-section">
                            <div class="info-item">
                                <span class="info-label">Codice Operatore</span>
                                <span class="info-value"><?= htmlspecialchars($operatore['codice_operatore']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ID Sistema</span>
                                <span class="info-value">#<?= $operatore['id'] ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Ruolo</span>
                                <span class="info-value">
                                    <?= $operatore['is_amministratore'] ? '👑 Amministratore' : '👤 Operatore' ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Stato Account</span>
                                <span class="info-value">
                                    <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Disattivato' ?>
                                </span>
                            </div>
                            <?php if ($lastActivity): ?>
                                <div class="info-item">
                                    <span class="info-label">Ultima Attività</span>
                                    <span class="info-value"><?= formatSessionTime($lastActivity) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>