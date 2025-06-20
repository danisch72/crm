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