<?php
/**
 * modules/operatori/create.php - Creazione Operatore CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON ROUTER
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Verifica permessi admin (doppio controllo)
if (!$sessionInfo['is_admin']) {
    header('Location: /crm/?action=operatori&error=permissions');
    exit;
}

// $db e $sessionInfo sono già disponibili dal router

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

// **LOGICA ESISTENTE MANTENUTA** - Gestione form submission
$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // **LOGICA ESISTENTE MANTENUTA** - Sanitizzazione e validazione input
        $cognome = trim($_POST['cognome'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $qualifiche = $_POST['qualifiche'] ?? [];
        $tipoContratto = $_POST['tipo_contratto'] ?? '';
        $isAmministratore = isset($_POST['is_amministratore']) ? 1 : 0;
        $isAttivo = isset($_POST['is_attivo']) ? 1 : 0;
        
        // Orari di lavoro
        $orarioMattinoInizio = $_POST['orario_mattino_inizio'] ?? null;
        $orarioMattinoFine = $_POST['orario_mattino_fine'] ?? null;
        $orarioPomeriggioInizio = $_POST['orario_pomeriggio_inizio'] ?? null;
        $orarioPomeriggioFine = $_POST['orario_pomeriggio_fine'] ?? null;
        $orarioContinuatoInizio = $_POST['orario_continuato_inizio'] ?? null;
        $orarioContinuatoFine = $_POST['orario_continuato_fine'] ?? null;
        
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
        
        // **LOGICA ESISTENTE MANTENUTA** - Verifica email unica
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
                'qualifiche' => json_encode($qualifiche),
                'tipo_contratto' => $tipoContratto,
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
    <title>Crea Nuovo Operatore - CRM Re.De Consulting</title>
    
    <style>
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
        .create-container {
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
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Bottoni */
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
        
        /* Form Container */
        .form-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Full width on mobile */
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
            }
            
            .form-actions > div {
                width: 100%;
                display: flex;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="create-container">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=operatori">Operatori</a> / 
            <span>Nuovo Operatore</span>
        </div>

        <!-- Header con azioni -->
        <header class="main-header">
            <div class="header-left">
                <h1 class="page-title">➕ Crea Nuovo Operatore</h1>
            </div>
            <div class="header-actions">
                <a href="/crm/?action=operatori" class="btn btn-secondary">
                    ← Torna alla Lista
                </a>
                <a href="/crm/?action=dashboard" class="btn btn-outline">
                    🏠 Dashboard
                </a>
            </div>
        </header>

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
            
            <?php if ($success && $successMessage): ?>
                <div class="alert alert-success">
                    <?= $successMessage ?>
                </div>
            <?php endif; ?>

            <!-- Form con action aggiornato -->
            <form method="POST" action="/crm/?action=operatori&view=create" class="operator-form">
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

                <!-- Informazioni Contrattuali -->
                <div class="form-section">
                    <h2 class="section-title">📋 Informazioni Contrattuali</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Tipo Contratto</label>
                            <select name="tipo_contratto" class="form-control">
                                <option value="">-- Seleziona --</option>
                                <option value="indeterminato" <?= ($_POST['tipo_contratto'] ?? '') === 'indeterminato' ? 'selected' : '' ?>>
                                    Tempo Indeterminato
                                </option>
                                <option value="determinato" <?= ($_POST['tipo_contratto'] ?? '') === 'determinato' ? 'selected' : '' ?>>
                                    Tempo Determinato
                                </option>
                                <option value="partita_iva" <?= ($_POST['tipo_contratto'] ?? '') === 'partita_iva' ? 'selected' : '' ?>>
                                    Partita IVA
                                </option>
                                <option value="apprendistato" <?= ($_POST['tipo_contratto'] ?? '') === 'apprendistato' ? 'selected' : '' ?>>
                                    Apprendistato
                                </option>
                                <option value="stage" <?= ($_POST['tipo_contratto'] ?? '') === 'stage' ? 'selected' : '' ?>>
                                    Stage/Tirocinio
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Data Inizio</label>
                            <input type="date" 
                                   name="data_inizio" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($_POST['data_inizio'] ?? date('Y-m-d')) ?>">
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
                                       value="<?= htmlspecialchars($_POST['orario_mattino_inizio'] ?? '09:00') ?>">
                                <span class="time-separator">-</span>
                                <input type="time" 
                                       name="orario_mattino_fine" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['orario_mattino_fine'] ?? '13:00') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Orario Pomeriggio</label>
                            <div class="time-inputs">
                                <input type="time" 
                                       name="orario_pomeriggio_inizio" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['orario_pomeriggio_inizio'] ?? '14:00') ?>">
                                <span class="time-separator">-</span>
                                <input type="time" 
                                       name="orario_pomeriggio_fine" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['orario_pomeriggio_fine'] ?? '18:00') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Orario Continuato (se applicabile)</label>
                            <div class="time-inputs" style="max-width: 300px;">
                                <input type="time" 
                                       name="orario_continuato_inizio" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['orario_continuato_inizio'] ?? '') ?>">
                                <span class="time-separator">-</span>
                                <input type="time" 
                                       name="orario_continuato_fine" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['orario_continuato_fine'] ?? '') ?>">
                            </div>
                            <div class="form-hint">
                                Compilare solo se l'operatore ha orario continuato invece di mattino/pomeriggio
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permessi e Stato -->
                <div class="form-section">
                    <h2 class="section-title">🔐 Permessi e Stato</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="switch-group">
                                <label class="switch">
                                    <input type="checkbox" 
                                           name="is_amministratore" 
                                           <?= isset($_POST['is_amministratore']) ? 'checked' : '' ?>>
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
                                           checked>
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

                <!-- Azioni Form -->
                <div class="form-actions">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            💾 Crea Operatore
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            🔄 Reset Form
                        </button>
                    </div>
                    <div>
                        <a href="/crm/?action=operatori" class="btn btn-outline">
                            ❌ Annulla
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-save form data
        const form = document.querySelector('.operator-form');
        const formInputs = form.querySelectorAll('input, select, textarea');
        
        // Salva i dati nel localStorage quando cambiano
        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                const formData = new FormData(form);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    if (!data[key]) data[key] = [];
                    data[key].push(value);
                }
                localStorage.setItem('operatorFormDraft', JSON.stringify(data));
            });
        });
        
        // Ripristina i dati salvati al caricamento (se non ci sono errori)
        <?php if (empty($_POST) && empty($errors)): ?>
        const savedData = localStorage.getItem('operatorFormDraft');
        if (savedData) {
            if (confirm('Trovati dati non salvati. Vuoi ripristinarli?')) {
                const data = JSON.parse(savedData);
                // Implementa il ripristino dei dati
            } else {
                localStorage.removeItem('operatorFormDraft');
            }
        }
        <?php endif; ?>
        
        // Pulisci localStorage dopo il successo
        <?php if ($success): ?>
        localStorage.removeItem('operatorFormDraft');
        <?php endif; ?>
    </script>
</body>
</html>