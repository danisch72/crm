<?php
/**
 * components/sidebar.php - Sidebar Laterale CRM Re.De Consulting
 * 
 * ✅ SIDEBAR PROFESSIONALE FISSA A SINISTRA
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