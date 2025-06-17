<?php
/**
 * modules/clienti/comunicazioni.php - Gestione Comunicazioni Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE CON SIDEBAR E HEADER CENTRALIZZATI
 * 
 * Features:
 * - Timeline comunicazioni
 * - Inserimento note, email, telefonate
 * - Filtri per tipo comunicazione
 * - Storico completo interazioni
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
            $data_comunicazione = $_POST['data_comunicazione'] ?? date('Y-m-d H:i');
            
            if (!array_key_exists($tipo, $tipiComunicazione)) {
                $errors[] = "Tipo comunicazione non valido";
            }
            
            if (empty($oggetto)) {
                $errors[] = "L'oggetto è obbligatorio";
            }
            
            if (empty($contenuto)) {
                $errors[] = "Il contenuto è obbligatorio";
            }
            
            // Converti data in formato corretto
            try {
                $dataCom = new DateTime($data_comunicazione);
                $dataFormatted = $dataCom->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $errors[] = "Data non valida";
            }
            
            if (empty($errors)) {
                try {
                    $db->insert('comunicazioni_clienti', [
                        'cliente_id' => $clienteId,
                        'operatore_id' => $sessionInfo['operatore_id'],
                        'tipo' => $tipo,
                        'oggetto' => $oggetto,
                        'contenuto' => $contenuto,
                        'data_comunicazione' => $dataFormatted,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $success = '✅ Comunicazione registrata con successo';
                    
                    // Reset form
                    $_POST = [];
                } catch (Exception $e) {
                    error_log("Errore inserimento comunicazione: " . $e->getMessage());
                    $errors[] = "Errore durante il salvataggio";
                }
            }
            break;
            
        case 'delete':
            $comunicazioneId = (int)($_POST['comunicazione_id'] ?? 0);
            if ($comunicazioneId && $sessionInfo['is_admin']) {
                try {
                    $db->delete('comunicazioni_clienti', 'id = ? AND cliente_id = ?', [$comunicazioneId, $clienteId]);
                    $success = '✅ Comunicazione eliminata';
                } catch (Exception $e) {
                    $errors[] = "Errore durante l'eliminazione";
                }
            }
            break;
    }
}

// Carica comunicazioni con filtri
$filtroTipo = $_GET['tipo'] ?? '';
$filtroMese = $_GET['mese'] ?? '';

$whereConditions = ['cc.cliente_id = ?'];
$params = [$clienteId];

if ($filtroTipo && array_key_exists($filtroTipo, $tipiComunicazione)) {
    $whereConditions[] = 'cc.tipo = ?';
    $params[] = $filtroTipo;
}

if ($filtroMese && preg_match('/^\d{4}-\d{2}$/', $filtroMese)) {
    $whereConditions[] = 'DATE_FORMAT(cc.data_comunicazione, "%Y-%m") = ?';
    $params[] = $filtroMese;
}

$whereClause = implode(' AND ', $whereConditions);

try {
    $comunicazioni = $db->select("
        SELECT 
            cc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome
        FROM comunicazioni_clienti cc
        LEFT JOIN operatori o ON cc.operatore_id = o.id
        WHERE $whereClause
        ORDER BY cc.data_comunicazione DESC
    ", $params);
    
    // Statistiche per tipo
    $statsTipo = $db->select("
        SELECT tipo, COUNT(*) as count
        FROM comunicazioni_clienti
        WHERE cliente_id = ?
        GROUP BY tipo
    ", [$clienteId]);
    
    $statsByType = [];
    foreach ($statsTipo as $stat) {
        $statsByType[$stat['tipo']] = $stat['count'];
    }
    
    // Mesi disponibili per filtro
    $mesiDisponibili = $db->select("
        SELECT DISTINCT DATE_FORMAT(data_comunicazione, '%Y-%m') as mese,
               DATE_FORMAT(data_comunicazione, '%M %Y') as mese_label
        FROM comunicazioni_clienti
        WHERE cliente_id = ?
        ORDER BY mese DESC
        LIMIT 12
    ", [$clienteId]);
    
} catch (Exception $e) {
    error_log("Errore caricamento comunicazioni: " . $e->getMessage());
    $comunicazioni = [];
}

// Helper per formattare date
function formatDataComunicazione($data) {
    $date = new DateTime($data);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        return 'Oggi alle ' . $date->format('H:i');
    } elseif ($diff->days == 1) {
        return 'Ieri alle ' . $date->format('H:i');
    } elseif ($diff->days < 7) {
        return $diff->days . ' giorni fa';
    } else {
        return $date->format('d/m/Y H:i');
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicazioni - <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- CSS nell'ordine corretto -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        .comunicazioni-container {
            padding: 2rem 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-green);
            text-decoration: none;
            transition: color var(--transition-fast);
        }
        
        .breadcrumb a:hover {
            color: var(--primary-green-hover);
        }
        
        .breadcrumb .separator {
            color: var(--gray-400);
        }
        
        .breadcrumb .current {
            color: var(--gray-900);
            font-weight: 500;
        }
        
        /* Layout a due colonne */
        .comunicazioni-layout {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .comunicazioni-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Form nuova comunicazione */
        .form-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            height: fit-content;
        }
        
        .form-header {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.25rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .tipo-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .tipo-option {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            font-size: 0.875rem;
        }
        
        .tipo-option:hover {
            border-color: var(--primary-green);
            background: var(--gray-50);
        }
        
        .tipo-option input[type="radio"] {
            display: none;
        }
        
        .tipo-option input[type="radio"]:checked + label {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        /* Timeline comunicazioni */
        .timeline-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .timeline-header {
            padding: 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .timeline-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }
        
        /* Filtri */
        .filters-row {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-badge {
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            color: var(--gray-700);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .filter-badge:hover {
            background: var(--gray-200);
        }
        
        .filter-badge.active {
            background: var(--primary-green);
            color: white;
        }
        
        .filter-count {
            background: rgba(0, 0, 0, 0.1);
            padding: 0.125rem 0.375rem;
            border-radius: var(--radius-full);
            font-size: 0.625rem;
            margin-left: 0.25rem;
        }
        
        /* Timeline items */
        .timeline-content {
            padding: 1rem;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 3rem;
            padding-bottom: 2rem;
            border-left: 2px solid var(--gray-200);
            margin-left: 1rem;
        }
        
        .timeline-item:last-child {
            border-left: none;
            padding-bottom: 0;
        }
        
        .timeline-icon {
            position: absolute;
            left: -1.25rem;
            top: 0;
            width: 2.5rem;
            height: 2.5rem;
            background: white;
            border: 2px solid var(--gray-300);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .timeline-date {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .timeline-card {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1rem;
            border: 1px solid var(--gray-200);
        }
        
        .timeline-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .timeline-oggetto {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.875rem;
        }
        
        .timeline-operatore {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .timeline-contenuto {
            font-size: 0.875rem;
            color: var(--gray-700);
            white-space: pre-wrap;
            margin-top: 0.5rem;
        }
        
        .timeline-actions {
            margin-top: 0.5rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-small:hover {
            background: var(--gray-200);
            border-color: var(--gray-400);
        }
        
        .btn-danger {
            color: var(--color-danger);
        }
        
        .btn-danger:hover {
            background: var(--color-danger-light);
            border-color: var(--color-danger);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        
        /* Quick actions */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .quick-action {
            flex: 1;
            padding: 0.5rem;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            text-align: center;
            font-size: 0.75rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        
        .quick-action:hover {
            background: var(--gray-100);
            border-color: var(--primary-green);
            color: var(--primary-green);
        }
        
        /* Buttons */
        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-submit:hover {
            background: var(--primary-green-hover);
            transform: translateY(-1px);
        }
        
        /* Alert messages */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: var(--color-success-light);
            color: var(--color-success);
            border: 1px solid var(--color-success);
        }
        
        .alert-danger {
            background: var(--color-danger-light);
            color: var(--color-danger);
            border: 1px solid var(--color-danger);
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
                    <!-- Breadcrumb -->
                    <nav class="breadcrumb">
                        <a href="/crm/">Home</a>
                        <span class="separator">/</span>
                        <a href="/crm/?action=clienti">Clienti</a>
                        <span class="separator">/</span>
                        <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>">
                            <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                        </a>
                        <span class="separator">/</span>
                        <span class="current">Comunicazioni</span>
                    </nav>

                    <!-- Messaggi -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Layout due colonne -->
                    <div class="comunicazioni-layout">
                        <!-- Form nuova comunicazione -->
                        <div class="form-section">
                            <h3 class="form-header">
                                ➕ Nuova Comunicazione
                            </h3>
                            
                            <form method="POST" action="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>">
                                <input type="hidden" name="action" value="add">
                                
                                <!-- Tipo comunicazione -->
                                <div class="form-group">
                                    <label class="form-label">Tipo di comunicazione</label>
                                    <div class="tipo-selector">
                                        <?php foreach ($tipiComunicazione as $value => $info): ?>
                                            <div class="tipo-option">
                                                <input type="radio" 
                                                       id="tipo_<?= $value ?>" 
                                                       name="tipo" 
                                                       value="<?= $value ?>"
                                                       <?= ($_POST['tipo'] ?? 'nota') == $value ? 'checked' : '' ?>>
                                                <label for="tipo_<?= $value ?>">
                                                    <?= $info['icon'] ?> <?= str_replace($info['icon'] . ' ', '', $info['label']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Data e ora -->
                                <div class="form-group">
                                    <label for="data_comunicazione" class="form-label">Data e ora</label>
                                    <input type="datetime-local" 
                                           id="data_comunicazione" 
                                           name="data_comunicazione" 
                                           class="form-input" 
                                           value="<?= date('Y-m-d\TH:i') ?>">
                                </div>
                                
                                <!-- Oggetto -->
                                <div class="form-group">
                                    <label for="oggetto" class="form-label">Oggetto</label>
                                    <input type="text" 
                                           id="oggetto" 
                                           name="oggetto" 
                                           class="form-input" 
                                           placeholder="Breve descrizione..."
                                           value="<?= htmlspecialchars($_POST['oggetto'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <!-- Contenuto -->
                                <div class="form-group">
                                    <label for="contenuto" class="form-label">Contenuto</label>
                                    <textarea id="contenuto" 
                                              name="contenuto" 
                                              class="form-textarea" 
                                              placeholder="Dettagli della comunicazione..."
                                              rows="5"
                                              required><?= htmlspecialchars($_POST['contenuto'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn-submit">
                                    💾 Salva Comunicazione
                                </button>
                            </form>
                            
                            <!-- Quick actions -->
                            <div class="quick-actions">
                                <?php if ($cliente['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>" 
                                       class="quick-action">
                                        📧 Invia Email
                                    </a>
                                <?php endif; ?>
                                <?php if ($cliente['telefono']): ?>
                                    <a href="tel:<?= htmlspecialchars($cliente['telefono']) ?>" 
                                       class="quick-action">
                                        📞 Chiama
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Timeline comunicazioni -->
                        <div class="timeline-section">
                            <div class="timeline-header">
                                <h3 class="timeline-title">
                                    💬 Storico Comunicazioni (<?= count($comunicazioni) ?>)
                                </h3>
                                
                                <!-- Filtri -->
                                <div class="filters-row">
                                    <!-- Filtro tipo -->
                                    <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>" 
                                       class="filter-badge <?= !$filtroTipo ? 'active' : '' ?>">
                                        Tutti
                                        <span class="filter-count"><?= count($comunicazioni) ?></span>
                                    </a>
                                    
                                    <?php foreach ($tipiComunicazione as $value => $info): ?>
                                        <?php 
                                        $count = $statsByType[$value] ?? 0;
                                        $isActive = $filtroTipo === $value;
                                        ?>
                                        <a href="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&tipo=<?= $value ?>" 
                                           class="filter-badge <?= $isActive ? 'active' : '' ?>">
                                            <?= $info['icon'] ?>
                                            <?php if ($count > 0): ?>
                                                <span class="filter-count"><?= $count ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                    
                                    <!-- Filtro mese -->
                                    <?php if (!empty($mesiDisponibili)): ?>
                                        <select onchange="window.location.href=this.value" 
                                                style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-full); border: 1px solid var(--gray-300);">
                                            <option value="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>">
                                                Tutti i mesi
                                            </option>
                                            <?php foreach ($mesiDisponibili as $mese): ?>
                                                <option value="/crm/?action=clienti&view=comunicazioni&id=<?= $clienteId ?>&mese=<?= $mese['mese'] ?>"
                                                        <?= $filtroMese === $mese['mese'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($mese['mese_label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="timeline-content">
                                <?php if (empty($comunicazioni)): ?>
                                    <div class="empty-state">
                                        <p>🔍 Nessuna comunicazione trovata</p>
                                        <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                                            Registra la prima comunicazione usando il form a sinistra
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    $lastDate = '';
                                    foreach ($comunicazioni as $comm): 
                                        $currentDate = date('d/m/Y', strtotime($comm['data_comunicazione']));
                                        $showDate = $currentDate !== $lastDate;
                                        $lastDate = $currentDate;
                                    ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon">
                                                <?= $tipiComunicazione[$comm['tipo']]['icon'] ?? '💬' ?>
                                            </div>
                                            
                                            <?php if ($showDate): ?>
                                                <div class="timeline-date">
                                                    <?= $currentDate ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="timeline-card">
                                                <div class="timeline-card-header">
                                                    <div>
                                                        <div class="timeline-oggetto">
                                                            <?= htmlspecialchars($comm['oggetto']) ?>
                                                        </div>
                                                        <div class="timeline-operatore">
                                                            <?= htmlspecialchars($comm['operatore_nome'] ?? 'Sistema') ?>
                                                            • <?= date('H:i', strtotime($comm['data_comunicazione'])) ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($sessionInfo['is_admin'] || $comm['operatore_id'] == $sessionInfo['operatore_id']): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminare questa comunicazione?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="comunicazione_id" value="<?= $comm['id'] ?>">
                                                            <button type="submit" class="btn-small btn-danger">
                                                                🗑️
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="timeline-contenuto">
                                                    <?= nl2br(htmlspecialchars($comm['contenuto'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Script per selezione tipo comunicazione -->
    <script>
        // Gestione selezione tipo comunicazione
        document.querySelectorAll('.tipo-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Rimuovi classe selected da tutti
                document.querySelectorAll('.tipo-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Aggiungi classe selected al genitore
                if (this.checked) {
                    this.closest('.tipo-option').classList.add('selected');
                }
            });
        });
        
        // Simula click sul primo radio al caricamento
        const checkedRadio = document.querySelector('.tipo-option input[type="radio"]:checked');
        if (checkedRadio) {
            checkedRadio.closest('.tipo-option').classList.add('selected');
        }
    </script>
    
    <!-- Script microinterazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
</body>
</html>