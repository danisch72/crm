<?php
/**
 * modules/clienti/index_list.php - Lista Clienti CRM Re.De Consulting
 * 
 * ✅ VERSIONE CON SIDEBAR E HEADER CENTRALIZZATI
 * ✅ LAYOUT ULTRA-DENSO CONFORME AL DESIGN SYSTEM DATEV v2.0
 * 
 * Features:
 * - Sidebar e header componenti centralizzati
 * - Layout tabellare 7-colonne enterprise-grade
 * - Design system Datev Koinos compliant
 * - Business logic commercialisti integrata
 */

// Avvia sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica che siamo passati dal router
if (!defined('CLIENTI_ROUTER_LOADED')) {
    header('Location: /crm/?action=clienti');
    exit;
}

// Variabili per i componenti
$pageTitle = 'Gestione Clienti';
$pageIcon = '🏢';

// Verifica permessi amministratore per alcune azioni
$isAdmin = $sessionInfo['is_admin'];

// Gestione filtri
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$tipologia = $_GET['tipologia'] ?? 'all';
$operatore = $_GET['operatore'] ?? 'all';

// Carica lista operatori per filtro
$operatori = [];
try {
    $operatori = $db->select("
        SELECT id, CONCAT(nome, ' ', cognome) as nome_completo
        FROM operatori
        WHERE is_attivo = 1
        ORDER BY cognome, nome
    ");
} catch (Exception $e) {
    error_log("Errore caricamento operatori: " . $e->getMessage());
}

// Query clienti con filtri
$whereConditions = ["1=1"];
$params = [];

if ($search) {
    $whereConditions[] = "(
        ragione_sociale LIKE ? OR 
        codice_fiscale LIKE ? OR 
        partita_iva LIKE ? OR
        email LIKE ? OR
        telefono LIKE ?
    )";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($status !== 'all') {
    $whereConditions[] = "stato = ?";
    $params[] = $status;
}

if ($tipologia !== 'all') {
    $whereConditions[] = "tipologia_azienda = ?";
    $params[] = $tipologia;
}

if ($operatore !== 'all' && is_numeric($operatore)) {
    $whereConditions[] = "operatore_responsabile_id = ?";
    $params[] = $operatore;
}

$whereClause = implode(' AND ', $whereConditions);

// Carica clienti
try {
    $clienti = $db->select("
        SELECT 
            c.*,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            (SELECT COUNT(*) FROM pratiche WHERE cliente_id = c.id) as totale_pratiche,
            (SELECT COUNT(*) FROM pratiche WHERE cliente_id = c.id AND stato = 'attiva') as pratiche_attive,
            (SELECT MAX(created_at) FROM comunicazioni_clienti WHERE cliente_id = c.id) as ultima_comunicazione
        FROM clienti c
        LEFT JOIN operatori o ON c.operatore_responsabile_id = o.id
        WHERE $whereClause
        ORDER BY c.ragione_sociale ASC
    ", $params);
} catch (Exception $e) {
    error_log("Errore caricamento clienti: " . $e->getMessage());
    $clienti = [];
}

// Funzioni helper
function getTipologiaIcon($tipologia) {
    $icons = [
        'individuale' => '👤',
        'srl' => '🏢',
        'spa' => '🏛️',
        'snc' => '🤝',
        'sas' => '🏪'
    ];
    return $icons[$tipologia] ?? '🏢';
}

function getStatusIcon($status) {
    return $status === 'attivo' ? '✅' : '⚠️';
}

function formatDataContatto($data) {
    if (!$data) return 'Mai';
    $date = new DateTime($data);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) return 'Oggi';
    if ($diff->days == 1) return 'Ieri';
    if ($diff->days < 7) return $diff->days . ' giorni fa';
    if ($diff->days < 30) return floor($diff->days / 7) . ' settimane fa';
    if ($diff->days < 365) return floor($diff->days / 30) . ' mesi fa';
    return 'Oltre un anno fa';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - CRM Re.De</title>
    
    <!-- CSS nell'ordine corretto -->
    <link rel="stylesheet" href="/crm/assets/css/design-system.css">
    <link rel="stylesheet" href="/crm/assets/css/datev-style.css">
    <link rel="stylesheet" href="/crm/assets/css/clienti.css">
    
    <style>
        /* Container principale con padding professionale */
        .clienti-container {
            padding: 1.5rem;
            background: #f9fafb;
            min-height: calc(100vh - 64px);
        }
        
        .filters-section {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .filters-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .form-input-compact {
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            min-width: 140px;
            background: #ffffff;
            transition: all 0.2s;
        }
        
        .form-input-compact:focus {
            outline: none;
            border-color: #007849;
            box-shadow: 0 0 0 3px rgba(0, 120, 73, 0.1);
        }
        
        .btn-primary-compact, .btn-secondary-compact {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            white-space: nowrap;
        }
        
        .btn-primary-compact {
            background: #007849;
            color: white;
        }
        
        .btn-primary-compact:hover {
            background: #005a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 120, 73, 0.2);
        }
        
        .btn-secondary-compact {
            background: #ffffff;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        
        .btn-secondary-compact:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        /* Tabella professionale con layout ottimizzato */
        .clienti-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 
                minmax(250px, 2fr)  /* Cliente */
                minmax(200px, 1.5fr) /* Contatti */
                120px               /* Tipologia */
                100px               /* Stato */
                120px               /* Pratiche */
                minmax(150px, 1fr)  /* Operatore */
                140px;              /* Azioni */
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .cliente-row {
            display: grid;
            grid-template-columns: 
                minmax(250px, 2fr)
                minmax(200px, 1.5fr)
                120px
                100px
                120px
                minmax(150px, 1fr)
                140px;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
            transition: all 0.2s;
            font-size: 0.8125rem;
        }
        
        .cliente-row:hover {
            background: #f9fafb;
        }
        
        /* Info cliente compatte */
        .cliente-info {
            min-width: 0; /* Permette text truncation */
        }
        
        .cliente-nome {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        
        .cliente-dettagli {
            font-size: 0.75rem;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Contatti ben organizzati */
        .contatti-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.75rem;
            min-width: 0;
        }
        
        .contatti-info > div {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Stati e badge professionali */
        .stato-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            white-space: nowrap;
        }
        
        .stato-attivo {
            background: #d1fae5;
            color: #065f46;
        }
        
        .stato-sospeso {
            background: #fed7aa;
            color: #92400e;
        }
        
        .stato-chiuso {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .tipologia-badge {
            background: #f3f4f6;
            color: #1f2937;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-align: center;
        }
        
        /* Operatore badge */
        .operatore-badge {
            background: #e0f2fe;
            color: #075985;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        
        /* Pratiche summary */
        .pratiche-summary {
            text-align: center;
        }
        
        .pratiche-summary > div:first-child {
            font-weight: 600;
            color: #1f2937;
        }
        
        /* Azioni row professionali */
        .row-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
        }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            white-space: nowrap;
        }
        
        .btn-action:hover {
            border-color: #007849;
            color: #007849;
            background: #f0fdf4;
        }
    </style>
</head>
<body>
    <!-- ✅ COMPONENTE SIDEBAR (OBBLIGATORIO) -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/navigation.php'; ?>
    
    <!-- ✅ COMPONENTE HEADER (OBBLIGATORIO) -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/crm/components/header.php'; ?>
    
    <!-- Content Wrapper con padding top per header -->
    <div class="content-wrapper">
            
            <main class="main-content">
                <div class="clienti-container">
                    <!-- Messaggi -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?= $success_message ?></div>
                    <?php endif; ?>
                    
                    <!-- Filtri -->
                    <div class="filters-section">
                        <form method="GET" class="filters-form">
                            <input type="hidden" name="action" value="clienti">
                            
                            <input type="text" 
                                   name="search" 
                                   placeholder="🔍 Cerca cliente..." 
                                   value="<?= htmlspecialchars($search) ?>"
                                   class="form-input-compact" 
                                   style="flex: 1; max-width: 300px;">
                            
                            <select name="status" class="form-input-compact">
                                <option value="all">Tutti gli stati</option>
                                <option value="attivo" <?= $status === 'attivo' ? 'selected' : '' ?>>✅ Attivi</option>
                                <option value="sospeso" <?= $status === 'sospeso' ? 'selected' : '' ?>>⚠️ Sospesi</option>
                                <option value="chiuso" <?= $status === 'chiuso' ? 'selected' : '' ?>>🔴 Chiusi</option>
                            </select>
                            
                            <select name="tipologia" class="form-input-compact">
                                <option value="all">Tutte le tipologie</option>
                                <option value="individuale" <?= $tipologia === 'individuale' ? 'selected' : '' ?>>👤 Individuale</option>
                                <option value="srl" <?= $tipologia === 'srl' ? 'selected' : '' ?>>🏢 SRL</option>
                                <option value="spa" <?= $tipologia === 'spa' ? 'selected' : '' ?>>🏭 SPA</option>
                                <option value="snc" <?= $tipologia === 'snc' ? 'selected' : '' ?>>👥 SNC</option>
                                <option value="sas" <?= $tipologia === 'sas' ? 'selected' : '' ?>>🤝 SAS</option>
                            </select>
                            
                            <select name="operatore" class="form-input-compact">
                                <option value="all">Tutti gli operatori</option>
                                <?php foreach ($operatori as $op): ?>
                                    <option value="<?= $op['id'] ?>" <?= $operatore == $op['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($op['nome_completo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="btn-primary-compact">Filtra</button>
                            <a href="/crm/?action=clienti" class="btn-secondary-compact">Reset</a>
                            
                            <?php if ($isAdmin): ?>
                                <a href="/crm/?action=clienti&view=create" class="btn-primary-compact" style="margin-left: auto;">
                                    ➕ Nuovo Cliente
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Tabella Clienti -->
                    <div class="clienti-table">
                        <div class="table-header">
                            <div>Cliente</div>
                            <div>Contatti</div>
                            <div>Tipologia</div>
                            <div>Stato</div>
                            <div>Pratiche</div>
                            <div>Operatore</div>
                            <div>Azioni</div>
                        </div>

                        <?php if (empty($clienti)): ?>
                            <div style="padding: 2rem; text-align: center; color: var(--gray-500);">
                                <p>🔍 Nessun cliente trovato con i filtri attuali</p>
                                <?php if ($isAdmin): ?>
                                    <a href="/crm/?action=clienti&view=create" class="btn-primary-compact" style="margin-top: 1rem;">
                                        ➕ Aggiungi il primo cliente
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($clienti as $cliente): ?>
                                <div class="cliente-row" data-cliente-id="<?= $cliente['id'] ?>">
                                    <!-- Cliente Info -->
                                    <div class="cliente-info">
                                        <div class="cliente-nome">
                                            <?= getTipologiaIcon($cliente['tipologia_azienda']) ?>
                                            <?= htmlspecialchars($cliente['ragione_sociale']) ?>
                                        </div>
                                        <div class="cliente-dettagli">
                                            <?php if ($cliente['codice_fiscale']): ?>
                                                CF: <?= htmlspecialchars($cliente['codice_fiscale']) ?>
                                            <?php endif; ?>
                                            <?php if ($cliente['partita_iva']): ?>
                                                | P.IVA: <?= htmlspecialchars($cliente['partita_iva']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Contatti -->
                                    <div class="contatti-info">
                                        <?php if ($cliente['email']): ?>
                                            <div>📧 <?= htmlspecialchars($cliente['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($cliente['telefono']): ?>
                                            <div>📞 <?= htmlspecialchars($cliente['telefono']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($cliente['ultima_comunicazione']): ?>
                                            <div style="color: var(--gray-500); font-size: 0.7rem;">
                                                💬 <?= formatDataContatto($cliente['ultima_comunicazione']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Tipologia -->
                                    <div style="text-align: center;">
                                        <span class="tipologia-badge">
                                            <?= strtoupper($cliente['tipologia_azienda'] ?? 'N/A') ?>
                                        </span>
                                    </div>

                                    <!-- Stato -->
                                    <div>
                                        <span class="stato-badge stato-<?= $cliente['stato'] ?>">
                                            <?= getStatusIcon($cliente['stato']) ?> 
                                            <?= ucfirst($cliente['stato']) ?>
                                        </span>
                                    </div>

                                    <!-- Pratiche -->
                                    <div class="pratiche-summary">
                                        <div><strong><?= $cliente['totale_pratiche'] ?? 0 ?></strong> totali</div>
                                        <?php if ($cliente['pratiche_attive'] > 0): ?>
                                            <div style="color: var(--color-success); font-size: 0.75rem;">
                                                <?= $cliente['pratiche_attive'] ?> attive
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Operatore -->
                                    <div>
                                        <?php if ($cliente['operatore_nome']): ?>
                                            <span class="operatore-badge">
                                                <?= htmlspecialchars($cliente['operatore_nome']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--gray-400); font-size: 0.75rem;">
                                                Non assegnato
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Azioni -->
                                    <div class="row-actions">
                                        <a href="/crm/?action=clienti&view=view&id=<?= $cliente['id'] ?>" 
                                           class="btn-action" 
                                           title="Visualizza">
                                            👁️ Vedi
                                        </a>
                                        
                                        <?php if ($isAdmin || $cliente['operatore_responsabile_id'] == $sessionInfo['operatore_id']): ?>
                                            <a href="/crm/?action=clienti&view=edit&id=<?= $cliente['id'] ?>" 
                                               class="btn-action" 
                                               title="Modifica">
                                                ✏️ Modifica
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="/crm/?action=clienti&view=documenti&id=<?= $cliente['id'] ?>" 
                                           class="btn-action" 
                                           title="Documenti">
                                            📁 Docs
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Script microinterazioni -->
    <script src="/crm/assets/js/microinteractions.js"></script>
</body>
</html>