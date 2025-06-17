<?php
/**
 * modules/clienti/view.php - Visualizzazione Dettagli Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE CON SIDEBAR E HEADER CENTRALIZZATI
 * 
 * Features:
 * - Vista dettagliata cliente con tutte le informazioni
 * - Timeline attività
 * - Documenti recenti
 * - Comunicazioni recenti
 * - Pratiche associate
 * - Azioni rapide
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili per i componenti
$pageTitle = 'Dettagli Cliente';
$pageIcon = '👁️';

// $clienteId già validato dal router
// Recupera dati cliente completi
try {
    $cliente = $db->selectOne("
        SELECT 
            c.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
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
    
    // Statistiche cliente
    $stats = [
        'pratiche_totali' => $db->selectOne("SELECT COUNT(*) as count FROM pratiche WHERE cliente_id = ?", [$clienteId])['count'] ?? 0,
        'pratiche_attive' => $db->selectOne("SELECT COUNT(*) as count FROM pratiche WHERE cliente_id = ? AND stato = 'attiva'", [$clienteId])['count'] ?? 0,
        'documenti_totali' => $db->selectOne("SELECT COUNT(*) as count FROM documenti_clienti WHERE cliente_id = ?", [$clienteId])['count'] ?? 0,
        'comunicazioni_totali' => $db->selectOne("SELECT COUNT(*) as count FROM comunicazioni_clienti WHERE cliente_id = ?", [$clienteId])['count'] ?? 0
    ];
    
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
    
    // Pratiche associate
    $pratiche = $db->select("
        SELECT 
            p.*,
            tp.nome as tipo_pratica_nome
        FROM pratiche p
        LEFT JOIN tipi_pratiche tp ON p.tipo_pratica_id = tp.id
        WHERE p.cliente_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ", [$clienteId]);
    
} catch (Exception $e) {
    error_log("Errore caricamento dati cliente: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore nel caricamento dei dati';
    header('Location: /crm/?action=clienti');
    exit;
}

// Funzioni helper
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
    return $stato === 'attivo' ? 'status-active' : ($stato === 'sospeso' ? 'status-warning' : 'status-inactive');
}

function getStatoLabel($stato) {
    $labels = [
        'attivo' => '✅ Attivo',
        'sospeso' => '⚠️ Sospeso',
        'chiuso' => '🔴 Chiuso'
    ];
    return $labels[$stato] ?? $stato;
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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- CSS nell'ordine corretto -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        .cliente-container {
            padding: 2rem 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header cliente */
        .cliente-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .cliente-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .cliente-icon {
            font-size: 2.5rem;
        }
        
        .cliente-name {
            font-size: 2rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .cliente-id {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        .cliente-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-action:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
            transform: translateY(-1px);
        }
        
        .btn-action.primary {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        .btn-action.primary:hover {
            background: var(--primary-green-hover);
            border-color: var(--primary-green-hover);
        }
        
        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background: var(--color-success-light);
            color: var(--color-success);
        }
        
        .status-warning {
            background: var(--color-warning-light);
            color: var(--color-warning);
        }
        
        .status-inactive {
            background: var(--color-danger-light);
            color: var(--color-danger);
        }
        
        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .info-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            font-size: 0.875rem;
            color: var(--gray-900);
        }
        
        .info-value.empty {
            color: var(--gray-400);
            font-style: italic;
        }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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
            font-size: 2rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        /* Content sections */
        .content-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .content-sections {
                grid-template-columns: 1fr;
            }
        }
        
        .section-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-content {
            padding: 1rem;
        }
        
        /* List items */
        .list-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.25rem;
        }
        
        .list-item-title {
            font-weight: 500;
            color: var(--gray-900);
        }
        
        .list-item-meta {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .list-item-description {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        /* Note section */
        .note-section {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .note-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        .note-text {
            font-size: 0.875rem;
            color: var(--gray-700);
            white-space: pre-wrap;
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
                <div class="cliente-container">
                    <!-- Header Cliente -->
                    <div class="cliente-header">
                        <div class="header-top">
                            <div class="cliente-title">
                                <div class="cliente-icon">
                                    <?= getTipologiaIcon($cliente['tipologia_azienda']) ?>
                                </div>
                                <div>
                                    <h1 class="cliente-name"><?= htmlspecialchars($cliente['ragione_sociale']) ?></h1>
                                    <div class="cliente-id">
                                        ID: #<?= str_pad($cliente['id'], 6, '0', STR_PAD_LEFT) ?> 
                                        • <?= htmlspecialchars($cliente['tipologia_azienda']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="cliente-actions">
                                <span class="status-badge <?= getStatoClass($cliente['stato']) ?>">
                                    <?= getStatoLabel($cliente['stato']) ?>
                                </span>
                                <?php if ($sessionInfo['is_admin'] || $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id']): ?>
                                    <a href="/crm/?action=clienti&view=edit&id=<?= $clienteId ?>" class="btn-action primary">
                                        ✏️ Modifica
                                    </a>
                                <?php endif; ?>
                                <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn-action">
                                    📁 Documenti
                                </a>
                                <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn-action">
                                    💬 Comunicazioni
                                </a>
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <!-- Dati fiscali -->
                            <div class="info-group">
                                <div class="info-label">Dati Fiscali</div>
                                <?php if ($cliente['codice_fiscale']): ?>
                                    <div class="info-value">CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['partita_iva']): ?>
                                    <div class="info-value">P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['codice_univoco']): ?>
                                    <div class="info-value">SDI: <?= htmlspecialchars($cliente['codice_univoco']) ?></div>
                                <?php endif; ?>
                                <?php if (!$cliente['codice_fiscale'] && !$cliente['partita_iva']): ?>
                                    <div class="info-value empty">Non specificati</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Sede legale -->
                            <div class="info-group">
                                <div class="info-label">Sede Legale</div>
                                <?php if ($cliente['indirizzo'] || $cliente['citta']): ?>
                                    <div class="info-value"><?= htmlspecialchars(formatIndirizzoCompleto($cliente)) ?></div>
                                <?php else: ?>
                                    <div class="info-value empty">Non specificata</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Contatti -->
                            <div class="info-group">
                                <div class="info-label">Contatti</div>
                                <?php if ($cliente['telefono']): ?>
                                    <div class="info-value">📞 <?= htmlspecialchars($cliente['telefono']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['email']): ?>
                                    <div class="info-value">📧 <?= htmlspecialchars($cliente['email']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['pec']): ?>
                                    <div class="info-value">📮 <?= htmlspecialchars($cliente['pec']) ?></div>
                                <?php endif; ?>
                                <?php if (!$cliente['telefono'] && !$cliente['email'] && !$cliente['pec']): ?>
                                    <div class="info-value empty">Non specificati</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Operatore responsabile -->
                            <div class="info-group">
                                <div class="info-label">Operatore Responsabile</div>
                                <?php if ($cliente['operatore_nome']): ?>
                                    <div class="info-value">
                                        👤 <?= htmlspecialchars($cliente['operatore_nome']) ?>
                                    </div>
                                    <?php if ($cliente['operatore_email']): ?>
                                        <div class="info-value" style="font-size: 0.75rem;">
                                            📧 <?= htmlspecialchars($cliente['operatore_email']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="info-value empty">Non assegnato</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($cliente['note']): ?>
                            <div class="note-section">
                                <div class="note-label">Note</div>
                                <div class="note-text"><?= nl2br(htmlspecialchars($cliente['note'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Statistiche -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📋</div>
                            <div class="stat-value"><?= $stats['pratiche_totali'] ?></div>
                            <div class="stat-label">Pratiche Totali</div>
                            <?php if ($stats['pratiche_attive'] > 0): ?>
                                <div style="color: var(--color-success); font-size: 0.75rem; margin-top: 0.25rem;">
                                    <?= $stats['pratiche_attive'] ?> attive
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📁</div>
                            <div class="stat-value"><?= $stats['documenti_totali'] ?></div>
                            <div class="stat-label">Documenti</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">💬</div>
                            <div class="stat-value"><?= $stats['comunicazioni_totali'] ?></div>
                            <div class="stat-label">Comunicazioni</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📅</div>
                            <div class="stat-value">
                                <?php
                                $giorni = floor((time() - strtotime($cliente['created_at'])) / 86400);
                                echo $giorni;
                                ?>
                            </div>
                            <div class="stat-label">Giorni da Acquisizione</div>
                        </div>
                    </div>
                    
                    <!-- Content Sections -->
                    <div class="content-sections">
                        <!-- Documenti Recenti -->
                        <div class="section-card">
                            <div class="section-header">
                                <h3 class="section-title">
                                    📁 Documenti Recenti
                                </h3>
                                <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn-action" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                    Vedi tutti
                                </a>
                            </div>
                            <div class="section-content">
                                <?php if (empty($documentiRecenti)): ?>
                                    <div class="empty-state">
                                        <p>Nessun documento caricato</p>
                                        <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn-action primary" style="margin-top: 1rem;">
                                            📤 Carica Documento
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($documentiRecenti as $doc): ?>
                                        <div class="list-item">
                                            <div class="list-item-header">
                                                <div class="list-item-title">
                                                    <?= getCategoriaDocIcon($doc['categoria']) ?>
                                                    <?= htmlspecialchars($doc['nome_file_originale']) ?>
                                                </div>
                                                <div class="list-item-meta">
                                                    <?= date('d/m/Y', strtotime($doc['data_upload'])) ?>
                                                </div>
                                            </div>
                                            <div class="list-item-description">
                                                Caricato da <?= htmlspecialchars($doc['operatore_nome'] ?? 'Sistema') ?>
                                                <?php if ($doc['note']): ?>
                                                    • <?= htmlspecialchars($doc['note']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Comunicazioni Recenti -->
                        <div class="section-card">
                            <div class="section-header">
                                <h3 class="section-title">
                                    💬 Comunicazioni Recenti
                                </h3>
                                <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn-action" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                    Vedi tutte
                                </a>
                            </div>
                            <div class="section-content">
                                <?php if (empty($comunicazioniRecenti)): ?>
                                    <div class="empty-state">
                                        <p>Nessuna comunicazione registrata</p>
                                        <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn-action primary" style="margin-top: 1rem;">
                                            ➕ Nuova Comunicazione
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($comunicazioniRecenti as $comm): ?>
                                        <div class="list-item">
                                            <div class="list-item-header">
                                                <div class="list-item-title">
                                                    <?php
                                                    $tipoIcon = [
                                                        'email' => '📧',
                                                        'telefono' => '📞',
                                                        'incontro' => '🤝',
                                                        'nota' => '📝'
                                                    ];
                                                    echo $tipoIcon[$comm['tipo']] ?? '💬';
                                                    ?>
                                                    <?= htmlspecialchars($comm['oggetto']) ?>
                                                </div>
                                                <div class="list-item-meta">
                                                    <?= date('d/m/Y H:i', strtotime($comm['created_at'])) ?>
                                                </div>
                                            </div>
                                            <div class="list-item-description">
                                                <?= htmlspecialchars(substr($comm['contenuto'], 0, 100)) ?>
                                                <?= strlen($comm['contenuto']) > 100 ? '...' : '' ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pratiche Associate -->
                    <?php if (!empty($pratiche)): ?>
                        <div class="section-card" style="margin-top: 2rem;">
                            <div class="section-header">
                                <h3 class="section-title">
                                    📋 Pratiche Associate
                                </h3>
                                <a href="/crm/?action=pratiche&cliente_id=<?= $clienteId ?>" class="btn-action" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                    Vedi tutte
                                </a>
                            </div>
                            <div class="section-content">
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; font-size: 0.875rem;">
                                        <thead>
                                            <tr style="border-bottom: 2px solid var(--gray-200);">
                                                <th style="text-align: left; padding: 0.5rem;">Codice</th>
                                                <th style="text-align: left; padding: 0.5rem;">Tipo</th>
                                                <th style="text-align: left; padding: 0.5rem;">Oggetto</th>
                                                <th style="text-align: left; padding: 0.5rem;">Stato</th>
                                                <th style="text-align: left; padding: 0.5rem;">Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pratiche as $pratica): ?>
                                                <tr style="border-bottom: 1px solid var(--gray-100);">
                                                    <td style="padding: 0.5rem;">
                                                        <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" style="color: var(--primary-green); text-decoration: none;">
                                                            #<?= str_pad($pratica['id'], 6, '0', STR_PAD_LEFT) ?>
                                                        </a>
                                                    </td>
                                                    <td style="padding: 0.5rem;">
                                                        <?= htmlspecialchars($pratica['tipo_pratica_nome'] ?? 'N/A') ?>
                                                    </td>
                                                    <td style="padding: 0.5rem;">
                                                        <?= htmlspecialchars($pratica['oggetto']) ?>
                                                    </td>
                                                    <td style="padding: 0.5rem;">
                                                        <span class="status-badge status-<?= $pratica['stato'] ?>">
                                                            <?= ucfirst($pratica['stato']) ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 0.5rem;">
                                                        <?= date('d/m/Y', strtotime($pratica['created_at'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Script microinterazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
</body>
</html>