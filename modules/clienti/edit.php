<?php
/**
 * modules/clienti/edit.php - Modifica Cliente CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON ROUTER
 */

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message
// $clienteId (validato dal router)

$pageTitle = 'Modifica Cliente';

// Recupera dati cliente
$cliente = $db->selectOne("SELECT * FROM clienti WHERE id = ?", [$clienteId]);
if (!$cliente) {
    header('Location: /crm/?action=clienti&error=not_found');
    exit;
}

// Controllo permessi: admin o operatore responsabile
$canEdit = $sessionInfo['is_admin'] || 
           $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id'];

if (!$canEdit) {
    header('Location: /crm/?action=clienti&error=permissions');
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
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validazione campi obbligatori
        $ragioneSociale = trim($_POST['ragione_sociale'] ?? '');
        $tipologiaAzienda = $_POST['tipologia_azienda'] ?? '';
        $codiceFiscale = trim($_POST['codice_fiscale'] ?? '');
        $partitaIva = trim($_POST['partita_iva'] ?? '');
        
        // Validazioni base
        if (empty($ragioneSociale)) {
            $errors[] = "La ragione sociale è obbligatoria";
        }
        
        if (empty($tipologiaAzienda)) {
            $errors[] = "La tipologia azienda è obbligatoria";
        }
        
        // Validazione CF/P.IVA in base al tipo
        if ($tipologiaAzienda === 'individuale') {
            if (empty($codiceFiscale)) {
                $errors[] = "Il codice fiscale è obbligatorio per le ditte individuali";
            } elseif (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i', $codiceFiscale)) {
                $errors[] = "Il codice fiscale non è valido";
            }
        } else {
            if (empty($partitaIva)) {
                $errors[] = "La partita IVA è obbligatoria per le società";
            } elseif (!preg_match('/^[0-9]{11}$/', $partitaIva)) {
                $errors[] = "La partita IVA deve contenere 11 cifre";
            }
        }
        
        // Altri campi
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($_POST['email'] && !$email) {
            $errors[] = "L'email non è valida";
        }
        
        // Verifica duplicati (escludendo cliente corrente)
        if (empty($errors)) {
            if ($partitaIva) {
                $existing = $db->selectOne(
                    "SELECT id FROM clienti WHERE partita_iva = ? AND id != ?", 
                    [$partitaIva, $clienteId]
                );
                if ($existing) {
                    $errors[] = "Esiste già un altro cliente con questa partita IVA";
                }
            }
            
            if ($codiceFiscale) {
                $existing = $db->selectOne(
                    "SELECT id FROM clienti WHERE codice_fiscale = ? AND id != ?", 
                    [$codiceFiscale, $clienteId]
                );
                if ($existing) {
                    $errors[] = "Esiste già un altro cliente con questo codice fiscale";
                }
            }
        }
        
        // Se non ci sono errori, procedi con l'aggiornamento
        if (empty($errors)) {
            // Prepara dati per aggiornamento
            $updateData = [
                'ragione_sociale' => $ragioneSociale,
                'tipologia_azienda' => $tipologiaAzienda,
                'codice_fiscale' => $codiceFiscale ?: null,
                'partita_iva' => $partitaIva ?: null,
                'indirizzo_sede' => trim($_POST['indirizzo_sede'] ?? ''),
                'cap' => trim($_POST['cap'] ?? ''),
                'citta' => trim($_POST['citta'] ?? ''),
                'provincia' => strtoupper(trim($_POST['provincia'] ?? '')),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'cellulare' => trim($_POST['cellulare'] ?? ''),
                'email' => $email ?: null,
                'pec' => trim($_POST['pec'] ?? ''),
                'banca_appoggio' => trim($_POST['banca_appoggio'] ?? ''),
                'iban' => trim($_POST['iban'] ?? ''),
                'operatore_responsabile_id' => $_POST['operatore_responsabile_id'] ?: null,
                'note_generali' => trim($_POST['note_generali'] ?? ''),
                'stato' => $_POST['stato'] ?? 'attivo',
                'is_attivo' => $_POST['stato'] === 'attivo' ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Aggiorna nel database
            $updated = $db->update('clienti', $updateData, 'id = ?', [$clienteId]);
            
            if ($updated !== false) {
                $_SESSION['success_message'] = "Cliente aggiornato con successo!";
                header('Location: /crm/?action=clienti&view=view&id=' . $clienteId);
                exit;
            } else {
                $errors[] = "Errore durante l'aggiornamento del cliente";
            }
        }
        
        // Se ci sono errori, ricarica i dati dal POST per mantenere le modifiche
        if (!empty($errors)) {
            foreach ($_POST as $key => $value) {
                if (isset($cliente[$key])) {
                    $cliente[$key] = $value;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Errore aggiornamento cliente: " . $e->getMessage());
        $errors[] = "Errore di sistema. Riprova più tardi.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De Consulting</title>
    
    <style>
        /* Design System Datev Koinos Compliant */
        :root {
            --primary-green: #00A86B;
            --secondary-green: #2E7D32;
            --accent-orange: #FF6B35;
            --danger-red: #DC3545;
            --warning-yellow: #FFC107;
            --gray-50: #F8F9FA;
            --gray-100: #E9ECEF;
            --gray-200: #DEE2E6;
            --gray-300: #CED4DA;
            --gray-400: #ADB5BD;
            --gray-500: #6C757D;
            --gray-600: #495057;
            --gray-700: #343A40;
            --gray-800: #212529;
            --font-base: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --transition-fast: all 0.15s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-base);
            font-size: 14px;
            color: var(--gray-800);
            background: #f5f5f5;
            line-height: 1.4;
        }
        
        /* Layout */
        .edit-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            padding: 0.5rem 0;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Header */
        .page-header {
            background: white;
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .header-info {
            flex: 1;
        }
        
        .page-title {
            font-size: 1.5rem;
            color: var(--gray-800);
            margin: 0 0 0.25rem 0;
            font-weight: 600;
        }
        
        .page-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Cliente Badge */
        .cliente-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            border-radius: 100px;
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        /* Bottoni */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            cursor: pointer;
            transition: var(--transition-fast);
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
        
        .btn-outline {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
        }
        
        /* Form Container */
        .form-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }
        
        /* Sections */
        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .form-grid.three-columns {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .form-field.full-width {
            grid-column: 1 / -1;
        }
        
        /* Form Elements */
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-label.required::after {
            content: " *";
            color: var(--danger-red);
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            width: 100%;
            transition: var(--transition-fast);
            font-family: inherit;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(0,168,107,0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Help Text */
        .form-help {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        /* Errors */
        .error-container {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .error-container ul {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }
        
        /* Info Box */
        .info-box {
            background: #EFF6FF;
            border: 1px solid #DBEAFE;
            color: #1E40AF;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        /* Status Radio */
        .status-options {
            display: flex;
            gap: 1rem;
        }
        
        .status-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-option input[type="radio"] {
            width: 16px;
            height: 16px;
        }
        
        .status-option label {
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid.three-columns {
                grid-template-columns: 1fr;
            }
            
            .header-top {
                flex-direction: column;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="/crm/?action=dashboard">Dashboard</a> / 
            <a href="/crm/?action=clienti">Clienti</a> / 
            <span>Modifica: <?= htmlspecialchars($cliente['ragione_sociale']) ?></span>
        </div>
        
        <!-- Header -->
        <header class="page-header">
            <div class="header-top">
                <div class="header-info">
                    <h1 class="page-title">✏️ Modifica Cliente</h1>
                    <div class="cliente-badge">
                        <span>📋</span>
                        <span>Codice: <?= htmlspecialchars($cliente['codice_cliente']) ?></span>
                    </div>
                    <div class="page-subtitle">
                        Creato il <?= date('d/m/Y', strtotime($cliente['created_at'])) ?>
                        <?php if ($cliente['updated_at'] && $cliente['updated_at'] != $cliente['created_at']): ?>
                            • Ultima modifica: <?= date('d/m/Y H:i', strtotime($cliente['updated_at'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="header-actions">
                    <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>" class="btn btn-secondary">
                        👁️ Visualizza
                    </a>
                    <a href="/crm/?action=clienti" class="btn btn-outline">
                        ← Lista Clienti
                    </a>
                    <a href="/crm/?action=dashboard" class="btn btn-outline">
                        🏠 Dashboard
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <strong>⚠️ Si sono verificati i seguenti errori:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Form -->
        <div class="form-container">
            <form method="POST" action="/crm/?action=clienti&view=edit&id=<?= $clienteId ?>">
                <!-- Sezione 1: Dati Principali -->
                <div class="form-section">
                    <h2 class="section-title">
                        <span>🏢</span> Dati Principali
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="ragione_sociale" class="form-label required">Ragione Sociale</label>
                            <input type="text" 
                                   id="ragione_sociale" 
                                   name="ragione_sociale" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['ragione_sociale']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-field">
                            <label for="tipologia_azienda" class="form-label required">Tipologia Azienda</label>
                            <select id="tipologia_azienda" 
                                    name="tipologia_azienda" 
                                    class="form-select" 
                                    required 
                                    onchange="toggleFiscalFields()">
                                <option value="">Seleziona...</option>
                                <?php foreach ($tipologieAzienda as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $cliente['tipologia_azienda'] === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="operatore_responsabile_id" class="form-label">Operatore Responsabile</label>
                            <select id="operatore_responsabile_id" name="operatore_responsabile_id" class="form-select">
                                <option value="">Non assegnato</option>
                                <?php foreach ($operatori as $op): ?>
                                    <option value="<?= $op['id'] ?>" <?= $cliente['operatore_responsabile_id'] == $op['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($op['nome_completo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="stato" class="form-label">Stato Cliente</label>
                            <div class="status-options">
                                <?php foreach ($statiDisponibili as $key => $label): ?>
                                    <div class="status-option">
                                        <input type="radio" 
                                               id="stato_<?= $key ?>" 
                                               name="stato" 
                                               value="<?= $key ?>"
                                               <?= $cliente['stato'] === $key ? 'checked' : '' ?>>
                                        <label for="stato_<?= $key ?>"><?= htmlspecialchars($label) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sezione 2: Dati Fiscali -->
                <div class="form-section">
                    <h2 class="section-title">
                        <span>📋</span> Dati Fiscali
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-field" id="cf-field">
                            <label for="codice_fiscale" class="form-label">Codice Fiscale</label>
                            <input type="text" 
                                   id="codice_fiscale" 
                                   name="codice_fiscale" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['codice_fiscale'] ?? '') ?>" 
                                   maxlength="16"
                                   style="text-transform: uppercase;">
                            <span class="form-help">16 caratteri per persone fisiche</span>
                        </div>
                        
                        <div class="form-field" id="piva-field">
                            <label for="partita_iva" class="form-label">Partita IVA</label>
                            <input type="text" 
                                   id="partita_iva" 
                                   name="partita_iva" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['partita_iva'] ?? '') ?>" 
                                   maxlength="11">
                            <span class="form-help">11 cifre</span>
                        </div>
                    </div>
                </div>
                
                <!-- Sezione 3: Sede Legale -->
                <div class="form-section">
                    <h2 class="section-title">
                        <span>📍</span> Sede Legale
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="indirizzo_sede" class="form-label">Indirizzo</label>
                            <input type="text" 
                                   id="indirizzo_sede" 
                                   name="indirizzo_sede" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['indirizzo_sede'] ?? '') ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="cap" class="form-label">CAP</label>
                            <input type="text" 
                                   id="cap" 
                                   name="cap" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['cap'] ?? '') ?>" 
                                   maxlength="5">
                        </div>
                        
                        <div class="form-field">
                            <label for="citta" class="form-label">Città</label>
                            <input type="text" 
                                   id="citta" 
                                   name="citta" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['citta'] ?? '') ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="provincia" class="form-label">Provincia</label>
                            <input type="text" 
                                   id="provincia" 
                                   name="provincia" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['provincia'] ?? '') ?>" 
                                   maxlength="2"
                                   style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
                
                <!-- Sezione 4: Contatti -->
                <div class="form-section">
                    <h2 class="section-title">
                        <span>📞</span> Contatti
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="telefono" class="form-label">Telefono</label>
                            <input type="tel" 
                                   id="telefono" 
                                   name="telefono" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="cellulare" class="form-label">Cellulare</label>
                            <input type="tel" 
                                   id="cellulare" 
                                   name="cellulare" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['cellulare'] ?? '') ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="pec" class="form-label">PEC</label>
                            <input type="email" 
                                   id="pec" 
                                   name="pec" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['pec'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Sezione 5: Dati Bancari -->
                <div class="form-section">
                    <h2 class="section-title">
                        <span>🏦</span> Dati Bancari
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="banca_appoggio" class="form-label">Banca d'Appoggio</label>
                            <input type="text" 
                                   id="banca_appoggio" 
                                   name="banca_appoggio" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['banca_appoggio'] ?? '') ?>">
                        </div>
                        
                        <div class="form-field">
                            <label for="iban" class="form-label">IBAN</label>
                            <input type="text" 
                                   id="iban" 
                                   name="iban" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($cliente['iban'] ?? '') ?>" 
                                   maxlength="27"
                                   style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
                
                <!-- Sezione 6: Note -->
                <div class="form-section">
                    <h2 class="section-title">
                        <span>📝</span> Note e Informazioni Aggiuntive
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="note_generali" class="form-label">Note Generali</label>
                            <textarea id="note_generali" 
                                      name="note_generali" 
                                      class="form-textarea"><?= htmlspecialchars($cliente['note_generali'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <div>
                        <span class="form-label">* Campi obbligatori</span>
                    </div>
                    <div>
                        <a href="/crm/?action=clienti" class="btn btn-secondary">
                            ❌ Annulla
                        </a>
                        <button type="submit" class="btn btn-primary">
                            💾 Salva Modifiche
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle campi fiscali in base al tipo azienda
        function toggleFiscalFields() {
            const tipo = document.getElementById('tipologia_azienda').value;
            const cfField = document.getElementById('cf-field');
            const pivaField = document.getElementById('piva-field');
            const cfInput = document.getElementById('codice_fiscale');
            const pivaInput = document.getElementById('partita_iva');
            
            if (tipo === 'individuale') {
                // Ditta individuale: CF obbligatorio, P.IVA opzionale
                cfField.querySelector('.form-label').classList.add('required');
                pivaField.querySelector('.form-label').classList.remove('required');
                cfInput.required = true;
                pivaInput.required = false;
            } else if (tipo && tipo !== '') {
                // Società: P.IVA obbligatoria, CF opzionale
                cfField.querySelector('.form-label').classList.remove('required');
                pivaField.querySelector('.form-label').classList.add('required');
                cfInput.required = false;
                pivaInput.required = true;
            } else {
                // Nessun tipo selezionato
                cfField.querySelector('.form-label').classList.remove('required');
                pivaField.querySelector('.form-label').classList.remove('required');
                cfInput.required = false;
                pivaInput.required = false;
            }
        }
        
        // Formattazione automatica
        document.getElementById('codice_fiscale').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('provincia').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('iban').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
        
        // Inizializza campi al caricamento
        toggleFiscalFields();
    </script>
</body>
</html>