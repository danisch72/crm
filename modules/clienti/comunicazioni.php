<?php
/**
 * modules/clienti/comunicazioni.php - Gestione Comunicazioni Cliente CRM Re.De Consulting
 * 
 * ✅ COMMUNICATION LOG PROFESSIONALE COMMERCIALISTI
 * 
 * Features:
 * - Timeline completa comunicazioni cliente
 * - Registro chiamate, email, incontri, note
 * - Form rapido per nuova comunicazione
 * - Filtri per tipo, periodo, operatore
 * - Follow-up automatici e promemoria
 * - Export log per audit e fatturazione
 * - Integrazione calendario per appuntamenti
 * - Statistiche comunicazioni per cliente
 * - Template rapidi per comunicazioni frequenti
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

// Carica dati cliente
try {
    $cliente = $db->selectOne("
        SELECT 
            ragione_sociale, 
            codice_cliente,
            email,
            telefono,
            cellulare,
            pec
        FROM clienti 
        WHERE id = ?
    ", [$clienteId]);
    
    if (!$cliente) {
        header('Location: /crm/modules/clienti/index.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Errore caricamento cliente $clienteId: " . $e->getMessage());
    header('Location: /crm/modules/clienti/index.php?error=db_error');
    exit;
}

$errors = [];
$success = '';

// Parametri filtri
$tipoFiltro = $_GET['tipo'] ?? 'all';
$operatoreFiltro = $_GET['operatore'] ?? 'all';
$periodoFiltro = $_GET['periodo'] ?? '30';
$prioritaFiltro = $_GET['priorita'] ?? 'all';

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_comunicazione':
                $result = addComunicazione();
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
                break;
                
            case 'update_comunicazione':
                $result = updateComunicazione();
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $errors[] = $result['message'];
                }
                break;
                
            case 'complete_followup':
                $notaId = (int)$_POST['nota_id'];
                $updated = $db->update('note_clienti', [
                    'followup_completato' => 1,
                    'stato' => 'completata',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ? AND cliente_id = ?', [$notaId, $clienteId]);
                
                if ($updated) {
                    $success = 'Follow-up marcato come completato';
                } else {
                    $errors[] = 'Errore durante l\'aggiornamento';
                }
                break;
                
            case 'delete_comunicazione':
                $notaId = (int)$_POST['nota_id'];
                
                // Verifica permessi (solo admin o creatore)
                $nota = $db->selectOne("SELECT operatore_id FROM note_clienti WHERE id = ? AND cliente_id = ?", [$notaId, $clienteId]);
                if ($nota && ($sessionInfo['is_admin'] || $nota['operatore_id'] == $sessionInfo['user_id'])) {
                    $deleted = $db->delete('note_clienti', 'id = ?', [$notaId]);
                    if ($deleted) {
                        $success = 'Comunicazione eliminata con successo';
                    } else {
                        $errors[] = 'Errore durante l\'eliminazione';
                    }
                } else {
                    $errors[] = 'Permessi insufficienti';
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Errore gestione comunicazioni: " . $e->getMessage());
        $errors[] = 'Errore interno durante l\'operazione';
    }
}

// Carica comunicazioni con filtri
try {
    $whereConditions = ['nc.cliente_id = ?'];
    $params = [$clienteId];
    
    if ($tipoFiltro !== 'all') {
        $whereConditions[] = 'nc.tipo_nota = ?';
        $params[] = $tipoFiltro;
    }
    
    if ($operatoreFiltro !== 'all' && is_numeric($operatoreFiltro)) {
        $whereConditions[] = 'nc.operatore_id = ?';
        $params[] = (int)$operatoreFiltro;
    }
    
    if ($prioritaFiltro !== 'all') {
        $whereConditions[] = 'nc.priorita = ?';
        $params[] = $prioritaFiltro;
    }
    
    if ($periodoFiltro !== 'all') {
        $whereConditions[] = 'nc.data_nota >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params[] = (int)$periodoFiltro;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $comunicazioni = $db->select("
        SELECT 
            nc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            o.email as operatore_email
        FROM note_clienti nc
        LEFT JOIN operatori o ON nc.operatore_id = o.id
        WHERE $whereClause
        ORDER BY nc.data_nota DESC, nc.priorita DESC
        LIMIT 100
    ", $params);
    
    // Statistiche comunicazioni
    $statsComunicazioni = $db->selectOne("
        SELECT 
            COUNT(*) as totale,
            SUM(CASE WHEN tipo_nota = 'chiamata' THEN 1 ELSE 0 END) as chiamate,
            SUM(CASE WHEN tipo_nota = 'email' THEN 1 ELSE 0 END) as email,
            SUM(CASE WHEN tipo_nota = 'incontro' THEN 1 ELSE 0 END) as incontri,
            SUM(CASE WHEN richiede_followup = 1 AND followup_completato = 0 THEN 1 ELSE 0 END) as followup_pending,
            AVG(CASE WHEN durata_minuti IS NOT NULL THEN durata_minuti END) as durata_media,
            MAX(data_nota) as ultima_comunicazione
        FROM note_clienti 
        WHERE cliente_id = ?
    ", [$clienteId]) ?: [
        'totale' => 0, 'chiamate' => 0, 'email' => 0, 'incontri' => 0,
        'followup_pending' => 0, 'durata_media' => 0, 'ultima_comunicazione' => null
    ];
    
    // Lista operatori per filtri
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo 
        FROM operatori 
        WHERE is_attivo = 1 
        ORDER BY nome, cognome
    ");
    
} catch (Exception $e) {
    error_log("Errore caricamento comunicazioni: " . $e->getMessage());
    $comunicazioni = [];
    $statsComunicazioni = [];
    $operatori = [];
}

// Funzioni per gestione comunicazioni
function addComunicazione() {
    global $clienteId, $db, $sessionInfo;
    
    $data = [
        'cliente_id' => $clienteId,
        'operatore_id' => $sessionInfo['user_id'],
        'titolo' => trim($_POST['titolo'] ?? ''),
        'contenuto' => trim($_POST['contenuto'] ?? ''),
        'tipo_nota' => $_POST['tipo_nota'] ?? 'altro',
        'priorita' => $_POST['priorita'] ?? 'media',
        'stato' => $_POST['stato'] ?? 'completata',
        'canale_comunicazione' => $_POST['canale_comunicazione'] ?? null,
        'durata_minuti' => !empty($_POST['durata_minuti']) ? (int)$_POST['durata_minuti'] : null,
        'richiede_followup' => isset($_POST['richiede_followup']) ? 1 : 0,
        'data_followup' => !empty($_POST['data_followup']) ? $_POST['data_followup'] : null,
        'data_nota' => !empty($_POST['data_nota']) ? $_POST['data_nota'] . ' ' . ($_POST['ora_nota'] ?? '12:00') : date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Validazioni
    if (empty($data['titolo'])) {
        return ['success' => false, 'message' => 'Titolo obbligatorio'];
    }
    
    if (empty($data['contenuto'])) {
        return ['success' => false, 'message' => 'Contenuto obbligatorio'];
    }
    
    try {
        $notaId = $db->insert('note_clienti', $data);
        
        if ($notaId) {
            return ['success' => true, 'message' => 'Comunicazione aggiunta con successo'];
        } else {
            return ['success' => false, 'message' => 'Errore durante il salvataggio'];
        }
    } catch (Exception $e) {
        error_log("Errore inserimento comunicazione: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore interno'];
    }
}

function updateComunicazione() {
    global $clienteId, $db, $sessionInfo;
    
    $notaId = (int)$_POST['nota_id'];
    
    // Verifica permessi
    $nota = $db->selectOne("SELECT operatore_id FROM note_clienti WHERE id = ? AND cliente_id = ?", [$notaId, $clienteId]);
    if (!$nota || (!$sessionInfo['is_admin'] && $nota['operatore_id'] != $sessionInfo['user_id'])) {
        return ['success' => false, 'message' => 'Permessi insufficienti'];
    }
    
    $data = [
        'titolo' => trim($_POST['titolo'] ?? ''),
        'contenuto' => trim($_POST['contenuto'] ?? ''),
        'tipo_nota' => $_POST['tipo_nota'] ?? 'altro',
        'priorita' => $_POST['priorita'] ?? 'media',
        'stato' => $_POST['stato'] ?? 'completata',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        $updated = $db->update('note_clienti', $data, 'id = ?', [$notaId]);
        
        if ($updated) {
            return ['success' => true, 'message' => 'Comunicazione aggiornata con successo'];
        } else {
            return ['success' => false, 'message' => 'Nessuna modifica apportata'];
        }
    } catch (Exception $e) {
        error_log("Errore aggiornamento comunicazione: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore interno'];
    }
}

// Funzioni helper
function getTypeIcon($tipo) {
    $icons = [
        'chiamata' => '📞',
        'email' => '📧',
        'incontro' => '🤝',
        'promemoria' => '⏰',
        'task' => '📋',
        'alert' => '🚨',
        'altro' => '📝'
    ];
    return $icons[$tipo] ?? '📝';
}

function getPriorityBadge($priorita) {
    $badges = [
        'bassa' => '<span style="background: #e5e7eb; color: #6b7280; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Bassa</span>',
        'media' => '<span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Media</span>',
        'alta' => '<span style="background: #fed7d7; color: #c53030; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Alta</span>',
        'urgente' => '<span style="background: #dc2626; color: white; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Urgente</span>'
    ];
    return $badges[$priorita] ?? $badges['media'];
}

function getStatoBadge($stato) {
    $badges = [
        'aperta' => '<span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Aperta</span>',
        'in_corso' => '<span style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">In Corso</span>',
        'completata' => '<span style="background: #dcfce7; color: #166534; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Completata</span>',
        'annullata' => '<span style="background: #f3f4f6; color: #6b7280; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem;">Annullata</span>'
    ];
    return $badges[$stato] ?? $badges['completata'];
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
    <title>💬 Comunicazioni <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* Communication Management Layout */
        .comm-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .comm-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .comm-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .quick-contacts {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .contact-btn {
            height: 36px;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .contact-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        /* Stats Summary */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-green);
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-green);
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Layout Principal */
        .comm-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }
        
        /* Timeline */
        .timeline-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .timeline-header {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .timeline-content {
            padding: 1rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .timeline-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--gray-300);
            transition: all var(--transition-fast);
            position: relative;
        }
        
        .timeline-item:hover {
            transform: translateX(2px);
            box-shadow: var(--shadow-sm);
        }
        
        .timeline-item.priority-alta {
            border-left-color: var(--warning-yellow);
        }
        
        .timeline-item.priority-urgente {
            border-left-color: var(--danger-red);
        }
        
        .timeline-item.has-followup {
            border-left-color: var(--accent-blue);
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .timeline-content-item {
            flex: 1;
            min-width: 0;
        }
        
        .timeline-header-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .timeline-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .timeline-actions {
            display: flex;
            gap: 0.25rem;
        }
        
        .btn-micro {
            width: 20px;
            height: 20px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: all var(--transition-fast);
        }
        
        .btn-edit {
            background: var(--warning-yellow);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger-red);
            color: white;
        }
        
        .btn-complete {
            background: var(--success-green);
            color: white;
        }
        
        .timeline-description {
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }
        
        .timeline-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .followup-alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Form Nuova Comunicazione */
        .new-comm-form {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .form-header {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-content {
            padding: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-field.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            height: 36px;
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: border-color var(--transition-fast);
        }
        
        .form-textarea {
            height: 80px;
            resize: vertical;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-green);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        
        .btn {
            height: 36px;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
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
            height: 32px;
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            background: white;
        }
        
        /* Templates Rapidi */
        .templates-section {
            margin-bottom: 1rem;
        }
        
        .templates-list {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .template-btn {
            height: 28px;
            padding: 0.25rem 0.5rem;
            background: var(--gray-100);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .template-btn:hover {
            background: var(--gray-200);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .comm-layout {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
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
        <div class="comm-container">
            <!-- Header -->
            <div class="comm-header">
                <div class="comm-title">
                    💬 Comunicazioni: <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                    <span style="font-size: 0.875rem; opacity: 0.8;">(<?= count($comunicazioni) ?> comunicazioni)</span>
                </div>
                <div class="quick-contacts">
                    <?php if ($cliente['telefono']): ?>
                        <a href="tel:<?= htmlspecialchars($cliente['telefono']) ?>" class="contact-btn">
                            📞 <?= htmlspecialchars($cliente['telefono']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($cliente['cellulare']): ?>
                        <a href="tel:<?= htmlspecialchars($cliente['cellulare']) ?>" class="contact-btn">
                            📱 <?= htmlspecialchars($cliente['cellulare']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($cliente['email']): ?>
                        <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>" class="contact-btn">
                            📧 Email
                        </a>
                    <?php endif; ?>
                    <a href="/crm/modules/clienti/view.php?id=<?= $clienteId ?>" class="contact-btn">
                        👁️ Dashboard
                    </a>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($errors)): ?>
                <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <div>⚠️ <?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiche Summary -->
            <div class="stats-summary">
                <div class="stat-card">
                    <span class="stat-number"><?= $statsComunicazioni['totale'] ?></span>
                    <div class="stat-label">Totale</div>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $statsComunicazioni['chiamate'] ?></span>
                    <div class="stat-label">Chiamate</div>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $statsComunicazioni['email'] ?></span>
                    <div class="stat-label">Email</div>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $statsComunicazioni['incontri'] ?></span>
                    <div class="stat-label">Incontri</div>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $statsComunicazioni['followup_pending'] ?></span>
                    <div class="stat-label">Follow-up Pending</div>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= number_format($statsComunicazioni['durata_media'], 0) ?></span>
                    <div class="stat-label">Min. Media</div>
                </div>
            </div>

            <!-- Filtri -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label class="filter-label">Tipo:</label>
                    <select class="filter-select" onchange="applyFilters()" id="tipoFiltro">
                        <option value="all" <?= $tipoFiltro === 'all' ? 'selected' : '' ?>>Tutti</option>
                        <option value="chiamata" <?= $tipoFiltro === 'chiamata' ? 'selected' : '' ?>>📞 Chiamate</option>
                        <option value="email" <?= $tipoFiltro === 'email' ? 'selected' : '' ?>>📧 Email</option>
                        <option value="incontro" <?= $tipoFiltro === 'incontro' ? 'selected' : '' ?>>🤝 Incontri</option>
                        <option value="promemoria" <?= $tipoFiltro === 'promemoria' ? 'selected' : '' ?>>⏰ Promemoria</option>
                        <option value="task" <?= $tipoFiltro === 'task' ? 'selected' : '' ?>>📋 Task</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Periodo:</label>
                    <select class="filter-select" onchange="applyFilters()" id="periodoFiltro">
                        <option value="7" <?= $periodoFiltro === '7' ? 'selected' : '' ?>>Ultima settimana</option>
                        <option value="30" <?= $periodoFiltro === '30' ? 'selected' : '' ?>>Ultimo mese</option>
                        <option value="90" <?= $periodoFiltro === '90' ? 'selected' : '' ?>>Ultimi 3 mesi</option>
                        <option value="365" <?= $periodoFiltro === '365' ? 'selected' : '' ?>>Ultimo anno</option>
                        <option value="all" <?= $periodoFiltro === 'all' ? 'selected' : '' ?>>Tutte</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Operatore:</label>
                    <select class="filter-select" onchange="applyFilters()" id="operatoreFiltro">
                        <option value="all" <?= $operatoreFiltro === 'all' ? 'selected' : '' ?>>Tutti</option>
                        <?php foreach ($operatori as $op): ?>
                            <option value="<?= $op['id'] ?>" <?= $operatoreFiltro == $op['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($op['nome_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-left: auto;">
                    <button class="btn btn-secondary" onclick="exportComunicazioni()">
                        📊 Export Log
                    </button>
                </div>
            </div>

            <!-- Layout Principale -->
            <div class="comm-layout">
                <!-- Timeline Comunicazioni -->
                <div class="timeline-container">
                    <div class="timeline-header">
                        <h3>📝 Timeline Comunicazioni</h3>
                        <button class="btn btn-secondary" onclick="refreshTimeline()">🔄 Aggiorna</button>
                    </div>
                    <div class="timeline-content">
                        <?php if (empty($comunicazioni)): ?>
                            <div class="empty-state">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">💬</div>
                                <h3>Nessuna comunicazione</h3>
                                <p>Inizia registrando la prima comunicazione con questo cliente</p>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($comunicazioni as $comm): ?>
                                    <div class="timeline-item priority-<?= $comm['priorita'] ?> <?= $comm['richiede_followup'] && !$comm['followup_completato'] ? 'has-followup' : '' ?>">
                                        <div class="timeline-icon">
                                            <?= getTypeIcon($comm['tipo_nota']) ?>
                                        </div>
                                        <div class="timeline-content-item">
                                            <div class="timeline-header-item">
                                                <div class="timeline-title"><?= htmlspecialchars($comm['titolo']) ?></div>
                                                <div class="timeline-actions">
                                                    <?php if ($comm['richiede_followup'] && !$comm['followup_completato']): ?>
                                                        <button class="btn-micro btn-complete" onclick="completeFollowup(<?= $comm['id'] ?>)" title="Completa Follow-up">
                                                            ✓
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($sessionInfo['is_admin'] || $comm['operatore_id'] == $sessionInfo['user_id']): ?>
                                                        <button class="btn-micro btn-edit" onclick="editComunicazione(<?= $comm['id'] ?>)" title="Modifica">
                                                            ✏️
                                                        </button>
                                                        <button class="btn-micro btn-delete" onclick="deleteComunicazione(<?= $comm['id'] ?>)" title="Elimina">
                                                            🗑️
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="timeline-description">
                                                <?= nl2br(htmlspecialchars($comm['contenuto'])) ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <span><?= getPriorityBadge($comm['priorita']) ?></span>
                                                <span><?= getStatoBadge($comm['stato']) ?></span>
                                                <span>👤 <?= htmlspecialchars($comm['operatore_nome']) ?></span>
                                                <span>🕒 <?= timeAgo($comm['data_nota']) ?></span>
                                                <?php if ($comm['durata_minuti']): ?>
                                                    <span>⏱️ <?= $comm['durata_minuti'] ?> min</span>
                                                <?php endif; ?>
                                                <?php if ($comm['canale_comunicazione']): ?>
                                                    <span>📡 <?= ucfirst($comm['canale_comunicazione']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($comm['richiede_followup'] && !$comm['followup_completato']): ?>
                                                <div class="followup-alert">
                                                    ⏰ Follow-up richiesto<?= $comm['data_followup'] ? ' entro il ' . date('d/m/Y', strtotime($comm['data_followup'])) : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Nuova Comunicazione -->
                <div class="new-comm-form">
                    <div class="form-header">
                        <h3>➕ Nuova Comunicazione</h3>
                    </div>
                    <div class="form-content">
                        <!-- Templates Rapidi -->
                        <div class="templates-section">
                            <div style="font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">⚡ Template Rapidi:</div>
                            <div class="templates-list">
                                <button class="template-btn" onclick="applyTemplate('chiamata')">📞 Chiamata</button>
                                <button class="template-btn" onclick="applyTemplate('email')">📧 Email</button>
                                <button class="template-btn" onclick="applyTemplate('incontro')">🤝 Incontro</button>
                                <button class="template-btn" onclick="applyTemplate('promemoria')">⏰ Promemoria</button>
                                <button class="template-btn" onclick="applyTemplate('richiesta_doc')">📋 Richiesta Doc</button>
                            </div>
                        </div>
                        
                        <form method="POST" id="commForm">
                            <input type="hidden" name="action" value="add_comunicazione">
                            
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">Tipo Comunicazione:</label>
                                    <select name="tipo_nota" class="form-select" id="tipoSelect">
                                        <option value="chiamata">📞 Chiamata</option>
                                        <option value="email">📧 Email</option>
                                        <option value="incontro">🤝 Incontro</option>
                                        <option value="promemoria">⏰ Promemoria</option>
                                        <option value="task">📋 Task</option>
                                        <option value="altro">📝 Altro</option>
                                    </select>
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Priorità:</label>
                                    <select name="priorita" class="form-select">
                                        <option value="bassa">Bassa</option>
                                        <option value="media" selected>Media</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>
                                
                                <div class="form-field full-width">
                                    <label class="form-label">Titolo/Oggetto:</label>
                                    <input type="text" name="titolo" class="form-input" id="titoloInput" 
                                           placeholder="Descrizione breve della comunicazione..." required>
                                </div>
                                
                                <div class="form-field full-width">
                                    <label class="form-label">Contenuto Dettagliato:</label>
                                    <textarea name="contenuto" class="form-textarea" id="contenutoTextarea" 
                                              placeholder="Descrizione dettagliata, argomenti trattati, note..." required></textarea>
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Data Comunicazione:</label>
                                    <input type="date" name="data_nota" class="form-input" 
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Ora:</label>
                                    <input type="time" name="ora_nota" class="form-input" 
                                           value="<?= date('H:i') ?>">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Durata (minuti):</label>
                                    <input type="number" name="durata_minuti" class="form-input" 
                                           placeholder="15" min="1" max="480">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Canale:</label>
                                    <select name="canale_comunicazione" class="form-select">
                                        <option value="">Non specificato</option>
                                        <option value="telefono">Telefono</option>
                                        <option value="email">Email</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="teams">Teams</option>
                                        <option value="zoom">Zoom</option>
                                        <option value="presenza">Di persona</option>
                                    </select>
                                </div>
                                
                                <div class="form-field full-width">
                                    <div class="form-checkbox">
                                        <input type="checkbox" name="richiede_followup" id="followupCheck">
                                        <label for="followupCheck">Richiede follow-up</label>
                                    </div>
                                </div>
                                
                                <div class="form-field" id="followupDateField" style="display: none;">
                                    <label class="form-label">Data Follow-up:</label>
                                    <input type="date" name="data_followup" class="form-input" 
                                           value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    🗑️ Reset
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    💾 Salva Comunicazione
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            setupFollowupToggle();
            setupFormValidation();
            
            console.log('Gestione comunicazioni caricata per cliente <?= $clienteId ?>');
        });
        
        function setupFollowupToggle() {
            const followupCheck = document.getElementById('followupCheck');
            const followupField = document.getElementById('followupDateField');
            
            followupCheck.addEventListener('change', function() {
                followupField.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        function setupFormValidation() {
            const form = document.getElementById('commForm');
            
            form.addEventListener('submit', function(e) {
                const titolo = document.getElementById('titoloInput').value.trim();
                const contenuto = document.getElementById('contenutoTextarea').value.trim();
                
                if (!titolo || !contenuto) {
                    e.preventDefault();
                    alert('Titolo e contenuto sono obbligatori');
                    return false;
                }
            });
        }
        
        // Template functions
        function applyTemplate(type) {
            const templates = {
                chiamata: {
                    tipo: 'chiamata',
                    titolo: 'Chiamata telefonica',
                    contenuto: 'Argomenti discussi:\n- \n\nProssimi passi:\n- '
                },
                email: {
                    tipo: 'email',
                    titolo: 'Comunicazione via email',
                    contenuto: 'Email inviata riguardo:\n- \n\nRisposta ricevuta:\n- '
                },
                incontro: {
                    tipo: 'incontro',
                    titolo: 'Incontro presso ufficio',
                    contenuto: 'Incontro per:\n- \n\nPresenti:\n- \n\nDecisioni:\n- '
                },
                promemoria: {
                    tipo: 'promemoria',
                    titolo: 'Promemoria importante',
                    contenuto: 'Promemoria per:\n- \n\nScadenza:\n- '
                },
                richiesta_doc: {
                    tipo: 'task',
                    titolo: 'Richiesta documentazione',
                    contenuto: 'Documenti richiesti:\n- \n\nScadenza consegna:\n- '
                }
            };
            
            const template = templates[type];
            if (template) {
                document.querySelector('[name="tipo_nota"]').value = template.tipo;
                document.getElementById('titoloInput').value = template.titolo;
                document.getElementById('contenutoTextarea').value = template.contenuto;
            }
        }
        
        // Filter functions
        function applyFilters() {
            const tipo = document.getElementById('tipoFiltro').value;
            const periodo = document.getElementById('periodoFiltro').value;
            const operatore = document.getElementById('operatoreFiltro').value;
            
            const params = new URLSearchParams(window.location.search);
            params.set('tipo', tipo);
            params.set('periodo', periodo);
            params.set('operatore', operatore);
            
            window.location.search = params.toString();
        }
        
        function refreshTimeline() {
            location.reload();
        }
        
        function exportComunicazioni() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'true');
            
            window.open('?' + params.toString(), '_blank');
        }
        
        // CRUD functions
        function completeFollowup(id) {
            if (!confirm('Sicuro di voler marcare questo follow-up come completato?')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="complete_followup">
                <input type="hidden" name="nota_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function editComunicazione(id) {
            // TODO: Implementare modal di modifica
            alert('Funzione di modifica in sviluppo');
        }
        
        function deleteComunicazione(id) {
            if (!confirm('Sicuro di voler eliminare questa comunicazione? L\'azione non può essere annullata.')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_comunicazione">
                <input type="hidden" name="nota_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function resetForm() {
            document.getElementById('commForm').reset();
            document.getElementById('followupDateField').style.display = 'none';
        }
        
        // Auto-save draft ogni 30 secondi
        setInterval(() => {
            const titolo = document.getElementById('titoloInput').value.trim();
            const contenuto = document.getElementById('contenutoTextarea').value.trim();
            
            if (titolo && contenuto) {
                // Salva bozza in localStorage
                const draft = {
                    titolo: titolo,
                    contenuto: contenuto,
                    tipo: document.querySelector('[name="tipo_nota"]').value,
                    timestamp: Date.now()
                };
                localStorage.setItem('comm_draft_<?= $clienteId ?>', JSON.stringify(draft));
            }
        }, 30000);
        
        // Recupera bozza al caricamento
        window.addEventListener('load', function() {
            const draft = localStorage.getItem('comm_draft_<?= $clienteId ?>');
            if (draft) {
                const data = JSON.parse(draft);
                // Se la bozza è più vecchia di 24 ore, ignorala
                if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                    if (confirm('Trovata bozza non salvata. Vuoi ripristinarla?')) {
                        document.getElementById('titoloInput').value = data.titolo;
                        document.getElementById('contenutoTextarea').value = data.contenuto;
                        document.querySelector('[name="tipo_nota"]').value = data.tipo;
                    }
                }
                localStorage.removeItem('comm_draft_<?= $clienteId ?>');
            }
        });
    </script>
</body>
</html>