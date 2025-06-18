<?php
/**
 * modules/operatori/edit.php - Modifica Operatore CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON COMPONENTI CENTRALIZZATI
 * ✅ SIDEBAR E HEADER INCLUSI COME DA ARCHITETTURA
 * ✅ DESIGN DATEV PROFESSIONAL ULTRA-COMPRESSO
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Variabili per i componenti (OBBLIGATORIE)
$pageTitle = 'Modifica Operatore';
$pageIcon = '✏️';

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
                        'operatore_modificato_nome' => "$cognome $nome",
                        'self_edit' => $isSelfEdit
                    ])
                ]);
                
                $success = true;
                
                // Ricarica dati aggiornati
                $operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
                $qualificheEsistenti = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];
            }
            
        } elseif ($action === 'change_password' && $isSelfEdit) {
            // **LOGICA ESISTENTE MANTENUTA** - Cambio password (solo per auto-edit)
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword)) {
                $errors[] = "La password attuale è obbligatoria";
            }
            
            if (empty($newPassword)) {
                $errors[] = "La nuova password è obbligatoria";
            } elseif (strlen($newPassword) < 8) {
                $errors[] = "La nuova password deve essere di almeno 8 caratteri";
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = "Le password non coincidono";
            }
            
            // Verifica password attuale
            if (empty($errors)) {
                if (!password_verify($currentPassword, $operatore['password_hash'])) {
                    $errors[] = "La password attuale non è corretta";
                }
            }
            
            // Se tutto ok, aggiorna password
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
            }
        }
        
    } catch (Exception $e) {
        error_log("Errore modifica operatore: " . $e->getMessage());
        $errors[] = "Errore durante l'aggiornamento. Riprova più tardi.";
    }
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
    <link rel="stylesheet" href="/crm/assets/css/datev-professional.css">
    <link rel="stylesheet" href="/crm/assets/css/operatori.css">
    
    <style>
        /* Form Container Compatto - Stesso stile di create.php */
        .form-container {
            max-width: 800px;
            margin: 1rem auto;
            padding: 1.5rem;
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .form-header {
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-header h1 {
            font-size: 1.5rem;
            color: var(--gray-900);
            margin: 0;
        }
        
        .form-header p {
            color: var(--gray-600);
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
        }
        
        /* Tab Navigation */
        .tab-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .tab-link {
            padding: 0.75rem 1rem;
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }
        
        .tab-link:hover {
            color: var(--gray-800);
        }
        
        .tab-link.active {
            color: var(--primary-green);
            border-bottom-color: var(--primary-green);
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Form Sections */
        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0 0 1rem 0;
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
        
        /* Form Groups */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
        }
        
        .form-label .required {
            color: var(--color-danger);
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            background: white;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-control:disabled {
            background: var(--gray-100);
            color: var(--gray-500);
            cursor: not-allowed;
        }
        
        .form-hint {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Checkbox Group */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            transition: background 0.2s ease;
        }
        
        .checkbox-item:hover {
            background: var(--gray-100);
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 0.5rem;
        }
        
        .checkbox-item label {
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
            flex: 1;
        }
        
        /* Time Inputs Grid */
        .time-inputs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            align-items: end;
        }
        
        .time-input-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .time-separator {
            color: var(--gray-400);
            font-weight: 500;
        }
        
        /* Switch Toggle */
        .form-switch {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
        }
        
        .form-switch.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-switch input[type="checkbox"] {
            width: 2.5rem;
            height: 1.25rem;
            position: relative;
            appearance: none;
            background: var(--gray-300);
            border-radius: 9999px;
            transition: background 0.2s ease;
            cursor: pointer;
        }
        
        .form-switch input[type="checkbox"]:checked {
            background: var(--primary-green);
        }
        
        .form-switch input[type="checkbox"]:disabled {
            cursor: not-allowed;
        }
        
        .form-switch input[type="checkbox"]::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 1rem;
            height: 1rem;
            background: white;
            border-radius: 50%;
            transition: transform 0.2s ease;
        }
        
        .form-switch input[type="checkbox"]:checked::after {
            transform: translateX(1.25rem);
        }
        
        .switch-label {
            flex: 1;
        }
        
        .switch-title {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.875rem;
        }
        
        .switch-description {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.125rem;
        }
        
        /* Password Section */
        .password-section {
            background: var(--gray-50);
            border-radius: var(--border-radius-md);
            padding: 1.5rem;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .form-actions-left {
            display: flex;
            gap: 0.75rem;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-error {
            background: var(--color-danger-light);
            color: var(--color-danger);
            border: 1px solid var(--color-danger);
        }
        
        .alert-success {
            background: var(--color-success-light);
            color: var(--color-success);
            border: 1px solid var(--color-success);
        }
        
        /* Info Badge */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            color: var(--gray-700);
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .form-actions-left {
                order: 2;
                justify-content: center;
            }
            
            .tab-nav {
                flex-wrap: wrap;
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
                <div class="form-container">
                    <div class="form-header">
                        <h1>✏️ Modifica Operatore</h1>
                        <p>
                            <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                            <span class="info-badge">
                                <?= $operatore['codice_operatore'] ?>
                            </span>
                        </p>
                    </div>
                    
                    <!-- Messaggi -->
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
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            ✅ Dati aggiornati con successo!
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($passwordChanged): ?>
                        <div class="alert alert-success">
                            ✅ Password cambiata con successo!
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tab Navigation -->
                    <div class="tab-nav">
                        <a href="#dati-generali" class="tab-link active" onclick="switchTab(event, 'dati-generali')">
                            📋 Dati Generali
                        </a>
                        <?php if ($isSelfEdit): ?>
                        <a href="#sicurezza" class="tab-link" onclick="switchTab(event, 'sicurezza')">
                            🔐 Sicurezza
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Content: Dati Generali -->
                    <div id="dati-generali" class="tab-content active">
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
                            
                            <!-- Contratto e Orari -->
                                
                                <div class="form-grid">
                                    <div>
                                        <label class="form-label">Orario Mattino</label>
                                        <div class="time-inputs-grid">
                                            <input type="time" 
                                                   name="orario_mattino_inizio" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_mattino_inizio'] ?? '') ?>">
                                            <div class="time-input-group">
                                                <span class="time-separator">-</span>
                                                <input type="time" 
                                                       name="orario_mattino_fine" 
                                                       class="form-control"
                                                       value="<?= htmlspecialchars($operatore['orario_mattino_fine'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="form-label">Orario Pomeriggio</label>
                                        <div class="time-inputs-grid">
                                            <input type="time" 
                                                   name="orario_pomeriggio_inizio" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_pomeriggio_inizio'] ?? '') ?>">
                                            <div class="time-input-group">
                                                <span class="time-separator">-</span>
                                                <input type="time" 
                                                       name="orario_pomeriggio_fine" 
                                                       class="form-control"
                                                       value="<?= htmlspecialchars($operatore['orario_pomeriggio_fine'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label class="form-label">Orario Continuato (alternativo)</label>
                                    <div class="time-inputs-grid" style="max-width: 300px;">
                                        <input type="time" 
                                               name="orario_continuato_inizio" 
                                               class="form-control"
                                               value="<?= htmlspecialchars($operatore['orario_continuato_inizio'] ?? '') ?>">
                                        <div class="time-input-group">
                                            <span class="time-separator">-</span>
                                            <input type="time" 
                                                   name="orario_continuato_fine" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_continuato_fine'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($isAdminEdit): ?>
                            <!-- Permessi e Stato (solo admin) -->
                            <div class="form-section">
                                <h2 class="section-title">🔐 Permessi e Stato</h2>
                                
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <label class="form-switch">
                                        <input type="checkbox" 
                                               name="is_amministratore" 
                                               value="1"
                                               <?= $operatore['is_amministratore'] ? 'checked' : '' ?>>
                                        <div class="switch-label">
                                            <div class="switch-title">Amministratore</div>
                                            <div class="switch-description">
                                                Accesso completo a tutte le funzionalità del sistema
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <label class="form-switch">
                                        <input type="checkbox" 
                                               name="is_attivo" 
                                               value="1"
                                               <?= $operatore['is_attivo'] ? 'checked' : '' ?>>
                                        <div class="switch-label">
                                            <div class="switch-title">Account Attivo</div>
                                            <div class="switch-description">
                                                L'operatore può accedere al sistema
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Mostra stato ma non modificabile -->
                            <div class="form-section">
                                <h2 class="section-title">🔐 Stato Account</h2>
                                <div style="display: flex; gap: 1rem;">
                                    <?php if ($operatore['is_amministratore']): ?>
                                        <span class="info-badge">👑 Amministratore</span>
                                    <?php endif; ?>
                                    <span class="info-badge">
                                        <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Actions -->
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    ✅ Salva Modifiche
                                </button>
                                
                                <div class="form-actions-left">
                                    <a href="/crm/?action=operatori&view=view&id=<?= $operatoreId ?>" class="btn btn-secondary">
                                        👁️ Visualizza
                                    </a>
                                    <a href="/crm/?action=operatori" class="btn btn-secondary">
                                        ← Torna alla Lista
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <?php if ($isSelfEdit): ?>
                    <!-- Tab Content: Sicurezza -->
                    <div id="sicurezza" class="tab-content">
                        <form method="POST" action="/crm/?action=operatori&view=edit&id=<?= $operatoreId ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="password-section">
                                <h2 class="section-title">🔑 Cambio Password</h2>
                                
                                <div class="form-grid" style="max-width: 500px;">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label class="form-label">
                                            Password Attuale <span class="required">*</span>
                                        </label>
                                        <input type="password" 
                                               name="current_password" 
                                               class="form-control" 
                                               required>
                                    </div>
                                    
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label class="form-label">
                                            Nuova Password <span class="required">*</span>
                                        </label>
                                        <input type="password" 
                                               name="new_password" 
                                               class="form-control" 
                                               required>
                                        <div class="form-hint">
                                            Minimo 8 caratteri
                                        </div>
                                    </div>
                                    
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label class="form-label">
                                            Conferma Nuova Password <span class="required">*</span>
                                        </label>
                                        <input type="password" 
                                               name="confirm_password" 
                                               class="form-control" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="form-actions" style="margin-top: 1.5rem; padding-top: 1.5rem;">
                                    <button type="submit" class="btn btn-primary">
                                        🔐 Cambia Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function switchTab(event, tabId) {
            event.preventDefault();
            
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Add active class to clicked tab and corresponding content
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
    </script>
</body>
</html>