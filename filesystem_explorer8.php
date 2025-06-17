<?php
/**
 * CRM Filesystem Explorer
 * Script per mappare la struttura completa del filesystem
 * Re.De Consulting - www.redeconsulting.eu/crm
 * 
 * UTILIZZO:
 * 1. Carica questo file nella root del CRM
 * 2. Accedi via browser con parametro: ?action=explore&key=YOUR_SECRET_KEY
 * 3. Lo script genererà un file filesystem_structure.txt scaricabile
 * 
 * SICUREZZA: Cambia la SECRET_KEY prima dell'uso!
 */

// ============================================================
// CONFIGURAZIONE SICUREZZA
// ============================================================
const SECRET_KEY = 'Fiasella13!'; // CAMBIA QUESTA CHIAVE!
const MAX_FILE_SIZE_DISPLAY = 10 * 1024 * 1024; // 10MB max per visualizzazione contenuto
const OUTPUT_FILE = 'filesystem_structure.txt';

// ============================================================
// VERIFICA ACCESSO
// ============================================================
function verifyAccess() {
    $provided_key = $_GET['key'] ?? '';
    $action = $_GET['action'] ?? '';
    
    if ($action !== 'explore' || $provided_key !== SECRET_KEY) {
        http_response_code(403);
        die('Access denied. Use: ?action=explore&key=YOUR_SECRET_KEY');
    }
}

// ============================================================
// CLASSE FILESYSTEM EXPLORER
// ============================================================
class FilesystemExplorer {
    private $root_path;
    private $structure = [];
    private $stats = [
        'total_files' => 0,
        'total_dirs' => 0,
        'total_size' => 0,
        'file_types' => [],
        'largest_files' => [],
        'sensitive_files' => []
    ];
    
    // File sensibili da segnalare (ma non escludere)
    private $sensitive_patterns = [
        '.env', '.htaccess', 'config.php', 'database.php', 
        'security.php', '*.sql', '*.log', 'wp-config.php'
    ];
    
    public function __construct($root_path = '.') {
        $this->root_path = realpath($root_path);
    }
    
    /**
     * Esplora ricorsivamente la struttura
     */
    public function explore() {
        echo "<h2>🔍 Exploring filesystem...</h2>";
        echo "<div id='progress'>Starting exploration...</div>";
        echo "<script>function updateProgress(msg) { document.getElementById('progress').innerHTML = msg; }</script>";
        
        $this->exploreDirectory($this->root_path, 0);
        $this->generateReport();
        
        return $this->structure;
    }
    
    /**
     * Esplora una directory ricorsivamente
     */
    private function exploreDirectory($path, $level = 0) {
        $dir_name = basename($path);
        
        // Update progress per directory importanti
        if ($level < 3) {
            echo "<script>updateProgress('Exploring: " . htmlspecialchars($dir_name) . "');</script>";
            echo str_repeat(' ', 1024); // Force browser to update
            flush();
        }
        
        try {
            $iterator = new DirectoryIterator($path);
            $dir_info = [
                'name' => $dir_name,
                'type' => 'directory',
                'path' => $path,
                'level' => $level,
                'children' => [],
                'size' => 0,
                'modified' => filemtime($path),
                'permissions' => substr(sprintf('%o', fileperms($path)), -4)
            ];
            
            $files = [];
            $dirs = [];
            
            foreach ($iterator as $item) {
                if ($item->isDot()) continue;
                
                $item_path = $item->getPathname();
                $item_name = $item->getFilename();
                
                if ($item->isDir()) {
                    $this->stats['total_dirs']++;
                    $subdir = $this->exploreDirectory($item_path, $level + 1);
                    $dirs[] = $subdir;
                    $dir_info['size'] += $subdir['size'];
                } else {
                    $this->stats['total_files']++;
                    $file_info = $this->analyzeFile($item_path, $level + 1);
                    $files[] = $file_info;
                    $dir_info['size'] += $file_info['size'];
                }
            }
            
            // Ordina files e directories
            usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            
            $dir_info['children'] = array_merge($dirs, $files);
            
            return $dir_info;
            
        } catch (Exception $e) {
            return [
                'name' => $dir_name,
                'type' => 'error',
                'error' => $e->getMessage(),
                'level' => $level
            ];
        }
    }
    
    /**
     * Analizza un singolo file
     */
    private function analyzeFile($file_path, $level) {
        $file_name = basename($file_path);
        $file_size = filesize($file_path);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Statistiche
        $this->stats['total_size'] += $file_size;
        $this->stats['file_types'][$file_ext] = ($this->stats['file_types'][$file_ext] ?? 0) + 1;
        
        // File più grandi
        if (count($this->stats['largest_files']) < 20) {
            $this->stats['largest_files'][] = ['name' => $file_name, 'size' => $file_size, 'path' => $file_path];
        } else {
            $min_size = min(array_column($this->stats['largest_files'], 'size'));
            if ($file_size > $min_size) {
                $min_key = array_search($min_size, array_column($this->stats['largest_files'], 'size'));
                $this->stats['largest_files'][$min_key] = ['name' => $file_name, 'size' => $file_size, 'path' => $file_path];
            }
        }
        
        // File sensibili
        if ($this->isSensitiveFile($file_name)) {
            $this->stats['sensitive_files'][] = $file_path;
        }
        
        $file_info = [
            'name' => $file_name,
            'type' => 'file',
            'extension' => $file_ext,
            'size' => $file_size,
            'size_human' => $this->formatBytes($file_size),
            'modified' => filemtime($file_path),
            'modified_human' => date('Y-m-d H:i:s', filemtime($file_path)),
            'permissions' => substr(sprintf('%o', fileperms($file_path)), -4),
            'level' => $level,
            'is_sensitive' => $this->isSensitiveFile($file_name)
        ];
        
        // Contenuto per file piccoli e importanti
        if ($file_size < 1024 && in_array($file_ext, ['env', 'txt', 'md', 'json'])) {
            try {
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    $file_info['content_preview'] = mb_substr($content, 0, 500);
                }
            } catch (Exception $e) {
                $file_info['content_error'] = $e->getMessage();
            }
        }
        
        return $file_info;
    }
    
    /**
     * Verifica se un file è sensibile
     */
    private function isSensitiveFile($filename) {
        $filename_lower = strtolower($filename);
        
        foreach ($this->sensitive_patterns as $pattern) {
            if (fnmatch(strtolower($pattern), $filename_lower)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Genera il report completo
     */
    private function generateReport() {
        $this->structure = $this->exploreDirectory($this->root_path);
        
        $report = $this->generateTextReport();
        
        // Salva su file
        file_put_contents(OUTPUT_FILE, $report);
        
        echo "<h2>✅ Exploration Complete!</h2>";
        echo "<div class='stats'>";
        echo "<p><strong>Total Files:</strong> " . number_format($this->stats['total_files']) . "</p>";
        echo "<p><strong>Total Directories:</strong> " . number_format($this->stats['total_dirs']) . "</p>";
        echo "<p><strong>Total Size:</strong> " . $this->formatBytes($this->stats['total_size']) . "</p>";
        echo "<p><strong>Sensitive Files Found:</strong> " . count($this->stats['sensitive_files']) . "</p>";
        echo "</div>";
        
        echo "<div class='download'>";
        echo "<a href='" . OUTPUT_FILE . "' download='filesystem_structure.txt' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;'>📥 Download Complete Report</a>";
        echo "</div>";
        
        // Anteprima struttura principiale
        echo "<h3>📁 Main Structure Preview:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo $this->generateTreePreview($this->structure, 0, 2); // Solo 2 livelli per preview
        echo "</pre>";
        
        // Top file types
        echo "<h3>📊 File Types Distribution:</h3>";
        arsort($this->stats['file_types']);
        echo "<ul>";
        foreach (array_slice($this->stats['file_types'], 0, 10, true) as $ext => $count) {
            $ext_display = empty($ext) ? '[no extension]' : ".$ext";
            echo "<li><strong>$ext_display:</strong> $count files</li>";
        }
        echo "</ul>";
    }
    
    /**
     * Genera il report testuale completo
     */
    private function generateTextReport() {
        $report = [];
        $report[] = "================================================================================";
        $report[] = "CRM FILESYSTEM STRUCTURE REPORT";
        $report[] = "Generated: " . date('Y-m-d H:i:s');
        $report[] = "Root Path: " . $this->root_path;
        $report[] = "================================================================================";
        $report[] = "";
        
        // Statistiche generali
        $report[] = "📊 GENERAL STATISTICS";
        $report[] = "================================================================================";
        $report[] = "Total Files: " . number_format($this->stats['total_files']);
        $report[] = "Total Directories: " . number_format($this->stats['total_dirs']);
        $report[] = "Total Size: " . $this->formatBytes($this->stats['total_size']);
        $report[] = "Sensitive Files: " . count($this->stats['sensitive_files']);
        $report[] = "";
        
        // Struttura ad albero
        $report[] = "🌳 DIRECTORY TREE STRUCTURE";
        $report[] = "================================================================================";
        $report[] = $this->generateTreeStructure($this->structure);
        $report[] = "";
        
        // File sensibili
        if (!empty($this->stats['sensitive_files'])) {
            $report[] = "🔒 SENSITIVE FILES DETECTED";
            $report[] = "================================================================================";
            foreach ($this->stats['sensitive_files'] as $sensitive_file) {
                $report[] = $sensitive_file;
            }
            $report[] = "";
        }
        
        // File più grandi
        $report[] = "📈 LARGEST FILES (Top 20)";
        $report[] = "================================================================================";
        usort($this->stats['largest_files'], fn($a, $b) => $b['size'] <=> $a['size']);
        foreach (array_slice($this->stats['largest_files'], 0, 20) as $file) {
            $report[] = sprintf("%-60s %s", $file['name'], $this->formatBytes($file['size']));
        }
        $report[] = "";
        
        // Tipi di file
        $report[] = "📋 FILE TYPES DISTRIBUTION";
        $report[] = "================================================================================";
        arsort($this->stats['file_types']);
        foreach ($this->stats['file_types'] as $ext => $count) {
            $ext_display = empty($ext) ? '[no extension]' : ".$ext";
            $report[] = sprintf("%-20s %d files", $ext_display, $count);
        }
        $report[] = "";
        
        // Dettaglio completo struttura
        $report[] = "📁 DETAILED STRUCTURE WITH METADATA";
        $report[] = "================================================================================";
        $report[] = $this->generateDetailedStructure($this->structure);
        
        return implode("\n", $report);
    }
    
    /**
     * Genera l'albero della struttura (preview)
     */
    private function generateTreePreview($item, $level, $max_level) {
        if ($level > $max_level) return '';
        
        $output = '';
        $indent = str_repeat('  ', $level);
        $icon = $item['type'] === 'directory' ? '📁' : '📄';
        
        $output .= $indent . $icon . ' ' . $item['name'];
        
        if ($item['type'] === 'file') {
            $output .= ' (' . $item['size_human'] . ')';
            if ($item['is_sensitive']) {
                $output .= ' 🔒';
            }
        }
        
        $output .= "\n";
        
        if (isset($item['children']) && $level < $max_level) {
            foreach ($item['children'] as $child) {
                $output .= $this->generateTreePreview($child, $level + 1, $max_level);
            }
        }
        
        return $output;
    }
    
    /**
     * Genera la struttura ad albero completa
     */
    private function generateTreeStructure($item, $level = 0) {
        $output = '';
        $indent = str_repeat('├── ', $level);
        if ($level === 0) $indent = '';
        
        $icon = $item['type'] === 'directory' ? '📁' : '📄';
        $name = $item['name'];
        
        if ($item['type'] === 'file') {
            $name .= ' (' . $item['size_human'] . ')';
        }
        
        $output .= $indent . $icon . ' ' . $name . "\n";
        
        if (isset($item['children'])) {
            foreach ($item['children'] as $child) {
                $output .= $this->generateTreeStructure($child, $level + 1);
            }
        }
        
        return $output;
    }
    
    /**
     * Genera la struttura dettagliata con metadati
     */
    private function generateDetailedStructure($item, $level = 0) {
        $output = '';
        $indent = str_repeat('  ', $level);
        
        $type_icon = $item['type'] === 'directory' ? '📁 DIR' : '📄 FILE';
        $sensitive_flag = ($item['is_sensitive'] ?? false) ? ' 🔒 SENSITIVE' : '';
        
        $output .= $indent . $type_icon . ' ' . $item['name'] . $sensitive_flag . "\n";
        
        if ($item['type'] === 'file') {
            $output .= $indent . "   Size: " . $item['size_human'] . " (" . number_format($item['size']) . " bytes)\n";
            $output .= $indent . "   Modified: " . $item['modified_human'] . "\n";
            $output .= $indent . "   Permissions: " . $item['permissions'] . "\n";
            
            if (isset($item['content_preview'])) {
                $output .= $indent . "   Preview: " . substr(str_replace(["\n", "\r"], [' ', ''], $item['content_preview']), 0, 100) . "...\n";
            }
        } else {
            $child_count = count($item['children'] ?? []);
            $output .= $indent . "   Items: $child_count\n";
            $output .= $indent . "   Total Size: " . $this->formatBytes($item['size']) . "\n";
            $output .= $indent . "   Permissions: " . $item['permissions'] . "\n";
        }
        
        $output .= "\n";
        
        if (isset($item['children'])) {
            foreach ($item['children'] as $child) {
                $output .= $this->generateDetailedStructure($child, $level + 1);
            }
        }
        
        return $output;
    }
    
    /**
     * Formatta i bytes in formato leggibile
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && isset($units[$i + 1]); $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// ============================================================
// ESECUZIONE SCRIPT
// ============================================================

// Verifica accesso solo se chiamato via web
if (isset($_SERVER['REQUEST_METHOD'])) {
    verifyAccess();
    
    // HTML Base per output
    echo '<!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CRM Filesystem Explorer</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 3px solid #007cba; padding-bottom: 10px; }
            h2 { color: #007cba; margin-top: 30px; }
            .stats { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .stats p { margin: 5px 0; }
            pre { white-space: pre-wrap; word-break: break-all; }
            #progress { background: #ffffcc; padding: 10px; border-radius: 5px; margin: 10px 0; font-weight: bold; }
            .download { text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔍 CRM Filesystem Explorer</h1>
            <p><strong>Root:</strong> ' . htmlspecialchars(realpath('.')) . '</p>
    ';
    
    // Esegui l'esplorazione
    $explorer = new FilesystemExplorer('.');
    $structure = $explorer->explore();
    
    echo '</div></body></html>';
    
} else {
    // Esecuzione da CLI
    echo "CRM Filesystem Explorer - CLI Mode\n";
    echo "==================================\n\n";
    
    $explorer = new FilesystemExplorer('.');
    $structure = $explorer->explore();
    
    echo "\n✅ Report saved to: " . OUTPUT_FILE . "\n";
    echo "📁 Total structure mapped successfully!\n";
}

?>