<?php
/**
 * AUTH CLASS - Sistema Autenticazione Minimalista
 * CRM Re.De Consulting
 * 
 * Gestisce SOLO autenticazione base:
 * - Login con email/password
 * - Logout
 * - Verifica stato autenticazione
 * 
 * NESSUNA logica business (no tracking ore, no modalità lavoro)
 */

class Auth {
    private static $instance = null;
    private $db = null;
    
    /**
     * Costruttore privato (Singleton)
     */
    private function __construct() {
        $this->initSession();
        $this->initDatabase();
    }
    
    /**
     * Ottieni istanza (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inizializza sessione sicura
     */
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.name', AUTH_SESSION_NAME);
            ini_set('session.cookie_lifetime', AUTH_SESSION_LIFETIME);
            ini_set('session.cookie_path', AUTH_SESSION_PATH);
            ini_set('session.cookie_secure', AUTH_SESSION_SECURE);
            ini_set('session.cookie_httponly', AUTH_SESSION_HTTPONLY);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_cookies', 1);
            ini_set('session.use_only_cookies', 1);
            
            session_start();
            
            // Rigenera ID sessione per sicurezza
            if (!isset($_SESSION['_auth_initialized'])) {
                session_regenerate_id(true);
                $_SESSION['_auth_initialized'] = true;
            }
        }
    }
    
    /**
     * Inizializza connessione database
     */
    private function initDatabase() {
        try {
            $dsn = 'mysql:host=' . AUTH_DB_HOST . ';dbname=' . AUTH_DB_NAME . ';charset=' . AUTH_DB_CHARSET;
            $this->db = new PDO($dsn, AUTH_DB_USER, AUTH_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Errore di connessione al database');
        }
    }
    
    /**
     * LOGIN - Autentica utente
     * 
     * @param string $email Email utente
     * @param string $password Password in chiaro
     * @return array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function login($email, $password) {
        try {
            // Validazione input
            if (empty($email) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Email e password sono obbligatori'
                ];
            }
            
            // Verifica lockout
            if ($this->isLockedOut($email)) {
                return [
                    'success' => false,
                    'message' => AUTH_MSG_ACCOUNT_LOCKED
                ];
            }
            
            // Cerca utente
            $stmt = $this->db->prepare("
                SELECT id, nome, cognome, email, password_hash, is_amministratore, is_attivo 
                FROM operatori 
                WHERE email = ? 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Verifica esistenza utente
            if (!$user) {
                $this->recordFailedAttempt($email);
                return [
                    'success' => false,
                    'message' => AUTH_MSG_INVALID_CREDENTIALS
                ];
            }
            
            // Verifica account attivo
            if (!$user['is_attivo']) {
                return [
                    'success' => false,
                    'message' => AUTH_MSG_ACCOUNT_INACTIVE
                ];
            }
            
            // Verifica password
            if (!password_verify($password, $user['password_hash'])) {
                $this->recordFailedAttempt($email);
                return [
                    'success' => false,
                    'message' => AUTH_MSG_INVALID_CREDENTIALS
                ];
            }
            
            // Login riuscito - pulisci tentativi falliti
            $this->clearFailedAttempts($email);
            
            // Imposta sessione
            $this->setSession($user);
            
            // Log accesso
            $this->logAccess($user['id'], 'login');
            
            return [
                'success' => true,
                'message' => AUTH_MSG_LOGIN_SUCCESS,
                'data' => [
                    'user_id' => $user['id'],
                    'nome' => $user['nome'],
                    'cognome' => $user['cognome'],
                    'is_admin' => (bool)$user['is_amministratore']
                ]
            ];
            
        } catch (Exception $e) {
            $this->logError('Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante il login'
            ];
        }
    }
    
    /**
     * LOGOUT - Disconnetti utente
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function logout() {
        try {
            // Log logout prima di distruggere sessione
            if ($this->isAuthenticated()) {
                $userId = $_SESSION['auth_user_id'];
                $this->logAccess($userId, 'logout');
            }
            
            // Distruggi sessione
            $_SESSION = [];
            
            // Elimina cookie di sessione
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Distruggi sessione
            session_destroy();
            
            return [
                'success' => true,
                'message' => AUTH_MSG_LOGOUT_SUCCESS
            ];
            
        } catch (Exception $e) {
            $this->logError('Logout error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante il logout'
            ];
        }
    }
    
    /**
     * Verifica se utente è autenticato
     * 
     * @return bool
     */
    public function isAuthenticated() {
        return isset($_SESSION['auth_user_id']) && 
               isset($_SESSION['auth_logged_in']) && 
               $_SESSION['auth_logged_in'] === true;
    }
    
    /**
     * Ottieni info utente corrente
     * 
     * @return array|null
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['auth_user_id'],
            'nome' => $_SESSION['auth_nome'],
            'cognome' => $_SESSION['auth_cognome'],
            'email' => $_SESSION['auth_email'],
            'is_admin' => $_SESSION['auth_is_admin']
        ];
    }
    
    /**
     * Verifica se utente è admin
     * 
     * @return bool
     */
    public function isAdmin() {
        return $this->isAuthenticated() && 
               isset($_SESSION['auth_is_admin']) && 
               $_SESSION['auth_is_admin'] === true;
    }
    
    /**
     * Imposta variabili di sessione
     */
    private function setSession($user) {
        // Rigenera ID sessione per sicurezza
        session_regenerate_id(true);
        
        // Imposta variabili sessione minime
        $_SESSION['auth_logged_in'] = true;
        $_SESSION['auth_user_id'] = $user['id'];
        $_SESSION['auth_nome'] = $user['nome'];
        $_SESSION['auth_cognome'] = $user['cognome'];
        $_SESSION['auth_email'] = $user['email'];
        $_SESSION['auth_is_admin'] = (bool)$user['is_amministratore'];
        $_SESSION['auth_login_time'] = time();
        
        // Compatibilità con vecchio sistema (da rimuovere gradualmente)
        $_SESSION['operatore_id'] = $user['id'];
        $_SESSION['operatore_nome'] = $user['nome'];
        $_SESSION['operatore_cognome'] = $user['cognome'];
        $_SESSION['operatore_email'] = $user['email'];
        $_SESSION['is_amministratore'] = $user['is_amministratore'];
    }
    
    /**
     * Verifica lockout per troppi tentativi
     */
    private function isLockedOut($email) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE email = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$email, AUTH_LOCKOUT_TIME]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= AUTH_LOGIN_ATTEMPTS;
    }
    
    /**
     * Registra tentativo fallito
     */
    private function recordFailedAttempt($email) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (email, ip_address, attempt_time) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    }
    
    /**
     * Pulisci tentativi falliti
     */
    private function clearFailedAttempts($email) {
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    /**
     * Log accesso/logout
     */
    private function logAccess($userId, $action) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auth_log (user_id, action, ip_address, user_agent, timestamp) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log silenzioso, non interrompere operazione
            $this->logError('Access log failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Log errori
     */
    private function logError($message) {
        if (AUTH_LOG_ENABLED) {
            $logMessage = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
            @file_put_contents(AUTH_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Genera token CSRF
     */
    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $_SESSION[AUTH_CSRF_TOKEN_NAME] = $token;
        return $token;
    }
    
    /**
     * Verifica token CSRF
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION[AUTH_CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[AUTH_CSRF_TOKEN_NAME], $token);
    }
}

// ================================================================
// FUNZIONI HELPER GLOBALI PER COMPATIBILITÀ
// ================================================================

/**
 * Verifica autenticazione (compatibilità)
 */
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        return Auth::getInstance()->isAuthenticated();
    }
}

/**
 * Ottieni ID utente corrente (compatibilità)
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        $user = Auth::getInstance()->getCurrentUser();
        return $user ? $user['id'] : null;
    }
}

/**
 * Verifica se admin (compatibilità)
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return Auth::getInstance()->isAdmin();
    }
}
?>