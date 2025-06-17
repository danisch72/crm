<?php
/**
 * modules/clienti/edit.php - Modifica Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON TUTTI I CAMPI DATABASE
 * ✅ LAYOUT ULTRA-COMPATTO DATEV OPTIMAL
 * 
 * Features:
 * - Form modifica con validazioni
 * - Controllo permessi (admin o operatore responsabile)
 * - Storico modifiche
 * - Design Datev Optimal
 * - NUOVO: regime_fiscale, liquidazione_iva, note_generali
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
        'operatore_responsabile_id' => (int)($_POST['operatore_responsabile_id'] ?? $cliente['operatore_responsabile_id']),
        'stato' => $_POST['stato'] ?? 'attivo',
        'regime_fiscale' => $_POST['regime_fiscale'] ?? '',
        'liquidazione_iva' => $_POST['liquidazione_iva'] ?? '',
        'note' => trim($_POST['note'] ?? ''),
        'note_generali' => trim($_POST['note_generali'] ?? '')
    ];
    
    // Validazioni
    if (empty($formData['ragione_sociale'])) {
        $errors[] = "Ragione sociale obbligatoria";
    }
    
    if (empty($formData['tipologia_azienda'])) {
        $errors[] = "Tipologia azienda obbligatoria";
    }
    
    // Validazione condizionale CF/P.IVA
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
    
    // Validazioni formato
    if (!empty($formData['cap']) && !preg_match('/^[0-9]{5}$/', $formData['cap'])) {
        $errors[] = "CAP non valido (5 cifre)";
    }
    
    if (!empty($formData['provincia']) && !preg_match('/^[A-Z]{2}$/', $formData['provincia'])) {
        $errors[] = "Provincia non valida (2 lettere)";
    }
    
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida";
    }
    
    if (!empty($formData['pec']) && !filter_var($formData['pec'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "PEC non valida";
    }
    
    // Se non ci sono errori, procedi con l'aggiornamento
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
    <title><?= $pageTitle ?> - <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-optimal.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Stili identici a create.php */
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
        
        .form-subtitle {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.25rem;
        }
        
        .form-label-required::after {
            content: ' *';
            color: var(--danger-red);
        }
        
        .form-control {
            width: 100%;
            padding: 0.375rem 0.625rem;
            font-size: 0.8125rem;
            line-height: 1.4;
            color: var(--gray-900);
            background-color: white;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            transition: all 0.15s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .form-control:disabled {
            background-color: var(--gray-50);
            cursor: not-allowed;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .form-actions-left {
            display: flex;
            gap: 0.75rem;
        }
        
        .error-list {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .error-list ul {
            margin: 0;
            padding-left: 1.25rem;
            font-size: 0.8125rem;
            color: #dc2626;
        }
        
        .form-help {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        .metadata-info {
            background-color: var(--gray-50);
            border-radius: 4px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            color: var(--gray-600);
        }
    </style>
</head>
<body class="datev-body">
    <div class="datev-container">
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
                                <span>Modifica Cliente</span>
                            </h1>
                            <div class="form-subtitle">
                                ID: #<?= str_pad($clienteId, 6, '0', STR_PAD_LEFT) ?> • 
                                Cliente dal <?= date('d/m/Y', strtotime($cliente['created_at'])) ?>
                            </div>
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
                                           required>
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
                                    <label class="form-label" id="labelCodiceFiscale">Codice Fiscale</label>
                                    <input type="text" 
                                           name="codice_fiscale" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['codice_fiscale']) ?>"
                                           maxlength="16"
                                           style="text-transform: uppercase;">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" id="labelPartitaIva">Partita IVA</label>
                                    <input type="text" 
                                           name="partita_iva" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($formData['partita_iva']) ?>"
                                           maxlength="11">
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
                                
                                <div class="form-group">
                                    <label class="form-label">Stato</label>
                                    <select name="stato" class="form-control">
                                        <?php foreach ($statiDisponibili as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $formData['stato'] === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dati Fiscali -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>💰</span>
                                <span>Dati Fiscali</span>
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Regime Fiscale</label>
                                    <select name="regime_fiscale" class="form-control">
                                        <option value="">Seleziona...</option>
                                        <option value="ordinario" <?= $formData['regime_fiscale'] === 'ordinario' ? 'selected' : '' ?>>Ordinario</option>
                                        <option value="semplificato" <?= $formData['regime_fiscale'] === 'semplificato' ? 'selected' : '' ?>>Semplificato</option>
                                        <option value="forfettario" <?= $formData['regime_fiscale'] === 'forfettario' ? 'selected' : '' ?>>Forfettario</option>
                                        <option value="altro" <?= $formData['regime_fiscale'] === 'altro' ? 'selected' : '' ?>>Altro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Liquidazione IVA</label>
                                    <select name="liquidazione_iva" class="form-control">
                                        <option value="">Seleziona...</option>
                                        <option value="mensile" <?= $formData['liquidazione_iva'] === 'mensile' ? 'selected' : '' ?>>Mensile</option>
                                        <option value="trimestrale" <?= $formData['liquidazione_iva'] === 'trimestrale' ? 'selected' : '' ?>>Trimestrale</option>
                                        <option value="fuori campo" <?= $formData['liquidazione_iva'] === 'fuori campo' ? 'selected' : '' ?>>Fuori Campo IVA</option>
                                        <option value="esente" <?= $formData['liquidazione_iva'] === 'esente' ? 'selected' : '' ?>>Esente IVA</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contatti -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>📍</span>
                                <span>Sede e Contatti</span>
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
                        
                        <!-- Note Generali -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <span>💬</span>
                                <span>Note Generali</span>
                            </h3>
                            
                            <div class="form-group">
                                <textarea name="note_generali" 
                                          class="form-control" 
                                          rows="2"
                                          placeholder="Commenti generali sulla ditta..."><?= htmlspecialchars($formData['note_generali']) ?></textarea>
                                <div class="form-help">Visibile a tutti gli operatori</div>
                            </div>
                        </div>
                        
                        <!-- Metadata -->
                        <?php if ($cliente['updated_at']): ?>
                        <div class="metadata-info">
                            Ultima modifica: <?= date('d/m/Y H:i', strtotime($cliente['updated_at'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Azioni -->
                        <div class="form-actions">
                            <div class="form-actions-left">
                                <?php if ($sessionInfo['is_admin']): ?>
                                    <a href="/crm/?action=clienti&view=delete&id=<?= $clienteId ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('⚠️ Sei sicuro di voler eliminare questo cliente? L\'operazione non può essere annullata.')">
                                        <span>🗑️ Elimina</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 0.75rem;">
                                <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>" class="btn btn-secondary">
                                    <span>← Annulla</span>
                                </a>
                                
                                <button type="submit" class="btn btn-primary">
                                    <span>💾 Salva Modifiche</span>
                                </button>
                            </div>
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
    
    // Validazione dinamica regime fiscale e IVA
    document.querySelector('select[name="regime_fiscale"]').addEventListener('change', function() {
        const liquidazioneSelect = document.querySelector('select[name="liquidazione_iva"]');
        
        // Se forfettario, preseleziona "fuori campo"
        if (this.value === 'forfettario') {
            liquidazioneSelect.value = 'fuori campo';
        }
    });
    </script>
</body>
</html>

<?php
// Funzioni di validazione (identiche a create.php)
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
            $sum += $double > 9 ? ($double - 9) : $double;
        }
    }
    
    return ($sum % 10) == 0;
}
?>