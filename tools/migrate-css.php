<?php
/**
 * tools/migrate-css.php
 * Script per aiutare nella migrazione al nuovo sistema CSS
 */

// Configurazione
$projectRoot = dirname(__DIR__);
$oldCssFiles = [
    'datev-style.css',
    'datev-optimal.css', 
    'datev-professional.css',
    'design-system.css',
    'responsive.css'
];
$newCssFile = 'design-system-unified.css';

// Mappa delle classi da sostituire
$classMap = [
    // Vecchie classi → Nuove classi
    'datev-green-primary' => 'color-primary',
    'datev-gray-100' => 'gray-100',
    'stat-card-value' => 'stat-value',
    'stat-card-label' => 'stat-label',
    'main-container' => 'app-layout',
    'content-wrapper' => 'main-wrapper',
    'sidebar-collapsed' => 'collapsed',
    // Aggiungi altre mappature necessarie
];

// Funzione per analizzare l'uso delle classi CSS
function analyzeCssUsage($dir) {
    global $classMap;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );
    
    $usage = [];
    
    foreach ($files as $file) {
        if ($file->isFile() && preg_match('/\.(php|html)$/', $file->getFilename())) {
            $content = file_get_contents($file->getPathname());
            
            foreach ($classMap as $oldClass => $newClass) {
                if (strpos($content, $oldClass) !== false) {
                    $usage[$file->getPathname()][] = $oldClass;
                }
            }
        }
    }
    
    return $usage;
}

// Funzione per sostituire le classi
function replaceClasses($file, $dryRun = true) {
    global $classMap;
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $replacements = 0;
    
    foreach ($classMap as $oldClass => $newClass) {
        $patterns = [
            // Classi in HTML
            '/class="([^"]*)\b' . preg_quote($oldClass) . '\b([^"]*)"/i',
            '/class=\'([^\']*)\b' . preg_quote($oldClass) . '\b([^\']*)\'/i',
            // Variabili CSS
            '/var\(--' . preg_quote($oldClass) . '\)/i',
        ];
        
        foreach ($patterns as $pattern) {
            $newContent = preg_replace_callback($pattern, function($matches) use ($newClass, $oldClass) {
                if (isset($matches[1])) {
                    return str_replace($oldClass, $newClass, $matches[0]);
                }
                return 'var(--' . $newClass . ')';
            }, $content);
            
            if ($newContent !== $content) {
                $replacements++;
                $content = $newContent;
            }
        }
    }
    
    if ($replacements > 0) {
        if (!$dryRun) {
            file_put_contents($file, $content);
            echo "✅ Aggiornato: $file ($replacements sostituzioni)\n";
        } else {
            echo "🔍 Da aggiornare: $file ($replacements sostituzioni)\n";
        }
    }
    
    return $replacements;
}

// Esecuzione
echo "🔧 MIGRAZIONE CSS - CRM Re.De Consulting\n";
echo "=========================================\n\n";

// Analisi
echo "📊 Analisi utilizzo classi CSS...\n";
$usage = analyzeCssUsage($projectRoot . '/modules');

if (empty($usage)) {
    echo "✅ Nessuna classe da migrare trovata!\n";
    exit(0);
}

echo "\n📋 File che necessitano aggiornamento:\n";
foreach ($usage as $file => $classes) {
    echo "- " . str_replace($projectRoot, '', $file) . "\n";
    echo "  Classi: " . implode(', ', array_unique($classes)) . "\n";
}

// Chiedi conferma
echo "\n⚠️  Vuoi procedere con la migrazione? (s/n): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 's') {
    echo "❌ Migrazione annullata.\n";
    exit(0);
}

// Esegui migrazione
echo "\n🚀 Esecuzione migrazione...\n";
$totalReplacements = 0;

foreach (array_keys($usage) as $file) {
    $totalReplacements += replaceClasses($file, false);
}

echo "\n✅ Migrazione completata! $totalReplacements sostituzioni effettuate.\n";

// Suggerimenti post-migrazione
echo "\n📌 PROSSIMI PASSI:\n";
echo "1. Rimuovi i vecchi file CSS:\n";
foreach ($oldCssFiles as $css) {
    echo "   rm assets/css/$css\n";
}
echo "2. Aggiorna i riferimenti nei file PHP per usare solo: $newCssFile\n";
echo "3. Testa tutte le pagine per verificare il corretto funzionamento\n";
echo "4. Committa le modifiche: git add -A && git commit -m 'Migrazione a design system unificato'\n";
?>