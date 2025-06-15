<?php
/**
 * AUTH CONFIG - Configurazione Sistema Autenticazione
 * CRM Re.De Consulting
 * 
 * SICUREZZA: Questo file legge le credenziali dal file .env
 * MAI inserire credenziali in chiaro in questo file!
 * 
 * File isolato per configurazione autenticazione
 * NESSUNA dipendenza da logiche business
 */

// Previeni accesso diretto
if (!defined('AUTH_INIT') && !defined('CRM_INIT')) {
    http_response_code(403);
    die('Accesso diretto non consentito');
}

// ================================================================
// CARICA CONFIGURAZIONE DA .ENV
// ================================================================
$envPath = dirname(dirname(__FILE__)) . '/.env'; // /crm/.env

if (!file_exists($envPath)) {
    die('ERRORE: File .env non trovato!');
}

// Verifica permessi file (dovrebbe essere 600 o 640)
$perms = fileperms($envPath) & 0777;
if ($perms > 0640) {
    error_log('ATTENZIONE: Il file .env ha permessi troppo permissivi: ' . decoct($perms));
}

// Parse .env file
$env = parse_ini_file($envPath);

if (!$env) {
    die('ERRORE: Impossibile leggere file .env!');
}

// ================================================================
// CONFIGURAZIONE DATABASE (DA .ENV)
// ================================================================
define('AUTH_DB_HOST', $env['DB_HOST'] ?? '');
define('AUTH_DB_NAME', $env['DB_NAME'] ?? '');
define('AUTH_DB_USER', $env['DB_USERNAME'] ?? '');
define('AUTH_DB_PASS', $env['DB_PASSWORD'] ?? '');
define('AUTH_DB_CHARSET', $env['DB_CHARSET'] ?? 'utf8mb4');

// Verifica che tutti i parametri siano presenti
if (empty(AUTH_DB_HOST) || empty(AUTH_DB_NAME) || empty(AUTH_DB_USER) || empty(AUTH_DB_PASS)) {
    die('ERRORE: Configurazione database incompleta in .env!');
}

// ================================================================
// CONFIGURAZIONE SESSIONI
// ================================================================
define('AUTH_SESSION_NAME', 'crm_rede_session');
define('AUTH_SESSION_LIFETIME', (int)($env['SESSION_TIMEOUT'] ?? 3600));
define('AUTH_SESSION_PATH', '/');
define('AUTH_SESSION_SECURE', true);  // HTTPS only
define('AUTH_SESSION_HTTPONLY', true); // No JS access

// ================================================================
// CONFIGURAZIONE SICUREZZA
// ================================================================
define('AUTH_SECRET_KEY', $env['APP_SECRET_KEY'] ?? 'default_insecure_key_change_me');
define('AUTH_PASSWORD_ALGO', PASSWORD_ARGON2ID);
define('AUTH_LOGIN_ATTEMPTS', 5); // Max tentativi login
define('AUTH_LOCKOUT_TIME', 900); // 15 minuti lockout
define('AUTH_CSRF_TOKEN_NAME', 'auth_token');

// ================================================================
// PATH E URL
// ================================================================
define('AUTH_ROOT', dirname(__FILE__));
define('AUTH_URL', '/crm/auth');
define('CRM_URL', '/crm');
define('LOGIN_URL', AUTH_URL . '/login.php');
define('LOGOUT_URL', AUTH_URL . '/logout.php');
define('DASHBOARD_URL', CRM_URL . '/?action=dashboard');

// ================================================================
// MESSAGGI SISTEMA
// ================================================================
define('AUTH_MSG_INVALID_CREDENTIALS', 'Email o password non validi');
define('AUTH_MSG_ACCOUNT_LOCKED', 'Account bloccato per troppi tentativi');
define('AUTH_MSG_ACCOUNT_INACTIVE', 'Account non attivo');
define('AUTH_MSG_LOGIN_SUCCESS', 'Accesso effettuato con successo');
define('AUTH_MSG_LOGOUT_SUCCESS', 'Disconnessione avvenuta con successo');
define('AUTH_MSG_SESSION_EXPIRED', 'Sessione scaduta, effettua nuovamente il login');
define('AUTH_MSG_NOT_AUTHENTICATED', 'Devi effettuare il login per accedere');

// ================================================================
// OPZIONI DEBUG (disabilitare in produzione)
// ================================================================
define('AUTH_DEBUG', ($env['APP_DEBUG'] ?? 'false') === 'true');
define('AUTH_LOG_ENABLED', true);
define('AUTH_LOG_FILE', $_SERVER['DOCUMENT_ROOT'] . '/crm/' . ($env['LOG_PATH'] ?? 'auth/logs/') . 'auth.log');

// ================================================================
// FUNZIONE AUTOLOAD SEMPLICE
// ================================================================
spl_autoload_register(function ($class) {
    $file = AUTH_ROOT . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>