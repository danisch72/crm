<?php
/**
 * UPDATE_OPERATORI_AUTH.PHP
 * 
 * Script per aggiornare automaticamente tutti i file del modulo operatori
 * per utilizzare il sistema di autenticazione esistente
 * 
 * BACKUP I FILE PRIMA DI ESEGUIRE QUESTO SCRIPT!
 * 
 * Uso: php update_operatori_auth.php
 */

// Definisci i file da modificare
$files = [
    'index.php',
    'create.php',
    'edit.php',
    'view.php',
    'stats.php',
    'sessions_data.php'
];

// Path base del modulo operatori
$basePath = __DIR__ . '/modules/operatori/';

// Blocco vecchio da sostituire (pattern regex)
$oldPattern = '/\/\/ Avvia sessione.*?\\$db = Database::getInstance\(\);/s';

// Nuovo blocco da inserire
$newBlock = '// Include bootstrap che gestisce l\'autenticazione
require_once dirname(dirname(__DIR__)) . \'/core/bootstrap.php\';

// Carica Database e helpers
loadDatabase();
loadHelpers();

// A questo punto siamo già autenticati (bootstrap ha verificato)
// Ottieni info utente corrente
$currentUser = getCurrentUser();
if (!$currentUser) {
    header(\'Location: \' . LOGIN_URL);
    exit;
}

// Prepara sessionInfo nel formato che il modulo si aspetta
$sessionInfo = [
    \'operatore_id\' => $currentUser[\'id\'],
    \'nome\' => $currentUser[\'nome\'],
    \'cognome\' => $currentUser[\'cognome\'],
    \'email\' => $currentUser[\'email\'],
    \'nome_completo\' => $currentUser[\'nome\'] . \' \' . $currentUser[\'cognome\'],
    \'is_admin\' => $currentUser[\'is_admin\'],
    \'is_amministratore\' => $currentUser[\'is_admin\'] // alias
];

$db = Database::getInstance();';

// Contatori
$updated = 0;
$errors = 0;

echo "🔧 AGGIORNAMENTO MODULO OPERATORI PER SISTEMA AUTH\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Processa ogni file
foreach ($files as $file) {
    $filePath = $basePath . $file;
    
    echo "📄 Elaborazione $file... ";
    
    if (!file_exists($filePath)) {
        echo "❌ NON TROVATO\n";
        $errors++;
        continue;
    }
    
    // Backup del file
    $backupPath = $filePath . '.backup_' . date('YmdHis');
    if (!copy($filePath, $backupPath)) {
        echo "❌ ERRORE BACKUP\n";
        $errors++;
        continue;
    }
    
    // Leggi contenuto
    $content = file_get_contents($filePath);
    
    // Sostituisci il blocco di autenticazione
    $newContent = preg_replace($oldPattern, $newBlock, $content, 1, $count);
    
    if ($count === 0) {
        echo "⚠️  PATTERN NON TROVATO (potrebbe essere già aggiornato)\n";
        continue;
    }
    
    // Modifiche specifiche per file
    if ($file === 'create.php' || $file === 'edit.php') {
        // Sostituisci PASSWORD_ARGON2ID con AUTH_PASSWORD_ALGO
        $newContent = str_replace('PASSWORD_ARGON2ID', 'AUTH_PASSWORD_ALGO', $newContent);
        
        // Sostituisci campo password con password_hash nelle query
        $newContent = preg_replace(
            '/INSERT INTO operatori \(([^)]*)\bpassword\b/',
            'INSERT INTO operatori ($1password_hash',
            $newContent
        );
        
        $newContent = preg_replace(
            '/UPDATE operatori SET ([^)]*)\bpassword\s*=/',
            'UPDATE operatori SET $1password_hash =',
            $newContent
        );
    }
    
    // Scrivi il file aggiornato
    if (file_put_contents($filePath, $newContent)) {
        echo "✅ AGGIORNATO (backup: $backupPath)\n";
        $updated++;
    } else {
        echo "❌ ERRORE SCRITTURA\n";
        $errors++;
    }
}

echo "\n" . str_repeat("=", 52) . "\n";
echo "📊 RIEPILOGO:\n";
echo "   ✅ File aggiornati: $updated\n";
echo "   ❌ Errori: $errors\n";
echo "   📁 Backup creati nella stessa directory\n";

if ($errors === 0) {
    echo "\n✨ Aggiornamento completato con successo!\n";
    echo "   Testa ora il modulo operatori per verificare che tutto funzioni.\n";
} else {
    echo "\n⚠️  Ci sono stati errori. Controlla i file manualmente.\n";
}

echo "\n💡 PROSSIMI PASSI:\n";
echo "1. Testa il login al sistema\n";
echo "2. Verifica che la lista operatori si carichi\n";
echo "3. Prova a creare un nuovo operatore\n";
echo "4. Se tutto funziona, puoi eliminare i file .backup_*\n";
echo "5. Se ci sono problemi, ripristina dai backup\n\n";