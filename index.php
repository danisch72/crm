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
            showError('Azione non valida: ' . $action);
        }
        break;
}

// ================================================================
// FUNZIONI HELPER
// ================================================================

/**
 * Mostra errore generico
 */
function showError($message) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Errore - CRM Re.De Consulting</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f5f7f9;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            h1 {
                color: #dc2626;
                margin-bottom: 20px;
            }
            p {
                color: #666;
                margin-bottom: 30px;
            }
            a {
                color: #007849;
                text-decoration: none;
                font-weight: 600;
            }
            a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>Errore</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="<?= CRM_BASE_URL ?>">Torna alla Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}