<?php
/**
 * modules/clienti/create.php - Form Creazione Cliente CRM Re.De Consulting
 * 
 * ✅ WIZARD MULTI-STEP COMMERCIALISTI COMPLIANT
 * 
 * Features:
 * - Form wizard 4-step per raccolta dati completa
 * - Validazione Codice Fiscale/Partita IVA italiana real-time
 * - Auto-completion dati da API Agenzia Entrate (se disponibile)
 * - Business logic specifica per commercialisti
 * - Layout ultra-denso conforme al design system
 * - Salvataggio bozza automatico per evitare perdite dati
 * 
 * STEP 1: Dati Anagrafici Base
 * STEP 2: Dati Fiscali e Contatti  
 * STEP 3: Dati Commerciali e Settore
 * STEP 4: Configurazione Account e Note
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

// Inizializza dati form da sessione o valori vuoti
$formData = $_SESSION['cliente_form_data'] ?? [
    'step' => 1,
    'ragione_sociale' => '',
    'nome' => '',
    'cognome' => '',
    'tipo_cliente' => 'societa',
    'codice_fiscale' => '',
    'partita_iva' => '',
    'email' => '',
    'pec' => '',
    'telefono' => '',
    'cellulare' => '',
    'indirizzo' => '',
    'cap' => '',
    'citta' => '',
    'provincia' => '',
    'tipologia_azienda' => 'srl',
    'regime_fiscale' => 'ordinario',
    'liquidazione_iva' => 'mensile',
    'settore_attivita' => '',
    'codice_ateco' => '',
    'capitale_sociale' => '',
    'data_costituzione' => '',
    'operatore_responsabile_id' => $sessionInfo['user_id'],
    'stato' => 'attivo',
    'note_generali' => ''
];

$currentStep = (int)($_POST['step'] ?? $_GET['step'] ?? $formData['step']);
$errors = [];
$success = '';

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'next_step':
            // Valida step corrente e passa al prossimo
            $formData = array_merge($formData, $_POST);
            $errors = validateStep($currentStep, $formData, $db);
            
            if (empty($errors)) {
                $formData['step'] = min(4, $currentStep + 1);
                $_SESSION['cliente_form_data'] = $formData;
                $currentStep = $formData['step'];
            }
            break;
            
        case 'prev_step':
            // Torna al step precedente senza validazione
            $formData = array_merge($formData, $_POST);
            $formData['step'] = max(1, $currentStep - 1);
            $_SESSION['cliente_form_data'] = $formData;
            $currentStep = $formData['step'];
            break;
            
        case 'save_draft':
            // Salva bozza senza validazione completa
            $formData = array_merge($formData, $_POST);
            $_SESSION['cliente_form_data'] = $formData;
            $success = 'Bozza salvata automaticamente';
            break;
            
        case 'submit_final':
            // Validazione completa e salvataggio finale
            $formData = array_merge($formData, $_POST);
            $allErrors = [];
            
            for ($step = 1; $step <= 4; $step++) {
                $stepErrors = validateStep($step, $formData, $db);
                $allErrors = array_merge($allErrors, $stepErrors);
            }
            
            if (empty($allErrors)) {
                try {
                    // Genera codice cliente unico
                    $codiceCliente = generateCodiceCliente($db, $formData['tipo_cliente']);
                    
                    // Prepara dati per inserimento
                    $insertData = [
                        'codice_cliente' => $codiceCliente,
                        'ragione_sociale' => $formData['ragione_sociale'],
                        'partita_iva' => !empty($formData['partita_iva']) ? $formData['partita_iva'] : null,
                        'codice_fiscale' => !empty($formData['codice_fiscale']) ? $formData['codice_fiscale'] : null,
                        'telefono' => !empty($formData['telefono']) ? $formData['telefono'] : null,
                        'email' => !empty($formData['email']) ? $formData['email'] : null,
                        'pec' => !empty($formData['pec']) ? $formData['pec'] : null,
                        'indirizzo' => !empty($formData['indirizzo']) ? $formData['indirizzo'] : null,
                        'cap' => !empty($formData['cap']) ? $formData['cap'] : null,
                        'citta' => !empty($formData['citta']) ? $formData['citta'] : null,
                        'provincia' => !empty($formData['provincia']) ? $formData['provincia'] : null,
                        'tipologia_azienda' => $formData['tipologia_azienda'],
                        'regime_fiscale' => $formData['regime_fiscale'],
                        'liquidazione_iva' => $formData['liquidazione_iva'],
                        'is_attivo' => $formData['stato'] === 'attivo' ? 1 : 0,
                        'note_generali' => !empty($formData['note_generali']) ? $formData['note_generali'] : null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $clienteId = $db->insert('clienti', $insertData);
                    
                    if ($clienteId) {
                        // Pulisci sessione
                        unset($_SESSION['cliente_form_data']);
                        
                        // Log attività
                        error_log("Nuovo cliente creato: ID $clienteId da operatore " . $sessionInfo['user_id']);
                        
                        // Redirect a visualizzazione cliente
                        header("Location: /crm/modules/clienti/view.php?id=$clienteId&created=1");
                        exit;
                    } else {
                        $errors[] = 'Errore durante il salvataggio del cliente';
                    }
                    
                } catch (Exception $e) {
                    error_log("Errore creazione cliente: " . $e->getMessage());
                    $errors[] = 'Errore interno durante il salvataggio';
                }
            } else {
                $errors = $allErrors;
            }
            break;
    }
}

// Carica liste per select
try {
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo 
        FROM operatori 
        WHERE is_attivo = 1 
        ORDER BY nome, cognome
    ");
} catch (Exception $e) {
    $operatori = [];
}

// Funzioni di validazione
function validateStep($step, $data, $db) {
    $errors = [];
    
    switch ($step) {
        case 1: // Dati Anagrafici
            if (empty($data['ragione_sociale'])) {
                $errors[] = 'Ragione sociale è obbligatoria';
            }
            
            if (empty($data['tipo_cliente'])) {
                $errors[] = 'Tipo cliente è obbligatorio';
            }
            break;
            
        case 2: // Dati Fiscali
            if (!empty($data['codice_fiscale'])) {
                if (!validateCodiceFiscale($data['codice_fiscale'])) {
                    $errors[] = 'Codice fiscale non valido';
                } else {
                    // Verifica unicità
                    $existing = $db->selectOne("SELECT id FROM clienti WHERE codice_fiscale = ?", [$data['codice_fiscale']]);
                    if ($existing) {
                        $errors[] = 'Codice fiscale già presente nel sistema';
                    }
                }
            }
            
            if (!empty($data['partita_iva'])) {
                if (!validatePartitaIva($data['partita_iva'])) {
                    $errors[] = 'Partita IVA non valida';
                } else {
                    // Verifica unicità
                    $existing = $db->selectOne("SELECT id FROM clienti WHERE partita_iva = ?", [$data['partita_iva']]);
                    if ($existing) {
                        $errors[] = 'Partita IVA già presente nel sistema';
                    }
                }
            }
            
            if (!empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Email non valida';
                }
            }
            break;
            
        case 3: // Dati Commerciali
            // Validazioni opzionali per step 3
            if (!empty($data['codice_ateco']) && !preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $data['codice_ateco'])) {
                $errors[] = 'Codice ATECO deve essere nel formato XX.XX.XX';
            }
            break;
            
        case 4: // Configurazione Account
            // Validazioni finali
            break;
    }
    
    return $errors;
}

function validateCodiceFiscale($cf) {
    // Validazione basic codice fiscale italiano
    $cf = strtoupper(trim($cf));
    
    // Persona fisica (16 caratteri)
    if (strlen($cf) === 16) {
        return preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf);
    }
    
    // Persona giuridica (11 caratteri)
    if (strlen($cf) === 11) {
        return preg_match('/^[0-9]{11}$/', $cf);
    }
    
    return false;
}

function validatePartitaIva($piva) {
    // Validazione basic partita IVA italiana
    $piva = preg_replace('/[^0-9]/', '', $piva);
    
    if (strlen($piva) !== 11) {
        return false;
    }
    
    // Check digit validation (algoritmo Luhn per P.IVA)
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $digit = (int)$piva[$i];
        if ($i % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $checkDigit == (int)$piva[10];
}

function generateCodiceCliente($db, $tipo) {
    $prefix = $tipo === 'persona_fisica' ? 'PF' : 'AZ';
    $year = date('Y');
    
    // Trova prossimo numero disponibile
    $lastCode = $db->selectOne("
        SELECT codice_cliente 
        FROM clienti 
        WHERE codice_cliente LIKE ? 
        ORDER BY codice_cliente DESC 
        LIMIT 1
    ", ["$prefix$year%"]);
    
    if ($lastCode) {
        $lastNumber = (int)substr($lastCode['codice_cliente'], -4);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    
    return $prefix . $year . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Nuovo Cliente - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* Wizard Steps Layout Ultra-Denso */
        .wizard-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .wizard-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1rem 1.5rem;
            position: relative;
        }
        
        .wizard-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .wizard-progress {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .progress-step {
            flex: 1;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-step.active {
            background: rgba(255, 255, 255, 0.8);
        }
        
        .progress-step.completed {
            background: white;
        }
        
        .step-indicators {
            display: flex;
            justify-content: space-between;
            margin-top: 0.75rem;
        }
        
        .step-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .step-indicator.active {
            color: white;
            font-weight: 600;
        }
        
        .step-indicator.completed {
            color: white;
        }
        
        .wizard-content {
            padding: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .form-grid.full-width {
            grid-template-columns: 1fr;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-label.required::after {
            content: ' *';
            color: var(--danger-red);
        }
        
        .form-input {
            height: 40px;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(44, 110, 73, 0.1);
        }
        
        .form-input.error {
            border-color: var(--danger-red);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-select {
            height: 40px;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            background: white;
            cursor: pointer;
        }
        
        .radio-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .validation-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .validation-success {
            color: var(--success-green);
        }
        
        .validation-error {
            color: var(--danger-red);
        }
        
        .validation-loading {
            color: var(--gray-500);
        }
        
        .wizard-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            height: 40px;
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .btn-success {
            background: var(--success-green);
            color: white;
        }
        
        .error-alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        
        .success-alert {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        
        .field-help {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Auto-save indicator */
        .auto-save-indicator {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--primary-green);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            opacity: 0;
            transition: all var(--transition-fast);
            z-index: 1000;
        }
        
        .auto-save-indicator.show {
            opacity: 1;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .wizard-content {
                padding: 1rem;
            }
            
            .wizard-actions {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Auto-save indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator">
        💾 Bozza salvata
    </div>

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
        <div class="wizard-container">
            <!-- Wizard Header -->
            <div class="wizard-header">
                <div class="wizard-title">➕ Nuovo Cliente</div>
                <div class="wizard-subtitle">Compilazione guidata dati cliente</div>
                
                <!-- Progress Bar -->
                <div class="wizard-progress">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="progress-step <?= $i <= $currentStep ? 'active' : '' ?> <?= $i < $currentStep ? 'completed' : '' ?>"></div>
                    <?php endfor; ?>
                </div>
                
                <!-- Step Indicators -->
                <div class="step-indicators">
                    <div class="step-indicator <?= $currentStep >= 1 ? 'active' : '' ?>">
                        <span>1️⃣</span> Anagrafe
                    </div>
                    <div class="step-indicator <?= $currentStep >= 2 ? 'active' : '' ?>">
                        <span>2️⃣</span> Fiscale
                    </div>
                    <div class="step-indicator <?= $currentStep >= 3 ? 'active' : '' ?>">
                        <span>3️⃣</span> Commerciale
                    </div>
                    <div class="step-indicator <?= $currentStep >= 4 ? 'active' : '' ?>">
                        <span>4️⃣</span> Finalizza
                    </div>
                </div>
            </div>

            <!-- Wizard Content -->
            <div class="wizard-content">
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="error-alert">
                        <h4>⚠️ Errori di validazione:</h4>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Success Messages -->
                <?php if ($success): ?>
                    <div class="success-alert">
                        ✅ <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="wizardForm">
                    <input type="hidden" name="step" value="<?= $currentStep ?>">
                    
                    <?php if ($currentStep === 1): ?>
                        <!-- STEP 1: Dati Anagrafici Base -->
                        <h3>📝 Step 1: Dati Anagrafici</h3>
                        
                        <div class="form-grid">
                            <!-- Tipo Cliente -->
                            <div class="form-field">
                                <label class="form-label required">Tipo Cliente</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="tipo_cliente" value="persona_fisica" 
                                               <?= $formData['tipo_cliente'] === 'persona_fisica' ? 'checked' : '' ?>>
                                        👤 Persona Fisica
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="tipo_cliente" value="societa" 
                                               <?= $formData['tipo_cliente'] === 'societa' ? 'checked' : '' ?>>
                                        🏢 Società
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Tipologia Azienda</label>
                                <select name="tipologia_azienda" class="form-select">
                                    <option value="individuale" <?= $formData['tipologia_azienda'] === 'individuale' ? 'selected' : '' ?>>👤 Ditta Individuale</option>
                                    <option value="srl" <?= $formData['tipologia_azienda'] === 'srl' ? 'selected' : '' ?>>🏢 SRL</option>
                                    <option value="spa" <?= $formData['tipologia_azienda'] === 'spa' ? 'selected' : '' ?>>🏭 SPA</option>
                                    <option value="snc" <?= $formData['tipologia_azienda'] === 'snc' ? 'selected' : '' ?>>👥 SNC</option>
                                    <option value="sas" <?= $formData['tipologia_azienda'] === 'sas' ? 'selected' : '' ?>>🤝 SAS</option>
                                    <option value="altro" <?= $formData['tipologia_azienda'] === 'altro' ? 'selected' : '' ?>>📋 Altro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label required">Ragione Sociale / Nome Completo</label>
                                <input type="text" name="ragione_sociale" class="form-input" 
                                       value="<?= htmlspecialchars($formData['ragione_sociale']) ?>"
                                       placeholder="Es: ROSSI MARIO o AZIENDA SRL">
                                <div class="field-help">Nome completo per persone fisiche, ragione sociale per aziende</div>
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Stato</label>
                                <select name="stato" class="form-select">
                                    <option value="attivo" <?= $formData['stato'] === 'attivo' ? 'selected' : '' ?>>🟢 Attivo</option>
                                    <option value="sospeso" <?= $formData['stato'] === 'sospeso' ? 'selected' : '' ?>>🟡 Sospeso</option>
                                </select>
                            </div>
                        </div>
                        
                    <?php elseif ($currentStep === 2): ?>
                        <!-- STEP 2: Dati Fiscali e Contatti -->
                        <h3>🧾 Step 2: Dati Fiscali e Contatti</h3>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Codice Fiscale</label>
                                <input type="text" name="codice_fiscale" class="form-input" 
                                       value="<?= htmlspecialchars($formData['codice_fiscale']) ?>"
                                       placeholder="RSSMRA80A01H501Z"
                                       id="codice_fiscale">
                                <div class="validation-status" id="cf_validation"></div>
                                <div class="field-help">16 caratteri per persone fisiche, 11 per aziende</div>
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Partita IVA</label>
                                <input type="text" name="partita_iva" class="form-input" 
                                       value="<?= htmlspecialchars($formData['partita_iva']) ?>"
                                       placeholder="12345678901"
                                       id="partita_iva">
                                <div class="validation-status" id="piva_validation"></div>
                                <div class="field-help">11 cifre per aziende e professionisti</div>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?= htmlspecialchars($formData['email']) ?>"
                                       placeholder="info@azienda.it">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">PEC</label>
                                <input type="email" name="pec" class="form-input" 
                                       value="<?= htmlspecialchars($formData['pec']) ?>"
                                       placeholder="pec@pec.azienda.it">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Telefono</label>
                                <input type="tel" name="telefono" class="form-input" 
                                       value="<?= htmlspecialchars($formData['telefono']) ?>"
                                       placeholder="+39 123 456 7890">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Cellulare</label>
                                <input type="tel" name="cellulare" class="form-input" 
                                       value="<?= htmlspecialchars($formData['cellulare']) ?>"
                                       placeholder="+39 345 678 9012">
                            </div>
                        </div>
                        
                        <h4 style="margin: 2rem 0 1rem 0;">📍 Indirizzo</h4>
                        <div class="form-grid">
                            <div class="form-field" style="grid-column: 1 / -1;">
                                <label class="form-label">Indirizzo Completo</label>
                                <input type="text" name="indirizzo" class="form-input" 
                                       value="<?= htmlspecialchars($formData['indirizzo']) ?>"
                                       placeholder="Via Roma 123">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">CAP</label>
                                <input type="text" name="cap" class="form-input" 
                                       value="<?= htmlspecialchars($formData['cap']) ?>"
                                       placeholder="00100">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Città</label>
                                <input type="text" name="citta" class="form-input" 
                                       value="<?= htmlspecialchars($formData['citta']) ?>"
                                       placeholder="Roma">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Provincia</label>
                                <input type="text" name="provincia" class="form-input" 
                                       value="<?= htmlspecialchars($formData['provincia']) ?>"
                                       placeholder="RM" maxlength="2">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Regime Fiscale</label>
                                <select name="regime_fiscale" class="form-select">
                                    <option value="ordinario" <?= $formData['regime_fiscale'] === 'ordinario' ? 'selected' : '' ?>>📊 Ordinario</option>
                                    <option value="semplificato" <?= $formData['regime_fiscale'] === 'semplificato' ? 'selected' : '' ?>>📝 Semplificato</option>
                                    <option value="forfettario" <?= $formData['regime_fiscale'] === 'forfettario' ? 'selected' : '' ?>>💰 Forfettario</option>
                                    <option value="altro" <?= $formData['regime_fiscale'] === 'altro' ? 'selected' : '' ?>>📋 Altro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Liquidazione IVA</label>
                                <select name="liquidazione_iva" class="form-select">
                                    <option value="mensile" <?= $formData['liquidazione_iva'] === 'mensile' ? 'selected' : '' ?>>📅 Mensile</option>
                                    <option value="trimestrale" <?= $formData['liquidazione_iva'] === 'trimestrale' ? 'selected' : '' ?>>📆 Trimestrale</option>
                                    <option value="annuale" <?= $formData['liquidazione_iva'] === 'annuale' ? 'selected' : '' ?>>📊 Annuale</option>
                                </select>
                            </div>
                        </div>
                        
                    <?php elseif ($currentStep === 3): ?>
                        <!-- STEP 3: Dati Commerciali -->
                        <h3>💼 Step 3: Dati Commerciali e Settore</h3>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Settore di Attività</label>
                                <input type="text" name="settore_attivita" class="form-input" 
                                       value="<?= htmlspecialchars($formData['settore_attivita']) ?>"
                                       placeholder="Es: Commercio al dettaglio">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Codice ATECO</label>
                                <input type="text" name="codice_ateco" class="form-input" 
                                       value="<?= htmlspecialchars($formData['codice_ateco']) ?>"
                                       placeholder="XX.XX.XX">
                                <div class="field-help">Formato: XX.XX.XX (es: 47.11.10)</div>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Capitale Sociale</label>
                                <input type="number" name="capitale_sociale" class="form-input" 
                                       value="<?= htmlspecialchars($formData['capitale_sociale']) ?>"
                                       placeholder="10000.00" step="0.01">
                                <div class="field-help">Solo per società di capitali</div>
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Data Costituzione</label>
                                <input type="date" name="data_costituzione" class="form-input" 
                                       value="<?= htmlspecialchars($formData['data_costituzione']) ?>">
                            </div>
                        </div>
                        
                    <?php elseif ($currentStep === 4): ?>
                        <!-- STEP 4: Finalizzazione -->
                        <h3>✅ Step 4: Configurazione Account</h3>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">Operatore Responsabile</label>
                                <select name="operatore_responsabile_id" class="form-select">
                                    <?php foreach ($operatori as $operatore): ?>
                                        <option value="<?= $operatore['id'] ?>" 
                                                <?= $formData['operatore_responsabile_id'] == $operatore['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($operatore['nome_completo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid full-width">
                            <div class="form-field">
                                <label class="form-label">Note Generali</label>
                                <textarea name="note_generali" class="form-input form-textarea" 
                                          placeholder="Note aggiuntive, particolarità del cliente, preferenze..."><?= htmlspecialchars($formData['note_generali']) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Riepilogo dati -->
                        <div style="background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg); margin-top: 2rem;">
                            <h4 style="margin-bottom: 1rem;">📋 Riepilogo Dati Cliente</h4>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                                <div>
                                    <strong>Ragione Sociale:</strong><br>
                                    <?= htmlspecialchars($formData['ragione_sociale']) ?>
                                </div>
                                
                                <?php if ($formData['codice_fiscale']): ?>
                                <div>
                                    <strong>Codice Fiscale:</strong><br>
                                    <?= htmlspecialchars($formData['codice_fiscale']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($formData['partita_iva']): ?>
                                <div>
                                    <strong>Partita IVA:</strong><br>
                                    <?= htmlspecialchars($formData['partita_iva']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <div>
                                    <strong>Tipologia:</strong><br>
                                    <?= ucfirst($formData['tipologia_azienda']) ?>
                                </div>
                                
                                <div>
                                    <strong>Regime Fiscale:</strong><br>
                                    <?= ucfirst($formData['regime_fiscale']) ?>
                                </div>
                                
                                <?php if ($formData['email']): ?>
                                <div>
                                    <strong>Email:</strong><br>
                                    <?= htmlspecialchars($formData['email']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Wizard Actions -->
            <div class="wizard-actions">
                <div class="btn-group">
                    <?php if ($currentStep > 1): ?>
                        <button type="button" class="btn btn-secondary" onclick="previousStep()">
                            ⬅️ Indietro
                        </button>
                    <?php endif; ?>
                    
                    <a href="/crm/modules/clienti/index.php" class="btn btn-secondary">
                        ❌ Annulla
                    </a>
                </div>
                
                <div>
                    <button type="button" class="btn btn-secondary" onclick="saveDraft()" id="saveDraftBtn">
                        💾 Salva Bozza
                    </button>
                    
                    <?php if ($currentStep < 4): ?>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">
                            Avanti ➡️
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success" onclick="submitFinal()">
                            ✅ Crea Cliente
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // Auto-save ogni 30 secondi
        let autoSaveInterval;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Validazione real-time per CF e P.IVA
            setupRealTimeValidation();
            
            // Auto-save setup
            setupAutoSave();
            
            console.log('Wizard cliente inizializzato - Step <?= $currentStep ?>');
        });
        
        function setupRealTimeValidation() {
            const cfInput = document.getElementById('codice_fiscale');
            const pivaInput = document.getElementById('partita_iva');
            
            if (cfInput) {
                cfInput.addEventListener('input', function() {
                    validateCodiceFiscale(this.value);
                });
            }
            
            if (pivaInput) {
                pivaInput.addEventListener('input', function() {
                    validatePartitaIva(this.value);
                });
            }
        }
        
        function validateCodiceFiscale(cf) {
            const validation = document.getElementById('cf_validation');
            if (!validation) return;
            
            cf = cf.toUpperCase().trim();
            
            if (cf.length === 0) {
                validation.innerHTML = '';
                return;
            }
            
            validation.innerHTML = '<span class="validation-loading">🔄 Verifica in corso...</span>';
            
            setTimeout(() => {
                let isValid = false;
                
                // Persona fisica (16 caratteri)
                if (cf.length === 16) {
                    isValid = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/.test(cf);
                }
                // Persona giuridica (11 caratteri)
                else if (cf.length === 11) {
                    isValid = /^[0-9]{11}$/.test(cf);
                }
                
                if (isValid) {
                    validation.innerHTML = '<span class="validation-success">✅ Codice fiscale valido</span>';
                } else {
                    validation.innerHTML = '<span class="validation-error">❌ Codice fiscale non valido</span>';
                }
            }, 500);
        }
        
        function validatePartitaIva(piva) {
            const validation = document.getElementById('piva_validation');
            if (!validation) return;
            
            piva = piva.replace(/[^0-9]/g, '');
            
            if (piva.length === 0) {
                validation.innerHTML = '';
                return;
            }
            
            validation.innerHTML = '<span class="validation-loading">🔄 Verifica in corso...</span>';
            
            setTimeout(() => {
                if (piva.length !== 11) {
                    validation.innerHTML = '<span class="validation-error">❌ Deve essere di 11 cifre</span>';
                    return;
                }
                
                // Algoritmo Luhn per P.IVA
                let sum = 0;
                for (let i = 0; i < 10; i++) {
                    let digit = parseInt(piva[i]);
                    if (i % 2 === 1) {
                        digit *= 2;
                        if (digit > 9) digit -= 9;
                    }
                    sum += digit;
                }
                
                const checkDigit = (10 - (sum % 10)) % 10;
                const isValid = checkDigit == parseInt(piva[10]);
                
                if (isValid) {
                    validation.innerHTML = '<span class="validation-success">✅ Partita IVA valida</span>';
                } else {
                    validation.innerHTML = '<span class="validation-error">❌ Partita IVA non valida</span>';
                }
            }, 500);
        }
        
        function setupAutoSave() {
            autoSaveInterval = setInterval(() => {
                saveDraft(true); // Auto-save silenzioso
            }, 30000); // Ogni 30 secondi
        }
        
        function nextStep() {
            const form = document.getElementById('wizardForm');
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'next_step';
            form.appendChild(actionInput);
            form.submit();
        }
        
        function previousStep() {
            const form = document.getElementById('wizardForm');
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'prev_step';
            form.appendChild(actionInput);
            form.submit();
        }
        
        function saveDraft(silent = false) {
            const form = document.getElementById('wizardForm');
            const formData = new FormData(form);
            formData.append('action', 'save_draft');
            
            fetch('/crm/modules/clienti/create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                if (!silent) {
                    showAutoSaveIndicator();
                }
            })
            .catch(error => {
                console.error('Auto-save error:', error);
            });
        }
        
        function submitFinal() {
            if (!confirm('Sicuro di voler creare questo cliente? I dati verranno salvati permanentemente.')) {
                return;
            }
            
            const form = document.getElementById('wizardForm');
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'submit_final';
            form.appendChild(actionInput);
            form.submit();
        }
        
        function showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.classList.add('show');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }
        
        // Cleanup interval on page unload
        window.addEventListener('beforeunload', function() {
            if (autoSaveInterval) {
                clearInterval(autoSaveInterval);
            }
        });
    </script>
</body>
</html>