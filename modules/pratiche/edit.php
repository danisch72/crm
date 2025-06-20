<?php
/**
 * modules/pratiche/edit.php - Modifica Pratica
 * 
 * ✅ FORM MODIFICA PRATICA ESISTENTE
 * 
 * Features:
 * - Caricamento dati pratica esistente
 * - Modifica informazioni principali
 * - Gestione task (aggiunta/rimozione)
 * - Cambio stato e priorità
 * - Validazioni e controlli permessi
 */

// Verifica router
if (!defined('PRATICHE_ROUTER_LOADED')) {
    header('Location: /crm/?action=pratiche');
    exit;
}

// Variabili dal router:
// $sessionInfo, $db, $currentUser, $pratica (già caricata e verificata dal router)

// Carica task esistenti
$tasks = $db->select("
    SELECT * FROM task 
    WHERE pratica_id = ? 
    ORDER BY ordine, id
", [$pratica['id']]);

// Gestione form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    try {
        // Validazione campi
        $titolo = trim($_POST['titolo'] ?? '');
        $descrizione = trim($_POST['descrizione'] ?? '');
        $priorita = $_POST['priorita'] ?? 'media';
        $stato = $_POST['stato'] ?? $pratica['stato'];
        $data_scadenza = $_POST['data_scadenza'] ?? '';
        $ore_stimate = floatval($_POST['ore_stimate'] ?? 0);
        
        if (empty($titolo)) {
            $errors[] = 'Il titolo è obbligatorio';
        }
        
        if (empty($data_scadenza)) {
            $errors[] = 'La data di scadenza è obbligatoria';
        }
        
        if (empty($errors)) {
            $db->beginTransaction();
            
            // Aggiorna pratica
            $updated = $db->update('pratiche', [
                'titolo' => $titolo,
                'descrizione' => $descrizione,
                'priorita' => $priorita,
                'stato' => $stato,
                'data_scadenza' => $data_scadenza,
                'ore_stimate' => $ore_stimate,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$pratica['id']]);
            
            // Gestione task
            if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
                // Marca tutti i task esistenti per la cancellazione
                $existingTaskIds = array_column($tasks, 'id');
                $updatedTaskIds = [];
                
                foreach ($_POST['tasks'] as $index => $taskData) {
                    if (!empty($taskData['titolo'])) {
                        if (isset($taskData['id']) && $taskData['id'] > 0) {
                            // Aggiorna task esistente
                            $db->update('task', [
                                'titolo' => $taskData['titolo'],
                                'descrizione' => $taskData['descrizione'] ?? '',
                                'ore_stimate' => floatval($taskData['ore_stimate'] ?? 0),
                                'ordine' => $index,
                                'updated_at' => date('Y-m-d H:i:s')
                            ], 'id = ? AND pratica_id = ?', [$taskData['id'], $pratica['id']]);
                            
                            $updatedTaskIds[] = $taskData['id'];
                        } else {
                            // Crea nuovo task
                            $db->insert('task', [
                                'pratica_id' => $pratica['id'],
                                'titolo' => $taskData['titolo'],
                                'descrizione' => $taskData['descrizione'] ?? '',
                                'stato' => 'da_fare',
                                'ore_stimate' => floatval($taskData['ore_stimate'] ?? 0),
                                'ordine' => $index,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                }
                
                // Elimina task rimossi
                $taskToDelete = array_diff($existingTaskIds, $updatedTaskIds);
                if (!empty($taskToDelete)) {
                    $placeholders = implode(',', array_fill(0, count($taskToDelete), '?'));
                    $db->query(
                        "DELETE FROM task WHERE pratica_id = ? AND id IN ($placeholders)",
                        array_merge([$pratica['id']], $taskToDelete)
                    );
                }
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = '✅ Pratica aggiornata con successo';
            header('Location: /crm/?action=pratiche&view=view&id=' . $pratica['id']);
            exit;
            
        }
    } catch (Exception $e) {
        $db->rollback();
        $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
    }
}

// Include i componenti UI
require_once CRM_PATH . '/components/header.php';
require_once CRM_PATH . '/components/navigation.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Pratica - CRM Re.De Consulting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/crm/assets/css/style.css">
    <style>
        /* Form styles */
        .edit-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .form-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-label.required::after {
            content: ' *';
            color: #dc2626;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0,120,73,0.1);
        }
        
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Task management */
        .tasks-section {
            margin-top: 2rem;
        }
        
        .task-list {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .task-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            background: white;
            position: relative;
        }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-item:hover {
            background: #f9fafb;
        }
        
        .task-header {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }
        
        .task-handle {
            cursor: move;
            color: #9ca3af;
            padding: 0.25rem;
        }
        
        .task-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e5e7eb;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }
        
        .task-content {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
        }
        
        .task-fields {
            display: grid;
            gap: 0.5rem;
        }
        
        .task-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
            align-items: flex-start;
        }
        
        .btn-icon {
            background: transparent;
            border: none;
            padding: 0.25rem;
            cursor: pointer;
            font-size: 1.25rem;
            opacity: 0.7;
            transition: opacity 0.15s;
        }
        
        .btn-icon:hover {
            opacity: 1;
        }
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #007849;
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 120, 73, 0.2);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include CRM_PATH . '/components/navigation.php'; ?>
        
        <div class="main-wrapper">
            <?php include CRM_PATH . '/components/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="edit-container">
                    <div class="form-header">
                        <h1>✏️ Modifica Pratica</h1>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="form-card">
                        <!-- Sezione Info Base -->
                        <div class="form-section">
                            <h2 class="form-section-title">Informazioni Principali</h2>
                            
                            <div class="form-group">
                                <label class="form-label required">Titolo Pratica</label>
                                <input type="text" 
                                       name="titolo" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($pratica['titolo']) ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Descrizione</label>
                                <textarea name="descrizione" 
                                          class="form-control form-textarea"><?= htmlspecialchars($pratica['descrizione'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">Stato</label>
                                    <select name="stato" class="form-control form-select" required>
                                        <?php foreach (PRATICHE_STATI as $key => $stato): ?>
                                        <option value="<?= $key ?>" <?= $pratica['stato'] === $key ? 'selected' : '' ?>>
                                            <?= $stato['icon'] ?> <?= $stato['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Priorità</label>
                                    <select name="priorita" class="form-control form-select" required>
                                        <?php foreach (PRATICHE_PRIORITA as $key => $priorita): ?>
                                        <option value="<?= $key ?>" <?= $pratica['priorita'] === $key ? 'selected' : '' ?>>
                                            <?= $priorita['icon'] ?> <?= $priorita['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label required">Data Scadenza</label>
                                    <input type="date" 
                                           name="data_scadenza" 
                                           class="form-control"
                                           value="<?= $pratica['data_scadenza'] ?>"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Ore Stimate</label>
                                    <input type="number" 
                                           name="ore_stimate" 
                                           class="form-control"
                                           value="<?= $pratica['ore_stimate'] ?>"
                                           min="0"
                                           step="0.5">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sezione Task -->
                        <div class="form-section tasks-section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h2 class="form-section-title" style="margin: 0;">Task della Pratica</h2>
                                <button type="button" class="btn btn-secondary" onclick="addTask()">
                                    ➕ Aggiungi Task
                                </button>
                            </div>
                            
                            <div class="task-list" id="taskList">
                                <?php foreach ($tasks as $index => $task): ?>
                                <div class="task-item" data-task-id="<?= $task['id'] ?>">
                                    <div class="task-header">
                                        <span class="task-handle">⋮⋮</span>
                                        <span class="task-number"><?= $index + 1 ?></span>
                                        
                                        <div class="task-content">
                                            <div class="task-fields">
                                                <input type="hidden" name="tasks[<?= $index ?>][id]" value="<?= $task['id'] ?>">
                                                
                                                <input type="text" 
                                                       name="tasks[<?= $index ?>][titolo]" 
                                                       class="task-input"
                                                       placeholder="Titolo task..."
                                                       value="<?= htmlspecialchars($task['titolo']) ?>"
                                                       required>
                                                
                                                <textarea name="tasks[<?= $index ?>][descrizione]" 
                                                          class="task-input"
                                                          placeholder="Descrizione task..."
                                                          rows="2"><?= htmlspecialchars($task['descrizione'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                                <input type="number" 
                                                       name="tasks[<?= $index ?>][ore_stimate]" 
                                                       class="task-input"
                                                       placeholder="Ore"
                                                       value="<?= $task['ore_stimate'] ?>"
                                                       min="0"
                                                       step="0.5"
                                                       style="width: 80px;">
                                                
                                                <div class="task-actions">
                                                    <button type="button" class="btn-icon" onclick="removeTask(this)" title="Rimuovi task">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="form-actions">
                            <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" class="btn btn-secondary">
                                ✖ Annulla
                            </a>
                            <button type="submit" class="btn btn-primary">
                                💾 Salva Modifiche
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        let taskIndex = <?= count($tasks) ?>;
        
        function addTask() {
            const taskList = document.getElementById('taskList');
            const newTask = document.createElement('div');
            newTask.className = 'task-item';
            newTask.innerHTML = `
                <div class="task-header">
                    <span class="task-handle">⋮⋮</span>
                    <span class="task-number">${taskList.children.length + 1}</span>
                    
                    <div class="task-content">
                        <div class="task-fields">
                            <input type="hidden" name="tasks[${taskIndex}][id]" value="">
                            
                            <input type="text" 
                                   name="tasks[${taskIndex}][titolo]" 
                                   class="task-input"
                                   placeholder="Titolo task..."
                                   required>
                            
                            <textarea name="tasks[${taskIndex}][descrizione]" 
                                      class="task-input"
                                      placeholder="Descrizione task..."
                                      rows="2"></textarea>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                            <input type="number" 
                                   name="tasks[${taskIndex}][ore_stimate]" 
                                   class="task-input"
                                   placeholder="Ore"
                                   min="0"
                                   step="0.5"
                                   style="width: 80px;">
                            
                            <div class="task-actions">
                                <button type="button" class="btn-icon" onclick="removeTask(this)" title="Rimuovi task">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            taskList.appendChild(newTask);
            taskIndex++;
            
            // Focus sul nuovo campo
            newTask.querySelector('input[type="text"]').focus();
            
            // Rinumera task
            updateTaskNumbers();
        }
        
        function removeTask(button) {
            const taskItem = button.closest('.task-item');
            taskItem.remove();
            updateTaskNumbers();
        }
        
        function updateTaskNumbers() {
            document.querySelectorAll('.task-item').forEach((item, index) => {
                item.querySelector('.task-number').textContent = index + 1;
            });
        }
        
        // Drag & Drop (base)
        document.addEventListener('DOMContentLoaded', function() {
            const taskList = document.getElementById('taskList');
            let draggedElement = null;
            
            taskList.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('task-handle')) {
                    draggedElement = e.target.closest('.task-item');
                    draggedElement.style.opacity = '0.5';
                }
            });
            
            taskList.addEventListener('dragend', function(e) {
                if (draggedElement) {
                    draggedElement.style.opacity = '';
                    draggedElement = null;
                }
            });
            
            taskList.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            taskList.addEventListener('drop', function(e) {
                e.preventDefault();
                // Implementare logica riordino
            });
        });
    </script>
</body>
</html>