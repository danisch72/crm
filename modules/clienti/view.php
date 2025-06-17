<?php
/**
 * modules/clienti/view.php - Visualizzazione Dettagli Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE DEFINITIVA CON COMPONENTI CENTRALIZZATI
 * ✅ LAYOUT ULTRA-COMPATTO DATEV OPTIMAL
 * 
 * Features:
 * - Vista dettagliata cliente
 * - Riepilogo pratiche e documenti
 * - Timeline comunicazioni
 * - Design Datev Optimal
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
} catch (Exception $e) {
    error_log("Errore caricamento cliente: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore caricamento dati';
    header('Location: /crm/?action=clienti');
    exit;
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
    $stats['documenti_totali'] = $db->count('documenti_clienti', 'cliente_id = ?', [$clienteId]);
    
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

// Helper functions
function getTipologiaIcon($tipo) {
    $icons = [
        'individuale' => '👤',
        'srl' => '🏢',
        'spa' => '🏭',
        'snc' => '👥',
        'sas' => '🤝'
    ];
    return $icons[$tipo] ?? '🏢';
}

function getTipologiaLabel($tipo) {
    $labels = [
        'individuale' => 'Ditta Individuale',
        'srl' => 'S.r.l.',
        'spa' => 'S.p.A.',
        'snc' => 'S.n.c.',
        'sas' => 'S.a.s.'
    ];
    return $labels[$tipo] ?? $tipo;
}

function getStatoClass($stato) {
    $classes = [
        'attivo' => 'status-active',
        'sospeso' => 'status-warning',
        'chiuso' => 'status-inactive'
    ];
    return $classes[$stato] ?? '';
}

function getStatoLabel($stato) {
    $labels = [
        'attivo' => '✅ Attivo',
        'sospeso' => '⚠️ Sospeso',
        'chiuso' => '🔴 Chiuso'
    ];
    return $labels[$stato] ?? $stato;
}

function getTipoComunicazioneIcon($tipo) {
    $icons = [
        'email' => '📧',
        'telefono' => '📞',
        'incontro' => '🤝',
        'nota' => '📝'
    ];
    return $icons[$tipo] ?? '💬';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Layout ultra-compatto */
        .cliente-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header cliente */
        .cliente-header {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .cliente-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .cliente-icon {
            font-size: 2.5rem;
            line-height: 1;
        }
        
        .cliente-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .cliente-id {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .cliente-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-action.primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-action.primary:hover {
            background: var(--primary-green-hover);
        }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.813rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        /* Info sections */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1rem 1.25rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-content {
            padding: 1.25rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.813rem;
            color: var(--gray-600);
        }
        
        .info-value {
            font-size: 0.875rem;
            color: var(--gray-900);
            font-weight: 500;
            text-align: right;
        }
        
        /* Timeline comunicazioni */
        .timeline {
            padding: 1rem;
        }
        
        .timeline-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
            min-width: 0;
        }
        
        .timeline-title {
            font-weight: 500;
            color: var(--gray-900);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .timeline-meta {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-600);
        }
        
        .empty-state-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
            }
            
            .cliente-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .info-grid {
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
                <div class="cliente-container">
                    <!-- Messaggi -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                    <?php endif; ?>
                    
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
                                        • <?= getTipologiaLabel($cliente['tipologia_azienda']) ?>
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
                                <a href="/crm/?action=clienti&view=documenti&id=<?= $clienteId ?>" class="btn-action btn-secondary">
                                    📁 Documenti
                                </a>
                                <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" class="btn-action btn-secondary">
                                    💬 Comunicazioni
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiche -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📋</div>
                            <div class="stat-value"><?= $stats['pratiche_attive'] ?></div>
                            <div class="stat-label">Pratiche Attive</div>
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
                                <?php if ($stats['ultima_comunicazione']): ?>
                                    <?= date('d/m', strtotime($stats['ultima_comunicazione'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                            <div class="stat-label">Ultimo Contatto</div>
                        </div>
                    </div>
                    
                    <!-- Info sections -->
                    <div class="info-grid">
                        <!-- Dati fiscali -->
                        <div class="info-section">
                            <div class="section-header">
                                <span>🧾</span>
                                <span>Dati Fiscali</span>
                            </div>
                            <div class="section-content">
                                <?php if ($cliente['codice_fiscale']): ?>
                                <div class="info-row">
                                    <span class="info-label">Codice Fiscale</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['codice_fiscale']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cliente['partita_iva']): ?>
                                <div class="info-row">
                                    <span class="info-label">Partita IVA</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['partita_iva']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cliente['codice_univoco']): ?>
                                <div class="info-row">
                                    <span class="info-label">Codice SDI</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['codice_univoco']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!$cliente['codice_fiscale'] && !$cliente['partita_iva'] && !$cliente['codice_univoco']): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <p>Nessun dato fiscale inserito</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recapiti -->
                        <div class="info-section">
                            <div class="section-header">
                                <span>📍</span>
                                <span>Recapiti</span>
                            </div>
                            <div class="section-content">
                                <?php if ($cliente['indirizzo'] || $cliente['citta']): ?>
                                <div class="info-row">
                                    <span class="info-label">Indirizzo</span>
                                    <span class="info-value">
                                        <?= htmlspecialchars($cliente['indirizzo']) ?>
                                        <?php if ($cliente['cap'] || $cliente['citta']): ?>
                                            <br><?= htmlspecialchars($cliente['cap']) ?> <?= htmlspecialchars($cliente['citta']) ?>
                                            <?php if ($cliente['provincia']): ?>(<?= htmlspecialchars($cliente['provincia']) ?>)<?php endif; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cliente['telefono']): ?>
                                <div class="info-row">
                                    <span class="info-label">Telefono</span>
                                    <span class="info-value">
                                        <a href="tel:<?= htmlspecialchars($cliente['telefono']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= htmlspecialchars($cliente['telefono']) ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cliente['email']): ?>
                                <div class="info-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-value">
                                        <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= htmlspecialchars($cliente['email']) ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cliente['pec']): ?>
                                <div class="info-row">
                                    <span class="info-label">PEC</span>
                                    <span class="info-value">
                                        <a href="mailto:<?= htmlspecialchars($cliente['pec']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= htmlspecialchars($cliente['pec']) ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!$cliente['indirizzo'] && !$cliente['telefono'] && !$cliente['email'] && !$cliente['pec']): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📍</div>
                                    <p>Nessun recapito inserito</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Info gestionali -->
                        <div class="info-section">
                            <div class="section-header">
                                <span>⚙️</span>
                                <span>Informazioni Gestionali</span>
                            </div>
                            <div class="section-content">
                                <div class="info-row">
                                    <span class="info-label">Operatore Responsabile</span>
                                    <span class="info-value"><?= htmlspecialchars($cliente['operatore_nome'] ?? 'Non assegnato') ?></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">Cliente dal</span>
                                    <span class="info-value"><?= date('d/m/Y', strtotime($cliente['created_at'])) ?></span>
                                </div>
                                
                                <?php if ($cliente['updated_at']): ?>
                                <div class="info-row">
                                    <span class="info-label">Ultima modifica</span>
                                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($cliente['updated_at'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Note -->
                    <?php if ($cliente['note']): ?>
                    <div class="info-section" style="margin-bottom: 1.5rem;">
                        <div class="section-header">
                            <span>📝</span>
                            <span>Note</span>
                        </div>
                        <div class="section-content">
                            <p style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($cliente['note']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Ultime comunicazioni -->
                    <div class="info-section">
                        <div class="section-header">
                            <span>💬</span>
                            <span>Ultime Comunicazioni</span>
                            <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" 
                               style="margin-left: auto; font-size: 0.875rem; color: var(--primary-green); text-decoration: none;">
                                Vedi tutte →
                            </a>
                        </div>
                        <div class="timeline">
                            <?php if (empty($ultimeComunicazioni)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">💬</div>
                                    <p>Nessuna comunicazione registrata</p>
                                    <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" 
                                       class="btn btn-primary" style="margin-top: 1rem;">
                                        Aggiungi comunicazione
                                    </a>
                                </div>
                            <?php else: ?>
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>