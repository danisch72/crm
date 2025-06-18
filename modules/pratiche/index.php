<?php
/**
 * modules/pratiche/index.php - Entry Point Modulo Pratiche
 * 
 * ✅ ENTRY POINT STANDARD PER MODULO CRM
 * 
 * Questo file serve solo come punto di ingresso per il modulo.
 * Tutto il routing e la logica sono gestiti dal router.php
 */

// Definisci che siamo nel CRM per compatibilità
if (!defined('CRM_INIT')) {
    define('CRM_INIT', true);
}

// Include il router che gestisce tutto il modulo
require_once __DIR__ . '/router.php';

// Fine - il router si occupa di tutto il resto