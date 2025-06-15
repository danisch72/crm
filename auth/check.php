<?php
/**
 * CHECK AUTH API - Verifica Stato Autenticazione
 * CRM Re.De Consulting
 * 
 * Endpoint AJAX per verificare se utente è autenticato
 * Restituisce JSON con stato e info utente
 */

define('AUTH_INIT', true);
require_once 'config.php';
require_once 'Auth.php';

// Header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$auth = Auth::getInstance();

// Verifica autenticazione
if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    
    // Calcola tempo rimanente sessione
    $loginTime = $_SESSION['auth_login_time'] ?? time();
    $elapsed = time() - $loginTime;
    $remaining = AUTH_SESSION_LIFETIME - $elapsed;
    
    echo json_encode([
        'authenticated' => true,
        'user' => $user,
        'session' => [
            'remaining_seconds' => max(0, $remaining),
            'expires_at' => $loginTime + AUTH_SESSION_LIFETIME
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'message' => AUTH_MSG_NOT_AUTHENTICATED
    ]);
}
?>