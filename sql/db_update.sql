-- Script di aggiornamento database CRM Re.De Consulting
-- Da eseguire se il database esiste già

-- Aggiunge colonna note alla tabella sessioni_lavoro se non esiste
ALTER TABLE sessioni_lavoro 
ADD COLUMN IF NOT EXISTS note TEXT NULL COMMENT 'Note sulla sessione (es. chiusura automatica)' 
AFTER is_attiva;

-- Chiude eventuali sessioni orfane (più vecchie di 24 ore)
UPDATE sessioni_lavoro 
SET is_attiva = 0,
    note = 'Sessione chiusa automaticamente - timeout sistema',
    logout_timestamp = login_timestamp + INTERVAL 12 HOUR,
    ore_effettive = LEAST(12, ore_contratto + 1),
    ore_extra = GREATEST(0, LEAST(12, ore_contratto + 1) - ore_contratto)
WHERE is_attiva = 1 
AND login_timestamp < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Crea indice per ottimizzare le query di cleanup
CREATE INDEX IF NOT EXISTS idx_sessioni_cleanup 
ON sessioni_lavoro (is_attiva, login_timestamp);

-- Aggiorna password admin se è ancora quella temporanea
UPDATE operatori 
SET password_hash = '$argon2id$v=19$m=65536,t=4,p=3$UmVEZUNvbnN1bHRpbmc$jJ8rKH8P9V7aQ6E5TYJ+XrGt2m4S9hB7cF8qL3nW1xY'
WHERE email = 'admin@redeconsulting.eu' 
AND password_hash = '$argon2id$v=19$m=65536,t=4,p=3$QWRtaW5pc3RyYXRvcg$hash_temporaneo';

-- Password aggiornata: "admin123" (CAMBIARE IMMEDIATAMENTE!)

SELECT 'Database aggiornato con successo!' as status;