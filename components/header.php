<?php
/**
 * components/header.php - Header Orizzontale CRM Re.De Consulting
 * 
 * ✅ HEADER PROFESSIONALE CON TIMER E USER INFO
 * 
 * Features:
 * - Titolo pagina dinamico
 * - Timer sessione lavoro
 * - Info utente e avatar
 * - Toggle sidebar mobile
 * - Design minimalista professionale
 */

// Verifica variabili necessarie
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

if (!isset($pageIcon)) {
    $pageIcon = '🏠';
}

// Calcola tempo sessione
$sessionStart = $_SESSION['session_start'] ?? time();
$sessionDuration = time() - $sessionStart;
$hours = floor($sessionDuration / 3600);
$minutes = floor(($sessionDuration % 3600) / 60);
$seconds = $sessionDuration % 60;
$timerDisplay = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

// Info utente
$userInitials = '';
if (isset($sessionInfo['nome']) && isset($sessionInfo['cognome'])) {
    $userInitials = strtoupper(substr($sessionInfo['nome'], 0, 1) . substr($sessionInfo['cognome'], 0, 1));
} else {
    $nameParts = explode(' ', $sessionInfo['nome_completo'] ?? 'User');
    $userInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
}
?>

<!-- Header Orizzontale Professionale -->
<header class="main-header">
    <div class="header-left">
        <!-- Toggle Sidebar Mobile -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
        
        <!-- Titolo Pagina -->
        <div class="page-title-section">
            <h1 class="page-title">
                <span class="page-icon"><?= $pageIcon ?></span>
                <?= htmlspecialchars($pageTitle) ?>
            </h1>
            <?php if (isset($pageSubtitle)): ?>
                <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="header-right">
        <!-- Timer Lavoro -->
        <div class="work-timer">
            <svg class="timer-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
            </svg>
            <span class="timer-text" id="workTimer"><?= $timerDisplay ?></span>
            <span class="timer-label">/ 8h</span>
        </div>
        
        <!-- Notifiche (placeholder) -->
        <button class="header-btn notification-btn" aria-label="Notifiche">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" fill="currentColor"/>
            </svg>
            <span class="notification-dot"></span>
        </button>
        
        <!-- User Menu -->
        <div class="user-menu" id="userMenu">
            <button class="user-menu-btn" aria-label="Menu utente">
                <div class="user-avatar">
                    <?= $userInitials ?>
                </div>
                <span class="user-name"><?= htmlspecialchars($sessionInfo['nome_completo'] ?? 'Utente') ?></span>
                <svg class="chevron-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M4.646 6.646a.5.5 0 01.708 0L8 9.293l2.646-2.647a.5.5 0 01.708.708l-3 3a.5.5 0 01-.708 0l-3-3a.5.5 0 010-.708z"/>
                </svg>
            </button>
            
            <!-- Dropdown Menu -->
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="user-avatar-large"><?= $userInitials ?></div>
                    <div class="user-info">
                        <div class="user-fullname"><?= htmlspecialchars($sessionInfo['nome_completo'] ?? 'Utente') ?></div>
                        <div class="user-role"><?= $sessionInfo['is_admin'] ? 'Amministratore' : 'Operatore' ?></div>
                    </div>
                </div>
                
                <div class="dropdown-divider"></div>
                
                <a href="/crm/?action=profile" class="dropdown-item">
                    <span class="dropdown-icon">👤</span>
                    Il mio profilo
                </a>
                
                <a href="/crm/?action=settings" class="dropdown-item">
                    <span class="dropdown-icon">⚙️</span>
                    Impostazioni
                </a>
                
                <div class="dropdown-divider"></div>
                
                <a href="/crm/logout.php" class="dropdown-item dropdown-logout">
                    <span class="dropdown-icon">🚪</span>
                    Esci
                </a>
            </div>
        </div>
    </div>
</header>

<style>
/* =============================================
   HEADER PROFESSIONALE
   ============================================= */

.main-header {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 64px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.5rem;
    z-index: 999;
    transition: left 0.3s ease;
}

/* Left Section */
.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.sidebar-toggle {
    display: none;
    padding: 0.5rem;
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
}

.sidebar-toggle:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.page-title-section {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-icon {
    font-size: 1.5rem;
}

.page-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0;
}

/* Right Section */
.header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Work Timer */
.work-timer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f3f4f6;
    border-radius: 8px;
    font-size: 0.875rem;
}

.timer-icon {
    color: #6b7280;
}

.timer-text {
    font-weight: 600;
    color: #1f2937;
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
}

.timer-label {
    color: #6b7280;
}

/* Header Buttons */
.header-btn {
    position: relative;
    padding: 0.5rem;
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
}

.header-btn:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.notification-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    border: 2px solid #ffffff;
}

/* User Menu */
.user-menu {
    position: relative;
}

.user-menu-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: none;
    border: none;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
}

.user-menu-btn:hover {
    background: #f3f4f6;
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: #007849;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #1f2937;
}

.chevron-icon {
    color: #6b7280;
    transition: transform 0.2s;
}

.user-menu-btn:hover .chevron-icon {
    transform: translateY(1px);
}

/* User Dropdown */
.user-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    width: 280px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s;
}

.user-menu.active .user-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar-large {
    width: 48px;
    height: 48px;
    background: #007849;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    font-weight: 600;
}

.user-fullname {
    font-weight: 600;
    color: #1f2937;
}

.user-role {
    font-size: 0.875rem;
    color: #6b7280;
}

.dropdown-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 0.5rem 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #4b5563;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.dropdown-logout {
    color: #ef4444;
}

.dropdown-logout:hover {
    background: #fee2e2;
}

/* Responsive */
@media (max-width: 768px) {
    .main-header {
        left: 0;
    }
    
    .sidebar-toggle {
        display: block;
    }
    
    .work-timer {
        display: none;
    }
    
    .user-name {
        display: none;
    }
}

/* Layout compensation */
.content-wrapper {
    padding-top: 64px;
    margin-left: 260px;
    min-height: 100vh;
    background: #f9fafb;
    transition: margin-left 0.3s ease;
}

@media (max-width: 768px) {
    .content-wrapper {
        margin-left: 0;
    }
}
</style>

<script>
// Timer aggiornamento real-time
(function() {
    const timerElement = document.getElementById('workTimer');
    if (!timerElement) return;
    
    let seconds = <?= $sessionDuration ?>;
    
    setInterval(() => {
        seconds++;
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        
        timerElement.textContent = 
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');
    }, 1000);
})();

// User dropdown toggle
document.addEventListener('DOMContentLoaded', function() {
    const userMenuBtn = document.querySelector('.user-menu-btn');
    const userMenu = document.getElementById('userMenu');
    
    if (userMenuBtn) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
        });
    }
    
    // Chiudi dropdown cliccando fuori
    document.addEventListener('click', function() {
        userMenu?.classList.remove('active');
    });
    
    // Toggle sidebar mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('mainSidebar');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar?.classList.toggle('open');
    });
});
</script>