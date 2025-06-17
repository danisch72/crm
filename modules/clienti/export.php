<?php
/**
 * modules/clienti/export.php - Export Dati Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE CON SIDEBAR E HEADER CENTRALIZZATI
 * ✅ EXPORT PROFESSIONALE COMMERCIALISTI COMPLIANT
 * 
 * Features:
 * - Export Excel/CSV con dati fiscali completi
 * - Template specifici per dichiarazioni e controlli
 * - Export multiplo clienti selezionati
 * - Formattazione professionale per uso fiscale
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definisci che siamo nel CRM per router
if (!defined('CRM_INIT')) {
    define('CRM_INIT', true);
}

// Include bootstrap per autenticazione
require_once dirname(dirname(__DIR__)) . '/core/bootstrap.php';

// Verifica autenticazione
if (!isAuthenticated()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Carica helpers e database
loadDatabase();
loadHelpers();

// Prepara sessionInfo per compatibilità
$currentUser = getCurrentUser();
$sessionInfo = [
    'operatore_id' => $currentUser['id'],
    'user_id' => $currentUser['id'],
    'nome' => $currentUser['nome'],
    'cognome' => $currentUser['cognome'],
    'email' => $currentUser['email'],
    'nome_completo' => $currentUser['nome'] . ' ' . $currentUser['cognome'],
    'is_admin' => $currentUser['is_admin']
];

$db = Database::getInstance();

// Variabili per i componenti
$pageTitle = 'Export Clienti';
$pageIcon = '📊';

// Solo admin possono fare export
if (!$sessionInfo['is_admin']) {
    $_SESSION['error_message'] = '⚠️ Solo gli amministratori possono esportare i dati';
    header('Location: /crm/?action=clienti');
    exit;
}

// Parametri export
$exportType = $_GET['type'] ?? 'excel';
$clienteId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$clienteIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$template = $_GET['template'] ?? 'completo';

// Se c'è una richiesta di download diretto
if (isset($_GET['download']) && $_GET['download'] === '1') {
    processExport();
    exit;
}

// Altrimenti mostra la pagina di configurazione export

// Templates disponibili
$templates = [
    'completo' => [
        'label' => '📊 Export Completo',
        'description' => 'Tutti i dati del cliente inclusi pratiche e documenti'
    ],
    'fiscale' => [
        'label' => '🧾 Dati Fiscali',
        'description' => 'Solo dati fiscali per dichiarazioni (CF, P.IVA, sede)'
    ],
    'contatti' => [
        'label' => '📞 Rubrica Contatti',
        'description' => 'Anagrafica e contatti per comunicazioni massive'
    ],
    'pratiche' => [
        'label' => '📋 Pratiche Associate',
        'description' => 'Lista pratiche e scadenze per cliente'
    ],
    'sintetico' => [
        'label' => '📄 Report Sintetico',
        'description' => 'Vista compatta con dati essenziali'
    ]
];

// Formati disponibili
$formati = [
    'excel' => ['label' => '📗 Excel (.xlsx)', 'icon' => '📗'],
    'csv' => ['label' => '📄 CSV', 'icon' => '📄'],
    'pdf' => ['label' => '📕 PDF', 'icon' => '📕']
];

// Se ci sono clienti selezionati, carica i loro dati
$clientiSelezionati = [];
if ($clienteId) {
    $clienteIds = [$clienteId];
}

if (!empty($clienteIds)) {
    $placeholders = str_repeat('?,', count($clienteIds) - 1) . '?';
    $clientiSelezionati = $db->select("
        SELECT id, ragione_sociale, codice_fiscale, partita_iva
        FROM clienti
        WHERE id IN ($placeholders)
        ORDER BY ragione_sociale
    ", $clienteIds);
}

// Funzione per processare l'export
function processExport() {
    global $db, $sessionInfo, $exportType, $clienteIds, $template;
    
    if (empty($clienteIds)) {
        die('Errore: Nessun cliente selezionato');
    }
    
    // Carica dati in base al template
    $placeholders = str_repeat('?,', count($clienteIds) - 1) . '?';
    
    // Query base clienti
    $clienti = $db->select("
        SELECT 
            c.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            (SELECT COUNT(*) FROM pratiche WHERE cliente_id = c.id) as totale_pratiche,
            (SELECT COUNT(*) FROM documenti_clienti WHERE cliente_id = c.id) as totale_documenti
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
        WHERE c.id IN ($placeholders)
        ORDER BY c.ragione_sociale
    ", $clienteIds);
    
    // Genera export in base al formato
    switch ($exportType) {
        case 'csv':
            generateCSV($clienti, $template);
            break;
        case 'pdf':
            generatePDF($clienti, $template);
            break;
        case 'excel':
        default:
            generateExcel($clienti, $template);
            break;
    }
}

// Funzione per generare CSV
function generateCSV($clienti, $template) {
    $filename = 'export_clienti_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM per Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers in base al template
    switch ($template) {
        case 'fiscale':
            $headers = ['ID', 'Ragione Sociale', 'Codice Fiscale', 'Partita IVA', 'Codice SDI', 'Indirizzo', 'CAP', 'Città', 'Provincia'];
            break;
        case 'contatti':
            $headers = ['ID', 'Ragione Sociale', 'Email', 'PEC', 'Telefono', 'Indirizzo Completo'];
            break;
        default:
            $headers = ['ID', 'Ragione Sociale', 'CF/P.IVA', 'Email', 'Telefono', 'Operatore', 'Stato', 'Pratiche', 'Documenti'];
    }
    
    fputcsv($output, $headers, ';');
    
    // Dati
    foreach ($clienti as $cliente) {
        switch ($template) {
            case 'fiscale':
                $row = [
                    $cliente['id'],
                    $cliente['ragione_sociale'],
                    $cliente['codice_fiscale'],
                    $cliente['partita_iva'],
                    $cliente['codice_univoco'],
                    $cliente['indirizzo'],
                    $cliente['cap'],
                    $cliente['citta'],
                    $cliente['provincia']
                ];
                break;
            case 'contatti':
                $indirizzo = implode(' ', array_filter([
                    $cliente['indirizzo'],
                    $cliente['cap'],
                    $cliente['citta'],
                    $cliente['provincia'] ? '(' . $cliente['provincia'] . ')' : ''
                ]));
                $row = [
                    $cliente['id'],
                    $cliente['ragione_sociale'],
                    $cliente['email'],
                    $cliente['pec'],
                    $cliente['telefono'],
                    $indirizzo
                ];
                break;
            default:
                $cf_piva = $cliente['codice_fiscale'] ?: $cliente['partita_iva'];
                $row = [
                    $cliente['id'],
                    $cliente['ragione_sociale'],
                    $cf_piva,
                    $cliente['email'],
                    $cliente['telefono'],
                    $cliente['operatore_nome'] ?: 'Non assegnato',
                    ucfirst($cliente['stato']),
                    $cliente['totale_pratiche'],
                    $cliente['totale_documenti']
                ];
        }
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

// Stub per altre funzioni di export
function generateExcel($clienti, $template) {
    // In produzione useresti PHPSpreadsheet
    // Per ora generiamo CSV con estensione .xls
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="export_clienti_' . date('Y-m-d_His') . '.xls"');
    
    // Genera come CSV ma con tab come separatore
    echo "ID\tRagione Sociale\tCF/P.IVA\tEmail\tTelefono\tOperatore\tStato\n";
    
    foreach ($clienti as $cliente) {
        $cf_piva = $cliente['codice_fiscale'] ?: $cliente['partita_iva'];
        echo implode("\t", [
            $cliente['id'],
            $cliente['ragione_sociale'],
            $cf_piva,
            $cliente['email'],
            $cliente['telefono'],
            $cliente['operatore_nome'] ?: 'Non assegnato',
            ucfirst($cliente['stato'])
        ]) . "\n";
    }
    
    exit;
}

function generatePDF($clienti, $template) {
    // In produzione useresti TCPDF o simili
    die('Export PDF non ancora implementato');
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
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        .export-container {
            padding: 2rem 1rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .export-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }
        
        .export-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }
        
        .export-description {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        /* Layout due colonne */
        .export-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .export-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Sezioni */
        .export-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Template cards */
        .template-grid {
            display: grid;
            gap: 0.75rem;
        }
        
        .template-card {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 1rem;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .template-card:hover {
            border-color: var(--primary-green);
            background: var(--gray-50);
        }
        
        .template-card input[type="radio"] {
            display: none;
        }
        
        .template-card input[type="radio"]:checked + label {
            border-color: var(--primary-green);
            background: rgba(0, 120, 73, 0.05);
        }
        
        .template-card label {
            display: block;
            cursor: pointer;
        }
        
        .template-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .template-desc {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        /* Format cards */
        .format-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }
        
        .format-card {
            text-align: center;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .format-card:hover {
            border-color: var(--primary-green);
            background: var(--gray-50);
        }
        
        .format-card input[type="radio"] {
            display: none;
        }
        
        .format-card input[type="radio"]:checked + label {
            border-color: var(--primary-green);
            background: rgba(0, 120, 73, 0.05);
        }
        
        .format-card label {
            display: block;
            cursor: pointer;
        }
        
        .format-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .format-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        /* Clienti selezionati */
        .clienti-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 0.5rem;
        }
        
        .cliente-item {
            padding: 0.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .cliente-item:last-child {
            border-bottom: none;
        }
        
        /* Actions */
        .export-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-green-hover);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-info {
            background: var(--color-info-light);
            color: var(--color-info);
            border: 1px solid var(--color-info);
        }
        
        /* Summary */
        .export-summary {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.875rem;
        }
        
        .summary-label {
            color: var(--gray-600);
        }
        
        .summary-value {
            font-weight: 600;
            color: var(--gray-900);
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
                <div class="export-container">
                    <div class="export-header">
                        <h1 class="export-title">📊 Export Dati Clienti</h1>
                        <p class="export-description">
                            Configura ed esporta i dati dei clienti in formato Excel, CSV o PDF per uso professionale e fiscale.
                        </p>
                    </div>
                    
                    <?php if (empty($clientiSelezionati) && !isset($_GET['all'])): ?>
                        <div class="alert alert-info">
                            ℹ️ Nessun cliente selezionato. 
                            <a href="/crm/?action=clienti">Torna alla lista clienti</a> per selezionare i clienti da esportare.
                        </div>
                    <?php else: ?>
                        <form id="exportForm">
                            <input type="hidden" name="download" value="1">
                            <?php if (isset($_GET['all'])): ?>
                                <input type="hidden" name="all" value="1">
                            <?php else: ?>
                                <input type="hidden" name="ids" value="<?= htmlspecialchars(implode(',', $clienteIds)) ?>">
                            <?php endif; ?>
                            
                            <div class="export-layout">
                                <!-- Colonna sinistra -->
                                <div>
                                    <!-- Selezione template -->
                                    <div class="export-section">
                                        <h3 class="section-title">
                                            📋 Seleziona Template
                                        </h3>
                                        <div class="template-grid">
                                            <?php foreach ($templates as $key => $tmpl): ?>
                                                <div class="template-card">
                                                    <input type="radio" 
                                                           id="template_<?= $key ?>" 
                                                           name="template" 
                                                           value="<?= $key ?>"
                                                           <?= $template === $key ? 'checked' : '' ?>>
                                                    <label for="template_<?= $key ?>">
                                                        <div class="template-name"><?= $tmpl['label'] ?></div>
                                                        <div class="template-desc"><?= $tmpl['description'] ?></div>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Selezione formato -->
                                    <div class="export-section" style="margin-top: 1rem;">
                                        <h3 class="section-title">
                                            💾 Formato Export
                                        </h3>
                                        <div class="format-grid">
                                            <?php foreach ($formati as $key => $fmt): ?>
                                                <div class="format-card">
                                                    <input type="radio" 
                                                           id="format_<?= $key ?>" 
                                                           name="type" 
                                                           value="<?= $key ?>"
                                                           <?= $exportType === $key ? 'checked' : '' ?>>
                                                    <label for="format_<?= $key ?>">
                                                        <div class="format-icon"><?= $fmt['icon'] ?></div>
                                                        <div class="format-name"><?= $fmt['label'] ?></div>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Colonna destra -->
                                <div>
                                    <!-- Clienti selezionati -->
                                    <div class="export-section">
                                        <h3 class="section-title">
                                            🏢 Clienti da Esportare
                                        </h3>
                                        
                                        <?php if (isset($_GET['all'])): ?>
                                            <p>Verranno esportati <strong>TUTTI i clienti</strong> presenti nel database.</p>
                                        <?php elseif (!empty($clientiSelezionati)): ?>
                                            <div class="clienti-list">
                                                <?php foreach ($clientiSelezionati as $cliente): ?>
                                                    <div class="cliente-item">
                                                        <strong><?= htmlspecialchars($cliente['ragione_sociale']) ?></strong><br>
                                                        <?php if ($cliente['codice_fiscale']): ?>
                                                            CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?>
                                                        <?php endif; ?>
                                                        <?php if ($cliente['partita_iva']): ?>
                                                            <?= $cliente['codice_fiscale'] ? ' | ' : '' ?>
                                                            P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--gray-600);">
                                                Totale: <strong><?= count($clientiSelezionati) ?></strong> clienti
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Riepilogo export -->
                                    <div class="export-section" style="margin-top: 1rem;">
                                        <h3 class="section-title">
                                            📊 Riepilogo Export
                                        </h3>
                                        <div class="export-summary">
                                            <div class="summary-item">
                                                <span class="summary-label">Template:</span>
                                                <span class="summary-value" id="summaryTemplate">Export Completo</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Formato:</span>
                                                <span class="summary-value" id="summaryFormat">Excel (.xlsx)</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Numero record:</span>
                                                <span class="summary-value">
                                                    <?= isset($_GET['all']) ? 'Tutti' : count($clientiSelezionati) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Azioni -->
                            <div class="export-actions">
                                <a href="/crm/?action=clienti" class="btn btn-secondary">
                                    ← Torna alla lista
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    📥 Scarica Export
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Script per gestione form -->
    <script>
        // Aggiorna riepilogo quando cambiano le selezioni
        document.addEventListener('DOMContentLoaded', function() {
            const templateRadios = document.querySelectorAll('input[name="template"]');
            const formatRadios = document.querySelectorAll('input[name="type"]');
            
            const templates = <?= json_encode($templates) ?>;
            const formati = <?= json_encode($formati) ?>;
            
            function updateSummary() {
                const selectedTemplate = document.querySelector('input[name="template"]:checked')?.value || 'completo';
                const selectedFormat = document.querySelector('input[name="type"]:checked')?.value || 'excel';
                
                document.getElementById('summaryTemplate').textContent = templates[selectedTemplate].label.replace(/^[^\s]+ /, '');
                document.getElementById('summaryFormat').textContent = formati[selectedFormat].label.replace(/^[^\s]+ /, '');
            }
            
            templateRadios.forEach(radio => radio.addEventListener('change', updateSummary));
            formatRadios.forEach(radio => radio.addEventListener('change', updateSummary));
            
            // Update iniziale
            updateSummary();
            
            // Gestione submit form
            document.getElementById('exportForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const params = new URLSearchParams(formData);
                
                window.location.href = '/crm/?action=clienti&view=export&' + params.toString();
            });
        });
    </script>
    
    <!-- Script microinterazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
</body>
</html>