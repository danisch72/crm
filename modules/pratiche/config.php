<?php
/**
 * modules/pratiche/config.php - Configurazione Modulo Pratiche
 * 
 * ✅ COSTANTI E CONFIGURAZIONI CENTRALIZZATE
 * 
 * Definisce:
 * - Tipi di pratiche disponibili
 * - Stati pratiche e task
 * - Configurazioni UI
 * - Regole business
 */

// Previeni accesso diretto
if (!defined('PRATICHE_ROUTER_LOADED') && !defined('CRM_INIT')) {
    die('Accesso diretto non consentito');
}

// ============================================
// TIPI PRATICA
// ============================================

define('PRATICHE_TYPES', [
    'dichiarazione_redditi' => [
        'label' => 'Dichiarazione dei Redditi',
        'icon' => '📊',
        'color' => '#3B82F6',
        'ore_default' => 10
    ],
    'dichiarazione_iva' => [
        'label' => 'Dichiarazione IVA',
        'icon' => '📋',
        'color' => '#10B981',
        'ore_default' => 8
    ],
    'bilancio_ordinario' => [
        'label' => 'Bilancio Ordinario',
        'icon' => '📈',
        'color' => '#8B5CF6',
        'ore_default' => 20
    ],
    'bilancio_semplificato' => [
        'label' => 'Bilancio Semplificato',
        'icon' => '📉',
        'color' => '#6366F1',
        'ore_default' => 12
    ],
    'costituzione_societa' => [
        'label' => 'Costituzione Società',
        'icon' => '🏢',
        'color' => '#EC4899',
        'ore_default' => 15
    ],
    'modifica_societaria' => [
        'label' => 'Modifica Societaria',
        'icon' => '🔧',
        'color' => '#F59E0B',
        'ore_default' => 8
    ],
    'pratiche_inps' => [
        'label' => 'Pratiche INPS',
        'icon' => '🏛️',
        'color' => '#14B8A6',
        'ore_default' => 5
    ],
    'pratiche_camera_commercio' => [
        'label' => 'Pratiche Camera Commercio',
        'icon' => '🏪',
        'color' => '#F97316',
        'ore_default' => 4
    ],
    'contrattualistica' => [
        'label' => 'Contrattualistica',
        'icon' => '📄',
        'color' => '#EF4444',
        'ore_default' => 6
    ],
    'consulenza_fiscale' => [
        'label' => 'Consulenza Fiscale',
        'icon' => '💼',
        'color' => '#7C3AED',
        'ore_default' => 3
    ],
    'consulenza_lavoro' => [
        'label' => 'Consulenza Lavoro',
        'icon' => '👥',
        'color' => '#06B6D4',
        'ore_default' => 4
    ],
    'altra' => [
        'label' => 'Altra Pratica',
        'icon' => '📎',
        'color' => '#6B7280',
        'ore_default' => 5
    ]
]);

// ============================================
// STATI PRATICA
// ============================================

define('PRATICHE_STATI', [
    'bozza' => [
        'label' => 'Bozza',
        'icon' => '📝',
        'color' => '#6B7280',
        'can_edit' => true,
        'can_delete' => true
    ],
    'da_iniziare' => [
        'label' => 'Da Iniziare',
        'icon' => '⏸️',
        'color' => '#F59E0B',
        'can_edit' => true,
        'can_delete' => true
    ],
    'in_corso' => [
        'label' => 'In Corso',
        'icon' => '🔄',
        'color' => '#3B82F6',
        'can_edit' => true,
        'can_delete' => false
    ],
    'in_attesa' => [
        'label' => 'In Attesa Cliente',
        'icon' => '⏳',
        'color' => '#8B5CF6',
        'can_edit' => true,
        'can_delete' => false
    ],
    'in_revisione' => [
        'label' => 'In Revisione',
        'icon' => '🔍',
        'color' => '#EC4899',
        'can_edit' => true,
        'can_delete' => false
    ],
    'completata' => [
        'label' => 'Completata',
        'icon' => '✅',
        'color' => '#10B981',
        'can_edit' => false,
        'can_delete' => false
    ],
    'fatturata' => [
        'label' => 'Fatturata',
        'icon' => '💰',
        'color' => '#059669',
        'can_edit' => false,
        'can_delete' => false
    ],
    'archiviata' => [
        'label' => 'Archiviata',
        'icon' => '📁',
        'color' => '#374151',
        'can_edit' => false,
        'can_delete' => false
    ]
]);

// ============================================
// STATI TASK
// ============================================

define('TASK_STATI', [
    'da_fare' => [
        'label' => 'Da Fare',
        'icon' => '⏸️',
        'color' => '#F59E0B',
        'progress' => 0
    ],
    'in_corso' => [
        'label' => 'In Corso',
        'icon' => '🔄',
        'color' => '#3B82F6',
        'progress' => 50
    ],
    'completato' => [
        'label' => 'Completato',
        'icon' => '✅',
        'color' => '#10B981',
        'progress' => 100
    ],
    'bloccato' => [
        'label' => 'Bloccato',
        'icon' => '🚫',
        'color' => '#DC2626',
        'progress' => 0
    ]
]);

// ============================================
// PRIORITÀ
// ============================================

define('PRATICHE_PRIORITA', [
    'bassa' => [
        'label' => 'Bassa',
        'icon' => '🟢',
        'color' => '#10B981',
        'giorni_extra' => 7
    ],
    'media' => [
        'label' => 'Media',
        'icon' => '🟡',
        'color' => '#F59E0B',
        'giorni_extra' => 0
    ],
    'alta' => [
        'label' => 'Alta',
        'icon' => '🟠',
        'color' => '#F97316',
        'giorni_extra' => -3
    ],
    'urgente' => [
        'label' => 'Urgente',
        'icon' => '🔴',
        'color' => '#DC2626',
        'giorni_extra' => -7
    ]
]);

// ============================================
// UI CONFIGURATION
// ============================================

define('PRATICHE_UI_CONFIG', [
    'items_per_page' => 20,
    'max_recent_items' => 5,
    'default_view' => 'kanban', // 'list' o 'kanban'
    'enable_drag_drop' => true,
    'show_archived' => false,
    'auto_archive_days' => 30,
    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i',
    'currency' => '€',
    'decimal_separator' => ',',
    'thousands_separator' => '.'
]);

// ============================================
// BUSINESS RULES
// ============================================

define('PRATICHE_BUSINESS_RULES', [
    // Validazioni
    'min_titolo_length' => 5,
    'max_titolo_length' => 255,
    'max_descrizione_length' => 5000,
    'max_ore_giornaliere' => 12,
    'max_ore_pratica' => 999,
    
    // Tariffe orarie
    'tariffe_orarie' => [
        'base' => 75.00,
        'qualificata' => 95.00,
        'specialistica' => 120.00
    ],
    
    // Limiti
    'max_task_per_pratica' => 50,
    'max_ore_giornaliere' => 10,
    'max_tracking_continuo' => 240, // minuti
    
    // Notifiche
    'alert_giorni_scadenza' => [30, 15, 7, 3, 1],
    'alert_ore_superate' => 0.9, // 90% delle ore stimate
    
    // Workflow
    'auto_complete_threshold' => 1.0, // 100% task completati
    'require_approval_for' => ['bilancio_ordinario', 'costituzione_societa'],
    
    // Template
    'template_active_days' => 365, // giorni validità template
]);

// ============================================
// TRACKING CONFIGURATION
// ============================================

define('TRACKING_CONFIG', [
    'auto_pause_after' => 1800, // 30 minuti inattività
    'min_session_duration' => 60, // 1 minuto minimo
    'allow_manual_entry' => true,
    'require_notes_for_long_sessions' => 14400, // 4 ore
    'interruption_types' => [
        'chiamata_cliente' => 'Chiamata Cliente',
        'chiamata_interna' => 'Chiamata Interna',
        'pausa_pranzo' => 'Pausa Pranzo',
        'emergenza' => 'Emergenza',
        'altro' => 'Altro'
    ]
]);

// ============================================
// PERMISSIONS
// ============================================

define('PRATICHE_PERMISSIONS', [
    'create' => ['admin', 'operatore'],
    'edit_own' => ['admin', 'operatore'],
    'edit_all' => ['admin'],
    'delete' => ['admin'],
    'view_all' => ['admin'],
    'manage_templates' => ['admin'],
    'export' => ['admin', 'operatore'],
    'reassign' => ['admin', 'operatore'],
    'approve' => ['admin']
]);

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Ottieni configurazione tipo pratica
 */
function getPraticaType($type) {
    return PRATICHE_TYPES[$type] ?? PRATICHE_TYPES['altra'];
}

/**
 * Ottieni configurazione stato pratica
 */
function getPraticaStato($stato) {
    return PRATICHE_STATI[$stato] ?? PRATICHE_STATI['bozza'];
}

/**
 * Ottieni configurazione stato task
 */
function getTaskStato($stato) {
    return TASK_STATI[$stato] ?? TASK_STATI['da_fare'];
}

/**
 * Verifica se utente ha permesso
 */
function userCanForPratiche($action, $user) {
    $allowedRoles = PRATICHE_PERMISSIONS[$action] ?? [];
    $userRole = $user['is_admin'] ? 'admin' : 'operatore';
    return in_array($userRole, $allowedRoles);
}

/**
 * Calcola scadenza in base a priorità
 */
function calcolaScadenza($dataInizio, $giorniBase, $priorita = 'media') {
    $config = PRATICHE_PRIORITA[$priorita] ?? PRATICHE_PRIORITA['media'];
    $giorniTotali = $giorniBase + $config['giorni_extra'];
    
    $scadenza = new DateTime($dataInizio);
    $scadenza->add(new DateInterval("P{$giorniTotali}D"));
    
    return $scadenza->format('Y-m-d');
}

/**
 * Genera numero pratica progressivo
 */
function generateNumeroPratica($anno = null) {
    global $db;
    
    if (!$anno) {
        $anno = date('Y');
    }
    
    // Ottieni ultimo numero per l'anno
    $result = $db->selectOne(
        "SELECT MAX(CAST(SUBSTRING_INDEX(numero_pratica, '/', -1) AS UNSIGNED)) as ultimo 
         FROM pratiche 
         WHERE YEAR(created_at) = ?",
        [$anno]
    );
    
    $prossimo = ($result['ultimo'] ?? 0) + 1;
    return sprintf("PR%d/%04d", $anno, $prossimo);
}

/**
 * Ottieni pratica con tutti i dati correlati
 */
function getPraticaCompleta($praticaId) {
    global $db;
    
    $pratica = $db->selectOne("
        SELECT 
            p.*,
            c.ragione_sociale as cliente_nome,
            c.email as cliente_email,
            c.telefono as cliente_telefono,
            CONCAT(o.nome, ' ', o.cognome) as operatore_nome,
            COUNT(DISTINCT t.id) as totale_task,
            COUNT(DISTINCT CASE WHEN t.stato = 'completato' THEN t.id END) as task_completati,
            COALESCE(SUM(t.ore_lavorate), 0) as ore_totali_lavorate
        FROM pratiche p
        LEFT JOIN clienti c ON p.cliente_id = c.id
        LEFT JOIN operatori o ON p.operatore_assegnato_id = o.id
        LEFT JOIN task t ON p.id = t.pratica_id
        WHERE p.id = ?
        GROUP BY p.id
    ", [$praticaId]);
    
    return $pratica;
}

/**
 * Ottieni informazioni scadenza
 */
function getScadenzaInfo($dataScadenza) {
    $oggi = new DateTime();
    $scadenza = new DateTime($dataScadenza);
    $diff = $oggi->diff($scadenza);
    $giorni = (int)$diff->format('%R%a');
    
    if ($giorni < 0) {
        return [
            'text' => abs($giorni) . ' giorni fa',
            'class' => 'text-danger',
            'icon' => '🔴',
            'is_scaduta' => true
        ];
    } elseif ($giorni == 0) {
        return [
            'text' => 'Oggi',
            'class' => 'text-warning',
            'icon' => '🟡',
            'is_scaduta' => false
        ];
    } elseif ($giorni <= 3) {
        return [
            'text' => 'tra ' . $giorni . ' giorni',
            'class' => 'text-warning',
            'icon' => '🟠',
            'is_scaduta' => false
        ];
    } elseif ($giorni <= 7) {
        return [
            'text' => 'tra ' . $giorni . ' giorni',
            'class' => 'text-info',
            'icon' => '🔵',
            'is_scaduta' => false
        ];
    } else {
        return [
            'text' => date('d/m/Y', strtotime($dataScadenza)),
            'class' => 'text-muted',
            'icon' => '🟢',
            'is_scaduta' => false
        ];
    }
}

// Fine config.php