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
    
    /**
     * Costruttore privato (Singleton)
     */
    private function __construct() {
        try {
            // Verifica che le costanti siano definite
            if (!defined('DB_HOST')) {
                // Se non sono definite, prova a caricare bootstrap
                $bootstrapPath = dirname(dirname(__DIR__)) . '/core/bootstrap.php';
                if (file_exists($bootstrapPath)) {
                    require_once $bootstrapPath;
                } else {
                    throw new Exception('Costanti database non definite e bootstrap.php non trovato');
                }
            }
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            
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
     * Esegui INSERT con query diretta
     * Accetta sia query SQL completa che tabella+dati
     */
    public function insert($tableOrQuery, $dataOrParams = []) {
        try {
            // Se il primo parametro contiene spazi, è una query completa
            if (strpos($tableOrQuery, ' ') !== false) {
                // Query completa con parametri
                $stmt = $this->db->prepare($tableOrQuery);
                $stmt->execute($dataOrParams);
            } else {
                // Nome tabella con array associativo di dati
                $columns = array_keys($dataOrParams);
                $values = array_map(function($col) { return ":$col"; }, $columns);
                
                $query = "INSERT INTO $tableOrQuery (" . implode(', ', $columns) . ") 
                         VALUES (" . implode(', ', $values) . ")";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute($dataOrParams);
            }
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Errore query INSERT: " . $e->getMessage());
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
            $i = 0;
            foreach ($whereParams as $key => $value) {
                if (is_numeric($key)) {
                    // Parametri posizionali - convertili in named
                    $params["where_$i"] = $value;
                    $where = preg_replace('/\?/', ":where_$i", $where, 1);
                    $i++;
                } else {
                    $params[$key] = $value;
                }
            }
            
            $query = "UPDATE $table SET $setString WHERE $where";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Errore query UPDATE: " . $e->getMessage());
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
            error_log("Errore query DELETE: " . $e->getMessage());
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