<?php
/**
 * modules/clienti/view.php - Visualizzazione Cliente CRM Re.De Consulting
 * 
 * ✅ VISTA DETTAGLIATA CLIENTE - VERSIONE CORRETTA
 * 
 * Features:
 * - Vista dettagliata con tutte le informazioni cliente
 * - Dashboard attività e statistiche
 * - Accesso rapido a documenti e comunicazioni
 * - Timeline attività recenti
 * - Informazioni fiscali e amministrative
 * - Layout uniforme con sistema operatori
 */

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message
// $clienteId (validato dal router)

$pageTitle = 'Dettagli Cliente';

// Recupera dati cliente completi
try {
    $cliente = $db->selectOne("
        SELECT 
            c.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_responsabile_nome,
            o.email as operatore_email,
            o.telefono as operatore_telefono
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
        WHERE c.id = ?
    ", [$clienteId]);
    
    if (!$cliente) {
        $_SESSION['error_message'] = '⚠️ Cliente non trovato';
        header('Location: /crm/?action=clienti');
        exit;
    }
    
    // Controllo permessi: admin o operatore responsabile
    $canView = $sessionInfo['is_admin'] || 
               $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id'];
    
    if (!$canView) {
        $_SESSION['error_message'] = '⚠️ Non hai i permessi per visualizzare questo cliente';
        header('Location: /crm/?action=clienti');
        exit;
    }
    
    // Statistiche cliente
    $stats = $db->selectOne("
        SELECT 
            (SELECT COUNT(*) FROM documenti_clienti WHERE cliente_id = ?) as totale_documenti,
            (SELECT COUNT(*) FROM comunicazioni_clienti WHERE cliente_id = ?) as totale_comunicazioni,
            (SELECT COUNT(*) FROM comunicazioni_clienti WHERE cliente_id = ? AND data_followup IS NOT NULL AND completato = 0) as followup_pendenti,
            (SELECT MAX(created_at) FROM comunicazioni_clienti WHERE cliente_id = ?) as ultima_comunicazione,
            (SELECT COUNT(*) FROM documenti_clienti WHERE cliente_id = ? AND data_scadenza <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as documenti_in_scadenza
    ", [$clienteId, $clienteId, $clienteId, $clienteId, $clienteId]);
    
    // Documenti recenti
    $documentiRecenti = $db->select("
        SELECT 
            dc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM documenti_clienti dc
        LEFT JOIN operatori o ON dc.operatore_id = o.id
        WHERE dc.cliente_id = ?
        ORDER BY dc.data_upload DESC
        LIMIT 5
    ", [$clienteId]);
    
    // Comunicazioni recenti
    $comunicazioniRecenti = $db->select("
        SELECT 
            cc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM comunicazioni_clienti cc
        LEFT JOIN operatori o ON cc.operatore_id = o.id
        WHERE cc.cliente_id = ?
        ORDER BY cc.created_at DESC
        LIMIT 5
    ", [$clienteId]);
    
    // Attività timeline (documenti + comunicazioni misti)
    $timeline = $db->select("
        (SELECT 
            'documento' as tipo,
            id,
            nome_file_originale as titolo,
            CONCAT('Caricato documento: ', categoria) as descrizione,
            data_upload as data_evento,
            operatore_id
        FROM documenti_clienti 
        WHERE cliente_id = ?
        ORDER BY data_upload DESC
        LIMIT 10)
        
        UNION ALL
        
        (SELECT 
            'comunicazione' as tipo,
            id,
            oggetto as titolo,
            CONCAT(tipo, ': ', LEFT(contenuto, 100)) as descrizione,
            created_at as data_evento,
            operatore_id
        FROM comunicazioni_clienti 
        WHERE cliente_id = ?
        ORDER BY created_at DESC
        LIMIT 10)
        
        ORDER BY data_evento DESC
        LIMIT 10
    ", [$clienteId, $clienteId]);
    
} catch (Exception $e) {
    error_log("Errore caricamento dati cliente: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore nel caricamento dei dati';
    header('Location: /crm/?action=clienti');
    exit;
}

// Funzioni helper vista
function getTipologiaIcon($tipologia) {
    $icons = [
        'individuale' => '👤',
        'srl' => '🏢',
        'spa' => '🏛️',
        'snc' => '🤝',
        'sas' => '🏪'
    ];
    return $icons[$tipologia] ?? '🏢';
}

function formatIndirizzoCompleto($cliente) {
    $parts = [];
    if ($cliente['indirizzo']) $parts[] = $cliente['indirizzo'];
    if ($cliente['cap']) $parts[] = $cliente['cap'];
    if ($cliente['citta']) $parts[] = $cliente['citta'];
    if ($cliente['provincia']) $parts[] = '(' . $cliente['provincia'] . ')';
    return implode(' ', $parts);
}

function getStatoClass($stato) {
    return $stato === 'attivo' ? 'status-active' : 'status-inactive';
}

function getStatoLabel($stato) {
    return $stato === 'attivo' ? '✅ Attivo' : '⚠️ Sospeso';
}

function getCategoriaDocIcon($categoria) {
    $icons = [
        'contratto' => '📄',
        'fattura' => '🧾',
        'documento_identita' => '🪪',
        'visura' => '🏢',
        'bilancio' => '📊',
        'dichiarazione' => '📋',
        'generale' => '📎'
    ];
    return $icons[$categoria] ?? '📎';
}

function getTipoComIcon($tipo) {
    $icons = [
        'telefono' => '📞',
        'email' => '📧',
        'whatsapp' => '💬',
        'teams' => '👥',
        'zoom' => '📹',
        'presenza' => '🤝',
        'nota' => '📝'
    ];
    return $icons[$tipo] ?? '💭';
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d == 0) {
        if ($diff->h == 0) {
            return $diff->i . ' minuti fa';
        }
        return $diff->h . ' ore fa';
    } elseif ($diff->d == 1) {
        return 'Ieri';
    } elseif ($diff->d < 7) {
        return $diff->d . ' giorni fa';
    } elseif ($diff->d < 30) {
        return floor($diff->d / 7) . ' settimane fa';
    } else {
        return $ago->format('d/m/Y');
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De Consulting</title>
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Layout Vista Cliente */
        .view-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Header Cliente */
        .cliente-header {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .cliente-identity {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .cliente-avatar {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .cliente-details h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: var(--text-primary);
        }
        
        .cliente-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .cliente-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        /* Statistiche Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .stat-alert {
            color: var(--warning-color);
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }
        
        /* Dashboard Content */
        .dashboard-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }
        
        @media (max-width: 1200px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Widget base */
        .widget {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        
        .widget-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .widget-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .widget-content {
            padding: 1.5rem;
        }
        
        /* Info sections */
        .info-section {
            margin-bottom: 1.5rem;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
        }
        
        .info-section h4 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 0.5rem;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .info-value {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        /* Timeline */
        .timeline-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
            min-width: 0;
        }
        
        .timeline-title {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
        }
        
        .timeline-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .timeline-time {
            font-size: 0.75rem;
            color: var(--text-tertiary);
            margin-top: 0.2rem;
        }
        
        /* Lista documenti/comunicazioni recenti */
        .recent-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .recent-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .recent-item:hover {
            background: var(--gray-100);
            transform: translateX(2px);
        }
        
        .recent-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .recent-item-title {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .recent-item-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }
        
        .recent-item-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-left: 0.5rem;
        }
        
        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .empty-state-icon {
            font-size: 2rem;
            opacity: 0.5;
            margin-bottom: 0.5rem;
        }
        
        /* Tabs per sezioni */
        .info-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-tab {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }
        
        .info-tab:hover {
            color: var(--primary-color);
        }
        
        .info-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>

    <div class="view-container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" style="margin-bottom: 1rem;">
            <a href="/crm/">Home</a>
            <span class="separator">/</span>
            <a href="/crm/?action=clienti">Clienti</a>
            <span class="separator">/</span>
            <span class="current"><?= htmlspecialchars($cliente['ragione_sociale']) ?></span>
        </nav>

        <!-- Header Cliente -->
        <div class="cliente-header">
            <div class="header-top">
                <div class="cliente-identity">
                    <!-- Avatar -->
                    <div class="cliente-avatar">
                        <?= getTipologiaIcon($cliente['tipologia_azienda']) ?>
                    </div>
                    
                    <!-- Dettagli -->
                    <div class="cliente-details">
                        <h1><?= htmlspecialchars($cliente['ragione_sociale']) ?></h1>
                        <div class="cliente-meta">
                            <div class="meta-item">
                                <span>🏢</span>
                                <span><?= ucfirst($cliente['tipologia_azienda']) ?></span>
                            </div>
                            <?php if ($cliente['codice_cliente']): ?>
                                <div class="meta-item">
                                    <span>🆔</span>
                                    <span><?= htmlspecialchars($cliente['codice_cliente']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($cliente['partita_iva']): ?>
                                <div class="meta-item">
                                    <span>📋</span>
                                    <span>P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($cliente['codice_fiscale']): ?>
                                <div class="meta-item">
                                    <span>🪪</span>
                                    <span>CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($cliente['operatore_responsabile_nome']): ?>
                                <div class="meta-item">
                                    <span>👤</span>
                                    <span>Resp: <?= htmlspecialchars($cliente['operatore_responsabile_nome']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: right;">
                    <!-- Status -->
                    <div class="status-badge <?= getStatoClass($cliente['stato']) ?>">
                        <?= getStatoLabel($cliente['stato']) ?>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="cliente-actions">
                <a href="/crm/?action=clienti&view=edit&id=<?= $clienteId ?>" class="btn btn-primary">
                    ✏️ Modifica
                </a>
                <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn btn-secondary">
                    📁 Documenti
                </a>
                <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn btn-secondary">
                    💬 Comunicazioni
                </a>
                <a href="/crm/?action=clienti" class="btn btn-secondary">
                    ← Torna alla Lista
                </a>
            </div>
        </div>

        <!-- Statistiche Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">📁</div>
                <div class="stat-value"><?= $stats['totale_documenti'] ?></div>
                <div class="stat-label">Documenti</div>
                <?php if ($stats['documenti_in_scadenza'] > 0): ?>
                    <div class="stat-alert">⚠️ <?= $stats['documenti_in_scadenza'] ?> in scadenza</div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-value"><?= $stats['totale_comunicazioni'] ?></div>
                <div class="stat-label">Comunicazioni</div>
                <?php if ($stats['followup_pendenti'] > 0): ?>
                    <div class="stat-alert">⏰ <?= $stats['followup_pendenti'] ?> follow-up</div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value">
                    <?php if ($stats['ultima_comunicazione']): ?>
                        <?= timeAgo($stats['ultima_comunicazione']) ?>
                    <?php else: ?>
                        Mai
                    <?php endif; ?>
                </div>
                <div class="stat-label">Ultimo Contatto</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value">
                    <?php 
                    $giorniDaCreazione = floor((time() - strtotime($cliente['created_at'])) / 86400);
                    echo $giorniDaCreazione;
                    ?>
                </div>
                <div class="stat-label">Giorni Cliente</div>
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
                        <!-- Tabs -->
                        <div class="info-tabs">
                            <div class="info-tab active" onclick="switchTab('anagrafica')">
                                Dati Anagrafici
                            </div>
                            <div class="info-tab" onclick="switchTab('fiscale')">
                                Dati Fiscali
                            </div>
                            <div class="info-tab" onclick="switchTab('contatti')">
                                Contatti
                            </div>
                            <div class="info-tab" onclick="switchTab('note')">
                                Note
                            </div>
                        </div>
                        
                        <!-- Tab Contents -->
                        <div id="tab-anagrafica" class="tab-content active">
                            <div class="info-section">
                                <h4>🏢 Informazioni Aziendali</h4>
                                <div class="info-grid">
                                    <span class="info-label">Ragione Sociale</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['ragione_sociale']) ?></span>
                                    
                                    <span class="info-label">Tipologia</span>
                                    <span class="info-value"><?= ucfirst($cliente['tipologia_azienda']) ?></span>
                                    
                                    <span class="info-label">Settore</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['settore_attivita'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Codice ATECO</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['codice_ateco'] ?? 'Non specificato') ?></span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>📍 Sede Legale</h4>
                                <div class="info-grid">
                                    <span class="info-label">Indirizzo</span>
                                    <span class="info-value"><?= formatIndirizzoCompleto($cliente) ?: 'Non specificato' ?></span>
                                    
                                    <span class="info-label">CAP</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['cap'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Città</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['citta'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Provincia</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['provincia'] ?? 'Non specificato') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="tab-fiscale" class="tab-content">
                            <div class="info-section">
                                <h4>💰 Dati Fiscali</h4>
                                <div class="info-grid">
                                    <span class="info-label">Partita IVA</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['partita_iva'] ?? 'Non specificata') ?></span>
                                    
                                    <span class="info-label">Codice Fiscale</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['codice_fiscale'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Regime Fiscale</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['regime_fiscale'] ?? 'Ordinario') ?></span>
                                    
                                    <span class="info-label">Codice SDI</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['codice_sdi'] ?? 'Non specificato') ?></span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>🏦 Dati Bancari</h4>
                                <div class="info-grid">
                                    <span class="info-label">IBAN</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['iban'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Banca</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['banca'] ?? 'Non specificata') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="tab-contatti" class="tab-content">
                            <div class="info-section">
                                <h4>📞 Contatti Principali</h4>
                                <div class="info-grid">
                                    <span class="info-label">Telefono</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['telefono'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Cellulare</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['cellulare'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['email'] ?? 'Non specificata') ?></span>
                                    
                                    <span class="info-label">PEC</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['pec'] ?? 'Non specificata') ?></span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>👤 Referente</h4>
                                <div class="info-grid">
                                    <span class="info-label">Nome</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['referente_nome'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Ruolo</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['referente_ruolo'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Telefono</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['referente_telefono'] ?? 'Non specificato') ?></span>
                                    
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['referente_email'] ?? 'Non specificata') ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="tab-note" class="tab-content">
                            <div class="info-section">
                                <h4>📝 Note e Osservazioni</h4>
                                <div style="background: var(--gray-50); padding: 1rem; border-radius: var(--border-radius-sm); min-height: 100px;">
                                    <?php if ($cliente['note']): ?>
                                        <?= nl2br(htmlspecialchars($cliente['note'])) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-style: italic;">
                                            Nessuna nota presente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline Attività -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">⏱️ Timeline Attività</h3>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($timeline)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <p>Nessuna attività registrata</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($timeline as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon">
                                        <?= $event['tipo'] === 'documento' ? getCategoriaDocIcon('generale') : getTipoComIcon('nota') ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title">
                                            <?= htmlspecialchars($event['titolo']) ?>
                                        </div>
                                        <div class="timeline-desc">
                                            <?= htmlspecialchars($event['descrizione']) ?>
                                        </div>
                                        <div class="timeline-time">
                                            <?= timeAgo($event['data_evento']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonna Laterale -->
            <div>
                <!-- Documenti Recenti -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">📁 Documenti Recenti</h3>
                        <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn btn-sm btn-secondary">
                            Vedi tutti
                        </a>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($documentiRecenti)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <p>Nessun documento</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($documentiRecenti as $doc): ?>
                                    <a href="/crm/uploads/clienti/<?= $clienteId ?>/<?= $doc['nome_file_salvato'] ?>" 
                                       target="_blank"
                                       class="recent-item">
                                        <div class="recent-item-info">
                                            <div class="recent-item-title">
                                                <?= htmlspecialchars($doc['nome_file_originale']) ?>
                                            </div>
                                            <div class="recent-item-meta">
                                                <?= getCategoriaDocIcon($doc['categoria']) ?> 
                                                <?= ucfirst($doc['categoria']) ?> • 
                                                <?= timeAgo($doc['data_upload']) ?>
                                            </div>
                                        </div>
                                        <div class="recent-item-icon">
                                            👁️
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comunicazioni Recenti -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">💬 Comunicazioni Recenti</h3>
                        <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn btn-sm btn-secondary">
                            Vedi tutte
                        </a>
                    </div>
                    <div class="widget-content">
                        <?php if (empty($comunicazioniRecenti)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">💭</div>
                                <p>Nessuna comunicazione</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($comunicazioniRecenti as $com): ?>
                                    <div class="recent-item">
                                        <div class="recent-item-info">
                                            <div class="recent-item-title">
                                                <?= htmlspecialchars($com['oggetto']) ?>
                                            </div>
                                            <div class="recent-item-meta">
                                                <?= getTipoComIcon($com['tipo']) ?> 
                                                <?= ucfirst($com['tipo']) ?> • 
                                                <?= timeAgo($com['created_at']) ?>
                                            </div>
                                        </div>
                                        <?php if ($com['data_followup'] && !$com['completato']): ?>
                                            <div class="recent-item-icon" style="color: var(--warning-color);">
                                                ⏰
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Operatore -->
                <?php if ($cliente['operatore_responsabile_nome']): ?>
                    <div class="widget">
                        <div class="widget-header">
                            <h3 class="widget-title">👤 Operatore Responsabile</h3>
                        </div>
                        <div class="widget-content">
                            <div style="text-align: center;">
                                <div style="font-size: 1.1rem; font-weight: 500; margin-bottom: 0.5rem;">
                                    <?= htmlspecialchars($cliente['operatore_responsabile_nome']) ?>
                                </div>
                                <?php if ($cliente['operatore_email']): ?>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.3rem;">
                                        📧 <?= htmlspecialchars($cliente['operatore_email']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($cliente['operatore_telefono']): ?>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                        📞 <?= htmlspecialchars($cliente['operatore_telefono']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Switch tabs
        function switchTab(tabName) {
            // Remove active from all tabs and contents
            document.querySelectorAll('.info-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Add active to clicked tab and its content
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
</body>
</html>