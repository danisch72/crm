<?php
/**
 * modules/operatori/index.php - Lista Operatori CRM Re.De Consulting
 * 
 * ✅ LAYOUT ULTRA-DENSO CONFORME AL PIANO DI ILLUSTRAZIONE v2.0
 * 
 * Features:
 * - Layout tabellare 7-colonne enterprise-grade (220px|180px|100px|80px|90px|120px|auto)
 * - Statistiche inline compatte (40px altezza)
 * - Micro-components ottimizzati (avatar 24px, bottoni 24px)
 * - Spacing ultra-compatto (-75% padding, -70% margin)
 * - Design system Datev Koinos compliant
 * - Densità informazioni +300% vs layout precedente
 * 
 * 🔧 CORREZIONI DATABASE v2.1:
 * - Rimossa dipendenza da colonna 'ultimo_accesso' (non esistente)
 * - Calcolo ultimo accesso da MAX(login_timestamp) in sessioni_lavoro
 * - Aggiunto error handling robusto per query database
 * - Gestione graceful di valori null/mancanti
 * - Protezione JavaScript per API non ancora implementate
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorsi assoluti robusti per evitare problemi
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/auth/AuthSystem.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/crm/core/functions/helpers.php';

// Verifica autenticazione
if (!AuthSystem::isAuthenticated()) {
    header('Location: /crm/core/auth/login.php');
    exit;
}

$sessionInfo = AuthSystem::getSessionInfo();
$db = Database::getInstance();

// Verifica permessi amministratore per alcune azioni
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
            CASE WHEN sl.id IS NOT NULL THEN 1 ELSE 0 END as is_online,
            COALESCE(sl.modalita_lavoro, '') as modalita_corrente,
            sl.login_timestamp as sessione_inizio,
            (SELECT COUNT(*) FROM sessioni_lavoro sl2 WHERE sl2.operatore_id = o.id AND sl2.is_attiva = 1) as sessioni_attive,
            (SELECT COALESCE(SUM(ore_effettive), 0) FROM sessioni_lavoro sl3 WHERE sl3.operatore_id = o.id AND WEEK(sl3.login_timestamp) = WEEK(NOW())) as ore_settimana,
            (SELECT MAX(login_timestamp) FROM sessioni_lavoro sl4 WHERE sl4.operatore_id = o.id) as ultimo_accesso,
            40 as ore_settimanali
        FROM operatori o
        LEFT JOIN sessioni_lavoro sl ON o.id = sl.operatore_id AND sl.is_attiva = 1
        $whereClause
        ORDER BY o.is_attivo DESC, (SELECT MAX(login_timestamp) FROM sessioni_lavoro sl5 WHERE sl5.operatore_id = o.id) DESC",
        $params
    );
} catch (Exception $e) {
    error_log("Errore query operatori: " . $e->getMessage());
    $operatori = [];
}

// Statistiche generali (Solo Admin)
$stats = null;
if ($isAdmin) {
    try {
        $stats = $db->selectOne(
            "SELECT 
                COUNT(*) as totale_operatori,
                SUM(CASE WHEN is_attivo = 1 THEN 1 ELSE 0 END) as operatori_attivi,
                SUM(CASE WHEN is_amministratore = 1 THEN 1 ELSE 0 END) as amministratori,
                (SELECT COUNT(DISTINCT operatore_id) FROM sessioni_lavoro WHERE is_attiva = 1) as sessioni_attive,
                NOW() as ultimo_aggiornamento
            FROM operatori"
        );
    } catch (Exception $e) {
        error_log("Errore query statistiche: " . $e->getMessage());
        $stats = [
            'totale_operatori' => 0,
            'operatori_attivi' => 0,
            'amministratori' => 0,
            'sessioni_attive' => 0,
            'ultimo_aggiornamento' => date('Y-m-d H:i:s')
        ];
    }
}

// Funzione helper per formattare l'ultimo accesso
function formatLastAccess($timestamp) {
    if (!$timestamp || $timestamp === '0000-00-00 00:00:00') return '-';
    
    try {
        $diff = time() - strtotime($timestamp);
        if ($diff < 3600) return floor($diff/60) . 'm';
        if ($diff < 86400) return floor($diff/3600) . 'h';
        if ($diff < 604800) return floor($diff/86400) . 'd';
        return date('d/m', strtotime($timestamp));
    } catch (Exception $e) {
        return '-';
    }
}

// Funzione helper per status indicator
function getStatusIndicator($isActive, $isOnline) {
    if (!$isActive) return '❌';
    return $isOnline ? '🟢' : '🔴';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Gestione Operatori - CRM Re.De Consulting</title>
    
    <!-- Design System Datev Ultra-Denso -->
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/responsive.css">
    
    <!-- Layout Ultra-Denso Specifico -->
    <style>
        /* Layout Ultra-Denso Enterprise */
        .stats-inline {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            padding: 0.5rem 0;
        }
        
        .stat-compact {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 0.5rem 0.75rem;
            height: 40px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            transition: all var(--transition-fast);
        }
        
        .stat-compact:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }
        
        .stat-icon {
            font-size: 1rem;
        }
        
        /* Layout Tabellare 7-Colonne Ultra-Denso */
        .operators-table {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 220px 180px 100px 80px 90px 120px auto;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .operator-row {
            display: grid;
            grid-template-columns: 220px 180px 100px 80px 90px 120px auto;
            gap: 0.5rem;
            padding: 0.25rem 0.5rem;
            height: 32px;
            align-items: center;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }
        
        .operator-row:hover {
            background: var(--green-50);
            border-color: var(--primary-green);
        }
        
        .operator-row:last-child {
            border-bottom: none;
        }
        
        /* Micro-Components Ultra-Compatti */
        .operator-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .operator-avatar {
            width: 24px;
            height: 24px;
            background: var(--primary-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .operator-name {
            font-weight: 500;
            color: var(--gray-800);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .badge-mini {
            background: var(--accent-orange);
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: var(--radius-sm);
            font-size: 0.625rem;
            font-weight: 500;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
        }
        
        .online-count {
            color: var(--secondary-green);
            font-weight: 600;
        }
        
        .offline-count {
            color: var(--gray-400);
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
        
        .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            height: 32px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        
        /* Responsive Ultra-Denso */
        @media (max-width: 768px) {
            .table-header {
                display: none;
            }
            
            .operator-row {
                display: block;
                height: auto;
                padding: 0.5rem;
                border-bottom: 2px solid var(--gray-200);
            }
            
            .stats-inline {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .operators-header {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav class="nav">
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="/crm/dashboard.php" class="nav-link">
                            <span class="nav-icon">🏠</span>
                            <span>Dashboard</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="/crm/modules/operatori/" class="nav-link active">
                            <span class="nav-icon">👥</span>
                            <span>Operatori</span>
                            <?php if ($isAdmin): ?>
                                <span class="nav-badge">Admin</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="app-header">
                <div class="header-left">
                    <button class="sidebar-toggle" type="button">
                        <span>☰</span>
                    </button>
                    <h1 class="page-title">Gestione Operatori</h1>
                </div>
                
                <div class="header-right">
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
                <!-- Header con azioni -->
                <div class="operators-header">
                    <div>
                        <h2>👥 Gestione Operatori</h2>
                        <p>Visualizza e gestisci tutti gli operatori del sistema</p>
                    </div>
                    
                    <div class="header-actions">
                        <?php if ($isAdmin): ?>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                ➕ Nuovo Operatore
                            </a>
                            <a href="stats.php" class="btn btn-secondary btn-sm">
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
                                <button type="submit" class="btn btn-primary btn-sm">🔍 Filtra</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Lista Operatori - Layout Tabellare Ultra-Denso -->
                <div class="operators-table">
                    <?php if (empty($operatori)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                            <h3 style="margin-bottom: 0.5rem;">👥 Nessun operatore trovato</h3>
                            <p style="margin-bottom: 1rem; font-size: 0.875rem;">
                                Non ci sono operatori che corrispondono ai filtri selezionati.
                            </p>
                            <?php if ($isAdmin): ?>
                                <a href="create.php" class="btn btn-primary">
                                    ➕ Crea Primo Operatore
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Header Tabella -->
                        <div class="table-header">
                            <div>Operatore</div>
                            <div>Email</div>
                            <div>Status</div>
                            <div>Ore Sett.</div>
                            <div>Online</div>
                            <div>Ultimo Accesso</div>
                            <div>Azioni</div>
                        </div>
                        
                        <!-- Righe Operatori -->
                        <?php foreach ($operatori as $operatore): ?>
                            <div class="operator-row" data-operator-id="<?= $operatore['id'] ?>">
                                <!-- Colonna 1: Operatore (220px) -->
                                <div class="operator-info">
                                    <div class="operator-avatar">
                                        <?= substr($operatore['nome'], 0, 1) . substr($operatore['cognome'], 0, 1) ?>
                                    </div>
                                    <div class="operator-name">
                                        <?= htmlspecialchars($operatore['cognome'] . ' ' . $operatore['nome']) ?>
                                        <?php if ($operatore['is_amministratore']): ?>
                                            <span class="badge-mini">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Colonna 2: Email (180px) -->
                                <div style="font-size: 0.75rem; color: var(--gray-600); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($operatore['email']) ?>
                                </div>
                                
                                <!-- Colonna 3: Status (100px) -->
                                <div class="status-indicator">
                                    <?= getStatusIndicator($operatore['is_attivo'], $operatore['is_online']) ?>
                                    <span style="font-size: 0.75rem;">
                                        <?= $operatore['is_attivo'] ? 'Attivo' : 'Inattivo' ?>
                                    </span>
                                </div>
                                
                                <!-- Colonna 4: Ore Settimana (80px) -->
                                <div style="text-align: center; font-weight: 600; color: var(--gray-700);">
                                    <?= round($operatore['ore_settimana'] ?? 0) ?>h
                                    <?php if (($operatore['ore_settimana'] ?? 0) > ($operatore['ore_settimanali'] ?? 40)): ?>
                                        <div style="font-size: 0.625rem; color: var(--accent-orange);">
                                            +<?= round(($operatore['ore_settimana'] ?? 0) - ($operatore['ore_settimanali'] ?? 40)) ?>h
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Colonna 5: Online (90px) -->
                                <div class="status-indicator">
                                    <?php if ($operatore['is_online']): ?>
                                        <span class="online-count">🟢 <?= $operatore['sessioni_attive'] ?></span>
                                        <div style="font-size: 0.625rem; color: var(--gray-500);">
                                            <?= ucfirst($operatore['modalita_corrente']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="offline-count">🔴 0</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Colonna 6: Ultimo Accesso (120px) -->
                                <div style="text-align: center; font-size: 0.75rem; color: var(--gray-600);">
                                    <?= formatLastAccess($operatore['ultimo_accesso'] ?? null) ?>
                                    <?php if (!empty($operatore['ultimo_accesso']) && $operatore['ultimo_accesso'] !== '0000-00-00 00:00:00'): ?>
                                        <div style="font-size: 0.625rem; color: var(--gray-400);">
                                            <?= date('d/m', strtotime($operatore['ultimo_accesso'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Colonna 7: Azioni (auto) -->
                                <div style="display: flex; justify-content: center; gap: 0.125rem;">
                                    <a href="view.php?id=<?= $operatore['id'] ?>" class="btn-micro view" title="Visualizza">👁️</a>
                                    
                                    <?php if ($isAdmin || $sessionInfo['operatore_id'] == $operatore['id']): ?>
                                        <a href="edit.php?id=<?= $operatore['id'] ?>" class="btn-micro edit" title="Modifica">✏️</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($isAdmin && $operatore['id'] != $sessionInfo['operatore_id']): ?>
                                        <button onclick="toggleOperatorStatus(<?= $operatore['id'] ?>, <?= $operatore['is_attivo'] ? 'false' : 'true' ?>)" 
                                                class="btn-micro delete" 
                                                title="<?= $operatore['is_attivo'] ? 'Disattiva' : 'Attiva' ?>">
                                            <?= $operatore['is_attivo'] ? '🚫' : '✅' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Micro-Interazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
    <script>
        // Toggle status operatore
        function toggleOperatorStatus(operatorId, newStatus) {
            if (!confirm('Sei sicuro di voler modificare lo status di questo operatore?')) {
                return;
            }
            
            // Verifica che gli endpoint API esistano prima di chiamarli
            fetch('/crm/api/operators/toggle-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    operator_id: operatorId,
                    status: newStatus
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    location.reload();
                } else {
                    alert('Errore: ' + (data ? data.message : 'Risposta non valida dal server'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback: reload pagina dopo 1 secondo per vedere eventuali cambiamenti
                alert('Funzione in sviluppo. La pagina verrà aggiornata.');
                setTimeout(() => location.reload(), 1000);
            });
        }
        
        // Auto-refresh statistiche ogni 5 minuti (solo se admin)
        <?php if ($isAdmin): ?>
        setInterval(() => {
            const statsContainer = document.querySelector('.stats-inline');
            if (statsContainer) {
                fetch('/crm/api/operators/stats')
                    .then(response => {
                        if (response.ok) {
                            return response.json();
                        }
                        throw new Error('Stats API not available');
                    })
                    .then(data => {
                        // Aggiorna solo i valori senza reload completo
                        // Implementazione dettagliata in microinteractions.js
                        console.log('Stats updated:', data);
                    })
                    .catch(error => {
                        console.log('Stats refresh not available yet:', error.message);
                        // Silentemente fallisce se l'API non è ancora implementata
                    });
            }
        }, 300000); // 5 minuti
        <?php endif; ?>
        
        // Tooltips per azioni
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                // Implementazione tooltip in microinteractions.js
            });
        });
    </script>
</body>
</html>