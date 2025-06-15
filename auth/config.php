<?php
/**
 * CONFIG.PHP - Configurazione Sistema Autenticazione
 * CRM Re.De Consulting
 * 
 * Carica configurazione da .env e definisce costanti
 * COMPLETAMENTE ISOLATO DAI MODULI
 */

// Previeni accesso diretto
if (!defined('AUTH_INIT') && !defined('CRM_INIT')) {
    die('Accesso diretto non consentito');
}

// ================================================================
// CARICA FILE .ENV
// ================================================================

class EnvLoader {
    private static $instance = null;
    private $env = [];
    
    private function __construct() {
        $this->loadEnv();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadEnv() {
        $envPath = dirname(dirname(__FILE__)) . '/.env';
        
        if (!file_exists($envPath)) {
            die('File .env non trovato. Verificare configurazione.');
        }
        
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignora commenti
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Rimuovi virgolette se presenti
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            $this->env[$name] = $value;
        }
    }
    
    public function get($key, $default = null) {
        return $this->env[$key] ?? $default;
    }
}

// Carica ambiente
$env = EnvLoader::getInstance();

// ================================================================
// COSTANTI DATABASE
// ================================================================

define('DB_HOST', $env->get('DB_HOST', 'localhost'));
define('DB_NAME', $env->get('DB_NAME'));
define('DB_USERNAME', $env->get('DB_USERNAME'));
define('DB_PASSWORD', $env->get('DB_PASSWORD'));
define('DB_CHARSET', $env->get('DB_CHARSET', 'utf8mb4'));

// ================================================================
// COSTANTI SICUREZZA
// ================================================================

define('AUTH_SECRET_KEY', $env->get('APP_SECRET_KEY', 'crm_rede_2025_secret_key_32chars'));
define('AUTH_SESSION_LIFETIME', (int)$env->get('SESSION_TIMEOUT', 3600)); // 1 ora default
define('AUTH_LOGIN_ATTEMPTS', 5); // Max tentativi login
define('AUTH_LOCKOUT_TIME', 900); // 15 minuti lockout
define('AUTH_PASSWORD_ALGO', PASSWORD_ARGON2ID); // Algoritmo più sicuro

// ================================================================
// COSTANTI PERCORSI
// ================================================================

define('AUTH_ROOT', dirname(__FILE__));
define('CRM_ROOT_PATH', dirname(AUTH_ROOT));
define('CRM_BASE_URL', '/crm');
define('LOGIN_URL', CRM_BASE_URL . '/auth/login.php');
define('LOGOUT_URL', CRM_BASE_URL . '/?action=logout');
define('DASHBOARD_URL', CRM_BASE_URL . '/?action=dashboard');

// ================================================================
// CONFIGURAZIONE SESSIONE
// ================================================================

// Configura sessione sicura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', AUTH_SESSION_LIFETIME);

// Nome sessione personalizzato
session_name('CRM_REDE_AUTH');

// ================================================================
// TIMEZONE E LOCALE
// ================================================================

date_default_timezone_set($env->get('APP_TIMEZONE', 'Europe/Rome'));
setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'italian');

// ================================================================
// ERROR REPORTING
// ================================================================

if ($env->get('APP_DEBUG', 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING);
    ini_set('display_errors', 0);
}

// ================================================================
// HELPER FUNCTIONS COMPATIBILITÀ
// ================================================================

/**
 * Verifica se l'utente è autenticato (compatibilità legacy)
 */
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return isset($_SESSION['operatore_id']) && $_SESSION['operatore_id'] > 0;
    }
}

/**
 * Ottieni ID utente corrente (compatibilità legacy)
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['operatore_id'] ?? null;
    }
}

/**
 * Verifica se admin (compatibilità legacy)
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return $_SESSION['is_amministratore'] ?? false;
    }
}