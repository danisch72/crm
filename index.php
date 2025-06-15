<?php
/**
 * INDEX.PHP - Entry Point CRM Re.De Consulting
 * 
 * VERSIONE CON BOOTSTRAP
 * - Usa bootstrap.php come ponte
 * - Sistema auth completamente isolato
 * - Solo gestione routing moduli
 */

// ================================================================
// CARICA BOOTSTRAP (che gestisce tutto)
// ================================================================
require_once __DIR__ . '/core/bootstrap.php';

// Bootstrap ha già:
// - Verificato autenticazione
// - Caricato sistema auth
// - Caricato componenti opzionali
// - Definito funzioni utility

// ================================================================
// ROUTING SEMPLIFICATO
// ================================================================

// Ottieni azione richiesta
$action = isset($_GET['action']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['action']) : 'dashboard';

// Array moduli disponibili (aggiungere nuovi moduli qui)
$availableModules = [
    'dashboard' => 'modules/dashboard',       // Modulo dashboard
    'operatori' => 'modules/operatori',       // Modulo operatori
    'clienti' => 'modules/clienti',           // Modulo clienti
    // Aggiungere nuovi moduli qui
];

// Gestione routing
switch ($action) {
    case 'logout':
        // Include direttamente il file di logout
        require_once CRM_ROOT . '/auth/logout.php';
        break;
        
    default:
        // Cerca tra i moduli disponibili
        if (isset($availableModules[$action])) {
            // Tutti i moduli sono nella directory modules
            $moduleName = str_replace('modules/', '', $availableModules[$action]);
            if (!loadModule($moduleName)) {
                showError("Modulo '$action' non trovato");
            }
        } else {
            showError("Azione '$action' non valida");
        }
        break;
}

// Fine index.php
?>