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

// Recupera ID operatore da modificare (già validato dal router)
$operatoreId = $_GET['id'];

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
            $telefono = trim($_POST['telefono'] ?? '');
            $qualifiche = $_POST['qualifiche'] ?? [];
            $tipoContratto = $_POST['tipo_contratto'] ?? '';
            
            // Orari
            $orarioMattinoInizio = $_POST['orario_mattino_inizio'] ?? null;
            $orarioMattinoFine = $_POST['orario_mattino_fine'] ?? null;
            $orarioPomeriggioInizio = $_POST['orario_pomeriggio_inizio'] ?? null;
            $orarioPomeriggioFine = $_POST['orario_pomeriggio_fine'] ?? null;
            $orarioContinuatoInizio = $_POST['orario_continuato_inizio'] ?? null;
            $orarioContinuatoFine = $_POST['orario_continuato_fine'] ?? null;
            
            // **LOGICA ESISTENTE MANTENUTA** - Solo admin può modificare questi campi
            if ($isAdminEdit) {
                $isAmministratore = isset($_POST['is_amministratore']) ? 1 : 0;
                $isAttivo = isset($_POST['is_attivo']) ? 1 : 0;
            } else {
                $isAmministratore = $operatore['is_amministratore'];
                $isAttivo = $operatore['is_attivo'];
            }
            
            // Validazione
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
            
            // Verifica email unica (escluso operatore corrente)
            if (empty($errors)) {
                $exists = $db->selectOne(
                    "SELECT id FROM operatori WHERE email = ? AND id != ?", 
                    [$email, $operatoreId]
                );
                if ($exists) {
                    $errors[] = "Email già utilizzata da un altro operatore";
                }
            }
            
            // Se nessun errore, procedi con l'aggiornamento
            if (empty($errors)) {
                $updateData = [
                    'cognome' => $cognome,
                    'nome' => $nome,
                    'email' => $email,
                    'telefono' => $telefono,
                    'qualifiche' => json_encode($qualifiche),
                    'tipo_contratto' => $tipoContratto,
                    'orario_mattino_inizio' => $orarioMattinoInizio,
                    'orario_mattino_fine' => $orarioMattinoFine,
                    'orario_pomeriggio_inizio' => $orarioPomeriggioInizio,
                    'orario_pomeriggio_fine' => $orarioPomeriggioFine,
                    'orario_continuato_inizio' => $orarioContinuatoInizio,
                    'orario_continuato_fine' => $orarioContinuatoFine,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Solo admin può modificare questi campi
                if ($isAdminEdit) {
                    $updateData['is_amministratore'] = $isAmministratore;
                    $updateData['is_attivo'] = $isAttivo;
                }
                
                $db->update('operatori', $updateData, 'id = ?', [$operatoreId]);
                
                // Log modifica
                $db->insert('auth_log', [
                    'user_id' => $sessionInfo['operatore_id'],
                    'action' => 'update_operator',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'additional_data' => json_encode([
                        'operatore_modificato_id' => $operatoreId,
                        'modifiche' => array_keys($updateData)
                    ])
                ]);
                
                $success = true;
                $_SESSION['success_message'] = "Dati operatore aggiornati con successo!";
                header('Location: /crm/?action=operatori&view=view&id=' . $operatoreId . '&success=updated');
                exit;
            }
            
        } elseif ($action === 'change_password' && $isSelfEdit) {
            // **LOGICA ESISTENTE MANTENUTA** - Cambio password (solo per sé stessi)
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $errors[] = "Tutti i campi password sono obbligatori";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "Le nuove password non coincidono";
            } elseif (strlen($newPassword) < 8) {
                $errors[] = "La password deve essere di almeno 8 caratteri";
            } elseif (!password_verify($currentPassword, $operatore['password_hash'])) {
                $errors[] = "Password attuale non corretta";
            }
            
            if (empty($errors)) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->update('operatori', [
                    'password_hash' => $newHash,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$operatoreId]);
                
                // Log cambio password
                $db->insert('auth_log', [
                    'user_id' => $operatoreId,
                    'action' => 'password_change',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $passwordChanged = true;
                $success = true;
            }
        }
        
    } catch (Exception $e) {
        error_log("Errore modifica operatore: " . $e->getMessage());
        $errors[] = "Errore durante la modifica. Riprova più tardi.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Operatore - CRM Re.De Consulting</title>
    
    <style>
        /* Stessi stili di create.php */
        :root {
            --primary-blue: #194F8B;
            --secondary-green: #97BC5B;
            --accent-orange: #FF7F41;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success-green: #22c55e;
            --warning-yellow: #f59e0b;
            --danger-red: #ef4444;
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --transition-fast: 150ms ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        /* Container principale */
        .edit-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-blue);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Header */
        .main-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .header-info {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 2rem;
            background: white;
            padding: 0 1rem;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .tab-button:hover {
            color: var(--gray-900);
        }
        
        .tab-button.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Form styles (riusa da create.php) */
        .form-container {
            background: white;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        /* Altri stili copiati da create.php... */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all var(--transition-fast);
            border: 1px solid transparent;
            cursor: pointer;
            background: white;
        }
        
        .btn-primary {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        
        .btn-primary:hover {
            background: #16406e;
        }
        
        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }
        
        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
        }
        
        /* Form Grid Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        /* Form Controls */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }
        
        .form-label .required {
            color: var(--danger-red);
            margin-left: 0.25rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(25, 79, 139, 0.1);
        }
        
        .form-control:disabled {
            background: var(--gray-100);
            color: var(--gray-500);
            cursor: not-allowed;
        }
        
        .form-hint {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* Checkboxes */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 0.5rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            background: var(--gray-50);
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
            user-select: none;
        }
        
        /* Switch Toggle */
        .switch-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .switch {
            position: relative;
            display: inline-block;
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
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-blue);
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        /* Disabled switch */
        .switch input:disabled + .slider {
            background-color: var(--gray-200);
            cursor: not-allowed;
        }
        
        /* Time inputs */
        .time-inputs {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 0.5rem;
            align-items: center;
        }
        
        .time-separator {
            color: var(--gray-500);
            font-weight: 500;
        }
        
        /* Error Messages */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Password form specific */
        .password-form {
            max-width: 500px;
        }
        
        /* Info badges */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border-radius: var(--radius-md);
            font-size: 0.8125rem;
        }
        
        .info-badge.admin {
            background: #fef3c7;
            color: #d97706;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                overflow-x: auto;
            }
            
            .main-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span>Modifica <?= htmlspecialchars($operatore['nome'] . ' ' . $operatore['cognome']) ?></span>
        </div>

        <!-- Header con info -->
        <header class="main-header">
            <div class="header-left">
                <h1 class="page-title">✏️ Modifica Operatore</h1>
                <div class="header-info">
                    <?php if ($isSelfEdit): ?>
                        <span class="info-badge">👤 Modifica profilo personale</span>
                    <?php elseif ($isAdminEdit): ?>
                        <span class="info-badge admin">👑 Modifica come amministratore</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="/crm/?action=operatori&view=view&id=<?= $operatoreId ?>" class="btn btn-secondary">
                    👁️ Visualizza
                </a>
                <a href="/crm/?action=operatori" class="btn btn-secondary">
                    ← Torna alla Lista
                </a>
                <a href="/crm/?action=dashboard" class="btn btn-outline">
                    🏠 Dashboard
                </a>
            </div>
        </header>

        <!-- Tabs per sezioni -->
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('general')">
                📋 Dati Generali
            </button>
            <?php if ($isSelfEdit): ?>
                <button class="tab-button" onclick="switchTab('password')">
                    🔐 Cambio Password
                </button>
            <?php endif; ?>
            <?php if ($isAdminEdit): ?>
                <button class="tab-button" onclick="switchTab('permissions')">
                    🔒 Permessi e Stato
                </button>
            <?php endif; ?>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Messaggi di errore/successo -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Errori nel form:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success && !$passwordChanged): ?>
                <div class="alert alert-success">
                    ✅ Dati operatore aggiornati con successo!
                </div>
            <?php endif; ?>
            
            <?php if ($passwordChanged): ?>
                <div class="alert alert-success">
                    🔐 Password modificata con successo!
                </div>
            <?php endif; ?>

            <!-- Tab Dati Generali -->
            <div id="general-tab" class="tab-content active">
                <form method="POST" action="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>">
                    <input type="hidden" name="action" value="update">
                    
                    <!-- Dati Anagrafici -->
                    <div class="form-section">
                        <h2 class="section-title">👤 Dati Anagrafici</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    Cognome <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       name="cognome" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($operatore['cognome']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Nome <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       name="nome" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($operatore['nome']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($operatore['email']) ?>" 
                                       required>
                                <div class="form-hint">
                                    Utilizzata per l'accesso al sistema
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Telefono</label>
                                <input type="tel" 
                                       name="telefono" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($operatore['telefono'] ?? '') ?>"
                                       placeholder="+39 123 456 7890">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Codice Operatore</label>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($operatore['codice_operatore']) ?>" 
                                       disabled>
                                <div class="form-hint">
                                    Non modificabile
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Data Registrazione</label>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?= date('d/m/Y H:i', strtotime($operatore['created_at'])) ?>" 
                                       disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Qualifiche e Competenze -->
                    <div class="form-section">
                        <h2 class="section-title">🎯 Qualifiche e Competenze</h2>
                        <div class="form-group">
                            <label class="form-label">Seleziona le qualifiche dell'operatore</label>
                            <div class="checkbox-group">
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
                    </div>

                    <!-- Informazioni Contrattuali -->
                    <div class="form-section">
                        <h2 class="section-title">📋 Informazioni Contrattuali</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Tipo Contratto</label>
                                <select name="tipo_contratto" class="form-control">
                                    <option value="">-- Seleziona --</option>
                                    <option value="indeterminato" <?= $operatore['tipo_contratto'] === 'indeterminato' ? 'selected' : '' ?>>
                                        Tempo Indeterminato
                                    </option>
                                    <option value="determinato" <?= $operatore['tipo_contratto'] === 'determinato' ? 'selected' : '' ?>>
                                        Tempo Determinato
                                    </option>
                                    <option value="partita_iva" <?= $operatore['tipo_contratto'] === 'partita_iva' ? 'selected' : '' ?>>
                                        Partita IVA
                                    </option>
                                    <option value="apprendistato" <?= $operatore['tipo_contratto'] === 'apprendistato' ? 'selected' : '' ?>>
                                        Apprendistato
                                    </option>
                                    <option value="stage" <?= $operatore['tipo_contratto'] === 'stage' ? 'selected' : '' ?>>
                                        Stage/Tirocinio
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Orari di Lavoro -->
                    <div class="form-section">
                        <h2 class="section-title">🕐 Orari di Lavoro</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Orario Mattino</label>
                                <div class="time-inputs">
                                    <input type="time" 
                                           name="orario_mattino_inizio" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['orario_mattino_inizio'] ?? '') ?>">
                                    <span class="time-separator">-</span>
                                    <input type="time" 
                                           name="orario_mattino_fine" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['orario_mattino_fine'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Orario Pomeriggio</label>
                                <div class="time-inputs">
                                    <input type="time" 
                                           name="orario_pomeriggio_inizio" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['orario_pomeriggio_inizio'] ?? '') ?>">
                                    <span class="time-separator">-</span>
                                    <input type="time" 
                                           name="orario_pomeriggio_fine" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['orario_pomeriggio_fine'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Orario Continuato (se applicabile)</label>
                                <div class="time-inputs" style="max-width: 300px;">
                                    <input type="time" 
                                           name="orario_continuato_inizio" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['orario_continuato_inizio'] ?? '') ?>">
                                    <span class="time-separator">-</span>
                                    <input type="time" 
                                           name="orario_continuato_fine" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['orario_continuato_fine'] ?? '') ?>">
                                </div>
                                <div class="form-hint">
                                    Compilare solo se l'operatore ha orario continuato invece di mattino/pomeriggio
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isAdminEdit): ?>
                    <!-- Permessi e Stato (solo admin) -->
                    <div class="form-section">
                        <h2 class="section-title">🔐 Permessi e Stato</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <div class="switch-group">
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="is_amministratore" 
                                               <?= $operatore['is_amministratore'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <label class="form-label" style="margin-bottom: 0;">
                                        Amministratore di Sistema
                                    </label>
                                </div>
                                <div class="form-hint">
                                    Gli amministratori possono gestire altri operatori e accedere a tutte le funzionalità
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="switch-group">
                                    <label class="switch">
                                        <input type="checkbox" 
                                               name="is_attivo" 
                                               <?= $operatore['is_attivo'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <label class="form-label" style="margin-bottom: 0;">
                                        Account Attivo
                                    </label>
                                </div>
                                <div class="form-hint">
                                    Disattivare per impedire l'accesso al sistema
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Azioni Form -->
                    <div class="form-actions">
                        <div>
                            <button type="submit" class="btn btn-primary">
                                💾 Salva Modifiche
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                🔄 Ripristina
                            </button>
                        </div>
                        <div>
                            <a href="/crm/?action=operatori&view=view&id=<?= $operatoreId ?>" class="btn btn-outline">
                                ❌ Annulla
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($isSelfEdit): ?>
            <!-- Tab Cambio Password -->
            <div id="password-tab" class="tab-content">
                <form method="POST" action="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>" class="password-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-section">
                        <h2 class="section-title">🔐 Cambio Password</h2>
                        
                        <?php if (!$isSelfEdit): ?>
                            <div class="alert alert-warning">
                                ⚠️ Solo l'utente può modificare la propria password
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label class="form-label">
                                    Password Attuale <span class="required">*</span>
                                </label>
                                <input type="password" 
                                       name="current_password" 
                                       class="form-control" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Nuova Password <span class="required">*</span>
                                </label>
                                <input type="password" 
                                       name="new_password" 
                                       class="form-control" 
                                       required
                                       minlength="8">
                                <div class="form-hint">
                                    Minimo 8 caratteri
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Conferma Nuova Password <span class="required">*</span>
                                </label>
                                <input type="password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       required
                                       minlength="8">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    🔒 Cambia Password
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Switch tabs
        function switchTab(tabName) {
            // Rimuovi active da tutti i tab
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Attiva il tab selezionato
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Validazione password match
        const newPassword = document.querySelector('input[name="new_password"]');
        const confirmPassword = document.querySelector('input[name="confirm_password"]');
        
        if (newPassword && confirmPassword) {
            confirmPassword.addEventListener('input', () => {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Le password non coincidono');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>