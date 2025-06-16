<?php
/**
 * modules/clienti/router.php - Router Modulo Clienti CRM Re.De Consulting
 * 
 * ✅ VERSIONE CORRETTA SENZA DIPENDENZA DA AuthSystem.php
 */

// Previeni accesso diretto al router
if (!defined('CRM_INIT')) {
    define('CRM_INIT', true);
    $_GET['view'] = 'index';
}

// Include bootstrap per autenticazione e setup
require_once dirname(dirname(__DIR__)) . '/core/bootstrap.php';

// Bootstrap ha già verificato l'autenticazione
// Ottieni info utente corrente usando le funzioni del bootstrap
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// ================================================================
// CONFIGURAZIONE ROUTING
// ================================================================

// Views disponibili nel modulo
$availableViews = [
    'index'         => 'index_list.php',    // Lista clienti (rinominato)
    'create'        => 'create.php',        // Creazione nuovo cliente
    'edit'          => 'edit.php',          // Modifica cliente
    'view'          => 'view.php',          // Visualizzazione dettagli
    'documenti'     => 'documenti.php',     // Gestione documenti
    'comunicazioni' => 'comunicazioni.php', // Gestione comunicazioni
    'config'        => 'config.php',        // Configurazioni (solo admin)
];

// Views che richiedono permessi admin
$adminOnlyViews = ['config'];

// Views che richiedono parametro ID
$viewsRequiringId = ['edit', 'view', 'documenti', 'comunicazioni'];

// ================================================================
// GESTIONE REQUEST
// ================================================================

// Ottieni view richiesta (default: index)
$requestedView = $_GET['view'] ?? 'index';

// Sanifica view
$requestedView = preg_replace('/[^a-z0-9_-]/i', '', $requestedView);

// Verifica se view esiste
if (!isset($availableViews[$requestedView])) {
    $requestedView = 'index';
}

// ================================================================
// CONTROLLO PERMESSI
// ================================================================

// Verifica permessi admin per views riservate
if (in_array($requestedView, $adminOnlyViews) && !$currentUser['is_admin']) {
    $_SESSION['error_message'] = '⚠️ Non hai i permessi per accedere a questa sezione';
    header('Location: /crm/?action=clienti');
    exit;
}

// ================================================================
// VALIDAZIONE PARAMETRI
// ================================================================

// Per edit, view, documenti e comunicazioni, verifica presenza ID
if (in_array($requestedView, $viewsRequiringId)) {
    $clienteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$clienteId) {
        $_SESSION['error_message'] = '⚠️ ID cliente mancante';
        header('Location: /crm/?action=clienti');
        exit;
    }
    
    // Passaggio ID al file incluso
    $_GET['id'] = $clienteId;
}

// ================================================================
// PREPARAZIONE AMBIENTE
// ================================================================

// Prepara sessionInfo nel formato che i file si aspettano
$sessionInfo = [
    'operatore_id' => $currentUser['id'],
    'user_id' => $currentUser['id'], // alias per compatibilità
    'nome' => $currentUser['nome'],
    'cognome' => $currentUser['cognome'],
    'email' => $currentUser['email'],
    'nome_completo' => $currentUser['nome'] . ' ' . $currentUser['cognome'],
    'is_admin' => $currentUser['is_admin'],
    'is_amministratore' => $currentUser['is_admin'] // alias
];

// Definisci costanti utili per i file inclusi
define('CLIENTI_MODULE_PATH', __DIR__);
define('CLIENTI_MODULE_URL', '/crm/modules/clienti');

// ================================================================
// CARICA CONFIG SE NECESSARIO
// ================================================================

// Carica sempre il file di configurazione del modulo
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile) && $requestedView !== 'config') {
    // Il config richiede che CRM_INIT sia definito
    require_once $configFile;
}

// ================================================================
// GESTIONE MESSAGGI FLASH
// ================================================================

// Recupera messaggi dalla sessione
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
$warning_message = $_SESSION['warning_message'] ?? '';
$info_message = $_SESSION['info_message'] ?? '';

// Pulisci messaggi dopo averli letti
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
unset($_SESSION['warning_message']);
unset($_SESSION['info_message']);

// ================================================================
// CARICAMENTO DATABASE
// ================================================================

// Carica classe Database dal bootstrap
loadDatabase();
$db = Database::getInstance();

// ================================================================
// CARICAMENTO VIEW
// ================================================================

// Path completo al file da includere
$viewFile = __DIR__ . '/' . $availableViews[$requestedView];

// Verifica esistenza file (doppia sicurezza)
if (!file_exists($viewFile)) {
    error_log("Router Clienti: File non trovato: $viewFile");
    $_SESSION['error_message'] = '⚠️ Pagina non trovata';
    header('Location: /crm/?action=dashboard');
    exit;
}

// ================================================================
// LOG ACCESSO (per audit trail)
// ================================================================

if (function_exists('logModuleAccess')) {
    logModuleAccess('clienti', $requestedView, $currentUser['id']);
}

// ================================================================
// INCLUDE VIEW
// ================================================================

// Definisci flag per indicare che siamo passati dal router
define('CLIENTI_ROUTER_LOADED', true);

// Include il file della view
try {
    require_once $viewFile;
} catch (Exception $e) {
    error_log("Errore caricamento view clienti: " . $e->getMessage());
    $_SESSION['error_message'] = '⚠️ Errore nel caricamento della pagina';
    header('Location: /crm/?action=dashboard');
    exit;
}

// Fine router.php