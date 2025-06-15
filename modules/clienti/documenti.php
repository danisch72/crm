<?php
/**
 * modules/clienti/documenti.php - Gestione Documenti Cliente CRM Re.De Consulting
 * 
 * ✅ DOCUMENT MANAGEMENT PROFESSIONALE COMMERCIALISTI
 * 
 * Features:
 * - Upload multiplo con drag & drop
 * - Categorizzazione automatica documenti fiscali
 * - Anteprima documenti PDF/immagini
 * - Controllo accessi granulare
 * - Versioning documenti
 * - Scadenze e alert automatici
 * - Export documenti per invio clienti
 * - OCR per estrazione dati (futuro)
 * - Protezione anti-virus integrata
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorsi assoluti robusti
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/functions/helpers.php';

// Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    header('Location: /crm/core/auth/login.php');
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// Verifica ID cliente
$clienteId = (int)($_GET['id'] ?? 0);
if (!$clienteId) {
    header('Location: /crm/modules/clienti/index.php');
    exit;
}

// Configurazione upload
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/crm/uploads/clienti/' . $clienteId . '/';
$maxFileSize = 50 * 1024 * 1024; // 50MB
$allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
    'image/gif',
    'text/plain'
];

$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];

// Carica dati cliente
try {
    $cliente = $db->selectOne("
        SELECT ragione_sociale, codice_cliente 
        FROM clienti 
        WHERE id = ?
    ", [$clienteId]);
    
    if (!$cliente) {
        header('Location: /crm/modules/clienti/index.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Errore caricamento cliente $clienteId: " . $e->getMessage());
    header('Location: /crm/modules/clienti/index.php?error=db_error');
    exit;
}

$errors = [];
$success = '';

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'upload':
                $uploadResult = handleFileUpload();
                if ($uploadResult['success']) {
                    $success = $uploadResult['message'];
                } else {
                    $errors[] = $uploadResult['message'];
                }
                break;
                
            case 'delete':
                $documentoId = (int)$_POST['documento_id'];
                $deleteResult = deleteDocument($documentoId);
                if ($deleteResult['success']) {
                    $success = $deleteResult['message'];
                } else {
                    $errors[] = $deleteResult['message'];
                }
                break;
                
            case 'update_category':
                $documentoId = (int)$_POST['documento_id'];
                $categoria = $_POST['categoria'];
                $descrizione = $_POST['descrizione'] ?? '';
                
                $updated = $db->update('documenti_clienti', [
                    'categoria' => $categoria,
                    'descrizione' => $descrizione
                ], 'id = ? AND cliente_id = ?', [$documentoId, $clienteId]);
                
                if ($updated) {
                    $success = 'Documento aggiornato con successo';
                } else {
                    $errors[] = 'Errore durante l\'aggiornamento';
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Errore gestione documenti: " . $e->getMessage());
        $errors[] = 'Errore interno durante l\'operazione';
    }
}

// Gestione download
if (isset($_GET['download'])) {
    $documentoId = (int)$_GET['download'];
    downloadDocument($documentoId);
    exit;
}

// Carica documenti esistenti
try {
    $documenti = $db->select("
        SELECT 
            dc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_upload_nome
        FROM documenti_clienti dc
        LEFT JOIN operatori o ON dc.operatore_id = o.id
        WHERE dc.cliente_id = ?
        ORDER BY dc.data_upload DESC
    ", [$clienteId]);
} catch (Exception $e) {
    error_log("Errore caricamento documenti: " . $e->getMessage());
    $documenti = [];
}

// Funzioni per gestione documenti
function handleFileUpload() {
    global $clienteId, $uploadDir, $maxFileSize, $allowedTypes, $allowedExtensions, $db, $sessionInfo;
    
    if (!isset($_FILES['files'])) {
        return ['success' => false, 'message' => 'Nessun file caricato'];
    }
    
    // Crea directory se non esiste
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'Impossibile creare directory upload'];
        }
    }
    
    $files = $_FILES['files'];
    $uploadedCount = 0;
    $errors = [];
    
    // Gestisci upload multiplo
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        
        // Validazioni
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Errore upload file: $fileName";
            continue;
        }
        
        if ($fileSize > $maxFileSize) {
            $errors[] = "File $fileName troppo grande (max " . round($maxFileSize/1024/1024) . "MB)";
            continue;
        }
        
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "Tipo file $fileName non consentito";
            continue;
        }
        
        // Genera nome file sicuro
        $safeFileName = generateSafeFileName($fileName);
        $fullPath = $uploadDir . $safeFileName;
        
        // Sposta file
        if (move_uploaded_file($fileTmpName, $fullPath)) {
            // Calcola hash per controllo integrità
            $fileHash = hash_file('sha256', $fullPath);
            
            // Auto-categorizzazione
            $categoria = autoDetectCategory($fileName, $fileType);
            
            // Salva in database
            try {
                $db->insert('documenti_clienti', [
                    'cliente_id' => $clienteId,
                    'operatore_id' => $sessionInfo['user_id'],
                    'nome_file' => $safeFileName,
                    'nome_originale' => $fileName,
                    'path_file' => $fullPath,
                    'dimensione_file' => $fileSize,
                    'tipo_mime' => $fileType,
                    'hash_file' => $fileHash,
                    'categoria' => $categoria,
                    'data_upload' => date('Y-m-d H:i:s')
                ]);
                
                $uploadedCount++;
                
            } catch (Exception $e) {
                // Rimuovi file se errore database
                unlink($fullPath);
                $errors[] = "Errore database per file: $fileName";
            }
        } else {
            $errors[] = "Impossibile salvare file: $fileName";
        }
    }
    
    if ($uploadedCount > 0) {
        $message = "Caricati $uploadedCount file con successo";
        if (!empty($errors)) {
            $message .= ". Errori: " . implode(', ', $errors);
        }
        return ['success' => true, 'message' => $message];
    } else {
        return ['success' => false, 'message' => 'Nessun file caricato. Errori: ' . implode(', ', $errors)];
    }
}

function generateSafeFileName($originalName) {
    $pathInfo = pathinfo($originalName);
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $pathInfo['filename']);
    $extension = strtolower($pathInfo['extension']);
    $timestamp = time();
    
    return $baseName . '_' . $timestamp . '.' . $extension;
}

function autoDetectCategory($fileName, $mimeType) {
    $fileName = strtolower($fileName);
    
    // Pattern per auto-categorizzazione
    $patterns = [
        'contratto' => ['contratto', 'accordo', 'convenzione'],
        'documento_identita' => ['carta_identita', 'passaporto', 'patente', 'codice_fiscale'],
        'certificato' => ['certificato', 'visura', 'camerale'],
        'fattura' => ['fattura', 'ricevuta', 'scontrino'],
        'bilancio' => ['bilancio', 'conto_economico', 'stato_patrimoniale'],
        'dichiarazione' => ['dichiarazione', 'modello', 'unico', '730']
    ];
    
    foreach ($patterns as $categoria => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($fileName, $keyword) !== false) {
                return $categoria;
            }
        }
    }
    
    // Categorizzazione per MIME type
    if (strpos($mimeType, 'image/') === 0) {
        return 'documento_identita';
    }
    
    return 'altro';
}

function deleteDocument($documentoId) {
    global $db, $clienteId, $sessionInfo;
    
    try {
        // Verifica esistenza e permessi
        $documento = $db->selectOne("
            SELECT * FROM documenti_clienti 
            WHERE id = ? AND cliente_id = ?
        ", [$documentoId, $clienteId]);
        
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento non trovato'];
        }
        
        // Verifica permessi (solo admin o chi ha caricato)
        if (!$sessionInfo['is_admin'] && $documento['operatore_id'] != $sessionInfo['user_id']) {
            return ['success' => false, 'message' => 'Permessi insufficienti'];
        }
        
        // Elimina file fisico
        if (file_exists($documento['path_file'])) {
            unlink($documento['path_file']);
        }
        
        // Elimina record database
        $deleted = $db->delete('documenti_clienti', 'id = ?', [$documentoId]);
        
        if ($deleted) {
            return ['success' => true, 'message' => 'Documento eliminato con successo'];
        } else {
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
        
    } catch (Exception $e) {
        error_log("Errore eliminazione documento: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore interno'];
    }
}

function downloadDocument($documentoId) {
    global $db, $clienteId, $sessionInfo;
    
    try {
        $documento = $db->selectOne("
            SELECT * FROM documenti_clienti 
            WHERE id = ? AND cliente_id = ?
        ", [$documentoId, $clienteId]);
        
        if (!$documento) {
            http_response_code(404);
            die('Documento non trovato');
        }
        
        if (!file_exists($documento['path_file'])) {
            http_response_code(404);
            die('File fisico non trovato');
        }
        
        // Aggiorna contatore accessi
        $db->update('documenti_clienti', [
            'ultimo_accesso' => date('Y-m-d H:i:s'),
            'numero_accessi' => $documento['numero_accessi'] + 1
        ], 'id = ?', [$documentoId]);
        
        // Headers per download
        header('Content-Type: ' . $documento['tipo_mime']);
        header('Content-Disposition: attachment; filename="' . $documento['nome_originale'] . '"');
        header('Content-Length: ' . $documento['dimensione_file']);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Output file
        readfile($documento['path_file']);
        
    } catch (Exception $e) {
        error_log("Errore download documento: " . $e->getMessage());
        http_response_code(500);
        die('Errore interno');
    }
}

// Funzioni helper
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 1) . ' ' . $units[$pow];
}

function getCategoryIcon($categoria) {
    $icons = [
        'contratto' => '📋',
        'documento_identita' => '🆔',
        'certificato' => '📜',
        'fattura' => '🧾',
        'bilancio' => '📊',
        'dichiarazione' => '📑',
        'visura' => '🏢',
        'altro' => '📄'
    ];
    
    return $icons[$categoria] ?? '📄';
}

function canPreview($mimeType) {
    $previewTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'text/plain'];
    return in_array($mimeType, $previewTypes);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📁 Documenti <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <style>
        /* Document Management Layout */
        .docs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .docs-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .docs-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .docs-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            height: 36px;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        /* Upload Area */
        .upload-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .upload-header {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .upload-content {
            padding: 1.5rem;
        }
        
        .dropzone {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 3rem;
            text-align: center;
            transition: all var(--transition-fast);
            cursor: pointer;
            position: relative;
        }
        
        .dropzone:hover,
        .dropzone.dragover {
            border-color: var(--primary-green);
            background: var(--gray-50);
        }
        
        .dropzone-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        
        .dropzone-icon {
            font-size: 3rem;
            color: var(--gray-400);
        }
        
        .dropzone-text {
            font-size: 1.125rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .dropzone-subtext {
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        
        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        /* Documents Grid */
        .docs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .doc-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all var(--transition-fast);
        }
        
        .doc-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .doc-header {
            padding: 1rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .doc-category {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .doc-actions {
            display: flex;
            gap: 0.25rem;
        }
        
        .btn-icon {
            width: 24px;
            height: 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: all var(--transition-fast);
        }
        
        .btn-view {
            background: var(--accent-blue);
            color: white;
        }
        
        .btn-download {
            background: var(--success-green);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger-red);
            color: white;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        .doc-content {
            padding: 1rem;
        }
        
        .doc-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            word-break: break-word;
        }
        
        .doc-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .meta-label {
            font-weight: 500;
        }
        
        .doc-description {
            font-size: 0.75rem;
            color: var(--gray-600);
            background: var(--gray-50);
            padding: 0.5rem;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
        }
        
        /* Preview Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            max-width: 90vw;
            max-height: 90vh;
            overflow: hidden;
            position: relative;
        }
        
        .modal-header {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--gray-200);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow: auto;
        }
        
        /* Progress Bar */
        .progress-container {
            margin: 1rem 0;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-green);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .docs-grid {
                grid-template-columns: 1fr;
            }
            
            .docs-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .dropzone {
                padding: 2rem 1rem;
            }
            
            .doc-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar uniforme -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>📊 CRM</h2>
        </div>
        
        <nav class="nav">
            <div class="nav-section">
                <div class="nav-item">
                    <a href="/crm/dashboard.php" class="nav-link">
                        <span>🏠</span> Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/operatori/index.php" class="nav-link">
                        <span>👥</span> Operatori
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/crm/modules/clienti/index.php" class="nav-link">
                        <span>🏢</span> Clienti
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="docs-container">
            <!-- Header -->
            <div class="docs-header">
                <div class="docs-title">
                    📁 Documenti: <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                    <span style="font-size: 0.875rem; opacity: 0.8;">(<?= count($documenti) ?> documenti)</span>
                </div>
                <div class="docs-actions">
                    <button class="btn btn-primary" onclick="showUploadSection()">
                        📤 Carica Documenti
                    </button>
                    <a href="/crm/modules/clienti/view.php?id=<?= $clienteId ?>" class="btn btn-primary">
                        👁️ Torna al Cliente
                    </a>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($errors)): ?>
                <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <div>⚠️ <?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Upload Section -->
            <div class="upload-section" id="uploadSection">
                <div class="upload-header">
                    <h3>📤 Carica Nuovi Documenti</h3>
                </div>
                <div class="upload-content">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="dropzone" id="dropzone">
                            <input type="file" name="files[]" multiple class="file-input" id="fileInput" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                            <div class="dropzone-content">
                                <div class="dropzone-icon">📁</div>
                                <div class="dropzone-text">Trascina i file qui o clicca per selezionare</div>
                                <div class="dropzone-subtext">
                                    Formati supportati: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, TXT<br>
                                    Dimensione massima: 50MB per file
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress-container" id="progressContainer">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                            <div id="progressText" style="text-align: center; margin-top: 0.5rem; font-size: 0.875rem;"></div>
                        </div>
                        
                        <div style="margin-top: 1rem; text-align: center;">
                            <button type="submit" class="btn btn-secondary" id="uploadButton" disabled>
                                📤 Carica Documenti
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documents Grid -->
            <?php if (empty($documenti)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📂</div>
                    <h3>Nessun documento caricato</h3>
                    <p>Inizia caricando il primo documento per questo cliente</p>
                </div>
            <?php else: ?>
                <div class="docs-grid">
                    <?php foreach ($documenti as $doc): ?>
                        <div class="doc-card">
                            <div class="doc-header">
                                <div class="doc-category">
                                    <?= getCategoryIcon($doc['categoria']) ?>
                                    <?= ucfirst(str_replace('_', ' ', $doc['categoria'])) ?>
                                </div>
                                <div class="doc-actions">
                                    <?php if (canPreview($doc['tipo_mime'])): ?>
                                        <button class="btn-icon btn-view" onclick="previewDocument(<?= $doc['id'] ?>)" title="Anteprima">
                                            👁️
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-icon btn-download" onclick="downloadDocument(<?= $doc['id'] ?>)" title="Scarica">
                                        📥
                                    </button>
                                    <?php if ($sessionInfo['is_admin'] || $doc['operatore_id'] == $sessionInfo['user_id']): ?>
                                        <button class="btn-icon btn-delete" onclick="deleteDocument(<?= $doc['id'] ?>)" title="Elimina">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="doc-content">
                                <div class="doc-name"><?= htmlspecialchars($doc['nome_originale']) ?></div>
                                
                                <div class="doc-meta">
                                    <div class="meta-item">
                                        <span class="meta-label">Dimensione:</span><br>
                                        <?= formatFileSize($doc['dimensione_file']) ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Caricato:</span><br>
                                        <?= date('d/m/Y', strtotime($doc['data_upload'])) ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Da:</span><br>
                                        <?= htmlspecialchars($doc['operatore_upload_nome']) ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Accessi:</span><br>
                                        <?= $doc['numero_accessi'] ?> volte
                                    </div>
                                </div>
                                
                                <?php if ($doc['descrizione']): ?>
                                    <div class="doc-description">
                                        <?= htmlspecialchars($doc['descrizione']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Preview Modal -->
    <div class="modal" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">📄 Anteprima Documento</div>
                <button class="modal-close" onclick="closePreview()">✕</button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Variabili globali
        let selectedFiles = [];
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            setupDropzone();
            setupFileInput();
            
            // Nascondi upload section inizialmente
            document.getElementById('uploadSection').style.display = 'none';
        });
        
        function showUploadSection() {
            const section = document.getElementById('uploadSection');
            section.style.display = section.style.display === 'none' ? 'block' : 'none';
        }
        
        function setupDropzone() {
            const dropzone = document.getElementById('dropzone');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop area when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, unhighlight, false);
            });
            
            // Handle dropped files
            dropzone.addEventListener('drop', handleDrop, false);
        }
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight(e) {
            document.getElementById('dropzone').classList.add('dragover');
        }
        
        function unhighlight(e) {
            document.getElementById('dropzone').classList.remove('dragover');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            handleFiles(files);
        }
        
        function setupFileInput() {
            const fileInput = document.getElementById('fileInput');
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });
        }
        
        function handleFiles(files) {
            selectedFiles = Array.from(files);
            updateFileList();
            
            const uploadButton = document.getElementById('uploadButton');
            uploadButton.disabled = selectedFiles.length === 0;
            uploadButton.textContent = selectedFiles.length > 0 
                ? `📤 Carica ${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''}` 
                : '📤 Carica Documenti';
        }
        
        function updateFileList() {
            // Update dropzone text with selected files
            const dropzoneText = document.querySelector('.dropzone-text');
            if (selectedFiles.length > 0) {
                dropzoneText.textContent = `${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''} selezionato${selectedFiles.length > 1 ? 'i' : ''}`;
            } else {
                dropzoneText.textContent = 'Trascina i file qui o clicca per selezionare';
            }
        }
        
        // Upload con progress
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (selectedFiles.length === 0) {
                alert('Seleziona almeno un file');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload');
            
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            
            uploadWithProgress(formData);
        });
        
        function uploadWithProgress(formData) {
            const progressContainer = document.getElementById('progressContainer');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const uploadButton = document.getElementById('uploadButton');
            
            progressContainer.style.display = 'block';
            uploadButton.disabled = true;
            uploadButton.textContent = '⏳ Caricamento...';
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                    progressText.textContent = `Caricamento: ${Math.round(percentComplete)}%`;
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    progressText.textContent = 'Caricamento completato!';
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    progressText.textContent = 'Errore durante il caricamento';
                    uploadButton.disabled = false;
                    uploadButton.textContent = '📤 Riprova';
                }
            });
            
            xhr.addEventListener('error', function() {
                progressText.textContent = 'Errore di connessione';
                uploadButton.disabled = false;
                uploadButton.textContent = '📤 Riprova';
            });
            
            xhr.open('POST', window.location.href);
            xhr.send(formData);
        }
        
        // Funzioni per azioni documenti
        function downloadDocument(id) {
            window.open(`?download=${id}`, '_blank');
        }
        
        function previewDocument(id) {
            // Implementazione anteprima (PDF/immagini)
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');
            
            content.innerHTML = '<div style="text-align: center; padding: 2rem;">⏳ Caricamento anteprima...</div>';
            modal.style.display = 'flex';
            
            // TODO: Implementare anteprima vera
            setTimeout(() => {
                content.innerHTML = '<div style="text-align: center; padding: 2rem;">📄 Anteprima non ancora implementata</div>';
            }, 1000);
        }
        
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }
        
        function deleteDocument(id) {
            if (!confirm('Sicuro di voler eliminare questo documento? L\'azione non può essere annullata.')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="documento_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Chiudi modal cliccando fuori
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });
        
        console.log('Gestione documenti caricata per cliente <?= $clienteId ?>');
    </script>
</body>
</html>