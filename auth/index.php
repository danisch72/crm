<?php
/**
 * AUTH INDEX - Entry Point Sistema Autenticazione
 * CRM Re.De Consulting
 * 
 * Redirect automatico al login
 */

define('AUTH_INIT', true);
require_once 'config.php';

// Redirect al login
header('Location: ' . LOGIN_URL);
exit;
?>