-- Schema Database CRM Re.De Consulting
-- Versione: 1.0
-- Charset: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- MODULO 2: GESTIONE OPERATORI
-- ============================================

-- Tabella operatori
CREATE TABLE IF NOT EXISTS operatori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cognome VARCHAR(100) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    qualifiche JSON COMMENT 'Array di qualifiche: Contabilità, IRPEF, IRES, ecc.',
    tipo_contratto ENUM('spezzato', 'continuato') DEFAULT 'continuato',
    orario_mattino_inizio TIME NULL,
    orario_mattino_fine TIME NULL,
    orario_pomeriggio_inizio TIME NULL,
    orario_pomeriggio_fine TIME NULL,
    orario_continuato_inizio TIME NULL,
    orario_continuato_fine TIME NULL,
    is_amministratore BOOLEAN DEFAULT FALSE,
    is_attivo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_attivo (is_attivo)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella sessioni lavoro (tracking login/logout)
CREATE TABLE IF NOT EXISTS sessioni_lavoro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operatore_id INT NOT NULL,
    modalita_lavoro ENUM('ufficio', 'smart_working') NOT NULL,
    login_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_timestamp TIMESTAMP NULL,
    ore_contratto DECIMAL(5,2) DEFAULT 0.00,
    ore_effettive DECIMAL(5,2) DEFAULT 0.00,
    ore_extra DECIMAL(5,2) DEFAULT 0.00,
    is_attiva BOOLEAN DEFAULT TRUE,
    note TEXT NULL COMMENT 'Note sulla sessione (es. chiusura automatica)',
    FOREIGN KEY (operatore_id) REFERENCES operatori(id) ON DELETE CASCADE,
    INDEX idx_operatore (operatore_id),
    INDEX idx_data (login_timestamp),
    INDEX idx_attiva (is_attiva)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MODULO 3: GESTIONE CLIENTI
-- ============================================

-- Tabella clienti
CREATE TABLE IF NOT EXISTS clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codice_cliente VARCHAR(20) UNIQUE NOT NULL,
    ragione_sociale VARCHAR(255) NOT NULL,
    partita_iva VARCHAR(20) NULL,
    codice_fiscale VARCHAR(20) NULL,
    telefono VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    pec VARCHAR(255) NULL,
    indirizzo TEXT NULL,
    cap VARCHAR(10) NULL,
    citta VARCHAR(100) NULL,
    provincia VARCHAR(5) NULL,
    tipologia_azienda ENUM('individuale', 'srl', 'spa', 'snc', 'sas', 'altro') NULL,
    regime_fiscale ENUM('ordinario', 'semplificato', 'forfettario', 'altro') NULL,
    liquidazione_iva ENUM('mensile', 'trimestrale', 'annuale') NULL,
    is_attivo BOOLEAN DEFAULT TRUE,
    note_generali TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_codice (codice_cliente),
    INDEX idx_ragione_sociale (ragione_sociale),
    INDEX idx_piva (partita_iva),
    INDEX idx_attivo (is_attivo)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella documenti clienti
CREATE TABLE IF NOT EXISTS documenti_clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    operatore_id INT NOT NULL,
    nome_file VARCHAR(255) NOT NULL,
    path_file VARCHAR(500) NOT NULL,
    dimensione_file INT NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    categoria ENUM('contratto', 'documento_identita', 'certificato', 'fattura', 'altro') DEFAULT 'altro',
    descrizione TEXT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (operatore_id) REFERENCES operatori(id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_categoria (categoria),
    INDEX idx_data (data_upload)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella note clienti (storico interazioni)
CREATE TABLE IF NOT EXISTS note_clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    operatore_id INT NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    contenuto TEXT NOT NULL,
    tipo_nota ENUM('chiamata', 'email', 'incontro', 'promemoria', 'altro') DEFAULT 'altro',
    data_nota TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (operatore_id) REFERENCES operatori(id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_tipo (tipo_nota),
    INDEX idx_data (data_nota)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MODULO 4: GESTIONE LAVORAZIONI DI STUDIO
-- ============================================

-- Tabella settori
CREATE TABLE IF NOT EXISTS settori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT NULL,
    colore_hex VARCHAR(7) DEFAULT '#007849', -- Verde Datev Koinos
    is_attivo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nome (nome),
    INDEX idx_attivo (is_attivo)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella pratiche (impegni occasionali)
CREATE TABLE IF NOT EXISTS pratiche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settore_id INT NOT NULL,
    cliente_id INT NOT NULL,
    operatore_assegnato_id INT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT NULL,
    stato ENUM('da_iniziare', 'in_corso', 'completata', 'sospesa') DEFAULT 'da_iniziare',
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    data_scadenza DATE NULL,
    ore_stimate DECIMAL(5,2) DEFAULT 0.00,
    ore_lavorate DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (settore_id) REFERENCES settori(id),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (operatore_assegnato_id) REFERENCES operatori(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_scadenza (data_scadenza),
    INDEX idx_cliente (cliente_id),
    INDEX idx_operatore (operatore_assegnato_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella adempimenti (impegni ricorrenti)
CREATE TABLE IF NOT EXISTS adempimenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settore_id INT NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT NULL,
    frequenza ENUM('mensile', 'trimestrale', 'semestrale', 'annuale') NOT NULL,
    giorno_scadenza INT NOT NULL COMMENT 'Giorno del mese (1-31)',
    mese_scadenza INT NULL COMMENT 'Mese specifico per frequenze annuali (1-12)',
    ore_stimate DECIMAL(5,2) DEFAULT 0.00,
    is_attivo BOOLEAN DEFAULT TRUE,
    applicabile_a ENUM('tutti_clienti', 'clienti_specifici') DEFAULT 'clienti_specifici',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (settore_id) REFERENCES settori(id),
    INDEX idx_frequenza (frequenza),
    INDEX idx_attivo (is_attivo)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella relazione adempimenti-clienti
CREATE TABLE IF NOT EXISTS adempimenti_clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adempimento_id INT NOT NULL,
    cliente_id INT NOT NULL,
    is_attivo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (adempimento_id) REFERENCES adempimenti(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    UNIQUE KEY uk_adempimento_cliente (adempimento_id, cliente_id),
    INDEX idx_attivo (is_attivo)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella task
CREATE TABLE IF NOT EXISTS task (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pratica_id INT NULL,
    adempimento_id INT NULL,
    cliente_id INT NOT NULL,
    operatore_assegnato_id INT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT NULL,
    data_scadenza DATE NOT NULL,
    stato ENUM('da_iniziare', 'in_corso', 'completato', 'sospeso') DEFAULT 'da_iniziare',
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    ore_stimate DECIMAL(5,2) DEFAULT 0.00,
    ore_lavorate DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pratica_id) REFERENCES pratiche(id) ON DELETE CASCADE,
    FOREIGN KEY (adempimento_id) REFERENCES adempimenti(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (operatore_assegnato_id) REFERENCES operatori(id) ON DELETE SET NULL,
    INDEX idx_scadenza (data_scadenza),
    INDEX idx_stato (stato),
    INDEX idx_cliente (cliente_id),
    INDEX idx_operatore (operatore_assegnato_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MODULO 5: GESTIONE SCADENZE E APPUNTAMENTI
-- ============================================

-- Tabella festività
CREATE TABLE IF NOT EXISTS festivita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    data_festivita DATE NOT NULL,
    anno INT NOT NULL,
    is_ricorrente BOOLEAN DEFAULT FALSE,
    UNIQUE KEY uk_data_anno (data_festivita, anno),
    INDEX idx_anno (anno)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella appuntamenti
CREATE TABLE IF NOT EXISTS appuntamenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operatore_id INT NOT NULL,
    cliente_id INT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT NULL,
    data_appuntamento DATE NOT NULL,
    ora_inizio TIME NOT NULL,
    ora_fine TIME NOT NULL,
    tipo ENUM('incontro_cliente', 'telefonata', 'riunione_interna', 'altro') DEFAULT 'altro',
    stato ENUM('programmato', 'completato', 'annullato') DEFAULT 'programmato',
    promemoria_minuti INT DEFAULT 15,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operatore_id) REFERENCES operatori(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    INDEX idx_data (data_appuntamento),
    INDEX idx_operatore (operatore_id),
    INDEX idx_stato (stato)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MODULO 6: TRACKING E INTERRUZIONI
-- ============================================

-- Tabella tracking task
CREATE TABLE IF NOT EXISTS tracking_task (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    operatore_id INT NOT NULL,
    data_lavoro DATE NOT NULL,
    ora_inizio TIMESTAMP NOT NULL,
    ora_fine TIMESTAMP NULL,
    ore_lavorate DECIMAL(5,2) DEFAULT 0.00,
    note TEXT NULL,
    is_completato BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES task(id) ON DELETE CASCADE,
    FOREIGN KEY (operatore_id) REFERENCES operatori(id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_operatore (operatore_id),
    INDEX idx_data (data_lavoro)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella interruzioni
CREATE TABLE IF NOT EXISTS interruzioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operatore_id INT NOT NULL,
    cliente_id INT NULL,
    tipo_interruzione ENUM('telefonata', 'email', 'pausa_caffe', 'riunione', 'altro') NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT NULL,
    data_interruzione DATE NOT NULL,
    ora_inizio TIMESTAMP NOT NULL,
    ora_fine TIMESTAMP NULL,
    durata_minuti INT DEFAULT 0,
    ha_generato_appuntamento BOOLEAN DEFAULT FALSE,
    appuntamento_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operatore_id) REFERENCES operatori(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    FOREIGN KEY (appuntamento_id) REFERENCES appuntamenti(id) ON DELETE SET NULL,
    INDEX idx_operatore (operatore_id),
    INDEX idx_data (data_interruzione),
    INDEX idx_tipo (tipo_interruzione)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATI INIZIALI
-- ============================================

-- Inserimento settori predefiniti
INSERT IGNORE INTO settori (nome, descrizione, colore_hex) VALUES
('Amministrative', 'Pratiche amministrative generali', '#007849'),
('Contabili', 'Contabilità e bilanci', '#86B817'),
('Fiscali', 'Adempimenti fiscali e tributari', '#FFB500'),
('Tributarie', 'Gestione tributi e tasse', '#E60012'),
('Legali', 'Consulenza legale', '#2F5496'),
('Consulenza', 'Consulenza generale', '#7030A0');

-- Inserimento festività italiane 2025
INSERT IGNORE INTO festivita (nome, data_festivita, anno, is_ricorrente) VALUES
('Capodanno', '2025-01-01', 2025, true),
('Epifania', '2025-01-06', 2025, true),
('Pasqua', '2025-04-20', 2025, false),
('Lunedì dell\'Angelo', '2025-04-21', 2025, false),
('Festa della Liberazione', '2025-04-25', 2025, true),
('Festa del Lavoro', '2025-05-01', 2025, true),
('Festa della Repubblica', '2025-06-02', 2025, true),
('Ferragosto', '2025-08-15', 2025, true),
('Ognissanti', '2025-11-01', 2025, true),
('Immacolata Concezione', '2025-12-08', 2025, true),
('Natale', '2025-12-25', 2025, true),
('Santo Stefano', '2025-12-26', 2025, true);

-- Creazione utente amministratore predefinito
INSERT IGNORE INTO operatori (cognome, nome, email, password_hash, qualifiche, is_amministratore, is_attivo) VALUES
('Admin', 'Sistema', 'admin@redeconsulting.eu', '$argon2id$v=19$m=65536,t=4,p=3$QWRtaW5pc3RyYXRvcg$hash_temporaneo', '["Amministrazione", "Tutti i settori"]', true, true);

SET FOREIGN_KEY_CHECKS = 1;