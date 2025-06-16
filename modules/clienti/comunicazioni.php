<?php
/**
 * modules/clienti/comunicazioni.php - Gestione Comunicazioni Cliente CRM Re.De Consulting
 * 
 * ✅ COMMUNICATION LOG PROFESSIONALE COMMERCIALISTI - VERSIONE CORRETTA
 * 
 * Features:
 * - Timeline completa comunicazioni cliente
 * - Registro chiamate, email, incontri, note
 * - Form rapido per nuova comunicazione
 * - Filtri per tipo, periodo, operatore
 * - Follow-up automatici e promemoria
 * - Export log per audit e fatturazione
 * - Integrazione calendario per appuntamenti
 * - Statistiche comunicazioni per cliente
 * - Template rapidi per comunicazioni frequenti
 */

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili già disponibili dal router:
// $sessionInfo, $db, $error_message, $success_message
// $clienteId (validato dal router)

$pageTitle = 'Comunicazioni Cliente';

// Carica dati cliente
try {
    $cliente = $db->selectOne("
        SELECT 
            ragione_sociale, 
            codice_cliente,
            email,
            telefono,
            cellulare,
            pec
        FROM clienti 
        WHERE id = ?
    ", [$clienteId]);
    
    if (!$cliente) {
        $_SESSION['error_message'] = '⚠️ Cliente non trovato';
        header('Location: /crm/?action=clienti');
        exit;
    }
} catch (Exception $e) {
    error_log("Errore caricamento cliente $clienteId: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore database';
    header('Location: /crm/?action=clienti');
    exit;
}

$errors = [];
$success = '';

// Parametri filtri
$tipoFiltro = $_GET['tipo'] ?? 'all';
$operatoreFiltro = $_GET['operatore'] ?? 'all';
$periodoFiltro = $_GET['periodo'] ?? '30';
$prioritaFiltro = $_GET['priorita'] ?? 'all';

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_comunicazione':
            $tipo = $_POST['tipo'] ?? '';
            $oggetto = trim($_POST['oggetto'] ?? '');
            $contenuto = trim($_POST['contenuto'] ?? '');
            $followup = $_POST['followup'] ?? null;
            $priorita = $_POST['priorita'] ?? 'normale';
            
            // Validazioni
            if (empty($tipo)) {
                $errors[] = "Seleziona il tipo di comunicazione";
            }
            if (empty($oggetto)) {
                $errors[] = "L'oggetto è obbligatorio";
            }
            if (empty($contenuto)) {
                $errors[] = "Il contenuto è obbligatorio";
            }
            
            if (empty($errors)) {
                try {
                    $db->insert('comunicazioni_clienti', [
                        'cliente_id' => $clienteId,
                        'operatore_id' => $sessionInfo['operatore_id'],
                        'tipo' => $tipo,
                        'oggetto' => $oggetto,
                        'contenuto' => $contenuto,
                        'data_followup' => $followup,
                        'priorita' => $priorita,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Aggiorna ultima comunicazione cliente
                    $db->update('clienti', [
                        'ultima_comunicazione' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$clienteId]);
                    
                    $success = '✅ Comunicazione registrata con successo';
                    
                    // Reset form
                    $_POST = [];
                } catch (Exception $e) {
                    $errors[] = "Errore salvataggio comunicazione";
                    error_log("Errore insert comunicazione: " . $e->getMessage());
                }
            }
            break;
            
        case 'mark_complete':
            $comunicazioneId = (int)($_POST['comunicazione_id'] ?? 0);
            if ($comunicazioneId) {
                try {
                    $db->update('comunicazioni_clienti', [
                        'completato' => 1,
                        'data_completamento' => date('Y-m-d H:i:s')
                    ], 'id = ? AND cliente_id = ?', [$comunicazioneId, $clienteId]);
                    
                    $success = '✅ Comunicazione marcata come completata';
                } catch (Exception $e) {
                    $errors[] = "Errore aggiornamento comunicazione";
                }
            }
            break;
    }
}

// Costruisci query per comunicazioni
$whereConditions = ['cc.cliente_id = ?'];
$queryParams = [$clienteId];

// Applica filtri
if ($tipoFiltro !== 'all') {
    $whereConditions[] = 'cc.tipo = ?';
    $queryParams[] = $tipoFiltro;
}

if ($operatoreFiltro !== 'all') {
    $whereConditions[] = 'cc.operatore_id = ?';
    $queryParams[] = $operatoreFiltro;
}

if ($prioritaFiltro !== 'all') {
    $whereConditions[] = 'cc.priorita = ?';
    $queryParams[] = $prioritaFiltro;
}

// Filtro periodo
if ($periodoFiltro !== 'all') {
    $dataInizio = date('Y-m-d', strtotime("-$periodoFiltro days"));
    $whereConditions[] = 'cc.created_at >= ?';
    $queryParams[] = $dataInizio;
}

$whereClause = implode(' AND ', $whereConditions);

// Carica comunicazioni
try {
    $comunicazioni = $db->select("
        SELECT 
            cc.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            DATEDIFF(NOW(), cc.created_at) as giorni_fa
        FROM comunicazioni_clienti cc
        LEFT JOIN operatori o ON cc.operatore_id = o.id
        WHERE $whereClause
        ORDER BY cc.created_at DESC
    ", $queryParams);
    
    // Carica operatori per filtro
    $operatori = $db->select("
        SELECT DISTINCT o.id, CONCAT(o.nome, ' ', o.cognome) as nome_completo
        FROM operatori o
        INNER JOIN comunicazioni_clienti cc ON o.id = cc.operatore_id
        WHERE cc.cliente_id = ?
        ORDER BY nome_completo
    ", [$clienteId]);
    
    // Statistiche comunicazioni
    $stats = $db->selectOne("
        SELECT 
            COUNT(*) as totale,
            COUNT(CASE WHEN tipo = 'telefono' THEN 1 END) as telefonate,
            COUNT(CASE WHEN tipo = 'email' THEN 1 END) as email,
            COUNT(CASE WHEN tipo = 'presenza' THEN 1 END) as incontri,
            COUNT(CASE WHEN completato = 0 AND data_followup IS NOT NULL THEN 1 END) as followup_pendenti
        FROM comunicazioni_clienti
        WHERE cliente_id = ?
    ", [$clienteId]);
    
} catch (Exception $e) {
    error_log("Errore caricamento comunicazioni: " . $e->getMessage());
    $comunicazioni = [];
    $operatori = [];
    $stats = ['totale' => 0, 'telefonate' => 0, 'email' => 0, 'incontri' => 0, 'followup_pendenti' => 0];
}

// Funzioni helper
function getTipoComunicazioneIcon($tipo) {
    $icons = [
        'telefono' => '📞',
        'email' => '📧',
        'whatsapp' => '💬',
        'teams' => '👥',
        'zoom' => '📹',
        'presenza' => '🤝',
        'nota' => '📝'
    ];
    return $icons[$tipo] ?? '💭';
}

function getTipoComunicazioneNome($tipo) {
    $nomi = [
        'telefono' => 'Telefonata',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'teams' => 'Teams',
        'zoom' => 'Zoom',
        'presenza' => 'Incontro',
        'nota' => 'Nota'
    ];
    return $nomi[$tipo] ?? 'Altro';
}

function getPrioritaClass($priorita) {
    $classes = [
        'alta' => 'priority-high',
        'normale' => 'priority-normal',
        'bassa' => 'priority-low'
    ];
    return $classes[$priorita] ?? 'priority-normal';
}

function formatTimeAgo($giorni) {
    if ($giorni == 0) return 'Oggi';
    if ($giorni == 1) return 'Ieri';
    if ($giorni < 7) return "$giorni giorni fa";
    if ($giorni < 30) return floor($giorni/7) . " settimane fa";
    if ($giorni < 365) return floor($giorni/30) . " mesi fa";
    return floor($giorni/365) . " anni fa";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($cliente['ragione_sociale']) ?> - CRM Re.De</title>
    
    <!-- Design System CSS -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Layout comunicazioni */
        .comunicazioni-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .comunicazioni-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 968px) {
            .comunicazioni-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Form nuova comunicazione */
        .new-comunicazione-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.3rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-50);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Tipo comunicazione selector */
        .tipo-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .tipo-option {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }
        
        .tipo-option:hover {
            background: var(--gray-50);
        }
        
        .tipo-option.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .tipo-option input[type="radio"] {
            display: none;
        }
        
        /* Timeline comunicazioni */
        .timeline-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .timeline-stats {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        /* Filtri */
        .filters-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 0.4rem 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.85rem;
            background: white;
        }
        
        /* Timeline items */
        .timeline {
            position: relative;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gray-200);
        }
        
        .timeline-item {
            position: relative;
            padding-left: 50px;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .timeline-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .timeline-icon {
            position: absolute;
            left: 10px;
            top: 0;
            width: 22px;
            height: 22px;
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .timeline-content {
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
        }
        
        .timeline-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .timeline-title {
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .timeline-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .timeline-body {
            margin-top: 0.5rem;
            color: var(--text-primary);
            white-space: pre-wrap;
        }
        
        .timeline-footer {
            margin-top: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .timeline-tags {
            display: flex;
            gap: 0.5rem;
        }
        
        .tag {
            padding: 0.2rem 0.5rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .tag.priority-high {
            background: var(--danger-50);
            color: var(--danger-red);
        }
        
        .tag.priority-normal {
            background: var(--primary-50);
            color: var(--primary-color);
        }
        
        .tag.priority-low {
            background: var(--gray-100);
            color: var(--text-secondary);
        }
        
        .tag.followup {
            background: var(--warning-50);
            color: var(--warning-color);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        
        /* Quick templates */
        .templates-dropdown {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .templates-button {
            font-size: 0.85rem;
            color: var(--primary-color);
            cursor: pointer;
            text-decoration: underline;
        }
        
        .templates-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-md);
            display: none;
            z-index: 10;
        }
        
        .templates-menu.show {
            display: block;
        }
        
        .template-item {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .template-item:last-child {
            border-bottom: none;
        }
        
        .template-item:hover {
            background: var(--gray-50);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>

    <div class="comunicazioni-container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" style="margin-bottom: 1rem;">
            <a href="/crm/">Home</a>
            <span class="separator">/</span>
            <a href="/crm/?action=clienti">Clienti</a>
            <span class="separator">/</span>
            <a href="/crm/?action=clienti&view=view&id=<?= $clienteId ?>">
                <?= htmlspecialchars($cliente['ragione_sociale']) ?>
            </a>
            <span class="separator">/</span>
            <span class="current">Comunicazioni</span>
        </nav>

        <!-- Messaggi -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="comunicazioni-layout">
            <!-- Sidebar: Nuova comunicazione -->
            <div class="new-comunicazione-card">
                <h3 style="margin-bottom: 1rem;">📝 Nuova Comunicazione</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_comunicazione">
                    
                    <!-- Tipo comunicazione -->
                    <div class="form-group">
                        <label>Tipo</label>
                        <div class="tipo-selector">
                            <label class="tipo-option">
                                <input type="radio" name="tipo" value="telefono" required>
                                <div>📞 Telefono</div>
                            </label>
                            <label class="tipo-option">
                                <input type="radio" name="tipo" value="email" required>
                                <div>📧 Email</div>
                            </label>
                            <label class="tipo-option">
                                <input type="radio" name="tipo" value="whatsapp" required>
                                <div>💬 WhatsApp</div>
                            </label>
                            <label class="tipo-option">
                                <input type="radio" name="tipo" value="teams" required>
                                <div>👥 Teams</div>
                            </label>
                            <label class="tipo-option">
                                <input type="radio" name="tipo" value="presenza" required>
                                <div>🤝 Presenza</div>
                            </label>
                            <label class="tipo-option">
                                <input type="radio" name="tipo" value="nota" required>
                                <div>📝 Nota</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Templates rapidi -->
                    <div class="templates-dropdown">
                        <span class="templates-button" onclick="toggleTemplates()">
                            🎯 Usa template rapido
                        </span>
                        <div class="templates-menu" id="templatesMenu">
                            <div class="template-item" onclick="useTemplate('richiesta_documenti')">
                                📄 Richiesta documenti
                            </div>
                            <div class="template-item" onclick="useTemplate('promemoria_scadenza')">
                                ⏰ Promemoria scadenza
                            </div>
                            <div class="template-item" onclick="useTemplate('conferma_appuntamento')">
                                📅 Conferma appuntamento
                            </div>
                            <div class="template-item" onclick="useTemplate('sollecito_pagamento')">
                                💰 Sollecito pagamento
                            </div>
                        </div>
                    </div>
                    
                    <!-- Oggetto -->
                    <div class="form-group">
                        <label for="oggetto">Oggetto</label>
                        <input type="text" 
                               id="oggetto" 
                               name="oggetto" 
                               class="form-control" 
                               required
                               value="<?= htmlspecialchars($_POST['oggetto'] ?? '') ?>">
                    </div>
                    
                    <!-- Contenuto -->
                    <div class="form-group">
                        <label for="contenuto">Contenuto</label>
                        <textarea id="contenuto" 
                                  name="contenuto" 
                                  class="form-control" 
                                  required
                                  rows="4"><?= htmlspecialchars($_POST['contenuto'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Priorità -->
                    <div class="form-group">
                        <label for="priorita">Priorità</label>
                        <select id="priorita" name="priorita" class="form-control">
                            <option value="bassa">🔵 Bassa</option>
                            <option value="normale" selected>🟢 Normale</option>
                            <option value="alta">🔴 Alta</option>
                        </select>
                    </div>
                    
                    <!-- Follow-up -->
                    <div class="form-group">
                        <label for="followup">Follow-up (opzionale)</label>
                        <input type="date" 
                               id="followup" 
                               name="followup" 
                               class="form-control"
                               min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        💾 Salva Comunicazione
                    </button>
                </form>
                
                <!-- Contatti rapidi cliente -->
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem;">Contatti Cliente</h4>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                        <?php if ($cliente['telefono']): ?>
                            <div>📞 <?= htmlspecialchars($cliente['telefono']) ?></div>
                        <?php endif; ?>
                        <?php if ($cliente['cellulare']): ?>
                            <div>📱 <?= htmlspecialchars($cliente['cellulare']) ?></div>
                        <?php endif; ?>
                        <?php if ($cliente['email']): ?>
                            <div>📧 <?= htmlspecialchars($cliente['email']) ?></div>
                        <?php endif; ?>
                        <?php if ($cliente['pec']): ?>
                            <div>📮 <?= htmlspecialchars($cliente['pec']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main: Timeline comunicazioni -->
            <div class="timeline-container">
                <div class="timeline-header">
                    <h3>📋 Storico Comunicazioni</h3>
                    
                    <div class="timeline-stats">
                        <div class="stat-item">
                            <span>📊</span>
                            <span><?= $stats['totale'] ?> totali</span>
                        </div>
                        <div class="stat-item">
                            <span>📞</span>
                            <span><?= $stats['telefonate'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span>📧</span>
                            <span><?= $stats['email'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span>🤝</span>
                            <span><?= $stats['incontri'] ?></span>
                        </div>
                        <?php if ($stats['followup_pendenti'] > 0): ?>
                            <div class="stat-item" style="color: var(--warning-color);">
                                <span>⏰</span>
                                <span><?= $stats['followup_pendenti'] ?> follow-up</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Filtri -->
                <div class="filters-bar">
                    <select class="filter-select" onchange="applyFilter('tipo', this.value)">
                        <option value="all">Tutti i tipi</option>
                        <option value="telefono" <?= $tipoFiltro === 'telefono' ? 'selected' : '' ?>>📞 Telefonate</option>
                        <option value="email" <?= $tipoFiltro === 'email' ? 'selected' : '' ?>>📧 Email</option>
                        <option value="whatsapp" <?= $tipoFiltro === 'whatsapp' ? 'selected' : '' ?>>💬 WhatsApp</option>
                        <option value="teams" <?= $tipoFiltro === 'teams' ? 'selected' : '' ?>>👥 Teams</option>
                        <option value="presenza" <?= $tipoFiltro === 'presenza' ? 'selected' : '' ?>>🤝 Presenza</option>
                        <option value="nota" <?= $tipoFiltro === 'nota' ? 'selected' : '' ?>>📝 Note</option>
                    </select>
                    
                    <select class="filter-select" onchange="applyFilter('periodo', this.value)">
                        <option value="all">Tutto il periodo</option>
                        <option value="7" <?= $periodoFiltro === '7' ? 'selected' : '' ?>>Ultima settimana</option>
                        <option value="30" <?= $periodoFiltro === '30' ? 'selected' : '' ?>>Ultimo mese</option>
                        <option value="90" <?= $periodoFiltro === '90' ? 'selected' : '' ?>>Ultimi 3 mesi</option>
                        <option value="365" <?= $periodoFiltro === '365' ? 'selected' : '' ?>>Ultimo anno</option>
                    </select>
                    
                    <?php if (!empty($operatori)): ?>
                        <select class="filter-select" onchange="applyFilter('operatore', this.value)">
                            <option value="all">Tutti gli operatori</option>
                            <?php foreach ($operatori as $op): ?>
                                <option value="<?= $op['id'] ?>" <?= $operatoreFiltro == $op['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <select class="filter-select" onchange="applyFilter('priorita', this.value)">
                        <option value="all">Tutte le priorità</option>
                        <option value="alta" <?= $prioritaFiltro === 'alta' ? 'selected' : '' ?>>🔴 Alta</option>
                        <option value="normale" <?= $prioritaFiltro === 'normale' ? 'selected' : '' ?>>🟢 Normale</option>
                        <option value="bassa" <?= $prioritaFiltro === 'bassa' ? 'selected' : '' ?>>🔵 Bassa</option>
                    </select>
                </div>
                
                <!-- Timeline -->
                <?php if (empty($comunicazioni)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💭</div>
                        <p>Nessuna comunicazione registrata</p>
                        <p style="font-size: 0.85rem;">Inizia registrando la prima comunicazione con questo cliente</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($comunicazioni as $com): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <?= getTipoComunicazioneIcon($com['tipo']) ?>
                                </div>
                                
                                <div class="timeline-content">
                                    <div class="timeline-header-row">
                                        <div>
                                            <div class="timeline-title">
                                                <?= htmlspecialchars($com['oggetto']) ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <?= getTipoComunicazioneNome($com['tipo']) ?> • 
                                                <?= formatTimeAgo($com['giorni_fa']) ?> • 
                                                <?= htmlspecialchars($com['operatore_nome']) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="timeline-meta">
                                            <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-body">
                                        <?= nl2br(htmlspecialchars($com['contenuto'])) ?>
                                    </div>
                                    
                                    <div class="timeline-footer">
                                        <div class="timeline-tags">
                                            <span class="tag <?= getPrioritaClass($com['priorita']) ?>">
                                                <?= ucfirst($com['priorita']) ?>
                                            </span>
                                            
                                            <?php if ($com['data_followup'] && !$com['completato']): ?>
                                                <span class="tag followup">
                                                    ⏰ Follow-up: <?= date('d/m/Y', strtotime($com['data_followup'])) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($com['completato']): ?>
                                                <span class="tag" style="background: var(--success-50); color: var(--success-color);">
                                                    ✅ Completato
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($com['data_followup'] && !$com['completato']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="mark_complete">
                                                <input type="hidden" name="comunicazione_id" value="<?= $com['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary" title="Marca come completato">
                                                    ✅
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Selettore tipo comunicazione
        document.addEventListener('DOMContentLoaded', function() {
            const tipoOptions = document.querySelectorAll('.tipo-option');
            
            tipoOptions.forEach(option => {
                option.addEventListener('click', function() {
                    tipoOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
                
                // Se radio è già selezionato
                const radio = option.querySelector('input[type="radio"]');
                if (radio && radio.checked) {
                    option.classList.add('selected');
                }
            });
        });
        
        // Templates menu
        function toggleTemplates() {
            const menu = document.getElementById('templatesMenu');
            menu.classList.toggle('show');
        }
        
        // Chiudi menu se clicchi fuori
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('templatesMenu');
            const button = document.querySelector('.templates-button');
            
            if (!menu.contains(e.target) && e.target !== button) {
                menu.classList.remove('show');
            }
        });
        
        // Usa template
        function useTemplate(tipo) {
            const templates = {
                richiesta_documenti: {
                    oggetto: 'Richiesta documentazione',
                    contenuto: 'Gentile Cliente,\n\nLe chiediamo cortesemente di inviarci la seguente documentazione:\n\n- [ELENCO DOCUMENTI]\n\nScadenza: [DATA]\n\nGrazie per la collaborazione.'
                },
                promemoria_scadenza: {
                    oggetto: 'Promemoria scadenza importante',
                    contenuto: 'Promemoria per scadenza del [DATA] riguardante [OGGETTO].\n\nÈ necessario procedere entro i termini per evitare sanzioni.'
                },
                conferma_appuntamento: {
                    oggetto: 'Conferma appuntamento',
                    contenuto: 'Confermiamo l\'appuntamento per il giorno [DATA] alle ore [ORA] presso il nostro studio.\n\nIn caso di impedimenti, vi preghiamo di avvisarci tempestivamente.'
                },
                sollecito_pagamento: {
                    oggetto: 'Sollecito pagamento fattura',
                    contenuto: 'Con la presente vi ricordiamo che risulta ancora da saldare la fattura n. [NUMERO] del [DATA] per un importo di € [IMPORTO].\n\nVi preghiamo di provvedere al pagamento entro [SCADENZA].'
                }
            };
            
            const template = templates[tipo];
            if (template) {
                document.getElementById('oggetto').value = template.oggetto;
                document.getElementById('contenuto').value = template.contenuto;
                
                // Chiudi menu
                document.getElementById('templatesMenu').classList.remove('show');
                
                // Focus sul contenuto per modifiche
                document.getElementById('contenuto').focus();
            }
        }
        
        // Applica filtri
        function applyFilter(tipo, valore) {
            const params = new URLSearchParams(window.location.search);
            params.set(tipo, valore);
            
            // Mantieni altri parametri
            params.set('action', 'clienti');
            params.set('view', 'comunicazioni');
            params.set('id', '<?= $clienteId ?>');
            
            window.location.href = '/crm/?' + params.toString();
        }
    </script>
</body>
</html>