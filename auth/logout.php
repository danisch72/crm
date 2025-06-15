<?php
/**
 * LOGOUT - Disconnessione Utente
 * CRM Re.De Consulting
 * 
 * Gestisce il logout e redirect al login
 */

define('AUTH_INIT', true);
require_once 'config.php';
require_once 'Auth.php';

$auth = Auth::getInstance();

// Esegui logout
$result = $auth->logout();

// Redirect al login con messaggio
if ($result['success']) {
    header('Location: ' . LOGIN_URL . '?logout=1');
} else {
    header('Location: ' . LOGIN_URL . '?error=logout_failed');
}
exit;
?>