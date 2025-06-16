<?php
/**
 * modules/operatori/router.php - Router Modulo Operatori CRM Re.De Consulting
 * 
 * ✅ GESTIONE CENTRALIZZATA ROUTING MODULO OPERATORI
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
    'index'  => 'index_list.php',
    'create' => 'create.php',
    'edit'   => 'edit.php',
    'view'   => 'view.php',
    'stats'  => 'stats.php',
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
$adminOnlyViews = ['create', 'stats'];

// Verifica permessi admin per views riservate
if (in_array($requestedView, $adminOnlyViews) && !$currentUser['is_admin']) {
    $_SESSION['error_message'] = '⚠️ Non hai i permessi per accedere a questa sezione';
    header('Location: /crm/?action=operatori');
    exit;
}

// Per edit e view, verifica presenza ID
if (in_array($requestedView, ['edit', 'view'])) {
    $operatoreId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$operatoreId) {
        $_SESSION['error_message'] = '⚠️ ID operatore mancante';
        header('Location: /crm/?action=operatori');
        exit;
    }
    
    $_GET['id'] = $operatoreId;
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
define('OPERATORI_ROUTER_LOADED', true);

// Include la view
$viewFile = __DIR__ . '/' . $availableViews[$requestedView];

if (file_exists($viewFile)) {
    require_once $viewFile;
} else {
    error_log("Router Operatori: File non trovato: $viewFile");
    $_SESSION['error_message'] = '⚠️ Pagina non trovata';
    header('Location: /crm/?action=dashboard');
    exit;
}