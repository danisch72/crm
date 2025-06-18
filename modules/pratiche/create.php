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
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router disponibili:
// $sessionInfo, $db, $currentUser

// Gestione step wizard
$currentStep = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$wizardData = $_SESSION['pratica_wizard'] ?? [];

// Se form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
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
        } elseif ($_POST['action'] === 'prev') {
            $currentStep--;
        } elseif ($_POST['action'] === 'save' && $currentStep === 5) {
            // Crea pratica
            $praticaId = creaPratica($wizardData, $db, $currentUser);
            if ($praticaId) {
                unset($_SESSION['pratica_wizard']);
                $_SESSION['success_message'] = '✅ Pratica creata con successo!';
                header("Location: /crm/?action=pratiche&view=view&id=$praticaId");
                exit;
            } else {
                $error_message = 'Errore durante la creazione della pratica';
            }
        }
    }
}

// Carica dati necessari per ogni step
$clienti = [];
$templates = [];
$templateTasks = [];

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

// Funzioni helper
function creaPratica($data, $db, $user) {
    try {
        $db->beginTransaction();
        
        // Genera numero pratica
        $anno = date('Y');
        $lastNumber = $db->selectOne("
            SELECT MAX(CAST(SUBSTRING_INDEX(numero_pratica, '-', -1) AS UNSIGNED)) as last_num
            FROM pratiche
            WHERE numero_pratica LIKE ?
        ", ["PRT-$anno-%"]);
        
        $nextNumber = ($lastNumber['last_num'] ?? 0) + 1;
        $numeroPratica = sprintf("PRT-%s-%06d", $anno, $nextNumber);
        
        // Inserisci pratica
        $praticaId = $db->insert('pratiche', [
            'numero_pratica' => $numeroPratica,
            'cliente_id' => $data['cliente_id'],
            'tipo_pratica' => $data['tipo_pratica'],
            'titolo' => $data['titolo'],
            'descrizione' => $data['descrizione'],
            'priorita' => $data['priorita'],
            'data_apertura' => date('Y-m-d'),
            'data_scadenza' => $data['data_scadenza'],
            'stato' => 'attiva',
            'operatore_responsabile_id' => $user['id'],
            'valore_pratica' => $data['valore_pratica'],
            'ore_preventivate' => $data['ore_preventivate'],
            'template_id' => $data['template_id'] ?? null,
            'created_by' => $user['id']
        ]);
        
        // Crea task
        if (!empty($data['tasks'])) {
            foreach ($data['tasks'] as $index => $task) {
                if (!empty($task['titolo'])) {
                    $db->insert('task', [
                        'pratica_id' => $praticaId,
                        'titolo' => $task['titolo'],
                        'descrizione' => $task['descrizione'] ?? '',
                        'ordine' => $index + 1,
                        'ore_stimate' => $task['ore_stimate'] ?? 0,
                        'is_obbligatorio' => $task['is_obbligatorio'] ?? 1,
                        'stato' => 'da_fare',
                        'operatore_assegnato_id' => $user['id'],
                        'operatore_creato_id' => $user['id']
                    ]);
                }
            }
        }
        
        // Aggiorna contatore template
        if (!empty($data['template_id'])) {
            $db->query("
                UPDATE pratiche_template 
                SET utilizzi_count = utilizzi_count + 1 
                WHERE id = ?
            ", [$data['template_id']]);
        }
        
        $db->commit();
        return $praticaId;
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Errore creazione pratica: " . $e->getMessage());
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
        /* Wizard styles */
        .wizard-container {
            max-width: 900px;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Cliente card */
        .cliente-card {
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cliente-card:hover {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }
        
        .cliente-card.selected {
            border-color: var(--primary-green);
            background: #f0fdf4;
            border-width: 2px;
        }
        
        .cliente-nome {
            font-weight: 600;
            font-size: 0.875rem;
            color: #1f2937;
        }
        
        .cliente-info {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Template card */
        .template-option {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .template-option:hover {
            border-color: var(--primary-green);
            background: #f9fafb;
        }
        
        .template-option.selected {
            border-color: var(--primary-green);
            background: #f0fdf4;
        }
        
        .template-radio {
            margin-top: 0.125rem;
        }
        
        .template-content {
            flex: 1;
        }
        
        .template-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .template-description {
            font-size: 0.75rem;
            color: #6b7280;
            line-height: 1.4;
        }
        
        .template-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            font-size: 0.6875rem;
            color: #6b7280;
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
                    <form method="POST" class="wizard-form">
                        <input type="hidden" name="step" value="<?= $currentStep ?>">
                        
                        <div class="wizard-content">
                            <?php if ($currentStep === 1): ?>
                                <!-- Step 1: Cliente -->
                                <h2 class="step-title">Seleziona il Cliente</h2>
                                <p class="step-description">
                                    Scegli il cliente per cui creare la nuova pratica
                                </p>
                                
                                <div class="search-box">
                                    <span class="search-icon">🔍</span>
                                    <input type="text" 
                                           class="form-control search-input" 
                                           placeholder="Cerca cliente per nome o codice fiscale..."
                                           onkeyup="filterClienti(this.value)">
                                </div>
                                
                                <div id="clienti-list">
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
                                
                                <?php if (empty($clienti)): ?>
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
                                                <div class="template-content">
                                                    <div class="template-name">
                                                        <?= htmlspecialchars($template['nome']) ?>
                                                    </div>
                                                    <?php if ($template['descrizione']): ?>
                                                        <div class="template-description">
                                                            <?= htmlspecialchars($template['descrizione']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="template-meta">
                                                        <span>⏱️ <?= $template['ore_totali_stimate'] ?>h stimate</span>
                                                        <span>💰 €<?= number_format($template['tariffa_consigliata'], 2, ',', '.') ?></span>
                                                        <span>📅 <?= $template['giorni_completamento'] ?>gg</span>
                                                        <span>✨ Usato <?= $template['utilizzi_count'] ?> volte</span>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                            <?php elseif ($currentStep === 3): ?>
                                <!-- Step 3: Dettagli -->
                                <h2 class="step-title">Dettagli della Pratica</h2>
                                <p class="step-description">
                                    Inserisci le informazioni specifiche per questa pratica
                                </p>
                                
                                <?php
                                // Calcola defaults da template se selezionato
                                $selectedTemplate = null;
                                if (!empty($wizardData['template_id'])) {
                                    foreach ($templates as $t) {
                                        if ($t['id'] == $wizardData['template_id']) {
                                            $selectedTemplate = $t;
                                            break;
                                        }
                                    }
                                }
                                
                                $defaultTitolo = '';
                                $defaultScadenza = date('Y-m-d', strtotime('+30 days'));
                                $defaultOre = 0;
                                $defaultValore = 0;
                                
                                if ($selectedTemplate) {
                                    $defaultTitolo = $selectedTemplate['nome'];
                                    $defaultScadenza = date('Y-m-d', strtotime('+' . $selectedTemplate['giorni_completamento'] . ' days'));
                                    $defaultOre = $selectedTemplate['ore_totali_stimate'];
                                    $defaultValore = $selectedTemplate['tariffa_consigliata'];
                                }
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
                                        <div class="form-help">
                                            Stima delle ore necessarie
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Valore pratica (€)</label>
                                        <input type="number" 
                                               name="valore_pratica" 
                                               class="form-control" 
                                               value="<?= $wizardData['valore_pratica'] ?? $defaultValore ?>"
                                               step="0.01"
                                               min="0">
                                        <div class="form-help">
                                            Valore economico della pratica
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($currentStep === 4): ?>
                                <!-- Step 4: Task -->
                                <h2 class="step-title">Definisci i Task</h2>
                                <p class="step-description">
                                    Configura i task necessari per completare la pratica
                                </p>
                                
                                <div class="task-list" id="task-list">
                                    <?php 
                                    $tasks = $wizardData['tasks'] ?? [];
                                    if (empty($tasks) && !empty($templateTasks)) {
                                        // Usa task del template
                                        foreach ($templateTasks as $index => $task) {
                                            $tasks[] = [
                                                'titolo' => $task['titolo'],
                                                'descrizione' => $task['descrizione'] ?? '',
                                                'ore_stimate' => $task['ore_stimate'] ?? 0,
                                                'is_obbligatorio' => $task['is_obbligatorio'] ?? 1
                                            ];
                                        }
                                    }
                                    
                                    // Assicura almeno un task
                                    if (empty($tasks)) {
                                        $tasks[] = ['titolo' => '', 'ore_stimate' => 1];
                                    }
                                    ?>
                                    
                                    <?php foreach ($tasks as $index => $task): ?>
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
                                
                                $tipoConfig = getPraticaType($wizardData['tipo_pratica']);
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
                                                <?= PRATICHE_PRIORITA[$wizardData['priorita']]['label'] ?? '' ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Scadenza:</span>
                                            <span class="summary-value">
                                                <?= date('d/m/Y', strtotime($wizardData['data_scadenza'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($wizardData['descrizione'])): ?>
                                    <div class="summary-section">
                                        <h3 class="summary-title">📝 Descrizione</h3>
                                        <div class="summary-content">
                                            <?= nl2br(htmlspecialchars($wizardData['descrizione'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="summary-section">
                                    <h3 class="summary-title">💰 Dati Economici</h3>
                                    <div class="summary-content">
                                        <div class="summary-item">
                                            <span class="summary-label">Ore preventivate:</span>
                                            <span class="summary-value">
                                                <?= number_format($wizardData['ore_preventivate'] ?? 0, 1, ',', '.') ?> ore
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Valore pratica:</span>
                                            <span class="summary-value">
                                                €<?= number_format($wizardData['valore_pratica'] ?? 0, 2, ',', '.') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="summary-section">
                                    <h3 class="summary-title">📋 Task previsti (<?= count($wizardData['tasks'] ?? []) ?>)</h3>
                                    <div class="summary-content">
                                        <?php 
                                        $totaleOreTask = 0;
                                        foreach (($wizardData['tasks'] ?? []) as $index => $task): 
                                            if (!empty($task['titolo'])):
                                                $totaleOreTask += floatval($task['ore_stimate'] ?? 0);
                                        ?>
                                            <div class="summary-item">
                                                <span class="summary-label">
                                                    <?= $index + 1 ?>. <?= htmlspecialchars($task['titolo']) ?>
                                                </span>
                                                <span class="summary-value">
                                                    <?= number_format($task['ore_stimate'] ?? 0, 1, ',', '.') ?>h
                                                </span>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                        <div class="summary-item" style="border-top: 1px solid #e5e7eb; margin-top: 0.5rem; padding-top: 0.5rem;">
                                            <span class="summary-label"><strong>Totale ore task:</strong></span>
                                            <span class="summary-value"><strong><?= number_format($totaleOreTask, 1, ',', '.') ?>h</strong></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
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
            </main>
        </div>
    </div>
    
    <script>
        // Gestione selezione cliente
        document.querySelectorAll('.cliente-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.cliente-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                
                // Abilita bottone next
                document.querySelector('button[value="next"]').disabled = false;
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
        
        // Carica template per tipo (placeholder per AJAX futuro)
        function loadTemplates(tipo) {
            // In futuro qui ci sarà una chiamata AJAX per caricare i template
            // Per ora ricarica la pagina mantenendo la selezione
            if (tipo) {
                document.querySelector('button[value="next"]').click();
            }
        }
    </script>
</body>
</html>