<?php
/**
 * modules/clienti/create.php - Creazione Nuovo Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE DEFINITIVA CON COMPONENTI CENTRALIZZATI
 * ✅ LAYOUT ULTRA-COMPATTO DATEV OPTIMAL
 * 
 * Features:
 * - Form creazione con validazioni
 * - Layout compatto a 2 colonne
 * - Validazione CF/P.IVA in tempo reale
 * - Design Datev Optimal
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

// Gestione form submission
$errors = [];
$formData = [
    'ragione_sociale' => '',
    'tipologia_azienda' => 'individuale',
    'codice_fiscale' => '',
    'partita_iva' => '',
    'indirizzo' => '',
    'cap' => '',
    'citta' => '',
    'provincia' => '',
    'telefono' => '',
    'email' => '',
    'pec' => '',
    'operatore_responsabile_id' => $sessionInfo['operatore_id'],
    'note' => ''
];

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
           'operatore_responsabile_id' => (int)($_POST['operatore_responsabile_id'] ?? $sessionInfo['operatore_id']),
        'note' => trim($_POST['note'] ?? '')
    ];
    
    // Validazioni
    if (empty($formData['ragione_sociale'])) {
        $errors[] = "Ragione sociale obbligatoria";
    }
    
    if (empty($formData['tipologia_azienda'])) {
        $errors[] = "Tipologia azienda obbligatoria";
    }
    
    // Validazione CF/P.IVA in base a tipologia
    if ($formData['tipologia_azienda'] === 'individuale') {
        if (empty($formData['codice_fiscale'])) {
            $errors[] = "Codice fiscale obbligatorio per ditte individuali";
        } elseif (!isValidCodiceFiscale($formData['codice_fiscale'])) {
            $errors[] = "Codice fiscale non valido";
        }
    } else {
        if (empty($formData['partita_iva'])) {
            $errors[] = "Partita IVA obbligatoria per società";
        } elseif (!isValidPartitaIva($formData['partita_iva'])) {
            $errors[] = "Partita IVA non valida";
        }
    }
    
    if (!empty($formData['cap']) && !preg_match('/^\d{5}$/', $formData['cap'])) {
        $errors[] = "CAP non valido (5 cifre)";
    }
    
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida";
    }
    
    if (!empty($formData['pec']) && !filter_var($formData['pec'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "PEC non valida";
    }
    
    // Verifica unicità CF/P.IVA
    if (empty($errors)) {
        try {
            if (!empty($formData['codice_fiscale'])) {
                $existing = $db->selectOne(
                    "SELECT id FROM clienti WHERE codice_fiscale = ?", 
                    [$formData['codice_fiscale']]
                );
                if ($existing) {
                    $errors[] = "Codice fiscale già presente in archivio";
                }
            }
            
            if (!empty($formData['partita_iva'])) {
                $existing = $db->selectOne(
                    "SELECT id FROM clienti WHERE partita_iva = ?", 
                    [$formData['partita_iva']]
                );
                if ($existing) {
                    $errors[] = "Partita IVA già presente in archivio";
                }
            }
            
            if (empty($errors)) {
                // Prepara dati per inserimento
                $insertData = $formData;
                $insertData['stato'] = 'attivo';
                $insertData['created_by'] = $sessionInfo['operatore_id'];
                $insertData['created_at'] = date('Y-m-d H:i:s');
                
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
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Container uniforme con index_list */
        .container {
            padding: 1rem;
            max-width: 100%;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1.25rem;
            width: 100%;
            max-width: none;
        }
        
        .form-header {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        
        .form-section {
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-size: 0.813rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .form-group {
            margin-bottom: 0.625rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-label-required::after {
            content: ' *';
            color: var(--color-danger);
        }
        
        .form-control {
            width: 100%;
            padding: 0.375rem 0.625rem;
            font-size: 0.813rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(0, 120, 73, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-hint {
            font-size: 0.688rem;
            color: var(--gray-500);
            margin-top: 0.125rem;
        }
        
        .form-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .error-list {
            background: var(--color-danger-light);
            border: 1px solid var(--color-danger);
            border-radius: 4px;
            padding: 0.625rem;
            margin-bottom: 0.75rem;
        }
        
        .error-list ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        .error-list li {
            color: var(--color-danger);
            font-size: 0.75rem;
            line-height: 1.3;
        }
        
        /* Grid responsive più ampio */
        @media (min-width: 1200px) {
            .form-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
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
                <div class="container">
                    <form method="POST" class="form-card">
                        <div class="form-header">
                            <h1 class="form-title">
                                <span><?= $pageIcon ?></span>
                                <span>Creazione Nuovo Cliente</span>
                            </h1>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="error-list">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Dati principali -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>📋</span>
                                <span>Dati Principali</span>
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label form-label-required">Ragione Sociale</label>
                                    <input type="text" 
                                           name="ragione_sociale" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['ragione_sociale']) ?>"
                                           required
                                           autofocus>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label form-label-required">Tipologia Azienda</label>
                                    <select name="tipologia_azienda" 
                                            class="form-control" 
                                            id="tipologiaAzienda"
                                            required>
                                        <?php foreach ($tipologieAzienda as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $formData['tipologia_azienda'] === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Operatore Responsabile</label>
                                    <select name="operatore_responsabile_id" class="form-control">
                                        <?php foreach ($operatori as $op): ?>
                                            <option value="<?= $op['id'] ?>" <?= $formData['operatore_responsabile_id'] == $op['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($op['nome_completo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dati fiscali -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>🧾</span>
                                <span>Dati Fiscali</span>
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" id="labelCodiceFiscale">Codice Fiscale</label>
                                    <input type="text" 
                                           name="codice_fiscale" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['codice_fiscale']) ?>"
                                           maxlength="16"
                                           style="text-transform: uppercase;">
                                    <div class="form-hint">16 caratteri per persone fisiche</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" id="labelPartitaIva">Partita IVA</label>
                                    <input type="text" 
                                           name="partita_iva" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['partita_iva']) ?>"
                                           maxlength="11">
                                    <div class="form-hint">11 cifre numeriche</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recapiti -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>📍</span>
                                <span>Recapiti e Contatti</span>
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label">Indirizzo</label>
                                    <input type="text" 
                                           name="indirizzo" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['indirizzo']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">CAP</label>
                                    <input type="text" 
                                           name="cap" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['cap']) ?>"
                                           maxlength="5">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Città</label>
                                    <input type="text" 
                                           name="citta" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['citta']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Provincia</label>
                                    <input type="text" 
                                           name="provincia" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['provincia']) ?>"
                                           maxlength="2"
                                           style="text-transform: uppercase;">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Telefono</label>
                                    <input type="tel" 
                                           name="telefono" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['telefono']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['email']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">PEC</label>
                                    <input type="email" 
                                           name="pec" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['pec']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Note -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>📝</span>
                                <span>Note Aggiuntive</span>
                            </h3>
                            
                            <div class="form-group">
                                <textarea name="note" 
                                          class="form-control" 
                                          rows="2"
                                          placeholder="Eventuali note o informazioni aggiuntive..."><?= htmlspecialchars($formData['note']) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Azioni -->
                        <div class="form-actions">
                            <a href="/crm/?action=clienti" class="btn btn-secondary">
                                <span>← Annulla</span>
                            </a>
                            
                            <button type="submit" class="btn btn-primary">
                                <span>💾 Salva Cliente</span>
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    // Gestione dinamica campi fiscali
    document.getElementById('tipologiaAzienda').addEventListener('change', function() {
        const isIndividuale = this.value === 'individuale';
        const cfLabel = document.getElementById('labelCodiceFiscale');
        const pivaLabel = document.getElementById('labelPartitaIva');
        
        if (isIndividuale) {
            cfLabel.classList.add('form-label-required');
            pivaLabel.classList.remove('form-label-required');
        } else {
            cfLabel.classList.remove('form-label-required');
            pivaLabel.classList.add('form-label-required');
        }
    });
    
    // Trigger iniziale
    document.getElementById('tipologiaAzienda').dispatchEvent(new Event('change'));
    
    // Auto uppercase
    document.querySelectorAll('input[style*="text-transform: uppercase"]').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });
    </script>
</body>
</html>

<?php
// Funzioni di validazione
function isValidCodiceFiscale($cf) {
    $cf = strtoupper(trim($cf));
    
    // Controllo lunghezza
    if (strlen($cf) != 16 && strlen($cf) != 11) {
        return false;
    }
    
    // CF persona fisica (16 caratteri)
    if (strlen($cf) == 16) {
        return preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf);
    }
    
    // CF persona giuridica (11 caratteri numerici)
    return preg_match('/^[0-9]{11}$/', $cf);
}

function isValidPartitaIva($piva) {
    $piva = trim($piva);
    
    // Deve essere 11 cifre
    if (!preg_match('/^[0-9]{11}$/', $piva)) {
        return false;
    }
    
    // Algoritmo di controllo partita IVA italiana
    $sum = 0;
    for ($i = 0; $i < 11; $i++) {
        $digit = (int)$piva[$i];
        if ($i % 2 == 0) {
            $sum += $digit;
        } else {
            $double = $digit * 2;
            $sum += $double > 9 ? $double - 9 : $double;
        }
    }
    
    return $sum % 10 == 0;
}
?>