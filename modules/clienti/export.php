<?php
/**
 * modules/clienti/export.php - Export Dati Cliente CRM Re.De Consulting
 * 
 * ✅ EXPORT PROFESSIONALE COMMERCIALISTI COMPLIANT
 * 
 * Features:
 * - Export Excel/CSV con dati fiscali completi
 * - Template specifici per dichiarazioni e controlli
 * - Export multiplo clienti selezionati
 * - Formattazione professionale per uso fiscale
 * - Protezione dati e controllo accessi
 * - Export pratiche e scadenze associate
 * - Compatibilità con software fiscali standard
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

// Parametri export
$exportType = $_GET['type'] ?? 'excel';
$clienteId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$clienteIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$template = $_GET['template'] ?? 'completo';

// Validazione parametri
if (!$clienteId && empty($clienteIds)) {
    die('Errore: Nessun cliente specificato per l\'export');
}

if ($clienteId) {
    $clienteIds = [$clienteId];
}

// Sanitize IDs
$clienteIds = array_filter(array_map('intval', $clienteIds));

if (empty($clienteIds)) {
    die('Errore: ID clienti non validi');
}

try {
    // Carica dati clienti
    $placeholders = str_repeat('?,', count($clienteIds) - 1) . '?';
    
    $clienti = $db->select("
        SELECT 
            c.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_responsabile_nome,
            o.email as operatore_email,
            
            -- Conteggio pratiche
            (SELECT COUNT(*) FROM pratiche p WHERE p.cliente_id = c.id) as totale_pratiche,
            (SELECT COUNT(*) FROM pratiche p WHERE p.cliente_id = c.id AND p.stato IN ('da_iniziare', 'in_corso')) as pratiche_attive,
            (SELECT COUNT(*) FROM pratiche p WHERE p.cliente_id = c.id AND p.stato = 'completata') as pratiche_completate,
            
            -- Ultima comunicazione
            (SELECT MAX(data_nota) FROM note_clienti nc WHERE nc.cliente_id = c.id) as ultima_comunicazione,
            
            -- Conteggio documenti
            (SELECT COUNT(*) FROM documenti_clienti dc WHERE dc.cliente_id = c.id) as totale_documenti
            
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
        WHERE c.id IN ($placeholders)
        ORDER BY c.ragione_sociale
    ", $clienteIds);
    
    if (empty($clienti)) {
        die('Errore: Nessun cliente trovato con gli ID specificati');
    }
    
    // Se richieste, carica anche pratiche associate
    $pratiche = [];
    if ($template === 'completo' || $template === 'pratiche') {
        $pratiche = $db->select("
            SELECT 
                p.*,
                c.ragione_sociale as cliente_nome,
                s.nome as settore_nome,
                CONCAT(o.nome, ' ', o.cognome) as operatore_nome
            FROM pratiche p
            LEFT JOIN clienti c ON p.cliente_id = c.id
            LEFT JOIN settori s ON p.settore_id = s.id
            LEFT JOIN operatori o ON p.operatore_assegnato_id = o.id
            WHERE p.cliente_id IN ($placeholders)
            ORDER BY c.ragione_sociale, p.data_scadenza ASC
        ", $clienteIds);
    }
    
    // Genera export basato sul tipo richiesto
    switch ($exportType) {
        case 'excel':
            generateExcelExport($clienti, $pratiche, $template);
            break;
        case 'csv':
            generateCSVExport($clienti, $pratiche, $template);
            break;
        case 'pdf':
            generatePDFExport($clienti, $pratiche, $template);
            break;
        default:
            die('Tipo di export non supportato');
    }
    
} catch (Exception $e) {
    error_log("Errore export clienti: " . $e->getMessage());
    die('Errore durante l\'export: ' . $e->getMessage());
}

/**
 * Genera export Excel/XLSX
 */
function generateExcelExport($clienti, $pratiche, $template) {
    $filename = 'export_clienti_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM per UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    switch ($template) {
        case 'fiscale':
            generateFiscaleTemplate($output, $clienti);
            break;
        case 'pratiche':
            generatePraticheTemplate($output, $clienti, $pratiche);
            break;
        case 'contatti':
            generateContattiTemplate($output, $clienti);
            break;
        default: // completo
            generateCompletoTemplate($output, $clienti, $pratiche);
    }
    
    fclose($output);
    exit;
}

/**
 * Genera export CSV
 */
function generateCSVExport($clienti, $pratiche, $template) {
    generateExcelExport($clienti, $pratiche, $template); // Usa stesso motore
}

/**
 * Template Export Fiscale - Solo dati essenziali per dichiarazioni
 */
function generateFiscaleTemplate($output, $clienti) {
    // Header
    fputcsv($output, [
        'Codice Cliente',
        'Ragione Sociale',
        'Codice Fiscale',
        'Partita IVA',
        'Tipologia Azienda',
        'Regime Fiscale',
        'Liquidazione IVA',
        'Indirizzo',
        'CAP',
        'Città',
        'Provincia',
        'Email',
        'PEC',
        'Telefono',
        'Data Costituzione',
        'Capitale Sociale',
        'Codice ATECO',
        'Settore Attività',
        'Stato',
        'Note Fiscali'
    ], ';');
    
    // Dati
    foreach ($clienti as $cliente) {
        fputcsv($output, [
            $cliente['codice_cliente'],
            $cliente['ragione_sociale'],
            $cliente['codice_fiscale'],
            $cliente['partita_iva'],
            ucfirst($cliente['tipologia_azienda']),
            ucfirst($cliente['regime_fiscale']),
            ucfirst($cliente['liquidazione_iva']),
            $cliente['indirizzo'],
            $cliente['cap'],
            $cliente['citta'],
            $cliente['provincia'],
            $cliente['email'],
            $cliente['pec'],
            $cliente['telefono'],
            $cliente['data_costituzione'],
            $cliente['capitale_sociale'],
            $cliente['codice_ateco'],
            $cliente['settore_attivita'],
            $cliente['is_attivo'] ? 'Attivo' : 'Sospeso',
            $cliente['note_generali']
        ], ';');
    }
}

/**
 * Template Export Completo - Tutti i dati disponibili
 */
function generateCompletoTemplate($output, $clienti, $pratiche) {
    // Header Clienti
    fputcsv($output, [
        'DATI CLIENTI'
    ], ';');
    
    fputcsv($output, [
        'Codice Cliente',
        'Ragione Sociale',
        'Codice Fiscale', 
        'Partita IVA',
        'Tipologia',
        'Regime Fiscale',
        'Liquidazione IVA',
        'Email',
        'PEC', 
        'Telefono',
        'Cellulare',
        'Indirizzo Completo',
        'CAP',
        'Città',
        'Provincia',
        'Operatore Responsabile',
        'Stato',
        'Creato il',
        'Ultima Modifica',
        'Totale Pratiche',
        'Pratiche Attive',
        'Pratiche Completate',
        'Ultima Comunicazione',
        'Totale Documenti',
        'Note'
    ], ';');
    
    // Dati Clienti
    foreach ($clienti as $cliente) {
        fputcsv($output, [
            $cliente['codice_cliente'],
            $cliente['ragione_sociale'],
            $cliente['codice_fiscale'],
            $cliente['partita_iva'],
            ucfirst($cliente['tipologia_azienda']),
            ucfirst($cliente['regime_fiscale']),
            ucfirst($cliente['liquidazione_iva']),
            $cliente['email'],
            $cliente['pec'],
            $cliente['telefono'],
            $cliente['cellulare'],
            $cliente['indirizzo'],
            $cliente['cap'],
            $cliente['citta'],
            $cliente['provincia'],
            $cliente['operatore_responsabile_nome'],
            $cliente['is_attivo'] ? 'Attivo' : 'Sospeso',
            date('d/m/Y', strtotime($cliente['created_at'])),
            date('d/m/Y', strtotime($cliente['updated_at'])),
            $cliente['totale_pratiche'],
            $cliente['pratiche_attive'],
            $cliente['pratiche_completate'],
            $cliente['ultima_comunicazione'] ? date('d/m/Y', strtotime($cliente['ultima_comunicazione'])) : '',
            $cliente['totale_documenti'],
            $cliente['note_generali']
        ], ';');
    }
    
    // Se ci sono pratiche, aggiungile
    if (!empty($pratiche)) {
        fputcsv($output, [], ';'); // Riga vuota
        fputcsv($output, ['PRATICHE ASSOCIATE'], ';');
        
        fputcsv($output, [
            'Cliente',
            'Titolo Pratica',
            'Settore',
            'Stato',
            'Priorità',
            'Data Scadenza',
            'Operatore Assegnato',
            'Ore Stimate',
            'Ore Lavorate',
            'Creata il',
            'Descrizione'
        ], ';');
        
        foreach ($pratiche as $pratica) {
            fputcsv($output, [
                $pratica['cliente_nome'],
                $pratica['titolo'],
                $pratica['settore_nome'],
                ucfirst($pratica['stato']),
                ucfirst($pratica['priorita']),
                $pratica['data_scadenza'] ? date('d/m/Y', strtotime($pratica['data_scadenza'])) : '',
                $pratica['operatore_nome'],
                $pratica['ore_stimate'],
                $pratica['ore_lavorate'],
                date('d/m/Y', strtotime($pratica['created_at'])),
                $pratica['descrizione']
            ], ';');
        }
    }
}

/**
 * Template Export Pratiche
 */
function generatePraticheTemplate($output, $clienti, $pratiche) {
    fputcsv($output, [
        'Cliente',
        'Codice Cliente',
        'Codice Fiscale',
        'Partita IVA',
        'Titolo Pratica',
        'Settore',
        'Stato Pratica',
        'Priorità',
        'Data Scadenza',
        'Operatore Assegnato',
        'Ore Stimate',
        'Ore Lavorate',
        'Percentuale Completamento',
        'Giorni Rimanenti',
        'Creata il',
        'Descrizione'
    ], ';');
    
    foreach ($pratiche as $pratica) {
        $cliente = array_filter($clienti, fn($c) => $c['id'] == $pratica['cliente_id']);
        $cliente = reset($cliente);
        
        $giorniRimanenti = '';
        if ($pratica['data_scadenza']) {
            $diff = (strtotime($pratica['data_scadenza']) - time()) / (60 * 60 * 24);
            $giorniRimanenti = round($diff);
        }
        
        $percentualeCompletamento = '';
        if ($pratica['ore_stimate'] > 0) {
            $percentualeCompletamento = round(($pratica['ore_lavorate'] / $pratica['ore_stimate']) * 100, 1) . '%';
        }
        
        fputcsv($output, [
            $pratica['cliente_nome'],
            $cliente['codice_cliente'] ?? '',
            $cliente['codice_fiscale'] ?? '',
            $cliente['partita_iva'] ?? '',
            $pratica['titolo'],
            $pratica['settore_nome'],
            ucfirst($pratica['stato']),
            ucfirst($pratica['priorita']),
            $pratica['data_scadenza'] ? date('d/m/Y', strtotime($pratica['data_scadenza'])) : '',
            $pratica['operatore_nome'],
            $pratica['ore_stimate'],
            $pratica['ore_lavorate'],
            $percentualeCompletamento,
            $giorniRimanenti,
            date('d/m/Y', strtotime($pratica['created_at'])),
            $pratica['descrizione']
        ], ';');
    }
}

/**
 * Template Export Contatti
 */
function generateContattiTemplate($output, $clienti) {
    fputcsv($output, [
        'Ragione Sociale',
        'Tipologia',
        'Email Principale',
        'PEC',
        'Telefono Fisso',
        'Cellulare',
        'Indirizzo',
        'CAP',
        'Città',
        'Provincia',
        'Operatore Responsabile',
        'Email Operatore',
        'Ultima Comunicazione',
        'Stato Cliente'
    ], ';');
    
    foreach ($clienti as $cliente) {
        fputcsv($output, [
            $cliente['ragione_sociale'],
            ucfirst($cliente['tipologia_azienda']),
            $cliente['email'],
            $cliente['pec'],
            $cliente['telefono'],
            $cliente['cellulare'],
            $cliente['indirizzo'],
            $cliente['cap'],
            $cliente['citta'],
            $cliente['provincia'],
            $cliente['operatore_responsabile_nome'],
            $cliente['operatore_email'],
            $cliente['ultima_comunicazione'] ? date('d/m/Y H:i', strtotime($cliente['ultima_comunicazione'])) : '',
            $cliente['is_attivo'] ? 'Attivo' : 'Sospeso'
        ], ';');
    }
}

/**
 * Genera export PDF (implementazione base)
 */
function generatePDFExport($clienti, $pratiche, $template) {
    $filename = 'export_clienti_' . date('Y-m-d_H-i-s') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo generateHTMLReport($clienti, $pratiche, $template);
    exit;
}

/**
 * Genera report HTML per PDF
 */
function generateHTMLReport($clienti, $pratiche, $template) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Export Clienti CRM Re.De Consulting</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #666; }
        .section-title { font-size: 16px; font-weight: bold; margin: 20px 0 10px 0; color: #2c6e49; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Export Clienti</h1>
        <h2>CRM Re.De Consulting</h2>
        <p>Generato il: ' . date('d/m/Y H:i') . '</p>
        <p>Template: ' . ucfirst($template) . '</p>
    </div>';
    
    $html .= '<div class="section-title">Dati Clienti (' . count($clienti) . ' totali)</div>';
    $html .= '<table>';
    $html .= '<tr>
        <th>Ragione Sociale</th>
        <th>CF/P.IVA</th>
        <th>Tipologia</th>
        <th>Contatti</th>
        <th>Operatore</th>
        <th>Stato</th>
    </tr>';
    
    foreach ($clienti as $cliente) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($cliente['ragione_sociale']) . '</td>';
        $html .= '<td>' . htmlspecialchars($cliente['codice_fiscale'] ?? $cliente['partita_iva'] ?? '-') . '</td>';
        $html .= '<td>' . ucfirst($cliente['tipologia_azienda']) . '</td>';
        $html .= '<td>' . htmlspecialchars($cliente['email'] ?? $cliente['telefono'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($cliente['operatore_responsabile_nome'] ?? 'Non assegnato') . '</td>';
        $html .= '<td>' . ($cliente['is_attivo'] ? 'Attivo' : 'Sospeso') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    if (!empty($pratiche) && ($template === 'completo' || $template === 'pratiche')) {
        $html .= '<div class="section-title">Pratiche Associate (' . count($pratiche) . ' totali)</div>';
        $html .= '<table>';
        $html .= '<tr>
            <th>Cliente</th>
            <th>Pratica</th>
            <th>Stato</th>
            <th>Scadenza</th>
            <th>Operatore</th>
        </tr>';
        
        foreach ($pratiche as $pratica) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($pratica['cliente_nome']) . '</td>';
            $html .= '<td>' . htmlspecialchars($pratica['titolo']) . '</td>';
            $html .= '<td>' . ucfirst($pratica['stato']) . '</td>';
            $html .= '<td>' . ($pratica['data_scadenza'] ? date('d/m/Y', strtotime($pratica['data_scadenza'])) : '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($pratica['operatore_nome'] ?? 'Non assegnato') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
    }
    
    $html .= '<div class="footer">
        <p>CRM Re.De Consulting - Export generato automaticamente</p>
        <p>www.redeconsulting.eu</p>
    </div>
</body>
</html>';
    
    return $html;
}

// Log dell'export per audit
error_log("Export clienti eseguito da operatore " . $sessionInfo['user_id'] . " - Template: $template - Clienti: " . implode(',', $clienteIds));
?>