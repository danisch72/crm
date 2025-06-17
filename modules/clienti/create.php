<?php
/**
 * modules/clienti/create.php - Creazione Nuovo Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE CON SIDEBAR E HEADER CENTRALIZZATI
 * 
 * Features:
 * - Form creazione cliente con validazioni
 * - Business logic commercialisti
 * - Validazione CF/P.IVA italiana
 * - Assegnazione operatore responsabile
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili per i componenti
$pageTitle = 'Nuovo Cliente';
$pageIcon = '➕';

// Solo admin possono creare clienti
if (!$sessionInfo['is_admin']) {
    $_SESSION['error_message'] = '⚠️ Solo gli amministratori possono creare nuovi clienti';
    header('Location: /crm/?action=clienti');
    exit;
}

// Carica lista operatori per assegnazione
$operatori = [];
try {
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo
        FROM operatori
        WHERE is_attivo = 1
        ORDER BY cognome, nome
    ");
} catch (Exception $e) {
    error_log("Errore caricamento operatori: " . $e->getMessage());
}

// Tipologie azienda disponibili
$tipologieAzienda = [
    'individuale' => 'Ditta Individuale',
    'srl' => 'S.r.l. - Società a Responsabilità Limitata',
    'spa' => 'S.p.A. - Società per Azioni',
    'snc' => 'S.n.c. - Società in Nome Collettivo',
    'sas' => 'S.a.s. - Società in Accomandita Semplice'
];

// Stati disponibili
$statiDisponibili = [
    'attivo' => 'Attivo',
    'sospeso' => 'Sospeso'
];

// Gestione form submission
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Raccogli dati form
    $formData = [
        'ragione_sociale' => trim($_POST['ragione_sociale'] ?? ''),
        'tipologia_azienda' => $_POST['tipologia_azienda'] ?? '',
        'codice_fiscale' => strtoupper(trim($_POST['codice_fiscale'] ?? '')),
        'partita_iva' => trim($_POST['partita_iva'] ?? ''),
        'indirizzo' => trim($_POST['indirizzo'] ?? ''),
        'cap' => trim($_POST['cap'] ?? ''),
        'citta' => trim($_POST['citta'] ?? ''),
        'provincia' => strtoupper(trim($_POST['provincia'] ?? '')),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'pec' => strtolower(trim($_POST['pec'] ?? '')),
        'codice_univoco' => strtoupper(trim($_POST['codice_univoco'] ?? '')),
        'operatore_responsabile_id' => $_POST['operatore_responsabile_id'] ?? null,
        'stato' => $_POST['stato'] ?? 'attivo',
        'note' => trim($_POST['note'] ?? '')
    ];
    
    // Validazioni
    if (empty($formData['ragione_sociale'])) {
        $errors[] = "La ragione sociale è obbligatoria";
    }
    
    if (empty($formData['tipologia_azienda'])) {
        $errors[] = "La tipologia azienda è obbligatoria";
    }
    
    // Validazione CF/P.IVA in base a tipologia
    if ($formData['tipologia_azienda'] === 'individuale') {
        if (empty($formData['codice_fiscale']) || !preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $formData['codice_fiscale'])) {
            $errors[] = "Codice fiscale non valido per ditta individuale";
        }
    } else {
        if (empty($formData['partita_iva']) || !preg_match('/^[0-9]{11}$/', $formData['partita_iva'])) {
            $errors[] = "Partita IVA non valida";
        }
    }
    
    // Validazione email
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida";
    }
    
    if (!empty($formData['pec']) && !filter_var($formData['pec'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "PEC non valida";
    }
    
    // Validazione CAP
    if (!empty($formData['cap']) && !preg_match('/^[0-9]{5}$/', $formData['cap'])) {
        $errors[] = "CAP non valido (deve essere di 5 cifre)";
    }
    
    // Se non ci sono errori, salva
    if (empty($errors)) {
        try {
            // Verifica unicità CF/P.IVA
            if (!empty($formData['codice_fiscale'])) {
                $existing = $db->selectOne("SELECT id FROM clienti WHERE codice_fiscale = ?", [$formData['codice_fiscale']]);
                if ($existing) {
                    $errors[] = "Codice fiscale già presente in archivio";
                }
            }
            
            if (!empty($formData['partita_iva'])) {
                $existing = $db->selectOne("SELECT id FROM clienti WHERE partita_iva = ?", [$formData['partita_iva']]);
                if ($existing) {
                    $errors[] = "Partita IVA già presente in archivio";
                }
            }
            
            if (empty($errors)) {
                // Prepara dati per insert
                $insertData = $formData;
                $insertData['created_by'] = $sessionInfo['operatore_id'];
                $insertData['created_at'] = date('Y-m-d H:i:s');
                $insertData['updated_at'] = date('Y-m-d H:i:s');
                
                // Inserisci cliente
                $clienteId = $db->insert('clienti', $insertData);
                
                if ($clienteId) {
                    $_SESSION['success_message'] = '✅ Cliente creato con successo';
                    header('Location: /crm/?action=clienti&view=view&id=' . $clienteId);
                    exit;
                } else {
                    $errors[] = "Errore durante la creazione del cliente";
                }
            }
        } catch (Exception $e) {
            error_log("Errore creazione cliente: " . $e->getMessage());
            $errors[] = "Errore di sistema durante la creazione";
        }
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
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-professional.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        .form-container {
            padding: 1.5rem;
            background: #f9fafb;
            min-height: calc(100vh - 64px);
        }
        
        .form-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
        }
        
        .form-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 0.75rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.375rem;
        }
        
        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            transition: all 0.2s;
            background: #ffffff;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007849;
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: white;
            cursor: pointer;
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn-submit {
            background: #007849;
            color: white;
            padding: 0.625rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8125rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 120, 73, 0.2);
        }
        
        .btn-cancel {
            background: #ffffff;
            color: #4b5563;
            padding: 0.625rem 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8125rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.8125rem;
            border: 1px solid;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }
        
        .form-help {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- ✅ COMPONENTE SIDEBAR (OBBLIGATORIO) -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
    
    <!-- ✅ COMPONENTE HEADER (OBBLIGATORIO) -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
    
    <!-- Content Wrapper con padding top per header -->
    <div class="content-wrapper">
            
            <main class="main-content">
                <div class="form-container">
                    <div class="form-card">
                        <div class="form-header">
                            <h1 class="form-title">
                                <?= $pageIcon ?> Nuovo Cliente
                            </h1>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <strong>Errori nel form:</strong>
                                <ul style="margin: 0.5rem 0 0 1.5rem;">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="/crm/?action=clienti&view=create">
                            <!-- Sezione Dati Anagrafici -->
                            <div class="form-section">
                                <h2 class="section-title">📋 Dati Anagrafici</h2>
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="ragione_sociale" class="form-label required">Ragione Sociale</label>
                                        <input type="text" 
                                               id="ragione_sociale" 
                                               name="ragione_sociale" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['ragione_sociale'] ?? '') ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="tipologia_azienda" class="form-label required">Tipologia Azienda</label>
                                        <select id="tipologia_azienda" name="tipologia_azienda" class="form-select" required>
                                            <option value="">-- Seleziona --</option>
                                            <?php foreach ($tipologieAzienda as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= ($formData['tipologia_azienda'] ?? '') == $value ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="codice_fiscale" class="form-label">Codice Fiscale</label>
                                        <input type="text" 
                                               id="codice_fiscale" 
                                               name="codice_fiscale" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['codice_fiscale'] ?? '') ?>" 
                                               maxlength="16"
                                               style="text-transform: uppercase;">
                                        <div class="form-help">16 caratteri per persone fisiche</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="partita_iva" class="form-label">Partita IVA</label>
                                        <input type="text" 
                                               id="partita_iva" 
                                               name="partita_iva" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['partita_iva'] ?? '') ?>" 
                                               maxlength="11">
                                        <div class="form-help">11 cifre per aziende</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sezione Sede Legale -->
                            <div class="form-section">
                                <h2 class="section-title">🏢 Sede Legale</h2>
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="indirizzo" class="form-label">Indirizzo</label>
                                        <input type="text" 
                                               id="indirizzo" 
                                               name="indirizzo" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['indirizzo'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cap" class="form-label">CAP</label>
                                        <input type="text" 
                                               id="cap" 
                                               name="cap" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['cap'] ?? '') ?>" 
                                               maxlength="5">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="citta" class="form-label">Città</label>
                                        <input type="text" 
                                               id="citta" 
                                               name="citta" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['citta'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="provincia" class="form-label">Provincia</label>
                                        <input type="text" 
                                               id="provincia" 
                                               name="provincia" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['provincia'] ?? '') ?>" 
                                               maxlength="2"
                                               style="text-transform: uppercase;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sezione Contatti -->
                            <div class="form-section">
                                <h2 class="section-title">📞 Contatti</h2>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="telefono" class="form-label">Telefono</label>
                                        <input type="tel" 
                                               id="telefono" 
                                               name="telefono" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['telefono'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" 
                                               id="email" 
                                               name="email" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="pec" class="form-label">PEC</label>
                                        <input type="email" 
                                               id="pec" 
                                               name="pec" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['pec'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="codice_univoco" class="form-label">Codice Univoco SDI</label>
                                        <input type="text" 
                                               id="codice_univoco" 
                                               name="codice_univoco" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($formData['codice_univoco'] ?? '') ?>" 
                                               maxlength="7"
                                               style="text-transform: uppercase;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sezione Gestione -->
                            <div class="form-section">
                                <h2 class="section-title">⚙️ Gestione</h2>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="operatore_responsabile_id" class="form-label">Operatore Responsabile</label>
                                        <select id="operatore_responsabile_id" name="operatore_responsabile_id" class="form-select">
                                            <option value="">-- Non assegnato --</option>
                                            <?php foreach ($operatori as $operatore): ?>
                                                <option value="<?= $operatore['id'] ?>" <?= ($formData['operatore_responsabile_id'] ?? '') == $operatore['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($operatore['nome_completo']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="stato" class="form-label">Stato</label>
                                        <select id="stato" name="stato" class="form-select">
                                            <?php foreach ($statiDisponibili as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= ($formData['stato'] ?? 'attivo') == $value ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label for="note" class="form-label">Note</label>
                                        <textarea id="note" 
                                                  name="note" 
                                                  class="form-textarea" 
                                                  rows="3"><?= htmlspecialchars($formData['note'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Azioni -->
                            <div class="form-actions">
                                <a href="/crm/?action=clienti" class="btn-cancel">Annulla</a>
                                <button type="submit" class="btn-submit">
                                    💾 Salva Cliente
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>

    <!-- Script per validazioni e interazioni -->
    <script>
        // Auto-uppercase per campi specifici
        document.getElementById('codice_fiscale').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('provincia').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('codice_univoco').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Validazione tipologia e campi obbligatori
        document.getElementById('tipologia_azienda').addEventListener('change', function(e) {
            const tipo = e.target.value;
            const cfField = document.getElementById('codice_fiscale');
            const pivaField = document.getElementById('partita_iva');
            
            if (tipo === 'individuale') {
                cfField.parentElement.querySelector('.form-label').classList.add('required');
                pivaField.parentElement.querySelector('.form-label').classList.remove('required');
            } else if (tipo !== '') {
                cfField.parentElement.querySelector('.form-label').classList.remove('required');
                pivaField.parentElement.querySelector('.form-label').classList.add('required');
            }
        });
    </script>
    
    <!-- Script microinterazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
</body>
</html>