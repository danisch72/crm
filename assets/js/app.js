/**
 * app.js - JavaScript Unificato CRM Re.De Consulting
 * Gestisce tutte le interazioni comuni dell'applicazione
 */

// Namespace globale per evitare conflitti
window.CRM = window.CRM || {};

/**
 * Modulo principale dell'applicazione
 */
CRM.App = (function() {
    'use strict';
    
    // Configurazione
    const config = {
        sidebarCollapsedClass: 'collapsed',
        activeNavClass: 'active',
        timerUpdateInterval: 1000,
        notificationCheckInterval: 30000,
        apiBaseUrl: '/crm/api/'
    };
    
    // Stato dell'applicazione
    let state = {
        sidebarCollapsed: false,
        workTimerStart: null,
        workTimerInterval: null,
        notifications: []
    };
    
    /**
     * Inizializzazione
     */
    function init() {
        // Inizializza componenti
        initSidebar();
        initWorkTimer();
        initNotifications();
        initTooltips();
        initModals();
        initForms();
        initTables();
        
        // Event listeners globali
        initGlobalEvents();
        
        console.log('✅ CRM App inizializzato');
    }
    
    /**
     * Gestione Sidebar
     */
    function initSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (!sidebar || !toggleBtn) return;
        
        // Recupera stato salvato
        state.sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (state.sidebarCollapsed) {
            sidebar.classList.add(config.sidebarCollapsedClass);
        }
        
        // Toggle sidebar
        toggleBtn.addEventListener('click', function() {
            state.sidebarCollapsed = !state.sidebarCollapsed;
            sidebar.classList.toggle(config.sidebarCollapsedClass);
            localStorage.setItem('sidebarCollapsed', state.sidebarCollapsed);
        });
    }
    
    /**
     * Timer di lavoro
     */
    function initWorkTimer() {
        const timerElement = document.getElementById('workTimer');
        if (!timerElement) return;
        
        // Recupera ora di inizio da sessionStorage
        const savedStart = sessionStorage.getItem('workTimerStart');
        if (savedStart) {
            state.workTimerStart = new Date(savedStart);
        } else {
            state.workTimerStart = new Date();
            sessionStorage.setItem('workTimerStart', state.workTimerStart);
        }
        
        // Aggiorna timer ogni secondo
        updateWorkTimer();
        state.workTimerInterval = setInterval(updateWorkTimer, config.timerUpdateInterval);
    }
    
    function updateWorkTimer() {
        const timerElement = document.getElementById('workTimer');
        if (!timerElement || !state.workTimerStart) return;
        
        const now = new Date();
        const diff = now - state.workTimerStart;
        
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        timerElement.textContent = 
            `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    
    /**
     * Sistema di notifiche
     */
    function initNotifications() {
        checkNotifications();
        setInterval(checkNotifications, config.notificationCheckInterval);
    }
    
    function checkNotifications() {
        // Chiamata API per nuove notifiche
        fetch(config.apiBaseUrl + 'notifications/check')
            .then(response => response.json())
            .then(data => {
                updateNotificationBadge(data.count);
                state.notifications = data.notifications || [];
            })
            .catch(error => console.error('Errore controllo notifiche:', error));
    }
    
    function updateNotificationBadge(count) {
        const badges = document.querySelectorAll('.notification-count');
        badges.forEach(badge => {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'block' : 'none';
        });
    }
    
    /**
     * Tooltip
     */
    function initTooltips() {
        const tooltips = document.querySelectorAll('[data-tooltip]');
        
        tooltips.forEach(element => {
            element.addEventListener('mouseenter', showTooltip);
            element.addEventListener('mouseleave', hideTooltip);
        });
    }
    
    function showTooltip(e) {
        const text = e.target.getAttribute('data-tooltip');
        const tooltip = createTooltipElement(text);
        
        document.body.appendChild(tooltip);
        positionTooltip(tooltip, e.target);
    }
    
    function hideTooltip() {
        const tooltips = document.querySelectorAll('.tooltip');
        tooltips.forEach(t => t.remove());
    }
    
    function createTooltipElement(text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 9999;
        `;
        return tooltip;
    }
    
    function positionTooltip(tooltip, target) {
        const rect = target.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    }
    
    /**
     * Modal
     */
    function initModals() {
        // Apri modal
        document.addEventListener('click', function(e) {
            if (e.target.matches('[data-modal-open]')) {
                const modalId = e.target.getAttribute('data-modal-open');
                openModal(modalId);
            }
            
            // Chiudi modal
            if (e.target.matches('[data-modal-close], .modal-backdrop')) {
                closeModal();
            }
        });
        
        // Chiudi con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }
    
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        const modals = document.querySelectorAll('.modal.active');
        modals.forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
    
    /**
     * Form enhancements
     */
    function initForms() {
        // Auto-save drafts
        initAutoSave();
        
        // Validazione real-time
        initRealtimeValidation();
        
        // Submit con conferma
        initConfirmSubmit();
    }
    
    function initAutoSave() {
        const forms = document.querySelectorAll('[data-autosave]');
        
        forms.forEach(form => {
            const formId = form.getAttribute('data-autosave');
            
            // Recupera bozza salvata
            const savedData = localStorage.getItem(`draft_${formId}`);
            if (savedData) {
                restoreFormData(form, JSON.parse(savedData));
            }
            
            // Salva su input
            form.addEventListener('input', debounce(function() {
                saveFormData(form, formId);
            }, 1000));
            
            // Pulisci su submit
            form.addEventListener('submit', function() {
                localStorage.removeItem(`draft_${formId}`);
            });
        });
    }
    
    function saveFormData(form, formId) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        localStorage.setItem(`draft_${formId}`, JSON.stringify(data));
        showSaveIndicator();
    }
    
    function restoreFormData(form, data) {
        Object.keys(data).forEach(key => {
            const field = form.elements[key];
            if (field) {
                field.value = data[key];
            }
        });
    }
    
    function showSaveIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'save-indicator';
        indicator.textContent = '✓ Bozza salvata';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #059669;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            animation: fadeIn 0.3s;
        `;
        
        document.body.appendChild(indicator);
        setTimeout(() => indicator.remove(), 2000);
    }
    
    function initRealtimeValidation() {
        const inputs = document.querySelectorAll('.form-control[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    }
    
    function validateField(field) {
        const isValid = field.checkValidity();
        
        field.classList.toggle('is-invalid', !isValid);
        
        // Mostra/nascondi messaggio errore
        const errorMsg = field.parentElement.querySelector('.invalid-feedback');
        if (errorMsg) {
            errorMsg.style.display = isValid ? 'none' : 'block';
        }
    }
    
    function initConfirmSubmit() {
        const forms = document.querySelectorAll('[data-confirm-submit]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const message = this.getAttribute('data-confirm-submit');
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    }
    
    /**
     * Tabelle avanzate
     */
    function initTables() {
        // Ordinamento
        initTableSort();
        
        // Selezione multipla
        initTableSelection();
        
        // Ricerca inline
        initTableSearch();
    }
    
    function initTableSort() {
        const sortableHeaders = document.querySelectorAll('th[data-sortable]');
        
        sortableHeaders.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(this);
            });
        });
    }
    
    function sortTable(header) {
        const table = header.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const column = Array.from(header.parentElement.children).indexOf(header);
        const isNumeric = header.getAttribute('data-sortable') === 'numeric';
        const currentOrder = header.getAttribute('data-order') || 'asc';
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        
        // Ordina righe
        rows.sort((a, b) => {
            const aValue = a.cells[column].textContent.trim();
            const bValue = b.cells[column].textContent.trim();
            
            if (isNumeric) {
                return newOrder === 'asc' 
                    ? parseFloat(aValue) - parseFloat(bValue)
                    : parseFloat(bValue) - parseFloat(aValue);
            } else {
                return newOrder === 'asc'
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            }
        });
        
        // Riordina DOM
        rows.forEach(row => tbody.appendChild(row));
        
        // Aggiorna stato
        header.setAttribute('data-order', newOrder);
        updateSortIndicators(header, newOrder);
    }
    
    function updateSortIndicators(header, order) {
        // Rimuovi indicatori esistenti
        document.querySelectorAll('.sort-indicator').forEach(i => i.remove());
        
        // Aggiungi nuovo indicatore
        const indicator = document.createElement('span');
        indicator.className = 'sort-indicator';
        indicator.textContent = order === 'asc' ? ' ↑' : ' ↓';
        header.appendChild(indicator);
    }
    
    function initTableSelection() {
        const tables = document.querySelectorAll('[data-selectable]');
        
        tables.forEach(table => {
            // Aggiungi checkbox header
            const headerRow = table.querySelector('thead tr');
            const checkAllTh = document.createElement('th');
            checkAllTh.innerHTML = '<input type="checkbox" class="check-all">';
            headerRow.insertBefore(checkAllTh, headerRow.firstChild);
            
            // Aggiungi checkbox a ogni riga
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const checkTd = document.createElement('td');
                checkTd.innerHTML = '<input type="checkbox" class="row-check">';
                row.insertBefore(checkTd, row.firstChild);
            });
            
            // Gestisci selezione
            const checkAll = table.querySelector('.check-all');
            checkAll.addEventListener('change', function() {
                const checks = table.querySelectorAll('.row-check');
                checks.forEach(check => check.checked = this.checked);
                updateBulkActions(table);
            });
            
            table.addEventListener('change', function(e) {
                if (e.target.classList.contains('row-check')) {
                    updateBulkActions(table);
                }
            });
        });
    }
    
    function updateBulkActions(table) {
        const checkedCount = table.querySelectorAll('.row-check:checked').length;
        const bulkActions = document.querySelector('.bulk-actions');
        
        if (bulkActions) {
            bulkActions.style.display = checkedCount > 0 ? 'block' : 'none';
            const countSpan = bulkActions.querySelector('.selected-count');
            if (countSpan) {
                countSpan.textContent = checkedCount;
            }
        }
    }
    
    function initTableSearch() {
        const searchInputs = document.querySelectorAll('[data-table-search]');
        
        searchInputs.forEach(input => {
            const tableId = input.getAttribute('data-table-search');
            const table = document.getElementById(tableId);
            
            if (!table) return;
            
            input.addEventListener('input', debounce(function() {
                filterTable(table, this.value);
            }, 300));
        });
    }
    
    function filterTable(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
        
        // Mostra messaggio se nessun risultato
        const visibleRows = table.querySelectorAll('tbody tr:not([style*="display: none"])');
        showEmptyMessage(table, visibleRows.length === 0);
    }
    
    function showEmptyMessage(table, show) {
        let emptyRow = table.querySelector('.empty-message-row');
        
        if (show && !emptyRow) {
            const colspan = table.querySelectorAll('thead th').length;
            emptyRow = document.createElement('tr');
            emptyRow.className = 'empty-message-row';
            emptyRow.innerHTML = `
                <td colspan="${colspan}" class="text-center text-muted p-4">
                    Nessun risultato trovato
                </td>
            `;
            table.querySelector('tbody').appendChild(emptyRow);
        } else if (!show && emptyRow) {
            emptyRow.remove();
        }
    }
    
    /**
     * Eventi globali
     */
    function initGlobalEvents() {
        // Conferma uscita con modifiche non salvate
        let hasUnsavedChanges = false;
        
        document.addEventListener('input', function(e) {
            if (e.target.matches('input, textarea, select')) {
                hasUnsavedChanges = true;
            }
        });
        
        document.addEventListener('submit', function() {
            hasUnsavedChanges = false;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Gestione errori AJAX globale
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Errore non gestito:', e.reason);
            showNotification('Si è verificato un errore. Riprova più tardi.', 'error');
        });
    }
    
    /**
     * Utility functions
     */
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
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'error' ? '#dc2626' : '#059669'};
            color: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideInRight 0.3s;
            z-index: 9999;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    
    // API pubblica
    return {
        init: init,
        showNotification: showNotification,
        openModal: openModal,
        closeModal: closeModal,
        state: state
    };
})();

// Inizializza quando il DOM è pronto
document.addEventListener('DOMContentLoaded', CRM.App.init);

// Animazioni CSS necessarie
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);