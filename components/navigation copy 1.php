<?php
/**
 * components/navigation.php - Navigazione Centralizzata CRM Re.De Consulting
 * 
 * Componente unificato per sidebar e header del CRM
 * Include:
 * - Logo RE.DE integrato
 * - Menu navigazione dinamico
 * - Evidenziazione pagina attiva
 * - Header con timer e user menu
 * - Design Datev Koinos compliant
 * 
 * @version 2.0
 * @author Tecnico Informatico + Grafico Esperto RE.DE
 */

// Verifica autenticazione
if (!isset($sessionInfo) || empty($sessionInfo)) {
    // Tenta di caricare info sessione se non disponibili
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Se ancora non disponibili, redirect al login
    if (!isset($_SESSION['user_id'])) {
        header('Location: /crm/login.php');
        exit;
    }
    
    // Costruisci sessionInfo minimo
    $sessionInfo = [
        'nome_completo' => $_SESSION['nome'] . ' ' . $_SESSION['cognome'],
        'is_admin' => $_SESSION['is_admin'] ?? false
    ];
}

// Determina pagina attiva basandosi su URL
$currentAction = $_GET['action'] ?? 'dashboard';
$currentView = $_GET['view'] ?? '';

// Funzione helper per determinare se un link è attivo
function isNavActive($action, $view = '') {
    global $currentAction, $currentView;
    
    if ($view) {
        return $currentAction === $action && $currentView === $view;
    }
    return $currentAction === $action;
}

// Configurazione moduli disponibili
// Facilmente estendibile aggiungendo nuovi elementi all'array
$navModules = [
    [
        'action' => 'dashboard',
        'icon' => '🏠',
        'label' => 'Dashboard',
        'href' => '/crm/dashboard.php'
    ],
    [
        'action' => 'operatori',
        'icon' => '👥',
        'label' => 'Operatori',
        'href' => '/crm/modules/operatori/index.php'
    ],
    [
        'action' => 'clienti',
        'icon' => '🏢',
        'label' => 'Clienti',
        'href' => '/crm/modules/clienti/index.php'
    ],
    [
        'action' => 'pratiche',
        'icon' => '📋',
        'label' => 'Pratiche',
        'href' => '/crm/modules/pratiche/index.php'
    ],
    [
        'action' => 'scadenze',
        'icon' => '⏰',
        'label' => 'Scadenze',
        'href' => '/crm/modules/scadenze/index.php'
    ]
    // Nuovi moduli possono essere aggiunti qui in futuro
];
?>

<!-- Sidebar Unificata CRM Re.De -->
<div class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <!-- Logo RE.DE al posto del testo CRM -->
        <div class="sidebar-logo">
            <img src="/crm/assets/images/logo-rede-white.png" 
                 alt="RE.DE Consulting" 
                 class="logo-rede"
                 style="height: 40px; width: auto;">
        </div>
        
        <!-- Toggle Button per mobile/collapsed -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon">☰</span>
        </button>
    </div>
    
    <nav class="nav">
        <div class="nav-section">
            <?php foreach ($navModules as $module): ?>
                <div class="nav-item">
                    <a href="<?= $module['href'] ?>" 
                       class="nav-link <?= isNavActive($module['action']) ? 'nav-link-active' : '' ?>">
                        <span class="nav-icon"><?= $module['icon'] ?></span>
                        <span class="nav-text"><?= $module['label'] ?></span>
                        
                        <?php 
                        // Badge notifiche per moduli specifici (esempio)
                        if ($module['action'] === 'scadenze'): 
                            // Query per contare scadenze imminenti
                            $scadenzeCount = 0; // TODO: implementare query reale
                            if ($scadenzeCount > 0):
                        ?>
                            <span class="nav-badge"><?= $scadenzeCount ?></span>
                        <?php 
                            endif;
                        endif; 
                        ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($sessionInfo['is_admin'] ?? false): ?>
        <!-- Sezione Admin (solo per amministratori) -->
        <div class="nav-section nav-section-admin">
            <div class="nav-section-title">Amministrazione</div>
            <div class="nav-item">
                <a href="/crm/?action=settings" 
                   class="nav-link <?= isNavActive('settings') ? 'nav-link-active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Impostazioni</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="/crm/?action=reports" 
                   class="nav-link <?= isNavActive('reports') ? 'nav-link-active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Report</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer Sidebar con info utente -->
        <div class="sidebar-footer">
            <div class="user-info-compact">
                <div class="user-avatar-small">
                    <?= substr($sessionInfo['nome_completo'], 0, 1) ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($sessionInfo['nome_completo']) ?></div>
                    <div class="user-role"><?= $sessionInfo['is_admin'] ? 'Amministratore' : 'Operatore' ?></div>
                </div>
            </div>
            
            <!-- Logout -->
            <a href="/crm/logout.php" class="nav-link nav-link-logout" title="Esci">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Esci</span>
            </a>
        </div>
    </nav>
</div>

<!-- Header Principale -->
<header class="main-header" id="mainHeader">
    <div class="header-left">
        <!-- Titolo pagina dinamico -->
        <h1 class="page-title">
            <?php
            // Determina titolo basandosi su action/view
            $pageTitle = 'Dashboard';
            foreach ($navModules as $module) {
                if ($module['action'] === $currentAction) {
                    $pageTitle = $module['label'];
                    break;
                }
            }
            
            // Aggiungi sottotitolo per view specifiche
            if ($currentView) {
                $viewTitles = [
                    'create' => 'Nuovo',
                    'edit' => 'Modifica',
                    'view' => 'Dettaglio',
                    'stats' => 'Statistiche',
                    'export' => 'Esporta',
                    'import' => 'Importa'
                ];
                
                if (isset($viewTitles[$currentView])) {
                    $pageTitle .= ' - ' . $viewTitles[$currentView];
                }
            }
            
            echo htmlspecialchars($pageTitle);
            ?>
        </h1>
    </div>
    
    <div class="header-right">
        <!-- Timer Lavoro -->
        <div class="work-timer work-timer-display" id="workTimer">
            <span class="timer-icon">⏱️</span>
            <span class="timer-text" id="timerText">00:00:00</span>
            <span class="timer-info">/ 8h</span>
        </div>
        
        <!-- Notifiche (futuro) -->
        <div class="notifications-wrapper">
            <button class="notification-bell" id="notificationBell">
                <span class="bell-icon">🔔</span>
                <span class="notification-count" style="display: none;">0</span>
            </button>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu" id="userMenu">
            <div class="user-avatar" data-tooltip="<?= htmlspecialchars($sessionInfo['nome_completo']) ?>">
                <?= strtoupper(substr($sessionInfo['nome_completo'], 0, 1)) ?>
            </div>
            
            <!-- Dropdown Menu (nascosto di default) -->
            <div class="user-dropdown" id="userDropdown" style="display: none;">
                <div class="dropdown-header">
                    <div class="dropdown-user-name"><?= htmlspecialchars($sessionInfo['nome_completo']) ?></div>
                    <div class="dropdown-user-email"><?= htmlspecialchars($sessionInfo['email'] ?? '') ?></div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="/crm/?action=profile" class="dropdown-item">
                    <span class="dropdown-icon">👤</span>
                    <span>Il mio profilo</span>
                </a>
                <a href="/crm/?action=settings&section=account" class="dropdown-item">
                    <span class="dropdown-icon">⚙️</span>
                    <span>Impostazioni account</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="/crm/logout.php" class="dropdown-item dropdown-item-danger">
                    <span class="dropdown-icon">🚪</span>
                    <span>Esci</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Styles specifici per navigation component -->
<style>
/* Logo RE.DE styling */
.logo-rede {
    filter: brightness(0) invert(1); /* Rende il logo bianco su sfondo verde */
    transition: all 0.3s ease;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
}

.sidebar.collapsed .logo-rede {
    height: 24px !important;
}

/* Sidebar Footer Styles */
.sidebar-footer {
    margin-top: auto;
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info-compact {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.user-avatar-small {
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
    color: white;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
}

.sidebar.collapsed .user-info-compact,
.sidebar.collapsed .nav-section-title {
    display: none;
}

.nav-link-logout {
    color: rgba(255, 255, 255, 0.7) !important;
}

.nav-link-logout:hover {
    background: rgba(255, 67, 54, 0.2) !important;
    color: #ff4336 !important;
}

/* Admin section styles */
.nav-section-admin {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.nav-section-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
    padding: 0 1rem;
    margin-bottom: 0.5rem;
}

/* User Dropdown Styles */
.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    min-width: 220px;
    z-index: 1000;
}

.dropdown-header {
    padding: 1rem;
    border-bottom: 1px solid #e5e5e5;
}

.dropdown-user-name {
    font-weight: 600;
    color: #333;
}

.dropdown-user-email {
    font-size: 0.875rem;
    color: #666;
    margin-top: 0.25rem;
}

.dropdown-divider {
    height: 1px;
    background: #e5e5e5;
    margin: 0.5rem 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #333;
    text-decoration: none;
    transition: background 0.2s ease;
}

.dropdown-item:hover {
    background: #f5f5f5;
}

.dropdown-item-danger {
    color: #dc3545;
}

.dropdown-item-danger:hover {
    background: #fee;
}

.dropdown-icon {
    font-size: 1rem;
}

/* Notification styles */
.notifications-wrapper {
    position: relative;
}

.notification-bell {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.5rem;
    position: relative;
}

.notification-count {
    position: absolute;
    top: 0;
    right: 0;
    background: #dc3545;
    color: white;
    font-size: 0.625rem;
    font-weight: 600;
    padding: 0.125rem 0.375rem;
    border-radius: 10px;
    min-width: 16px;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sidebar-footer {
        padding: 0.5rem;
    }
    
    .timer-info {
        display: none;
    }
    
    .header-left h1 {
        font-size: 1.25rem;
    }
}
</style>

<!-- Script per gestione interazioni -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Sidebar
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('mainSidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            // Salva stato in localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        
        // Ripristina stato sidebar
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }
    
    // User Menu Dropdown
    const userAvatar = document.querySelector('.user-avatar');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.style.display = userDropdown.style.display === 'none' ? 'block' : 'none';
        });
        
        // Chiudi dropdown cliccando fuori
        document.addEventListener('click', function() {
            userDropdown.style.display = 'none';
        });
        
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Timer Lavoro
    const timerText = document.getElementById('timerText');
    if (timerText) {
        let seconds = 0;
        
        // Recupera tempo salvato se esiste
        const savedTime = sessionStorage.getItem('workTime');
        if (savedTime) {
            seconds = parseInt(savedTime);
        }
        
        // Aggiorna timer ogni secondo
        setInterval(function() {
            seconds++;
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            timerText.textContent = 
                String(hours).padStart(2, '0') + ':' + 
                String(minutes).padStart(2, '0') + ':' + 
                String(secs).padStart(2, '0');
            
            // Salva tempo
            sessionStorage.setItem('workTime', seconds);
            
            // Alert overtime (dopo 8 ore)
            if (seconds === 28800) { // 8 ore
                timerText.style.color = '#dc3545';
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('RE.DE CRM', {
                        body: 'Hai completato le 8 ore lavorative!',
                        icon: '/crm/assets/images/logo-rede.png'
                    });
                }
            }
        }, 1000);
    }
});
</script>