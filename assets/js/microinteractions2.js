/* =============================================
   MICROINTERACTIONS.JS - VERSIONE UNIFICATA v2.0
   Funzionalità unificate per CRM DATEV-Style
   ============================================= */

class CRMInterface {
    constructor() {
        this.sidebar = null;
        this.sidebarToggle = null;
        this.timer = null;
        this.sessionStart = Date.now();
        this.isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        this.init();
    }

    init() {
        this.setupDOMElements();
        this.setupSidebar();
        this.setupTimer();
        this.setupEventListeners();
        this.setupTableInteractions();
        this.setupFormValidation();
        this.setupNotifications();
        
        // Inizializza animazioni di entrata
        this.animatePageLoad();
    }

    setupDOMElements() {
        this.sidebar = document.querySelector('.sidebar');
        this.sidebarToggle = document.querySelector('.sidebar-toggle');
        this.timer = document.querySelector('.timer-text');
        this.contentWrapper = document.querySelector('.content-wrapper');
    }

    setupSidebar() {
        if (!this.sidebar || !this.sidebarToggle) return;

        // Applica stato iniziale della sidebar
        if (this.isCollapsed) {
            this.sidebar.classList.add('collapsed');
        }

        // Event listener per il toggle
        this.sidebarToggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleSidebar();
        });

        // Gestione hover per sidebar collassata
        if (this.isCollapsed) {
            this.setupSidebarHover();
        }

        // Evidenzia pagina corrente
        this.highlightCurrentPage();
    }

    toggleSidebar() {
        this.isCollapsed = !this.isCollapsed;
        this.sidebar.classList.toggle('collapsed', this.isCollapsed);
        
        // Salva stato nel localStorage
        localStorage.setItem('sidebarCollapsed', this.isCollapsed);

        // Aggiorna hover behavior
        if (this.isCollapsed) {
            this.setupSidebarHover();
        } else {
            this.removeSidebarHover();
        }

        // Trigger per animazioni responsive
        this.triggerLayoutUpdate();
    }

    setupSidebarHover() {
        if (!this.sidebar) return;

        this.sidebar.addEventListener('mouseenter', () => {
            if (this.isCollapsed) {
                this.sidebar.style.width = 'var(--sidebar-width)';
                this.sidebar.style.zIndex = '1001';
            }
        });

        this.sidebar.addEventListener('mouseleave', () => {
            if (this.isCollapsed) {
                this.sidebar.style.width = 'var(--sidebar-collapsed-width)';
                this.sidebar.style.zIndex = '1000';
            }
        });
    }

    removeSidebarHover() {
        if (!this.sidebar) return;
        
        this.sidebar.removeEventListener('mouseenter', this.setupSidebarHover);
        this.sidebar.removeEventListener('mouseleave', this.setupSidebarHover);
        this.sidebar.style.width = '';
        this.sidebar.style.zIndex = '';
    }

    highlightCurrentPage() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            
            // Controlla se l'href del link corrisponde al path corrente
            const href = link.getAttribute('href');
            if (href && currentPath.includes(href)) {
                link.classList.add('active');
            }
        });
    }

    setupTimer() {
        if (!this.timer) return;

        this.updateTimer();
        
        // Aggiorna timer ogni secondo
        setInterval(() => {
            this.updateTimer();
        }, 1000);

        // Salva ora di inizio sessione
        if (!sessionStorage.getItem('sessionStart')) {
            sessionStorage.setItem('sessionStart', this.sessionStart.toString());
        } else {
            this.sessionStart = parseInt(sessionStorage.getItem('sessionStart'));
        }
    }

    updateTimer() {
        if (!this.timer) return;

        const now = Date.now();
        const elapsed = Math.floor((now - this.sessionStart) / 1000);
        
        const hours = Math.floor(elapsed / 3600);
        const minutes = Math.floor((elapsed % 3600) / 60);
        const seconds = elapsed % 60;

        const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        this.timer.textContent = timeString;

        // Cambia colore se la sessione è troppo lunga (>8 ore)
        const timerContainer = this.timer.closest('.session-timer');
        if (timerContainer) {
            if (hours >= 8) {
                timerContainer.style.background = '#fff3cd';
                timerContainer.style.borderColor = '#ffc107';
                timerContainer.style.color = '#856404';
            }
        }
    }

    setupEventListeners() {
        // Gestione window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });

        // Gestione escape key per chiudere modali
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Gestione form submissions
        document.addEventListener('submit', (e) => {
            this.handleFormSubmit(e);
        });

        // Gestione click su bottoni con loading
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn[data-loading]')) {
                this.handleLoadingButton(e.target);
            }
        });
    }

    setupTableInteractions() {
        const tables = document.querySelectorAll('.table');
        
        tables.forEach(table => {
            // Gestione hover delle righe
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', () => {
                    this.highlightTableRow(row);
                });
                
                row.addEventListener('mouseleave', () => {
                    this.unhighlightTableRow(row);
                });
            });

            // Gestione ordinamento colonne (se presenti header clickabili)
            const headers = table.querySelectorAll('th[data-sortable]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    this.sortTable(table, header);
                });
            });
        });

        // Setup ricerca tabelle
        this.setupTableSearch();
    }

    highlightTableRow(row) {
        row.style.backgroundColor = 'var(--datev-green-light)';
        row.style.transform = 'translateX(4px)';
    }

    unhighlightTableRow(row) {
        row.style.backgroundColor = '';
        row.style.transform = '';
    }

    sortTable(table, header) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(header.parentElement.children).indexOf(header);
        const isAscending = header.classList.contains('sort-asc');

        rows.sort((a, b) => {
            const aValue = a.children[columnIndex].textContent.trim();
            const bValue = b.children[columnIndex].textContent.trim();
            
            // Prova a convertire in numeri se possibile
            const aNum = parseFloat(aValue);
            const bNum = parseFloat(bValue);
            
            let comparison = 0;
            if (!isNaN(aNum) && !isNaN(bNum)) {
                comparison = aNum - bNum;
            } else {
                comparison = aValue.localeCompare(bValue);
            }
            
            return isAscending ? -comparison : comparison;
        });

        // Rimuovi classi di ordinamento da tutti gli header
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });

        // Aggiungi classe appropriata
        header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');

        // Riordina righe
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));

        // Animazione
        rows.forEach((row, index) => {
            setTimeout(() => {
                row.style.animation = 'fadeIn 0.3s ease';
            }, index * 50);
        });
    }

    setupTableSearch() {
        const searchInputs = document.querySelectorAll('[data-table-search]');
        
        searchInputs.forEach(input => {
            const targetTable = document.querySelector(input.dataset.tableSearch);
            if (!targetTable) return;

            input.addEventListener('input', (e) => {
                this.filterTable(targetTable, e.target.value);
            });
        });
    }

    filterTable(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matches = text.includes(term);
            
            row.style.display = matches ? '' : 'none';
            
            if (matches && term) {
                row.style.animation = 'slideInRight 0.3s ease';
            }
        });
    }

    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            const inputs = form.querySelectorAll('.form-control');
            
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateInput(input);
                });
                
                input.addEventListener('input', () => {
                    this.clearValidationError(input);
                });
            });
        });
    }

    validateInput(input) {
        const value = input.value.trim();
        const required = input.hasAttribute('required');
        const type = input.getAttribute('type');
        const pattern = input.getAttribute('pattern');
        
        let isValid = true;
        let errorMessage = '';

        // Validazione required
        if (required && !value) {
            isValid = false;
            errorMessage = 'Questo campo è obbligatorio';
        }

        // Validazione email
        if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Inserisci un email valida';
            }
        }

        // Validazione pattern personalizzato
        if (pattern && value) {
            const regex = new RegExp(pattern);
            if (!regex.test(value)) {
                isValid = false;
                errorMessage = 'Formato non valido';
            }
        }

        this.showValidationResult(input, isValid, errorMessage);
        return isValid;
    }

    showValidationResult(input, isValid, errorMessage) {
        this.clearValidationError(input);
        
        if (!isValid) {
            input.style.borderColor = 'var(--datev-danger)';
            input.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.style.color = 'var(--datev-danger)';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '4px';
            errorDiv.textContent = errorMessage;
            
            input.parentElement.appendChild(errorDiv);
        } else if (input.value.trim()) {
            input.style.borderColor = 'var(--datev-success)';
            input.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
        }
    }

    clearValidationError(input) {
        input.style.borderColor = '';
        input.style.boxShadow = '';
        
        const errorDiv = input.parentElement.querySelector('.form-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    setupNotifications() {
        // Setup container per notifiche se non esiste
        if (!document.querySelector('.notifications-container')) {
            const container = document.createElement('div');
            container.className = 'notifications-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        const container = document.querySelector('.notifications-container');
        if (!container) return;

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${this.getNotificationIcon(type)}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;

        // Stili inline per la notifica
        notification.style.cssText = `
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 12px;
            overflow: hidden;
            transform: translateX(100%);
            transition: all 0.3s ease;
            border-left: 4px solid var(--datev-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'});
        `;

        const content = notification.querySelector('.notification-content');
        content.style.cssText = `
            display: flex;
            align-items: center;
            padding: 16px;
            gap: 12px;
        `;

        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            margin-left: auto;
            color: #6c757d;
        `;

        container.appendChild(notification);

        // Animazione di entrata
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Auto-remove
        const autoRemove = setTimeout(() => {
            this.removeNotification(notification);
        }, duration);

        // Click per chiudere
        closeBtn.addEventListener('click', () => {
            clearTimeout(autoRemove);
            this.removeNotification(notification);
        });
    }

    removeNotification(notification) {
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.parentElement.removeChild(notification);
            }
        }, 300);
    }

    getNotificationIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    handleFormSubmit(e) {
        const form = e.target;
        if (!form.hasAttribute('data-validate')) return;

        const inputs = form.querySelectorAll('.form-control[required]');
        let isFormValid = true;

        inputs.forEach(input => {
            if (!this.validateInput(input)) {
                isFormValid = false;
            }
        });

        if (!isFormValid) {
            e.preventDefault();
            this.showNotification('Correggi gli errori nel form prima di continuare', 'error');
            return false;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            this.handleLoadingButton(submitBtn);
        }
    }

    handleLoadingButton(button) {
        const originalText = button.textContent;
        const loadingText = button.dataset.loadingText || 'Caricamento...';
        
        button.textContent = loadingText;
        button.disabled = true;
        button.classList.add('loading');

        // Simula caricamento (in un'app reale, questo sarebbe gestito dal server)
        setTimeout(() => {
            button.textContent = originalText;
            button.disabled = false;
            button.classList.remove('loading');
        }, 2000);
    }

    handleResize() {
        const width = window.innerWidth;
        
        // Auto-collapse sidebar su mobile
        if (width <= 768 && !this.isCollapsed) {
            this.toggleSidebar();
        }
    }

    closeAllModals() {
        const modals = document.querySelectorAll('.modal, .popup, .overlay');
        modals.forEach(modal => {
            if (modal.style.display !== 'none') {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
        });
    }

    triggerLayoutUpdate() {
        // Trigger per eventuali componenti che devono reagire al cambio layout
        window.dispatchEvent(new Event('layoutUpdate'));
    }

    animatePageLoad() {
        // Anima elementi in entrata
        const animateElements = document.querySelectorAll('.stat-card, .table-container, .filters-container');
        
        animateElements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                element.style.transition = 'all 0.4s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    // Metodi utility pubblici
    static showSuccess(message) {
        if (window.crmInterface) {
            window.crmInterface.showNotification(message, 'success');
        }
    }

    static showError(message) {
        if (window.crmInterface) {
            window.crmInterface.showNotification(message, 'error');
        }
    }

    static showWarning(message) {
        if (window.crmInterface) {
            window.crmInterface.showNotification(message, 'warning');
        }
    }

    static showInfo(message) {
        if (window.crmInterface) {
            window.crmInterface.showNotification(message, 'info');
        }
    }
}

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    window.crmInterface = new CRMInterface();
    
    // Esponi metodi globali per facilità d'uso
    window.showSuccess = CRMInterface.showSuccess;
    window.showError = CRMInterface.showError;
    window.showWarning = CRMInterface.showWarning;
    window.showInfo = CRMInterface.showInfo;
});

// Export per moduli ES6 se necessario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CRMInterface;
}