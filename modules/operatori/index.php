<?php
/**
 * modules/operatori/index.php - Entry Point Modulo Operatori
 * 
 * ✅ WRAPPER PER ROUTER MODULO OPERATORI
 * 
 * Questo file agisce come punto di ingresso per il modulo operatori
 * e delega tutto il routing al router.php
 */

// Definisci che siamo nel CRM (per compatibilità con router)
if (!defined('CRM_INIT')) {
    define('CRM_INIT', true);
}

// Include il router che gestirà tutto
require_once __DIR__ . '/router.php';

// Il router si occuperà di:
// - Verificare autenticazione
// - Controllare permessi
// - Validare parametri
// - Caricare la view appropriata