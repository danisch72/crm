<?php
/**
 * modules/clienti/view.php - Visualizzazione Dettagli Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON TUTTI I CAMPI DATABASE
 * ✅ LAYOUT ULTRA-COMPATTO DATEV OPTIMAL
 * 
 * Features:
 * - Vista dettagliata cliente
 * - Riepilogo pratiche e documenti
 * - Timeline comunicazioni
 * - Design Datev Optimal
 * - NUOVO: visualizzazione regime_fiscale, liquidazione_iva, note_generali
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
$pageTitle = 'Dettaglio Cliente';
$pageIcon = '🏢';

// $clienteId già validato dal router
// Recupera dati cliente completi
$cliente = null;
try {
    $cliente = $db->selectOne("
        SELECT c.*, 
               CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
               o.email as operatore_email
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
        WHERE c.id = ?
    ", [$clienteId]);
    
    if (!$cliente) {
        $_SESSION['error_message'] = '⚠️ Cliente non trovato';
        header('Location: /crm/?action=clienti');
        exit;
    }
} catch (Exception $e) {
    error_log("Errore caricamento cliente: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore caricamento dati';
    header('Location: /crm/?action=clienti');
    exit;
}

// Funzioni helper per formattazione
function formatRegimeFiscale($regime) {
    $regimi = [
        'ordinario' => 'Regime Ordinario',
        'semplificato' => 'Regime Semplificato',
        'forfettario' => 'Regime Forfettario',
        'altro' => 'Altro'
    ];
    return $regimi[$regime] ?? $regime;
}

function formatLiquidazioneIva($liquidazione) {
    $tipi = [
        'mensile' => 'Mensile',
        'trimestrale' => 'Trimestrale',
        'fuori campo' => 'Fuori Campo IVA',
        'esente' => 'Esente IVA'
    ];
    return $tipi[$liquidazione] ?? $liquidazione;
}

// Statistiche cliente
$stats = [
    'pratiche_totali' => 0,
    'pratiche_attive' => 0,
    'documenti_totali' => 0,
    'comunicazioni_totali' => 0,
    'ultima_comunicazione' => null
];

try {
    // Pratiche
    $pratiche = $db->selectOne("
        SELECT 
            COUNT(*) as totali,
            COUNT(CASE WHEN stato = 'attiva' THEN 1 END) as attive
        FROM pratiche 
        WHERE cliente_id = ?
    ", [$clienteId]);
    
    if ($pratiche) {
        $stats['pratiche_totali'] = $pratiche['totali'];
        $stats['pratiche_attive'] = $pratiche['attive'];
    }
    
    // Documenti
    $docCount = $db->selectOne("
        SELECT COUNT(*) as total 
        FROM documenti_clienti 
        WHERE cliente_id = ?
    ", [$clienteId]);
    $stats['documenti_totali'] = $docCount ? $docCount['total'] : 0;
    
    // Comunicazioni
    $comunicazioni = $db->selectOne("
        SELECT 
            COUNT(*) as totali,
            MAX(created_at) as ultima
        FROM comunicazioni_clienti 
        WHERE cliente_id = ?
    ", [$clienteId]);
    
    if ($comunicazioni) {
        $stats['comunicazioni_totali'] = $comunicazioni['totali'];
        $stats['ultima_comunicazione'] = $comunicazioni['ultima'];
    }
} catch (Exception $e) {
    error_log("Errore caricamento statistiche cliente: " . $e->getMessage());
}

// Ultime comunicazioni
$ultimeComunicazioni = [];
try {
    $ultimeComunicazioni = $db->select("
        SELECT cc.*, 
               CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM comunicazioni_clienti cc
        LEFT JOIN operatori o ON cc.operatore_id = o.id
        WHERE cc.cliente_id = ?
        ORDER BY cc.created_at DESC
        LIMIT 5
    ", [$clienteId]);
} catch (Exception $e) {
    error_log("Errore caricamento comunicazioni: " . $e->getMessage());
}

// Helper per icone tipo comunicazione
function getTipoComunicazioneIcon($tipo) {
    $icons = [
        'telefono' => '📞',
        'email' => '📧',
        'whatsapp' => '💬',
        'teams' => '👥',
        'zoom' => '📹',
        'presenza' => '🤝',
        'nota' => '📝'
    ];
    return $icons[$tipo] ?? '💬';
}

// Helper per badge stato
function getStatoBadgeClass($stato) {
    $classes = [
        'attivo' => 'badge-success',
        'sospeso' => 'badge-warning',
        'chiuso' => 'badge-danger'
    ];
    return $classes[$stato] ?? 'badge-secondary';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- CSS nell'ordine corretto -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-professional.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Layout denso per view */
        .view-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .cliente-header {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .header-title {
            flex: 1;
        }
        
        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.25rem 0;
        }
        
        .cliente-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .info-group {
            border-left: 3px solid #e5e7eb;
            padding-left: 1rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 0.875rem;
            color: #1f2937;
            font-weight: 500;
        }
        
        .info-value.empty {
            color: #9ca3af;
            font-style: italic;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        .section-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.25rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .stat-card {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .timeline-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-icon {
            width: 32px;
            height: 32px;
            background: #f3f4f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
            min-width: 0;
        }
        
        .timeline-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.125rem;
        }
        
        .timeline-meta {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background-color: #fed7aa;
            color: #92400e;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .note-section {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .note-content {
            font-size: 0.875rem;
            color: #4b5563;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #9ca3af;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-top {
                flex-direction: column;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body class="datev-body">
    <div class="datev-container">
        <!-- ✅ COMPONENTE SIDEBAR (OBBLIGATORIO) -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <!-- ✅ COMPONENTE HEADER (OBBLIGATORIO) -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="view-container">
                    <!-- Header Cliente -->
                    <div class="cliente-header">
                        <div class="header-top">
                            <div class="header-title">
                                <h1><?= htmlspecialchars($cliente['ragione_sociale']) ?></h1>
                                <div class="cliente-meta">
                                    <span>ID: #<?= str_pad($clienteId, 6, '0', STR_PAD_LEFT) ?></span>
                                    <span>•</span>
                                    <span class="badge <?= getStatoBadgeClass($cliente['stato']) ?>">
                                        <?= ucfirst($cliente['stato']) ?>
                                    </span>
                                    <span>•</span>
                                    <span>Cliente dal <?= date('d/m/Y', strtotime($cliente['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="header-actions">
                                <?php if ($sessionInfo['is_admin'] || $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id']): ?>
                                    <a href="/crm/?action=clienti&view=edit&id=<?= $clienteId ?>" class="btn btn-primary">
                                        <span>✏️ Modifica</span>
                                    </a>
                                <?php endif; ?>
                                <a href="/crm/?action=clienti" class="btn btn-secondary">
                                    <span>← Torna alla lista</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <!-- Dati Fiscali -->
                            <div class="info-group">
                                <div class="info-label">Dati Fiscali</div>
                                <?php if ($cliente['codice_fiscale']): ?>
                                    <div class="info-value">C.F.: <?= htmlspecialchars($cliente['codice_fiscale']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['partita_iva']): ?>
                                    <div class="info-value">P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['regime_fiscale']): ?>
                                    <div class="info-value">Regime: <?= formatRegimeFiscale($cliente['regime_fiscale']) ?></div>
                                <?php endif; ?>
                                <?php if ($cliente['liquidazione_iva']): ?>
                                    <div class="info-value">IVA: <?= formatLiquidazioneIva($cliente['liquidazione_iva']) ?></div>
                                <?php endif; ?>
                                <?php if (!$cliente['codice_fiscale'] && !$cliente['partita_iva'] && !$cliente['regime_fiscale'] && !$cliente['liquidazione_iva']): ?>
                                    <div class="info-value empty">Dati fiscali non inseriti</div>
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
                                    <div class="info-value empty">Nessun contatto inserito</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Sede -->
                            <div class="info-group">
                                <div class="info-label">Sede Legale</div>
                                <?php if ($cliente['indirizzo'] || $cliente['citta']): ?>
                                    <div class="info-value">
                                        <?= htmlspecialchars($cliente['indirizzo']) ?>
                                        <?php if ($cliente['citta']): ?>
                                            <br><?= htmlspecialchars($cliente['cap']) ?> <?= htmlspecialchars($cliente['citta']) ?> 
                                            <?php if ($cliente['provincia']): ?>(<?= htmlspecialchars($cliente['provincia']) ?>)<?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="info-value empty">Indirizzo non inserito</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Operatore -->
                            <div class="info-group">
                                <div class="info-label">Operatore Responsabile</div>
                                <div class="info-value">
                                    👤 <?= htmlspecialchars($cliente['operatore_nome']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Grid -->
                    <div class="content-grid">
                        <!-- Colonna principale -->
                        <div>
                            <!-- Note Cliente -->
                            <?php if ($cliente['note'] || $cliente['note_generali']): ?>
                            <div class="section-card">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <span>📝</span>
                                        <span>Note Aggiuntive</span>
                                    </h2>
                                </div>
                                
                                <?php if ($cliente['note']): ?>
                                <div class="note-section">
                                    <div class="note-content"><?= nl2br(htmlspecialchars($cliente['note'])) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cliente['note_generali']): ?>
                                <div class="note-section" style="margin-top: 1rem;">
                                    <div class="info-label">Note Generali</div>
                                    <div class="note-content"><?= nl2br(htmlspecialchars($cliente['note_generali'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Timeline Comunicazioni -->
                            <div class="section-card" style="margin-top: 1.5rem;">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <span>💬</span>
                                        <span>Ultime Comunicazioni</span>
                                    </h2>
                                    <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn btn-sm btn-outline">
                                        Vedi tutte
                                    </a>
                                </div>
                                
                                <?php if (!empty($ultimeComunicazioni)): ?>
                                    <?php foreach ($ultimeComunicazioni as $com): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <?= getTipoComunicazioneIcon($com['tipo']) ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-title">
                                                <?= htmlspecialchars($com['oggetto']) ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?> • 
                                                <?= htmlspecialchars($com['operatore_nome']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <p>Nessuna comunicazione registrata</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sidebar -->
                        <div>
                            <!-- Statistiche -->
                            <div class="section-card">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <span>📊</span>
                                        <span>Statistiche</span>
                                    </h2>
                                </div>
                                
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <div class="stat-value"><?= $stats['pratiche_attive'] ?></div>
                                        <div class="stat-label">Pratiche Attive</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?= $stats['documenti_totali'] ?></div>
                                        <div class="stat-label">Documenti</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?= $stats['comunicazioni_totali'] ?></div>
                                        <div class="stat-label">Comunicazioni</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value">
                                            <?php if ($stats['ultima_comunicazione']): ?>
                                                <?= date('d/m', strtotime($stats['ultima_comunicazione'])) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                        <div class="stat-label">Ultimo Contatto</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Azioni Rapide -->
                            <div class="section-card" style="margin-top: 1.5rem;">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <span>⚡</span>
                                        <span>Azioni Rapide</span>
                                    </h2>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn btn-block btn-outline">
                                        <span>📁 Gestione Documenti</span>
                                    </a>
                                    <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn btn-block btn-outline">
                                        <span>💬 Nuova Comunicazione</span>
                                    </a>
                                    <a href="/crm/?action=pratiche&view=create&cliente_id=<?= $clienteId ?>" class="btn btn-block btn-outline">
                                        <span>📋 Nuova Pratica</span>
                                    </a>
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