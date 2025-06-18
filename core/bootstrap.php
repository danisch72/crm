<?php
/**
 * BOOTSTRAP.PHP - File Ponte di Inizializzazione
 * CRM Re.De Consulting
 * 
 * QUESTO È IL PONTE TRA IL SISTEMA AUTH E TUTTI I MODULI
 * Non modifica mai i file in /auth/, li usa solamente
 * 
 * Posizione: /crm/core/bootstrap.php
 */

// ================================================================
// DEFINIZIONI BASE
// ================================================================

// Previeni accesso diretto
if (count(get_included_files()) === 1) {
    die('Accesso diretto non consentito');
}

// Definisci costante inizializzazione (per compatibilità)
if (!defined('CRM_INIT')) {
    define('CRM_INIT', true);
}

// Definisci root CRM
if (!defined('CRM_ROOT')) {
    define('CRM_ROOT', dirname(dirname(__FILE__)));
}

// Definisci CRM_PATH per retrocompatibilità con moduli esistenti
if (!defined('CRM_PATH')) {
    define('CRM_PATH', CRM_ROOT);
}

// ================================================================
// CARICA SISTEMA AUTENTICAZIONE
// ================================================================

// Includi configurazione auth (che legge .env)
require_once CRM_ROOT . '/auth/config.php';

// Includi classe Auth
require_once CRM_ROOT . '/auth/Auth.php';

// ================================================================
// VERIFICA AUTENTICAZIONE
// ================================================================

// Ottieni istanza Auth
$auth = Auth::getInstance();

// Flag per pagine che non richiedono autenticazione
$publicPages = [
    'login' => true,
    'logout' => true,
    'check' => true
];

// Determina se siamo in una pagina pubblica
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$isPublicPage = isset($publicPages[str_replace('.php', '', $currentScript)]);

// Se non è pagina pubblica e non autenticato, redirect al login
if (!$isPublicPage && !$auth->isAuthenticated()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// ================================================================
// CARICA COMPONENTI OPZIONALI
// ================================================================


// In getCurrentUser() o dopo autenticazione riuscita:
function initializeUserSession($userData) {
    // ... codice esistente ...
    
    // Inizializza session_start solo se non esiste
    if (!isset($_SESSION['session_start'])) {
        $_SESSION['session_start'] = time();
        $_SESSION['session_date'] = date('Y-m-d');
    }
    
    // Reset timer solo se è un nuovo giorno
    $currentDate = date('Y-m-d');
    if (isset($_SESSION['session_date']) && $_SESSION['session_date'] !== $currentDate) {
        $_SESSION['session_start'] = time();
        $_SESSION['session_date'] = $currentDate;
    }
}

// Alternativa: modifica diretta in header.php
// In components/header.php, sostituire:
// $sessionStart = $_SESSION['session_start'] ?? time();
// Con:
if (!isset($_SESSION['session_start'])) {
    $_SESSION['session_start'] = time();
    $_SESSION['session_date'] = date('Y-m-d');
}

// Reset solo se nuovo giorno
$currentDate = date('Y-m-d');
if (isset($_SESSION['session_date']) && $_SESSION['session_date'] !== $currentDate) {
    $_SESSION['session_start'] = time();
    $_SESSION['session_date'] = $currentDate;
}

$sessionStart = $_SESSION['session_start'];
/**
 * Carica classe Database se disponibile
 * Nota: Non obbligatorio, caricato solo se necessario
 */
function loadDatabase() {
    $dbFile = CRM_ROOT . '/core/classes/Database.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
        return true;
    }
    return false;
}

/**
 * Carica helpers se disponibili
 * Nota: Non obbligatorio, caricato solo se necessario
 */
function loadHelpers() {
    $helperFile = CRM_ROOT . '/core/functions/helpers.php';
    if (file_exists($helperFile)) {
        require_once $helperFile;
        return true;
    }
    return false;
}

// ================================================================
// FUNZIONI PONTE UTILITY
// ================================================================

/**
 * Ottieni utente corrente (wrapper per Auth)
 * @return array|null
 */
function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

/**
 * Verifica se utente è admin (wrapper per Auth)
 * @return bool
 */
function isAdmin() {
    global $auth;
    return $auth->isAdmin();
}

/**
 * Verifica se utente è autenticato (wrapper per Auth)
 * @return bool
 */
function isAuthenticated() {
    global $auth;
    return $auth->isAuthenticated();
}

/**
 * Genera URL assoluto per il CRM
 * @param string $path Percorso relativo (es: 'modules/clienti')
 * @return string URL completo
 */
function crmUrl($path = '') {
    $path = ltrim($path, '/');
    return CRM_BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Genera path assoluto per file system
 * @param string $path Percorso relativo
 * @return string Path completo
 */
function crmPath($path = '') {
    $path = ltrim($path, '/');
    return CRM_ROOT . ($path ? '/' . $path : '');
}

/**
 * Include un modulo in modo sicuro
 * @param string $module Nome modulo (es: 'clienti')
 * @param string $file File da includere (default: index.php)
 * @return bool True se incluso con successo
 */
function loadModule($module, $file = 'index.php') {
    $modulePath = crmPath("modules/$module/$file");
    
    if (file_exists($modulePath)) {
        include $modulePath;
        return true;
    }
    
    return false;
}

/**
 * Redirect sicuro interno al CRM
 * @param string $path Percorso interno (es: 'modules/clienti')
 * @param array $params Parametri GET opzionali
 */
function crmRedirect($path = '', $params = []) {
    $url = crmUrl($path);
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    header("Location: $url");
    exit;
}

/**
 * Mostra errore generico (per compatibilità)
 * @param string $message Messaggio errore
 * @param bool $die Se terminare esecuzione
 */
function showError($message, $die = true) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Errore - CRM Re.De Consulting</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f7f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            h1 {
                color: #dc2626;
                margin-bottom: 20px;
            }
            p {
                color: #666;
                margin-bottom: 30px;
            }
            a {
                color: #007849;
                text-decoration: none;
                font-weight: 600;
            }
            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>Errore</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="<?= CRM_BASE_URL ?>">Torna alla Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    if ($die) exit;
}

/**
 * Log debug (solo in ambiente development)
 * @param mixed $data Dati da loggare
 * @param string $label Etichetta opzionale
 */
function debugLog($data, $label = '') {
    if (defined('APP_DEBUG') && APP_DEBUG === 'true') {
        $logDir = CRM_ROOT . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp]";
        
        if ($label) {
            $logEntry .= " [$label]";
        }
        
        $logEntry .= " " . print_r($data, true) . "\n";
        
        error_log($logEntry, 3, $logDir . '/debug.log');
    }
}

// ================================================================
// COSTANTI UTILITY AGGIUNTIVE
// ================================================================

// Versione Bootstrap
define('BOOTSTRAP_VERSION', '1.0');

// Flag ambiente (da .env se disponibile)
if (!defined('APP_ENV')) {
    define('APP_ENV', 'production');
}

// Flag debug (da .env se disponibile)
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

// ================================================================
// AUTO-CARICAMENTO CLASSI (OPZIONALE)
// ================================================================

/**
 * Registra autoloader per classi del CRM
 * Cerca in /core/classes/ per convenzione
 */
spl_autoload_register(function ($className) {
    // Converti namespace in percorso
    $classFile = CRM_ROOT . '/core/classes/' . str_replace('\\', '/', $className) . '.php';
    
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// ================================================================
// LOG INIZIALIZZAZIONE
// ================================================================

debugLog('Bootstrap inizializzato', 'BOOTSTRAP');
debugLog([
    'user' => getCurrentUser(),
    'authenticated' => isAuthenticated(),
    'admin' => isAdmin()
], 'AUTH_STATUS');

// Fine bootstrap.php