<?php
/**
 * components/sidebar.php - Sidebar Laterale CRM Re.De Consulting
 * 
 * ✅ SIDEBAR FISSA (NO COLLAPSE)
 * ✅ DESIGN PROFESSIONALE DATEV KOINOS
 * ✅ NAVIGAZIONE COMPLETA E MODULARE
 * ✅ NESSUNA SCROLLBAR
 * 
 * Features:
 * - Menu principale con icone
 * - Sezione admin condizionale
 * - Badge contatori
 * - Evidenziazione pagina attiva
 * - Logo RE.DE
 */

// Verifica autenticazione
if (!isset($sessionInfo) || empty($sessionInfo)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: /crm/login.php');
        exit;
    }
    
    $sessionInfo = [
        'nome_completo' => $_SESSION['nome'] ?? 'Utente',
        'nome' => $_SESSION['nome'] ?? '',
        'cognome' => $_SESSION['cognome'] ?? '',
        'is_admin' => $_SESSION['is_admin'] ?? false,
        'operatore_id' => $_SESSION['user_id'] ?? 0
    ];
}

// Determina pagina attiva
$currentAction = $_GET['action'] ?? 'dashboard';
$currentView = $_GET['view'] ?? '';

// Contatori per badge (in produzione usare query DB reali)
$clientiAttivi = 3;
$praticheAttive = 3;
$scadenzeOggi = 0;
?>

<!-- Sidebar Styles -->
<style>
/* Sidebar Navigation più compatta */
.sidebar-nav {
    flex: 1;
    padding: 0.25rem 0;
}

/* Nav badge urgent con animazione */
.nav-badge.urgent {
    background: #ff4444;
    color: white;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}
</style>

<!-- Sidebar Laterale -->
<aside class="sidebar" id="mainSidebar">
    <!-- Header con Logo -->
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="/crm/assets/images/logo-rede.png" 
                 alt="RE.DE Consulting" 
                 class="sidebar-logo"
                 onerror="this.style.display='none'; document.getElementById('logo-text').style.display='flex';">
            <div id="logo-text" class="logo-text" style="display:none;">
                <span class="logo-rede">RE.DE</span>
                <span class="logo-consulting">CONSULTING</span>
            </div>
        </div>
    </div>
    
    <!-- Navigazione Principale -->
    <nav class="sidebar-nav">
        <!-- Menu Principale -->
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/crm/?action=dashboard" 
                   class="nav-link <?= $currentAction === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="/crm/?action=operatori" 
                   class="nav-link <?= $currentAction === 'operatori' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Operatori</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="/crm/?action=clienti" 
                   class="nav-link <?= $currentAction === 'clienti' ? 'active' : '' ?>">
                    <span class="nav-icon">🏢</span>
                    <span class="nav-text">Clienti</span>
                    <?php if ($clientiAttivi > 0): ?>
                        <span class="nav-badge"><?= $clientiAttivi ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="/crm/?action=pratiche" 
                   class="nav-link <?= $currentAction === 'pratiche' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Pratiche</span>
                    <?php if ($praticheAttive > 0): ?>
                        <span class="nav-badge"><?= $praticheAttive ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="/crm/?action=scadenze" 
                   class="nav-link <?= $currentAction === 'scadenze' ? 'active' : '' ?>">
                    <span class="nav-icon">⏰</span>
                    <span class="nav-text">Scadenze</span>
                    <?php if ($scadenzeOggi > 0): ?>
                        <span class="nav-badge urgent"><?= $scadenzeOggi ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        
        <?php if ($sessionInfo['is_admin'] ?? false): ?>
        <!-- Sezione Admin -->
        <div class="nav-divider"></div>
        
        <div class="nav-section">
            <h5 class="nav-section-title">AMMINISTRAZIONE</h5>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/crm/?action=settings" 
                       class="nav-link <?= $currentAction === 'settings' ? 'active' : '' ?>">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Impostazioni</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/crm/?action=reports" 
                       class="nav-link <?= $currentAction === 'reports' ? 'active' : '' ?>">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Report</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/crm/?action=backup" 
                       class="nav-link <?= $currentAction === 'backup' ? 'active' : '' ?>">
                        <span class="nav-icon">💾</span>
                        <span class="nav-text">Backup</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>
    
    <!-- Footer Sidebar -->
    <div class="sidebar-footer">
        <div class="user-info-mini">
            <div class="user-avatar-mini">
                <?= strtoupper(substr($sessionInfo['nome'] ?? 'U', 0, 1) . substr($sessionInfo['cognome'] ?? '', 0, 1)) ?>
            </div>
            <div class="user-details-mini">
                <div class="user-name-mini"><?= htmlspecialchars($sessionInfo['nome_completo']) ?></div>
                <div class="user-role-mini"><?= $sessionInfo['is_admin'] ? 'Admin' : 'Operatore' ?></div>
            </div>
        </div>
        
        <a href="/crm/auth/logout.php" class="nav-link nav-logout">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Esci</span>
        </a>
    </div>
</aside>

<!-- Overlay per mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- JavaScript Sidebar -->
<script>
// Funzione per mobile
window.toggleMobileSidebar = function() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('sidebar-mobile-open');
    
    if (sidebar.classList.contains('sidebar-mobile-open')) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Click su overlay chiude sidebar mobile
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-mobile-open');
            overlay.style.display = 'none';
        });
    }
    
    // Chiudi sidebar su mobile quando si clicca un link
    if (window.innerWidth < 768) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('sidebar-mobile-open');
                if (overlay) overlay.style.display = 'none';
            });
        });
    }
});
</script>