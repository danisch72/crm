/**
 * microinteractions.js - Sistema Microinterazioni CRM Re.De Consulting
 * 
 * Features:
 * - Timer lavoro in tempo reale
 * - Animazioni smooth sidebar/menu
 * - Hover effects avanzati
 * - Responsive mobile interactions
 * - Task tracking animations
 * 
 * @author Tecnico Informatico + Grafico Esperto
 * @version 1.1 - FIXED
 */

class CRMInteractions {
    constructor() {
        this.sidebarOpen = true;
        this.workTimer = null;
        this.timerInterval = null;
        this.startTime = null;
        this.contractHours = 8; // Ore contrattuali default
        
        this.init();
    }
    
    /**
     * Inizializza tutte le microinterazioni
     */
    init() {
        this.initSidebar();
        this.initWorkTimer();
        this.initButtonAnimations();
        this.initCardAnimations();
        this.initFormInteractions();
        this.initTableInteractions();
        this.initTooltips();
        this.initMobileMenu();
        this.initScrollAnimations();
        
        // Inizializza timer se esistono dati di sessione
        this.loadWorkSession();
        
        console.log('🚀 CRM Microinteractions initialized');
    }
    
    /**
     * Gestione Sidebar Toggle
     */
    initSidebar() {
        const toggleBtn = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (!toggleBtn || !sidebar) return;
        
        toggleBtn.addEventListener('click', () => {
            this.toggleSidebar();
        });
        
        // Chiusura automatica su mobile quando si clicca su un link
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    this.closeSidebar();
                }
            });
        });
        
        // Gestione resize window
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 1024) {
                this.closeSidebar();
            } else {
                this.openSidebar();
            }
        });
    }
    
    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        this.sidebarOpen = !this.sidebarOpen;
        
        if (this.sidebarOpen) {
            this.openSidebar();
        } else {
            this.closeSidebar();
        }
    }
    
    openSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.add('open');
        }
        
        if (mainContent && window.innerWidth > 1024) {
            mainContent.classList.remove('expanded');
        }
        
        this.sidebarOpen = true;
    }
    
    closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        if (sidebar) {
            sidebar.classList.add('collapsed');
            sidebar.classList.remove('open');
        }
        
        if (mainContent) {
            mainContent.classList.add('expanded');
        }
        
        this.sidebarOpen = false;
    }
    
    /**
     * Timer Lavoro in Tempo Reale
     */
    initWorkTimer() {
        this.workTimer = document.querySelector('.work-timer-display');
        
        if (this.workTimer) {
            this.startWorkTimer();
        }
    }
    
    /**
     * Carica dati di sessione dal backend - FUNZIONE CORRETTA
     */
    loadWorkSession() {
        fetch('/crm/api/session-info.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.session) {
                this.startTime = new Date(data.session.login_timestamp * 1000);
                this.contractHours = data.session.ore_contratto || 8;
                this.updateTimerDisplay();
            }
        })
        .catch(error => {
            console.log('Session info not available:', error);
        });
        // NESSUN ALTRO .catch() QUI - ERRORE RISOLTO!
    }
    
    startWorkTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
        
        this.timerInterval = setInterval(() => {
            this.updateTimerDisplay();
        }, 1000);
    }
    
    updateTimerDisplay() {
        if (!this.workTimer || !this.startTime) return;
        
        const now = new Date();
        const elapsed = now - this.startTime;
        const hours = Math.floor(elapsed / (1000 * 60 * 60));
        const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
        
        const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Aggiorna display
        const timeDisplay = this.workTimer.querySelector('.time-display');
        if (timeDisplay) {
            timeDisplay.textContent = timeString;
        }
        
        // Cambia colore se superiore alle ore contrattuali
        const totalHours = hours + (minutes / 60);
        if (totalHours > this.contractHours) {
            this.workTimer.classList.add('overtime');
        } else {
            this.workTimer.classList.remove('overtime');
        }
        
        // Aggiorna info extra
        this.updateTimerInfo(totalHours);
    }
    
    updateTimerInfo(totalHours) {
        const extraHours = Math.max(0, totalHours - this.contractHours);
        const extraDisplay = this.workTimer.querySelector('.extra-time');
        
        if (extraDisplay && extraHours > 0) {
            const extraHoursInt = Math.floor(extraHours);
            const extraMinutes = Math.floor((extraHours - extraHoursInt) * 60);
            extraDisplay.textContent = `+${extraHoursInt}:${extraMinutes.toString().padStart(2, '0')}`;
            extraDisplay.style.display = 'inline';
        } else if (extraDisplay) {
            extraDisplay.style.display = 'none';
        }
    }
    
    /**
     * Animazioni Bottoni
     */
    initButtonAnimations() {
        const buttons = document.querySelectorAll('.btn');
        
        buttons.forEach(btn => {
            // Effetto ripple click
            btn.addEventListener('click', (e) => {
                this.createRippleEffect(e, btn);
            });
            
            // Hover animation
            btn.addEventListener('mouseenter', () => {
                if (!btn.disabled) {
                    btn.style.transform = 'translateY(-2px)';
                }
            });
            
            btn.addEventListener('mouseleave', () => {
                if (!btn.disabled) {
                    btn.style.transform = 'translateY(0)';
                }
            });
            
            // Loading state
            if (btn.classList.contains('btn-loading')) {
                this.addLoadingSpinner(btn);
            }
        });
    }
    
    createRippleEffect(event, element) {
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }
    
    addLoadingSpinner(button) {
        const spinner = document.createElement('span');
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '⟳';
        spinner.style.cssText = `
            display: inline-block;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        `;
        
        button.insertBefore(spinner, button.firstChild);
    }
    
    /**
     * Animazioni Cards
     */
    initCardAnimations() {
        const cards = document.querySelectorAll('.card, .stat-card');
        
        // Intersection Observer per animazioni on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        
        cards.forEach((card, index) => {
            // Stagger animation
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `all 0.6s ease ${index * 0.1}s`;
            
            observer.observe(card);
            
            // Hover animations
            card.addEventListener('mouseenter', () => {
                card.style.transform += ' scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = card.style.transform.replace(' scale(1.02)', '');
            });
        });
    }
    
    /**
     * Interazioni Form
     */
    initFormInteractions() {
        const inputs = document.querySelectorAll('.form-control');
        
        inputs.forEach(input => {
            // Label animation
            const formGroup = input.closest('.form-group');
            const label = formGroup?.querySelector('.form-label');
            
            input.addEventListener('focus', () => {
                if (label) {
                    label.style.color = 'var(--primary-green)';
                    label.style.transform = 'translateY(-2px)';
                }
            });
            
            input.addEventListener('blur', () => {
                if (label) {
                    label.style.color = 'var(--gray-700)';
                    label.style.transform = 'translateY(0)';
                }
            });
            
            // Validation feedback
            input.addEventListener('input', () => {
                this.validateFieldRealTime(input);
            });
        });
        
        // Form submission animations
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                this.animateFormSubmission(form);
            });
        });
    }
    
    validateFieldRealTime(input) {
        const isValid = input.checkValidity();
        
        if (input.value.length > 0) {
            if (isValid) {
                input.style.borderColor = 'var(--secondary-green)';
                this.removeFieldError(input);
            } else {
                input.style.borderColor = 'var(--danger-red)';
                this.showFieldError(input);
            }
        } else {
            input.style.borderColor = 'var(--gray-200)';
            this.removeFieldError(input);
        }
    }
    
    animateFormSubmission(form) {
        // Disabilita tutti i submit button nel form
        const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<span class="loading-spinner"></span> Invio in corso...';
            }
        });
    }
    
    showFieldError(input) {
        let errorDiv = input.parentNode.querySelector('.field-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.style.cssText = `
                color: var(--danger-red);
                font-size: var(--font-size-xs);
                margin-top: 0.25rem;
                animation: slideDown 0.3s ease;
            `;
            input.parentNode.appendChild(errorDiv);
        }
        errorDiv.textContent = input.validationMessage;
    }
    
    removeFieldError(input) {
        const errorDiv = input.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    /**
     * Interazioni Tabelle
     */
    initTableInteractions() {
        const tableRows = document.querySelectorAll('.table tbody tr');
        
        tableRows.forEach(row => {
            row.addEventListener('click', () => {
                // Rimuovi selezione precedente
                tableRows.forEach(r => r.classList.remove('selected'));
                
                // Aggiungi selezione corrente
                row.classList.add('selected');
                
                // Animazione di selezione
                row.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    row.style.transform = 'scale(1)';
                }, 150);
            });
        });
        
        // Sorting animations
        const sortableHeaders = document.querySelectorAll('.table th[data-sortable]');
        sortableHeaders.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.animateTableSort(header);
            });
        });
    }
    
    animateTableSort(header) {
        // Aggiungi indicatore di sorting
        const icon = header.querySelector('.sort-icon') || document.createElement('span');
        icon.className = 'sort-icon';
        icon.innerHTML = '↕️';
        
        if (!header.querySelector('.sort-icon')) {
            header.appendChild(icon);
        }
        
        // Anima la tabella
        const table = header.closest('.table');
        table.style.opacity = '0.7';
        
        setTimeout(() => {
            table.style.opacity = '1';
        }, 300);
    }
    
    /**
     * Tooltips
     */
    initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }
    
    showTooltip(element) {
        const tooltipText = element.getAttribute('data-tooltip');
        const tooltip = document.createElement('div');
        
        tooltip.className = 'tooltip';
        tooltip.textContent = tooltipText;
        tooltip.style.cssText = `
            position: absolute;
            background: var(--gray-800);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
            z-index: var(--z-tooltip);
            opacity: 0;
            transform: translateY(5px);
            transition: all 0.2s ease;
            pointer-events: none;
        `;
        
        document.body.appendChild(tooltip);
        
        // Posiziona tooltip
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        
        // Anima apparizione
        setTimeout(() => {
            tooltip.style.opacity = '1';
            tooltip.style.transform = 'translateY(0)';
        }, 10);
        
        this.currentTooltip = tooltip;
    }
    
    hideTooltip() {
        if (this.currentTooltip) {
            this.currentTooltip.style.opacity = '0';
            this.currentTooltip.style.transform = 'translateY(5px)';
            
            setTimeout(() => {
                if (this.currentTooltip) {
                    this.currentTooltip.remove();
                    this.currentTooltip = null;
                }
            }, 200);
        }
    }
    
    /**
     * Menu Mobile
     */
    initMobileMenu() {
        // Gestione swipe per aprire/chiudere sidebar su mobile
        let startX = null;
        let startY = null;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });
        
        document.addEventListener('touchmove', (e) => {
            if (!startX || !startY) return;
            
            const deltaX = e.touches[0].clientX - startX;
            const deltaY = e.touches[0].clientY - startY;
            
            // Solo swipe orizzontali
            if (Math.abs(deltaY) > Math.abs(deltaX)) return;
            
            // Swipe da sinistra per aprire
            if (deltaX > 50 && startX < 50 && !this.sidebarOpen) {
                this.openSidebar();
            }
            
            // Swipe verso sinistra per chiudere
            if (deltaX < -50 && this.sidebarOpen) {
                this.closeSidebar();
            }
        });
        
        document.addEventListener('touchend', () => {
            startX = null;
            startY = null;
        });
    }
    
    /**
     * Scroll Animations
     */
    initScrollAnimations() {
        // Parallax effect per header
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const header = document.querySelector('.app-header');
            
            if (header && scrolled > 10) {
                header.style.boxShadow = 'var(--shadow-lg)';
                header.style.backdropFilter = 'blur(8px)';
            } else if (header) {
                header.style.boxShadow = 'var(--shadow-sm)';
                header.style.backdropFilter = 'none';
            }
        });
        
        // Smooth scroll per link interni
        const internalLinks = document.querySelectorAll('a[href^="#"]');
        internalLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const target = document.querySelector(link.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    /**
     * Utility Functions
     */
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            color: white;
            font-weight: 500;
            z-index: var(--z-modal);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 300px;
        `;
        
        // Colori per tipo
        const colors = {
            info: 'var(--info-blue)',
            success: 'var(--secondary-green)',
            warning: 'var(--accent-orange)',
            error: 'var(--danger-red)'
        };
        
        notification.style.background = colors[type] || colors.info;
        
        document.body.appendChild(notification);
        
        // Anima entrata
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-rimozione
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, duration);
        
        return notification;
    }
    
    showModal(title, content, options = {}) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: var(--z-modal);
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        modalContent.style.cssText = `
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        `;
        
        modalContent.innerHTML = `
            <h3 style="margin-bottom: 1rem; color: var(--gray-800);">${title}</h3>
            <div>${content}</div>
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        // Anima apparizione
        setTimeout(() => {
            modal.style.opacity = '1';
            modalContent.style.transform = 'scale(1)';
        }, 10);
        
        // Chiusura su click overlay
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal(modal);
            }
        });
        
        return modal;
    }
    
    closeModal(modal) {
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
    
    /**
     * Task Tracking Functions
     */
    startTaskTimer(taskId) {
        const timerElement = document.querySelector(`[data-task-id="${taskId}"] .task-timer`);
        if (!timerElement) return;
        
        // Aggiungi classe di tracking attivo
        timerElement.classList.add('active');
        timerElement.innerHTML = '<span class="timer-pulse">●</span> 00:00:00';
        
        // Avvia timer
        let startTime = Date.now();
        let interval = setInterval(() => {
            const elapsed = Date.now() - startTime;
            const time = this.formatDuration(elapsed);
            timerElement.innerHTML = `<span class="timer-pulse">●</span> ${time}`;
        }, 1000);
        
        // Salva riferimento interval
        timerElement.setAttribute('data-interval', interval);
        
        this.showNotification('Timer task avviato', 'success');
    }
    
    stopTaskTimer(taskId) {
        const timerElement = document.querySelector(`[data-task-id="${taskId}"] .task-timer`);
        if (!timerElement) return;
        
        // Ferma timer
        const interval = timerElement.getAttribute('data-interval');
        if (interval) {
            clearInterval(interval);
        }
        
        timerElement.classList.remove('active');
        this.showNotification('Timer task fermato', 'info');
    }
    
    formatDuration(milliseconds) {
        const seconds = Math.floor(milliseconds / 1000);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    
    /**
     * Cleanup
     */
    destroy() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
        
        // Rimuovi event listeners globali se esistono
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize);
        }
        if (this.handleScroll) {
            window.removeEventListener('scroll', this.handleScroll);
        }
        
        console.log('🔄 CRM Interactions destroyed');
    }
}

// CSS Aggiuntivo per le animazioni
const additionalCSS = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    .timer-pulse {
        color: var(--danger-red);
        animation: pulse 1.5s infinite;
    }
    
    .work-timer.overtime {
        background: rgba(230, 0, 18, 0.1);
        border-color: var(--danger-red);
        color: var(--danger-red);
    }
    
    .table tbody tr.selected {
        background: var(--green-100);
        border-left: 4px solid var(--primary-green);
    }
    
    .task-timer.active {
        background: var(--green-50);
        color: var(--primary-green);
        border: 1px solid var(--green-200);
        border-radius: var(--radius-md);
        padding: 0.25rem 0.5rem;
        font-weight: 500;
    }
    
    .loading-spinner {
        display: inline-block;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }
`;

// Inietta CSS aggiuntivo
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalCSS;
document.head.appendChild(styleSheet);

// Inizializza sistema quando DOM è pronto
let crmInteractions;

document.addEventListener('DOMContentLoaded', () => {
    crmInteractions = new CRMInteractions();
});

// Cleanup su page unload
window.addEventListener('beforeunload', () => {
    if (crmInteractions) {
        crmInteractions.destroy();
    }
});

// Export per uso esterno
window.CRMInteractions = CRMInteractions;