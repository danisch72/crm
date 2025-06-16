<?php
/**
 * components/header.php - Header Centralizzato CRM Re.De Consulting
 * 
 * Header orizzontale con:
 * - Titolo pagina
 * - Timer lavoro
 * - Info utente
 * - Azioni rapide
 * 
 * @version 1.0
 * @author Tecnico Informatico RE.DE
 */

// Determina titolo pagina
$pageTitle = 'Dashboard';
$pageIcon = '🏠';

// Mappa titoli e icone per le pagine
$pageTitles = [
    'dashboard' => ['title' => 'Dashboard', 'icon' => '🏠'],
    'operatori' => ['title' => 'Gestione Operatori', 'icon' => '👥'],
    'clienti' => ['title' => 'Gestione Clienti', 'icon' => '🏢'],
    'pratiche' => ['title' => 'Gestione Pratiche', 'icon' => '📋'],
    'scadenze' => ['title' => 'Gestione Scadenze', 'icon' => '⏰'],
    'settings' => ['title' => 'Impostazioni', 'icon' => '⚙️'],
    'reports' => ['title' => 'Report', 'icon' => '📊']
];

$currentAction = $_GET['action'] ?? 'dashboard';
if (isset($pageTitles[$currentAction])) {
    $pageTitle = $pageTitles[$currentAction]['title'];
    $pageIcon = $pageTitles[$currentAction]['icon'];
}

// Sottotitoli per view specifiche
$viewSubtitles = [
    'create' => 'Nuovo',
    'edit' => 'Modifica',
    'view' => 'Dettaglio',
    'stats' => 'Statistiche',
    'export' => 'Esporta',
    'import' => 'Importa'
];

$currentView = $_GET['view'] ?? '';
if ($currentView && isset($viewSubtitles[$currentView])) {
    $pageTitle .= ' - ' . $viewSubtitles[$currentView];
}
?>

<!-- Header principale -->
<header class="main-header">
    <div class="header-left">
        <!-- Toggle mobile sidebar -->
        <button class="sidebar-toggle" onclick="toggleMobileSidebar()">
            ☰
        </button>
        
        <!-- Titolo pagina -->
        <div class="header-title">
            <h1><?= $pageIcon ?> <?= htmlspecialchars($pageTitle) ?></h1>
            <?php if ($currentAction === 'clienti'): ?>
                <p class="header-subtitle">Portfolio clienti studio commercialista</p>
            <?php elseif ($currentAction === 'operatori'): ?>
                <p class="header-subtitle">Team e collaboratori</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="header-right">
        <!-- Timer lavoro -->
        <div class="work-timer-display">
            <span class="timer-icon">⏱️</span>
            <span class="timer-text" id="workTimer">00:00:00</span>
            <span class="timer-label">/ 8h</span>
        </div>
        
        <!-- User info -->
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($sessionInfo['nome_completo'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($sessionInfo['nome_completo'] ?? 'Utente') ?></div>
                <div class="user-role"><?= ($sessionInfo['is_admin'] ?? false) ? 'Amministratore' : 'Operatore' ?></div>
            </div>
        </div>
    </div>
</header>

<!-- Styles per header -->
<style>
.main-header {
    background: white;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    position: relative;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.sidebar-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
}

@media (max-width: 768px) {
    .sidebar-toggle {
        display: block;
    }
}

.header-title h1 {
    font-size: 1.25rem;
    margin: 0;
    color: var(--gray-800, #2c3e50);
}

.header-subtitle {
    font-size: 0.875rem;
    color: var(--gray-600, #6c757d);
    margin: 0.125rem 0 0 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 2rem;
}

/* Timer styling */
.work-timer-display {
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.timer-icon {
    font-size: 1rem;
}

.timer-text {
    font-weight: 600;
    color: #0369a1;
    font-family: 'Courier New', monospace;
    min-width: 70px;
}

.timer-label {
    color: #64748b;
    font-size: 0.75rem;
}

/* User info */
.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 36px;
    height: 36px;
    background: var(--primary-green, #007849);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.user-details {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-800, #2c3e50);
    line-height: 1.2;
}

.user-role {
    font-size: 0.75rem;
    color: var(--gray-600, #6c757d);
}

/* Responsive */
@media (max-width: 768px) {
    .main-header {
        padding: 0 1rem;
    }
    
    .header-subtitle {
        display: none;
    }
    
    .user-details {
        display: none;
    }
    
    .timer-label {
        display: none;
    }
}
</style>

<!-- Script timer -->
<script>
// Timer lavoro semplice
(function() {
    let seconds = 0;
    
    // Recupera tempo salvato
    const savedTime = sessionStorage.getItem('workTime');
    if (savedTime) {
        seconds = parseInt(savedTime);
    }
    
    function updateTimer() {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        const display = 
            String(hours).padStart(2, '0') + ':' + 
            String(minutes).padStart(2, '0') + ':' + 
            String(secs).padStart(2, '0');
        
        document.getElementById('workTimer').textContent = display;
        
        // Cambia colore se supera 8 ore
        if (seconds > 28800) {
            document.querySelector('.timer-text').style.color = '#dc2626';
        }
        
        seconds++;
        sessionStorage.setItem('workTime', seconds);
    }
    
    updateTimer();
    setInterval(updateTimer, 1000);
})();

// Toggle sidebar mobile
function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('mobile-open');
}
</script>