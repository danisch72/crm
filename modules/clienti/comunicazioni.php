<?php
/**
 * modules/clienti/comunicazioni.php - Gestione Comunicazioni Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE DEFINITIVA CON COMPONENTI CENTRALIZZATI
 * ✅ LAYOUT ULTRA-COMPATTO DATEV OPTIMAL
 * 
 * Features:
 * - Timeline comunicazioni
 * - Inserimento note, email, telefonate
 * - Filtri per tipo comunicazione
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
$pageTitle = 'Comunicazioni Cliente';
$pageIcon = '💬';

// $clienteId già validato dal router
// Recupera dati cliente
$cliente = $db->selectOne("
    SELECT id, ragione_sociale, operatore_responsabile_id, email, telefono
    FROM clienti 
    WHERE id = ?
", [$clienteId]);

if (!$cliente) {
    $_SESSION['error_message'] = '⚠️ Cliente non trovato';
    header('Location: /crm/?action=clienti');
    exit;
}

// Tipi di comunicazione disponibili
$tipiComunicazione = [
    'email' => ['label' => '📧 Email', 'icon' => '📧'],
    'telefono' => ['label' => '📞 Telefonata', 'icon' => '📞'],
    'incontro' => ['label' => '🤝 Incontro', 'icon' => '🤝'],
    'nota' => ['label' => '📝 Nota interna', 'icon' => '📝']
];

// Gestione filtri
$filtroTipo = $_GET['tipo'] ?? 'all';
$filtroPeriodo = $_GET['periodo'] ?? '30';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Gestione form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            // Validazione
            $tipo = $_POST['tipo'] ?? '';
            $oggetto = trim($_POST['oggetto'] ?? '');
            $contenuto = trim($_POST['contenuto'] ?? '');
            $data_comunicazione = $_POST['data_comunicazione'] ?? date('Y-m-d H:i:s');
            
            if (!array_key_exists($tipo, $tipiComunicazione)) {
                $errors[] = "Tipo comunicazione non valido";
            }
            
            if (empty($oggetto)) {
                $errors[] = "Oggetto obbligatorio";
            }
            
            if (empty($contenuto)) {
                $errors[] = "Contenuto obbligatorio";
            }
            
            if (empty($errors)) {
                try {
                    $id = $db->insert('comunicazioni_clienti', [
                        'cliente_id' => $clienteId,
                        'operatore_id' => $sessionInfo['operatore_id'],
                        'tipo' => $tipo,
                        'oggetto' => $oggetto,
                        'contenuto' => $contenuto,
                        'data_comunicazione' => $data_comunicazione,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($id) {
                        $success = '✅ Comunicazione registrata con successo';
                        
                        // Svuota form
                        $_POST = [];
                    } else {
                        $errors[] = "Errore durante il salvataggio";
                    }
                } catch (Exception $e) {
                    error_log("Errore inserimento comunicazione: " . $e->getMessage());
                    $errors[] = "Errore di sistema";
                }
            }
            break;
            
        case 'delete':
            $comunicazioneId = (int)($_POST['comunicazione_id'] ?? 0);
            if ($comunicazioneId && $sessionInfo['is_admin']) {
                try {
                    $success = $db->delete('comunicazioni_clienti', 'id = ? AND cliente_id = ?', [$comunicazioneId, $clienteId]);
                    if ($success) {
                        $_SESSION['success_message'] = '✅ Comunicazione eliminata';
                        header('Location: /crm/?action=clienti&view=comunicazioni&id=' . $clienteId);
                        exit;
                    }
                } catch (Exception $e) {
                    $errors[] = "Errore durante l'eliminazione";
                }
            }
            break;
    }
}

// Query comunicazioni con filtri
$whereConditions = ["cliente_id = ?"];
$params = [$clienteId];

if ($filtroTipo !== 'all') {
    $whereConditions[] = "tipo = ?";
    $params[] = $filtroTipo;
}

if ($filtroPeriodo !== 'all') {
    $whereConditions[] = "DATE(data_comunicazione) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $params[] = $filtroPeriodo;
}

$whereClause = implode(' AND ', $whereConditions);

// Conta totale per paginazione
$totalCount = 0;
try {
    $totalCount = $db->count('comunicazioni_clienti', $whereClause, $params);
} catch (Exception $e) {
    error_log("Errore conteggio comunicazioni: " . $e->getMessage());
}

$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Carica comunicazioni
$comunicazioni = [];
try {
    $comunicazioni = $db->select("
        SELECT cc.*, 
               CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
               o.id as operatore_id
        FROM comunicazioni_clienti cc
        LEFT JOIN operatori o ON cc.operatore_id = o.id
        WHERE $whereClause
        ORDER BY cc.data_comunicazione DESC, cc.created_at DESC
        LIMIT $perPage OFFSET $offset
    ", $params);
} catch (Exception $e) {
    error_log("Errore caricamento comunicazioni: " . $e->getMessage());
}

// Statistiche
$stats = [
    'totali' => $totalCount,
    'email' => 0,
    'telefono' => 0,
    'incontro' => 0,
    'nota' => 0
];

try {
    $statsData = $db->select("
        SELECT tipo, COUNT(*) as count
        FROM comunicazioni_clienti
        WHERE cliente_id = ?
        GROUP BY tipo
    ", [$clienteId]);
    
    foreach ($statsData as $stat) {
        if (isset($stats[$stat['tipo']])) {
            $stats[$stat['tipo']] = $stat['count'];
        }
    }
} catch (Exception $e) {
    error_log("Errore statistiche comunicazioni: " . $e->getMessage());
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
        /* Layout comunicazioni ultra-compatto */
        .comunicazioni-container {
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .comunicazioni-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header-info h1 {
            font-size: 1.5rem;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }
        
        .header-info p {
            color: var(--gray-600);
            margin: 0;
            font-size: 0.875rem;
        }
        
        .add-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-row-full {
            grid-template-columns: 1fr;
        }
        
        .filters-bar {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .stats-mini {
            display: flex;
            gap: 1rem;
            margin-left: auto;
            font-size: 0.813rem;
        }
        
        .stat-mini {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--gray-600);
        }
        
        .comunicazioni-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .comunicazione-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }
        
        .comunicazione-item:hover {
            background: var(--gray-50);
        }
        
        .comunicazione-item:last-child {
            border-bottom: none;
        }
        
        .comunicazione-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .comunicazione-tipo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tipo-icon {
            font-size: 1.25rem;
        }
        
        .tipo-info {
            display: flex;
            flex-direction: column;
        }
        
        .tipo-label {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.875rem;
        }
        
        .tipo-meta {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .comunicazione-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }
        
        .comunicazione-oggetto {
            font-weight: 500;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .comunicazione-contenuto {
            color: var(--gray-700);
            font-size: 0.875rem;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .toggle-form {
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-green);
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .toggle-form:hover {
            color: var(--primary-green-hover);
        }
        
        @media (max-width: 768px) {
            .comunicazioni-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-mini {
                margin-left: 0;
                justify-content: space-around;
            }
            
            .form-row {
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
                <div class="comunicazioni-container">
                    <!-- Header -->
                    <div class="comunicazioni-header">
                        <div class="header-info">
                            <h1><?= htmlspecialchars($cliente['ragione_sociale']) ?></h1>
                            <p>Registro comunicazioni e contatti</p>
                        </div>
                        <div class="header-actions">
                            <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>" class="btn btn-secondary">
                                ← Torna al cliente
                            </a>
                        </div>
                    </div>
                    
                    <!-- Messaggi -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p style="margin: 0;"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Form aggiunta -->
                    <div class="add-form" id="addForm" style="display: none;">
                        <h3 style="margin: 0 0 1rem 0; font-size: 1.125rem;">
                            ➕ Nuova Comunicazione
                        </h3>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="form-row">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-control" required>
                                    <?php foreach ($tipiComunicazione as $value => $tipo): ?>
                                        <option value="<?= $value ?>" <?= ($_POST['tipo'] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= $tipo['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">Data/Ora</label>
                                <input type="datetime-local" 
                                       name="data_comunicazione" 
                                       class="form-control" 
                                       value="<?= date('Y-m-d\TH:i') ?>"
                                       required>
                            </div>
                            
                            <div class="form-row">
                                <label class="form-label">Oggetto</label>
                                <input type="text" 
                                       name="oggetto" 
                                       class="form-control" 
                                       placeholder="Breve descrizione..."
                                       value="<?= htmlspecialchars($_POST['oggetto'] ?? '') ?>"
                                       required>
                            </div>
                            
                            <div class="form-row form-row-full">
                                <label class="form-label">Contenuto</label>
                                <textarea name="contenuto" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Dettagli della comunicazione..."
                                          required><?= htmlspecialchars($_POST['contenuto'] ?? '') ?></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                                <button type="button" class="btn btn-secondary" onclick="toggleForm()">
                                    Annulla
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    💾 Salva Comunicazione
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="toggle-form" onclick="toggleForm()" id="toggleButton">
                        <span>➕</span>
                        <span>Aggiungi Comunicazione</span>
                    </div>
                    
                    <!-- Filtri -->
                    <div class="filters-bar">
                        <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex: 1;">
                            <input type="hidden" name="action" value="clienti">
                            <input type="hidden" name="view" value="comunicazioni">
                            <input type="hidden" name="id" value="<?= $clienteId ?>">
                            
                            <select name="tipo" class="form-control" style="width: auto;">
                                <option value="all">Tutti i tipi</option>
                                <?php foreach ($tipiComunicazione as $value => $tipo): ?>
                                    <option value="<?= $value ?>" <?= $filtroTipo === $value ? 'selected' : '' ?>>
                                        <?= $tipo['label'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="periodo" class="form-control" style="width: auto;">
                                <option value="7" <?= $filtroPeriodo == '7' ? 'selected' : '' ?>>Ultima settimana</option>
                                <option value="30" <?= $filtroPeriodo == '30' ? 'selected' : '' ?>>Ultimo mese</option>
                                <option value="90" <?= $filtroPeriodo == '90' ? 'selected' : '' ?>>Ultimi 3 mesi</option>
                                <option value="365" <?= $filtroPeriodo == '365' ? 'selected' : '' ?>>Ultimo anno</option>
                                <option value="all" <?= $filtroPeriodo == 'all' ? 'selected' : '' ?>>Tutto</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary btn-small">
                                Filtra
                            </button>
                        </form>
                        
                        <div class="stats-mini">
                            <div class="stat-mini">
                                <span>📧</span>
                                <span><?= $stats['email'] ?></span>
                            </div>
                            <div class="stat-mini">
                                <span>📞</span>
                                <span><?= $stats['telefono'] ?></span>
                            </div>
                            <div class="stat-mini">
                                <span>🤝</span>
                                <span><?= $stats['incontro'] ?></span>
                            </div>
                            <div class="stat-mini">
                                <span>📝</span>
                                <span><?= $stats['nota'] ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista comunicazioni -->
                    <div class="comunicazioni-list">
                        <?php if (empty($comunicazioni)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <p>Nessuna comunicazione registrata</p>
                                <p style="font-size: 0.875rem; color: var(--gray-500);">
                                    Clicca su "Aggiungi Comunicazione" per iniziare
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comunicazioni as $com): ?>
                                <div class="comunicazione-item">
                                    <div class="comunicazione-header">
                                        <div class="comunicazione-tipo">
                                            <div class="tipo-icon">
                                                <?= $tipiComunicazione[$com['tipo']]['icon'] ?>
                                            </div>
                                            <div class="tipo-info">
                                                <div class="tipo-label">
                                                    <?= $tipiComunicazione[$com['tipo']]['label'] ?>
                                                </div>
                                                <div class="tipo-meta">
                                                    <?= date('d/m/Y H:i', strtotime($com['data_comunicazione'])) ?> • 
                                                    <?= htmlspecialchars($com['operatore_nome']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($sessionInfo['is_admin'] || $com['operatore_id'] == $sessionInfo['operatore_id']): ?>
                                            <div class="comunicazione-actions">
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Eliminare questa comunicazione?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="comunicazione_id" value="<?= $com['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="comunicazione-oggetto">
                                        <?= htmlspecialchars($com['oggetto']) ?>
                                    </div>
                                    
                                    <div class="comunicazione-contenuto">
                                        <?= nl2br(htmlspecialchars($com['contenuto'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination" style="margin-top: 1.5rem;">
                            <?php if ($page > 1): ?>
                                <a href="?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&page=1<?= buildQueryString() ?>" 
                                   class="page-link">«</a>
                                <a href="?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&page=<?= $page - 1 ?><?= buildQueryString() ?>" 
                                   class="page-link">‹</a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&page=<?= $i ?><?= buildQueryString() ?>" 
                                   class="page-link <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&page=<?= $page + 1 ?><?= buildQueryString() ?>" 
                                   class="page-link">›</a>
                                <a href="?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&page=<?= $totalPages ?><?= buildQueryString() ?>" 
                                   class="page-link">»</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    function toggleForm() {
        const form = document.getElementById('addForm');
        const button = document.getElementById('toggleButton');
        
        if (form.style.display === 'none') {
            form.style.display = 'block';
            button.style.display = 'none';
            // Focus sul primo campo
            form.querySelector('select[name="tipo"]').focus();
        } else {
            form.style.display = 'none';
            button.style.display = 'flex';
        }
    }
    
    // Mostra form se ci sono errori
    <?php if (!empty($errors) && $_POST['action'] === 'add'): ?>
    toggleForm();
    <?php endif; ?>
    </script>
</body>
</html>

<?php
function buildQueryString() {
    $params = [];
    if (!empty($_GET['tipo']) && $_GET['tipo'] !== 'all') $params[] = 'tipo=' . $_GET['tipo'];
    if (!empty($_GET['periodo']) && $_GET['periodo'] !== '30') $params[] = 'periodo=' . $_GET['periodo'];
    
    return $params ? '&' . implode('&', $params) : '';
}
?>