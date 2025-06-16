<?php
/**
 * modules/clienti/create.php - Creazione Cliente CRM Re.De Consulting
 * 
 * ✅ FORM CREAZIONE CLIENTE - LAYOUT UNIFORME DATEV
 * 
 * Features:
 * - Form completo per nuovo cliente
 * - Validazione business rules commercialisti
 * - Auto-generazione codice cliente
 * - Validazione CF/P.IVA
 * - Layout uniforme con altri moduli
 */

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message

$pageTitle = 'Nuovo Cliente';

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

// Regimi fiscali disponibili
$regimiFiscali = [
    'ordinario' => 'Regime Ordinario',
    'semplificato' => 'Regime Semplificato',
    'forfettario' => 'Regime Forfettario'
];

// Gestione form submission
$errors = [];
$formData = []; // Per mantenere i dati in caso di errore

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Raccogli tutti i dati del form
        $formData = [
            'ragione_sociale' => trim($_POST['ragione_sociale'] ?? ''),
            'tipologia_azienda' => $_POST['tipologia_azienda'] ?? '',
            'codice_fiscale' => strtoupper(trim($_POST['codice_fiscale'] ?? '')),
            'partita_iva' => trim($_POST['partita_iva'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'cellulare' => trim($_POST['cellulare'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'pec' => trim($_POST['pec'] ?? ''),
            'indirizzo' => trim($_POST['indirizzo'] ?? ''),
            'cap' => trim($_POST['cap'] ?? ''),
            'citta' => trim($_POST['citta'] ?? ''),
            'provincia' => strtoupper(trim($_POST['provincia'] ?? '')),
            'regime_fiscale' => $_POST['regime_fiscale'] ?? 'ordinario',
            'settore_attivita' => trim($_POST['settore_attivita'] ?? ''),
            'codice_ateco' => trim($_POST['codice_ateco'] ?? ''),
            'operatore_responsabile_id' => $_POST['operatore_responsabile_id'] ?: null,
            'note' => trim($_POST['note'] ?? '')
        ];
        
        // Validazioni
        if (empty($formData['ragione_sociale'])) {
            $errors[] = "La ragione sociale è obbligatoria";
        }
        
        if (empty($formData['tipologia_azienda'])) {
            $errors[] = "La tipologia azienda è obbligatoria";
        }
        
        // Validazione CF/P.IVA in base al tipo
        if ($formData['tipologia_azienda'] === 'individuale') {
            if (empty($formData['codice_fiscale'])) {
                $errors[] = "Il codice fiscale è obbligatorio per le ditte individuali";
            } elseif (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i', $formData['codice_fiscale'])) {
                $errors[] = "Il codice fiscale non è valido";
            }
        } else {
            if (empty($formData['partita_iva'])) {
                $errors[] = "La partita IVA è obbligatoria per le società";
            } elseif (!preg_match('/^[0-9]{11}$/', $formData['partita_iva'])) {
                $errors[] = "La partita IVA deve contenere 11 cifre";
            }
        }
        
        // Validazione email
        if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'email non è valida";
        }
        
        if (!empty($formData['pec']) && !filter_var($formData['pec'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "La PEC non è valida";
        }
        
        // Validazione CAP
        if (!empty($formData['cap']) && !preg_match('/^[0-9]{5}$/', $formData['cap'])) {
            $errors[] = "Il CAP deve contenere 5 cifre";
        }
        
        // Validazione provincia
        if (!empty($formData['provincia']) && !preg_match('/^[A-Z]{2}$/', $formData['provincia'])) {
            $errors[] = "La provincia deve contenere 2 lettere";
        }
        
        // Verifica duplicati
        if (empty($errors)) {
            if (!empty($formData['partita_iva'])) {
                $existing = $db->selectOne("SELECT id FROM clienti WHERE partita_iva = ?", [$formData['partita_iva']]);
                if ($existing) {
                    $errors[] = "Esiste già un cliente con questa partita IVA";
                }
            }
            
            if (!empty($formData['codice_fiscale'])) {
                $existing = $db->selectOne("SELECT id FROM clienti WHERE codice_fiscale = ?", [$formData['codice_fiscale']]);
                if ($existing) {
                    $errors[] = "Esiste già un cliente con questo codice fiscale";
                }
            }
        }
        
        // Se non ci sono errori, procedi con l'inserimento
        if (empty($errors)) {
            // Genera codice cliente
            $codiceCliente = generateCodiceCliente($formData['tipologia_azienda'], $db);
            
            // Prepara dati per inserimento
            $insertData = $formData;
            $insertData['codice_cliente'] = $codiceCliente;
            $insertData['stato'] = 'attivo';
            $insertData['created_at'] = date('Y-m-d H:i:s');
            $insertData['created_by'] = $sessionInfo['operatore_id'];
            
            // Rimuovi campi vuoti per evitare problemi con NULL
            foreach ($insertData as $key => $value) {
                if ($value === '') {
                    $insertData[$key] = null;
                }
            }
            
            // Inserisci nel database
            $db->insert('clienti', $insertData);
            $clienteId = $db->lastInsertId();
            
            // Redirect con successo
            $_SESSION['success_message'] = '✅ Cliente creato con successo!';
            header('Location: /crm/?action=clienti&view=view&id=' . $clienteId);
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Errore creazione cliente: " . $e->getMessage());
        $errors[] = "Errore durante il salvataggio. Riprova più tardi.";
    }
} else {
    // Prima apertura del form - inizializza array vuoto
    $formData = [
        'ragione_sociale' => '',
        'tipologia_azienda' => '',
        'codice_fiscale' => '',
        'partita_iva' => '',
        'telefono' => '',
        'cellulare' => '',
        'email' => '',
        'pec' => '',
        'indirizzo' => '',
        'cap' => '',
        'citta' => '',
        'provincia' => '',
        'regime_fiscale' => 'ordinario',
        'settore_attivita' => '',
        'codice_ateco' => '',
        'operatore_responsabile_id' => '',
        'note' => ''
    ];
}

// Funzione per generare codice cliente
function generateCodiceCliente($tipologia, $db) {
    $prefisso = match($tipologia) {
        'individuale' => 'DI',
        'srl' => 'SR',
        'spa' => 'SA',
        'snc' => 'SN',
        'sas' => 'SS',
        default => 'CL'
    };
    
    $anno = date('Y');
    
    // Trova ultimo numero progressivo
    $ultimoCodice = $db->selectOne("
        SELECT codice_cliente 
        FROM clienti 
        WHERE codice_cliente LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ", [$prefisso . $anno . '%']);
    
    if ($ultimoCodice) {
        $ultimoNumero = (int)substr($ultimoCodice['codice_cliente'], -4);
        $nuovoNumero = $ultimoNumero + 1;
    } else {
        $nuovoNumero = 1;
    }
    
    return $prefisso . $anno . str_pad($nuovoNumero, 4, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Nuovo Cliente - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <!-- Form Styles Ultra-Compatti -->
    <style>
        /* Form Container */
        .form-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        /* Form Section */
        .form-section {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Form Grid Ultra-Compatto */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-label .required {
            color: var(--danger-red);
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            height: 32px;
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: border-color var(--transition-fast);
        }
        
        .form-textarea {
            height: 80px;
            resize: vertical;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        /* Info Box */
        .info-box {
            background: var(--green-50);
            border: 1px solid var(--green-200);
            border-radius: var(--radius-md);
            padding: 0.5rem 0.75rem;
            margin: 0.75rem 0;
            font-size: 0.8125rem;
            color: var(--green-700);
        }
        
        /* Form Actions */
        .form-actions {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Error Container */
        .error-container {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .error-container ul {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }
        
        /* Buttons Compatti */
        .btn-primary-compact,
        .btn-secondary-compact,
        .btn-outline-compact {
            height: 32px;
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-primary-compact {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary-compact:hover {
            background: var(--secondary-green);
        }
        
        .btn-secondary-compact {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary-compact:hover {
            background: var(--gray-300);
        }
        
        .btn-outline-compact {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-outline-compact:hover {
            background: var(--gray-50);
        }
        
        /* Breadcrumb Compatto */
        .breadcrumb {
            padding: 0.5rem 0;
            margin-bottom: 0.75rem;
            font-size: 0.8125rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .header-actions .btn-secondary-compact {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar uniforme identica al modulo operatori -->
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
                    <a href="/crm/modules/clienti/index.php" class="nav-link nav-link-active">
                        <span>🏢</span> Clienti
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/pratiche/index.php" class="nav-link">
                        <span>📋</span> Pratiche
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/scadenze/index.php" class="nav-link">
                        <span>⏰</span> Scadenze
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=clienti">Clienti</a> / 
            <span>Nuovo Cliente</span>
        </div>
        
        <!-- Header Section -->
        <div class="main-header">
            <div class="header-title">
                <h1>➕ Nuovo Cliente</h1>
                <p class="header-subtitle">Inserimento nuovo cliente portfolio</p>
            </div>
            
            <div class="header-actions">
                <a href="/crm/?action=clienti" class="btn-secondary-compact">
                    ← Torna alla Lista
                </a>
                <a href="/crm/?action=dashboard" class="btn-outline-compact">
                    🏠 Dashboard
                </a>
            </div>
        </div>
        
        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <strong>⚠️ Correggere i seguenti errori:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Form -->
        <form method="POST" action="/crm/?action=clienti&view=create" class="form-container">
            <!-- Dati Principali -->
            <section class="form-section">
                <h2 class="section-title">📋 Dati Principali</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            Ragione Sociale <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="ragione_sociale" 
                               class="form-input" 
                               value="<?= htmlspecialchars($formData['ragione_sociale']) ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Tipologia Azienda <span class="required">*</span>
                        </label>
                        <select name="tipologia_azienda" class="form-select" required>
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($tipologieAzienda as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $formData['tipologia_azienda'] === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="info-box">
                    ℹ️ Per le ditte individuali è richiesto il Codice Fiscale, per le società la Partita IVA
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" id="cf-label">
                            Codice Fiscale
                        </label>
                        <input type="text" 
                               name="codice_fiscale" 
                               class="form-input" 
                               value="<?= htmlspecialchars($formData['codice_fiscale']) ?>"
                               maxlength="16"
                               style="text-transform: uppercase;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" id="piva-label">
                            Partita IVA
                        </label>
                        <input type="text" 
                               name="partita_iva" 
                               class="form-input" 
                               value="<?= htmlspecialchars($formData['partita_iva']) ?>"
                               maxlength="11">
                    </div>
                </div>
            </section>
            
            <!-- Contatti -->
            <section class="form-section">
                <h2 class="section-title">📞 Contatti</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Telefono</label>
                        <input type="tel" 
                               name="telefono" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['telefono']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cellulare</label>
                        <input type="tel" 
                               name="cellulare" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['cellulare']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" 
                               name="email" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">PEC</label>
                        <input type="email" 
                               name="pec" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['pec']) ?>">
                    </div>
                </div>
            </section>
            
            <!-- Sede Legale -->
            <section class="form-section">
                <h2 class="section-title">📍 Sede Legale</h2>
                
                <div class="form-group">
                    <label class="form-label">Indirizzo</label>
                    <input type="text" 
                           name="indirizzo" 
                           class="form-input"
                           value="<?= htmlspecialchars($formData['indirizzo']) ?>">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">CAP</label>
                        <input type="text" 
                               name="cap" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['cap']) ?>"
                               maxlength="5">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Città</label>
                        <input type="text" 
                               name="citta" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['citta']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Provincia</label>
                        <input type="text" 
                               name="provincia" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['provincia']) ?>"
                               maxlength="2"
                               style="text-transform: uppercase;">
                    </div>
                </div>
            </section>
            
            <!-- Dati Fiscali -->
            <section class="form-section">
                <h2 class="section-title">💰 Dati Fiscali e Attività</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Regime Fiscale</label>
                        <select name="regime_fiscale" class="form-select">
                            <?php foreach ($regimiFiscali as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $formData['regime_fiscale'] === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Settore Attività</label>
                        <input type="text" 
                               name="settore_attivita" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['settore_attivita']) ?>"
                               placeholder="es. Commercio, Servizi, Produzione">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Codice ATECO</label>
                        <input type="text" 
                               name="codice_ateco" 
                               class="form-input"
                               value="<?= htmlspecialchars($formData['codice_ateco']) ?>"
                               placeholder="es. 47.11.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Operatore Responsabile</label>
                        <select name="operatore_responsabile_id" class="form-select">
                            <option value="">-- Non assegnato --</option>
                            <?php foreach ($operatori as $op): ?>
                                <option value="<?= $op['id'] ?>" <?= $formData['operatore_responsabile_id'] == $op['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>
            
            <!-- Note -->
            <section class="form-section">
                <h2 class="section-title">📝 Note</h2>
                
                <div class="form-group">
                    <label class="form-label">Note aggiuntive</label>
                    <textarea name="note" 
                              class="form-textarea" 
                              rows="4"
                              placeholder="Inserisci eventuali note o informazioni aggiuntive..."><?= htmlspecialchars($formData['note']) ?></textarea>
                </div>
            </section>
            
            <!-- Actions -->
            <div class="form-actions">
                <a href="/crm/?action=clienti" class="btn-outline-compact">
                    Annulla
                </a>
                <button type="submit" class="btn-primary-compact">
                    💾 Salva Cliente
                </button>
            </div>
        </form>
    </main>
    
    <!-- JavaScript per Interazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
    <script>
        // Auto uppercase per campi specifici
        document.querySelector('[name="codice_fiscale"]').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        document.querySelector('[name="provincia"]').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Gestione dinamica label CF/P.IVA
        document.querySelector('[name="tipologia_azienda"]').addEventListener('change', function() {
            const isIndividuale = this.value === 'individuale';
            const cfLabel = document.getElementById('cf-label');
            const pivaLabel = document.getElementById('piva-label');
            const cfInput = document.querySelector('[name="codice_fiscale"]');
            const pivaInput = document.querySelector('[name="partita_iva"]');
            
            if (isIndividuale) {
                cfLabel.innerHTML = 'Codice Fiscale <span class="required">*</span>';
                pivaLabel.innerHTML = 'Partita IVA';
                cfInput.required = true;
                pivaInput.required = false;
            } else if (this.value) {
                cfLabel.innerHTML = 'Codice Fiscale';
                pivaLabel.innerHTML = 'Partita IVA <span class="required">*</span>';
                cfInput.required = false;
                pivaInput.required = true;
            } else {
                cfLabel.innerHTML = 'Codice Fiscale';
                pivaLabel.innerHTML = 'Partita IVA';
                cfInput.required = false;
                pivaInput.required = false;
            }
        });
        
        // Trigger change event on load per impostare correttamente i required
        document.querySelector('[name="tipologia_azienda"]').dispatchEvent(new Event('change'));
    </script>
</body>
</html>