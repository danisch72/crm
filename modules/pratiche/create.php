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
            
            // Crea pratica
            try {
                $db->beginTransaction();
                
                // Genera numero pratica
                $numeroPratica = generateNumeroPratica();
                
                // Prepara dati pratica
                $praticaData = [
                    'cliente_id' => $wizardData['cliente_id'],
                    'tipo_pratica' => $wizardData['tipo_pratica'],
                    'titolo' => $wizardData['titolo'],
                    'descrizione' => $wizardData['descrizione'],
                    'stato' => 'da_iniziare',
                    'priorita' => $wizardData['priorita'],
                    'data_scadenza' => $wizardData['data_scadenza'],
                    'ore_stimate' => floatval($wizardData['ore_preventivate']),
                    'operatore_assegnato_id' => $currentUser['id'],
                    'template_id' => $wizardData['template_id'] ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $currentUser['id']
                ];
                
                error_log("Creating pratica with data: " . print_r($praticaData, true));
                
                $praticaId = $db->insert('pratiche', $praticaData);
                
                if (!$praticaId) {
                    throw new Exception("Errore creazione pratica");
                }
                
                // Crea task
                if (!empty($wizardData['tasks'])) {
                    foreach ($wizardData['tasks'] as $index => $task) {
                        if (!empty($task['titolo'])) {
                            $taskData = [
                                'pratica_id' => $praticaId,
                                'titolo' => $task['titolo'],
                                'descrizione' => $task['descrizione'] ?? '',
                                'stato' => 'da_fare',
                                'ore_stimate' => floatval($task['ore_stimate'] ?? 0),
                                'ordine' => $index,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $db->insert('task', $taskData);
                        }
                    }
                }
                
                $db->commit();
                
                // Pulisci sessione wizard
                unset($_SESSION['pratica_wizard']);
                unset($_SESSION['pratica_wizard_step']);
                
                // Redirect a view pratica
                $_SESSION['success_message'] = '✅ Pratica creata con successo!';
                header("Location: /crm/?action=pratiche&view=view&id=$praticaId");
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Errore creazione pratica: " . $e->getMessage());
                $_SESSION['error_message'] = 'Errore durante la creazione della pratica';
            }
        }
    }
}

// Carica dati necessari per gli step
$clienti = [];
$templates = [];
$tipiPratica = PRATICHE_TYPES;

if ($currentStep === 1) {
    // Carica clienti attivi
    $clienti = $db->select("
        SELECT id, ragione_sociale, codice_fiscale, partita_iva, email, telefono
        FROM clienti
        WHERE stato = 'attivo'
        ORDER BY ragione_sociale
    ");
} elseif ($currentStep === 2 && !empty($wizardData['tipo_pratica'])) {
    // Carica template per tipo pratica
    $templates = $db->select("
        SELECT id, nome, descrizione, ore_totali_stimate, tariffa_consigliata
        FROM pratiche_template
        WHERE tipo_pratica = ? AND is_attivo = 1
        ORDER BY nome
    ", [$wizardData['tipo_pratica']]);
} elseif ($currentStep === 4 && !empty($wizardData['template_id'])) {
    // Carica task da template
    $templateTasks = $db->select("
        SELECT titolo, descrizione, ore_stimate, ordine
        FROM pratiche_template_task
        WHERE template_id = ?
        ORDER BY ordine
    ", [$wizardData['template_id']]);
    
    if (empty($wizardData['tasks']) && !empty($templateTasks)) {
        $wizardData['tasks'] = $templateTasks;
    }
}

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova Pratica - CRM Re.De Consulting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/crm/assets/css/style.css">
    <style>
        /* Wizard container largo */
        .wizard-container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        /* Progress bar */
        .wizard-progress {
            display: flex;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: -1;
        }
        
        .progress-step.completed::after {
            background: var(--primary-green);
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            background: #e5e7eb;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        
        .progress-step.active .step-circle,
        .progress-step.completed .step-circle {
            background: var(--primary-green);
            color: white;
        }
        
        .step-label {
            font-size: 0.875rem;
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
        
        /* Cliente cards */
        .clienti-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        
        .cliente-card {
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cliente-card:hover {
            border-color: var(--primary-green);
            box-shadow: 0 2px 8px rgba(0,120,73,0.1);
        }
        
        .cliente-card.selected {
            border-color: var(--primary-green);
            background: rgba(0,120,73,0.05);
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
        
        /* Template cards */
        .template-grid {
            display: grid;
            gap: 1rem;
        }
        
        .template-card {
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .template-card:hover {
            border-color: var(--primary-green);
        }
        
        .template-card.selected {
            border-color: var(--primary-green);
            background: rgba(0,120,73,0.05);
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
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,120,73,0.2);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="main-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="wizard-container">
                    <!-- Progress Bar -->
                    <div class="wizard-progress">
                        <?php
                        $steps = [
                            1 => 'Cliente',
                            2 => 'Tipo Pratica',
                            3 => 'Dettagli',
                            4 => 'Task',
                            5 => 'Conferma'
                        ];
                        
                        foreach ($steps as $step => $label):
                            $isActive = $step === $currentStep;
                            $isCompleted = $step < $currentStep;
                        ?>
                        <div class="progress-step <?= $isActive ? 'active' : '' ?> <?= $isCompleted ? 'completed' : '' ?>">
                            <div class="step-circle"><?= $step ?></div>
                            <div class="step-label"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Content -->
                    <form method="POST" id="wizardForm">
                        <input type="hidden" name="step" value="<?= $currentStep ?>">
                        
                        <div class="wizard-content">
                            <?php if ($currentStep === 1): ?>
                                <!-- Step 1: Selezione Cliente -->
                                <h2 class="step-title">Seleziona il Cliente</h2>
                                <p class="step-description">Scegli il cliente per cui creare la pratica</p>
                                
                                <div class="form-group">
                                    <input type="text" 
                                           class="form-control" 
                                           placeholder="🔍 Cerca cliente..."
                                           onkeyup="filterClienti(this.value)">
                                </div>
                                
                                <div class="clienti-grid">
                                    <?php foreach ($clienti as $cliente): ?>
                                    <div class="cliente-card <?= ($wizardData['cliente_id'] ?? '') == $cliente['id'] ? 'selected' : '' ?>"
                                         onclick="selectCliente(<?= $cliente['id'] ?>)"
                                         data-nome="<?= strtolower($cliente['ragione_sociale']) ?>">
                                        <div class="cliente-nome"><?= htmlspecialchars($cliente['ragione_sociale']) ?></div>
                                        <div class="cliente-info">
                                            <?php if ($cliente['codice_fiscale']): ?>
                                                CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?>
                                            <?php elseif ($cliente['partita_iva']): ?>
                                                P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="cliente-info">
                                            <?= htmlspecialchars($cliente['email'] ?? 'Email non disponibile') ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <input type="hidden" name="cliente_id" id="cliente_id" value="<?= $wizardData['cliente_id'] ?? '' ?>">
                                
                            <?php elseif ($currentStep === 2): ?>
                                <!-- Step 2: Tipo Pratica e Template -->
                                <h2 class="step-title">Tipo di Pratica</h2>
                                <p class="step-description">Seleziona il tipo di pratica e se utilizzare un template</p>
                                
                                <div class="form-group">
                                    <label class="form-label required">Tipo Pratica</label>
                                    <select name="tipo_pratica" class="form-control form-select" required onchange="loadTemplates(this.value)">
                                        <option value="">Seleziona tipo...</option>
                                        <?php foreach ($tipiPratica as $key => $tipo): ?>
                                        <option value="<?= $key ?>" <?= ($wizardData['tipo_pratica'] ?? '') === $key ? 'selected' : '' ?>>
                                            <?= $tipo['icon'] ?> <?= $tipo['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if (!empty($templates)): ?>
                                <div class="form-group">
                                    <label class="form-label">Template Disponibili</label>
                                    <div class="template-grid">
                                        <div class="template-card <?= empty($wizardData['template_id']) ? 'selected' : '' ?>"
                                             onclick="selectTemplate(null)">
                                            <strong>➕ Crea pratica vuota</strong>
                                            <p class="text-muted">Definisci manualmente tutti i task</p>
                                        </div>
                                        
                                        <?php foreach ($templates as $template): ?>
                                        <div class="template-card <?= ($wizardData['template_id'] ?? '') == $template['id'] ? 'selected' : '' ?>"
                                             onclick="selectTemplate(<?= $template['id'] ?>)">
                                            <strong><?= htmlspecialchars($template['nome']) ?></strong>
                                            <p class="text-muted"><?= htmlspecialchars($template['descrizione']) ?></p>
                                            <div class="template-meta">
                                                <span>⏱️ <?= $template['ore_totali_stimate'] ?>h stimate</span>
                                                <span>💰 €<?= number_format($template['tariffa_consigliata'], 2, ',', '.') ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <input type="hidden" name="template_id" id="template_id" value="<?= $wizardData['template_id'] ?? '' ?>">
                                <input type="hidden" name="usa_template" value="<?= $wizardData['usa_template'] ?? 0 ?>">
                                
                            <?php elseif ($currentStep === 3): ?>
                                <!-- Step 3: Dettagli Pratica -->
                                <h2 class="step-title">Dettagli Pratica</h2>
                                <p class="step-description">Inserisci le informazioni principali</p>
                                
                                <div class="form-group">
                                    <label class="form-label required">Titolo Pratica</label>
                                    <input type="text" 
                                           name="titolo" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($wizardData['titolo'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Descrizione</label>
                                    <textarea name="descrizione" 
                                              class="form-control form-textarea"><?= htmlspecialchars($wizardData['descrizione'] ?? '') ?></textarea>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label required">Priorità</label>
                                        <select name="priorita" class="form-control form-select" required>
                                            <?php foreach (PRATICHE_PRIORITA as $key => $priorita): ?>
                                            <option value="<?= $key ?>" <?= ($wizardData['priorita'] ?? 'media') === $key ? 'selected' : '' ?>>
                                                <?= $priorita['icon'] ?> <?= $priorita['label'] ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label required">Data Scadenza</label>
                                        <input type="date" 
                                               name="data_scadenza" 
                                               class="form-control"
                                               value="<?= $wizardData['data_scadenza'] ?? '' ?>"
                                               min="<?= date('Y-m-d') ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label">Ore Preventivate</label>
                                        <input type="number" 
                                               name="ore_preventivate" 
                                               class="form-control"
                                               value="<?= $wizardData['ore_preventivate'] ?? '' ?>"
                                               min="0"
                                               step="0.5">
                                        <p class="form-help">Stima delle ore necessarie</p>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Valore Pratica</label>
                                        <input type="number" 
                                               name="valore_pratica" 
                                               class="form-control"
                                               value="<?= $wizardData['valore_pratica'] ?? '' ?>"
                                               min="0"
                                               step="0.01">
                                        <p class="form-help">Valore economico stimato</p>
                                    </div>
                                </div>
                                
                            <?php elseif ($currentStep === 4): ?>
                                <!-- Step 4: Task -->
                                <h2 class="step-title">Definizione Task</h2>
                                <p class="step-description">Aggiungi o modifica i task della pratica</p>
                                
                                <div class="task-list" id="taskList">
                                    <?php 
                                    $tasks = $wizardData['tasks'] ?? [];
                                    if (empty($tasks)) {
                                        $tasks = [['titolo' => '', 'descrizione' => '', 'ore_stimate' => '']];
                                    }
                                    
                                    foreach ($tasks as $index => $task): 
                                    ?>
                                    <div class="task-item" data-index="<?= $index ?>">
                                        <div class="task-header">
                                            <span class="task-number"><?= $index + 1 ?></span>
                                            <input type="text" 
                                                   name="tasks[<?= $index ?>][titolo]" 
                                                   class="form-control task-title-input"
                                                   placeholder="Titolo task..."
                                                   value="<?= htmlspecialchars($task['titolo'] ?? '') ?>">
                                            <input type="number" 
                                                   name="tasks[<?= $index ?>][ore_stimate]" 
                                                   class="form-control task-ore"
                                                   placeholder="Ore"
                                                   min="0"
                                                   step="0.5"
                                                   value="<?= $task['ore_stimate'] ?? '' ?>">
                                            <div class="task-actions">
                                                <button type="button" class="btn-icon" onclick="removeTask(<?= $index ?>)">🗑️</button>
                                            </div>
                                        </div>
                                        <textarea name="tasks[<?= $index ?>][descrizione]" 
                                                  class="form-control form-textarea"
                                                  placeholder="Descrizione task..."
                                                  style="margin-top: 0.5rem;"><?= htmlspecialchars($task['descrizione'] ?? '') ?></textarea>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="btn btn-secondary" onclick="addTask()" style="margin-top: 1rem;">
                                    ➕ Aggiungi Task
                                </button>
                                
                            <?php elseif ($currentStep === 5): ?>
                                <!-- Step 5: Conferma -->
                                <h2 class="step-title">Riepilogo e Conferma</h2>
                                <p class="step-description">Verifica i dati prima di creare la pratica</p>
                                
                                <?php
                                // Carica info cliente
                                $clienteInfo = null;
                                if (!empty($wizardData['cliente_id'])) {
                                    $clienteInfo = $db->selectOne(
                                        "SELECT ragione_sociale FROM clienti WHERE id = ?",
                                        [$wizardData['cliente_id']]
                                    );
                                }
                                ?>
                                
                                <div style="background: #f9fafb; padding: 1.5rem; border-radius: 8px;">
                                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Dettagli Pratica</h3>
                                    
                                    <dl style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem 1rem;">
                                        <dt style="font-weight: 500; color: #6b7280;">Cliente:</dt>
                                        <dd><?= htmlspecialchars($clienteInfo['ragione_sociale'] ?? 'N/D') ?></dd>
                                        
                                        <dt style="font-weight: 500; color: #6b7280;">Tipo:</dt>
                                        <dd><?= PRATICHE_TYPES[$wizardData['tipo_pratica'] ?? '']['label'] ?? 'N/D' ?></dd>
                                        
                                        <dt style="font-weight: 500; color: #6b7280;">Titolo:</dt>
                                        <dd><?= htmlspecialchars($wizardData['titolo'] ?? 'N/D') ?></dd>
                                        
                                        <dt style="font-weight: 500; color: #6b7280;">Priorità:</dt>
                                        <dd><?= PRATICHE_PRIORITA[$wizardData['priorita'] ?? 'media']['label'] ?></dd>
                                        
                                        <dt style="font-weight: 500; color: #6b7280;">Scadenza:</dt>
                                        <dd><?= !empty($wizardData['data_scadenza']) ? date('d/m/Y', strtotime($wizardData['data_scadenza'])) : 'N/D' ?></dd>
                                        
                                        <dt style="font-weight: 500; color: #6b7280;">Task previsti:</dt>
                                        <dd><?= count($wizardData['tasks'] ?? []) ?></dd>
                                        
                                        <dt style="font-weight: 500; color: #6b7280;">Ore totali:</dt>
                                        <dd>
                                            <?php
                                            $oreTotali = 0;
                                            foreach (($wizardData['tasks'] ?? []) as $task) {
                                                $oreTotali += floatval($task['ore_stimate'] ?? 0);
                                            }
                                            echo number_format($oreTotali, 1, ',', '.');
                                            ?> ore
                                        </dd>
                                    </dl>
                                </div>
                                
                                <?php if (!empty($wizardData['tasks'])): ?>
                                <div style="margin-top: 1.5rem;">
                                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Task Previsti</h3>
                                    <ol style="list-style: decimal; padding-left: 1.5rem;">
                                        <?php foreach ($wizardData['tasks'] as $task): 
                                            if (empty($task['titolo'])) continue;
                                        ?>
                                        <li style="margin-bottom: 0.5rem;">
                                            <?= htmlspecialchars($task['titolo']) ?>
                                            <?php if (!empty($task['ore_stimate'])): ?>
                                                <span style="color: #6b7280; font-size: 0.875rem;">
                                                    (<?= number_format($task['ore_stimate'], 1, ',', '.') ?> ore)
                                                </span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                                <?php endif; ?>
                                
                            <?php endif; ?>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="wizard-navigation">
                            <div>
                                <?php if ($currentStep > 1): ?>
                                <button type="submit" name="action" value="prev" class="btn btn-secondary">
                                    ◀ Indietro
                                </button>
                                <?php else: ?>
                                <a href="/crm/?action=pratiche" class="btn btn-secondary">
                                    ✖ Annulla
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <?php if ($currentStep < 5): ?>
                                <button type="submit" 
                                        name="action" 
                                        value="next" 
                                        class="btn btn-primary"
                                        id="btnNext">
                                    Avanti ▶
                                </button>
                                <?php else: ?>
                                <button type="submit" 
                                        name="action" 
                                        value="save" 
                                        class="btn btn-primary">
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
        function selectCliente(id) {
            document.querySelectorAll('.cliente-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            document.getElementById('cliente_id').value = id;
            
            // Abilita pulsante next
            checkStep1Validity();
        }
        
        function filterClienti(search) {
            const term = search.toLowerCase();
            document.querySelectorAll('.cliente-card').forEach(card => {
                const nome = card.dataset.nome;
                card.style.display = nome.includes(term) ? 'block' : 'none';
            });
        }
        
        // Gestione template
        function selectTemplate(id) {
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            document.getElementById('template_id').value = id || '';
            document.querySelector('[name="usa_template"]').value = id ? 1 : 0;
        }
        
        function loadTemplates(tipoPratica) {
            if (tipoPratica) {
                // In produzione, fare AJAX per caricare template
                // Per ora, submit form per ricaricare
                document.getElementById('wizardForm').submit();
            }
        }
        
        // Gestione task
        let taskIndex = <?= count($wizardData['tasks'] ?? []) ?>;
        
        function addTask() {
            const taskList = document.getElementById('taskList');
            const newTask = document.createElement('div');
            newTask.className = 'task-item';
            newTask.dataset.index = taskIndex;
            
            newTask.innerHTML = `
                <div class="task-header">
                    <span class="task-number">${taskIndex + 1}</span>
                    <input type="text" 
                           name="tasks[${taskIndex}][titolo]" 
                           class="form-control task-title-input"
                           placeholder="Titolo task...">
                    <input type="number" 
                           name="tasks[${taskIndex}][ore_stimate]" 
                           class="form-control task-ore"
                           placeholder="Ore"
                           min="0"
                           step="0.5">
                    <div class="task-actions">
                        <button type="button" class="btn-icon" onclick="removeTask(${taskIndex})">🗑️</button>
                    </div>
                </div>
                <textarea name="tasks[${taskIndex}][descrizione]" 
                          class="form-control form-textarea"
                          placeholder="Descrizione task..."
                          style="margin-top: 0.5rem;"></textarea>
            `;
            
            taskList.appendChild(newTask);
            taskIndex++;
            
            // Focus sul nuovo campo
            newTask.querySelector('input[type="text"]').focus();
        }
        
        function removeTask(index) {
            const task = document.querySelector(`[data-index="${index}"]`);
            if (task) {
                task.remove();
                // Rinumera task
                document.querySelectorAll('.task-item').forEach((item, idx) => {
                    item.querySelector('.task-number').textContent = idx + 1;
                });
            }
        }
        
        // Validazione step
        function checkStep1Validity() {
            const clienteId = document.getElementById('cliente_id').value;
            const btnNext = document.getElementById('btnNext');
            if (btnNext) {
                btnNext.disabled = !clienteId;
            }
        }
        
        // Init
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($currentStep === 1): ?>
            checkStep1Validity();
            <?php endif; ?>
        });
    </script>
</body>
</html>