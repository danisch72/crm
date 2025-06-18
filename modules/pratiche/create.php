<?php
/**
 * modules/pratiche/create.php - Wizard Creazione Pratica
 * 
 * ✅ WIZARD MULTI-STEP CON TEMPLATE
 * 
 * Steps:
 * 1. Selezione Cliente
 * 2. Tipo Pratica e Template
 * 3. Dettagli e Scadenze
 * 4. Revisione Task
 * 5. Conferma e Creazione
 * 
 * VERSIONE CORRETTA CON FIX:
 * - Selezione clienti funzionante (cliente-card)
 * - Salvataggio con campi corretti
 * - Layout largo (1280px)
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router disponibili:
// $sessionInfo, $db, $currentUser

// Gestione step wizard
$currentStep = isset($_POST['step']) ? (int)$_POST['step'] : ($_SESSION['pratica_wizard_step'] ?? 1);
$wizardData = $_SESSION['pratica_wizard'] ?? [];

// Se form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Debug: log dei dati POST
    error_log("POST data: " . print_r($_POST, true));
    error_log("Current step: " . $currentStep);
    error_log("Action: " . ($_POST['action'] ?? 'none'));
    
    // Salva dati step corrente
    switch ($currentStep) {
        case 1: // Cliente
            $wizardData['cliente_id'] = $_POST['cliente_id'] ?? null;
            break;
            
        case 2: // Tipo e Template
            $wizardData['tipo_pratica'] = $_POST['tipo_pratica'] ?? null;
            $wizardData['template_id'] = $_POST['template_id'] ?? null;
            $wizardData['usa_template'] = isset($_POST['usa_template']);
            break;
            
        case 3: // Dettagli
            $wizardData['titolo'] = $_POST['titolo'] ?? '';
            $wizardData['descrizione'] = $_POST['descrizione'] ?? '';
            $wizardData['priorita'] = $_POST['priorita'] ?? 'media';
            $wizardData['data_scadenza'] = $_POST['data_scadenza'] ?? '';
            $wizardData['valore_pratica'] = $_POST['valore_pratica'] ?? 0;
            $wizardData['ore_preventivate'] = $_POST['ore_preventivate'] ?? 0;
            break;
            
        case 4: // Task
            $wizardData['tasks'] = $_POST['tasks'] ?? [];
            break;
    }
    
    $_SESSION['pratica_wizard'] = $wizardData;
    
    // Gestione navigazione
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'next') {
            $currentStep++;
            $_SESSION['pratica_wizard_step'] = $currentStep;
        } elseif ($_POST['action'] === 'prev') {
            $currentStep--;
            $_SESSION['pratica_wizard_step'] = $currentStep;
        } elseif ($_POST['action'] === 'save' && $currentStep === 5) {
            // Debug prima di creare
            error_log("Attempting to create pratica with data: " . print_r($wizardData, true));
            
            // Verifica dati obbligatori
            if (empty($wizardData['cliente_id'])) {
                $error_message = 'Errore: Cliente non selezionato';
                error_log("Missing cliente_id");
            } elseif (empty($wizardData['tipo_pratica'])) {
                $error_message = 'Errore: Tipo pratica non selezionato';
                error_log("Missing tipo_pratica");
            } elseif (empty($wizardData['titolo'])) {
                $error_message = 'Errore: Titolo pratica mancante';
                error_log("Missing titolo");
            } elseif (empty($wizardData['data_scadenza'])) {
                $error_message = 'Errore: Data scadenza mancante';
                error_log("Missing data_scadenza");
            } else {
                // Crea pratica
                $praticaId = creaPratica($wizardData, $db, $currentUser);
                if ($praticaId) {
                    unset($_SESSION['pratica_wizard']);
                    unset($_SESSION['pratica_wizard_step']);
                    $_SESSION['success_message'] = '✅ Pratica creata con successo! ID: ' . $praticaId;
                    
                    // Redirect alla lista pratiche invece che alla view
                    // (la view potrebbe non esistere ancora)
                    header("Location: /crm/?action=pratiche");
                    exit;
                } else {
                    $error_message = 'Errore durante la creazione della pratica. Controlla i log per dettagli.';
                    error_log("Failed to create pratica");
                }
            }
        }
    }
}

// Carica dati necessari per ogni step
$clienti = [];
$templates = [];
$templateTasks = [];

// Carica sempre i clienti per lo step 5 (riepilogo)
if ($currentStep === 1 || $currentStep >= 3) {
    // Carica clienti
    if ($currentUser['is_admin']) {
        $clienti = $db->select("
            SELECT id, ragione_sociale, codice_fiscale, partita_iva
            FROM clienti
            WHERE stato = 'attivo'
            ORDER BY ragione_sociale
        ");
    } else {
        $clienti = $db->select("
            SELECT id, ragione_sociale, codice_fiscale, partita_iva
            FROM clienti
            WHERE stato = 'attivo' AND operatore_responsabile_id = ?
            ORDER BY ragione_sociale
        ", [$currentUser['id']]);
    }
}

if ($currentStep >= 2 && !empty($wizardData['tipo_pratica'])) {
    // Carica template per tipo pratica
    $templates = $db->select("
        SELECT * FROM pratiche_template
        WHERE tipo_pratica = ? AND is_attivo = 1
        ORDER BY utilizzi_count DESC, nome
    ", [$wizardData['tipo_pratica']]);
}

if ($currentStep >= 4 && !empty($wizardData['template_id']) && $wizardData['usa_template']) {
    // Carica task del template
    $templateTasks = $db->select("
        SELECT * FROM pratiche_template_task
        WHERE template_id = ?
        ORDER BY ordine
    ", [$wizardData['template_id']]);
} elseif ($currentStep >= 4) {
    // Task di default se non usa template
    $templateTasks = getDefaultTasksForType($wizardData['tipo_pratica']);
}

// Funzioni helper - VERSIONE CORRETTA PER DATABASE SCHEMA
function creaPratica($data, $db, $user) {
    try {
        $db->beginTransaction();
        
        // Ottieni o crea settore di default
        $settoreDefault = $db->selectOne("SELECT id FROM settori WHERE nome = 'Generale' AND is_attivo = 1");
        if (!$settoreDefault) {
            // Crea settore di default se non esiste
            $settoreId = $db->insert('settori', [
                'nome' => 'Generale',
                'descrizione' => 'Settore generale per pratiche non categorizzate',
                'colore_hex' => '#007849',
                'is_attivo' => 1
            ]);
        } else {
            $settoreId = $settoreDefault['id'];
        }
        
        // Inserisci pratica con SOLO I CAMPI CHE ESISTONO NEL DATABASE
        $praticaId = $db->insert('pratiche', [
            'settore_id' => $settoreId, // CAMPO OBBLIGATORIO
            'cliente_id' => $data['cliente_id'], // CAMPO OBBLIGATORIO
            'operatore_assegnato_id' => $user['id'], // Assegna all'utente corrente
            'titolo' => $data['titolo'],
            'descrizione' => $data['descrizione'],
            'stato' => 'in_corso', // CORRETTO: valori validi sono 'da_iniziare', 'in_corso', 'completata', 'sospesa'
            'priorita' => $data['priorita'],
            'data_scadenza' => $data['data_scadenza'],
            'ore_stimate' => floatval($data['ore_preventivate']), // Nome campo nel DB schema
            'ore_lavorate' => 0.00 // Inizializza a 0
        ]);
        
        // Log per debug
        error_log("Pratica creata con ID: " . $praticaId);
        
        // Crea task con campi e stati corretti
        if (!empty($data['tasks'])) {
            foreach ($data['tasks'] as $index => $task) {
                if (!empty($task['titolo'])) {
                    // Calcola data scadenza task (7 giorni dopo per default)
                    $dataScadenzaTask = date('Y-m-d', strtotime('+7 days'));
                    
                    $taskId = $db->insert('task', [
                        'pratica_id' => $praticaId,
                        'cliente_id' => $data['cliente_id'], // CAMPO OBBLIGATORIO
                        'titolo' => $task['titolo'],
                        'descrizione' => $task['descrizione'] ?? '',
                        'data_scadenza' => $dataScadenzaTask, // CAMPO OBBLIGATORIO
                        'stato' => 'da_iniziare', // CORRETTO: valori validi sono 'da_iniziare', 'in_corso', 'completato', 'sospeso'
                        'priorita' => 'media',
                        'ore_stimate' => floatval($task['ore_stimate'] ?? 0),
                        'ore_lavorate' => 0.00,
                        'operatore_assegnato_id' => $user['id']
                    ]);
                    
                    error_log("Task creato con ID: " . $taskId);
                }
            }
        }
        
        $db->commit();
        
        // Salva informazioni aggiuntive in sessione per uso futuro
        $_SESSION['pratica_info'] = [
            'id' => $praticaId,
            'tipo_pratica' => $data['tipo_pratica'],
            'valore_pratica' => $data['valore_pratica'],
            'template_id' => $data['template_id'] ?? null
        ];
        
        return $praticaId;
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore creazione pratica DETTAGLIATO: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function getDefaultTasksForType($tipo) {
    // Task di default per tipo pratica
    $defaults = [
        'dichiarazione_iva' => [
            ['titolo' => 'Raccolta documenti', 'ore_stimate' => 1],
            ['titolo' => 'Elaborazione dati', 'ore_stimate' => 3],
            ['titolo' => 'Invio telematico', 'ore_stimate' => 0.5]
        ],
        'bilancio_ordinario' => [
            ['titolo' => 'Raccolta documenti', 'ore_stimate' => 2],
            ['titolo' => 'Redazione bilancio', 'ore_stimate' => 8],
            ['titolo' => 'Deposito CCIAA', 'ore_stimate' => 1]
        ]
    ];
    
    return $defaults[$tipo] ?? [
        ['titolo' => 'Task 1', 'ore_stimate' => 1]
    ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova Pratica - CRM Re.De Consulting</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    
    <style>
        /* Wizard styles - LAYOUT LARGO */
        .wizard-container {
            max-width: 1280px; /* FIX: era 900px */
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        /* Progress bar */
        .wizard-progress {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }
        
        .progress-step {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s;
        }
        
        .progress-step.active .step-circle {
            border-color: var(--primary-green);
            color: var(--primary-green);
            background: var(--primary-green);
            color: white;
        }
        
        .progress-step.completed .step-circle {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }
        
        .step-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .progress-step.active .step-label {
            color: var(--primary-green);
            font-weight: 600;
        }
        
        /* Content card */
        .wizard-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 2rem;
            min-height: 400px;
        }
        
        .step-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .step-description {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }
        
        /* Form styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-label.required::after {
            content: ' *';
            color: #dc2626;
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0,120,73,0.1);
        }
        
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-help {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        /* Cliente cards - FIX CLASSE */
        .clienti-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .cliente-card {
            display: block;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }
        
        .cliente-card:hover {
            border-color: var(--primary-green);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .cliente-card.selected {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }
        
        .cliente-nome {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .cliente-info {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        /* Template options */
        .template-option {
            display: block;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            margin-bottom: 0.75rem;
        }
        
        .template-option:hover {
            border-color: var(--primary-green);
        }
        
        .template-option.selected {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }
        
        .template-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .template-info {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .template-radio {
            display: none;
        }
        
        /* Task list */
        .task-list {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .task-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            background: white;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .task-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e5e7eb;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }
        
        .task-title-input {
            flex: 1;
            margin: 0 0.75rem;
        }
        
        .task-ore {
            width: 80px;
        }
        
        .task-actions {
            display: flex;
            gap: 0.25rem;
        }
        
        .btn-icon {
            padding: 0.25rem;
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        /* Navigation */
        .wizard-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a37;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Summary */
        .summary-section {
            margin-bottom: 1.5rem;
        }
        
        .summary-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .summary-content {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.8125rem;
        }
        
        .summary-label {
            color: #6b7280;
        }
        
        .summary-value {
            font-weight: 500;
            color: #1f2937;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .wizard-container {
                margin: 1rem auto;
            }
            
            .wizard-content {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .step-label {
                display: none;
            }
        }
        
        /* Search box */
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-input {
            padding-left: 2.5rem;
        }
        
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="wizard-container">
                    <!-- Progress Bar -->
                    <div class="wizard-progress">
                        <div class="progress-steps">
                            <div class="progress-step <?= $currentStep >= 1 ? 'active' : '' ?> <?= $currentStep > 1 ? 'completed' : '' ?>">
                                <div class="step-circle">1</div>
                                <div class="step-label">Cliente</div>
                            </div>
                            <div class="progress-step <?= $currentStep >= 2 ? 'active' : '' ?> <?= $currentStep > 2 ? 'completed' : '' ?>">
                                <div class="step-circle">2</div>
                                <div class="step-label">Tipo e Template</div>
                            </div>
                            <div class="progress-step <?= $currentStep >= 3 ? 'active' : '' ?> <?= $currentStep > 3 ? 'completed' : '' ?>">
                                <div class="step-circle">3</div>
                                <div class="step-label">Dettagli</div>
                            </div>
                            <div class="progress-step <?= $currentStep >= 4 ? 'active' : '' ?> <?= $currentStep > 4 ? 'completed' : '' ?>">
                                <div class="step-circle">4</div>
                                <div class="step-label">Task</div>
                            </div>
                            <div class="progress-step <?= $currentStep >= 5 ? 'active' : '' ?>">
                                <div class="step-circle">5</div>
                                <div class="step-label">Conferma</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div class="wizard-content">
                        <?php if (isset($error_message) && !empty($error_message)): ?>
                            <div class="alert alert-error" style="background: #fee; border: 1px solid #fcc; color: #c00; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                                ⚠️ <?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['debug']) && $_SESSION['debug']): ?>
                            <div style="background: #f0f0f0; border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem; font-family: monospace; font-size: 0.8rem;">
                                <strong>DEBUG:</strong><br>
                                Step: <?= $currentStep ?><br>
                                Action: <?= $_POST['action'] ?? 'none' ?><br>
                                Wizard Data: <pre><?= print_r($wizardData, true) ?></pre>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="<?= $currentStep ?>">
                            
                            <?php if ($currentStep === 1): ?>
                                <!-- Step 1: Selezione Cliente - CORRETTO -->
                                <h2 class="step-title">Seleziona Cliente</h2>
                                <p class="step-description">
                                    Scegli il cliente per cui creare la pratica
                                </p>
                                
                                <?php if (!empty($clienti)): ?>
                                    <div class="search-box">
                                        <span class="search-icon">🔍</span>
                                        <input type="text" 
                                               class="form-control search-input" 
                                               placeholder="Cerca cliente..."
                                               onkeyup="filterClienti(this.value)">
                                    </div>
                                    
                                    <div class="clienti-list">
                                        <?php foreach ($clienti as $cliente): ?>
                                            <label class="cliente-card <?= ($wizardData['cliente_id'] ?? '') == $cliente['id'] ? 'selected' : '' ?>">
                                                <input type="radio" 
                                                       name="cliente_id" 
                                                       value="<?= $cliente['id'] ?>"
                                                       <?= ($wizardData['cliente_id'] ?? '') == $cliente['id'] ? 'checked' : '' ?>
                                                       style="display: none;">
                                                <div class="cliente-nome">
                                                    <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                                </div>
                                                <div class="cliente-info">
                                                    <?php if ($cliente['codice_fiscale']): ?>
                                                        CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($cliente['partita_iva']): ?>
                                                        • P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🏢</div>
                                        <p>Nessun cliente disponibile</p>
                                        <a href="/crm/?action=clienti&view=create" class="btn btn-primary">
                                            Crea nuovo cliente
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                            <?php elseif ($currentStep === 2): ?>
                                <!-- Step 2: Tipo e Template -->
                                <h2 class="step-title">Tipo di Pratica</h2>
                                <p class="step-description">
                                    Seleziona il tipo di pratica e scegli se utilizzare un template
                                </p>
                                
                                <div class="form-group">
                                    <label class="form-label required">Tipo di pratica</label>
                                    <select name="tipo_pratica" class="form-control form-select" required onchange="loadTemplates(this.value)">
                                        <option value="">Seleziona tipo...</option>
                                        <?php foreach (PRATICHE_TYPES as $key => $tipo): ?>
                                            <option value="<?= $key ?>" <?= ($wizardData['tipo_pratica'] ?? '') === $key ? 'selected' : '' ?>>
                                                <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (!empty($templates)): ?>
                                    <div class="form-group">
                                        <label class="form-label">Usa un template</label>
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   name="usa_template" 
                                                   id="usa_template"
                                                   <?= $wizardData['usa_template'] ?? false ? 'checked' : '' ?>
                                                   onchange="toggleTemplates()">
                                            <label for="usa_template" style="margin-left: 0.5rem; font-weight: normal;">
                                                Utilizza un template predefinito
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div id="templates-list" style="<?= $wizardData['usa_template'] ?? false ? '' : 'display: none;' ?>">
                                        <?php foreach ($templates as $template): ?>
                                            <label class="template-option <?= ($wizardData['template_id'] ?? '') == $template['id'] ? 'selected' : '' ?>">
                                                <input type="radio" 
                                                       name="template_id" 
                                                       value="<?= $template['id'] ?>"
                                                       class="template-radio"
                                                       <?= ($wizardData['template_id'] ?? '') == $template['id'] ? 'checked' : '' ?>>
                                                <div class="template-name">
                                                    <?= htmlspecialchars($template['nome']) ?>
                                                </div>
                                                <div class="template-info">
                                                    <?= htmlspecialchars($template['descrizione'] ?? '') ?>
                                                    <br>
                                                    <strong>Ore stimate:</strong> <?= $template['ore_totali_stimate'] ?>h
                                                    • <strong>Giorni:</strong> <?= $template['giorni_completamento'] ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                            <?php elseif ($currentStep === 3): ?>
                                <!-- Step 3: Dettagli e Scadenze -->
                                <h2 class="step-title">Dettagli Pratica</h2>
                                <p class="step-description">
                                    Inserisci i dettagli della pratica
                                </p>
                                
                                <?php
                                // Valori di default basati su tipo pratica
                                $tipoConfig = getPraticaType($wizardData['tipo_pratica'] ?? '');
                                $defaultTitolo = $tipoConfig['label'] . ' - ' . date('Y');
                                $defaultOre = $tipoConfig['ore_default'] ?? 10;
                                $defaultScadenza = date('Y-m-d', strtotime('+30 days'));
                                ?>
                                
                                <div class="form-group">
                                    <label class="form-label required">Titolo pratica</label>
                                    <input type="text" 
                                           name="titolo" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($wizardData['titolo'] ?? $defaultTitolo) ?>"
                                           required>
                                    <div class="form-help">
                                        Inserisci un titolo descrittivo per la pratica
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Descrizione</label>
                                    <textarea name="descrizione" 
                                              class="form-control form-textarea"><?= htmlspecialchars($wizardData['descrizione'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label required">Priorità</label>
                                        <select name="priorita" class="form-control form-select" required>
                                            <?php foreach (PRATICHE_PRIORITA as $key => $priorita): ?>
                                                <option value="<?= $key ?>" <?= ($wizardData['priorita'] ?? 'media') === $key ? 'selected' : '' ?>>
                                                    <?= $priorita['label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">Data scadenza</label>
                                        <input type="date" 
                                               name="data_scadenza" 
                                               class="form-control" 
                                               value="<?= $wizardData['data_scadenza'] ?? $defaultScadenza ?>"
                                               min="<?= date('Y-m-d') ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Ore preventivate</label>
                                        <input type="number" 
                                               name="ore_preventivate" 
                                               class="form-control" 
                                               value="<?= $wizardData['ore_preventivate'] ?? $defaultOre ?>"
                                               step="0.5"
                                               min="0">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Valore pratica (€)</label>
                                        <input type="number" 
                                               name="valore_pratica" 
                                               class="form-control" 
                                               value="<?= $wizardData['valore_pratica'] ?? 0 ?>"
                                               step="0.01"
                                               min="0">
                                    </div>
                                </div>
                                
                            <?php elseif ($currentStep === 4): ?>
                                <!-- Step 4: Task -->
                                <h2 class="step-title">Task della Pratica</h2>
                                <p class="step-description">
                                    Definisci i task da completare per questa pratica
                                </p>
                                
                                <div class="task-list" id="task-list">
                                    <?php 
                                    $tasks = $wizardData['tasks'] ?? $templateTasks;
                                    if (empty($tasks)) {
                                        $tasks = [['titolo' => '', 'ore_stimate' => 1]];
                                    }
                                    
                                    foreach ($tasks as $index => $task): 
                                    ?>
                                        <div class="task-item" data-index="<?= $index ?>">
                                            <div class="task-header">
                                                <span class="task-number"><?= $index + 1 ?></span>
                                                <input type="text" 
                                                       name="tasks[<?= $index ?>][titolo]" 
                                                       class="form-control task-title-input" 
                                                       placeholder="Titolo del task"
                                                       value="<?= htmlspecialchars($task['titolo'] ?? '') ?>"
                                                       required>
                                                <input type="number" 
                                                       name="tasks[<?= $index ?>][ore_stimate]" 
                                                       class="form-control task-ore" 
                                                       placeholder="Ore"
                                                       value="<?= $task['ore_stimate'] ?? 1 ?>"
                                                       step="0.5"
                                                       min="0">
                                                <div class="task-actions">
                                                    <button type="button" class="btn-icon" onclick="removeTask(<?= $index ?>)" title="Rimuovi">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </div>
                                            <textarea name="tasks[<?= $index ?>][descrizione]" 
                                                      class="form-control form-textarea" 
                                                      placeholder="Descrizione (opzionale)"
                                                      style="margin-top: 0.5rem; min-height: 60px;"><?= htmlspecialchars($task['descrizione'] ?? '') ?></textarea>
                                            <input type="hidden" 
                                                   name="tasks[<?= $index ?>][is_obbligatorio]" 
                                                   value="<?= $task['is_obbligatorio'] ?? 1 ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="btn btn-secondary" onclick="addTask()" style="margin-top: 1rem;">
                                    ➕ Aggiungi Task
                                </button>
                                
                            <?php elseif ($currentStep === 5): ?>
                                <!-- Step 5: Riepilogo e Conferma -->
                                <h2 class="step-title">Riepilogo e Conferma</h2>
                                <p class="step-description">
                                    Verifica i dati inseriti prima di creare la pratica
                                </p>
                                
                                <?php
                                // Recupera dati per riepilogo
                                $clienteSelezionato = null;
                                foreach ($clienti as $c) {
                                    if ($c['id'] == $wizardData['cliente_id']) {
                                        $clienteSelezionato = $c;
                                        break;
                                    }
                                }
                                
                                $tipoConfig = getPraticaType($wizardData['tipo_pratica'] ?? '');
                                ?>
                                
                                <div class="summary-section">
                                    <h3 class="summary-title">📋 Informazioni Generali</h3>
                                    <div class="summary-content">
                                        <div class="summary-item">
                                            <span class="summary-label">Cliente:</span>
                                            <span class="summary-value">
                                                <?= htmlspecialchars($clienteSelezionato['ragione_sociale'] ?? 'N/D') ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Tipo pratica:</span>
                                            <span class="summary-value">
                                                <?= $tipoConfig['icon'] ?> <?= $tipoConfig['label'] ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Titolo:</span>
                                            <span class="summary-value">
                                                <?= htmlspecialchars($wizardData['titolo'] ?? '') ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Priorità:</span>
                                            <span class="summary-value">
                                                <?= PRATICHE_PRIORITA[$wizardData['priorita'] ?? 'media']['label'] ?? '' ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Scadenza:</span>
                                            <span class="summary-value">
                                                <?= date('d/m/Y', strtotime($wizardData['data_scadenza'] ?? 'now')) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="summary-section">
                                    <h3 class="summary-title">⚙️ Dettagli Economici</h3>
                                    <div class="summary-content">
                                        <div class="summary-item">
                                            <span class="summary-label">Ore preventivate:</span>
                                            <span class="summary-value">
                                                <?= $wizardData['ore_preventivate'] ?? 0 ?>h
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Valore pratica:</span>
                                            <span class="summary-value">
                                                € <?= number_format($wizardData['valore_pratica'] ?? 0, 2, ',', '.') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($wizardData['tasks'])): ?>
                                    <div class="summary-section">
                                        <h3 class="summary-title">📝 Task Previsti</h3>
                                        <div class="summary-content">
                                            <?php 
                                            $totaleOreTask = 0;
                                            foreach ($wizardData['tasks'] as $task): 
                                                if (!empty($task['titolo'])):
                                                    $totaleOreTask += $task['ore_stimate'] ?? 0;
                                            ?>
                                                <div class="summary-item">
                                                    <span class="summary-label"><?= htmlspecialchars($task['titolo']) ?>:</span>
                                                    <span class="summary-value"><?= $task['ore_stimate'] ?? 0 ?>h</span>
                                                </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                            <div class="summary-item" style="border-top: 1px solid #e5e7eb; padding-top: 0.5rem; margin-top: 0.5rem;">
                                                <span class="summary-label"><strong>Totale ore task:</strong></span>
                                                <span class="summary-value"><strong><?= $totaleOreTask ?>h</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Navigation -->
                            <div class="wizard-navigation">
                                <div>
                                    <?php if ($currentStep > 1): ?>
                                        <button type="submit" name="action" value="prev" class="btn btn-secondary">
                                            ← Indietro
                                        </button>
                                    <?php else: ?>
                                        <a href="/crm/?action=pratiche" class="btn btn-secondary">
                                            Annulla
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <?php if ($currentStep < 5): ?>
                                        <button type="submit" 
                                                name="action" 
                                                value="next" 
                                                class="btn btn-primary"
                                                <?= $currentStep === 1 && empty($wizardData['cliente_id']) ? 'disabled' : '' ?>>
                                            Avanti →
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" 
                                                name="action" 
                                                value="save" 
                                                class="btn btn-primary"
                                                onclick="return confirm('Confermi la creazione della pratica?')">
                                            ✅ Crea Pratica
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Gestione selezione cliente - JAVASCRIPT CORRETTO
        document.querySelectorAll('.cliente-card').forEach(card => {
            card.addEventListener('click', function() {
                // Rimuovi selezione precedente
                document.querySelectorAll('.cliente-card').forEach(c => c.classList.remove('selected'));
                
                // Aggiungi selezione corrente
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                
                // Abilita bottone next
                const nextBtn = document.querySelector('button[value="next"]');
                if (nextBtn) {
                    nextBtn.disabled = false;
                }
            });
        });
        
        // Gestione selezione template
        document.querySelectorAll('.template-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.template-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Filtro clienti
        function filterClienti(search) {
            const searchLower = search.toLowerCase();
            document.querySelectorAll('.cliente-card').forEach(card => {
                const nome = card.querySelector('.cliente-nome').textContent.toLowerCase();
                const info = card.querySelector('.cliente-info').textContent.toLowerCase();
                
                if (nome.includes(searchLower) || info.includes(searchLower)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Toggle templates
        function toggleTemplates() {
            const useTemplate = document.getElementById('usa_template').checked;
            document.getElementById('templates-list').style.display = useTemplate ? 'block' : 'none';
        }
        
        // Gestione task
        let taskIndex = <?= count($wizardData['tasks'] ?? []) ?>;
        
        function addTask() {
            const taskList = document.getElementById('task-list');
            const newTask = document.createElement('div');
            newTask.className = 'task-item';
            newTask.dataset.index = taskIndex;
            
            newTask.innerHTML = `
                <div class="task-header">
                    <span class="task-number">${taskIndex + 1}</span>
                    <input type="text" 
                           name="tasks[${taskIndex}][titolo]" 
                           class="form-control task-title-input" 
                           placeholder="Titolo del task"
                           required>
                    <input type="number" 
                           name="tasks[${taskIndex}][ore_stimate]" 
                           class="form-control task-ore" 
                           placeholder="Ore"
                           value="1"
                           step="0.5"
                           min="0">
                    <div class="task-actions">
                        <button type="button" class="btn-icon" onclick="removeTask(${taskIndex})" title="Rimuovi">
                            🗑️
                        </button>
                    </div>
                </div>
                <textarea name="tasks[${taskIndex}][descrizione]" 
                          class="form-control form-textarea" 
                          placeholder="Descrizione (opzionale)"
                          style="margin-top: 0.5rem; min-height: 60px;"></textarea>
                <input type="hidden" name="tasks[${taskIndex}][is_obbligatorio]" value="1">
            `;
            
            taskList.appendChild(newTask);
            taskIndex++;
            
            // Rinumera task
            updateTaskNumbers();
        }
        
        function removeTask(index) {
            const taskItem = document.querySelector(`[data-index="${index}"]`);
            if (taskItem && document.querySelectorAll('.task-item').length > 1) {
                taskItem.remove();
                updateTaskNumbers();
            }
        }
        
        function updateTaskNumbers() {
            document.querySelectorAll('.task-item').forEach((item, index) => {
                item.querySelector('.task-number').textContent = index + 1;
            });
        }
        
        // Carica template per tipo
        function loadTemplates(tipo) {
            if (tipo) {
                document.querySelector('button[value="next"]').click();
            }
        }
    </script>
</body>
</html>