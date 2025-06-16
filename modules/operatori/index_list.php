<?php
/**
 * modules/operatori/index_list.php - Lista Operatori CRM Re.De Consulting
 * 
 * ✅ VERSIONE AGGIORNATA CON ROUTER
 */

// Verifica che siamo passati dal router
if (!defined('OPERATORI_ROUTER_LOADED')) {
    header('Location: /crm/?action=operatori');
    exit;
}

// Tutto è già disponibile dal router:
// $sessionInfo, $db, $currentUser
$isAdmin = $sessionInfo['is_admin'];

// Gestione filtri
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$role = $_GET['role'] ?? 'all';

// Costruzione query con filtri
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(nome LIKE ? OR cognome LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($status !== 'all') {
    $whereConditions[] = "is_attivo = ?";
    $params[] = ($status === 'active') ? 1 : 0;
}

if ($role !== 'all') {
    $whereConditions[] = "is_amministratore = ?";
    $params[] = ($role === 'admin') ? 1 : 0;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Query principale operatori con join per sessioni attive
try {
    $operatori = $db->select(
        "SELECT o.*, 
            (SELECT MAX(login_timestamp) FROM sessioni_lavoro WHERE operatore_id = o.id) as ultimo_accesso,
            (SELECT COUNT(*) FROM sessioni_lavoro WHERE operatore_id = o.id AND logout_timestamp IS NULL) as sessioni_attive
        FROM operatori o
        $whereClause
        ORDER BY o.cognome, o.nome",
        $params
    );
    
    // Statistiche per admin
    $stats = null;
    if ($isAdmin) {
        $stats = $db->selectOne("
            SELECT 
                COUNT(*) as totale_operatori,
                SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as operatori_attivi,
                SUM(CASE WHEN is_amministratore = 1 THEN 1 ELSE 0 END) as amministratori,
                SUM(CASE WHEN EXISTS(
                    SELECT 1 FROM sessioni_lavoro 
                    WHERE operatore_id = operatori.id 
                    AND logout_timestamp IS NULL
                ) THEN 1 ELSE 0 END) as sessioni_attive,
                NOW() as ultimo_aggiornamento
            FROM operatori
        ");
    }
} catch (Exception $e) {
    error_log("Errore query operatori: " . $e->getMessage());
    $operatori = [];
    $stats = null;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Operatori - CRM Re.De Consulting</title>
    
    <link rel="stylesheet" href="/crm/assets/css/datev-koinos.css">
    
    <style>
        :root {
            /* Datev Koinos Colors */
            --primary-blue: #1B4F8F;
            --secondary-green: #61B44A;
            --accent-orange: #EE7F00;
            --danger-red: #DC3545;
            --gray-100: #F5F5F5;
            --gray-200: #E0E0E0;
            --gray-300: #BDBDBD;
            --gray-400: #9E9E9E;
            --gray-500: #757575;
            --gray-600: #616161;
            --gray-700: #424242;
            --gray-800: #212121;
            
            /* Spacing Ultra-Compatto */
            --spacing-xs: 0.125rem;
            --spacing-sm: 0.25rem;
            --spacing-md: 0.5rem;
            --spacing-lg: 0.75rem;
            --spacing-xl: 1rem;
            
            /* Sizes */
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            
            /* Font */
            --font-primary: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --transition-fast: all 0.15s ease;
        }
        
        body {
            font-family: var(--font-primary);
            background: var(--gray-100);
            margin: 0;
            padding: 0;
            font-size: 0.875rem;
            line-height: 1.3;
            color: var(--gray-800);
        }
        
        .page-wrapper {
            min-height: 100vh;
            background: white;
        }
        
        /* Header Ultra-Denso */
        .main-header {
            background: var(--primary-blue);
            color: white;
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
        }
        
        .page-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Timer e User Menu Compatti */
        .work-timer-display {
            background: rgba(255,255,255,0.1);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--secondary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Content Container */
        .content-container {
            padding: 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Statistiche Inline Ultra-Compatte */
        .stats-inline {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 0.5rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .stat-compact {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .stat-compact span:last-child {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        /* Tabella Ultra-Densa */
        .data-table {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        
        .table-header {
            background: var(--gray-50);
            display: grid;
            grid-template-columns: 220px 180px 100px 80px 90px 120px auto;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 220px 180px 100px 80px 90px 120px auto;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            align-items: center;
            transition: var(--transition-fast);
        }
        
        .table-row:hover {
            background: var(--gray-50);
        }
        
        /* Micro Components */
        .avatar-micro {
            width: 24px;
            height: 24px;
            background: var(--secondary-green);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.625rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .status-badge {
            padding: 0.125rem 0.5rem;
            border-radius: 999px;
            font-size: 0.625rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: #E7F5E1;
            color: #2E7D32;
        }
        
        .status-inactive {
            background: #FFEBEE;
            color: #C62828;
        }
        
        .role-badge {
            padding: 0.125rem 0.375rem;
            border-radius: var(--radius-sm);
            font-size: 0.625rem;
            font-weight: 500;
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .role-admin {
            background: #E3F2FD;
            color: #1565C0;
        }
        
        /* Bottoni Micro */
        .btn-micro {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            margin: 0 0.125rem;
        }
        
        .btn-micro.view {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .btn-micro.view:hover {
            background: var(--secondary-green);
            color: white;
        }
        
        .btn-micro.edit {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .btn-micro.edit:hover {
            background: var(--accent-orange);
            color: white;
        }
        
        .btn-micro.delete {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .btn-micro.delete:hover {
            background: var(--danger-red);
            color: white;
        }
        
        /* Header Actions Ultra-Compatto */
        .operators-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-top: 0.5rem;
        }
        
        .operators-header h2 {
            color: var(--gray-800);
            margin-bottom: 0.25rem;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .operators-header p {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Filtri Compatti */
        .filters-container {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 120px 120px auto;
            gap: 0.5rem;
            align-items: center;
        }
        
        /* Form Controls Compatti */
        .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            background: white;
            transition: var(--transition-fast);
        }
        
        .form-control-sm:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(27, 79, 143, 0.1);
        }
        
        /* Bottoni Ultra-Compatti */
        .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition-fast);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.625rem;
        }
        
        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }
        
        .btn-primary:hover {
            background: #153E70;
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            background: var(--gray-100);
        }
        
        /* Responsive Ultra-Denso */
        @media (max-width: 1024px) {
            .table-header,
            .table-row {
                grid-template-columns: 200px 150px 80px 70px 80px 100px auto;
            }
        }
        
        @media (max-width: 768px) {
            .operators-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-inline {
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                display: none;
            }
            
            .table-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                padding: 0.75rem;
                border-bottom: 2px solid var(--gray-200);
            }
        }
        
        /* Messaggi */
        .alert {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: #E7F5E1;
            color: #2E7D32;
            border: 1px solid #C3E6C3;
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #FFCDD2;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header Ultra-Denso -->
        <header class="main-header">
            <div class="header-left">
                <button class="menu-toggle">
                    <span>☰</span>
                </button>
                <h1 class="page-title">Gestione Operatori</h1>
            </div>
            
            <div class="header-right">
                <!-- Link Dashboard -->
                <a href="/crm/?action=dashboard" class="btn btn-secondary btn-sm">
                    🏠 Dashboard
                </a>
                
                <!-- Timer Lavoro -->
                <div class="work-timer work-timer-display">
                    <span class="timer-icon">⏱️</span>
                    <span class="time-display">00:00:00</span>
                </div>
                
                <!-- User Menu -->
                <div class="user-menu">
                    <div class="user-avatar" data-tooltip="<?= htmlspecialchars($sessionInfo['nome_completo']) ?>">
                        <?= substr($sessionInfo['nome_completo'], 0, 1) ?>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content Ultra-Denso -->
        <div class="content-container">
            <!-- Messaggi Flash -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Header con azioni -->
            <div class="operators-header">
                <div>
                    <h2>👥 Gestione Operatori</h2>
                    <p>Visualizza e gestisci tutti gli operatori del sistema</p>
                </div>
                
                <div class="header-actions">
                    <?php if ($isAdmin): ?>
                        <a href="/crm/?action=operatori&view=create" class="btn btn-primary btn-sm">
                            ➕ Nuovo Operatore
                        </a>
                        <a href="/crm/?action=operatori&view=stats" class="btn btn-secondary btn-sm">
                            📊 Statistiche Team
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistiche Inline Ultra-Compatte (Solo Admin) -->
            <?php if ($isAdmin && $stats): ?>
            <div class="stats-inline">
                <div class="stat-compact">
                    <span class="stat-icon">👥</span>
                    <span><?= $stats['totale_operatori'] ?? 0 ?> Operatori</span>
                </div>
                
                <div class="stat-compact">
                    <span class="stat-icon">✅</span>
                    <span><?= $stats['operatori_attivi'] ?? 0 ?> Attivi (<?= ($stats['totale_operatori'] ?? 0) > 0 ? round(($stats['operatori_attivi'] ?? 0) / ($stats['totale_operatori'] ?? 1) * 100) : 0 ?>%)</span>
                </div>
                
                <div class="stat-compact">
                    <span class="stat-icon">👨‍💼</span>
                    <span><?= $stats['amministratori'] ?? 0 ?> Admin</span>
                </div>
                
                <div class="stat-compact">
                    <span class="stat-icon">🕐</span>
                    <span><?= $stats['sessioni_attive'] ?? 0 ?> Sessioni</span>
                </div>
                
                <div class="stat-compact">
                    <span class="stat-icon">🔄</span>
                    <span><?= isset($stats['ultimo_aggiornamento']) ? date('H:i', strtotime($stats['ultimo_aggiornamento'])) : date('H:i') ?> Aggiornato</span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Filtri Compatti -->
            <div class="filters-container">
                <form method="GET">
                    <input type="hidden" name="action" value="operatori">
                    <div class="filters-row">
                        <div>
                            <input type="text" 
                                   name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="🔍 Cerca per nome, cognome o email..." 
                                   class="form-control form-control-sm">
                        </div>
                        
                        <div>
                            <select name="status" class="form-control form-control-sm">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tutti Stati</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Attivi</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inattivi</option>
                            </select>
                        </div>
                        
                        <div>
                            <select name="role" class="form-control form-control-sm">
                                <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>Tutti Ruoli</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Amministratori</option>
                                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Operatori</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-primary btn-sm">Filtra</button>
                            <a href="/crm/?action=operatori" class="btn btn-secondary btn-sm">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tabella Operatori Ultra-Densa -->
            <div class="data-table">
                <div class="table-header">
                    <div>Operatore</div>
                    <div>Email</div>
                    <div>Ruolo</div>
                    <div>Stato</div>
                    <div>Ultimo Accesso</div>
                    <div>Sessioni Attive</div>
                    <div>Azioni</div>
                </div>
                
                <?php if (!empty($operatori)): ?>
                    <?php foreach ($operatori as $operatore): ?>
                    <div class="table-row">
                        <div style="display: flex; align-items: center;">
                            <span class="avatar-micro">
                                <?= strtoupper(substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1)) ?>
                            </span>
                            <div>
                                <div style="font-weight: 500;"><?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?></div>
                                <div style="font-size: 0.625rem; color: var(--gray-500);">ID: #<?= $operatore['id'] ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <div style="font-size: 0.75rem;"><?= htmlspecialchars($operatore['email']) ?></div>
                        </div>
                        
                        <div>
                            <?php if ($operatore['is_amministratore']): ?>
                                <span class="role-badge role-admin">👨‍💼 Admin</span>
                            <?php else: ?>
                                <span class="role-badge">👤 Operatore</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($operatore['is_attivo']): ?>
                                <span class="status-badge status-active">● Attivo</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">● Inattivo</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($operatore['ultimo_accesso']): ?>
                                <div style="font-size: 0.625rem;"><?= date('d/m/Y', strtotime($operatore['ultimo_accesso'])) ?></div>
                                <div style="font-size: 0.625rem; color: var(--gray-500);"><?= date('H:i', strtotime($operatore['ultimo_accesso'])) ?></div>
                            <?php else: ?>
                                <span style="font-size: 0.625rem; color: var(--gray-400);">Mai</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($operatore['sessioni_attive'] > 0): ?>
                                <span style="color: var(--secondary-green); font-weight: 600;">🟢 <?= $operatore['sessioni_attive'] ?></span>
                            <?php else: ?>
                                <span style="color: var(--gray-400);">-</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; gap: 0.125rem;">
                            <a href="/crm/?action=operatori&view=view&id=<?= $operatore['id'] ?>" 
                               class="btn-micro view" 
                               title="Visualizza">
                                👁️
                            </a>
                            
                            <?php if ($isAdmin || $sessionInfo['operatore_id'] == $operatore['id']): ?>
                                <a href="/crm/?action=operatori&view=edit&id=<?= $operatore['id'] ?>" 
                                   class="btn-micro edit" 
                                   title="Modifica">
                                    ✏️
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($isAdmin && $operatore['id'] != $sessionInfo['operatore_id']): ?>
                                <button class="btn-micro delete" 
                                        title="Disattiva" 
                                        onclick="toggleOperatore(<?= $operatore['id'] ?>, <?= $operatore['is_attivo'] ?>)">
                                    🗑️
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 2rem; text-align: center; color: var(--gray-500);">
                        <p>Nessun operatore trovato con i criteri di ricerca specificati.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle stato operatore
        function toggleOperatore(id, currentStatus) {
            if (!confirm(currentStatus ? 'Disattivare questo operatore?' : 'Riattivare questo operatore?')) {
                return;
            }
            
            // Implementare chiamata AJAX per toggle stato
            console.log('Toggle operatore', id);
        }
        
        // Timer lavoro mock
        let seconds = 0;
        setInterval(() => {
            seconds++;
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            document.querySelector('.time-display').textContent = 
                `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }, 1000);
    </script>
</body>
</html>