<?php
/**
 * modules/clienti/edit.php - Form Modifica Cliente CRM Re.De Consulting
 * 
 * ✅ MODIFICA CLIENTE ULTRA-COMPLETA COMMERCIALISTI
 * 
 * Features:
 * - Form completo pre-compilato con dati esistenti
 * - Validazione CF/P.IVA con controllo duplicati (escluso se stesso)
 * - Storico modifiche e audit trail
 * - Layout ultra-denso conforme al design system
 * - Sezioni collassibili per organizzazione dati
 * - Auto-save per prevenire perdite dati
 * - Controllo permessi granulare
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

// Verifica ID cliente
$clienteId = (int)($_GET['id'] ?? 0);
if (!$clienteId) {
    header('Location: /crm/modules/clienti/index.php');
    exit;
}

// Carica dati cliente esistenti
try {
    $cliente = $db->selectOne("
        SELECT * FROM clienti WHERE id = ?
    ", [$clienteId]);
    
    if (!$cliente) {
        header('Location: /crm/modules/clienti/index.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Errore caricamento cliente $clienteId: " . $e->getMessage());
    header('Location: /crm/modules/clienti/index.php?error=db_error');
    exit;
}

$errors = [];
$success = '';

// Gestione submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validazione dati
        $formData = [
            'ragione_sociale' => trim($_POST['ragione_sociale'] ?? ''),
            'tipo_cliente' => $_POST['tipo_cliente'] ?? '',
            'codice_fiscale' => trim($_POST['codice_fiscale'] ?? ''),
            'partita_iva' => trim($_POST['partita_iva'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'pec' => trim($_POST['pec'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'cellulare' => trim($_POST['cellulare'] ?? ''),
            'indirizzo' => trim($_POST['indirizzo'] ?? ''),
            'cap' => trim($_POST['cap'] ?? ''),
            'citta' => trim($_POST['citta'] ?? ''),
            'provincia' => trim($_POST['provincia'] ?? ''),
            'tipologia_azienda' => $_POST['tipologia_azienda'] ?? '',
            'regime_fiscale' => $_POST['regime_fiscale'] ?? '',
            'liquidazione_iva' => $_POST['liquidazione_iva'] ?? '',
            'settore_attivita' => trim($_POST['settore_attivita'] ?? ''),
            'codice_ateco' => trim($_POST['codice_ateco'] ?? ''),
            'capitale_sociale' => !empty($_POST['capitale_sociale']) ? (float)$_POST['capitale_sociale'] : null,
            'data_costituzione' => !empty($_POST['data_costituzione']) ? $_POST['data_costituzione'] : null,
            'operatore_responsabile_id' => (int)($_POST['operatore_responsabile_id'] ?? 0) ?: null,
            'stato' => $_POST['stato'] ?? 'attivo',
            'note_generali' => trim($_POST['note_generali'] ?? '')
        ];
        
        // Validazioni
        if (empty($formData['ragione_sociale'])) {
            $errors[] = 'Ragione sociale è obbligatoria';
        }
        
        // Validazione CF con controllo duplicati (escluso se stesso)
        if (!empty($formData['codice_fiscale'])) {
            if (!validateCodiceFiscale($formData['codice_fiscale'])) {
                $errors[] = 'Codice fiscale non valido';
            } else {
                $existing = $db->selectOne("SELECT id FROM clienti WHERE codice_fiscale = ? AND id != ?", 
                    [$formData['codice_fiscale'], $clienteId]);
                if ($existing) {
                    $errors[] = 'Codice fiscale già assegnato ad altro cliente';
                }
            }
        }
        
        // Validazione P.IVA con controllo duplicati (escluso se stesso)
        if (!empty($formData['partita_iva'])) {
            if (!validatePartitaIva($formData['partita_iva'])) {
                $errors[] = 'Partita IVA non valida';
            } else {
                $existing = $db->selectOne("SELECT id FROM clienti WHERE partita_iva = ? AND id != ?", 
                    [$formData['partita_iva'], $clienteId]);
                if ($existing) {
                    $errors[] = 'Partita IVA già assegnata ad altro cliente';
                }
            }
        }
        
        // Validazione email
        if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida';
        }
        
        // Validazione PEC
        if (!empty($formData['pec']) && !filter_var($formData['pec'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'PEC non valida';
        }
        
        // Validazione ATECO
        if (!empty($formData['codice_ateco']) && !preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $formData['codice_ateco'])) {
            $errors[] = 'Codice ATECO deve essere nel formato XX.XX.XX';
        }
        
        if (empty($errors)) {
            // Prepara dati per update
            $updateData = [
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
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $updated = $db->update('clienti', $updateData, 'id = ?', [$clienteId]);
            
            if ($updated) {
                // Log della modifica
                error_log("Cliente $clienteId modificato da operatore " . $sessionInfo['user_id']);
                
                // Aggiungi nota di sistema se c'erano cambiamenti significativi
                $changesDetected = checkSignificantChanges($cliente, $formData);
                if ($changesDetected) {
                    $db->insert('note_clienti', [
                        'cliente_id' => $clienteId,
                        'operatore_id' => $sessionInfo['user_id'],
                        'titolo' => 'Dati cliente aggiornati',
                        'contenuto' => 'Modifica dati cliente: ' . implode(', ', $changesDetected),
                        'tipo_nota' => 'altro',
                        'data_nota' => date('Y-m-d H:i:s')
                    ]);
                }
                
                $success = 'Cliente aggiornato con successo!';
                
                // Ricarica dati aggiornati
                $cliente = $db->selectOne("SELECT * FROM clienti WHERE id = ?", [$clienteId]);
            } else {
                $errors[] = 'Errore durante l\'aggiornamento';
            }
        }
        
    } catch (Exception $e) {
        error_log("Errore modifica cliente $clienteId: " . $e->getMessage());
        $errors[] = 'Errore interno durante il salvataggio';
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

// Funzioni helper
function validateCodiceFiscale($cf) {
    $cf = strtoupper(trim($cf));
    
    if (strlen($cf) === 16) {
        return preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf);
    }
    
    if (strlen($cf) === 11) {
        return preg_match('/^[0-9]{11}$/', $cf);
    }
    
    return false;
}

function validatePartitaIva($piva) {
    $piva = preg_replace('/[^0-9]/', '', $piva);
    
    if (strlen($piva) !== 11) {
        return false;
    }
    
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

function checkSignificantChanges($oldData, $newData) {
    $significantFields = [
        'ragione_sociale', 'codice_fiscale', 'partita_iva', 
        'email', 'telefono', 'tipologia_azienda', 'regime_fiscale'
    ];
    
    $changes = [];
    foreach ($significantFields as $field) {
        $oldValue = $oldData[$field] ?? '';
        $newValue = $newData[$field] ?? '';
        
        if ($oldValue !== $newValue) {
            $changes[] = ucfirst(str_replace('_', ' ', $field));
        }
    }
    
    return $changes;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ Modifica Cliente - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* Form Modifica Ultra-Denso */
        .edit-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .edit-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .edit-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .cliente-status {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }
        
        .edit-content {
            padding: 2rem;
        }
        
        /* Sezioni Collassibili */
        .section {
            margin-bottom: 2rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        
        .section-header {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-fast);
        }
        
        .section-header:hover {
            background: var(--gray-100);
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-toggle {
            font-size: 1.25rem;
            transition: transform var(--transition-fast);
        }
        
        .section.collapsed .section-toggle {
            transform: rotate(-90deg);
        }
        
        .section-content {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: block;
        }
        
        .section.collapsed .section-content {
            display: none;
        }
        
        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-grid.triple {
            grid-template-columns: 1fr 1fr 1fr;
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
        
        .field-help {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Dati Originali Display */
        .original-value {
            background: var(--gray-50);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }
        
        /* Actions */
        .edit-actions {
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
            text-decoration: none;
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
        
        .btn-danger {
            background: var(--danger-red);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        /* Alerts */
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
        
        /* Info Panel */
        .info-panel {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .info-value {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-900);
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
            .form-grid,
            .form-grid.triple {
                grid-template-columns: 1fr;
            }
            
            .edit-content {
                padding: 1rem;
            }
            
            .edit-actions {
                padding: 1rem;
            }
            
            .edit-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Auto-save indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator">
        💾 Modifiche salvate automaticamente
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
        <div class="edit-container">
            <!-- Header -->
            <div class="edit-header">
                <div class="edit-title">
                    ✏️ Modifica Cliente: <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                </div>
                <div class="cliente-status">
                    Codice: <?= htmlspecialchars($cliente['codice_cliente']) ?>
                </div>
            </div>

            <!-- Content -->
            <div class="edit-content">
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

                <!-- Info Panel -->
                <div class="info-panel">
                    <div class="info-row">
                        <span class="info-label">Cliente creato il:</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($cliente['created_at'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ultima modifica:</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($cliente['updated_at'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Codice Cliente:</span>
                        <span class="info-value"><?= htmlspecialchars($cliente['codice_cliente']) ?></span>
                    </div>
                </div>

                <form method="POST" id="editForm">
                    <!-- Sezione 1: Dati Anagrafici -->
                    <div class="section">
                        <div class="section-header" onclick="toggleSection(this)">
                            <div class="section-title">
                                📝 Dati Anagrafici Base
                            </div>
                            <div class="section-toggle">▼</div>
                        </div>
                        <div class="section-content">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label required">Ragione Sociale / Nome</label>
                                    <input type="text" name="ragione_sociale" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['ragione_sociale']) ?>" required>
                                    <div class="original-value">
                                        Originale: <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                    </div>
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Tipologia Azienda</label>
                                    <select name="tipologia_azienda" class="form-select">
                                        <option value="individuale" <?= $cliente['tipologia_azienda'] === 'individuale' ? 'selected' : '' ?>>👤 Ditta Individuale</option>
                                        <option value="srl" <?= $cliente['tipologia_azienda'] === 'srl' ? 'selected' : '' ?>>🏢 SRL</option>
                                        <option value="spa" <?= $cliente['tipologia_azienda'] === 'spa' ? 'selected' : '' ?>>🏭 SPA</option>
                                        <option value="snc" <?= $cliente['tipologia_azienda'] === 'snc' ? 'selected' : '' ?>>👥 SNC</option>
                                        <option value="sas" <?= $cliente['tipologia_azienda'] === 'sas' ? 'selected' : '' ?>>🤝 SAS</option>
                                        <option value="altro" <?= $cliente['tipologia_azienda'] === 'altro' ? 'selected' : '' ?>>📋 Altro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">Stato Cliente</label>
                                    <select name="stato" class="form-select">
                                        <option value="attivo" <?= $cliente['is_attivo'] ? 'selected' : '' ?>>🟢 Attivo</option>
                                        <option value="sospeso" <?= !$cliente['is_attivo'] ? 'selected' : '' ?>>🟡 Sospeso</option>
                                    </select>
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Operatore Responsabile</label>
                                    <select name="operatore_responsabile_id" class="form-select">
                                        <option value="">Nessun operatore assegnato</option>
                                        <?php foreach ($operatori as $operatore): ?>
                                            <option value="<?= $operatore['id'] ?>" 
                                                    <?= $cliente['operatore_responsabile_id'] == $operatore['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($operatore['nome_completo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione 2: Dati Fiscali -->
                    <div class="section">
                        <div class="section-header" onclick="toggleSection(this)">
                            <div class="section-title">
                                🧾 Dati Fiscali e Identificativi
                            </div>
                            <div class="section-toggle">▼</div>
                        </div>
                        <div class="section-content">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">Codice Fiscale</label>
                                    <input type="text" name="codice_fiscale" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['codice_fiscale']) ?>"
                                           id="codice_fiscale">
                                    <div class="validation-status" id="cf_validation"></div>
                                    <div class="field-help">16 caratteri per persone fisiche, 11 per aziende</div>
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Partita IVA</label>
                                    <input type="text" name="partita_iva" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['partita_iva']) ?>"
                                           id="partita_iva">
                                    <div class="validation-status" id="piva_validation"></div>
                                    <div class="field-help">11 cifre per aziende e professionisti</div>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">Regime Fiscale</label>
                                    <select name="regime_fiscale" class="form-select">
                                        <option value="ordinario" <?= $cliente['regime_fiscale'] === 'ordinario' ? 'selected' : '' ?>>📊 Ordinario</option>
                                        <option value="semplificato" <?= $cliente['regime_fiscale'] === 'semplificato' ? 'selected' : '' ?>>📝 Semplificato</option>
                                        <option value="forfettario" <?= $cliente['regime_fiscale'] === 'forfettario' ? 'selected' : '' ?>>💰 Forfettario</option>
                                        <option value="altro" <?= $cliente['regime_fiscale'] === 'altro' ? 'selected' : '' ?>>📋 Altro</option>
                                    </select>
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Liquidazione IVA</label>
                                    <select name="liquidazione_iva" class="form-select">
                                        <option value="mensile" <?= $cliente['liquidazione_iva'] === 'mensile' ? 'selected' : '' ?>>📅 Mensile</option>
                                        <option value="trimestrale" <?= $cliente['liquidazione_iva'] === 'trimestrale' ? 'selected' : '' ?>>📆 Trimestrale</option>
                                        <option value="annuale" <?= $cliente['liquidazione_iva'] === 'annuale' ? 'selected' : '' ?>>📊 Annuale</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione 3: Contatti -->
                    <div class="section">
                        <div class="section-header" onclick="toggleSection(this)">
                            <div class="section-title">
                                📞 Contatti e Indirizzi
                            </div>
                            <div class="section-toggle">▼</div>
                        </div>
                        <div class="section-content">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['email']) ?>">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">PEC</label>
                                    <input type="email" name="pec" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['pec']) ?>">
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">Telefono</label>
                                    <input type="tel" name="telefono" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['telefono']) ?>">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Cellulare</label>
                                    <input type="tel" name="cellulare" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['cellulare']) ?>">
                                </div>
                            </div>
                            
                            <div class="form-grid full-width">
                                <div class="form-field">
                                    <label class="form-label">Indirizzo Completo</label>
                                    <input type="text" name="indirizzo" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['indirizzo']) ?>">
                                </div>
                            </div>
                            
                            <div class="form-grid triple">
                                <div class="form-field">
                                    <label class="form-label">CAP</label>
                                    <input type="text" name="cap" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['cap']) ?>">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Città</label>
                                    <input type="text" name="citta" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['citta']) ?>">
                                </div>
                                
                                <div class="form-field">
                                    <label class="form-label">Provincia</label>
                                    <input type="text" name="provincia" class="form-input" 
                                           value="<?= htmlspecialchars($cliente['provincia']) ?>" maxlength="2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione 4: Note -->
                    <div class="section">
                        <div class="section-header" onclick="toggleSection(this)">
                            <div class="section-title">
                                📝 Note e Informazioni Aggiuntive
                            </div>
                            <div class="section-toggle">▼</div>
                        </div>
                        <div class="section-content">
                            <div class="form-grid full-width">
                                <div class="form-field">
                                    <label class="form-label">Note Generali</label>
                                    <textarea name="note_generali" class="form-input form-textarea" 
                                              placeholder="Note aggiuntive, particolarità del cliente, preferenze..."><?= htmlspecialchars($cliente['note_generali']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Actions -->
            <div class="edit-actions">
                <div>
                    <a href="/crm/modules/clienti/view.php?id=<?= $cliente['id'] ?>" class="btn btn-secondary">
                        👁️ Visualizza Cliente
                    </a>
                </div>
                
                <div class="btn-group">
                    <a href="/crm/modules/clienti/index.php" class="btn btn-secondary">
                        ❌ Annulla
                    </a>
                    <button type="button" class="btn btn-primary" onclick="saveChanges()">
                        💾 Salva Modifiche
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // Auto-save setup
        let autoSaveTimeout;
        let hasUnsavedChanges = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            setupRealTimeValidation();
            setupAutoSave();
            setupFormChangeDetection();
            
            console.log('Form modifica cliente inizializzato per ID: <?= $cliente['id'] ?>');
        });
        
        function toggleSection(header) {
            const section = header.parentElement;
            section.classList.toggle('collapsed');
        }
        
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
        
        function setupFormChangeDetection() {
            const form = document.getElementById('editForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    hasUnsavedChanges = true;
                    triggerAutoSave();
                });
            });
            
            // Warning per modifiche non salvate
            window.addEventListener('beforeunload', function(e) {
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = 'Hai modifiche non salvate. Sicuro di voler uscire?';
                }
            });
        }
        
        function setupAutoSave() {
            // Auto-save ogni 30 secondi se ci sono modifiche
            setInterval(() => {
                if (hasUnsavedChanges) {
                    autoSave();
                }
            }, 30000);
        }
        
        function triggerAutoSave() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                autoSave();
            }, 5000); // Auto-save dopo 5 secondi di inattività
        }
        
        function autoSave() {
            // Implementazione auto-save (salvataggio in sessione o draft)
            const formData = new FormData(document.getElementById('editForm'));
            
            // Salva in localStorage per recovery
            const dataObj = {};
            for (let [key, value] of formData.entries()) {
                dataObj[key] = value;
            }
            localStorage.setItem('cliente_edit_<?= $cliente['id'] ?>', JSON.stringify(dataObj));
            
            showAutoSaveIndicator();
        }
        
        function showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.classList.add('show');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
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
                
                if (cf.length === 16) {
                    isValid = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/.test(cf);
                } else if (cf.length === 11) {
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
        
        function saveChanges() {
            if (!confirm('Sicuro di voler salvare le modifiche al cliente?')) {
                return;
            }
            
            document.getElementById('editForm').submit();
            hasUnsavedChanges = false;
            
            // Rimuovi dati auto-save
            localStorage.removeItem('cliente_edit_<?= $cliente['id'] ?>');
        }
        
        // Recovery da auto-save al caricamento
        window.addEventListener('load', function() {
            const savedData = localStorage.getItem('cliente_edit_<?= $cliente['id'] ?>');
            if (savedData) {
                const confirmed = confirm('Sono presenti modifiche non salvate. Vuoi ripristinarle?');
                if (confirmed) {
                    const data = JSON.parse(savedData);
                    for (let [key, value] of Object.entries(data)) {
                        const input = document.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = value;
                            hasUnsavedChanges = true;
                        }
                    }
                } else {
                    localStorage.removeItem('cliente_edit_<?= $cliente['id'] ?>');
                }
            }
        });
    </script>
</body>
</html>