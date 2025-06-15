<?php
/**
 * INDEX.PHP - Entry Point CRM Re.De Consulting
 * 
 * VERSIONE SEMPLIFICATA
 * - Usa nuovo sistema auth isolato
 * - Nessuna logica business nel routing
 * - Solo gestione moduli base
 */

// ================================================================
// INIZIALIZZAZIONE
// ================================================================
define('CRM_INIT', true);
define('CRM_ROOT', __DIR__);

// ================================================================
// CARICA SISTEMA AUTH
// ================================================================
require_once CRM_ROOT . '/auth/config.php';
require_once CRM_ROOT . '/auth/Auth.php';

// ================================================================
// VERIFICA AUTENTICAZIONE
// ================================================================
$auth = Auth::getInstance();

// Se non autenticato, redirect al login
if (!$auth->isAuthenticated()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// ================================================================
// CARICA COMPONENTI BASE
// ================================================================

// Database (se necessario per moduli)
if (file_exists(CRM_ROOT . '/core/classes/Database.php')) {
    require_once CRM_ROOT . '/core/classes/Database.php';
}

// Helpers (mantieni per compatibilità)
if (file_exists(CRM_ROOT . '/core/functions/helpers.php')) {
    require_once CRM_ROOT . '/core/functions/helpers.php';
}

// ================================================================
// ROUTING SEMPLIFICATO
// ================================================================

// Ottieni azione richiesta
$action = isset($_GET['action']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['action']) : 'dashboard';

// Array moduli disponibili (aggiungere nuovi moduli qui)
$availableModules = [
    'dashboard' => '/dashboard.php',
    'operatori' => '/modules/operatori/index.php',
    'clienti' => '/modules/clienti/index.php',
    'logout' => AUTH_ROOT . '/logout.php'
];

// Gestione routing
switch ($action) {
    case 'logout':
        // Redirect a logout nel sistema auth
        header('Location: ' . LOGOUT_URL);
        exit;
        break;
        
    case 'dashboard':
        // Dashboard principale
        $file = CRM_ROOT . '/dashboard.php';
        if (file_exists($file)) {
            include $file;
        } else {
            showError('Dashboard non trovata');
        }
        break;
        
    default:
        // Cerca tra i moduli disponibili
        if (isset($availableModules[$action])) {
            $file = CRM_ROOT . $availableModules[$action];
            if (file_exists($file)) {
                include $file;
            } else {
                showError('Modulo non trovato: ' . $action);
            }
        } else {
            // Azione non riconosciuta - redirect a dashboard
            header('Location: ?action=dashboard');
            exit;
        }
}

// ================================================================
// FUNZIONI UTILITY
// ================================================================

/**
 * Mostra pagina di errore
 */
function showError($message) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Errore - CRM Re.De</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f3f4f6;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .error-container {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 500px;
                text-align: center;
            }
            .error-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            h1 {
                color: #E60012;
                margin-bottom: 0.5rem;
            }
            p {
                color: #6b7280;
                margin-bottom: 1.5rem;
            }
            .btn {
                display: inline-block;
                padding: 0.75rem 1.5rem;
                background: #007849;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 500;
                transition: background 0.2s;
            }
            .btn:hover {
                background: #005a37;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Errore</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="?action=dashboard" class="btn">Torna alla Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>