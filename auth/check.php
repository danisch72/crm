<?php
/**
 * CHECK.PHP - API Verifica Autenticazione
 * CRM Re.De Consulting
 * 
 * Endpoint AJAX per verificare stato autenticazione
 * Ritorna JSON con stato e info utente
 */

define('AUTH_INIT', true);
require_once 'config.php';
require_once 'Auth.php';

// Imposta header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Ottieni istanza Auth
$auth = Auth::getInstance();

// Prepara risposta
$response = [
    'authenticated' => false,
    'user' => null,
    'session_remaining' => 0
];

// Verifica autenticazione
if ($auth->isAuthenticated()) {
    $response['authenticated'] = true;
    $response['user'] = $auth->getCurrentUser();
    
    // Calcola tempo rimanente sessione
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        $remaining = AUTH_SESSION_LIFETIME - $elapsed;
        $response['session_remaining'] = max(0, $remaining);
    }
    
    // Aggiorna ultima attività
    $_SESSION['last_activity'] = time();
}

// Output JSON
echo json_encode($response, JSON_PRETTY_PRINT);