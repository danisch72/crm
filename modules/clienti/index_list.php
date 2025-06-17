<?php
/**
 * modules/clienti/index_list.php - Lista Clienti CRM Re.De Consulting
 * 
 * ✅ VERSIONE DEFINITIVA CON COMPONENTI CENTRALIZZATI
 * ✅ LAYOUT ULTRA-COMPATTO DATEV OPTIMAL
 * 
 * Features:
 * - Sidebar e header centralizzati
 * - Design system datev-optimal.css
 * - Layout tabellare denso professionale
 * - Filtri avanzati compatti
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
$pageTitle = 'Gestione Clienti';
$pageIcon = '🏢';

// Gestione filtri
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$tipologia = $_GET['tipologia'] ?? 'all';
$operatore = $_GET['operatore'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;

// Carica lista operatori per filtro
$operatori = [];
try {
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo
        FROM operatori
        WHERE is_attivo = 1
        ORDER BY cognome, nome
    ");
} catch (Exception $e) {
    error_log("Errore caricamento operatori: " . $e->getMessage());
}

// Query clienti con filtri
$whereConditions = ["1=1"];
$params = [];

if ($search) {
    $whereConditions[] = "(
        ragione_sociale LIKE ? OR 
        codice_fiscale LIKE ? OR 
        partita_iva LIKE ? OR
        email LIKE ? OR
        telefono LIKE ?
    )";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($status !== 'all') {
    $whereConditions[] = "stato = ?";
    $params[] = $status;
}

if ($tipologia !== 'all') {
    $whereConditions[] = "tipologia_azienda = ?";
    $params[] = $tipologia;
}

if ($operatore !== 'all' && is_numeric($operatore)) {
    $whereConditions[] = "operatore_responsabile_id = ?";
    $params[] = $operatore;
}

$whereClause = implode(' AND ', $whereConditions);

// Conta totale per paginazione
$totalCount = 0;
try {
    $countResult = $db->selectOne("
        SELECT COUNT(*) as total 
        FROM clienti c
        WHERE $whereClause
    ", $params);
    $totalCount = $countResult ? $countResult['total'] : 0;
} catch (Exception $e) {
    error_log("Errore conteggio clienti: " . $e->getMessage());
}

$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Carica clienti
$clienti = [];
try {
    $clienti = $db->select("
        SELECT 
            c.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            (SELECT COUNT(*) FROM pratiche WHERE cliente_id = c.id) as totale_pratiche,
            (SELECT COUNT(*) FROM pratiche WHERE cliente_id = c.id AND stato = 'attiva') as pratiche_attive,
            (SELECT MAX(created_at) FROM comunicazioni_clienti WHERE cliente_id = c.id) as ultima_comunicazione
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
        WHERE $whereClause
        ORDER BY c.ragione_sociale ASC
        LIMIT $perPage OFFSET $offset
    ", $params);
} catch (Exception $e) {
    error_log("Errore caricamento clienti: " . $e->getMessage());
    $error_message = "Errore nel caricamento dei clienti";
}

// Statistiche rapide
$stats = [
    'totali' => $totalCount,
    'attivi' => 0,
    'sospesi' => 0,
    'chiusi' => 0
];

try {
    $statsData = $db->selectOne("
        SELECT 
            COUNT(CASE WHEN stato = 'attivo' THEN 1 END) as attivi,
            COUNT(CASE WHEN stato = 'sospeso' THEN 1 END) as sospesi,
            COUNT(CASE WHEN stato = 'chiuso' THEN 1 END) as chiusi
        FROM clienti
        WHERE $whereClause
    ", $params);
    
    if ($statsData) {
        $stats = array_merge($stats, $statsData);
    }
} catch (Exception $e) {
    error_log("Errore statistiche: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Stili specifici ultra-compatti */
        .filters-section {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
        }
        
        .filters-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters-form .form-control {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table {
            font-size: 0.813rem;
        }
        
        .table th {
            padding: 0.625rem 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.025em;
            background: var(--gray-50);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table td {
            padding: 0.5rem 0.75rem;
            vertical-align: middle;
        }
        
        .cliente-info {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }
        
        .cliente-nome {
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .cliente-meta {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
        }
        
        .page-link {
            padding: 0.375rem 0.625rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            color: var(--gray-700);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .page-link:hover {
            background: var(--gray-50);
        }
        
        .page-link.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
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
                <div class="container">
                    <!-- Messaggi -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filtri -->
                    <div class="filters-section">
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="action" value="clienti">
                            
                            <input type="text" 
                                   name="search" 
                                   placeholder="🔍 Cerca cliente..." 
                                   value="<?= htmlspecialchars($search) ?>"
                                   class="form-control" 
                                   style="flex: 1; min-width: 200px;">
                            
                            <select name="status" class="form-control">
                                <option value="all">Tutti gli stati</option>
                                <option value="attivo" <?= $status === 'attivo' ? 'selected' : '' ?>>✅ Attivi</option>
                                <option value="sospeso" <?= $status === 'sospeso' ? 'selected' : '' ?>>⚠️ Sospesi</option>
                                <option value="chiuso" <?= $status === 'chiuso' ? 'selected' : '' ?>>🔴 Chiusi</option>
                            </select>
                            
                            <select name="tipologia" class="form-control">
                                <option value="all">Tutte le tipologie</option>
                                <option value="individuale" <?= $tipologia === 'individuale' ? 'selected' : '' ?>>👤 Individuale</option>
                                <option value="srl" <?= $tipologia === 'srl' ? 'selected' : '' ?>>🏢 SRL</option>
                                <option value="spa" <?= $tipologia === 'spa' ? 'selected' : '' ?>>🏭 SPA</option>
                                <option value="snc" <?= $tipologia === 'snc' ? 'selected' : '' ?>>👥 SNC</option>
                                <option value="sas" <?= $tipologia === 'sas' ? 'selected' : '' ?>>🤝 SAS</option>
                            </select>
                            
                            <?php if ($sessionInfo['is_admin']): ?>
                            <select name="operatore" class="form-control">
                                <option value="all">Tutti gli operatori</option>
                                <?php foreach ($operatori as $op): ?>
                                    <option value="<?= $op['id'] ?>" <?= $operatore == $op['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($op['nome_completo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">
                                <span>Filtra</span>
                            </button>
                            
                            <?php if ($search || $status !== 'all' || $tipologia !== 'all' || $operatore !== 'all'): ?>
                            <a href="/crm/?action=clienti" class="btn btn-secondary">
                                <span>Reset</span>
                            </a>
                            <?php endif; ?>
                            
                            <a href="/crm/?action=clienti&view=create" class="btn btn-primary">
                                <span>➕ Nuovo Cliente</span>
                            </a>
                        </form>
                    </div>
                    
                    <!-- Statistiche -->
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($stats['totali']) ?></div>
                            <div class="stat-label">Totali</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--color-success);">
                                <?= number_format($stats['attivi']) ?>
                            </div>
                            <div class="stat-label">Attivi</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--color-warning);">
                                <?= number_format($stats['sospesi']) ?>
                            </div>
                            <div class="stat-label">Sospesi</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="color: var(--color-danger);">
                                <?= number_format($stats['chiusi']) ?>
                            </div>
                            <div class="stat-label">Chiusi</div>
                        </div>
                    </div>
                    
                    <!-- Tabella clienti -->
                    <div class="table-container">
                        <?php if (empty($clienti)): ?>
                            <div class="empty-state" style="padding: 3rem; text-align: center;">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">🏢</div>
                                <p style="color: var(--gray-600);">Nessun cliente trovato</p>
                                <p style="font-size: 0.875rem; color: var(--gray-500);">
                                    Prova a modificare i filtri o aggiungi un nuovo cliente
                                </p>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Cliente</th>
                                        <th style="width: 120px;">Tipologia</th>
                                        <th style="width: 100px;">Stato</th>
                                        <th style="width: 150px;">Operatore</th>
                                        <th style="width: 80px; text-align: center;">Pratiche</th>
                                        <th style="width: 120px;">Ultimo Contatto</th>
                                        <th style="width: 120px; text-align: right;">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clienti as $index => $cliente): ?>
                                        <tr>
                                            <td style="color: var(--gray-500);">
                                                <?= $offset + $index + 1 ?>
                                            </td>
                                            <td>
                                                <div class="cliente-info">
                                                    <div class="cliente-nome">
                                                        <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                                    </div>
                                                    <div class="cliente-meta">
                                                        <?php if ($cliente['partita_iva']): ?>
                                                            P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?>
                                                        <?php elseif ($cliente['codice_fiscale']): ?>
                                                            C.F.: <?= htmlspecialchars($cliente['codice_fiscale']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted" style="font-size: 0.75rem;">
                                                    <?= getTipologiaLabel($cliente['tipologia_azienda']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= getStatoClass($cliente['stato']) ?>">
                                                    <?= getStatoIcon($cliente['stato']) ?> <?= getStatoLabel($cliente['stato']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.813rem;">
                                                    <?= htmlspecialchars($cliente['operatore_nome'] ?? 'Non assegnato') ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($cliente['pratiche_attive'] > 0): ?>
                                                    <span style="color: var(--color-success); font-weight: 600;">
                                                        <?= $cliente['pratiche_attive'] ?>
                                                    </span>
                                                    <span style="color: var(--gray-500);">
                                                        / <?= $cliente['totale_pratiche'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400);">
                                                        <?= $cliente['totale_pratiche'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cliente['ultima_comunicazione']): ?>
                                                    <span style="font-size: 0.75rem; color: var(--gray-600);">
                                                        <?= getTempoTrascorso($cliente['ultima_comunicazione']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size: 0.75rem; color: var(--gray-400);">
                                                        Mai contattato
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons" style="justify-content: flex-end;">
                                                    <a href="/crm/?action=clienti&view=view&id=<?= $cliente['id'] ?>" 
                                                       class="btn-action btn-secondary"
                                                       title="Visualizza">
                                                        👁️
                                                    </a>
                                                    <?php if ($sessionInfo['is_admin'] || $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id']): ?>
                                                        <a href="/crm/?action=clienti&view=edit&id=<?= $cliente['id'] ?>" 
                                                           class="btn-action btn-secondary"
                                                           title="Modifica">
                                                            ✏️
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="/crm/?action=clienti&view=documenti&id=<?= $cliente['id'] ?>" 
                                                       class="btn-action btn-secondary"
                                                       title="Documenti">
                                                        📁
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?action=clienti&page=1<?= buildQueryString() ?>" class="page-link">
                                    «
                                </a>
                                <a href="?action=clienti&page=<?= $page - 1 ?><?= buildQueryString() ?>" class="page-link">
                                    ‹
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?action=clienti&page=<?= $i ?><?= buildQueryString() ?>" 
                                   class="page-link <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?action=clienti&page=<?= $page + 1 ?><?= buildQueryString() ?>" class="page-link">
                                    ›
                                </a>
                                <a href="?action=clienti&page=<?= $totalPages ?><?= buildQueryString() ?>" class="page-link">
                                    »
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>

<?php
// Helper functions
function getTipologiaLabel($tipo) {
    $labels = [
        'individuale' => 'Individuale',
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

function getStatoIcon($stato) {
    $icons = [
        'attivo' => '✅',
        'sospeso' => '⚠️',
        'chiuso' => '🔴'
    ];
    return $icons[$stato] ?? '';
}

function getStatoLabel($stato) {
    return ucfirst($stato);
}

function getTempoTrascorso($data) {
    if (!$data) return 'Mai';
    
    $timestamp = strtotime($data);
    $diff = time() - $timestamp;
    
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min fa';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ore fa';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' giorni fa';
    } else {
        return date('d/m/Y', $timestamp);
    }
}

function buildQueryString() {
    $params = [];
    if (!empty($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
    if (!empty($_GET['status']) && $_GET['status'] !== 'all') $params[] = 'status=' . $_GET['status'];
    if (!empty($_GET['tipologia']) && $_GET['tipologia'] !== 'all') $params[] = 'tipologia=' . $_GET['tipologia'];
    if (!empty($_GET['operatore']) && $_GET['operatore'] !== 'all') $params[] = 'operatore=' . $_GET['operatore'];
    
    return $params ? '&' . implode('&', $params) : '';
}
?>