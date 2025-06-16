<?php
/**
 * modules/operatori/edit.php - Modifica Operatore CRM Re.De Consulting
 * 
 * ✅ LAYOUT ULTRA-DENSO UNIFORME v2.0 - OPERATORI-FRIENDLY
 * 
 * Features:
 * - Layout identico alla dashboard per uniformità totale
 * - Form modifica con controlli permessi avanzati
 * - Auto-edit e admin-edit supportati
 * - MANTENIMENTO TOTALE della logica esistente
 * - Design system Datev Koinos compliant
 */

// **LOGICA ESISTENTE MANTENUTA** - Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorsi assoluti per evitare problemi
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/functions/helpers.php';

// **LOGICA ESISTENTE MANTENUTA** - Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    header('Location: /crm/core/auth/login.php');
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// **LOGICA ESISTENTE MANTENUTA** - Recupera ID operatore da modificare
$operatoreId = $_GET['id'] ?? null;
if (!$operatoreId) {
    header('Location: /crm/modules/operatori/?error=missing_id');
    exit;
}

// Recupera dati operatore
$operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
if (!$operatore) {
    header('Location: /crm/modules/operatori/?error=not_found');
    exit;
}

// **LOGICA ESISTENTE MANTENUTA** - Controllo permessi: admin o auto-edit
$canEdit = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
$isAdminEdit = $sessionInfo['is_admin'] && $sessionInfo['operatore_id'] != $operatoreId;
$isSelfEdit = $sessionInfo['operatore_id'] == $operatoreId;

if (!$canEdit) {
    header('Location: /crm/modules/operatori/?error=permissions');
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
            
            // Permessi admin
            $isAmministratore = $isAdminEdit && isset($_POST['is_amministratore']) ? 1 : $operatore['is_amministratore'];
            $isAttivo = $isAdminEdit && isset($_POST['is_attivo']) ? 1 : $operatore['is_attivo'];
            
            // Orari di lavoro
            $orarioMattinoInizio = $_POST['orario_mattino_inizio'] ?? null;
            $orarioMattinoFine = $_POST['orario_mattino_fine'] ?? null;
            $orarioPomeriggioInizio = $_POST['orario_pomeriggio_inizio'] ?? null;
            $orarioPomeriggioFine = $_POST['orario_pomeriggio_fine'] ?? null;
            $orarioContinuatoInizio = $_POST['orario_continuato_inizio'] ?? null;
            $orarioContinuatoFine = $_POST['orario_continuato_fine'] ?? null;

            // Validazioni
            if (empty($cognome)) $errors[] = 'Il cognome è obbligatorio';
            if (empty($nome)) $errors[] = 'Il nome è obbligatorio';
            if (empty($email)) $errors[] = 'L\'email è obbligatoria';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida';
            if (empty($tipoContratto)) $errors[] = 'Il tipo di contratto è obbligatorio';

            // Verifica email duplicata (escludendo l'operatore corrente)
            if (!empty($email)) {
                $emailExists = $db->selectOne("SELECT id FROM operatori WHERE email = ? AND id != ?", [$email, $operatoreId]);
                if ($emailExists) {
                    $errors[] = 'Email già utilizzata da un altro operatore';
                }
            }

            // Se nessun errore, procedi con aggiornamento
            if (empty($errors)) {
                $qualificheJson = json_encode($qualifiche);
                
                $updated = $db->update(
                    "UPDATE operatori SET 
                        cognome = ?, nome = ?, email = ?, qualifiche = ?, tipo_contratto = ?,
                        orario_mattino_inizio = ?, orario_mattino_fine = ?,
                        orario_pomeriggio_inizio = ?, orario_pomeriggio_fine = ?,
                        orario_continuato_inizio = ?, orario_continuato_fine = ?,
                        is_amministratore = ?, is_attivo = ?, updated_at = NOW()
                    WHERE id = ?",
                    [
                        $cognome, $nome, $email, $qualificheJson, $tipoContratto,
                        $tipoContratto === 'spezzato' ? $orarioMattinoInizio : null,
                        $tipoContratto === 'spezzato' ? $orarioMattinoFine : null,
                        $tipoContratto === 'spezzato' ? $orarioPomeriggioInizio : null,
                        $tipoContratto === 'spezzato' ? $orarioPomeriggioFine : null,
                        $tipoContratto === 'continuato' ? $orarioContinuatoInizio : null,
                        $tipoContratto === 'continuato' ? $orarioContinuatoFine : null,
                        $isAmministratore, $isAttivo, $operatoreId
                    ]
                );
                
                if ($updated) {
                    $success = true;
                    // Ricarica dati aggiornati
                    $operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
                    $qualificheEsistenti = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];
                }
            }
            
        } elseif ($action === 'change_password') {
            // **LOGICA ESISTENTE MANTENUTA** - Cambio password
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validazioni password
            if ($isSelfEdit && !password_verify($currentPassword, $operatore['password'])) {
                $errors[] = 'Password attuale non corretta';
            }
            
            if (strlen($newPassword) < 8) {
                $errors[] = 'La nuova password deve essere di almeno 8 caratteri';
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Le password non coincidono';
            }
            
            if (empty($errors)) {
                $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
                
                $updated = $db->update(
                    "UPDATE operatori SET password = ?, updated_at = NOW() WHERE id = ?",
                    [$passwordHash, $operatoreId]
                );
                
                if ($updated) {
                    $passwordChanged = true;
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        error_log("Errore modifica operatore: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Operatore - CRM Re.De Consulting</title>
    
    <!-- CSS Sistema Uniforme -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* === LAYOUT ULTRA-DENSO UNIFORME - IDENTICO DASHBOARD === */
        
        /* Variabili CSS Uniformi - IDENTICHE */
        :root {
            --sidebar-width: 200px;
            --header-height: 60px;
            --primary-green: #2c6e49;
            --secondary-green: #4a9d6f;
            --accent-blue: #1e40af;
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
            --danger-red: #dc2626;
            --warning-yellow: #d97706;
            --success-green: #059669;
            --radius-md: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
        }

        /* Layout Principale Identico */
        .app-layout {
            display: flex;
            min-height: 100vh;
            background: var(--gray-50);
        }

        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--gray-200);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 10;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1rem;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--gray-700);
            text-decoration: none;
            font-size: var(--font-size-sm);
            border-radius: 0;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            background: var(--gray-50);
            color: var(--primary-green);
        }

        .nav-link.active {
            background: var(--primary-green);
            color: white;
        }

        .nav-icon {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            background: var(--accent-blue);
            color: white;
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            margin-left: auto;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .app-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            padding: 0.5rem;
        }

        .page-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .work-timer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-green);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }

        .timer-icon {
            font-size: 1rem;
        }

        .time-display {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .user-menu {
            position: relative;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--gray-600);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            font-size: var(--font-size-sm);
        }

        /* Content Layout */
        .content-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .edit-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .form-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .form-header {
            background: var(--gray-50);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-header h3 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-800);
            font-size: var(--font-size-lg);
        }

        .form-header p {
            margin: 0;
            color: var(--gray-500);
            font-size: var(--font-size-sm);
        }

        .form-content {
            padding: 1.5rem;
        }

        /* Info Badge */
        .info-badge {
            background: var(--accent-blue);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            font-size: var(--font-size-sm);
            text-align: center;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert h4 {
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }

        .alert ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        /* Form Elements */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: var(--font-size-sm);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(44, 110, 73, 0.1);
        }

        /* Qualifiche Checkboxes */
        .qualifiche-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
        }

        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }

        /* Orari Section */
        .orari-section {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
        }

        .orari-section.active {
            display: block;
        }

        .orari-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-green);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-danger {
            background: var(--danger-red);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 2rem;
            font-size: var(--font-size-sm);
            color: var(--gray-500);
        }

        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary-green);
        }

        /* Last Login Info */
        .last-login-info {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            font-size: var(--font-size-sm);
            text-align: center;
            color: var(--gray-600);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .edit-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .orari-row {
                grid-template-columns: 1fr;
            }

            .content-container {
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Uniforme -->
        <aside class="sidebar">
            <nav class="nav">
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="/crm/dashboard.php" class="nav-link">
                            <span class="nav-icon">🏠</span>
                            <span>Dashboard</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/operatori/" class="nav-link active">
                            <span class="nav-icon">👥</span>
                            <span>Operatori</span>
                            <?php if ($sessionInfo['is_admin']): ?>
                                <span class="nav-badge">Admin</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                
                <?php if ($sessionInfo['is_admin']): ?>
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="/crm/modules/clienti/" class="nav-link">
                            <span class="nav-icon">🏢</span>
                            <span>Clienti</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/reports/" class="nav-link">
                            <span class="nav-icon">📈</span>
                            <span>Reports</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/admin/" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span>Amministrazione</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header Uniforme -->
            <header class="app-header">
                <div class="header-left">
                    <button class="sidebar-toggle" type="button">
                        <span style="font-size: 1.25rem;">☰</span>
                    </button>
                    <h1 class="page-title">Modifica Operatore</h1>
                </div>
                
                <div class="header-right">
                    <!-- Timer Lavoro -->
                    <div class="work-timer work-timer-display">
                        <span class="timer-icon">⏱️</span>
                        <span class="time-display">00:00:00</span>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="user-menu">
                        <div class="user-avatar" data-tooltip="<?= htmlspecialchars($sessionInfo['nome_completo']) ?>">
                            <?= substr($sessionInfo['nome_completo'], 0, 1) ?>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <div class="content-container">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="/crm/dashboard.php">Dashboard</a>
                    <span> > </span>
                    <a href="/crm/modules/operatori/">Operatori</a>
                    <span> > </span>
                    <span style="color: var(--gray-800); font-weight: 500;">
                        <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                    </span>
                </div>
                
                <!-- Info Badge -->
                <?php if ($isSelfEdit): ?>
                    <div class="info-badge">
                        ℹ️ Stai modificando il tuo profilo personale
                    </div>
                <?php elseif ($isAdminEdit): ?>
                    <div class="info-badge">
                        🔧 Modifica amministratore - Accesso completo
                    </div>
                <?php endif; ?>
                
                <!-- Messaggi di errore/successo -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h4>❌ Errori di Validazione</h4>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h4>✅ Dati Aggiornati</h4>
                        <p>Le modifiche sono state salvate con successo.</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($passwordChanged): ?>
                    <div class="alert alert-success">
                        <h4>🔑 Password Aggiornata</h4>
                        <p>La password è stata cambiata con successo.</p>
                    </div>
                <?php endif; ?>
                
                <div class="edit-container">
                    <!-- Form Principale -->
                    <div class="form-container">
                        <div class="form-header">
                            <h3>✏️ Modifica Dati</h3>
                            <p><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></p>
                        </div>
                        
                        <div class="form-content">
                            <!-- **LOGICA ESISTENTE MANTENUTA** - Last Login Info -->
                            <?php 
                            $ultimoAccesso = $db->selectOne(
                                "SELECT login_timestamp FROM sessioni_lavoro WHERE operatore_id = ? 
                                 ORDER BY login_timestamp DESC LIMIT 1", 
                                [$operatoreId]
                            );
                            ?>
                            <?php if ($ultimoAccesso): ?>
                                <div class="last-login-info">
                                    📅 Ultimo accesso: <?= date('d/m/Y H:i', strtotime($ultimoAccesso['login_timestamp'])) ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Form Dati -->
                            <form method="POST">
                                <input type="hidden" name="action" value="update">
                                
                                <!-- Dati Anagrafici -->
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="cognome">Cognome *</label>
                                        <input type="text" 
                                               id="cognome" 
                                               name="cognome" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($operatore['cognome']) ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="nome">Nome *</label>
                                        <input type="text" 
                                               id="nome" 
                                               name="nome" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($operatore['nome']) ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="email">Email *</label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($operatore['email']) ?>" 
                                           required>
                                </div>
                                
                                <!-- Qualifiche -->
                                <div class="form-group">
                                    <label class="form-label">Qualifiche Professionali</label>
                                    <div class="qualifiche-grid">
                                        <?php foreach ($qualificheDisponibili as $qualifica): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" 
                                                       id="qual_<?= md5($qualifica) ?>" 
                                                       name="qualifiche[]" 
                                                       value="<?= htmlspecialchars($qualifica) ?>"
                                                       <?= in_array($qualifica, $qualificheEsistenti) ? 'checked' : '' ?>>
                                                <label for="qual_<?= md5($qualifica) ?>"><?= htmlspecialchars($qualifica) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Tipo Contratto -->
                                <div class="form-group">
                                    <label class="form-label" for="tipo_contratto">Tipo Contratto *</label>
                                    <select id="tipo_contratto" name="tipo_contratto" class="form-control" required>
                                        <option value="">Seleziona tipo contratto</option>
                                        <option value="spezzato" <?= $operatore['tipo_contratto'] === 'spezzato' ? 'selected' : '' ?>>
                                            Orario Spezzato (Mattino + Pomeriggio)
                                        </option>
                                        <option value="continuato" <?= $operatore['tipo_contratto'] === 'continuato' ? 'selected' : '' ?>>
                                            Orario Continuato
                                        </option>
                                    </select>
                                </div>
                                
                                <!-- Orari Spezzato -->
                                <div id="orari-spezzato" class="orari-section">
                                    <h4 style="margin-bottom: 1rem; color: var(--gray-700);">⏰ Orari Spezzato</h4>
                                    
                                    <div class="orari-row">
                                        <div class="form-group">
                                            <label class="form-label" for="orario_mattino_inizio">Mattino - Inizio</label>
                                            <input type="time" 
                                                   id="orario_mattino_inizio" 
                                                   name="orario_mattino_inizio" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_mattino_inizio'] ?? '') ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="orario_mattino_fine">Mattino - Fine</label>
                                            <input type="time" 
                                                   id="orario_mattino_fine" 
                                                   name="orario_mattino_fine" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_mattino_fine'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="orari-row">
                                        <div class="form-group">
                                            <label class="form-label" for="orario_pomeriggio_inizio">Pomeriggio - Inizio</label>
                                            <input type="time" 
                                                   id="orario_pomeriggio_inizio" 
                                                   name="orario_pomeriggio_inizio" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_pomeriggio_inizio'] ?? '') ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="orario_pomeriggio_fine">Pomeriggio - Fine</label>
                                            <input type="time" 
                                                   id="orario_pomeriggio_fine" 
                                                   name="orario_pomeriggio_fine" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_pomeriggio_fine'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Orari Continuato -->
                                <div id="orari-continuato" class="orari-section">
                                    <h4 style="margin-bottom: 1rem; color: var(--gray-700);">⏰ Orario Continuato</h4>
                                    
                                    <div class="orari-row">
                                        <div class="form-group">
                                            <label class="form-label" for="orario_continuato_inizio">Inizio</label>
                                            <input type="time" 
                                                   id="orario_continuato_inizio" 
                                                   name="orario_continuato_inizio" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_continuato_inizio'] ?? '') ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label" for="orario_continuato_fine">Fine</label>
                                            <input type="time" 
                                                   id="orario_continuato_fine" 
                                                   name="orario_continuato_fine" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($operatore['orario_continuato_fine'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Permessi Admin Only -->
                                <?php if ($isAdminEdit): ?>
                                <div class="form-row">
                                    <div class="form-group">
                                        <div class="checkbox-item">
                                            <input type="checkbox" 
                                                   id="is_amministratore" 
                                                   name="is_amministratore" 
                                                   value="1"
                                                   <?= $operatore['is_amministratore'] ? 'checked' : '' ?>>
                                            <label for="is_amministratore">🔧 Amministratore</label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="checkbox-item">
                                            <input type="checkbox" 
                                                   id="is_attivo" 
                                                   name="is_attivo" 
                                                   value="1" 
                                                   <?= $operatore['is_attivo'] ? 'checked' : '' ?>>
                                            <label for="is_attivo">✅ Operatore Attivo</label>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Actions -->
                                <div class="form-actions">
                                    <a href="/crm/modules/operatori/" class="btn btn-secondary">
                                        ← Annulla
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        💾 Salva Modifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sidebar Change Password -->
                    <div class="form-container">
                        <div class="form-header">
                            <h3>🔑 Cambia Password</h3>
                            <p>Modifica la password di accesso</p>
                        </div>
                        
                        <div class="form-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <!-- **LOGICA ESISTENTE MANTENUTA** - Password attuale solo per self-edit -->
                                <?php if ($isSelfEdit): ?>
                                <div class="form-group">
                                    <label class="form-label" for="current_password">Password Attuale *</label>
                                    <input type="password" 
                                           id="current_password" 
                                           name="current_password" 
                                           class="form-control" 
                                           required>
                                </div>
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label class="form-label" for="new_password">Nuova Password *</label>
                                    <input type="password" 
                                           id="new_password" 
                                           name="new_password" 
                                           class="form-control" 
                                           minlength="8"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Conferma Password *</label>
                                    <input type="password" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           class="form-control" 
                                           minlength="8"
                                           required>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-danger">
                                        🔑 Cambia Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="/crm/assets/js/microinteractions.js"></script>
    <script>
        // **LOGICA ESISTENTE MANTENUTA** - Gestione orari dinamici
        document.getElementById('tipo_contratto').addEventListener('change', function() {
            const spezzato = document.getElementById('orari-spezzato');
            const continuato = document.getElementById('orari-continuato');
            
            spezzato.classList.remove('active');
            continuato.classList.remove('active');
            
            if (this.value === 'spezzato') {
                spezzato.classList.add('active');
            } else if (this.value === 'continuato') {
                continuato.classList.add('active');
            }
        });

        // Attiva sezione orari se già selezionata
        const tipoContratto = document.getElementById('tipo_contratto').value;
        if (tipoContratto) {
            document.getElementById('tipo_contratto').dispatchEvent(new Event('change'));
        }

        // Validazione password match
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePassword() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Le password non coincidono');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);

        // Timer lavoro - logica esistente
        let timerInterval;
        let startTime = localStorage.getItem('workStartTime');
        let isPaused = localStorage.getItem('workPaused') === 'true';

        function updateTimer() {
            if (!startTime || isPaused) return;
            
            const now = new Date().getTime();
            const elapsed = now - parseInt(startTime);
            const hours = Math.floor(elapsed / (1000 * 60 * 60));
            const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
            
            const display = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            document.querySelector('.time-display').textContent = display;
        }

        if (startTime && !isPaused) {
            timerInterval = setInterval(updateTimer, 1000);
            updateTimer();
        }

        // Toggle sidebar mobile
        document.querySelector('.sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>