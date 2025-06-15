-- ============================================
-- TABELLE SISTEMA AUTENTICAZIONE
-- CRM Re.De Consulting
-- ============================================

-- Tabella tentativi login falliti (anti brute-force)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempt_time),
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella log accessi (audit trail)
CREATE TABLE IF NOT EXISTS auth_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action ENUM('login', 'logout', 'failed_login', 'password_change') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    additional_data JSON NULL,
    FOREIGN KEY (user_id) REFERENCES operatori(id) ON DELETE CASCADE,
    INDEX idx_user_time (user_id, timestamp),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pulizia automatica vecchi tentativi (opzionale)
-- Da eseguire periodicamente via cron
-- DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR);
-- DELETE FROM auth_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- ============================================
-- VERIFICA STRUTTURA ESISTENTE
-- ============================================

-- Assicurati che la tabella operatori abbia i campi necessari
-- (questa query è solo di verifica, non modifica nulla)
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'operatori'
    AND COLUMN_NAME IN ('id', 'email', 'password_hash', 'is_attivo', 'is_amministratore')
ORDER BY 
    ORDINAL_POSITION;