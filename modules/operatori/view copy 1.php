<?php
/**
 * modules/operatori/view.php - Visualizzazione Operatore CRM Re.De Consulting
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
$pageTitle = 'Dettagli Operatore';
$pageIcon = '👁️';

// Recupera ID operatore (già validato dal router)
$operatoreId = $_GET['id'];

// **LOGICA ESISTENTE MANTENUTA** - Carica dati operatore con statistiche
$operatore = $db->selectOne("
    SELECT o.*,
        (SELECT COUNT(*) FROM clienti WHERE operatore_responsabile_id = o.id AND is_attivo = 1) as clienti_attivi,
        (SELECT COUNT(*) FROM clienti WHERE operatore_responsabile_id = o.id) as clienti_totali,
        (SELECT MAX(login_timestamp) FROM sessioni_lavoro WHERE operatore_id = o.id) as ultimo_accesso,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM sessioni_lavoro s 
                WHERE s.operatore_id = o.id 
                AND s.logout_timestamp IS NULL 
                AND DATE(s.login_timestamp) = CURDATE()
            ) THEN 1 
            ELSE 0 
        END as is_online
    FROM operatori o 
    WHERE o.id = ?
", [$operatoreId]);

if (!$operatore) {
    header('Location: /crm/?action=operatori&error=not_found');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Controllo permessi visualizzazione
$canView = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
if (!$canView) {
    header('Location: /crm/?action=operatori&error=permissions');
    exit;
}

// Decode qualifiche
$qualifiche = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];

// **LOGICA ESISTENTE MANTENUTA** - Carica statistiche lavoro
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
        COUNT(DISTINCT DATE(login_timestamp)) as giorni_lavorati,
        COALESCE(AVG(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE NULL
            END
        ), 0) as media_ore_sessione
    FROM sessioni_lavoro 
    WHERE operatore_id = ?
", [$operatoreId]) ?: [
    'sessioni_totali' => 0,
    'ore_totali' => 0,
    'giorni_lavorati' => 0,
    'media_ore_sessione' => 0
];

// **LOGICA ESISTENTE MANTENUTA** - Statistiche ultimo mese
$statsMese = $db->selectOne("
    SELECT 
        COUNT(*) as sessioni_mese,
        COALESCE(SUM(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE 0
            END
        ), 0) as ore_mese
    FROM sessioni_lavoro 
    WHERE operatore_id = ? 
    AND DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
", [$operatoreId]) ?: ['sessioni_mese' => 0, 'ore_mese' => 0];

// Statistiche settimana corrente
$statsSettimana = $db->selectOne("
    SELECT 
        COUNT(*) as sessioni_settimana,
        COALESCE(SUM(
            CASE 
                WHEN logout_timestamp IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, login_timestamp, logout_timestamp) / 60.0
                ELSE 0
            END
        ), 0) as ore_settimana
    FROM sessioni_lavoro 
    WHERE operatore_id = ? 
    AND YEARWEEK(login_timestamp) = YEARWEEK(CURDATE())
", [$operatoreId]) ?: ['sessioni_settimana' => 0, 'ore_settimana' => 0];

// **LOGICA ESISTENTE MANTENUTA** - Carica clienti gestiti
$clientiGestiti = $db->select("
    SELECT 
        c.id,
        c.ragione_sociale,
        c.codice_cliente,
        c.tipologia_azienda,
        c.is_attivo,
        c.created_at
    FROM clienti c
    WHERE c.operatore_responsabile_id = ?
    ORDER BY c.is_attivo DESC, c.ragione_sociale ASC
    LIMIT 10
", [$operatoreId]);

// **LOGICA ESISTENTE MANTENUTA** - Ultime sessioni di lavoro
$ultimeSessioni = $db->select("
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
    LIMIT 5
", [$operatoreId]);

// Helper functions
function formatSessionTime($login, $logout = null) {
    $loginDate = new DateTime($login);
    $result = $loginDate->format('d/m/Y H:i');
    
    if ($logout) {
        $logoutDate = new DateTime($logout);
        $result .= ' - ' . $logoutDate->format('H:i');
    } else {
        $result .= ' - In corso';
    }
    
    return $result;
}

function formatHours($hours) {
    return number_format($hours, 1) . 'h';
}

function getTipologiaIcon($tipo) {
    $icons = [
        'srl' => '🏢', 'spa' => '🏭', 'snc' => '👥',
        'sas' => '🤝', 'individuale' => '👤'
    ];
    return $icons[$tipo] ?? '🏢';
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
        .operator-container {
            max-width: 1200px;
            margin: 1rem auto;
            padding: 0 1rem;
        }
        
        /* Header operatore */
        .operator-header {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        
        .operator-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .operator-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .operator-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-green-light);
            color: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .operator-details h1 {
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
            color: var(--gray-900);
        }
        
        .operator-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .meta-item span:first-child {
            font-size: 1rem;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 9999px;
        }
        
        .status-active {
            background: var(--color-success-light);
            color: var(--color-success);
        }
        
        .status-inactive {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .status-online {
            background: var(--color-info-light);
            color: var(--color-info);
        }
        
        /* Actions */
        .operator-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1rem 0;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Stats overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius-md);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin: 0;
        }
        
        /* Dashboard content */
        .dashboard-content {
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
        
        /* Info sections */
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
            margin: 0 0 0.75rem 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--gray-600);
        }
        
        .info-value {
            color: var(--gray-900);
            font-weight: 500;
            text-align: right;
        }
        
        /* Qualifiche */
        .qualifiche-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .qualifica-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: var(--primary-green-light);
            color: var(--primary-green);
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Client list */
        .client-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .client-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            transition: background 0.2s ease;
        }
        
        .client-item:hover {
            background: var(--gray-100);
        }
        
        .client-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .client-name {
            font-weight: 500;
            color: var(--gray-900);
            font-size: 0.875rem;
        }
        
        .client-code {
            color: var(--gray-500);
            font-size: 0.75rem;
        }
        
        .client-status {
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .client-status.active {
            background: var(--color-success-light);
            color: var(--color-success);
        }
        
        .client-status.inactive {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        /* Session list */
        .session-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .session-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            font-size: 0.8125rem;
        }
        
        .session-date {
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .session-duration {
            color: var(--gray-500);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        /* Quick actions */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.2s ease;
        }
        
        .quick-action:hover {
            background: var(--primary-green-light);
            color: var(--primary-green);
            transform: translateX(4px);
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
        
        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .operator-header-top {
                flex-direction: column;
                gap: 1rem;
            }
            
            .operator-info {
                flex-direction: column;
                text-align: center;
            }
            
            .operator-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
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
                <div class="operator-container">
                    <!-- Header Operatore -->
                    <div class="operator-header">
                        <div class="operator-header-top">
                            <div class="operator-info">
                                <div class="operator-avatar-large">
                                    <?= strtoupper(substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1)) ?>
                                </div>
                                
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
                                <span class="status-badge <?= $operatore['is_attivo'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                                </span>
                                
                                <?php if ($operatore['is_online']): ?>
                                    <span class="status-badge status-online" style="margin-top: 0.5rem; display: block;">
                                        🟢 Online ora
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="operator-actions">
                            <?php if ($sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId): ?>
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
                            <div class="stat-value"><?= formatHours($statsLavoro['ore_totali']) ?></div>
                            <div class="stat-label">Ore Lavorate</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📅</div>
                            <div class="stat-value"><?= formatHours($statsSettimana['ore_settimana']) ?></div>
                            <div class="stat-label">Questa Settimana</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📆</div>
                            <div class="stat-value"><?= formatHours($statsMese['ore_mese']) ?></div>
                            <div class="stat-label">Questo Mese</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">🏢</div>
                            <div class="stat-value"><?= $operatore['clienti_attivi'] ?>/<?= $operatore['clienti_totali'] ?></div>
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
                                    
                                    <!-- Contratto e Orari -->
                                    <div class="info-section">
                                        <h4>📅 Contratto e Orari</h4>
                                        <?php if ($operatore['tipo_contratto']): ?>
                                        <div class="info-item">
                                            <span class="info-label">Tipo Contratto</span>
                                            <span class="info-value">
                                                <?= ucfirst(str_replace('_', ' ', $operatore['tipo_contratto'])) ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($operatore['orario_mattino_inizio'] && $operatore['orario_mattino_fine']): ?>
                                        <div class="info-item">
                                            <span class="info-label">Orario Mattino</span>
                                            <span class="info-value">
                                                <?= substr($operatore['orario_mattino_inizio'], 0, 5) ?> - 
                                                <?= substr($operatore['orario_mattino_fine'], 0, 5) ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($operatore['orario_pomeriggio_inizio'] && $operatore['orario_pomeriggio_fine']): ?>
                                        <div class="info-item">
                                            <span class="info-label">Orario Pomeriggio</span>
                                            <span class="info-value">
                                                <?= substr($operatore['orario_pomeriggio_inizio'], 0, 5) ?> - 
                                                <?= substr($operatore['orario_pomeriggio_fine'], 0, 5) ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Qualifiche -->
                                    <?php if (!empty($qualifiche)): ?>
                                    <div class="info-section">
                                        <h4>🎯 Qualifiche e Competenze</h4>
                                        <div class="qualifiche-list">
                                            <?php foreach ($qualifiche as $qualifica): ?>
                                                <span class="qualifica-badge">
                                                    <?= htmlspecialchars($qualifica) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Clienti Gestiti -->
                            <div class="widget" style="margin-top: 1.5rem;">
                                <div class="widget-header">
                                    <h3 class="widget-title">🏢 Clienti Gestiti</h3>
                                </div>
                                <div class="widget-content">
                                    <?php if (empty($clientiGestiti)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">🏢</div>
                                            <p>Nessun cliente assegnato</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="client-list">
                                            <?php foreach ($clientiGestiti as $cliente): ?>
                                                <a href="/crm/?action=clienti&view=view&id=<?= $cliente['id'] ?>" class="client-item">
                                                    <div class="client-info">
                                                        <span><?= getTipologiaIcon($cliente['tipologia_azienda']) ?></span>
                                                        <div>
                                                            <div class="client-name">
                                                                <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                                            </div>
                                                            <div class="client-code">
                                                                <?= htmlspecialchars($cliente['codice_cliente']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="client-status <?= $cliente['is_attivo'] ? 'active' : 'inactive' ?>">
                                                        <?= $cliente['is_attivo'] ? 'Attivo' : 'Inattivo' ?>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php if ($operatore['clienti_totali'] > 10): ?>
                                            <div style="text-align: center; margin-top: 1rem;">
                                                <a href="/crm/?action=clienti&operatore=<?= $operatoreId ?>" class="btn btn-sm btn-secondary">
                                                    Vedi tutti (<?= $operatore['clienti_totali'] ?>)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonna Laterale -->
                        <div>
                            <!-- Ultime Sessioni -->
                            <div class="widget">
                                <div class="widget-header">
                                    <h3 class="widget-title">🕐 Ultime Sessioni</h3>
                                </div>
                                <div class="widget-content">
                                    <?php if (empty($ultimeSessioni)): ?>
                                        <div class="empty-state">
                                            <div class="empty-state-icon">🕐</div>
                                            <p>Nessuna sessione registrata</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="session-list">
                                            <?php foreach ($ultimeSessioni as $sessione): ?>
                                                <div class="session-item">
                                                    <div class="session-date">
                                                        <?= formatSessionTime($sessione['login_timestamp'], $sessione['logout_timestamp']) ?>
                                                    </div>
                                                    <?php if ($sessione['durata_ore']): ?>
                                                        <div class="session-duration">
                                                            Durata: <?= formatHours($sessione['durata_ore']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Azioni Rapide -->
                            <div class="widget" style="margin-top: 1.5rem;">
                                <div class="widget-header">
                                    <h3 class="widget-title">⚡ Azioni Rapide</h3>
                                </div>
                                <div class="widget-content">
                                    <div class="quick-actions">
                                        <?php if ($sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId): ?>
                                            <a href="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>" class="quick-action">
                                                <span>✏️</span>
                                                <div>
                                                    <div style="font-weight: 500;">Modifica Profilo</div>
                                                    <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                        Aggiorna dati e qualifiche
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="/crm/?action=clienti&operatore=<?= $operatoreId ?>" class="quick-action">
                                            <span>🏢</span>
                                            <div>
                                                <div style="font-weight: 500;">Clienti Assegnati</div>
                                                <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                    Vedi tutti i clienti gestiti
                                                </div>
                                            </div>
                                        </a>
                                        
                                        <?php if ($sessionInfo['is_admin']): ?>
                                            <a href="/crm/?action=operatori&view=stats" class="quick-action">
                                                <span>📊</span>
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
                                        <?php if ($operatore['ultimo_accesso']): ?>
                                            <div class="info-item">
                                                <span class="info-label">Ultimo Accesso</span>
                                                <span class="info-value">
                                                    <?= date('d/m/Y H:i', strtotime($operatore['ultimo_accesso'])) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
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