<?php
/**
 * modules/pratiche/router.php - Router Modulo Pratiche CRM Re.De Consulting
 * 
 * ✅ GESTIONE CENTRALIZZATA ROUTING MODULO PRATICHE
 * 
 * Features:
 * - Routing interno per tutte le views del modulo
 * - Controllo accessi centralizzato
 * - Gestione parametri e validazione
 * - Compatibilità con sistema esistente
 */

// Include bootstrap per autenticazione
require_once dirname(dirname(__DIR__)) . '/core/bootstrap.php';

// Verifica autenticazione
if (!isAuthenticated()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Ottieni utente corrente
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Carica Database e helpers
loadDatabase();
loadHelpers();

// Carica configurazione modulo
require_once __DIR__ . '/config.php';

// Prepara sessionInfo per compatibilità
$sessionInfo = [
    'operatore_id' => $currentUser['id'],
    'user_id' => $currentUser['id'],
    'nome' => $currentUser['nome'],
    'cognome' => $currentUser['cognome'],
    'email' => $currentUser['email'],
    'nome_completo' => $currentUser['nome'] . ' ' . $currentUser['cognome'],
    'is_admin' => $currentUser['is_admin'],
    'is_amministratore' => $currentUser['is_admin']
];

// Istanza database
$db = Database::getInstance();

// Views disponibili nel modulo
$availableViews = [
    'index'         => 'index_list.php',      // Lista pratiche + Kanban
    'create'        => 'create.php',          // Nuova pratica
    'edit'          => 'edit.php',            // Modifica pratica
    'view'          => 'view.php',            // Dashboard pratica
    'task_manager'  => 'task_manager.php',    // Gestione task
    'tracking'      => 'tracking.php',        // Tracking temporale
    'templates'     => 'templates.php',       // Gestione template (admin)
    'workflow'      => 'workflow.php',        // Cambio stati pratica
];

// Ottieni view richiesta (default: index)
$requestedView = $_GET['view'] ?? 'index';

// Sanifica view
$requestedView = preg_replace('/[^a-z0-9_-]/i', '', $requestedView);

// Verifica se view esiste
if (!isset($availableViews[$requestedView])) {
    $requestedView = 'index';
}

// Views che richiedono permessi admin
$adminOnlyViews = ['templates'];

// Verifica permessi admin per views riservate
if (in_array($requestedView, $adminOnlyViews) && !$currentUser['is_admin']) {
    $_SESSION['error_message'] = '⚠️ Non hai i permessi per accedere a questa sezione';
    header('Location: /crm/?action=pratiche');
    exit;
}

// Per edit, view, task_manager, tracking verifica presenza ID pratica
if (in_array($requestedView, ['edit', 'view', 'task_manager', 'tracking'])) {
    $praticaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$praticaId) {
        $_SESSION['error_message'] = '⚠️ ID pratica mancante';
        header('Location: /crm/?action=pratiche');
        exit;
    }
    
    // Verifica esistenza pratica e permessi
    $pratica = $db->selectOne(
        "SELECT p.*, c.ragione_sociale as cliente_nome 
         FROM pratiche p 
         LEFT JOIN clienti c ON p.cliente_id = c.id 
         WHERE p.id = ?",
        [$praticaId]
    );
    
    if (!$pratica) {
        $_SESSION['error_message'] = '⚠️ Pratica non trovata';
        header('Location: /crm/?action=pratiche');
        exit;
    }
    
    // Verifica permessi (admin o operatore assegnato)
    if (!$currentUser['is_admin'] && $pratica['operatore_responsabile_id'] != $currentUser['id']) {
        $_SESSION['error_message'] = '⚠️ Non hai i permessi per questa pratica';
        header('Location: /crm/?action=pratiche');
        exit;
    }
    
    $_GET['id'] = $praticaId;
}

// Gestione messaggi flash
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
$warning_message = $_SESSION['warning_message'] ?? '';

// Pulisci messaggi dopo averli letti
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
unset($_SESSION['warning_message']);

// Flag per indicare che siamo passati dal router
define('PRATICHE_ROUTER_LOADED', true);

// Include la view
$viewFile = __DIR__ . '/' . $availableViews[$requestedView];

// Verifica esistenza file (doppia sicurezza)
if (!file_exists($viewFile)) {
    error_log("Router Pratiche: File non trovato: $viewFile");
    $_SESSION['error_message'] = '⚠️ Pagina non trovata';
    header('Location: /crm/?action=dashboard');
    exit;
}

// Log accesso (per audit trail)
if (function_exists('logModuleAccess')) {
    logModuleAccess('pratiche', $requestedView, $currentUser['id']);
}

// Include il file della view
try {
    require_once $viewFile;
} catch (Exception $e) {
    error_log("Errore caricamento view pratiche: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore nel caricamento della pagina';
    header('Location: /crm/?action=dashboard');
    exit;
}

// Fine router.php