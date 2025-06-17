<?php
/**
 * components/navigation.php - Sidebar Professionale CRM Re.De Consulting
 * 
 * ✅ VERSIONE PROFESSIONALE CON UX OTTIMIZZATA
 * 
 * Features:
 * - Colori professionali ad alto contrasto
 * - Design pulito stile CRM moderni
 * - Hover states eleganti
 * - Logo RE.DE integrato
 * - Navigazione chiara e leggibile
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
        'nome_completo' => $_SESSION['nome'] . ' ' . $_SESSION['cognome'],
        'is_admin' => $_SESSION['is_admin'] ?? false
    ];
}

// Determina pagina attiva
$currentAction = $_GET['action'] ?? 'dashboard';
$currentView = $_GET['view'] ?? '';
?>

<!-- Sidebar Professionale -->
<aside class="sidebar" id="mainSidebar">
    <!-- Header con Logo -->
    <div class="sidebar-header">
        <img src="/crm/assets/images/logo-rede.png" 
             alt="RE.DE Consulting" 
             class="sidebar-logo">
        <span class="sidebar-title">CRM</span>
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
                    <?php
                    // Badge contatore (esempio)
                    $clientiAttivi = 127; // In produzione: query DB
                    ?>
                    <span class="nav-badge"><?= $clientiAttivi ?></span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="/crm/?action=pratiche" 
                   class="nav-link <?= $currentAction === 'pratiche' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Pratiche</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="/crm/?action=scadenze" 
                   class="nav-link <?= $currentAction === 'scadenze' ? 'active' : '' ?>">
                    <span class="nav-icon">⏰</span>
                    <span class="nav-text">Scadenze</span>
                    <?php
                    // Badge scadenze imminenti
                    $scadenzeOggi = 3; // In produzione: query DB
                    if ($scadenzeOggi > 0):
                    ?>
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
            </ul>
        </div>
        <?php endif; ?>
    </nav>
    
    <!-- Footer Sidebar -->
    <div class="sidebar-footer">
        <a href="/crm/logout.php" class="nav-link nav-logout">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Esci</span>
        </a>
    </div>
</aside>

<style>
/* =============================================
   SIDEBAR PROFESSIONALE - COLORI OTTIMIZZATI
   ============================================= */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: all 0.3s ease;
}

/* Header con Logo */
.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sidebar-logo {
    height: 32px;
    width: auto;
}

.sidebar-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

/* Navigazione */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 0.125rem 0.5rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #4b5563;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    font-weight: 500;
    position: relative;
}

.nav-link:hover {
    background-color: #f3f4f6;
    color: #1f2937;
}

/* Link Attivo - Verde Datev solo per highlight */
.nav-link.active {
    background-color: #007849;
    color: white;
}

.nav-link.active .nav-icon {
    filter: brightness(0) invert(1);
}

/* Icone e Testo */
.nav-icon {
    width: 20px;
    font-size: 1.125rem;
    margin-right: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.nav-text {
    flex: 1;
}

/* Badge */
.nav-badge {
    background-color: #e5e7eb;
    color: #1f2937;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    min-width: 20px;
    text-align: center;
}

.nav-badge.urgent {
    background-color: #ef4444;
    color: white;
}

.nav-link.active .nav-badge {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
}

/* Divider */
.nav-divider {
    height: 1px;
    background-color: #e5e7eb;
    margin: 1rem 1rem;
}

/* Section Title */
.nav-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0 1rem;
    margin: 0.5rem 0;
}

/* Footer */
.sidebar-footer {
    padding: 1rem 0.5rem;
    border-top: 1px solid #e5e7eb;
}

.nav-logout {
    color: #ef4444 !important;
}

.nav-logout:hover {
    background-color: #fee2e2 !important;
}

/* Scrollbar personalizzata */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: #f3f4f6;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Responsive - Sidebar collassabile */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
}

/* Animazioni sottili */
.nav-link {
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 120, 73, 0.1), transparent);
    transition: left 0.5s;
}

.nav-link:hover::before {
    left: 100%;
}
</style>