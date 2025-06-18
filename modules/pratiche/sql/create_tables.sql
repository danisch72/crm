-- ============================================
-- MODULO PRATICHE - CREAZIONE TABELLE
-- CRM Re.De Consulting
-- ============================================

-- Verifica e aggiorna tabella pratiche esistente
ALTER TABLE pratiche 
ADD COLUMN IF NOT EXISTS template_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS progress_percentage INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS totale_ore_stimate DECIMAL(8,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS totale_ore_lavorate DECIMAL(8,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS costo_totale DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media';

-- Verifica e aggiorna tabella task esistente
ALTER TABLE task 
ADD COLUMN IF NOT EXISTS pratica_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ordine INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_obbligatorio BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS dipende_da_task_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS ore_stimate DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS ore_lavorate DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS richiede_conferma BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS conferma_richiesta_a INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS conferma_richiesta_da INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS conferma_data TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS stato ENUM('da_fare', 'in_corso', 'completato', 'bloccato') DEFAULT 'da_fare',
ADD COLUMN IF NOT EXISTS percentuale_completamento INT DEFAULT 0;

-- Aggiungi foreign key se non esistono
ALTER TABLE task
ADD CONSTRAINT fk_task_pratica 
FOREIGN KEY IF NOT EXISTS (pratica_id) 
REFERENCES pratiche(id) ON DELETE CASCADE;

ALTER TABLE task
ADD CONSTRAINT fk_task_dipendenza
FOREIGN KEY IF NOT EXISTS (dipende_da_task_id) 
REFERENCES task(id) ON DELETE SET NULL;

-- Indici per performance
CREATE INDEX IF NOT EXISTS idx_task_pratica ON task(pratica_id);
CREATE INDEX IF NOT EXISTS idx_task_ordine ON task(pratica_id, ordine);
CREATE INDEX IF NOT EXISTS idx_task_stato ON task(stato);

-- ============================================
-- NUOVA TABELLA: pratiche_template
-- ============================================

CREATE TABLE IF NOT EXISTS pratiche_template (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    tipo_pratica ENUM(
        'dichiarazione_redditi', 'dichiarazione_iva', 'bilancio_ordinario',
        'bilancio_semplificato', 'costituzione_societa', 'modifica_societaria',
        'pratiche_inps', 'pratiche_camera_commercio', 'contrattualistica',
        'consulenza_fiscale', 'consulenza_lavoro', 'altra'
    ) NOT NULL,
    descrizione TEXT,
    ore_totali_stimate DECIMAL(6,2) DEFAULT 0.00,
    tariffa_consigliata DECIMAL(8,2) DEFAULT 0.00,
    giorni_completamento INT DEFAULT 30,
    documenti_necessari JSON,
    note_operative TEXT,
    is_attivo BOOLEAN DEFAULT TRUE,
    utilizzi_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES operatori(id),
    INDEX idx_tipo_attivo (tipo_pratica, is_attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NUOVA TABELLA: pratiche_template_task
-- ============================================

CREATE TABLE IF NOT EXISTS pratiche_template_task (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    ordine INT DEFAULT 0,
    ore_stimate DECIMAL(5,2) DEFAULT 0.00,
    is_obbligatorio BOOLEAN DEFAULT TRUE,
    dipende_da_ordine INT DEFAULT NULL,
    giorni_dopo_inizio INT DEFAULT 0,
    documenti_richiesti JSON,
    istruzioni TEXT,
    competenze_richieste JSON,
    
    FOREIGN KEY (template_id) REFERENCES pratiche_template(id) ON DELETE CASCADE,
    INDEX idx_template_ordine (template_id, ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATI DI ESEMPIO: Template Dichiarazione IVA
-- ============================================

INSERT INTO pratiche_template 
(nome, tipo_pratica, descrizione, ore_totali_stimate, tariffa_consigliata, giorni_completamento) 
VALUES
('Dichiarazione IVA Trimestrale', 'dichiarazione_iva', 
 'Template standard per liquidazione IVA trimestrale con invio telematico', 
 8.00, 450.00, 20);

SET @template_iva = LAST_INSERT_ID();

INSERT INTO pratiche_template_task 
(template_id, titolo, descrizione, ordine, ore_stimate, is_obbligatorio, giorni_dopo_inizio, documenti_richiesti) 
VALUES
(@template_iva, 'Download fatture da Agenzia Entrate', 
 'Scaricare tutte le fatture del trimestre dal cassetto fiscale', 
 1, 1.00, TRUE, 0, '["Credenziali cassetto fiscale"]'),

(@template_iva, 'Registrazione fatture in contabilità', 
 'Registrare tutte le fatture attive e passive nel sistema contabile', 
 2, 3.00, TRUE, 1, '["Fatture elettroniche", "Fatture cartacee"]'),

(@template_iva, 'Controllo e quadratura', 
 'Verificare correttezza registrazioni e quadratura IVA', 
 3, 1.50, TRUE, 2, NULL),

(@template_iva, 'Preparazione liquidazione', 
 'Calcolare IVA a debito/credito e preparare prospetto liquidazione', 
 4, 1.00, TRUE, 3, NULL),

(@template_iva, 'Generazione F24', 
 'Creare modello F24 per versamento IVA se a debito', 
 5, 0.50, TRUE, 3, NULL),

(@template_iva, 'Comunicazione al cliente', 
 'Inviare prospetto liquidazione e F24 al cliente per approvazione', 
 6, 0.50, TRUE, 4, '["Email cliente"]'),

(@template_iva, 'Invio telematico', 
 'Trasmettere liquidazione IVA tramite canale Entratel/Fisconline', 
 7, 0.50, TRUE, 15, '["Delega invio telematico"]');

-- ============================================
-- DATI DI ESEMPIO: Template Bilancio
-- ============================================

INSERT INTO pratiche_template 
(nome, tipo_pratica, descrizione, ore_totali_stimate, tariffa_consigliata, giorni_completamento) 
VALUES
('Bilancio Ordinario Società', 'bilancio_ordinario', 
 'Template completo per redazione bilancio ordinario con nota integrativa', 
 20.00, 2500.00, 45);

SET @template_bilancio = LAST_INSERT_ID();

INSERT INTO pratiche_template_task 
(template_id, titolo, descrizione, ordine, ore_stimate, is_obbligatorio, giorni_dopo_inizio) 
VALUES
(@template_bilancio, 'Richiesta documentazione', 
 'Richiedere al cliente tutta la documentazione necessaria', 
 1, 0.50, TRUE, 0),

(@template_bilancio, 'Verifica completezza contabilità', 
 'Controllare che tutte le registrazioni siano complete e corrette', 
 2, 3.00, TRUE, 5),

(@template_bilancio, 'Scritture di assestamento', 
 'Registrare ammortamenti, ratei, risconti e altre scritture di fine anno', 
 3, 4.00, TRUE, 10),

(@template_bilancio, 'Redazione prospetti contabili', 
 'Preparare Stato Patrimoniale e Conto Economico', 
 4, 3.00, TRUE, 15),

(@template_bilancio, 'Redazione nota integrativa', 
 'Scrivere nota integrativa secondo schema OIC', 
 5, 5.00, TRUE, 20),

(@template_bilancio, 'Calcolo imposte', 
 'Determinare IRES e IRAP dovute', 
 6, 2.00, TRUE, 25),

(@template_bilancio, 'Revisione finale', 
 'Controllo finale di tutti i documenti di bilancio', 
 7, 1.50, TRUE, 30),

(@template_bilancio, 'Approvazione cliente', 
 'Presentare bozza bilancio al cliente per approvazione', 
 8, 1.00, TRUE, 35),

(@template_bilancio, 'Deposito Camera Commercio', 
 'Depositare bilancio approvato presso CCIAA', 
 9, 1.00, TRUE, 40);

-- ============================================
-- DATI DI ESEMPIO: Template 730
-- ============================================

INSERT INTO pratiche_template 
(nome, tipo_pratica, descrizione, ore_totali_stimate, tariffa_consigliata, giorni_completamento) 
VALUES
('Modello 730 Precompilato', 'dichiarazione_redditi', 
 'Assistenza compilazione 730 con modello precompilato', 
 3.00, 150.00, 10);

SET @template_730 = LAST_INSERT_ID();

INSERT INTO pratiche_template_task 
(template_id, titolo, ordine, ore_stimate, is_obbligatorio, giorni_dopo_inizio) 
VALUES
(@template_730, 'Acquisizione delega e documenti', 1, 0.50, TRUE, 0),
(@template_730, 'Download precompilata', 2, 0.25, TRUE, 1),
(@template_730, 'Verifica dati e integrazioni', 3, 1.00, TRUE, 2),
(@template_730, 'Calcolo detrazioni e deduzioni', 4, 0.75, TRUE, 3),
(@template_730, 'Approvazione contribuente', 5, 0.25, TRUE, 5),
(@template_730, 'Invio telematico', 6, 0.25, TRUE, 7);

-- ============================================
-- AGGIORNA CONTATORI
-- ============================================

UPDATE pratiche_template SET utilizzi_count = 0;

-- Fine script creazione tabelle