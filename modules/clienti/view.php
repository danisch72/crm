<?php
/**
 * modules/clienti/view.php - Dashboard Cliente CRM Re.De Consulting
 * 
 * ✅ DASHBOARD CLIENTE ULTRA-COMPLETA COMMERCIALISTI
 * 
 * Features:
 * - Overview 360° del cliente con KPI principali
 * - Timeline attività e comunicazioni
 * - Gestione pratiche associate in tempo reale
 * - Alert scadenze e adempimenti
 * - Azioni rapide (call, email, documenti)
 * - Layout ultra-denso uniforme al sistema
 * - Export dati fiscali per dichiarazioni
 * - Sistema note e comunicazioni integrate
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

// Verifica ID cliente
$clienteId = (int)($_GET['id'] ?? 0);
if (!$clienteId) {
    header('Location: /crm/modules/clienti/index.php');
    exit;
}

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_nota':
                $titolo = trim($_POST['titolo'] ?? '');
                $contenuto = trim($_POST['contenuto'] ?? '');
                $tipo = $_POST['tipo'] ?? 'altro';
                
                if (empty($titolo) || empty($contenuto)) {
                    throw new Exception('Titolo e contenuto sono obbligatori');
                }
                
                $notaId = $db->insert('note_clienti', [
                    'cliente_id' => $clienteId,
                    'operatore_id' => $sessionInfo['user_id'],
                    'titolo' => $titolo,
                    'contenuto' => $contenuto,
                    'tipo_nota' => $tipo,
                    'data_nota' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Nota aggiunta con successo']);
                break;
                
            case 'update_operatore':
                $operatoreId = (int)$_POST['operatore_id'];
                
                $updated = $db->update('clienti', [
                    'operatore_responsabile_id' => $operatoreId,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$clienteId]);
                
                echo json_encode(['success' => true, 'message' => 'Operatore responsabile aggiornato']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Azione non valida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Carica dati cliente completi
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
        header('Location: /crm/modules/clienti/index.php?error=not_found');
        exit;
    }
    
    // Statistiche pratiche
    $statsPratiche = $db->selectOne("
        SELECT 
            COUNT(*) as totale,
            SUM(CASE WHEN stato IN ('da_iniziare', 'in_corso') THEN 1 ELSE 0 END) as attive,
            SUM(CASE WHEN stato = 'completata' THEN 1 ELSE 0 END) as completate,
            SUM(CASE WHEN stato = 'sospesa' THEN 1 ELSE 0 END) as sospese,
            SUM(CASE WHEN data_scadenza IS NOT NULL AND data_scadenza < NOW() THEN 1 ELSE 0 END) as scadute,
            AVG(CASE WHEN stato = 'completata' AND ore_lavorate > 0 THEN ore_lavorate END) as media_ore
        FROM pratiche 
        WHERE cliente_id = ?
    ", [$clienteId]) ?: [
        'totale' => 0, 'attive' => 0, 'completate' => 0, 'sospese' => 0, 'scadute' => 0, 'media_ore' => 0
    ];
    
    // Pratiche recenti
    $praticheRecenti = $db->select("
        SELECT 
            p.*,
            s.nome as settore_nome,
            s.colore_hex as settore_colore,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM pratiche p
        LEFT JOIN settori s ON p.settore_id = s.id
        LEFT JOIN operatori o ON p.operatore_assegnato_id = o.id
        WHERE p.cliente_id = ?
        ORDER BY 
            CASE p.stato
                WHEN 'in_corso' THEN 1
                WHEN 'da_iniziare' THEN 2
                WHEN 'sospesa' THEN 3
                WHEN 'completata' THEN 4
            END,
            p.data_scadenza ASC,
            p.created_at DESC
        LIMIT 10
    ", [$clienteId]);
    
    // Note e comunicazioni recenti
    $noteRecenti = $db->select("
        SELECT 
            nc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM note_clienti nc
        LEFT JOIN operatori o ON nc.operatore_id = o.id
        WHERE nc.cliente_id = ?
        ORDER BY nc.data_nota DESC
        LIMIT 10
    ", [$clienteId]);
    
    // Documenti del cliente
    $documenti = $db->select("
        SELECT 
            dc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_upload_nome
        FROM documenti_clienti dc
        LEFT JOIN operatori o ON dc.operatore_id = o.id
        WHERE dc.cliente_id = ?
        ORDER BY dc.data_upload DESC
        LIMIT 5
    ", [$clienteId]);
    
    // Prossime scadenze (simulate - da implementare con sistema scadenze)
    $prossimaScadenza = null;
    
    // Lista operatori per cambio responsabile
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo 
        FROM operatori 
        WHERE is_attivo = 1 
        ORDER BY nome, cognome
    ");
    
} catch (Exception $e) {
    error_log("Errore caricamento cliente $clienteId: " . $e->getMessage());
    header('Location: /crm/modules/clienti/index.php?error=db_error');
    exit;
}

// Funzioni helper
function formatCurrency($amount) {
    return $amount ? '€ ' . number_format($amount, 2, ',', '.') : '-';
}

function getStatusBadge($stato) {
    $badges = [
        'attivo' => '<span class="status-badge status-active">🟢 Attivo</span>',
        'sospeso' => '<span class="status-badge status-suspended">🟡 Sospeso</span>',
        'chiuso' => '<span class="status-badge status-closed">🔴 Chiuso</span>'
    ];
    return $badges[$stato] ?? '<span class="status-badge">❓ Sconosciuto</span>';
}

function getTipologiaIcon($tipologia) {
    $icons = [
        'individuale' => '👤',
        'srl' => '🏢',
        'spa' => '🏭', 
        'snc' => '👥',
        'sas' => '🤝'
    ];
    return $icons[$tipologia] ?? '📋';
}

function formatTelefono($telefono) {
    if (!$telefono) return '';
    return '<a href="tel:' . $telefono . '" class="phone-link">' . $telefono . '</a>';
}

function formatEmail($email) {
    if (!$email) return '';
    return '<a href="mailto:' . $email . '" class="email-link">' . $email . '</a>';
}

function timeAgo($datetime) {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Ora';
    if ($diff < 3600) return floor($diff/60) . 'm fa';
    if ($diff < 86400) return floor($diff/3600) . 'h fa';
    if ($diff < 604800) return floor($diff/86400) . 'g fa';
    return date('d/m/Y', $time);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👁️ <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* Layout Dashboard Cliente Ultra-Denso */
        .cliente-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .cliente-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: skewX(-15deg);
            transform-origin: top;
        }
        
        .header-content {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 2rem;
            align-items: start;
            position: relative;
            z-index: 1;
        }
        
        .cliente-info h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .cliente-dettagli {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .dettaglio-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .dettaglio-label {
            font-size: 0.75rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .dettaglio-value {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .header-stats {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 150px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: var(--radius-md);
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 0.7rem;
            opacity: 0.9;
        }
        
        .header-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 120px;
        }
        
        .action-btn {
            height: 32px;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-content {
            padding: 1rem;
        }
        
        /* Pratiche List */
        .pratiche-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .pratica-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-green);
            transition: all var(--transition-fast);
        }
        
        .pratica-item:hover {
            background: var(--gray-100);
            transform: translateX(2px);
        }
        
        .pratica-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .status-da_iniziare { background: var(--gray-400); }
        .status-in_corso { background: var(--warning-yellow); }
        .status-completata { background: var(--success-green); }
        .status-sospesa { background: var(--danger-red); }
        
        .pratica-info {
            flex: 1;
            min-width: 0;
        }
        
        .pratica-titolo {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .pratica-meta {
            font-size: 0.75rem;
            color: var(--gray-600);
            display: flex;
            gap: 1rem;
        }
        
        /* Note e Timeline */
        .timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .timeline-item {
            display: flex;
            gap: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .timeline-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .timeline-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
            min-width: 0;
        }
        
        .timeline-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .timeline-description {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        
        .timeline-meta {
            font-size: 0.7rem;
            color: var(--gray-500);
            display: flex;
            gap: 1rem;
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-suspended {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-closed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .quick-action {
            height: 60px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            color: var(--gray-700);
        }
        
        .quick-action:hover {
            background: var(--gray-50);
            border-color: var(--primary-green);
            transform: translateY(-1px);
            color: var(--gray-700);
        }
        
        .quick-action-icon {
            font-size: 1.25rem;
        }
        
        .quick-action-label {
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Form nuovo nota */
        .nota-form {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .form-input-sm {
            height: 32px;
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }
        
        .form-textarea-sm {
            min-height: 60px;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            resize: vertical;
        }
        
        .btn-sm {
            height: 32px;
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-primary-sm {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary-sm:hover {
            background: var(--secondary-green);
        }
        
        /* Links */
        .phone-link, .email-link {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .phone-link:hover, .email-link:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .cliente-dettagli {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
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
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header Cliente -->
        <div class="cliente-header">
            <div class="header-content">
                <div class="cliente-info">
                    <h1>
                        <?= getTipologiaIcon($cliente['tipologia_azienda']) ?>
                        <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                        <?= getStatusBadge($cliente['is_attivo'] ? 'attivo' : 'sospeso') ?>
                    </h1>
                    
                    <div class="cliente-dettagli">
                        <?php if ($cliente['codice_fiscale']): ?>
                        <div class="dettaglio-item">
                            <span class="dettaglio-label">Codice Fiscale</span>
                            <span class="dettaglio-value"><?= htmlspecialchars($cliente['codice_fiscale']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['partita_iva']): ?>
                        <div class="dettaglio-item">
                            <span class="dettaglio-label">Partita IVA</span>
                            <span class="dettaglio-value"><?= htmlspecialchars($cliente['partita_iva']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="dettaglio-item">
                            <span class="dettaglio-label">Tipologia</span>
                            <span class="dettaglio-value"><?= ucfirst($cliente['tipologia_azienda']) ?></span>
                        </div>
                        
                        <div class="dettaglio-item">
                            <span class="dettaglio-label">Regime Fiscale</span>
                            <span class="dettaglio-value"><?= ucfirst($cliente['regime_fiscale']) ?></span>
                        </div>
                        
                        <?php if ($cliente['operatore_responsabile_nome']): ?>
                        <div class="dettaglio-item">
                            <span class="dettaglio-label">Operatore Responsabile</span>
                            <span class="dettaglio-value"><?= htmlspecialchars($cliente['operatore_responsabile_nome']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="dettaglio-item">
                            <span class="dettaglio-label">Cliente dal</span>
                            <span class="dettaglio-value"><?= date('d/m/Y', strtotime($cliente['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $statsPratiche['totale'] ?></span>
                        <span class="stat-label">Pratiche</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $statsPratiche['attive'] ?></span>
                        <span class="stat-label">Attive</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= count($noteRecenti) ?></span>
                        <span class="stat-label">Note</span>
                    </div>
                </div>
                
                <div class="header-actions">
                    <a href="/crm/modules/clienti/edit.php?id=<?= $cliente['id'] ?>" class="action-btn">
                        ✏️ Modifica
                    </a>
                    <?php if ($cliente['email']): ?>
                    <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>" class="action-btn">
                        📧 Email
                    </a>
                    <?php endif; ?>
                    <?php if ($cliente['telefono']): ?>
                    <a href="tel:<?= htmlspecialchars($cliente['telefono']) ?>" class="action-btn">
                        📞 Chiama
                    </a>
                    <?php endif; ?>
                    <button class="action-btn" onclick="exportCliente()">
                        📊 Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Azioni Rapide -->
        <div class="quick-actions">
            <a href="/crm/modules/pratiche/create.php?cliente_id=<?= $cliente['id'] ?>" class="quick-action">
                <div class="quick-action-icon">📋</div>
                <div class="quick-action-label">Nuova Pratica</div>
            </a>
            <a href="/crm/modules/scadenze/create.php?cliente_id=<?= $cliente['id'] ?>" class="quick-action">
                <div class="quick-action-icon">⏰</div>
                <div class="quick-action-label">Scadenza</div>
            </a>
            <a href="/crm/modules/clienti/documenti.php?id=<?= $cliente['id'] ?>" class="quick-action">
                <div class="quick-action-icon">📁</div>
                <div class="quick-action-label">Documenti</div>
            </a>
            <button class="quick-action" onclick="showNotaForm()">
                <div class="quick-action-icon">📝</div>
                <div class="quick-action-label">Nuova Nota</div>
            </button>
            <button class="quick-action" onclick="showChangeOperatore()">
                <div class="quick-action-icon">👤</div>
                <div class="quick-action-label">Cambia Op.</div>
            </button>
            <a href="/crm/modules/clienti/comunicazioni.php?id=<?= $cliente['id'] ?>" class="quick-action">
                <div class="quick-action-icon">💬</div>
                <div class="quick-action-label">Comunicazioni</div>
            </a>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Pratiche Associate -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">📋 Pratiche Associate</h3>
                    <a href="/crm/modules/pratiche/index.php?cliente_id=<?= $cliente['id'] ?>" class="btn-sm btn-primary-sm">
                        Vedi tutte
                    </a>
                </div>
                <div class="card-content">
                    <?php if (empty($praticheRecenti)): ?>
                        <p style="text-align: center; color: var(--gray-500); padding: 2rem;">
                            📂 Nessuna pratica associata
                        </p>
                        <div style="text-align: center;">
                            <a href="/crm/modules/pratiche/create.php?cliente_id=<?= $cliente['id'] ?>" 
                               class="btn-sm btn-primary-sm">
                                ➕ Crea prima pratica
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="pratiche-list">
                            <?php foreach ($praticheRecenti as $pratica): ?>
                                <div class="pratica-item">
                                    <div class="pratica-status status-<?= $pratica['stato'] ?>"></div>
                                    <div class="pratica-info">
                                        <div class="pratica-titolo">
                                            <?= htmlspecialchars($pratica['titolo']) ?>
                                        </div>
                                        <div class="pratica-meta">
                                            <span><?= ucfirst($pratica['stato']) ?></span>
                                            <?php if ($pratica['data_scadenza']): ?>
                                                <span>⏰ <?= date('d/m/Y', strtotime($pratica['data_scadenza'])) ?></span>
                                            <?php endif; ?>
                                            <?php if ($pratica['operatore_nome']): ?>
                                                <span>👤 <?= htmlspecialchars($pratica['operatore_nome']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a href="/crm/modules/pratiche/view.php?id=<?= $pratica['id'] ?>" 
                                       class="btn-sm btn-primary-sm">
                                        👁️
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline Attività -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">📝 Timeline Attività</h3>
                    <button class="btn-sm btn-primary-sm" onclick="showNotaForm()">
                        ➕ Nuova
                    </button>
                </div>
                <div class="card-content">
                    <?php if (empty($noteRecenti)): ?>
                        <p style="text-align: center; color: var(--gray-500); padding: 2rem;">
                            📝 Nessuna comunicazione registrata
                        </p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($noteRecenti as $nota): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon">
                                        <?php
                                        $icons = [
                                            'chiamata' => '📞',
                                            'email' => '📧',
                                            'incontro' => '🤝',
                                            'promemoria' => '⏰',
                                            'altro' => '📝'
                                        ];
                                        echo $icons[$nota['tipo_nota']] ?? '📝';
                                        ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title">
                                            <?= htmlspecialchars($nota['titolo']) ?>
                                        </div>
                                        <div class="timeline-description">
                                            <?= htmlspecialchars(substr($nota['contenuto'], 0, 150)) ?>
                                            <?= strlen($nota['contenuto']) > 150 ? '...' : '' ?>
                                        </div>
                                        <div class="timeline-meta">
                                            <span>👤 <?= htmlspecialchars($nota['operatore_nome']) ?></span>
                                            <span>🕒 <?= timeAgo($nota['data_nota']) ?></span>
                                            <span>📂 <?= ucfirst($nota['tipo_nota']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Informazioni Aggiuntive -->
        <div class="dashboard-grid">
            <!-- Contatti e Indirizzi -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">📞 Contatti e Indirizzi</h3>
                </div>
                <div class="card-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <?php if ($cliente['email']): ?>
                        <div>
                            <strong>📧 Email:</strong><br>
                            <?= formatEmail($cliente['email']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['pec']): ?>
                        <div>
                            <strong>📨 PEC:</strong><br>
                            <?= formatEmail($cliente['pec']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['telefono']): ?>
                        <div>
                            <strong>📞 Telefono:</strong><br>
                            <?= formatTelefono($cliente['telefono']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cliente['indirizzo']): ?>
                        <div>
                            <strong>📍 Indirizzo:</strong><br>
                            <?= htmlspecialchars($cliente['indirizzo']) ?><br>
                            <?= htmlspecialchars($cliente['cap']) ?> <?= htmlspecialchars($cliente['citta']) ?> (<?= htmlspecialchars($cliente['provincia']) ?>)
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <strong>⚖️ Liquidazione IVA:</strong><br>
                            <?= ucfirst($cliente['liquidazione_iva']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documenti Recenti -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">📁 Documenti Recenti</h3>
                    <a href="/crm/modules/clienti/documenti.php?id=<?= $cliente['id'] ?>" class="btn-sm btn-primary-sm">
                        Gestisci
                    </a>
                </div>
                <div class="card-content">
                    <?php if (empty($documenti)): ?>
                        <p style="text-align: center; color: var(--gray-500); padding: 1rem;">
                            📂 Nessun documento caricato
                        </p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php foreach ($documenti as $doc): ?>
                                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; background: var(--gray-50); border-radius: var(--radius-md);">
                                    <div style="font-size: 1.25rem;">📄</div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 0.875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($doc['nome_file']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--gray-600);">
                                            <?= ucfirst($doc['categoria']) ?> • <?= timeAgo($doc['data_upload']) ?>
                                        </div>
                                    </div>
                                    <button class="btn-sm btn-primary-sm">👁️</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form Nuova Nota (Hidden) -->
        <div id="notaForm" class="nota-form" style="display: none;">
            <h4 style="margin-bottom: 1rem;">📝 Nuova Comunicazione</h4>
            <form id="formNuovaNota">
                <div class="form-row">
                    <input type="text" name="titolo" placeholder="Oggetto comunicazione..." class="form-input-sm" required>
                    <select name="tipo" class="form-input-sm">
                        <option value="chiamata">📞 Chiamata</option>
                        <option value="email">📧 Email</option>
                        <option value="incontro">🤝 Incontro</option>
                        <option value="promemoria">⏰ Promemoria</option>
                        <option value="altro">📝 Altro</option>
                    </select>
                </div>
                <textarea name="contenuto" placeholder="Descrizione della comunicazione..." 
                          class="form-textarea-sm" style="width: 100%; margin-bottom: 0.75rem;" required></textarea>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn-sm btn-primary-sm">💾 Salva</button>
                    <button type="button" class="btn-sm" onclick="hideNotaForm()">❌ Annulla</button>
                </div>
            </form>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="/crm/assets/js/microinteractions.js"></script>
    <script>
        let notaFormVisible = false;
        
        function showNotaForm() {
            const form = document.getElementById('notaForm');
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth' });
            notaFormVisible = true;
        }
        
        function hideNotaForm() {
            document.getElementById('notaForm').style.display = 'none';
            notaFormVisible = false;
        }
        
        function showChangeOperatore() {
            const operatori = <?= json_encode($operatori) ?>;
            const currentId = <?= $cliente['operatore_responsabile_id'] ?? 'null' ?>;
            
            let options = '<option value="">Seleziona operatore...</option>';
            operatori.forEach(op => {
                const selected = op.id == currentId ? 'selected' : '';
                options += `<option value="${op.id}" ${selected}>${op.nome_completo}</option>`;
            });
            
            const html = `
                <div style="background: white; padding: 1rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; min-width: 300px;">
                    <h4 style="margin-bottom: 1rem;">👤 Cambia Operatore Responsabile</h4>
                    <select id="nuovoOperatore" class="form-input-sm" style="width: 100%; margin-bottom: 1rem;">
                        ${options}
                    </select>
                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <button onclick="updateOperatore()" class="btn-sm btn-primary-sm">💾 Aggiorna</button>
                        <button onclick="closeModal()" class="btn-sm">❌ Annulla</button>
                    </div>
                </div>
                <div onclick="closeModal()" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999;"></div>
            `;
            
            const modal = document.createElement('div');
            modal.innerHTML = html;
            modal.id = 'operatoreModal';
            document.body.appendChild(modal);
        }
        
        function updateOperatore() {
            const operatoreId = document.getElementById('nuovoOperatore').value;
            
            if (!operatoreId) {
                alert('Seleziona un operatore');
                return;
            }
            
            fetch('/crm/modules/clienti/view.php?id=<?= $cliente['id'] ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_operatore&operatore_id=${operatoreId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
                closeModal();
            })
            .catch(error => {
                showNotification('Errore di connessione', 'error');
                console.error('Error:', error);
            });
        }
        
        function closeModal() {
            const modal = document.getElementById('operatoreModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function exportCliente() {
            window.open(`/crm/modules/clienti/export.php?id=<?= $cliente['id'] ?>`, '_blank');
        }
        
        // Form nuova nota
        document.getElementById('formNuovaNota').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_nota');
            
            fetch('/crm/modules/clienti/view.php?id=<?= $cliente['id'] ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Errore di connessione', 'error');
                console.error('Error:', error);
            });
        });
        
        function showNotification(message, type = 'info') {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type);
            } else {
                alert(message);
            }
        }
        
        // Controllo messaggio di creazione completata
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('created') === '1') {
            showNotification('Cliente creato con successo! 🎉', 'success');
        }
        
        console.log('Dashboard cliente caricata per: <?= htmlspecialchars($cliente['ragione_sociale']) ?>');
    </script>
</body>
</html>