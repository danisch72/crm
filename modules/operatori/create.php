<?php
/**
 * modules/operatori/create.php - Creazione Operatore Ultra-Compatta
 * 
 * ✅ LAYOUT DINAMICO CHE USA TUTTO LO SCHERMO
 * ✅ ZERO SPRECHI DI SPAZIO, FORM ULTRA-DENSO
 */

if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

$pageTitle = 'Nuovo Operatore';
$pageIcon = '➕';

if (!$sessionInfo['is_admin']) {
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

// Gestione form
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cognome = trim($_POST['cognome'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $qualifiche = $_POST['qualifiche'] ?? [];
        
        $orarioMattinoInizio = $_POST['orario_mattino_inizio'] ?? null;
        $orarioMattinoFine = $_POST['orario_mattino_fine'] ?? null;
        $orarioPomeriggioInizio = $_POST['orario_pomeriggio_inizio'] ?? null;
        $orarioPomeriggioFine = $_POST['orario_pomeriggio_fine'] ?? null;
        
        $isAmministratore = isset($_POST['is_amministratore']) ? 1 : 0;
        $isAttivo = isset($_POST['is_attivo']) ? 1 : 0;
        
        // Validazione
        if (empty($cognome)) $errors[] = "Cognome obbligatorio";
        if (empty($nome)) $errors[] = "Nome obbligatorio";
        if (empty($email)) $errors[] = "Email obbligatoria";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida";
        
        // Verifica email unica
        if (empty($errors)) {
            $exists = $db->selectOne("SELECT id FROM operatori WHERE email = ?", [$email]);
            if ($exists) $errors[] = "Email già registrata";
        }
        
        if (empty($errors)) {
            // Genera codice
            $lastOp = $db->selectOne("SELECT MAX(CAST(SUBSTRING(codice_operatore, 3) AS UNSIGNED)) as max_code FROM operatori WHERE codice_operatore LIKE 'OP%'");
            $nextCode = ($lastOp['max_code'] ?? 0) + 1;
            $codiceOperatore = 'OP' . str_pad($nextCode, 4, '0', STR_PAD_LEFT);
            
            // Password temporanea
            $tempPassword = bin2hex(random_bytes(6));
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            
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
                'is_amministratore' => $isAmministratore,
                'is_attivo' => $isAttivo,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $_SESSION['success_message'] = "Operatore creato! Password: $tempPassword";
            header('Location: /crm/?action=operatori');
            exit;
        }
    } catch (Exception $e) {
        error_log("Errore creazione operatore: " . $e->getMessage());
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
        /* Override per rimuovere TUTTI i limiti di larghezza */
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
        
        /* Header ultra-compatto */
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
        
        /* Grid a 3 colonne per massima densità */
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        /* Sezioni compatte */
        .form-section {
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #495057;
        }
        
        /* Form controls ultra-compatti */
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
        
        .required {
            color: #dc3545;
        }
        
        /* Checkbox compatti a 4 colonne */
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
        
        /* Time inputs */
        .time-group {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .time-separator {
            font-size: 12px;
            color: #6c757d;
        }
        
        /* Switch compatti */
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
        
        /* Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Alert compatto */
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
        
        /* Responsive */
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
                    <form method="POST" class="form-card">
                        <div class="form-header">
                            <h1><?= $pageIcon ?> Crea Nuovo Operatore</h1>
                            <button type="submit" class="btn btn-primary btn-sm">
                                💾 Salva
                            </button>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-error">
                                <?= implode(' • ', array_map('htmlspecialchars', $errors)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Grid a 3 colonne -->
                        <div class="form-grid-3">
                            <!-- Colonna 1: Dati Anagrafici -->
                            <div>
                                <div class="form-section">
                                    <h2 class="section-title">👤 ANAGRAFICA</h2>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Cognome <span class="required">*</span></label>
                                        <input type="text" name="cognome" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Nome <span class="required">*</span></label>
                                        <input type="text" name="nome" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Email <span class="required">*</span></label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Telefono</label>
                                        <input type="tel" name="telefono" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <!-- Permessi -->
                                <div class="form-section">
                                    <h2 class="section-title">🔐 PERMESSI</h2>
                                    
                                    <div class="switch-group">
                                        <label class="form-switch">
                                            <input type="checkbox" name="is_amministratore" value="1" 
                                                   class="switch-checkbox"
                                                   <?= ($_POST['is_amministratore'] ?? false) ? 'checked' : '' ?>>
                                            <span class="switch-label">Admin</span>
                                        </label>
                                        
                                        <label class="form-switch">
                                            <input type="checkbox" name="is_attivo" value="1" 
                                                   class="switch-checkbox" checked
                                                   <?= ($_POST['is_attivo'] ?? true) ? 'checked' : '' ?>>
                                            <span class="switch-label">Attivo</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Colonna 2: Orari -->
                            <div>
                                <div class="form-section">
                                    <h2 class="section-title">⏰ ORARI LAVORO</h2>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Mattino</label>
                                        <div class="time-group">
                                            <input type="time" name="orario_mattino_inizio" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['orario_mattino_inizio'] ?? '08:30') ?>">
                                            <span class="time-separator">-</span>
                                            <input type="time" name="orario_mattino_fine" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['orario_mattino_fine'] ?? '12:30') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Pomeriggio</label>
                                        <div class="time-group">
                                            <input type="time" name="orario_pomeriggio_inizio" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['orario_pomeriggio_inizio'] ?? '14:00') ?>">
                                            <span class="time-separator">-</span>
                                            <input type="time" name="orario_pomeriggio_fine" class="form-control"
                                                   value="<?= htmlspecialchars($_POST['orario_pomeriggio_fine'] ?? '18:00') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Colonna 3: Azioni rapide e info -->
                            <div>
                                <div class="form-section">
                                    <h2 class="section-title">ℹ️ INFO</h2>
                                    <div style="font-size: 11px; color: #6c757d; line-height: 1.4;">
                                        <p style="margin: 0 0 4px 0;">• Password temporanea generata automaticamente</p>
                                        <p style="margin: 0 0 4px 0;">• L'operatore dovrà cambiarla al primo accesso</p>
                                        <p style="margin: 0 0 4px 0;">• Email utilizzata per login</p>
                                        <p style="margin: 0;">• Codice operatore assegnato automaticamente</p>
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
                                               <?= in_array($qualifica, $_POST['qualifiche'] ?? []) ? 'checked' : '' ?>>
                                        <label for="qual_<?= md5($qualifica) ?>" style="cursor: pointer;">
                                            <?= htmlspecialchars($qualifica) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="/crm/?action=operatori" class="btn btn-secondary btn-sm">
                                ← Annulla
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm">
                                ✅ Crea Operatore
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>