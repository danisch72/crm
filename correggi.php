<?php
/**
 * auto_fix_pratiche.php - Corregge automaticamente tutti gli errori trovati
 * 
 * CARICA SUL SERVER: www.redeconsulting.eu/crm/auto_fix_pratiche.php
 * 
 * ⚠️ IMPORTANTE: Fai un BACKUP dei file prima di eseguire!
 */

// Carica bootstrap
require_once __DIR__ . '/core/bootstrap.php';

// Verifica auth
if (!isAuthenticated() || !getCurrentUser()['is_admin']) {
    die('Accesso negato - Solo amministratori');
}

echo "<h1>Auto Fix Modulo Pratiche</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .ok { background: #d4edda; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; }
    .warning { background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeeba; }
    .info { background: #d1ecf1; padding: 10px; margin: 10px 0; border: 1px solid #bee5eb; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    .backup { background: #e7e7e7; padding: 15px; margin: 20px 0; border: 2px dashed #999; }
</style>";

// Configurazione fix
$basePath = __DIR__ . '/modules/pratiche/';
$fixes = [
    'index_list.php' => [
        [
            'find' => 'COALESCE(SUM(t.ore_lavorate), 0) as ore_totali_lavorate,',
            'replace' => 'COALESCE(p.ore_lavorate, 0) as ore_totali_lavorate,',
            'line' => 69
        ],
        [
            'find' => 'p.operatore_responsabile_id',
            'replace' => 'p.operatore_assegnato_id',
            'all' => true // Sostituisci tutte le occorrenze
        ],
        [
            'find' => "FIELD(p.stato, 'urgente', 'alta', 'media', 'bassa'),",
            'replace' => "FIELD(p.priorita, 'urgente', 'alta', 'media', 'bassa'),",
            'line' => 78
        ]
    ],
    'view.php' => [
        [
            'find' => 'p.operatore_responsabile_id',
            'replace' => 'p.operatore_assegnato_id',
            'all' => true
        ]
    ],
    'view copy 2.php' => [
        [
            'find' => 'p.operatore_responsabile_id',
            'replace' => 'p.operatore_assegnato_id',
            'all' => true
        ]
    ],
    'api/task_api copy 1.php' => [
        [
            'find' => 'p.operatore_responsabile_id',
            'replace' => 'p.operatore_assegnato_id',
            'all' => true
        ]
    ]
];

// Funzione per creare backup
function createBackup($filePath) {
    $backupPath = $filePath . '.backup_' . date('YmdHis');
    if (copy($filePath, $backupPath)) {
        return $backupPath;
    }
    return false;
}

// Funzione per applicare fix
function applyFix($filePath, $fixes) {
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'File non trovato'];
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $changes = 0;
    
    foreach ($fixes as $fix) {
        if (isset($fix['all']) && $fix['all']) {
            // Sostituisci tutte le occorrenze
            $count = 0;
            $content = str_replace($fix['find'], $fix['replace'], $content, $count);
            $changes += $count;
        } else {
            // Sostituisci singola occorrenza
            if (strpos($content, $fix['find']) !== false) {
                $content = str_replace($fix['find'], $fix['replace'], $content);
                $changes++;
            }
        }
    }
    
    if ($changes > 0) {
        if (file_put_contents($filePath, $content)) {
            return ['success' => true, 'changes' => $changes];
        } else {
            return ['success' => false, 'message' => 'Impossibile scrivere il file'];
        }
    }
    
    return ['success' => true, 'changes' => 0];
}

// Modalità di esecuzione
$mode = $_GET['mode'] ?? 'preview';

if ($mode === 'preview') {
    // MODALITÀ PREVIEW (default)
    echo "<div class='warning'>";
    echo "<h2>⚠️ MODALITÀ PREVIEW</h2>";
    echo "<p>Questa è una preview delle modifiche che verranno apportate.</p>";
    echo "<p>Nessun file verrà modificato ora.</p>";
    echo "</div>";
    
    echo "<h2>File da Modificare:</h2>";
    foreach ($fixes as $file => $fileFixes) {
        $fullPath = $basePath . $file;
        echo "<div class='info'>";
        echo "<strong>📄 $file</strong><br>";
        
        if (file_exists($fullPath)) {
            echo "✅ File trovato<br>";
            echo "Modifiche previste:<br>";
            echo "<ul>";
            foreach ($fileFixes as $fix) {
                echo "<li>";
                echo "<code>" . htmlspecialchars($fix['find']) . "</code><br>";
                echo "→ <code>" . htmlspecialchars($fix['replace']) . "</code>";
                if (isset($fix['all']) && $fix['all']) {
                    echo " (tutte le occorrenze)";
                }
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "❌ File non trovato";
        }
        echo "</div>";
    }
    
    echo "<div class='warning'>";
    echo "<h3>Pronto per applicare le modifiche?</h3>";
    echo "<p><strong>IMPORTANTE:</strong> Verrà creato un backup di ogni file prima della modifica.</p>";
    echo "<a href='?mode=execute' onclick='return confirm(\"Sei sicuro di voler applicare le modifiche? Verrà fatto un backup automatico.\")' style='display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>🔧 Applica Modifiche</a>";
    echo " ";
    echo "<a href='/crm/?action=pratiche' style='display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>❌ Annulla</a>";
    echo "</div>";
    
} elseif ($mode === 'execute') {
    // MODALITÀ ESECUZIONE
    echo "<div class='error'>";
    echo "<h2>🔧 ESECUZIONE MODIFICHE</h2>";
    echo "</div>";
    
    $allSuccess = true;
    
    foreach ($fixes as $file => $fileFixes) {
        $fullPath = $basePath . $file;
        echo "<div class='info'>";
        echo "<strong>📄 $file</strong><br>";
        
        if (file_exists($fullPath)) {
            // Crea backup
            $backupPath = createBackup($fullPath);
            if ($backupPath) {
                echo "✅ Backup creato: " . basename($backupPath) . "<br>";
                
                // Applica fix
                $result = applyFix($fullPath, $fileFixes);
                if ($result['success']) {
                    if ($result['changes'] > 0) {
                        echo "✅ Modificato con successo ({$result['changes']} modifiche)<br>";
                    } else {
                        echo "ℹ️ Nessuna modifica necessaria<br>";
                    }
                } else {
                    echo "❌ Errore: {$result['message']}<br>";
                    $allSuccess = false;
                }
            } else {
                echo "❌ Impossibile creare backup<br>";
                $allSuccess = false;
            }
        } else {
            echo "⚠️ File non trovato, saltato<br>";
        }
        echo "</div>";
    }
    
    if ($allSuccess) {
        echo "<div class='ok'>";
        echo "<h3>✅ Tutte le modifiche sono state applicate con successo!</h3>";
        echo "<p>I file di backup sono stati creati con estensione .backup_[timestamp]</p>";
        echo "<p><a href='/crm/?action=pratiche' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>🎉 Vai al Modulo Pratiche</a></p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>⚠️ Alcune modifiche non sono riuscite</h3>";
        echo "<p>Controlla i messaggi di errore sopra.</p>";
        echo "</div>";
    }
}

// Info backup
echo "<div class='backup'>";
echo "<h3>📦 Gestione Backup</h3>";
echo "<p>I file di backup vengono creati automaticamente con il nome: <code>nomefile.php.backup_YYYYMMDDHHmmss</code></p>";
echo "<p>Per ripristinare un backup, rinomina il file rimuovendo l'estensione .backup_[timestamp]</p>";
echo "</div>";

// Mostra file di backup esistenti
echo "<h3>File di Backup Esistenti:</h3>";
$backupFiles = glob($basePath . '*.backup_*');
$backupFiles = array_merge($backupFiles, glob($basePath . 'api/*.backup_*'));

if (!empty($backupFiles)) {
    echo "<ul>";
    foreach ($backupFiles as $backup) {
        echo "<li>" . basename($backup) . " - " . date('Y-m-d H:i:s', filemtime($backup)) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Nessun backup trovato.</p>";
}
?>