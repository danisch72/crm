/**
 * modules/pratiche/assets/js/pratiche.js - JavaScript Task Management
 * 
 * ✅ GESTIONE INTERATTIVITÀ MODULO PRATICHE
 * 
 * Features:
 * - Drag & drop riordino task
 * - Validazioni client-side
 * - Update AJAX in tempo reale
 * - Gestione wizard creazione
 * - Timer tracking integrato
 * 
 * @version 1.0
 * @author CRM Re.De Consulting
 */

// ============================================
// NAMESPACE E CONFIGURAZIONE
// ============================================

const PraticheManager = (function() {
    'use strict';
    
    // Configurazione
    const config = {
        apiBaseUrl: '/crm/modules/pratiche/api/',
        dragClass: 'dragging',
        dropZoneClass: 'drop-zone',
        animationDuration: 300,
        autoSaveDelay: 1000,
        validationDelay: 500
    };
    
    // Cache elementi DOM
    let elements = {};
    
    // Stato applicazione
    let state = {
        isDragging: false,
        currentTask: null,
        autoSaveTimer: null,
        validationTimer: null,
        unsavedChanges: false
    };
    
    // ============================================
    // INIZIALIZZAZIONE
    // ============================================
    
    function init() {
        cacheElements();
        bindEvents();
        initDragAndDrop();
        initValidation();
        initAjaxForms();
        
        // Inizializza componenti specifici per pagina
        if (document.querySelector('.wizard-container')) {
            initWizard();
        }
        
        if (document.querySelector('.task-list')) {
            initTaskList();
        }
        
        console.log('✅ PraticheManager initialized');
    }
    
    function cacheElements() {
        elements = {
            taskList: document.querySelector('.task-list'),
            taskItems: document.querySelectorAll('.task-item'),
            forms: document.querySelectorAll('form[data-ajax="true"]'),
            wizardSteps: document.querySelectorAll('.wizard-step'),
            saveIndicator: document.querySelector('.save-indicator')
        };
    }
    
    // ============================================
    // DRAG & DROP TASK
    // ============================================
    
    function initDragAndDrop() {
        if (!elements.taskList) return;
        
        // Rendi i task draggable
        elements.taskItems.forEach(task => {
            task.draggable = true;
            
            // Eventi drag
            task.addEventListener('dragstart', handleDragStart);
            task.addEventListener('dragend', handleDragEnd);
            task.addEventListener('dragover', handleDragOver);
            task.addEventListener('drop', handleDrop);
            task.addEventListener('dragenter', handleDragEnter);
            task.addEventListener('dragleave', handleDragLeave);
        });
        
        // Aggiungi handle di trascinamento
        addDragHandles();
    }
    
    function addDragHandles() {
        elements.taskItems.forEach(task => {
            if (!task.querySelector('.drag-handle')) {
                const handle = document.createElement('span');
                handle.className = 'drag-handle';
                handle.innerHTML = '⋮⋮';
                handle.title = 'Trascina per riordinare';
                task.prepend(handle);
            }
        });
    }
    
    function handleDragStart(e) {
        state.isDragging = true;
        state.currentTask = this;
        
        this.classList.add(config.dragClass);
        e.effectAllowed = 'move';
        
        // Salva dati per il drop
        e.dataTransfer.setData('text/html', this.innerHTML);
        e.dataTransfer.setData('taskId', this.dataset.taskId);
        
        // Feedback visivo
        setTimeout(() => {
            this.style.opacity = '0.4';
        }, 0);
    }
    
    function handleDragEnd(e) {
        state.isDragging = false;
        
        this.classList.remove(config.dragClass);
        this.style.opacity = '';
        
        // Rimuovi tutte le classi drop-zone
        document.querySelectorAll('.' + config.dropZoneClass).forEach(el => {
            el.classList.remove(config.dropZoneClass);
        });
        
        // Salva nuovo ordine
        saveTaskOrder();
    }
    
    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        
        e.dataTransfer.dropEffect = 'move';
        
        // Calcola posizione per inserimento
        const afterElement = getDragAfterElement(elements.taskList, e.clientY);
        
        if (afterElement == null) {
            elements.taskList.appendChild(state.currentTask);
        } else {
            elements.taskList.insertBefore(state.currentTask, afterElement);
        }
        
        return false;
    }
    
    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        if (state.currentTask !== this) {
            // Riordina elementi
            updateTaskNumbers();
            showNotification('Task riordinati', 'success');
        }
        
        return false;
    }
    
    function handleDragEnter(e) {
        this.classList.add(config.dropZoneClass);
    }
    
    function handleDragLeave(e) {
        this.classList.remove(config.dropZoneClass);
    }
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.task-item:not(.' + config.dragClass + ')')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    function updateTaskNumbers() {
        const tasks = elements.taskList.querySelectorAll('.task-item');
        tasks.forEach((task, index) => {
            const numberEl = task.querySelector('.task-number');
            if (numberEl) {
                numberEl.textContent = index + 1;
            }
            task.dataset.order = index;
        });
    }
    
    // ============================================
    // VALIDAZIONI CLIENT-SIDE
    // ============================================
    
    function initValidation() {
        // Validazione form in tempo reale
        document.querySelectorAll('input[required], textarea[required]').forEach(field => {
            field.addEventListener('blur', validateField);
            field.addEventListener('input', debounce(validateField, config.validationDelay));
        });
        
        // Validazione date
        document.querySelectorAll('input[type="date"]').forEach(dateField => {
            dateField.addEventListener('change', validateDateField);
        });
        
        // Validazione dipendenze task
        document.querySelectorAll('select[name*="dipende_da"]').forEach(select => {
            select.addEventListener('change', validateTaskDependencies);
        });
    }
    
    function validateField(e) {
        const field = e.target || this;
        const value = field.value.trim();
        const fieldName = field.name || field.id;
        
        // Rimuovi errori precedenti
        clearFieldError(field);
        
        // Validazione required
        if (field.hasAttribute('required') && !value) {
            showFieldError(field, 'Questo campo è obbligatorio');
            return false;
        }
        
        // Validazioni specifiche per tipo
        switch (field.type) {
            case 'email':
                if (value && !isValidEmail(value)) {
                    showFieldError(field, 'Email non valida');
                    return false;
                }
                break;
                
            case 'number':
                const min = parseFloat(field.min);
                const max = parseFloat(field.max);
                const num = parseFloat(value);
                
                if (value && isNaN(num)) {
                    showFieldError(field, 'Inserire un numero valido');
                    return false;
                }
                
                if (!isNaN(min) && num < min) {
                    showFieldError(field, `Il valore minimo è ${min}`);
                    return false;
                }
                
                if (!isNaN(max) && num > max) {
                    showFieldError(field, `Il valore massimo è ${max}`);
                    return false;
                }
                break;
        }
        
        // Campo valido
        showFieldSuccess(field);
        return true;
    }
    
    function validateDateField(e) {
        const field = e.target;
        const value = field.value;
        const min = field.min;
        const max = field.max;
        
        clearFieldError(field);
        
        if (!value) return true;
        
        const date = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Verifica date minime/massime
        if (min && date < new Date(min)) {
            showFieldError(field, 'Data non può essere precedente a ' + formatDate(min));
            return false;
        }
        
        if (max && date > new Date(max)) {
            showFieldError(field, 'Data non può essere successiva a ' + formatDate(max));
            return false;
        }
        
        // Validazioni specifiche per scadenze
        if (field.name === 'data_scadenza' && date < today) {
            showFieldWarning(field, 'Attenzione: data scadenza nel passato');
        }
        
        return true;
    }
    
    function validateTaskDependencies(e) {
        const select = e.target;
        const selectedTaskId = select.value;
        const currentTaskId = select.closest('.task-item')?.dataset.taskId;
        
        if (!selectedTaskId) return true;
        
        // Verifica dipendenze circolari
        fetch(`${config.apiBaseUrl}task_api.php?action=check_circular_dependency`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: currentTaskId,
                depends_on: selectedTaskId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.has_circular_dependency) {
                showFieldError(select, 'Dipendenza circolare rilevata');
                select.value = '';
            }
        });
    }
    
    // ============================================
    // UPDATE AJAX
    // ============================================
    
    function initAjaxForms() {
        elements.forms.forEach(form => {
            form.addEventListener('submit', handleAjaxSubmit);
        });
        
        // Auto-save per campi specifici
        document.querySelectorAll('[data-autosave="true"]').forEach(field => {
            field.addEventListener('change', debounce(autoSaveField, config.autoSaveDelay));
        });
    }
    
    function handleAjaxSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Valida tutti i campi
        if (!validateForm(form)) {
            showNotification('Correggere gli errori nel form', 'error');
            return;
        }
        
        // Disabilita submit
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvataggio...';
        }
        
        // Prepara dati
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Invia richiesta
        fetch(form.action, {
            method: form.method || 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification(result.message || 'Salvato con successo', 'success');
                
                // Callback specifici
                if (form.dataset.onSuccess) {
                    window[form.dataset.onSuccess](result);
                }
                
                // Reset stato
                state.unsavedChanges = false;
                updateSaveIndicator();
                
            } else {
                showNotification(result.message || 'Errore durante il salvataggio', 'error');
            }
        })
        .catch(error => {
            console.error('Ajax error:', error);
            showNotification('Errore di connessione', 'error');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = submitBtn.dataset.originalText || 'Salva';
            }
        });
    }
    
    function autoSaveField(e) {
        const field = e.target;
        const endpoint = field.dataset.endpoint || 'task_api.php';
        const action = field.dataset.action || 'update_field';
        
        // Mostra indicatore salvataggio
        showSaving();
        
        fetch(`${config.apiBaseUrl}${endpoint}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: field.dataset.id,
                field: field.name,
                value: field.value
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showSaved();
                field.classList.add('saved');
                setTimeout(() => field.classList.remove('saved'), 2000);
            } else {
                showError();
                showNotification('Errore durante il salvataggio automatico', 'error');
            }
        })
        .catch(error => {
            console.error('Autosave error:', error);
            showError();
        });
    }
    
    function saveTaskOrder() {
        const tasks = Array.from(elements.taskList.querySelectorAll('.task-item'));
        const orderData = tasks.map((task, index) => ({
            id: task.dataset.taskId,
            ordine: index
        }));
        
        showSaving();
        
        fetch(`${config.apiBaseUrl}task_api.php?action=update_order`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tasks: orderData })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showSaved();
            } else {
                showError();
                showNotification('Errore durante il riordino', 'error');
            }
        });
    }
    
    // ============================================
    // WIZARD CREAZIONE PRATICA
    // ============================================
    
    function initWizard() {
        const wizard = document.querySelector('.wizard-container');
        if (!wizard) return;
        
        // Navigazione wizard
        wizard.querySelectorAll('.wizard-nav button').forEach(btn => {
            btn.addEventListener('click', handleWizardNavigation);
        });
        
        // Validazione step
        wizard.addEventListener('change', validateWizardStep);
    }
    
    function handleWizardNavigation(e) {
        const button = e.target;
        const direction = button.value;
        const currentStep = parseInt(button.dataset.currentStep);
        
        if (direction === 'next') {
            if (validateWizardStep(currentStep)) {
                navigateToStep(currentStep + 1);
            }
        } else if (direction === 'prev') {
            navigateToStep(currentStep - 1);
        } else if (direction === 'finish') {
            if (validateWizardStep(currentStep)) {
                submitWizard();
            }
        }
    }
    
    function validateWizardStep(stepOrEvent) {
        const step = typeof stepOrEvent === 'number' ? stepOrEvent : getCurrentWizardStep();
        const stepElement = document.querySelector(`.wizard-step[data-step="${step}"]`);
        
        if (!stepElement) return true;
        
        const requiredFields = stepElement.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!validateField({ target: field })) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    function navigateToStep(step) {
        // Nascondi tutti gli step
        document.querySelectorAll('.wizard-step').forEach(s => {
            s.style.display = 'none';
        });
        
        // Mostra step corrente
        const targetStep = document.querySelector(`.wizard-step[data-step="${step}"]`);
        if (targetStep) {
            targetStep.style.display = 'block';
            
            // Aggiorna progress bar
            updateWizardProgress(step);
            
            // Focus primo campo
            const firstField = targetStep.querySelector('input, select, textarea');
            if (firstField) {
                firstField.focus();
            }
        }
    }
    
    function updateWizardProgress(step) {
        const totalSteps = document.querySelectorAll('.wizard-step').length;
        const progress = (step / totalSteps) * 100;
        
        const progressBar = document.querySelector('.wizard-progress-bar');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }
        
        // Aggiorna indicatori step
        document.querySelectorAll('.wizard-step-indicator').forEach((indicator, index) => {
            if (index < step) {
                indicator.classList.add('completed');
                indicator.classList.remove('active');
            } else if (index === step - 1) {
                indicator.classList.add('active');
                indicator.classList.remove('completed');
            } else {
                indicator.classList.remove('active', 'completed');
            }
        });
    }
    
    // ============================================
    // GESTIONE TASK LIST
    // ============================================
    
    function initTaskList() {
        // Quick actions
        document.querySelectorAll('.task-quick-action').forEach(action => {
            action.addEventListener('click', handleQuickAction);
        });
        
        // Inline editing
        document.querySelectorAll('.editable').forEach(element => {
            element.addEventListener('dblclick', enableInlineEdit);
        });
        
        // Checkbox completamento
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleTaskComplete);
        });
    }
    
    function handleQuickAction(e) {
        e.preventDefault();
        
        const action = e.currentTarget;
        const taskId = action.dataset.taskId;
        const actionType = action.dataset.action;
        
        switch (actionType) {
            case 'start':
                startTask(taskId);
                break;
            case 'pause':
                pauseTask(taskId);
                break;
            case 'complete':
                completeTask(taskId);
                break;
            case 'assign':
                showAssignDialog(taskId);
                break;
        }
    }
    
    function enableInlineEdit(e) {
        const element = e.target;
        const originalValue = element.textContent;
        const field = element.dataset.field;
        
        // Crea input
        const input = document.createElement('input');
        input.type = 'text';
        input.value = originalValue;
        input.className = 'inline-edit-input';
        
        // Sostituisci elemento con input
        element.replaceWith(input);
        input.focus();
        input.select();
        
        // Gestisci salvataggio
        const saveEdit = () => {
            const newValue = input.value.trim();
            
            if (newValue && newValue !== originalValue) {
                // Salva via AJAX
                updateTaskField(element.dataset.taskId, field, newValue)
                    .then(() => {
                        element.textContent = newValue;
                        input.replaceWith(element);
                    })
                    .catch(() => {
                        element.textContent = originalValue;
                        input.replaceWith(element);
                    });
            } else {
                input.replaceWith(element);
            }
        };
        
        // Eventi
        input.addEventListener('blur', saveEdit);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveEdit();
            } else if (e.key === 'Escape') {
                input.replaceWith(element);
            }
        });
    }
    
    function handleTaskComplete(e) {
        const checkbox = e.target;
        const taskId = checkbox.dataset.taskId;
        const isCompleted = checkbox.checked;
        
        updateTaskStatus(taskId, isCompleted ? 'completato' : 'da_fare')
            .then(result => {
                const taskItem = checkbox.closest('.task-item');
                if (isCompleted) {
                    taskItem.classList.add('completed');
                    showNotification('Task completato!', 'success');
                } else {
                    taskItem.classList.remove('completed');
                }
            })
            .catch(error => {
                checkbox.checked = !isCompleted;
                showNotification('Errore aggiornamento task', 'error');
            });
    }
    
    // ============================================
    // FUNZIONI API
    // ============================================
    
    function startTask(taskId) {
        return callAPI('task_api.php', 'start_task', { task_id: taskId })
            .then(result => {
                if (result.success) {
                    updateTaskUI(taskId, 'in_corso');
                    if (window.crmInterface) {
                        window.crmInterface.startTaskTimer(taskId);
                    }
                }
                return result;
            });
    }
    
    function pauseTask(taskId) {
        return callAPI('task_api.php', 'pause_task', { task_id: taskId })
            .then(result => {
                if (result.success) {
                    updateTaskUI(taskId, 'paused');
                    if (window.crmInterface) {
                        window.crmInterface.stopTaskTimer(taskId);
                    }
                }
                return result;
            });
    }
    
    function completeTask(taskId) {
        return callAPI('task_api.php', 'complete_task', { task_id: taskId })
            .then(result => {
                if (result.success) {
                    updateTaskUI(taskId, 'completato');
                    showNotification('Task completato con successo!', 'success');
                }
                return result;
            });
    }
    
    function updateTaskField(taskId, field, value) {
        return callAPI('task_api.php', 'update_field', {
            task_id: taskId,
            field: field,
            value: value
        });
    }
    
    function updateTaskStatus(taskId, status) {
        return callAPI('task_api.php', 'update_status', {
            task_id: taskId,
            stato: status
        });
    }
    
    function callAPI(endpoint, action, data) {
        return fetch(`${config.apiBaseUrl}${endpoint}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .catch(error => {
            console.error('API Error:', error);
            throw error;
        });
    }
    
    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    
    function validateForm(form) {
        const fields = form.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        fields.forEach(field => {
            if (!validateField({ target: field })) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    function showFieldError(field, message) {
        field.classList.add('error');
        field.classList.remove('success', 'warning');
        
        let errorEl = field.nextElementSibling;
        if (!errorEl || !errorEl.classList.contains('field-error')) {
            errorEl = document.createElement('div');
            errorEl.className = 'field-error';
            field.parentNode.insertBefore(errorEl, field.nextSibling);
        }
        
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
    
    function showFieldWarning(field, message) {
        field.classList.add('warning');
        field.classList.remove('error', 'success');
        
        let warningEl = field.nextElementSibling;
        if (!warningEl || !warningEl.classList.contains('field-warning')) {
            warningEl = document.createElement('div');
            warningEl.className = 'field-warning';
            field.parentNode.insertBefore(warningEl, field.nextSibling);
        }
        
        warningEl.textContent = message;
        warningEl.style.display = 'block';
    }
    
    function showFieldSuccess(field) {
        field.classList.add('success');
        field.classList.remove('error', 'warning');
        clearFieldError(field);
    }
    
    function clearFieldError(field) {
        field.classList.remove('error', 'warning');
        
        const errorEl = field.nextElementSibling;
        if (errorEl && (errorEl.classList.contains('field-error') || errorEl.classList.contains('field-warning'))) {
            errorEl.style.display = 'none';
        }
    }
    
    function updateTaskUI(taskId, status) {
        const taskElement = document.querySelector(`[data-task-id="${taskId}"]`);
        if (!taskElement) return;
        
        // Aggiorna classi
        taskElement.classList.remove('in-corso', 'completato', 'paused');
        taskElement.classList.add(status.replace('_', '-'));
        
        // Aggiorna badge stato
        const statusBadge = taskElement.querySelector('.task-status');
        if (statusBadge) {
            statusBadge.textContent = getStatusLabel(status);
            statusBadge.className = `task-status status-${status.replace('_', '-')}`;
        }
    }
    
    function getStatusLabel(status) {
        const labels = {
            'da_fare': 'Da fare',
            'in_corso': 'In corso',
            'completato': 'Completato',
            'bloccato': 'Bloccato',
            'paused': 'In pausa'
        };
        
        return labels[status] || status;
    }
    
    function showSaving() {
        if (elements.saveIndicator) {
            elements.saveIndicator.textContent = 'Salvataggio...';
            elements.saveIndicator.className = 'save-indicator saving';
        }
    }
    
    function showSaved() {
        if (elements.saveIndicator) {
            elements.saveIndicator.textContent = 'Salvato';
            elements.saveIndicator.className = 'save-indicator saved';
            
            setTimeout(() => {
                elements.saveIndicator.textContent = '';
                elements.saveIndicator.className = 'save-indicator';
            }, 2000);
        }
    }
    
    function showError() {
        if (elements.saveIndicator) {
            elements.saveIndicator.textContent = 'Errore';
            elements.saveIndicator.className = 'save-indicator error';
        }
    }
    
    function updateSaveIndicator() {
        if (state.unsavedChanges) {
            showSaving();
        } else {
            showSaved();
        }
    }
    
    function showNotification(message, type = 'info') {
        // Usa il sistema di notifiche centralizzato se disponibile
        if (window.showSuccess && type === 'success') {
            window.showSuccess(message);
        } else if (window.showError && type === 'error') {
            window.showError(message);
        } else if (window.showInfo) {
            window.showInfo(message);
        } else {
            // Fallback
            console.log(`[${type.toUpperCase()}] ${message}`);
            alert(message);
        }
    }
    
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT');
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function getCurrentWizardStep() {
        const activeStep = document.querySelector('.wizard-step:not([style*="none"])');
        return activeStep ? parseInt(activeStep.dataset.step) : 1;
    }
    
    function showAssignDialog(taskId) {
        // TODO: Implementare dialog assegnazione
        console.log('Show assign dialog for task:', taskId);
    }
    
    function submitWizard() {
        const form = document.querySelector('.wizard-form');
        if (form) {
            form.submit();
        }
    }
    
    // ============================================
    // EVENTI GLOBALI
    // ============================================
    
    function bindEvents() {
        // Previeni perdita dati non salvati
        window.addEventListener('beforeunload', (e) => {
            if (state.unsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Ci sono modifiche non salvate. Vuoi davvero uscire?';
            }
        });
        
        // Traccia modifiche
        document.addEventListener('input', () => {
            state.unsavedChanges = true;
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+S per salvare
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeForm = document.querySelector('form:focus-within');
                if (activeForm) {
                    activeForm.dispatchEvent(new Event('submit'));
                }
            }
        });
    }
    
    // ============================================
    // PUBLIC API
    // ============================================
    
    return {
        init: init,
        startTask: startTask,
        pauseTask: pauseTask,
        completeTask: completeTask,
        updateTaskField: updateTaskField,
        updateTaskStatus: updateTaskStatus,
        showNotification: showNotification,
        validateForm: validateForm
    };
    
})();

// ============================================
// INIZIALIZZAZIONE AL CARICAMENTO DOM
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    PraticheManager.init();
});

// Export globale per utilizzo esterno
window.PraticheManager = PraticheManager;