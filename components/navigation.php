<?php
/**
 * components/navigation.php - Navigazione Centralizzata CRM Re.De Consulting
 * 
 * Componente semplice e unificato per sidebar del CRM
 * Include:
 * - Logo RE.DE grande e centrato
 * - Menu navigazione 
 * - Design pulito senza complicazioni
 * 
 * @version 2.1 - Semplificato
 * @author Tecnico Informatico + Grafico Esperto RE.DE
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

// Determina pagina attiva basandosi su URL
$currentAction = $_GET['action'] ?? 'dashboard';
$currentView = $_GET['view'] ?? '';
?>

<!-- Sidebar Uniforme CRM Re.De -->
<div class="sidebar">
    <div class="sidebar-header">
        <!-- Logo RE.DE più grande e centrato -->
        <div class="sidebar-logo">
            <img src="/crm/assets/images/logo-rede-white.png" 
                 alt="RE.DE Consulting" 
                 class="logo-rede">
        </div>
    </div>
    
    <nav class="nav">
        <div class="nav-section">
            <div class="nav-item">
                <a href="/crm/?action=dashboard" class="nav-link <?= $currentAction === 'dashboard' ? 'nav-link-active' : '' ?>">
                    <span>🏠</span> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="/crm/?action=operatori" class="nav-link <?= $currentAction === 'operatori' ? 'nav-link-active' : '' ?>">
                    <span>👥</span> Operatori
                </a>
            </div>
            <div class="nav-item">
                <a href="/crm/?action=clienti" class="nav-link <?= $currentAction === 'clienti' ? 'nav-link-active' : '' ?>">
                    <span>🏢</span> Clienti
                </a>
            </div>
            <div class="nav-item">
                <a href="/crm/?action=pratiche" class="nav-link <?= $currentAction === 'pratiche' ? 'nav-link-active' : '' ?>">
                    <span>📋</span> Pratiche
                </a>
            </div>
            <div class="nav-item">
                <a href="/crm/?action=scadenze" class="nav-link <?= $currentAction === 'scadenze' ? 'nav-link-active' : '' ?>">
                    <span>⏰</span> Scadenze
                </a>
            </div>
        </div>
        
        <?php if ($sessionInfo['is_admin'] ?? false): ?>
        <!-- Sezione Admin -->
        <div class="nav-section" style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2);">
            <div class="nav-section-title">Amministrazione</div>
            <div class="nav-item">
                <a href="/crm/?action=settings" class="nav-link <?= $currentAction === 'settings' ? 'nav-link-active' : '' ?>">
                    <span>⚙️</span> Impostazioni
                </a>
            </div>
            <div class="nav-item">
                <a href="/crm/?action=reports" class="nav-link <?= $currentAction === 'reports' ? 'nav-link-active' : '' ?>">
                    <span>📊</span> Report
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Logout in fondo -->
        <div class="nav-section" style="margin-top: auto; padding-top: 1rem;">
            <div class="nav-item">
                <a href="/crm/logout.php" class="nav-link">
                    <span>🚪</span> Esci
                </a>
            </div>
        </div>
    </nav>
</div>

<!-- Styles specifici per navigation component -->
<style>
/* Sidebar style semplice e pulito */
.sidebar {
    width: 280px;
    height: 100vh;
    background: var(--primary-green, #007849);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 2rem 1rem;
    text-align: center;
    background: rgba(0, 0, 0, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

/* Logo styling */
.logo-rede {
    height: 60px;
    width: auto;
    filter: brightness(0) invert(1); /* Rende il logo bianco */
}

/* Navigation */
.nav {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
    display: flex;
    flex-direction: column;
}

.nav-section {
    padding: 0.5rem 0;
}

.nav-section-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.6);
    padding: 0.5rem 1.5rem;
    font-weight: 600;
}

.nav-item {
    margin: 0.125rem 0.5rem;
}

.nav-link {
    display: flex;
    align-items: center;