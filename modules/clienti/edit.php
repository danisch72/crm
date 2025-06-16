<?php
/**
 * modules/operatori/edit.php - Modifica Operatore CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON ROUTER
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message
// $operatoreId (validato dal router)

$pageTitle = 'Modifica Operatore';

// Recupera dati operatore
$operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
if (!$operatore) {
    header('Location: /crm/?action=operatori&error=not_found');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Controllo permessi: admin o auto-edit
$canEdit = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
$isAdminEdit = $sessionInfo['is_admin'] && $sessionInfo['operatore_id'] != $operatoreId;
$isSelfEdit = $sessionInfo['operatore_id'] == $operatoreId;

if (!$canEdit) {
    header('Location: /crm/?action=operatori&error=permissions');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Qualifiche predefinite disponibili
$qualificheDisponibili = [
    'Contabilità Generale',
    'Bilanci',
    'Dichiarazioni IRPEF',
    'Dichiarazioni IRES',
    'Liquidazioni IVA',
    'F24 e Versamenti',
    'Consulenza Fiscale',
    'Consulenza del Lavoro',
    'Pratiche INPS',
    'Pratiche Camera di Commercio',
    'Contrattualistica',
    'Amministrazione Condominiali',
    'Gestione Clienti',
    'Formazione e Supporto'
];

// Decode qualifiche esistenti
$qualificheEsistenti = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];

// **LOGICA ESISTENTE MANTENUTA** - Gestione form submission
$errors = [];
$success = false;
$passwordChanged = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'update';
        
        if ($action === 'update') {
            // **LOGICA ESISTENTE MANTENUTA** - Aggiornamento dati generali
            $cognome = trim($_POST['cognome'] ?? '');
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $qualifiche = $_POST['qualifiche'] ?? [];
            $tipoContratto = $_POST['tipo_contratto'] ?? '';
            
            // Solo admin può modificare questi campi
            if ($isAdminEdit) {
                $isAmministratore = isset($_POST['is_amministratore']) ? 1 : 0;
                $isAttivo = isset($_POST['is_attivo']) ? 1 : 0;
            } else {
                $isAmministratore = $operatore['is_amministratore'];
                $isAttivo = $operatore['is_attivo'];
            }
            
            // Orari di lavoro
            $orarioMattinoInizio = $_POST['orario_mattino_inizio'] ?? null;
            $orarioMattinoFine = $_POST['orario_mattino_fine'] ?? null;
            $orarioPomeriggioInizio = $_POST['orario_pomeriggio_inizio'] ?? null;
            $orarioPomeriggioFine = $_POST['orario_pomeriggio_fine'] ?? null;
            $orarioContinuatoInizio = $_POST['orario_continuato_inizio'] ?? null;
            $orarioContinuatoFine = $_POST['orario_continuato_fine'] ?? null;
            
            // Validazioni
            if (empty($cognome)) {
                $errors[] = "Il cognome è obbligatorio";
            }
            
            if (empty($nome)) {
                $errors[] = "Il nome è obbligatorio";
            }
            
            if (empty($email)) {
                $errors[] = "L'email è obbligatoria";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "L'email non è valida";
            }
            
            // Verifica email duplicata (escludendo operatore corrente)
            if (empty($errors)) {
                $existingUser = $db->selectOne(
                    "SELECT id FROM operatori WHERE email = ? AND id != ?",
                    [$email, $operatoreId]
                );
                
                if ($existingUser) {
                    $errors[] = "Email già presente nel sistema";
                }
            }
            
            // Se non ci sono errori, procedi con l'aggiornamento
            if (empty($errors)) {
                $updateData = [
                    'cognome' => $cognome,
                    'nome' => $nome,
                    'email' => $email,
                    'qualifiche' => json_encode($qualifiche),
                    'tipo_contratto' => $tipoContratto,
                    'is_amministratore' => $isAmministratore,
                    'is_attivo' => $isAttivo,
                    'orario_mattino_inizio' => $orarioMattinoInizio,
                    'orario_mattino_fine' => $orarioMattinoFine,
                    'orario_pomeriggio_inizio' => $orarioPomeriggioInizio,
                    'orario_pomeriggio_fine' => $orarioPomeriggioFine,
                    'orario_continuato_inizio' => $orarioContinuatoInizio,
                    'orario_continuato_fine' => $orarioContinuatoFine,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $updated = $db->update('operatori', $updateData, 'id = ?', [$operatoreId]);
                
                if ($updated !== false) {
                    $success = true;
                    $_SESSION['success_message'] = "Operatore aggiornato con successo!";
                    header('Location: /crm/?action=operatori&view=view&id=' . $operatoreId);
                    exit;
                } else {
                    $errors[] = "Errore durante l'aggiornamento";
                }
            }
            
        } elseif ($action === 'change_password' && $_POST['new_password']) {
            // Cambio password
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (strlen($newPassword) < 8) {
                $errors[] = "La password deve essere di almeno 8 caratteri";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "Le password non coincidono";
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updated = $db->update(
                    'operatori', 
                    ['password_hash' => $passwordHash, 'updated_at' => date('Y-m-d H:i:s')], 
                    'id = ?', 
                    [$operatoreId]
                );
                
                if ($updated !== false) {
                    $passwordChanged = true;
                    $_SESSION['success_message'] = "Password aggiornata con successo!";
                } else {
                    $errors[] = "Errore durante l'aggiornamento della password";
                }
            }
        }
        
        // Ricarica dati operatore dopo modifiche
        if ($success || $passwordChanged) {
            $operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
            $qualificheEsistenti = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];
        }
        
    } catch (Exception $e) {
        error_log("Errore aggiornamento operatore: " . $e->getMessage());
        $errors[] = "Errore di sistema. Riprova più tardi.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De Consulting</title>
    
    <style>
        /* Design System Datev Koinos Compliant */
        :root {
            --primary-green: #00A86B;
            --secondary-green: #2E7D32;
            --accent-orange: #FF6B35;
            --danger-red: #DC3545;
            --warning-yellow: #FFC107;
            --gray-50: #F8F9FA;
            --gray-100: #E9ECEF;
            --gray-200: #DEE2E6;
            --gray-300: #CED4DA;
            --gray-400: #ADB5BD;
            --gray-500: #6C757D;
            --gray-600: #495057;
            --gray-700: #343A40;
            --gray-800: #212529;
            --font-base: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --transition-fast: all 0.15s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-base);
            font-size: 14px;
            color: var(--gray-800);
            background: #f5f5f5;
            line-height: 1.4;
        }
        
        /* Layout Container */
        .edit-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            padding: 0.5rem 0;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Header */
        .page-header {
            background: white;
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            color: var(--gray-800);
            margin: 0;
            font-weight: 600;
        }
        
        .page-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Bottoni */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            cursor: pointer;
            transition: var(--transition-fast);
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
        
        .btn-outline {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
        }
        
        .btn-danger {
            background: var(--danger-red);
            color: white;
        }
        
        .btn-danger:hover {
            background: #B91C1C;
        }
        
        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            position: relative;
            transition: var(--transition-fast);
        }
        
        .tab-button:hover {
            color: var(--gray-800);
            background: var(--gray-50);
        }
        
        .tab-button.active {
            color: var(--primary-green);
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-green);
        }
        
        .tab-content {
            padding: 1.5rem;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Form Sections */
        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .form-grid.three-columns {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .form-field.full-width {
            grid-column: 1 / -1;
        }
        
        /* Form Elements */
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-label.required::after {
            content: " *";
            color: var(--danger-red);
        }
        
        .form-input,
        .form-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            width: 100%;
            transition: var(--transition-fast);
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(0,168,107,0.1);
        }
        
        .form-input:disabled {
            background: var(--gray-100);
            cursor: not-allowed;
        }
        
        /* Checkbox Grid */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        /* Switches */
        .switch-field {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }
        
        .switch {
            position: relative;
            width: 48px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray-300);
            transition: .3s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-green);
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Messages */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        
        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }
        
        .alert-warning {
            background: #FFF3CD;
            color: #856404;
            border: 1px solid #FFEAA7;
        }
        
        /* Password Form */
        .password-form {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid.three-columns {
                grid-template-columns: 1fr;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span>Modifica: <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></span>
        </div>
        
        <!-- Header -->
        <header class="page-header">
            <div class="header-info">
                <h1 class="page-title">✏️ Modifica Operatore</h1>
                <div class="page-subtitle">
                    <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?> - 
                    <?= htmlspecialchars($operatore['email']) ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="/crm/?action=operatori&view=view&id=<?= $operatoreId ?>" class="btn btn-secondary">
                    👁️ Visualizza
                </a>
                <a href="/crm/?action=operatori" class="btn btn-outline">
                    ← Lista Operatori
                </a>
                <a href="/crm/?action=dashboard" class="btn btn-outline">
                    🏠 Dashboard
                </a>
            </div>
        </header>
        
        <!-- Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>⚠️ Si sono verificati i seguenti errori:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success || $passwordChanged): ?>
            <div class="alert alert-success">
                ✅ <?= $passwordChanged ? 'Password aggiornata con successo!' : 'Dati aggiornati con successo!' ?>
            </div>
        <?php endif; ?>
        
        <?php if ($isSelfEdit): ?>
            <div class="alert alert-warning">
                ⚠️ Stai modificando il tuo profilo. Alcuni campi potrebbero essere limitati.
            </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="switchTab('general')">
                    📋 Dati Generali
                </button>
                <button class="tab-button" onclick="switchTab('password')">
                    🔐 Cambia Password
                </button>
            </div>
            
            <!-- Tab Dati Generali -->
            <div id="general-tab" class="tab-content active">
                <form method="POST" action="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>">
                    <input type="hidden" name="action" value="update">
                    
                    <!-- Sezione 1: Dati Anagrafici -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <span>👤</span> Dati Anagrafici
                        </h2>
                        
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="cognome" class="form-label required">Cognome</label>
                                <input type="text" 
                                       id="cognome" 
                                       name="cognome" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($operatore['cognome']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-field">
                                <label for="nome" class="form-label required">Nome</label>
                                <input type="text" 
                                       id="nome" 
                                       name="nome" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($operatore['nome']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-field full-width">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($operatore['email']) ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sezione 2: Qualifiche e Competenze -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <span>🎯</span> Qualifiche e Competenze
                        </h2>
                        
                        <div class="checkbox-grid">
                            <?php foreach ($qualificheDisponibili as $qualifica): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="qual_<?= md5($qualifica) ?>" 
                                           name="qualifiche[]" 
                                           value="<?= htmlspecialchars($qualifica) ?>"
                                           <?= in_array($qualifica, $qualificheEsistenti) ? 'checked' : '' ?>>
                                    <label for="qual_<?= md5($qualifica) ?>">
                                        <?= htmlspecialchars($qualifica) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Sezione 3: Orari di Lavoro -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <span>🕐</span> Orari di Lavoro
                        </h2>
                        
                        <div class="form-grid three-columns">
                            <div class="form-field">
                                <label class="form-label">Tipo Contratto</label>
                                <select name="tipo_contratto" class="form-select">
                                    <option value="">Seleziona...</option>
                                    <option value="full_time" <?= $operatore['tipo_contratto'] === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                                    <option value="part_time" <?= $operatore['tipo_contratto'] === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                                    <option value="collaborazione" <?= $operatore['tipo_contratto'] === 'collaborazione' ? 'selected' : '' ?>>Collaborazione</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Mattino - Inizio</label>
                                <input type="time" 
                                       name="orario_mattino_inizio" 
                                       class="form-input"
                                       value="<?= htmlspecialchars($operatore['orario_mattino_inizio'] ?? '09:00') ?>">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Mattino - Fine</label>
                                <input type="time" 
                                       name="orario_mattino_fine" 
                                       class="form-input"
                                       value="<?= htmlspecialchars($operatore['orario_mattino_fine'] ?? '13:00') ?>">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Pomeriggio - Inizio</label>
                                <input type="time" 
                                       name="orario_pomeriggio_inizio" 
                                       class="form-input"
                                       value="<?= htmlspecialchars($operatore['orario_pomeriggio_inizio'] ?? '14:00') ?>">
                            </div>
                            
                            <div class="form-field">
                                <label class="form-label">Pomeriggio - Fine</label>
                                <input type="time" 
                                       name="orario_pomeriggio_fine" 
                                       class="form-input"
                                       value="<?= htmlspecialchars($operatore['orario_pomeriggio_fine'] ?? '18:00') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sezione 4: Permessi (solo per admin) -->
                    <?php if ($isAdminEdit): ?>
                    <div class="form-section">
                        <h2 class="section-title">
                            <span>🔐</span> Permessi e Stato
                        </h2>
                        
                        <div class="switch-field">
                            <label class="switch">
                                <input type="checkbox" 
                                       name="is_amministratore"
                                       <?= $operatore['is_amministratore'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <label>Amministratore (accesso completo al sistema)</label>
                        </div>
                        
                        <div class="switch-field">
                            <label class="switch">
                                <input type="checkbox" 
                                       name="is_attivo"
                                       <?= $operatore['is_attivo'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <label>Attivo (può accedere al sistema)</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <div>
                            <span class="form-label">* Campi obbligatori</span>
                        </div>
                        <div>
                            <a href="/crm/?action=operatori" class="btn btn-secondary">
                                ❌ Annulla
                            </a>
                            <button type="submit" class="btn btn-primary">
                                💾 Salva Modifiche
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tab Password -->
            <div id="password-tab" class="tab-content">
                <form method="POST" action="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-section">
                        <h2 class="section-title">
                            <span>🔑</span> Modifica Password
                        </h2>
                        
                        <div class="password-form">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="new_password" class="form-label required">Nuova Password</label>
                                    <input type="password" 
                                           id="new_password" 
                                           name="new_password" 
                                           class="form-input" 
                                           required
                                           minlength="8"
                                           placeholder="Minimo 8 caratteri">
                                </div>
                                
                                <div class="form-field">
                                    <label for="confirm_password" class="form-label required">Conferma Password</label>
                                    <input type="password" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           class="form-input" 
                                           required
                                           minlength="8"
                                           placeholder="Ripeti la password">
                                </div>
                            </div>
                            
                            <div style="margin-top: 1rem;">
                                <button type="submit" class="btn btn-primary">
                                    🔐 Cambia Password
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Gestione tabs
        function switchTab(tabName) {
            // Nascondi tutti i tab
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Rimuovi active da tutti i bottoni
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostra tab selezionato
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Attiva bottone corrispondente
            event.target.classList.add('active');
        }
    </script>
</body>
</html>