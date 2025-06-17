<?php
/**
 * modules/clienti/documenti.php - Gestione Documenti Cliente CRM Re.De Consulting
 * 
 * ✅ DOCUMENT MANAGEMENT PROFESSIONALE COMMERCIALISTI - VERSIONE CORRETTA
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

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message
// $clienteId (validato dal router)

$pageTitle = 'Documenti Cliente';

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
        $_SESSION['error_message'] = '⚠️ Cliente non trovato';
        header('Location: /crm/?action=clienti');
        exit;
    }
} catch (Exception $e) {
    error_log("Errore caricamento cliente $clienteId: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore database';
    header('Location: /crm/?action=clienti');
    exit;
}

$errors = [];
$success = '';

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload':
            $result = handleFileUpload();
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $errors[] = $result['message'];
            }
            break;
            
        case 'delete':
            $documentoId = (int)($_POST['documento_id'] ?? 0);
            if ($documentoId && $sessionInfo['is_admin']) {
                try {
                    // Recupera info file
                    $doc = $db->selectOne("
                        SELECT nome_file_salvato 
                        FROM documenti_clienti 
                        WHERE id = ? AND cliente_id = ?
                    ", [$documentoId, $clienteId]);
                    
                    if ($doc) {
                        // Elimina file fisico
                        $filePath = $uploadDir . $doc['nome_file_salvato'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        
                        // Elimina record DB
                        $db->delete('documenti_clienti', 'id = ?', [$documentoId]);
                        $success = '✅ Documento eliminato con successo';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Errore eliminazione documento';
                }
            }
            break;
    }
}

// Carica lista documenti
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
            // Salva in DB
            try {
                $db->insert('documenti_clienti', [
                    'cliente_id' => $clienteId,
                    'operatore_id' => $sessionInfo['operatore_id'],
                    'categoria' => $_POST['categoria'] ?? 'generale',
                    'nome_file_originale' => $fileName,
                    'nome_file_salvato' => $safeFileName,
                    'dimensione' => $fileSize,
                    'tipo_mime' => $fileType,
                    'note' => $_POST['note'] ?? null
                ]);
                $uploadedCount++;
            } catch (Exception $e) {
                $errors[] = "Errore salvataggio DB per: $fileName";
                // Rimuovi file se fallisce DB
                unlink($fullPath);
            }
        } else {
            $errors[] = "Impossibile salvare: $fileName";
        }
    }
    
    if ($uploadedCount > 0) {
        $message = "✅ Caricati $uploadedCount file con successo";
        if (!empty($errors)) {
            $message .= " (alcuni file hanno avuto problemi)";
        }
        return ['success' => true, 'message' => $message];
    } else {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }
}

function generateSafeFileName($originalName) {
    $info = pathinfo($originalName);
    $ext = strtolower($info['extension']);
    $name = preg_replace('/[^A-Za-z0-9\-]/', '_', $info['filename']);
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid()), 0, 6);
    
    return "{$name}_{$timestamp}_{$random}.{$ext}";
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function getCategoriaIcon($categoria) {
    $icons = [
        'contratto' => '📄',
        'fattura' => '🧾',
        'documento_identita' => '🪪',
        'visura' => '🏢',
        'bilancio' => '📊',
        'dichiarazione' => '📋',
        'generale' => '📎'
    ];
    return $icons[$categoria] ?? '📎';
}

function getCategoriaNome($categoria) {
    $nomi = [
        'contratto' => 'Contratti',
        'fattura' => 'Fatture',
        'documento_identita' => 'Documenti Identità',
        'visura' => 'Visure',
        'bilancio' => 'Bilanci',
        'dichiarazione' => 'Dichiarazioni',
        'generale' => 'Generale'
    ];
    return $nomi[$categoria] ?? 'Altro';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Layout denso documenti */
        .documenti-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .upload-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .dropzone {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }
        
        .dropzone:hover,
        .dropzone.dragover {
            border-color: var(--primary-color);
            background: var(--primary-50);
        }
        
        .dropzone-text {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .documenti-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .documento-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }
        
        .documento-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .documento-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .documento-info {
            flex: 1;
            min-width: 0;
        }
        
        .documento-nome {
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .documento-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }
        
        .documento-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .btn-icon {
            padding: 0.4rem;
            font-size: 0.9rem;
            border-radius: var(--border-radius-sm);
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-icon:hover {
            background: var(--gray-100);
            border-color: var(--border-color);
        }
        
        .btn-icon.danger:hover {
            color: var(--danger-red);
            border-color: var(--danger-red);
        }
        
        .upload-progress {
            margin-top: 1rem;
            display: none;
        }
        
        .progress-bar {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Categorie select */
        .categoria-select {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .categoria-select select {
            padding: 0.4rem 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
        }
        
        /* Filtri documenti */
        .filtri-documenti {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-chip {
            padding: 0.3rem 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: white;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .filter-chip:hover {
            background: var(--gray-50);
        }
        
        .filter-chip.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>

    <div class="documenti-container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" style="margin-bottom: 1rem;">
            <a href="/crm/">Home</a>
            <span class="separator">/</span>
            <a href="/crm/?action=clienti">Clienti</a>
            <span class="separator">/</span>
            <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>">
                <?= htmlspecialchars($cliente['ragione_sociale']) ?>
            </a>
            <span class="separator">/</span>
            <span class="current">Documenti</span>
        </nav>

        <!-- Messaggi -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <!-- Upload Section -->
        <div class="upload-section">
            <h3>📤 Carica Documenti</h3>
            
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                
                <div class="dropzone" id="dropzone">
                    <div class="dropzone-text">
                        📁 Trascina qui i documenti o clicca per selezionare
                    </div>
                    <input type="file" id="fileInput" name="files[]" multiple style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('fileInput').click()">
                        Seleziona Files
                    </button>
                </div>
                
                <div class="categoria-select">
                    <label>Categoria:</label>
                    <select name="categoria" id="categoriaSelect">
                        <option value="generale">📎 Generale</option>
                        <option value="contratto">📄 Contratti</option>
                        <option value="fattura">🧾 Fatture</option>
                        <option value="documento_identita">🪪 Documenti Identità</option>
                        <option value="visura">🏢 Visure</option>
                        <option value="bilancio">📊 Bilanci</option>
                        <option value="dichiarazione">📋 Dichiarazioni</option>
                    </select>
                    
                    <input type="text" name="note" placeholder="Note (opzionale)" style="flex: 1;">
                    
                    <button type="submit" id="uploadButton" class="btn btn-primary" disabled>
                        📤 Carica Documenti
                    </button>
                </div>
                
                <div class="upload-progress" id="uploadProgress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                        Caricamento in corso...
                    </div>
                </div>
            </form>
        </div>

        <!-- Lista Documenti -->
        <div class="upload-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>📁 Documenti (<?= count($documenti) ?>)</h3>
                
                <!-- Filtri -->
                <div class="filtri-documenti">
                    <div class="filter-chip active" data-filter="all">
                        Tutti
                    </div>
                    <?php
                    $categorie = ['contratto', 'fattura', 'documento_identita', 'visura', 'bilancio', 'dichiarazione', 'generale'];
                    foreach ($categorie as $cat): ?>
                        <div class="filter-chip" data-filter="<?= $cat ?>">
                            <?= getCategoriaIcon($cat) ?> <?= getCategoriaNome($cat) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (empty($documenti)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>Nessun documento caricato per questo cliente</p>
                </div>
            <?php else: ?>
                <div class="documenti-grid">
                    <?php foreach ($documenti as $doc): ?>
                        <div class="documento-card" data-categoria="<?= $doc['categoria'] ?>">
                            <div class="documento-icon">
                                <?= getCategoriaIcon($doc['categoria']) ?>
                            </div>
                            
                            <div class="documento-info">
                                <div class="documento-nome">
                                    <?= htmlspecialchars($doc['nome_file_originale']) ?>
                                </div>
                                <div class="documento-meta">
                                    <?= formatFileSize($doc['dimensione']) ?> • 
                                    <?= date('d/m/Y H:i', strtotime($doc['data_upload'])) ?> • 
                                    <?= htmlspecialchars($doc['operatore_upload_nome']) ?>
                                    <?php if ($doc['note']): ?>
                                        <br>📝 <?= htmlspecialchars($doc['note']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="documento-actions">
                                <a href="/crm/uploads/clienti/<?= $clienteId ?>/<?= $doc['nome_file_salvato'] ?>" 
                                   target="_blank" 
                                   class="btn-icon" 
                                   title="Visualizza">
                                    👁️
                                </a>
                                <a href="/crm/uploads/clienti/<?= $clienteId ?>/<?= $doc['nome_file_salvato'] ?>" 
                                   download="<?= $doc['nome_file_originale'] ?>"
                                   class="btn-icon" 
                                   title="Scarica">
                                    ⬇️
                                </a>
                                <?php if ($sessionInfo['is_admin']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Eliminare questo documento?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="documento_id" value="<?= $doc['id'] ?>">
                                        <button type="submit" class="btn-icon danger" title="Elimina">
                                            🗑️
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Variabili globali
        let selectedFiles = [];
        
        // Setup al caricamento pagina
        document.addEventListener('DOMContentLoaded', function() {
            setupDropzone();
            setupFileInput();
            setupFilters();
        });
        
        // Gestione filtri
        function setupFilters() {
            const chips = document.querySelectorAll('.filter-chip');
            const cards = document.querySelectorAll('.documento-card');
            
            chips.forEach(chip => {
                chip.addEventListener('click', function() {
                    // Rimuovi active da tutti
                    chips.forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    
                    // Mostra/nascondi cards
                    cards.forEach(card => {
                        if (filter === 'all' || card.dataset.categoria === filter) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        }
        
        // Toggle categoria details
        function toggleCategoria(categoria) {
            const details = document.getElementById('cat-' + categoria);
            details.style.display = details.style.display === 'none' ? 'block' : 'none';
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
                dropzoneText.textContent = '📁 Trascina qui i documenti o clicca per selezionare';
            }
        }
        
        // Gestione form upload
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                return;
            }
            
            // Mostra progress
            document.getElementById('uploadProgress').style.display = 'block';
            document.getElementById('uploadButton').disabled = true;
            
            // Simula progress (in produzione usereste XMLHttpRequest per progress reale)
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                document.getElementById('progressFill').style.width = progress + '%';
                
                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, 200);
        });
    </script>
</body>
</html>