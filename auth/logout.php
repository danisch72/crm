<?php
/**
 * LOGOUT.PHP - Gestione Logout
 * CRM Re.De Consulting
 * 
 * Disconnette l'utente e redirect al login
 */

define('AUTH_INIT', true);
require_once 'config.php';
require_once 'Auth.php';

// Ottieni istanza Auth
$auth = Auth::getInstance();

// Esegui logout
$auth->logout();

// Redirect al login con messaggio
header('Location: ' . LOGIN_URL . '?logout=1');
exit;