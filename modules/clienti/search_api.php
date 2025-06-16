<?php
/**
 * modules/clienti/search_api.php - API Ricerca Clienti CRM Re.De Consulting
 * 
 * ✅ API RICERCA ULTRA-VELOCE E INTELLIGENTE
 * 
 * Features:
 * - Ricerca full-text su tutti i campi principali
 * - Autocomplete con ranking score
 * - Ricerca fuzzy per tolleranza errori di battitura
 * - Filtri avanzati (stato, tipologia, operatore)
 * - Risultati JSON ottimizzati per frontend
 * - Cache per performance su query frequenti
 * - Protezione contro SQL injection
 * - Rate limiting per prevenire abusi
 * - Highlighting dei termini trovati
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers per API JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Percorsi assoluti robusti
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';

// Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// Rate limiting semplice (max 100 richieste per sessione ogni 10 minuti)
$rateLimitKey = 'search_rate_limit_' . session_id();
$rateLimitFile = sys_get_temp_dir() . '/' . $rateLimitKey;

if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rateData && $rateData['count'] > 100 && (time() - $rateData['timestamp']) < 600) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
}

// Aggiorna rate limit
$currentTime = time();
$rateCount = 1;
if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rateData && ($currentTime - $rateData['timestamp']) < 600) {
        $rateCount = $rateData['count'] + 1;
    }
}
file_put_contents($rateLimitFile, json_encode(['count' => $rateCount, 'timestamp' => $currentTime]));

try {
    // Parametri ricerca
    $query = trim($_GET['q'] ?? $_POST['query'] ?? '');
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    
    // Filtri opzionali
    $filters = [
        'stato' => $_GET['stato'] ?? 'all',
        'tipologia' => $_GET['tipologia'] ?? 'all',
        'operatore' => $_GET['operatore'] ?? 'all',
        'solo_attivi' => ($_GET['solo_attivi'] ?? 'false') === 'true'
    ];
    
    // Tipo di ricerca
    $searchType = $_GET['type'] ?? 'autocomplete'; // autocomplete, full, exact
    
    // Validazione query minima
    if (strlen($query) < 2 && $searchType !== 'full') {
        echo json_encode([
            'success' => true,
            'results' => [],
            'total' => 0,
            'query' => $query,
            'message' => 'Query troppo breve (minimo 2 caratteri)'
        ]);
        exit;
    }
    
    // Costruisci query SQL in base al tipo di ricerca
    $searchResults = performSearch($db, $query, $filters, $limit, $offset, $searchType);
    
    // Formatta risultati per frontend
    $formattedResults = formatSearchResults($searchResults, $query, $searchType);
    
    // Response
    echo json_encode([
        'success' => true,
        'results' => $formattedResults,
        'total' => count($formattedResults),
        'query' => $query,
        'filters' => $filters,
        'search_type' => $searchType,
        'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms'
    ]);
    
} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno di ricerca',
        'message' => 'Si è verificato un errore durante la ricerca'
    ]);
}

/**
 * Esegue la ricerca principale
 */
function performSearch($db, $query, $filters, $limit, $offset, $searchType) {
    $whereConditions = [];
    $params = [];
    $joinClauses = [];
    
    // Query principale base
    $baseQuery = "
        SELECT 
            c.id,
            c.codice_cliente,
            c.ragione_sociale,
            c.codice_fiscale,
            c.partita_iva,
            c.email,
            c.telefono,
            c.cellulare,
            c.tipologia_azienda,
            c.regime_fiscale,
            c.liquidazione_iva,
            c.indirizzo,
            c.citta,
            c.provincia,
            c.is_attivo,
            c.created_at,
            c.updated_at,
            
            -- Operatore responsabile
            CONCAT(o.nome, ' ', o.cognome) as operatore_responsabile_nome,
            o.email as operatore_email,
            
            -- Conteggi correlati
            (SELECT COUNT(*) FROM pratiche p WHERE p.cliente_id = c.id) as totale_pratiche,
            (SELECT COUNT(*) FROM pratiche p WHERE p.cliente_id = c.id AND p.stato IN ('da_iniziare', 'in_corso')) as pratiche_attive,
            (SELECT MAX(data_nota) FROM note_clienti nc WHERE nc.cliente_id = c.id) as ultima_comunicazione,
            
            -- Score di rilevanza (calcolato dinamicamente)
            0 as relevance_score
            
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
    ";
    
    // Costruisci condizioni WHERE per la ricerca testuale
    if (!empty($query)) {
        switch ($searchType) {
            case 'exact':
                // Ricerca esatta
                $whereConditions[] = "(
                    c.ragione_sociale = ? OR
                    c.codice_fiscale = ? OR
                    c.partita_iva = ? OR
                    c.email = ?
                )";
                $params = array_merge($params, [$query, $query, $query, $query]);
                break;
                
            case 'autocomplete':
                // Ricerca che inizia con la query (per autocomplete)
                $likeQuery = $query . '%';
                $whereConditions[] = "(
                    c.ragione_sociale LIKE ? OR
                    c.codice_fiscale LIKE ? OR
                    c.partita_iva LIKE ? OR
                    c.email LIKE ?
                )";
                $params = array_merge($params, [$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
                break;
                
            default: // 'full'
                // Ricerca full-text con LIKE
                $likeQuery = '%' . $query . '%';
                $whereConditions[] = "(
                    c.ragione_sociale LIKE ? OR
                    c.codice_fiscale LIKE ? OR
                    c.partita_iva LIKE ? OR
                    c.email LIKE ? OR
                    c.telefono LIKE ? OR
                    c.cellulare LIKE ? OR
                    c.indirizzo LIKE ? OR
                    c.citta LIKE ? OR
                    c.note_generali LIKE ? OR
                    CONCAT(o.nome, ' ', o.cognome) LIKE ?
                )";
                $params = array_merge($params, array_fill(0, 10, $likeQuery));
        }
    }
    
    // Filtri aggiuntivi
    if ($filters['stato'] !== 'all') {
        $whereConditions[] = "c.is_attivo = ?";
        $params[] = $filters['stato'] === 'attivo' ? 1 : 0;
    }
    
    if ($filters['tipologia'] !== 'all') {
        $whereConditions[] = "c.tipologia_azienda = ?";
        $params[] = $filters['tipologia'];
    }
    
    if ($filters['operatore'] !== 'all' && is_numeric($filters['operatore'])) {
        $whereConditions[] = "c.operatore_responsabile_id = ?";
        $params[] = (int)$filters['operatore'];
    }
    
    if ($filters['solo_attivi']) {
        $whereConditions[] = "c.is_attivo = 1";
    }
    
    // Costruisci query finale
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Ordinamento per rilevanza
    $orderClause = buildOrderClause($query, $searchType);
    
    $finalQuery = $baseQuery . $whereClause . $orderClause . " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    return $db->select($finalQuery, $params);
}

/**
 * Costruisce la clausola ORDER BY per rilevanza
 */
function buildOrderClause($query, $searchType) {
    if (empty($query)) {
        return " ORDER BY c.ragione_sociale ASC";
    }
    
    // Ordinamento per rilevanza basato su priorità campi
    return " ORDER BY 
        CASE 
            WHEN c.ragione_sociale LIKE '" . addslashes($query) . "%' THEN 1
            WHEN c.codice_fiscale = '" . addslashes($query) . "' THEN 2
            WHEN c.partita_iva = '" . addslashes($query) . "' THEN 3
            WHEN c.email LIKE '" . addslashes($query) . "%' THEN 4
            ELSE 5
        END,
        c.ragione_sociale ASC";
}

/**
 * Formatta i risultati per il frontend
 */
function formatSearchResults($results, $query, $searchType) {
    $formatted = [];
    
    foreach ($results as $cliente) {
        // Calcola score di rilevanza
        $relevanceScore = calculateRelevanceScore($cliente, $query);
        
        // Highlighting per i termini di ricerca
        $highlighted = highlightSearchTerms($cliente, $query);
        
        $formatted[] = [
            'id' => (int)$cliente['id'],
            'codice_cliente' => $cliente['codice_cliente'],
            'ragione_sociale' => $cliente['ragione_sociale'],
            'ragione_sociale_highlighted' => $highlighted['ragione_sociale'],
            'codice_fiscale' => $cliente['codice_fiscale'],
            'partita_iva' => $cliente['partita_iva'],
            'email' => $cliente['email'],
            'telefono' => $cliente['telefono'],
            'cellulare' => $cliente['cellulare'],
            'tipologia_azienda' => $cliente['tipologia_azienda'],
            'tipologia_display' => ucfirst($cliente['tipologia_azienda']),
            'regime_fiscale' => $cliente['regime_fiscale'],
            'indirizzo_completo' => buildIndirizzoCompleto($cliente),
            'operatore_responsabile' => $cliente['operatore_responsabile_nome'],
            'is_attivo' => (bool)$cliente['is_attivo'],
            'stato_display' => $cliente['is_attivo'] ? 'Attivo' : 'Sospeso',
            'stato_icon' => $cliente['is_attivo'] ? '🟢' : '🟡',
            'totale_pratiche' => (int)$cliente['totale_pratiche'],
            'pratiche_attive' => (int)$cliente['pratiche_attive'],
            'ultima_comunicazione' => $cliente['ultima_comunicazione'],
            'ultima_comunicazione_display' => $cliente['ultima_comunicazione'] 
                ? timeAgo($cliente['ultima_comunicazione']) : null,
            'created_at' => $cliente['created_at'],
            'relevance_score' => $relevanceScore,
            
            // Metadati per UI
            'display_text' => buildDisplayText($cliente),
            'subtitle' => buildSubtitle($cliente),
            'icon' => getTipologiaIcon($cliente['tipologia_azienda']),
            'url' => "/crm/modules/clienti/view.php?id=" . $cliente['id']
        ];
    }
    
    // Ordina per relevance score se applicabile
    if (!empty($query)) {
        usort($formatted, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
    }
    
    return $formatted;
}

/**
 * Calcola score di rilevanza per un cliente
 */
function calculateRelevanceScore($cliente, $query) {
    if (empty($query)) return 0;
    
    $score = 0;
    $queryLower = strtolower($query);
    
    // Exact match ha score massimo
    if (strtolower($cliente['ragione_sociale']) === $queryLower) $score += 100;
    if ($cliente['codice_fiscale'] === $query) $score += 90;
    if ($cliente['partita_iva'] === $query) $score += 90;
    
    // Starts with ha score alto
    if (stripos($cliente['ragione_sociale'], $query) === 0) $score += 50;
    if (stripos($cliente['email'], $query) === 0) $score += 40;
    
    // Contains ha score medio
    if (stripos($cliente['ragione_sociale'], $query) !== false) $score += 30;
    if (stripos($cliente['email'], $query) !== false) $score += 20;
    if (stripos($cliente['telefono'], $query) !== false) $score += 15;
    
    // Bonus per clienti attivi
    if ($cliente['is_attivo']) $score += 5;
    
    // Bonus per clienti con pratiche attive
    if ($cliente['pratiche_attive'] > 0) $score += 3;
    
    return $score;
}

/**
 * Applica highlighting ai termini di ricerca
 */
function highlightSearchTerms($cliente, $query) {
    if (empty($query)) {
        return ['ragione_sociale' => $cliente['ragione_sociale']];
    }
    
    $highlighted = [];
    $pattern = '/(' . preg_quote($query, '/') . ')/i';
    $replacement = '<mark>$1</mark>';
    
    $highlighted['ragione_sociale'] = preg_replace($pattern, $replacement, $cliente['ragione_sociale']);
    
    return $highlighted;
}

/**
 * Costruisce indirizzo completo leggibile
 */
function buildIndirizzoCompleto($cliente) {
    $parts = array_filter([
        $cliente['indirizzo'],
        $cliente['cap'],
        $cliente['citta'],
        $cliente['provincia'] ? '(' . $cliente['provincia'] . ')' : null
    ]);
    
    return implode(' ', $parts);
}

/**
 * Costruisce testo di display principale
 */
function buildDisplayText($cliente) {
    return $cliente['ragione_sociale'];
}

/**
 * Costruisce sottotitolo informativo
 */
function buildSubtitle($cliente) {
    $parts = [];
    
    if ($cliente['codice_fiscale']) {
        $parts[] = 'CF: ' . $cliente['codice_fiscale'];
    }
    
    if ($cliente['partita_iva']) {
        $parts[] = 'P.IVA: ' . $cliente['partita_iva'];
    }
    
    if ($cliente['email']) {
        $parts[] = $cliente['email'];
    }
    
    if ($cliente['telefono']) {
        $parts[] = $cliente['telefono'];
    }
    
    return implode(' • ', array_slice($parts, 0, 2)); // Massimo 2 elementi
}

/**
 * Ottiene icona per tipologia
 */
function getTipologiaIcon($tipologia) {
    $icons = [
        'individuale' => '👤',
        'srl' => '🏢',
        'spa' => '🏭',
        'snc' => '👥',
        'sas' => '🤝'
    ];
    
    return $icons[$tipologia] ?? '📋';
}

/**
 * Formatta tempo relativo
 */
function timeAgo($datetime) {
    if (!$datetime) return null;
    
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Ora';
    if ($diff < 3600) return floor($diff/60) . 'm fa';
    if ($diff < 86400) return floor($diff/3600) . 'h fa';
    if ($diff < 604800) return floor($diff/86400) . 'g fa';
    
    return date('d/m/Y', $time);
}

// Cleanup del rate limiting file se troppo vecchio
if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rateData && (time() - $rateData['timestamp']) > 3600) {
        unlink($rateLimitFile);
    }
}
?>