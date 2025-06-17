<?php
/**
 * check_db_structure.php - Verifica struttura tabella clienti
 * 
 * CARICARE QUESTO FILE TEMPORANEAMENTE SUL SERVER
 * Accedere via browser: www.redeconsulting.eu/crm/check_db_structure.php
 */

// Carica bootstrap per connessione DB
require_once __DIR__ . '/core/bootstrap.php';

// Verifica autenticazione
if (!isAuthenticated() || !getCurrentUser()['is_admin']) {
    die('Accesso negato - Solo amministratori');
}

// Carica Database
loadDatabase();
$db = Database::getInstance();

echo "<h1>Verifica Struttura Database CRM</h1>";
echo "<style>
    table { border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f4f4f4; }
    .missing { background: #ffcccc; }
    .ok { background: #ccffcc; }
    .warning { background: #ffffcc; }
</style>";

// 1. Verifica esistenza tabella clienti
echo "<h2>1. Verifica Tabella CLIENTI</h2>";
try {
    $result = $db->select("SHOW TABLES LIKE 'clienti'");
    if (empty($result)) {
        echo "<p class='missing'>❌ TABELLA 'clienti' NON ESISTE!</p>";
    } else {
        echo "<p class='ok'>✅ Tabella 'clienti' trovata</p>";
        
        // 2. Mostra struttura attuale
        echo "<h3>Struttura attuale:</h3>";
        $columns = $db->select("SHOW COLUMNS FROM clienti");
        
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $existingColumns = [];
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
            $existingColumns[] = $col['Field'];
        }
        echo "</table>";
        
        // 3. Verifica colonne richieste
        echo "<h3>Verifica colonne richieste:</h3>";
        $requiredColumns = [
            'id' => 'INT PRIMARY KEY',
            'ragione_sociale' => 'VARCHAR(255)',
            'tipologia_azienda' => 'ENUM',
            'codice_fiscale' => 'VARCHAR(16)',
            'partita_iva' => 'VARCHAR(11)',
            'indirizzo' => 'VARCHAR(255)',
            'cap' => 'VARCHAR(5)',
            'citta' => 'VARCHAR(100)',
            'provincia' => 'VARCHAR(2)',
            'telefono' => 'VARCHAR(50)',
            'email' => 'VARCHAR(255)',
            'pec' => 'VARCHAR(255)',
            'operatore_responsabile_id' => 'INT (FK)',
            'stato' => 'ENUM',
            'note' => 'TEXT',
            'created_by' => 'INT',
            'created_at' => 'TIMESTAMP',
            'updated_at' => 'TIMESTAMP'
        ];
        
        echo "<table>";
        echo "<tr><th>Colonna Richiesta</th><th>Tipo Atteso</th><th>Stato</th></tr>";
        
        foreach ($requiredColumns as $col => $type) {
            $exists = in_array($col, $existingColumns);
            $class = $exists ? 'ok' : 'missing';
            $status = $exists ? '✅ Presente' : '❌ MANCANTE';
            
            echo "<tr class='$class'>";
            echo "<td><strong>$col</strong></td>";
            echo "<td>$type</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='missing'>❌ Errore: " . $e->getMessage() . "</p>";
}

// 4. Verifica altre tabelle correlate
echo "<h2>2. Verifica Tabelle Correlate</h2>";
$relatedTables = ['pratiche', 'documenti_clienti', 'comunicazioni_clienti'];

foreach ($relatedTables as $table) {
    $result = $db->select("SHOW TABLES LIKE '$table'");
    if (empty($result)) {
        echo "<p class='warning'>⚠️ Tabella '$table' non trovata (opzionale)</p>";
    } else {
        echo "<p class='ok'>✅ Tabella '$table' presente</p>";
    }
}

// 5. Test query problematiche
echo "<h2>3. Test Query Problematiche</h2>";

// Test query dalla lista clienti
try {
    $testQuery = "SELECT c.*, o.nome FROM clienti c 
                  LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id 
                  LIMIT 1";
    $db->select($testQuery);
    echo "<p class='ok'>✅ Query con JOIN su operatore_responsabile_id: OK</p>";
} catch (Exception $e) {
    echo "<p class='missing'>❌ Query con JOIN fallita: " . $e->getMessage() . "</p>";
}

// Test query con stato
try {
    $testQuery = "SELECT COUNT(*) as total FROM clienti WHERE stato = 'attivo'";
    $db->select($testQuery);
    echo "<p class='ok'>✅ Query con campo 'stato': OK</p>";
} catch (Exception $e) {
    echo "<p class='missing'>❌ Query con 'stato' fallita: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>📋 ISTRUZIONI:</h2>";
echo "<ol>";
echo "<li>Se ci sono colonne MANCANTI (in rosso), esegui lo script SQL fornito</li>";
echo "<li>Fai un backup del database PRIMA di eseguire modifiche</li>";
echo "<li>Dopo aver eseguito lo script SQL, ricarica questa pagina per verificare</li>";
echo "<li>Una volta risolto, ELIMINA questo file dal server per sicurezza</li>";
echo "</ol>";

echo "<p><strong>Data verifica:</strong> " . date('Y-m-d H:i:s') . "</p>";