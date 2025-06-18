<?php
/**
 * modules/operatori/create.php - Creazione Operatore CRM Re.De Consulting
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
$pageTitle = 'Nuovo Operatore';
$pageIcon = '➕';

// Verifica permessi admin (doppio controllo)
if (!$sessionInfo['is_admin']) {
    header('Location: /crm/?action=operatori&error=permissions');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Qualifiche predefinite
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

// **LOGICA ESISTENTE MANTENUTA** - Gestione form submission
$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recupera dati form
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
        
        // Permessi
        $isAmministratore = isset($_POST['is_amministratore']) ? 1 : 0;
        $isAttivo = isset($_POST['is_attivo']) ? 1 : 0;
        
        // **LOGICA ESISTENTE MANTENUTA** - Validazione
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
        
        // Verifica email unica
        if (empty($errors)) {
            $exists = $db->selectOne("SELECT id FROM operatori WHERE email = ?", [$email]);
            if ($exists) {
                $errors[] = "Email già registrata nel sistema";
            }
        }
        
        // **LOGICA ESISTENTE MANTENUTA** - Se nessun errore, procedi con l'inserimento
        if (empty($errors)) {
            // Genera codice operatore
            $lastOp = $db->selectOne("SELECT MAX(CAST(SUBSTRING(codice_operatore, 3) AS UNSIGNED)) as max_code FROM operatori WHERE codice_operatore LIKE 'OP%'");
            $nextCode = ($lastOp['max_code'] ?? 0) + 1;
            $codiceOperatore = 'OP' . str_pad($nextCode, 4, '0', STR_PAD_LEFT);
            
            // Password temporanea (il primo login forzerà il cambio)
            $tempPassword = bin2hex(random_bytes(6));
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // **LOGICA ESISTENTE MANTENUTA** - Inserimento nel database
            $operatoreId = $db->insert('operatori', [
                'codice_operatore' => $codiceOperatore,
                'cognome' => $cognome,
                'nome' => $nome,
                'email' => $email,
                'password_hash' => $passwordHash,
                'telefono' => $telefono,
                'qualifiche' => json_encode($qualifiche),
                'orario_mattino_inizio' => $orarioMattinoInizio,
                'orario_mattino_fine' => $orarioMattinoFine,
                'orario_pomeriggio_inizio' => $orarioPomeriggioInizio,
                'orario_pomeriggio_fine' => $orarioPomeriggioFine,
                'orario_continuato_inizio' => $orarioContinuatoInizio,
                'orario_continuato_fine' => $orarioContinuatoFine,
                'is_amministratore' => $isAmministratore,
                'is_attivo' => $isAttivo,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // **LOGICA ESISTENTE MANTENUTA** - Log creazione
            $db->insert('auth_log', [
                'user_id' => $sessionInfo['operatore_id'],
                'action' => 'create_operator',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'additional_data' => json_encode([
                    'operatore_creato_id' => $operatoreId,
                    'operatore_creato_nome' => "$cognome $nome"
                ])
            ]);
            
            $success = true;
            $successMessage = "Operatore creato con successo! Password temporanea: <strong>$tempPassword</strong>";
            
            // Redirect dopo successo
            $_SESSION['success_message'] = "Operatore $nome $cognome creato con successo!";
            header('Location: /crm/?action=operatori&success=created');
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Errore creazione operatore: " . $e->getMessage());
        $errors[] = "Errore durante la creazione dell'operatore. Riprova più tardi.";
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
        /* Form Container Compatto */
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
        
        .form-grid.single {
            grid-template-columns: 1fr;
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
                        <h1>➕ Crea Nuovo Operatore</h1>
                        <p>Inserisci i dati del nuovo operatore del sistema</p>
                    </div>
                    
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
                    
                    <?php if ($success && $successMessage): ?>
                        <div class="alert alert-success">
                            <?= $successMessage ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Form -->
                    <form method="POST" action="/crm/?action=operatori&view=create">
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
                                           value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        Nome <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                           name="nome" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        Email <span class="required">*</span>
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           required>
                                    <div class="form-hint">
                                        Sarà utilizzata per l'accesso al sistema
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Telefono</label>
                                    <input type="tel" 
                                           name="telefono" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                                           placeholder="+39 123 456 7890">
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
                                                   <?= in_array($qualifica, $_POST['qualifiche'] ?? []) ? 'checked' : '' ?>>
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
                                               value="<?= htmlspecialchars($_POST['orario_mattino_inizio'] ?? '08:30') ?>">
                                        <div class="time-input-group">
                                            <span class="time-separator">-</span>
                                            <input type="time" 
                                                   name="orario_mattino_fine" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($_POST['orario_mattino_fine'] ?? '12:30') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="form-label">Orario Pomeriggio</label>
                                    <div class="time-inputs-grid">
                                        <input type="time" 
                                               name="orario_pomeriggio_inizio" 
                                               class="form-control"
                                               value="<?= htmlspecialchars($_POST['orario_pomeriggio_inizio'] ?? '14:00') ?>">
                                        <div class="time-input-group">
                                            <span class="time-separator">-</span>
                                            <input type="time" 
                                                   name="orario_pomeriggio_fine" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($_POST['orario_pomeriggio_fine'] ?? '18:00') ?>">
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
                                           value="<?= htmlspecialchars($_POST['orario_continuato_inizio'] ?? '') ?>">
                                    <div class="time-input-group">
                                        <span class="time-separator">-</span>
                                        <input type="time" 
                                               name="orario_continuato_fine" 
                                               class="form-control"
                                               value="<?= htmlspecialchars($_POST['orario_continuato_fine'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Permessi e Stato -->
                        <div class="form-section">
                            <h2 class="section-title">🔐 Permessi e Stato</h2>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <label class="form-switch">
                                    <input type="checkbox" 
                                           name="is_amministratore" 
                                           value="1"
                                           <?= ($_POST['is_amministratore'] ?? false) ? 'checked' : '' ?>>
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
                                           <?= ($_POST['is_attivo'] ?? true) ? 'checked' : '' ?>
                                           checked>
                                    <div class="switch-label">
                                        <div class="switch-title">Account Attivo</div>
                                        <div class="switch-description">
                                            L'operatore può accedere al sistema
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                ✅ Crea Operatore
                            </button>
                            
                            <div class="form-actions-left">
                                <a href="/crm/?action=operatori" class="btn btn-secondary">
                                    ❌ Annulla
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>