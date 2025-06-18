<?php
/**
 * modules/operatori/edit.php - Modifica Operatore Ultra-Compatta
 * 
 * ✅ LAYOUT DINAMICO CHE USA TUTTO LO SCHERMO
 * ✅ FORM ULTRA-DENSO SU 3 COLONNE
 */

if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

$pageTitle = 'Modifica Operatore';
$pageIcon = '✏️';

// Recupera ID operatore
$operatoreId = $_GET['id'];

// Recupera dati operatore
$operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
if (!$operatore) {
    header('Location: /crm/?action=operatori&error=not_found');
    exit;
}

// Controllo permessi
$canEdit = $sessionInfo['is_admin'] || $sessionInfo['operatore_id'] == $operatoreId;
$isAdminEdit = $sessionInfo['is_admin'] && $sessionInfo['operatore_id'] != $operatoreId;
$isSelfEdit = $sessionInfo['operatore_id'] == $operatoreId;

if (!$canEdit) {
    header('Location: /crm/?action=operatori&error=permissions');
    exit;
}

// Qualifiche disponibili
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

$qualificheEsistenti = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];

// Gestione form
$errors = [];
$success = false;
$passwordChanged = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'update';
        
        if ($action === 'update') {
            // Aggiornamento dati
            $cognome = trim($_POST['cognome'] ?? '');
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $qualifiche = $_POST['qualifiche'] ?? [];
            
            $orarioMattinoInizio = $_POST['orario_mattino_inizio'] ?? null;
            $orarioMattinoFine = $_POST['orario_mattino_fine'] ?? null;
            $orarioPomeriggioInizio = $_POST['orario_pomeriggio_inizio'] ?? null;
            $orarioPomeriggioFine = $_POST['orario_pomeriggio_fine'] ?? null;
            
            if ($isAdminEdit) {
                $isAmministratore = isset($_POST['is_amministratore']) ? 1 : 0;
                $isAttivo = isset($_POST['is_attivo']) ? 1 : 0;
            } else {
                $isAmministratore = $operatore['is_amministratore'];
                $isAttivo = $operatore['is_attivo'];
            }
            
            // Validazione
            if (empty($cognome)) $errors[] = "Cognome obbligatorio";
            if (empty($nome)) $errors[] = "Nome obbligatorio";
            if (empty($email)) $errors[] = "Email obbligatoria";
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida";
            
            if (empty($errors)) {
                $exists = $db->selectOne("SELECT id FROM operatori WHERE email = ? AND id != ?", [$email, $operatoreId]);
                if ($exists) $errors[] = "Email già utilizzata";
            }
            
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
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($isAdminEdit) {
                    $updateData['is_amministratore'] = $isAmministratore;
                    $updateData['is_attivo'] = $isAttivo;
                }
                
                $db->update('operatori', $updateData, 'id = ?', [$operatoreId]);
                $success = true;
                
                // Ricarica dati
                $operatore = $db->selectOne("SELECT * FROM operatori WHERE id = ?", [$operatoreId]);
                $qualificheEsistenti = json_decode($operatore['qualifiche'] ?? '[]', true) ?: [];
            }
            
        } elseif ($action === 'change_password' && $isSelfEdit) {
            // Cambio password
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword)) $errors[] = "Password attuale obbligatoria";
            if (empty($newPassword)) $errors[] = "Nuova password obbligatoria";
            elseif (strlen($newPassword) < 8) $errors[] = "Password minimo 8 caratteri";
            if ($newPassword !== $confirmPassword) $errors[] = "Le password non coincidono";
            
            if (empty($errors) && !password_verify($currentPassword, $operatore['password_hash'])) {
                $errors[] = "Password attuale errata";
            }
            
            if (empty($errors)) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->update('operatori', ['password_hash' => $newHash], 'id = ?', [$operatoreId]);
                $passwordChanged = true;
            }
        }
    } catch (Exception $e) {
        error_log("Errore modifica operatore: " . $e->getMessage());
        $errors[] = "Errore di sistema";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De</title>
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-ultra-compact.css">
    <style>
        /* Stessi stili di create.php ma con tabs */
        .form-container {
            max-width: none !important;
            width: 100%;
            padding: 12px;
            margin: 0;
        }
        
        .form-card {
            background: white;
            border: 1px solid #dee2e6;
            padding: 16px;
            margin-bottom: 0;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-header h1 {
            font-size: 16px;
            margin: 0;
        }
        
        .operator-info {
            font-size: 12px;
            color: #6c757d;
        }
        
        /* Tabs ultra-compatti */
        .tab-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab-link {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .tab-link:hover {
            color: #495057;
        }
        
        .tab-link.active {
            color: #007849;
            border-bottom-color: #007849;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Grid a 3 colonne */
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        .form-section {
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #495057;
        }
        
        .form-group {
            margin-bottom: 8px;
        }
        
        .form-label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .form-control {
            height: 26px;
            padding: 0 6px;
            font-size: 12px;
            border: 1px solid #ced4da;
            width: 100%;
        }
        
        .form-control:disabled {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .required {
            color: #dc3545;
        }
        
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 4px;
            background: #f8f9fa;
            font-size: 11px;
        }
        
        .checkbox-item input {
            margin-right: 4px;
        }
        
        .time-group {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .time-separator {
            font-size: 12px;
            color: #6c757d;
        }
        
        .switch-group {
            display: flex;
            gap: 16px;
        }
        
        .form-switch {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px;
            background: #f8f9fa;
            border-radius: 3px;
            flex: 1;
        }
        
        .switch-checkbox {
            width: 36px;
            height: 20px;
            position: relative;
            appearance: none;
            background: #ced4da;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .switch-checkbox:checked {
            background: #007849;
        }
        
        .switch-checkbox::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
        }
        
        .switch-checkbox:checked::after {
            transform: translateX(16px);
        }
        
        .switch-label {
            font-size: 12px;
            font-weight: 500;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
        }
        
        .alert {
            padding: 8px 12px;
            margin-bottom: 12px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .info-badge {
            display: inline-flex;
            padding: 2px 8px;
            background: #e9ecef;
            color: #495057;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        
        /* Password section */
        .password-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            max-width: 400px;
        }
        
        @media (max-width: 1200px) {
            .form-grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
            .checkbox-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .form-grid-3 {
                grid-template-columns: 1fr;
            }
            .checkbox-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
        
        <div class="content-wrapper">
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
            
            <main class="main-content">
                <div class="form-container">
                    <div class="form-card">
                        <div class="form-header">
                            <div>
                                <h1><?= $pageIcon ?> Modifica Operatore</h1>
                                <div class="operator-info">
                                    <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                                    <span class="info-badge"><?= $operatore['codice_operatore'] ?></span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="/crm/?action=operatori&view=view&id=<?= $operatoreId ?>" class="btn btn-secondary btn-sm">
                                    👁️ Visualizza
                                </a>
                                <a href="/crm/?action=operatori" class="btn btn-secondary btn-sm">
                                    ← Lista
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-error">
                                <?= implode(' • ', array_map('htmlspecialchars', $errors)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">✅ Dati aggiornati con successo!</div>
                        <?php endif; ?>
                        
                        <?php if ($passwordChanged): ?>
                            <div class="alert alert-success">✅ Password cambiata con successo!</div>
                        <?php endif; ?>
                        
                        <!-- Tabs -->
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
                        
                        <!-- Tab Dati Generali -->
                        <div id="dati-generali" class="tab-content active">
                            <form method="POST">
                                <input type="hidden" name="action" value="update">
                                
                                <div class="form-grid-3">
                                    <!-- Colonna 1: Anagrafica -->
                                    <div>
                                        <div class="form-section">
                                            <h2 class="section-title">👤 ANAGRAFICA</h2>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Cognome <span class="required">*</span></label>
                                                <input type="text" name="cognome" class="form-control" 
                                                       value="<?= htmlspecialchars($operatore['cognome']) ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Nome <span class="required">*</span></label>
                                                <input type="text" name="nome" class="form-control" 
                                                       value="<?= htmlspecialchars($operatore['nome']) ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Email <span class="required">*</span></label>
                                                <input type="email" name="email" class="form-control" 
                                                       value="<?= htmlspecialchars($operatore['email']) ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Telefono</label>
                                                <input type="tel" name="telefono" class="form-control" 
                                                       value="<?= htmlspecialchars($operatore['telefono'] ?? '') ?>">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Codice Op.</label>
                                                <input type="text" class="form-control" 
                                                       value="<?= htmlspecialchars($operatore['codice_operatore']) ?>" disabled>
                                            </div>
                                        </div>
                                        
                                        <?php if ($isAdminEdit): ?>
                                        <div class="form-section">
                                            <h2 class="section-title">🔐 PERMESSI</h2>
                                            
                                            <div class="switch-group">
                                                <label class="form-switch">
                                                    <input type="checkbox" name="is_amministratore" value="1" 
                                                           class="switch-checkbox"
                                                           <?= $operatore['is_amministratore'] ? 'checked' : '' ?>>
                                                    <span class="switch-label">Admin</span>
                                                </label>
                                                
                                                <label class="form-switch">
                                                    <input type="checkbox" name="is_attivo" value="1" 
                                                           class="switch-checkbox"
                                                           <?= $operatore['is_attivo'] ? 'checked' : '' ?>>
                                                    <span class="switch-label">Attivo</span>
                                                </label>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="form-section">
                                            <h2 class="section-title">🔐 STATO</h2>
                                            <div style="display: flex; gap: 8px;">
                                                <?php if ($operatore['is_amministratore']): ?>
                                                    <span class="info-badge">👑 Admin</span>
                                                <?php endif; ?>
                                                <span class="info-badge">
                                                    <?= $operatore['is_attivo'] ? '✅ Attivo' : '❌ Inattivo' ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Colonna 2: Orari -->
                                    <div>
                                        <div class="form-section">
                                            <h2 class="section-title">⏰ ORARI LAVORO</h2>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Mattino</label>
                                                <div class="time-group">
                                                    <input type="time" name="orario_mattino_inizio" class="form-control"
                                                           value="<?= htmlspecialchars($operatore['orario_mattino_inizio'] ?? '') ?>">
                                                    <span class="time-separator">-</span>
                                                    <input type="time" name="orario_mattino_fine" class="form-control"
                                                           value="<?= htmlspecialchars($operatore['orario_mattino_fine'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="form-label">Pomeriggio</label>
                                                <div class="time-group">
                                                    <input type="time" name="orario_pomeriggio_inizio" class="form-control"
                                                           value="<?= htmlspecialchars($operatore['orario_pomeriggio_inizio'] ?? '') ?>">
                                                    <span class="time-separator">-</span>
                                                    <input type="time" name="orario_pomeriggio_fine" class="form-control"
                                                           value="<?= htmlspecialchars($operatore['orario_pomeriggio_fine'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-section">
                                            <h2 class="section-title">📊 STATISTICHE</h2>
                                            <div style="font-size: 11px; line-height: 1.6;">
                                                <div>Registrato: <?= date('d/m/Y', strtotime($operatore['created_at'])) ?></div>
                                                <?php if ($operatore['ultimo_accesso']): ?>
                                                <div>Ultimo accesso: <?= date('d/m/Y H:i', strtotime($operatore['ultimo_accesso'])) ?></div>
                                                <?php endif; ?>
                                                <?php if ($operatore['updated_at']): ?>
                                                <div>Ultima modifica: <?= date('d/m/Y', strtotime($operatore['updated_at'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Colonna 3: Info e azioni -->
                                    <div>
                                        <div class="form-section">
                                            <h2 class="section-title">📋 RIEPILOGO</h2>
                                            <div style="font-size: 11px; line-height: 1.6;">
                                                <?php
                                                // Conta clienti assegnati
                                                $clientiCount = $db->count('clienti', 'operatore_responsabile_id = ?', [$operatoreId]);
                                                // Conta sessioni ultimo mese
                                                $sessioniCount = $db->count('sessioni_lavoro', 
                                                    'operatore_id = ? AND DATE(login_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)', 
                                                    [$operatoreId]);
                                                ?>
                                                <div>• Clienti gestiti: <strong><?= $clientiCount ?></strong></div>
                                                <div>• Sessioni (30gg): <strong><?= $sessioniCount ?></strong></div>
                                                <div>• Qualifiche: <strong><?= count($qualificheEsistenti) ?></strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Qualifiche su tutta la larghezza -->
                                <div class="form-section">
                                    <h2 class="section-title">🎯 QUALIFICHE E COMPETENZE</h2>
                                    <div class="checkbox-grid">
                                        <?php foreach ($qualificheDisponibili as $qualifica): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" 
                                                       id="qual_<?= md5($qualifica) ?>" 
                                                       name="qualifiche[]" 
                                                       value="<?= htmlspecialchars($qualifica) ?>"
                                                       <?= in_array($qualifica, $qualificheEsistenti) ? 'checked' : '' ?>>
                                                <label for="qual_<?= md5($qualifica) ?>" style="cursor: pointer;">
                                                    <?= htmlspecialchars($qualifica) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <a href="/crm/?action=operatori" class="btn btn-secondary btn-sm">
                                        ← Torna alla Lista
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        ✅ Salva Modifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <?php if ($isSelfEdit): ?>
                        <!-- Tab Sicurezza -->
                        <div id="sicurezza" class="tab-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-section">
                                    <h2 class="section-title">🔑 CAMBIO PASSWORD</h2>
                                    
                                    <div class="password-grid">
                                        <div class="form-group">
                                            <label class="form-label">Password Attuale <span class="required">*</span></label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Nuova Password <span class="required">*</span></label>
                                            <input type="password" name="new_password" class="form-control" required>
                                            <div style="font-size: 10px; color: #6c757d; margin-top: 2px;">Minimo 8 caratteri</div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Conferma Password <span class="required">*</span></label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 16px;">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            🔐 Cambia Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    function switchTab(event, tabId) {
        event.preventDefault();
        
        // Rimuovi active da tutti
        document.querySelectorAll('.tab-link').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Aggiungi active al selezionato
        event.target.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    }
    </script>
</body>
</html>