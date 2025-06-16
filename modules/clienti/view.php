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

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message
// $operatoreId (validato dal router)

$pageTitle = 'Dettagli Operatore';

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
    
    // Ore lavorate questa settimana
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
        AND YEARWEEK(login_timestamp) = YEARWEEK(CURDATE())
    ", [$operatoreId])['ore'] ?? 0;
    
    // Ore lavorate questo mese
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
        AND MONTH(login_timestamp) = MONTH(CURDATE())
        AND YEAR(login_timestamp) = YEAR(CURDATE())
    ", [$operatoreId])['ore'] ?? 0;
    
    // Ultima sessione
    $ultimaSessione = $db->selectOne("
        SELECT login_timestamp, logout_timestamp
        FROM sessioni_lavoro 
        WHERE operatore_id = ?
        ORDER BY login_timestamp DESC
        LIMIT 1
    ", [$operatoreId]);
    
    // Sessione attiva
    $sessioneAttiva = $db->selectOne("
        SELECT login_timestamp
        FROM sessioni_lavoro 
        WHERE operatore_id = ? AND logout_timestamp IS NULL
        ORDER BY login_timestamp DESC
        LIMIT 1
    ", [$operatoreId]);
    
} catch (Exception $e) {
    error_log("Errore calcolo statistiche operatore: " . $e->getMessage());
    $statsLavoro = ['sessioni_totali' => 0, 'ore_totali' => 0, 'media_ore_sessione' => 0, 'sessioni_ultimo_mese' => 0];
    $oreSettimana = 0;
    $oreMese = 0;
    $ultimaSessione = null;
    $sessioneAttiva = null;
}

// Decode qualifiche
$qualifiche = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];

// Funzioni helper
function formatOrario($time) {
    return $time ? date('H:i', strtotime($time)) : '-';
}

function getOrariLavoro($operatore) {
    $orari = [];
    
    if ($operatore['orario_mattino_inizio'] && $operatore['orario_mattino_fine']) {
        $orari[] = formatOrario($operatore['orario_mattino_inizio']) . ' - ' . formatOrario($operatore['orario_mattino_fine']);
    }
    
    if ($operatore['orario_pomeriggio_inizio'] && $operatore['orario_pomeriggio_fine']) {
        $orari[] = formatOrario($operatore['orario_pomeriggio_inizio']) . ' - ' . formatOrario($operatore['orario_pomeriggio_fine']);
    }
    
    return !empty($orari) ? implode(' / ', $orari) : 'Non definiti';
}

function getTipoContratto($tipo) {
    $tipi = [
        'full_time' => 'Full Time',
        'part_time' => 'Part Time',
        'collaborazione' => 'Collaborazione'
    ];
    return $tipi[$tipo] ?? 'Non specificato';
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
        .view-container {
            max-width: 1200px;
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
        
        /* Header Profile */
        .profile-header {
            background: white;
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }
        
        .profile-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .profile-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .profile-avatar {
            width: 64px;
            height: 64px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .profile-details h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .profile-meta {
            display: flex;
            gap: 1rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 100px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #D4EDDA;
            color: #155724;
        }
        
        .status-inactive {
            background: #F8D7DA;
            color: #721C24;
        }
        
        /* Actions */
        .profile-actions {
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
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary-green);
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
        
        /* Stats Overview */
        .stats-overview {
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
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
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
            color: var(--gray-800);
            font-weight: 500;
        }
        
        /* Qualifiche */
        .qualifiche-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .qualifica-badge {
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            border-radius: 100px;
            font-size: 0.8125rem;
            color: var(--gray-700);
        }
        
        /* Session Status */
        .session-status {
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-indicator.online {
            background: var(--primary-green);
        }
        
        .status-indicator.offline {
            background: var(--gray-400);
            animation: none;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gray-200);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--gray-400);
            border: 2px solid white;
        }
        
        .timeline-date {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }
        
        .timeline-content {
            font-size: 0.875rem;
            color: var(--gray-700);
        }
        
        /* Messages */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .profile-top {
                flex-direction: column;
                gap: 1rem;
            }
            
            .profile-actions {
                width: 100%;
            }
            
            .profile-actions .btn {
                flex: 1;
                justify-content: center;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="view-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></span>
        </div>
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-top">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1)) ?>
                    </div>
                    <div class="profile-details">
                        <h1><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></h1>
                        <div class="profile-meta">
                            <span>📧 <?= htmlspecialchars($operatore['email']) ?></span>
                            <span>👤 <?= $operatore['is_amministratore'] ? 'Amministratore' : 'Operatore' ?></span>
                            <span>📅 Dal <?= date('d/m/Y', strtotime($operatore['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="status-badge <?= $operatore['is_attivo'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-actions">
                <?php if ($canView): ?>
                    <a href="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>" class="btn btn-primary">
                        ✏️ Modifica
                    </a>
                <?php endif; ?>
                
                <a href="/crm/?action=operatori" class="btn btn-secondary">
                    ← Torna alla Lista
                </a>
                
                <a href="/crm/?action=dashboard" class="btn btn-outline">
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
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
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
                                <span class="info-label">Ruolo</span>
                                <span class="info-value"><?= $operatore['is_amministratore'] ? 'Amministratore' : 'Operatore' ?></span>
                            </div>
                        </div>
                        
                        <!-- Informazioni Contrattuali -->
                        <div class="info-section">
                            <h4>📝 Informazioni Contrattuali</h4>
                            <div class="info-item">
                                <span class="info-label">Tipo Contratto</span>
                                <span class="info-value"><?= getTipoContratto($operatore['tipo_contratto']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Orari di Lavoro</span>
                                <span class="info-value"><?= getOrariLavoro($operatore) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Data Registrazione</span>
                                <span class="info-value"><?= date('d/m/Y H:i', strtotime($operatore['created_at'])) ?></span>
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
                    </div>
                </div>
            </div>
            
            <!-- Colonna Laterale -->
            <div>
                <!-- Stato Sessione -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">🔄 Stato Attuale</h3>
                    </div>
                    <div class="widget-content">
                        <div class="session-status">
                            <?php if ($sessioneAttiva): ?>
                                <div class="status-indicator online"></div>
                                <div>
                                    <strong>Online</strong><br>
                                    <small>Dal <?= date('H:i', strtotime($sessioneAttiva['login_timestamp'])) ?></small>
                                </div>
                            <?php else: ?>
                                <div class="status-indicator offline"></div>
                                <div>
                                    <strong>Offline</strong><br>
                                    <small>
                                        <?php if ($ultimaSessione): ?>
                                            Ultimo accesso: <?= date('d/m/Y H:i', strtotime($ultimaSessione['login_timestamp'])) ?>
                                        <?php else: ?>
                                            Mai effettuato accesso
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiche Rapide -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">📊 Statistiche Rapide</h3>
                    </div>
                    <div class="widget-content">
                        <div class="info-item">
                            <span class="info-label">Media ore/sessione</span>
                            <span class="info-value"><?= number_format($statsLavoro['media_ore_sessione'], 1) ?>h</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Sessioni ultimo mese</span>
                            <span class="info-value"><?= $statsLavoro['sessioni_ultimo_mese'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>