<?php
/**
 * AUTH.PHP - Classe Sistema Autenticazione
 * CRM Re.De Consulting
 * 
 * Gestisce login, logout, sessioni e sicurezza
 * COMPLETAMENTE AUTONOMA E ISOLATA
 */

// Previeni accesso diretto
if (!defined('AUTH_INIT') && !defined('CRM_INIT')) {
    die('Accesso diretto non consentito');
}

class Auth {
    private static $instance = null;
    private $db = null;
    
    /**
     * Costruttore privato (Singleton)
     */
    private function __construct() {
        // Inizializza connessione database
        $this->initDatabase();
        
        // Avvia sessione se necessario
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Ottieni istanza singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inizializza connessione database
     */
    private function initDatabase() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->db = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Errore connessione database: " . $e->getMessage());
            die("Errore di sistema. Contattare amministratore.");
        }
    }
    
    /**
     * Login operatore
     * 
     * @param string $email
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login($email, $password) {
        // Sanitizza input
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Verifica email valida
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Email non valida'
            ];
        }
        
        // Verifica brute force
        if ($this->isLockedOut($email)) {
            return [
                'success' => false,
                'message' => 'Account temporaneamente bloccato per troppi tentativi. Riprova tra 15 minuti.'
            ];
        }
        
        // Cerca operatore
        $stmt = $this->db->prepare("
            SELECT 
                id,
                cognome,
                nome,
                email,
                password_hash,
                is_amministratore,
                is_attivo
            FROM operatori 
            WHERE email = ? AND is_attivo = 1
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verifica utente trovato
        if (!$user) {
            $this->recordFailedLogin($email);
            return [
                'success' => false,
                'message' => 'Credenziali non valide'
            ];
        }
        
        // Verifica password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedLogin($email);
            return [
                'success' => false,
                'message' => 'Credenziali non valide'
            ];
        }
        
        // Password corretta - aggiorna hash se necessario
        if (password_needs_rehash($user['password_hash'], AUTH_PASSWORD_ALGO)) {
            $newHash = password_hash($password, AUTH_PASSWORD_ALGO);
            $stmt = $this->db->prepare("UPDATE operatori SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
        }
        
        // Login riuscito - pulisci tentativi falliti
        $this->clearFailedLogins($email);
        
        // Imposta sessione
        $this->createSession($user);
        
        // Log accesso
        $this->logAccess($user['id'], 'login');
        
        return [
            'success' => true,
            'message' => 'Login effettuato con successo',
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'cognome' => $user['cognome'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_amministratore']
            ]
        ];
    }
    
    /**
     * Logout operatore
     */
    public function logout() {
        // Log logout prima di distruggere sessione
        if (isset($_SESSION['operatore_id'])) {
            $this->logAccess($_SESSION['operatore_id'], 'logout');
        }
        
        // Distruggi sessione
        $_SESSION = [];
        
        // Elimina cookie sessione
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Distruggi sessione
        session_destroy();
        
        return true;
    }
    
    /**
     * Verifica se utente è autenticato
     */
    public function isAuthenticated() {
        return isset($_SESSION['operatore_id']) && 
               isset($_SESSION['auth_token']) &&
               $_SESSION['auth_token'] === $this->generateAuthToken($_SESSION['operatore_id']);
    }
    
    /**
     * Ottieni utente corrente
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['operatore_id'],
            'nome' => $_SESSION['operatore_nome'],
            'cognome' => $_SESSION['operatore_cognome'],
            'email' => $_SESSION['operatore_email'],
            'is_admin' => $_SESSION['is_amministratore']
        ];
    }
    
    /**
     * Verifica se utente è admin
     */
    public function isAdmin() {
        return $this->isAuthenticated() && $_SESSION['is_amministratore'];
    }
    
    /**
     * Crea sessione per utente
     */
    private function createSession($user) {
        // Rigenera ID sessione per sicurezza
        session_regenerate_id(true);
        
        // Imposta variabili sessione
        $_SESSION['operatore_id'] = $user['id'];
        $_SESSION['operatore_nome'] = $user['nome'];
        $_SESSION['operatore_cognome'] = $user['cognome'];
        $_SESSION['operatore_email'] = $user['email'];
        $_SESSION['is_amministratore'] = (bool)$user['is_amministratore'];
        $_SESSION['auth_token'] = $this->generateAuthToken($user['id']);
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Genera token autenticazione
     */
    private function generateAuthToken($userId) {
        return hash_hmac('sha256', $userId . session_id(), AUTH_SECRET_KEY);
    }
    
    /**
     * Verifica se account è bloccato
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
     * Registra tentativo login fallito
     */
    private function recordFailedLogin($email) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (email, ip_address)
            VALUES (?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->execute([$email, $ip]);
    }
    
    /**
     * Pulisci tentativi login falliti
     */
    private function clearFailedLogins($email) {
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
                INSERT INTO auth_log (user_id, action, ip_address, user_agent)
                VALUES (?, ?, ?, ?)
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt->execute([$userId, $action, $ip, $userAgent]);
        } catch (Exception $e) {
            // Log silenzioso - non interrompere flusso
            $this->logError("Errore log accesso: " . $e->getMessage());
        }
    }
    
    /**
     * Genera token CSRF
     */
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verifica token CSRF
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Log errori
     */
    private function logError($message) {
        $logDir = AUTH_ROOT . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/auth_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        error_log($logMessage, 3, $logFile);
    }
}