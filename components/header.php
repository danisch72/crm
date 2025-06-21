<?php
/**
 * components/header.php - Header Orizzontale CRM Re.De Consulting
 * 
 * ✅ HEADER SENZA TOGGLE SIDEBAR (RIMOSSA)
 * ✅ MOBILE MENU BUTTON SOLO SU MOBILE
 * ✅ DESIGN PROFESSIONALE DATEV KOINOS
 * 
 * Features:
 * - Timer sessione lavoro
 * - Info utente e avatar
 * - Menu dropdown utente
 * - Mobile menu solo su mobile
 */

// Verifica variabili necessarie
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

if (!isset($pageIcon)) {
    $pageIcon = '🏠';
}

// Verifica sessione
if (!isset($sessionInfo) || empty($sessionInfo)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $sessionInfo = [
        'nome_completo' => $_SESSION['nome'] ?? 'Utente',
        'nome' => $_SESSION['nome'] ?? '',
        'cognome' => $_SESSION['cognome'] ?? '',
        'is_admin' => $_SESSION['is_admin'] ?? false,
        'operatore_id' => $_SESSION['user_id'] ?? 0
    ];
}

// Calcola tempo sessione
if (!isset($_SESSION['session_start'])) {
    $_SESSION['session_start'] = time();
}
$sessionStart = $_SESSION['session_start'];
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

<!-- Header Styles -->
<style>
/* Header principale */
.main-header {
    background: white;
    border-bottom: 1px solid var(--gray-200);
    padding: 0.75rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-left: auto;
}

/* Mobile menu button */
.mobile-menu-btn {
    display: none;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
    cursor: pointer;
    border-radius: var(--border-radius-md);
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.mobile-menu-btn:hover {
    background: var(--gray-100);
    border-color: var(--gray-400);
}

/* Page Title */
.page-title-section {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
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
    color: var(--gray-500);
    margin: 0;
}

/* Timer */
.work-timer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    background: var(--gray-50);
    border-radius: var(--border-radius-md);
    font-size: 0.875rem;
    border: 1px solid var(--gray-200);
}

.timer-icon {
    color: var(--primary-green);
}

.timer-text {
    font-weight: 600;
    font-family: var(--font-mono);
    color: var(--primary-green);
}

.timer-label {
    color: var(--gray-500);
}

/* User Menu */
.user-menu {
    position: relative;
}

.user-menu-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: var(--border-radius-md);
    transition: all 0.2s;
}

.user-menu-btn:hover {
    background: var(--gray-100);
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: var(--primary-green);
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
    color: var(--gray-700);
    font-weight: 500;
}

.chevron-icon {
    color: var(--gray-500);
    transition: transform 0.2s;
}

.user-menu-btn[aria-expanded="true"] .chevron-icon {
    transform: rotate(180deg);
}

/* Dropdown */
.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    width: 240px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s;
    z-index: 1000;
}

.user-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar-large {
    width: 40px;
    height: 40px;
    background: var(--primary-green);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.user-info {
    flex: 1;
}

.user-fullname {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.875rem;
}

.user-role {
    font-size: 0.75rem;
    color: var(--gray-500);
}

.dropdown-menu {
    padding: 0.5rem;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    color: var(--gray-700);
    text-decoration: none;
    border-radius: var(--border-radius-md);
    font-size: 0.875rem;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background: var(--gray-100);
    color: var(--gray-900);
}

.dropdown-icon {
    font-size: 1rem;
}

.dropdown-divider {
    height: 1px;
    background: var(--gray-200);
    margin: 0.5rem 0;
}

/* Responsive */
@media (max-width: 768px) {
    .mobile-menu-btn {
        display: flex;
    }
    
    .header-left {
        gap: 0.5rem;
    }
    
    .header-right {
        gap: 0.75rem;
    }
    
    .user-name,
    .timer-label {
        display: none;
    }
    
    .page-title {
        font-size: 1.125rem;
    }
}
</style>

<!-- Header Orizzontale -->
<header class="main-header">
    <div class="header-left">
        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" 
                id="mobileMenuBtn" 
                aria-label="Menu mobile">
            <span style="font-size: 18px;">☰</span>
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
        
        <!-- User Menu -->
        <div class="user-menu" id="userMenu">
            <button class="user-menu-btn" id="userMenuBtn" aria-expanded="false">
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
                
                <div class="dropdown-menu">
                    <a href="/crm/?action=profilo" class="dropdown-item">
                        <span class="dropdown-icon">👤</span>
                        Il mio profilo
                    </a>
                    <?php if ($sessionInfo['is_admin']): ?>
                    <a href="/crm/?action=impostazioni" class="dropdown-item">
                        <span class="dropdown-icon">⚙️</span>
                        Impostazioni
                    </a>
                    <?php endif; ?>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="/crm/auth/logout.php" class="dropdown-item">
                        <span class="dropdown-icon">🚪</span>
                        Esci
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- JavaScript per Header -->
<script>
// Timer aggiornamento
function updateTimer() {
    const timerEl = document.getElementById('workTimer');
    if (!timerEl) return;
    
    const startTime = <?= $sessionStart * 1000 ?>;
    const now = Date.now();
    const elapsed = Math.floor((now - startTime) / 1000);
    
    const hours = Math.floor(elapsed / 3600);
    const minutes = Math.floor((elapsed % 3600) / 60);
    const seconds = elapsed % 60;
    
    timerEl.textContent = 
        String(hours).padStart(2, '0') + ':' +
        String(minutes).padStart(2, '0') + ':' +
        String(seconds).padStart(2, '0');
}

// Aggiorna timer ogni secondo
setInterval(updateTimer, 1000);

// Gestione User Menu Dropdown
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.getElementById('userMenuBtn');
    const dropdown = document.getElementById('userDropdown');
    
    if (menuBtn && dropdown) {
        // Toggle menu
        menuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('show');
            
            if (isOpen) {
                dropdown.classList.remove('show');
                menuBtn.setAttribute('aria-expanded', 'false');
            } else {
                dropdown.classList.add('show');
                menuBtn.setAttribute('aria-expanded', 'true');
            }
        });
        
        // Chiudi cliccando fuori
        document.addEventListener('click', function(e) {
            if (!menuBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                menuBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    
    // Mobile: hamburger menu  
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            if (typeof window.toggleMobileSidebar === 'function') {
                window.toggleMobileSidebar();
            }
        });
    }
});
</script>