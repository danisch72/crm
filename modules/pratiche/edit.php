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
                            $taskId = $db->insert('task', [
                                'pratica_id' => $pratica['id'],
                                'cliente_id' => $pratica['cliente_id'],
                                'titolo' => $taskData['titolo'],
                                'descrizione' => $taskData['descrizione'] ?? '',
                                'data_scadenza' => date('Y-m-d', strtotime('+7 days')),
                                'stato' => 'da_iniziare',
                                'priorita' => 'media',
                                'ore_stimate' => floatval($taskData['ore_stimate'] ?? 0),
                                'ordine' => $index,
                                'operatore_assegnato_id' => $currentUser['id']
                            ]);
                        }
                    }
                }
                
                // Elimina task rimossi
                $toDelete = array_diff($existingTaskIds, $updatedTaskIds);
                if (!empty($toDelete)) {
                    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                    $db->query(
                        "DELETE FROM task WHERE id IN ($placeholders) AND pratica_id = ?",
                        array_merge($toDelete, [$pratica['id']])
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
            content: " *";
            color: #ef4444;
        }
        
        .form-control {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.15s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007849;
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Task section */
        .tasks-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .task-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .task-header {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 1rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .task-number {
            background: #007849;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .task-title-input {
            flex: 1;
        }
        
        .task-ore {
            width: 80px;
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
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
            <?php include CRM_PATH . '/components/header.php'; ?>
            
            <main class="main-content">
                <div class="edit-container">
                    <!-- Header -->
                    <div class="form-header">
                        <h1>✏️ Modifica Pratica</h1>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <strong>Errori nel form:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Form -->
                    <form method="POST" class="form-card">
                        <!-- Informazioni Base -->
                        <div class="form-section">
                            <h2 class="form-section-title">📋 Informazioni Pratica</h2>
                            
                            <div class="form-grid">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label required">Titolo pratica</label>
                                    <input type="text" 
                                           name="titolo" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($pratica['titolo']) ?>"
                                           required>
                                </div>
                                
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label">Descrizione</label>
                                    <textarea name="descrizione" 
                                              class="form-control form-textarea"><?= htmlspecialchars($pratica['descrizione']) ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Priorità</label>
                                    <select name="priorita" class="form-control" required>
                                        <?php foreach (PRATICHE_PRIORITA as $key => $config): ?>
                                            <option value="<?= $key ?>" 
                                                    <?= $pratica['priorita'] === $key ? 'selected' : '' ?>>
                                                <?= $config['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Stato</label>
                                    <select name="stato" class="form-control" required>
                                        <?php foreach (PRATICHE_STATI as $key => $config): ?>
                                            <option value="<?= $key ?>" 
                                                    <?= $pratica['stato'] === $key ? 'selected' : '' ?>>
                                                <?= $config['icon'] ?> <?= $config['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">Data scadenza</label>
                                    <input type="date" 
                                           name="data_scadenza" 
                                           class="form-control" 
                                           value="<?= $pratica['data_scadenza'] ?>"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Ore stimate</label>
                                    <input type="number" 
                                           name="ore_stimate" 
                                           class="form-control" 
                                           value="<?= $pratica['ore_stimate'] ?>"
                                           step="0.5"
                                           min="0">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Task -->
                        <div class="tasks-section">
                            <h2 class="form-section-title">📋 Task della Pratica</h2>
                            
                            <div id="task-list">
                                <?php foreach ($tasks as $index => $task): ?>
                                    <div class="task-item" data-index="<?= $index ?>">
                                        <input type="hidden" name="tasks[<?= $index ?>][id]" value="<?= $task['id'] ?>">
                                        <div class="task-header">
                                            <span class="task-number"><?= $index + 1 ?></span>
                                            <input type="text" 
                                                   name="tasks[<?= $index ?>][titolo]" 
                                                   class="form-control task-title-input" 
                                                   placeholder="Titolo del task"
                                                   value="<?= htmlspecialchars($task['titolo']) ?>"
                                                   required>
                                            <input type="number" 
                                                   name="tasks[<?= $index ?>][ore_stimate]" 
                                                   class="form-control task-ore" 
                                                   placeholder="Ore"
                                                   value="<?= $task['ore_stimate'] ?>"
                                                   step="0.5"
                                                   min="0">
                                            <div class="task-actions">
                                                <button type="button" class="btn-icon" onclick="removeTask(<?= $index ?>)" title="Rimuovi">
                                                    🗑️
                                                </button>
                                            </div>
                                        </div>
                                        <textarea name="tasks[<?= $index ?>][descrizione]" 
                                                  class="form-control form-textarea" 
                                                  placeholder="Descrizione (opzionale)"
                                                  style="margin-top: 0.5rem; min-height: 60px;"><?= htmlspecialchars($task['descrizione']) ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-secondary" onclick="addTask()">
                                ➕ Aggiungi Task
                            </button>
                        </div>
                        
                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                💾 Salva Modifiche
                            </button>
                            <a href="/crm/?action=pratiche&view=view&id=<?= $pratica['id'] ?>" class="btn btn-secondary">
                                ❌ Annulla
                            </a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Gestione task dinamici
        let taskIndex = <?= count($tasks) ?>;
        
        function addTask() {
            const taskList = document.getElementById('task-list');
            const newTask = document.createElement('div');
            newTask.className = 'task-item';
            newTask.dataset.index = taskIndex;
            
            newTask.innerHTML = `
                <input type="hidden" name="tasks[${taskIndex}][id]" value="0">
                <div class="task-header">
                    <span class="task-number">${taskIndex + 1}</span>
                    <input type="text" 
                           name="tasks[${taskIndex}][titolo]" 
                           class="form-control task-title-input" 
                           placeholder="Titolo del task"
                           required>
                    <input type="number" 
                           name="tasks[${taskIndex}][ore_stimate]" 
                           class="form-control task-ore" 
                           placeholder="Ore"
                           value="1"
                           step="0.5"
                           min="0">
                    <div class="task-actions">
                        <button type="button" class="btn-icon" onclick="removeTask(${taskIndex})" title="Rimuovi">
                            🗑️
                        </button>
                    </div>
                </div>
                <textarea name="tasks[${taskIndex}][descrizione]" 
                          class="form-control form-textarea" 
                          placeholder="Descrizione (opzionale)"
                          style="margin-top: 0.5rem; min-height: 60px;"></textarea>
            `;
            
            taskList.appendChild(newTask);
            taskIndex++;
            updateTaskNumbers();
        }
        
        function removeTask(index) {
            const taskItem = document.querySelector(`[data-index="${index}"]`);
            if (taskItem && document.querySelectorAll('.task-item').length > 1) {
                taskItem.remove();
                updateTaskNumbers();
            }
        }
        
        function updateTaskNumbers() {
            document.querySelectorAll('.task-item').forEach((item, index) => {
                item.querySelector('.task-number').textContent = index + 1;
            });
        }
    </script>
</body>
</html>