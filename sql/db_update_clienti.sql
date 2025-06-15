-- ============================================
-- AGGIORNAMENTO DATABASE MODULO CLIENTI
-- CRM Re.De Consulting - www.redeconsulting.eu/crm
-- ============================================
-- 
-- Questo script aggiorna il database esistente con le tabelle
-- necessarie per il Modulo 3: Gestione Clienti
-- 
-- ATTENZIONE: Eseguire questo script solo se le tabelle non esistono già!
-- 
-- ============================================

-- Verifica versione MySQL/MariaDB
SELECT VERSION() as mysql_version;

-- ============================================
-- 1. TABELLA PRINCIPALE CLIENTI
-- ============================================

-- Verifica se la tabella esiste già
SELECT COUNT(*) as table_exists 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'clienti';

-- Crea tabella clienti se non esiste
CREATE TABLE IF NOT EXISTS clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Codice univoco cliente
    codice_cliente VARCHAR(20) UNIQUE NOT NULL COMMENT 'Codice identificativo univoco (es: AZ2025001)',
    
    -- Dati anagrafici base
    ragione_sociale VARCHAR(255) NOT NULL COMMENT 'Ragione sociale o nome completo',
    tipo_cliente ENUM('persona_fisica', 'societa', 'ditta_individuale', 'associazione') DEFAULT 'societa' COMMENT 'Tipologia cliente',
    
    -- Dati fiscali identificativi
    codice_fiscale VARCHAR(20) NULL COMMENT 'Codice fiscale italiano (16 o 11 caratteri)',
    partita_iva VARCHAR(20) NULL COMMENT 'Partita IVA italiana (11 cifre)',
    codice_destinatario VARCHAR(7) NULL COMMENT 'Codice destinatario fatturazione elettronica',
    pec_destinatario VARCHAR(255) NULL COMMENT 'PEC per fatturazione elettronica',
    
    -- Contatti
    telefono VARCHAR(20) NULL COMMENT 'Numero telefono fisso',
    cellulare VARCHAR(20) NULL COMMENT 'Numero cellulare', 
    email VARCHAR(255) NULL COMMENT 'Email principale',
    pec VARCHAR(255) NULL COMMENT 'Posta elettronica certificata',
    sito_web VARCHAR(255) NULL COMMENT 'Sito web aziendale',
    
    -- Indirizzo
    indirizzo VARCHAR(255) NULL COMMENT 'Via, numero civico',
    cap VARCHAR(10) NULL COMMENT 'Codice avviamento postale',
    citta VARCHAR(100) NULL COMMENT 'Città',
    provincia VARCHAR(5) NULL COMMENT 'Sigla provincia (es: RM)',
    nazione VARCHAR(50) DEFAULT 'Italia' COMMENT 'Nazione',
    
    -- Dati commerciali e fiscali
    tipologia_azienda ENUM('individuale', 'srl', 'spa', 'snc', 'sas', 'altro') DEFAULT 'srl' COMMENT 'Forma giuridica',
    regime_fiscale ENUM('ordinario', 'semplificato', 'forfettario', 'altro') DEFAULT 'ordinario' COMMENT 'Regime fiscale',
    liquidazione_iva ENUM('mensile', 'trimestrale', 'annuale') DEFAULT 'mensile' COMMENT 'Frequenza liquidazione IVA',
    settore_attivita VARCHAR(255) NULL COMMENT 'Settore di attività',
    codice_ateco VARCHAR(10) NULL COMMENT 'Codice ATECO (XX.XX.XX)',
    forma_giuridica VARCHAR(100) NULL COMMENT 'Forma giuridica dettagliata',
    capitale_sociale DECIMAL(12,2) NULL COMMENT 'Capitale sociale per società',
    data_costituzione DATE NULL COMMENT 'Data costituzione società',
    
    -- Gestione account e operatori
    operatore_responsabile_id INT NULL COMMENT 'Operatore responsabile principale',
    operatore_commerciale_id INT NULL COMMENT 'Operatore commerciale di riferimento',
    stato ENUM('attivo', 'sospeso', 'chiuso') DEFAULT 'attivo' COMMENT 'Stato del cliente',
    categoria ENUM('standard', 'premium', 'vip') DEFAULT 'standard' COMMENT 'Categoria cliente',
    fatturato_annuo_stimato DECIMAL(12,2) NULL COMMENT 'Fatturato annuo stimato',
    
    -- Note e informazioni aggiuntive
    note_generali TEXT NULL COMMENT 'Note libere sul cliente',
    preferenze_comunicazione JSON NULL COMMENT 'Preferenze di comunicazione in JSON',
    documenti_richiesti JSON NULL COMMENT 'Lista documenti ancora da acquisire',
    
    -- Campi sistema
    is_attivo BOOLEAN DEFAULT TRUE COMMENT 'Cliente attivo/disattivato',
    created_by INT NULL COMMENT 'Operatore che ha creato il record',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/ora creazione',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/ora ultima modifica',
    
    -- Indici e vincoli
    UNIQUE KEY uk_codice_cliente (codice_cliente),
    UNIQUE KEY uk_codice_fiscale (codice_fiscale),
    UNIQUE KEY uk_partita_iva (partita_iva),
    
    INDEX idx_ragione_sociale (ragione_sociale),
    INDEX idx_tipo_cliente (tipo_cliente),
    INDEX idx_tipologia_azienda (tipologia_azienda),
    INDEX idx_stato (stato),
    INDEX idx_is_attivo (is_attivo),
    INDEX idx_operatore_responsabile (operatore_responsabile_id),
    INDEX idx_created_at (created_at),
    INDEX idx_email (email),
    INDEX idx_telefono (telefono),
    INDEX idx_citta_provincia (citta, provincia),
    
    -- Foreign keys (se tabella operatori esiste)
    FOREIGN KEY fk_clienti_operatore_responsabile (operatore_responsabile_id) 
        REFERENCES operatori(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY fk_clienti_operatore_commerciale (operatore_commerciale_id) 
        REFERENCES operatori(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY fk_clienti_created_by (created_by) 
        REFERENCES operatori(id) ON DELETE SET NULL ON UPDATE CASCADE
        
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Clienti e prospect dello studio commercialista';

-- ============================================
-- 2. TABELLA DOCUMENTI CLIENTI
-- ============================================

CREATE TABLE IF NOT EXISTS documenti_clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relazioni
    cliente_id INT NOT NULL COMMENT 'ID cliente proprietario',
    operatore_id INT NOT NULL COMMENT 'Operatore che ha caricato il documento',
    
    -- Informazioni file
    nome_file VARCHAR(255) NOT NULL COMMENT 'Nome file sul server',
    nome_originale VARCHAR(255) NOT NULL COMMENT 'Nome file originale caricato',
    path_file VARCHAR(500) NOT NULL COMMENT 'Percorso completo file sul server',
    dimensione_file INT NOT NULL COMMENT 'Dimensione file in bytes',
    tipo_mime VARCHAR(100) NOT NULL COMMENT 'Tipo MIME del file',
    hash_file VARCHAR(64) NULL COMMENT 'Hash SHA256 per verifica integrità',
    
    -- Categorizzazione e metadati
    categoria ENUM('contratto', 'documento_identita', 'certificato', 'fattura', 'bilancio', 'dichiarazione', 'visura', 'altro') DEFAULT 'altro' COMMENT 'Categoria documento',
    sottocategoria VARCHAR(100) NULL COMMENT 'Sottocategoria specifica',
    anno_riferimento YEAR NULL COMMENT 'Anno di riferimento del documento',
    mese_riferimento TINYINT NULL COMMENT 'Mese di riferimento (1-12)',
    descrizione TEXT NULL COMMENT 'Descrizione dettagliata del documento',
    tags JSON NULL COMMENT 'Tag per ricerca rapida',
    
    -- Gestione accessi e sicurezza
    is_riservato BOOLEAN DEFAULT FALSE COMMENT 'Documento riservato (solo admin)',
    is_firmato_digitalmente BOOLEAN DEFAULT FALSE COMMENT 'Documento con firma digitale',
    password_protetto BOOLEAN DEFAULT FALSE COMMENT 'Documento protetto da password',
    operatori_autorizzati JSON NULL COMMENT 'Lista ID operatori autorizzati alla visualizzazione',
    
    -- Scadenze e validità
    data_scadenza DATE NULL COMMENT 'Data scadenza documento (se applicabile)',
    giorni_preavviso_scadenza INT DEFAULT 30 COMMENT 'Giorni preavviso scadenza',
    is_scaduto BOOLEAN DEFAULT FALSE COMMENT 'Flag documento scaduto',
    
    -- Versioning e storico
    versione INT DEFAULT 1 COMMENT 'Versione del documento',
    documento_padre_id INT NULL COMMENT 'ID documento originale (per versioning)',
    sostituisce_documento_id INT NULL COMMENT 'ID documento che questo sostituisce',
    
    -- Campi sistema
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/ora upload',
    ultimo_accesso TIMESTAMP NULL COMMENT 'Data/ora ultimo accesso al documento',
    numero_accessi INT DEFAULT 0 COMMENT 'Contatore accessi al documento',
    
    -- Indici e vincoli
    INDEX idx_cliente (cliente_id),
    INDEX idx_operatore (operatore_id),
    INDEX idx_categoria (categoria),
    INDEX idx_anno_riferimento (anno_riferimento),
    INDEX idx_data_upload (data_upload),
    INDEX idx_data_scadenza (data_scadenza),
    INDEX idx_is_riservato (is_riservato),
    INDEX idx_is_scaduto (is_scaduto),
    INDEX idx_nome_file (nome_file),
    INDEX idx_hash_file (hash_file),
    
    -- Foreign keys
    FOREIGN KEY fk_documenti_cliente (cliente_id) 
        REFERENCES clienti(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY fk_documenti_operatore (operatore_id) 
        REFERENCES operatori(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY fk_documenti_padre (documento_padre_id) 
        REFERENCES documenti_clienti(id) ON DELETE SET NULL ON UPDATE CASCADE
        
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Documenti e allegati associati ai clienti';

-- ============================================
-- 3. TABELLA NOTE E COMUNICAZIONI CLIENTI
-- ============================================

CREATE TABLE IF NOT EXISTS note_clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relazioni
    cliente_id INT NOT NULL COMMENT 'ID cliente',
    operatore_id INT NOT NULL COMMENT 'Operatore che ha creato la nota',
    
    -- Contenuto nota
    titolo VARCHAR(255) NOT NULL COMMENT 'Titolo/oggetto della comunicazione',
    contenuto TEXT NOT NULL COMMENT 'Contenuto dettagliato della nota',
    
    -- Tipologia e categorizzazione
    tipo_nota ENUM('chiamata', 'email', 'incontro', 'promemoria', 'task', 'alert', 'altro') DEFAULT 'altro' COMMENT 'Tipo di comunicazione',
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media' COMMENT 'Priorità della nota',
    stato ENUM('aperta', 'in_corso', 'completata', 'annullata') DEFAULT 'aperta' COMMENT 'Stato della nota/task',
    
    -- Dettagli comunicazione
    canale_comunicazione VARCHAR(50) NULL COMMENT 'Canale usato (telefono, email, whatsapp, etc)',
    durata_minuti INT NULL COMMENT 'Durata comunicazione in minuti',
    partecipanti JSON NULL COMMENT 'Lista partecipanti alla comunicazione',
    
    -- Follow-up e azioni
    richiede_followup BOOLEAN DEFAULT FALSE COMMENT 'Richiede azione di follow-up',
    data_followup DATE NULL COMMENT 'Data prevista per follow-up',
    operatore_followup_id INT NULL COMMENT 'Operatore assegnato al follow-up',
    followup_completato BOOLEAN DEFAULT FALSE COMMENT 'Follow-up completato',
    
    -- Collegamento con pratiche e documenti
    pratica_collegata_id INT NULL COMMENT 'ID pratica collegata (se esiste)',
    documento_collegato_id INT NULL COMMENT 'ID documento collegato',
    
    -- Metadati aggiuntivi
    tags JSON NULL COMMENT 'Tag per categorizzazione rapida',
    allegati JSON NULL COMMENT 'Lista file allegati alla nota',
    coordinate_gps VARCHAR(50) NULL COMMENT 'Coordinate GPS se incontro in loco',
    
    -- Campi sistema
    data_nota TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/ora della nota/comunicazione',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/ora creazione record',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/ora ultima modifica',
    
    -- Indici
    INDEX idx_cliente (cliente_id),
    INDEX idx_operatore (operatore_id),
    INDEX idx_tipo_nota (tipo_nota),
    INDEX idx_data_nota (data_nota),
    INDEX idx_priorita (priorita),
    INDEX idx_stato (stato),
    INDEX idx_richiede_followup (richiede_followup),
    INDEX idx_data_followup (data_followup),
    INDEX idx_cliente_data (cliente_id, data_nota),
    INDEX idx_operatore_data (operatore_id, data_nota),
    
    -- Foreign keys
    FOREIGN KEY fk_note_cliente (cliente_id) 
        REFERENCES clienti(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY fk_note_operatore (operatore_id) 
        REFERENCES operatori(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY fk_note_operatore_followup (operatore_followup_id) 
        REFERENCES operatori(id) ON DELETE SET NULL ON UPDATE CASCADE
        
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Note, comunicazioni e attività associate ai clienti';

-- ============================================
-- 4. TABELLA CONTATTI AGGIUNTIVI CLIENTI
-- ============================================

CREATE TABLE IF NOT EXISTS contatti_clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relazione
    cliente_id INT NOT NULL COMMENT 'ID cliente',
    
    -- Dati contatto
    nome VARCHAR(100) NOT NULL COMMENT 'Nome del contatto',
    cognome VARCHAR(100) NOT NULL COMMENT 'Cognome del contatto',
    ruolo VARCHAR(100) NULL COMMENT 'Ruolo/posizione nel cliente',
    
    -- Recapiti
    telefono_diretto VARCHAR(20) NULL COMMENT 'Telefono diretto',
    cellulare VARCHAR(20) NULL COMMENT 'Cellulare personale',
    email VARCHAR(255) NULL COMMENT 'Email personale',
    
    -- Informazioni aggiuntive
    note VARCHAR(500) NULL COMMENT 'Note sul contatto',
    is_contatto_principale BOOLEAN DEFAULT FALSE COMMENT 'Contatto principale del cliente',
    is_autorizzato_fatturazione BOOLEAN DEFAULT FALSE COMMENT 'Autorizzato per questioni di fatturazione',
    is_autorizzato_fiscale BOOLEAN DEFAULT FALSE COMMENT 'Autorizzato per questioni fiscali',
    
    -- Campi sistema
    is_attivo BOOLEAN DEFAULT TRUE COMMENT 'Contatto attivo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indici
    INDEX idx_cliente (cliente_id),
    INDEX idx_nome_cognome (nome, cognome),
    INDEX idx_email (email),
    INDEX idx_is_contatto_principale (is_contatto_principale),
    INDEX idx_is_attivo (is_attivo),
    
    -- Foreign key
    FOREIGN KEY fk_contatti_cliente (cliente_id) 
        REFERENCES clienti(id) ON DELETE CASCADE ON UPDATE CASCADE
        
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci 
  COMMENT='Contatti aggiuntivi per ogni cliente';

-- ============================================
-- 5. TRIGGER PER AUTOMAZIONI
-- ============================================

-- Trigger per aggiornare automaticamente il campo updated_at
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_clienti_updated_at
    BEFORE UPDATE ON clienti
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

-- Trigger per generare codice cliente automatico se vuoto
CREATE TRIGGER IF NOT EXISTS tr_clienti_codice_auto
    BEFORE INSERT ON clienti
    FOR EACH ROW
BEGIN
    IF NEW.codice_cliente IS NULL OR NEW.codice_cliente = '' THEN
        SET @next_number = (
            SELECT COALESCE(MAX(CAST(SUBSTRING(codice_cliente, 7) AS UNSIGNED)), 0) + 1
            FROM clienti 
            WHERE codice_cliente LIKE CONCAT(
                CASE NEW.tipo_cliente
                    WHEN 'persona_fisica' THEN 'PF'
                    ELSE 'AZ'
                END,
                YEAR(NOW()), '%'
            )
        );
        
        SET NEW.codice_cliente = CONCAT(
            CASE NEW.tipo_cliente
                WHEN 'persona_fisica' THEN 'PF'
                ELSE 'AZ'
            END,
            YEAR(NOW()),
            LPAD(@next_number, 4, '0')
        );
    END IF;
END$$

-- Trigger per log automatico quando si crea una nota
CREATE TRIGGER IF NOT EXISTS tr_note_clienti_log
    AFTER INSERT ON note_clienti
    FOR EACH ROW
BEGIN
    -- Aggiorna contatore comunicazioni (se campo esiste)
    UPDATE clienti 
    SET updated_at = CURRENT_TIMESTAMP 
    WHERE id = NEW.cliente_id;
END$$

DELIMITER ;

-- ============================================
-- 6. VISTE UTILI PER REPORTING
-- ============================================

-- Vista riepilogativa clienti con statistiche
CREATE OR REPLACE VIEW v_clienti_summary AS
SELECT 
    c.id,
    c.codice_cliente,
    c.ragione_sociale,
    c.codice_fiscale,
    c.partita_iva,
    c.email,
    c.telefono,
    c.tipologia_azienda,
    c.regime_fiscale,
    c.is_attivo,
    c.created_at,
    
    -- Operatore responsabile
    CONCAT(o.nome, ' ', o.cognome) as operatore_responsabile,
    
    -- Statistiche correlate
    COUNT(DISTINCT nc.id) as totale_note,
    COUNT(DISTINCT dc.id) as totale_documenti,
    MAX(nc.data_nota) as ultima_comunicazione,
    
    -- Conteggi per tipologia nota
    SUM(CASE WHEN nc.tipo_nota = 'chiamata' THEN 1 ELSE 0 END) as chiamate,
    SUM(CASE WHEN nc.tipo_nota = 'email' THEN 1 ELSE 0 END) as email,
    SUM(CASE WHEN nc.tipo_nota = 'incontro' THEN 1 ELSE 0 END) as incontri,
    
    -- Flag di stato
    CASE 
        WHEN MAX(nc.data_nota) > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Attivo'
        WHEN MAX(nc.data_nota) > DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'Poco Attivo'
        ELSE 'Inattivo'
    END as stato_attivita

FROM clienti c
LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
LEFT JOIN note_clienti nc ON c.id = nc.cliente_id
LEFT JOIN documenti_clienti dc ON c.id = dc.cliente_id
GROUP BY c.id, c.codice_cliente, c.ragione_sociale, c.codice_fiscale, 
         c.partita_iva, c.email, c.telefono, c.tipologia_azienda, 
         c.regime_fiscale, c.is_attivo, c.created_at, o.nome, o.cognome;

-- ============================================
-- 7. DATI DI ESEMPIO (OPZIONALE)
-- ============================================

-- Inserisci alcuni clienti di esempio solo se la tabella è vuota
INSERT IGNORE INTO clienti (
    codice_cliente, ragione_sociale, tipo_cliente, tipologia_azienda,
    codice_fiscale, partita_iva, email, telefono, 
    indirizzo, cap, citta, provincia,
    regime_fiscale, liquidazione_iva, settore_attivita,
    operatore_responsabile_id, is_attivo, created_by
) 
SELECT 
    'AZ2025001' as codice_cliente,
    'ESEMPIO SRL' as ragione_sociale,
    'societa' as tipo_cliente,
    'srl' as tipologia_azienda,
    '12345678901' as codice_fiscale,
    '98765432109' as partita_iva,
    'info@esempio.it' as email,
    '+39 06 123456789' as telefono,
    'Via Roma 123' as indirizzo,
    '00100' as cap,
    'Roma' as citta,
    'RM' as provincia,
    'ordinario' as regime_fiscale,
    'mensile' as liquidazione_iva,
    'Servizi informatici' as settore_attivita,
    1 as operatore_responsabile_id,
    1 as is_attivo,
    1 as created_by
WHERE NOT EXISTS (SELECT 1 FROM clienti LIMIT 1);

-- ============================================
-- 8. VERIFICA FINALE E REPORT
-- ============================================

-- Verifica che tutte le tabelle siano state create
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('clienti', 'documenti_clienti', 'note_clienti', 'contatti_clienti')
ORDER BY table_name;

-- Verifica foreign keys
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_NAME IN ('clienti', 'documenti_clienti', 'note_clienti', 'contatti_clienti')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Report finale
SELECT 
    'Modulo Clienti installato con successo!' as status,
    NOW() as installation_date,
    DATABASE() as database_name,
    VERSION() as mysql_version;

-- ============================================
-- FINE SCRIPT AGGIORNAMENTO DATABASE
-- ============================================