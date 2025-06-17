<?php
/**
 * modules/operatori/index_list.php - Lista Operatori CRM Re.De Consulting
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
$pageTitle = 'Gestione Operatori';
$pageIcon = '👥';

// **LOGICA ESISTENTE MANTENUTA** - Ottieni parametri filtro
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$role = $_GET['role'] ?? 'all';

// **LOGICA ESISTENTE MANTENUTA** - Carica statistiche (solo per admin)
$stats = null;
if ($sessionInfo['is_admin']) {
    try {
        $stats = $db->selectOne("
            SELECT 
                COUNT(*) as totale_operatori,
                SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as operatori_attivi,
                SUM(CASE WHEN is_attivo = 0 THEN 1 ELSE 0 END) as operatori_inattivi,
                SUM(CASE WHEN is_amministratore = 1 THEN 1 ELSE 0 END) as amministratori,
                COUNT(DISTINCT CASE WHEN DATE(last_login) = CURDATE() THEN id END) as sessioni_attive,
                NOW() as ultimo_aggiornamento
            FROM operatori
        ");
    } catch (Exception $e) {
        error_log("Errore caricamento statistiche: " . $e->getMessage());
    }
}

// **LOGICA ESISTENTE MANTENUTA** - Query principale con filtri
$query = "SELECT 
    o.*,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM sessioni_lavoro s 
            WHERE s.operatore_id = o.id 
            AND s.logout_timestamp IS NULL 
            AND DATE(s.login_timestamp) = CURDATE()
        ) THEN 1 
        ELSE 0 
    END as is_online,
    (SELECT COUNT(*) FROM clienti c WHERE c.operatore_responsabile_id = o.id AND c.is_attivo = 1) as clienti_attivi,
    (SELECT MAX(login_timestamp) FROM sessioni_lavoro s WHERE s.operatore_id = o.id) as ultima_sessione
FROM operatori o
WHERE 1=1";

$params = [];

// Applica filtri
if ($search) {
    $query .= " AND (o.nome LIKE ? OR o.cognome LIKE ? OR o.email LIKE ? OR o.codice_operatore LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($status === 'active') {
    $query .= " AND o.is_attivo = 1";
} elseif ($status === 'inactive') {
    $query .= " AND o.is_attivo = 0";
}

if ($role === 'admin') {
    $query .= " AND o.is_amministratore = 1";
} elseif ($role === 'user') {
    $query .= " AND o.is_amministratore = 0";
}

$query .= " ORDER BY o.is_attivo DESC, o.cognome ASC, o.nome ASC";

// Esegui query
$operatori = [];
try {
    $operatori = $db->select($query, $params);
} catch (Exception $e) {
    error_log("Errore caricamento operatori: " . $e->getMessage());
}

// Helper functions per la vista
function formatLastLogin($timestamp) {
    if (!$timestamp) return 'Mai';
    
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        return 'Oggi alle ' . $date->format('H:i');
    } elseif ($diff->days == 1) {
        return 'Ieri alle ' . $date->format('H:i');
    } elseif ($diff->days < 7) {
        return $diff->days . ' giorni fa';
    } else {
        return $date->format('d/m/Y');
    }
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
        /* Stili specifici per lista operatori ultra-compressa */
        .operators-container {
            padding: 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .operators-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .operators-header h2 {
            font-size: 1.5rem;
            color: var(--gray-900);
            margin: 0;
        }
        
        .operators-header p {
            color: var(--gray-600);
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        /* Statistiche inline ultra-compatte */
        .stats-inline {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius-lg);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 2rem;
            align-items: center;
            font-size: 0.8125rem;
        }
        
        .stat-compact {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
        }
        
        .stat-compact .stat-icon {
            font-size: 1rem;
        }
        
        .stat-compact span:last-child {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        /* Filtri compatti */
        .filters-container {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
        }
        
        .filters-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .filters-row > div {
            flex: 1;
        }
        
        /* Tabella ultra-densa */
        .operators-table {
            background: white;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead th {
            background: var(--gray-50);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
        }
        
        .table tbody td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }
        
        .table tbody tr:hover {
            background: var(--gray-50);
        }
        
        /* Operator info cell */
        .operator-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .operator-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-green-light);
            color: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .operator-details {
            flex: 1;
        }
        
        .operator-name {
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .operator-email {
            color: var(--gray-500);
            font-size: 0.75rem;
            margin: 0;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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
        
        /* Role badge */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 4px;
            background: var(--primary-green-light);
            color: var(--primary-green);
        }
        
        /* Actions */
        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-action.primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-action.primary:hover {
            background: var(--primary-green-hover);
            transform: translateY(-1px);
        }
        
        .btn-action.secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .btn-action.secondary:hover {
            background: var(--gray-200);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .operators-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .stats-inline {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .operator-info {
                flex-direction: column;
                text-align: center;
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
                <div class="operators-container">
                    <!-- Messaggi Flash -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Header con azioni -->
                    <div class="operators-header">
                        <div>
                            <h2>👥 Gestione Operatori</h2>
                            <p>Visualizza e gestisci tutti gli operatori del sistema</p>
                        </div>
                        
                        <div class="header-actions">
                            <?php if ($sessionInfo['is_admin']): ?>
                                <a href="/crm/?action=operatori&view=create" class="btn btn-primary">
                                    ➕ Nuovo Operatore
                                </a>
                                <a href="/crm/?action=operatori&view=stats" class="btn btn-secondary">
                                    📊 Statistiche Team
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Statistiche Inline (Solo Admin) -->
                    <?php if ($sessionInfo['is_admin'] && $stats): ?>
                    <div class="stats-inline">
                        <div class="stat-compact">
                            <span class="stat-icon">👥</span>
                            <span><?= $stats['totale_operatori'] ?? 0 ?> Operatori</span>
                        </div>
                        
                        <div class="stat-compact">
                            <span class="stat-icon">✅</span>
                            <span><?= $stats['operatori_attivi'] ?? 0 ?> Attivi</span>
                        </div>
                        
                        <div class="stat-compact">
                            <span class="stat-icon">👑</span>
                            <span><?= $stats['amministratori'] ?? 0 ?> Admin</span>
                        </div>
                        
                        <div class="stat-compact">
                            <span class="stat-icon">🟢</span>
                            <span><?= $stats['sessioni_attive'] ?? 0 ?> Online</span>
                        </div>
                        
                        <div class="stat-compact">
                            <span class="stat-icon">🔄</span>
                            <span>Aggiornato: <?= date('H:i') ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filtri -->
                    <div class="filters-container">
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="action" value="operatori">
                            <div class="filters-row">
                                <div>
                                    <input type="text" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="🔍 Cerca per nome, cognome o email..." 
                                           class="form-control">
                                </div>
                                
                                <div>
                                    <select name="status" class="form-control">
                                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tutti gli stati</option>
                                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Solo attivi</option>
                                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Solo inattivi</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <select name="role" class="form-control">
                                        <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>Tutti i ruoli</option>
                                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Amministratori</option>
                                        <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Operatori</option>
                                    </select>
                                </div>
                                
                                <div style="flex: 0 0 auto;">
                                    <button type="submit" class="btn btn-primary">
                                        Filtra
                                    </button>
                                    
                                    <?php if ($search || $status !== 'all' || $role !== 'all'): ?>
                                    <a href="/crm/?action=operatori" class="btn btn-secondary">
                                        Reset
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Tabella Operatori -->
                    <div class="operators-table">
                        <?php if (empty($operatori)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">👥</div>
                                <p>Nessun operatore trovato</p>
                                <p style="font-size: 0.875rem; color: var(--gray-500);">
                                    Prova a modificare i filtri di ricerca
                                </p>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Operatore</th>
                                        <th style="width: 15%;">Ruolo</th>
                                        <th style="width: 10%;">Stato</th>
                                        <th style="width: 10%;">Clienti</th>
                                        <th style="width: 15%;">Ultimo Accesso</th>
                                        <th style="width: 10%; text-align: right;">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($operatori as $op): ?>
                                    <tr>
                                        <td>
                                            <div class="operator-info">
                                                <div class="operator-avatar">
                                                    <?= strtoupper(substr($op['nome'], 0, 1) . substr($op['cognome'], 0, 1)) ?>
                                                </div>
                                                <div class="operator-details">
                                                    <div class="operator-name">
                                                        <?= htmlspecialchars($op['cognome'] . ' ' . $op['nome']) ?>
                                                    </div>
                                                    <div class="operator-email">
                                                        <?= htmlspecialchars($op['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($op['is_amministratore']): ?>
                                                <span class="role-badge">👑 Admin</span>
                                            <?php else: ?>
                                                <span style="color: var(--gray-600); font-size: 0.875rem;">Operatore</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $op['is_attivo'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $op['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                                            </span>
                                            <?php if ($op['is_online']): ?>
                                                <span class="status-badge status-online" style="margin-left: 0.25rem;">
                                                    🟢 Online
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight: 600;"><?= $op['clienti_attivi'] ?></span>
                                            <span style="color: var(--gray-500); font-size: 0.75rem;">clienti</span>
                                        </td>
                                        <td>
                                            <span style="color: var(--gray-600); font-size: 0.8125rem;">
                                                <?= formatLastLogin($op['ultima_sessione']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="/crm/?action=operatori&view=view&id=<?= $op['id'] ?>" 
                                                   class="btn-action secondary"
                                                   title="Visualizza dettagli">
                                                    👁️ Vedi
                                                </a>
                                                
                                                <?php if ($sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $op['id']): ?>
                                                <a href="/crm/?action=operatori&view=edit&id=<?= $op['id'] ?>" 
                                                   class="btn-action primary"
                                                   title="Modifica operatore">
                                                    ✏️ Modifica
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>