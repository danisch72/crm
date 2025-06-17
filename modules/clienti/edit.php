<?php
/**
 * modules/clienti/edit.php - Modifica Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE CON SIDEBAR E HEADER CENTRALIZZATI
 * 
 * Features:
 * - Form modifica cliente con validazioni
 * - Controllo permessi (admin o operatore responsabile)
 * - Storico modifiche
 * - Validazione CF/P.IVA italiana
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
$pageTitle = 'Modifica Cliente';
$pageIcon = '✏️';

// $clienteId già validato dal router
// Recupera dati cliente
$cliente = $db->selectOne("SELECT * FROM clienti WHERE id = ?", [$clienteId]);
if (!$cliente) {
    $_SESSION['error_message'] = '⚠️ Cliente non trovato';
    header('Location: /crm/?action=clienti');
    exit;
}

// Controllo permessi: admin o operatore responsabile
$canEdit = $sessionInfo['is_admin'] || 
           $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id'];

if (!$canEdit) {
    $_SESSION['error_message'] = '⚠️ Non hai i permessi per modificare questo cliente';
    header('Location: /crm/?action=clienti&view=view&id=' . $clienteId);
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
    'sospeso' => 'Sospeso',
    'chiuso' => 'Chiuso'
];

// Gestione form submission
$errors = [];
$formData = $cliente; // Usa dati esistenti come base

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
            // Verifica unicità CF/P.IVA (escludendo il cliente corrente)
            if (!empty($formData['codice_fiscale'])) {
                $existing = $db->selectOne(
                    "SELECT id FROM clienti WHERE codice_fiscale = ? AND id != ?", 
                    [$formData['codice_fiscale'], $clienteId]
                );
                if ($existing) {
                    $errors[] = "Codice fiscale già presente in archivio";
                }
            }
            
            if (!empty($formData['partita_iva'])) {
                $existing = $db->selectOne(
                    "SELECT id FROM clienti WHERE partita_iva = ? AND id != ?", 
                    [$formData['partita_iva'], $clienteId]
                );
                if ($existing) {
                    $errors[] = "Partita IVA già presente in archivio";
                }
            }
            
            if (empty($errors)) {
                // Prepara dati per update
                $updateData = $formData;
                $updateData['updated_by'] = $sessionInfo['operatore_id'];
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                
                // Aggiorna cliente
                $success = $db->update('clienti', $updateData, 'id = ?', [$clienteId]);
                
                if ($success) {
                    // Log modifiche (opzionale)
                    if (function_exists('logClienteUpdate')) {
                        logClienteUpdate($clienteId, $cliente, $updateData, $sessionInfo['operatore_id']);
                    }
                    
                    $_SESSION['success_message'] = '✅ Cliente aggiornato con successo';
                    header('Location: /crm/?action=clienti&view=view&id=' . $clienteId);
                    exit;
                } else {
                    $errors[] = "Errore durante l'aggiornamento del cliente";
                }
            }
        } catch (Exception $e) {
            error_log("Errore aggiornamento cliente: " . $e->getMessage());
            $errors[] = "Errore di sistema durante l'aggiornamento";
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
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
        }
        
        .form-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .cliente-info {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.25rem;
        }
        
        .form-label.required::after {
            content: " *";
            color: var(--danger-red);
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            background: white;
            cursor: pointer;
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .form-actions-left {
            display: flex;
            gap: 1rem;
        }
        
        .form-actions-right {
            display: flex;
            gap: 1rem;
        }
        
        .btn-submit {
            background: var(--primary-green);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-submit:hover {
            background: var(--primary-green-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-cancel {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 0.75rem 2rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        
        .btn-cancel:hover {
            background: var(--gray-300);
        }
        
        .btn-danger {
            background: var(--color-danger);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-fast);
            font-size: 0.875rem;
        }
        
        .btn-danger:hover {
            background: var(--color-danger-dark);
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-danger {
            background: var(--color-danger-light);
            color: var(--color-danger);
            border: 1px solid var(--color-danger);
        }
        
        .form-help {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        .last-update {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 1rem;
            text-align: right;
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
                    <div class="form-card">
                        <div class="form-header">
                            <div>
                                <h1 class="form-title">
                                    <?= $pageIcon ?> Modifica Cliente
                                </h1>
                                <div class="cliente-info">
                                    ID: #<?= str_pad($cliente['id'], 6, '0', STR_PAD_LEFT) ?>
                                    <?php if ($cliente['created_at']): ?>
                                        • Creato il <?= date('d/m/Y', strtotime($cliente['created_at'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>" 
                                   class="btn-cancel" 
                                   style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                                    👁️ Visualizza
                                </a>
                            </div>
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
                        
                        <form method="POST" action="/crm/?action=clienti&view=edit&id=<?= $clienteId ?>">
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
                            
                            <!-- Info ultima modifica -->
                            <?php if ($cliente['updated_at']): ?>
                                <div class="last-update">
                                    Ultima modifica: <?= date('d/m/Y H:i', strtotime($cliente['updated_at'])) ?>
                                    <?php if ($cliente['updated_by']): ?>
                                        <?php
                                        $updater = $db->selectOne(
                                            "SELECT CONCAT(nome, ' ', cognome) as nome FROM operatori WHERE id = ?",
                                            [$cliente['updated_by']]
                                        );
                                        if ($updater) {
                                            echo " da " . htmlspecialchars($updater['nome']);
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Azioni -->
                            <div class="form-actions">
                                <div class="form-actions-left">
                                    <?php if ($sessionInfo['is_admin']): ?>
                                        <a href="/crm/?action=clienti&view=delete&id=<?= $clienteId ?>" 
                                           class="btn-danger"
                                           onclick="return confirm('⚠️ Sei sicuro di voler eliminare questo cliente? L\'operazione non può essere annullata.')">
                                            🗑️ Elimina
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="form-actions-right">
                                    <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>" class="btn-cancel">Annulla</a>
                                    <button type="submit" class="btn-submit">
                                        💾 Salva Modifiche
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
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
        
        // Trigger change event on load
        document.getElementById('tipologia_azienda').dispatchEvent(new Event('change'));
    </script>
    
    <!-- Script microinterazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
</body>
</html>