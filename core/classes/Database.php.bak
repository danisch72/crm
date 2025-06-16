<?php
/**
 * Classe Database - Gestione connessioni PDO
 * CRM Re.De Consulting
 * 
 * File: /crm/core/classes/Database.php
 */

class Database {
    private static $instance = null;
    public $db = null;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    
    /**
     * Costruttore privato (Singleton)
     */
    private function __construct() {
        // Verifica che bootstrap abbia caricato le costanti
        if (!defined('DB_HOST')) {
            die('Errore: Costanti database non definite. Includere bootstrap.php prima di Database.');
        }
        
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }
    
    /**
     * Connessione al database
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->db = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            error_log("Errore connessione database: " . $e->getMessage());
            die("Errore di connessione al database. Contattare l'amministratore.");
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
     * Esegui SELECT e ritorna array di risultati
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Errore query SELECT: " . $e->getMessage() . " - Query: " . $query);
            return [];
        }
    }
    
    /**
     * Esegui SELECT e ritorna un solo record
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Errore query SELECT ONE: " . $e->getMessage() . " - Query: " . $query);
            return false;
        }
    }
    
    /**
     * Esegui INSERT
     */
    public function insert($table, $data) {
        <?php
/**
 * Classe Database - Gestione connessioni PDO
 * CRM Re.De Consulting
 * 
 * Versione minima per compatibilità
 */

class Database {
    private static $instance = null;
    public $db = null;
    
    /**
     * Costruttore privato (Singleton)
     */
    private function __construct() {
        try {
            // Usa le costanti definite in bootstrap/config
            if (!defined('DB_HOST')) {
                throw new Exception('Costanti database non definite. Verificare bootstrap.php');
            }
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->db = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Errore connessione database: " . $e->getMessage());
            throw new Exception("Errore connessione database");
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
     * Esegui SELECT e ritorna tutti i risultati
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Errore query select: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Esegui SELECT e ritorna un solo risultato
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Errore query selectOne: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Esegui INSERT
     */
    public function insert($table, $data) {
        try {
            $columns = implode(',', array_keys($data));
            $values = ':' . implode(', :', array_keys($data));
            
            $query = "INSERT INTO $table ($columns) VALUES ($values)";
            $stmt = $this->db->prepare($query);
            $stmt->execute($data);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Errore query insert: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Esegui UPDATE
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $set = [];
            foreach ($data as $column => $value) {
                $set[] = "$column = :set_$column";
            }
            $setString = implode(', ', $set);
            
            // Prepara parametri con prefisso per evitare conflitti
            $params = [];
            foreach ($data as $key => $value) {
                $params["set_$key"] = $value;
            }
            
            // Aggiungi where params
            foreach ($whereParams as $key => $value) {
                if (is_numeric($key)) {
                    $params[] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
            
            $query = "UPDATE $table SET $setString WHERE $where";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Errore query update: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Esegui DELETE
     */
    public function delete($table, $where, $params = []) {
        try {
            $query = "DELETE FROM $table WHERE $where";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Errore query delete: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Esegui query generica
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Errore query: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Inizia transazione
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transazione
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transazione
     */
    public function rollback() {
        return $this->db->rollback();
    }
    
    /**
     * Ottieni ultimo ID inserito
     */
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }
    
    /**
     * Escape stringa per LIKE
     */
    public function escapeLike($string) {
        return str_replace(['%', '_'], ['\%', '\_'], $string);
    }
}