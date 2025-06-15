<?php
/**
 * modules/clienti/config.php - Configurazione Modulo Clienti CRM Re.De Consulting
 * 
 * ✅ CONFIGURAZIONE CENTRALIZZATA MODULO CLIENTI
 * 
 * Features:
 * - Configurazioni specifiche del modulo
 * - Validazioni business rules commercialisti
 * - Impostazioni upload e sicurezza
 * - Templates e automazioni
 * - Integrazioni esterne
 * - Personalizzazioni per studio
 */

// Previeni accesso diretto
if (!defined('CRM_INIT')) {
    die('Accesso non autorizzato');
}

// ============================================
// INFORMAZIONI MODULO
// ============================================

const CLIENTI_MODULE_CONFIG = [
    'name' => 'Gestione Clienti',
    'version' => '1.0.0',
    'description' => 'Modulo completo per la gestione del portfolio clienti dello studio commercialista',
    'author' => 'CRM Re.De Consulting',
    'dependencies' => ['operatori'],
    'database_version' => '1.0',
    'last_update' => '2025-06-14',
    
    // Permessi modulo
    'permissions' => [
        'clienti.view' => 'Visualizzazione clienti',
        'clienti.create' => 'Creazione nuovi clienti',
        'clienti.edit' => 'Modifica dati clienti',
        'clienti.delete' => 'Eliminazione clienti',
        'clienti.export' => 'Export dati clienti',
        'clienti.stats' => 'Statistiche portfolio (solo admin)',
        'clienti.documents' => 'Gestione documenti clienti',
        'clienti.communications' => 'Gestione comunicazioni',
        'clienti.assign' => 'Assegnazione operatori responsabili'
    ]
];

// ============================================
// CONFIGURAZIONI BUSINESS RULES
// ============================================

const CLIENTI_BUSINESS_RULES = [
    // Validazioni obbligatorie
    'required_fields' => [
        'ragione_sociale',
        'tipologia_azienda'
    ],
    
    // Validazioni condizionali
    'conditional_required' => [
        'partita_iva' => ['tipologia_azienda' => ['srl', 'spa', 'snc', 'sas']],
        'codice_fiscale' => ['tipologia_azienda' => ['individuale']]
    ],
    
    // Pattern validazione
    'validation_patterns' => [
        'codice_fiscale_pf' => '/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', // 16 caratteri
        'codice_fiscale_pg' => '/^[0-9]{11}$/', // 11 caratteri
        'partita_iva' => '/^[0-9]{11}$/', // 11 cifre
        'cap' => '/^[0-9]{5}$/', // 5 cifre
        'telefono' => '/^[\+]?[0-9\s\-\(\)\.]{6,20}$/',
        'email' => FILTER_VALIDATE_EMAIL,
        'codice_ateco' => '/^\d{2}\.\d{2}\.\d{2}$/' // XX.XX.XX
    ],
    
    // Limiti di sistema
    'limits' => [
        'max_clienti_per_operatore' => 200,
        'max_documenti_per_cliente' => 500,
        'max_comunicazioni_per_giorno' => 50,
        'max_note_length' => 5000
    ],
    
    // Auto-generazione codici
    'auto_codes' => [
        'enabled' => true,
        'format' => '{TYPE}{YEAR}{NUMBER:4}', // AZ2025001, PF2025001
        'prefixes' => [
            'persona_fisica' => 'PF',
            'societa' => 'AZ',
            'ditta_individuale' => 'DI',
            'associazione' => 'AS'
        ]
    ]
];

// ============================================
// CONFIGURAZIONI DOCUMENTI
// ============================================

const CLIENTI_DOCUMENTS_CONFIG = [
    // Upload settings
    'upload' => [
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain'
        ],
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'],
        'upload_path' => '/crm/uploads/clienti/',
        'organize_by_year' => true,
        'virus_scan' => false, // Implementare se disponibile ClamAV
        'auto_backup' => true
    ],
    
    // Categorizzazione automatica
    'auto_categorization' => [
        'enabled' => true,
        'patterns' => [
            'contratto' => ['contratto', 'accordo', 'convenzione', 'mandato'],
            'documento_identita' => ['carta_identita', 'passaporto', 'patente', 'codice_fiscale'],
            'certificato' => ['certificato', 'visura', 'camerale', 'atti_costitutivi'],
            'fattura' => ['fattura', 'ricevuta', 'scontrino', 'parcella'],
            'bilancio' => ['bilancio', 'conto_economico', 'stato_patrimoniale', 'nota_integrativa'],
            'dichiarazione' => ['dichiarazione', 'modello', 'unico', '730', 'iva', 'irap'],
            'visura' => ['visura', 'camerale', 'ipotecaria', 'catastale']
        ]
    ],
    
    // Retention policy
    'retention' => [
        'enabled' => true,
        'keep_years' => 10, // Obbligo fiscale
        'archive_after_years' => 7,
        'auto_cleanup' => false // Manuale per sicurezza
    ]
];

// ============================================
// CONFIGURAZIONI COMUNICAZIONI
// ============================================

const CLIENTI_COMMUNICATIONS_CONFIG = [
    // Canali disponibili
    'channels' => [
        'telefono' => ['icon' => '📞', 'color' => '#1e40af'],
        'email' => ['icon' => '📧', 'color' => '#059669'],
        'whatsapp' => ['icon' => '💬', 'color' => '#25d366'],
        'teams' => ['icon' => '👥', 'color' => '#6264a7'],
        'zoom' => ['icon' => '📹', 'color' => '#2d8cff'],
        'presenza' => ['icon' => '🤝', 'color' => '#d97706']
    ],
    
    // Template comunicazioni
    'templates' => [
        'richiesta_documenti' => [
            'titolo' => 'Richiesta documentazione',
            'contenuto' => "Gentile Cliente,\n\nLe chiediamo cortesemente di inviarci la seguente documentazione:\n\n- [ELENCO DOCUMENTI]\n\nScadenza: [DATA]\n\nGrazie per la collaborazione."
        ],
        'promemoria_scadenza' => [
            'titolo' => 'Promemoria scadenza importante',
            'contenuto' => "Promemoria per scadenza del [DATA] riguardante [OGGETTO].\n\nÈ necessario procedere entro i termini per evitare sanzioni."
        ],
        'conferma_appuntamento' => [
            'titolo' => 'Conferma appuntamento',
            'contenuto' => "Confermiamo l'appuntamento per il giorno [DATA] alle ore [ORA] presso il nostro studio.\n\nIn caso di impedimenti, vi preghiamo di avvisarci tempestivamente."
        ]
    ],
    
    // Follow-up automatici
    'auto_followup' => [
        'enabled' => true,
        'default_days' => 7,
        'reminder_days' => [1, 3, 7], // Giorni prima della scadenza
        'escalation_levels' => ['operatore', 'responsabile', 'admin']
    ],
    
    // Statistiche
    'stats_retention_days' => 365
];

// ============================================
// CONFIGURAZIONI EXPORT
// ============================================

const CLIENTI_EXPORT_CONFIG = [
    // Formati supportati
    'formats' => [
        'excel' => [
            'enabled' => true,
            'extension' => 'xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ],
        'csv' => [
            'enabled' => true,
            'extension' => 'csv',
            'mime_type' => 'text/csv',
            'delimiter' => ';',
            'encoding' => 'UTF-8'
        ],
        'pdf' => [
            'enabled' => true,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf'
        ]
    ],
    
    // Template export
    'templates' => [
        'fiscale' => [
            'name' => 'Dati Fiscali',
            'description' => 'Export ottimizzato per dichiarazioni fiscali',
            'fields' => [
                'codice_cliente', 'ragione_sociale', 'codice_fiscale', 'partita_iva',
                'tipologia_azienda', 'regime_fiscale', 'liquidazione_iva',
                'indirizzo', 'cap', 'citta', 'provincia'
            ]
        ],
        'contatti' => [
            'name' => 'Lista Contatti',
            'description' => 'Export per mailing e comunicazioni',
            'fields' => [
                'ragione_sociale', 'email', 'pec', 'telefono', 'cellulare',
                'operatore_responsabile_nome', 'operatore_email'
            ]
        ],
        'completo' => [
            'name' => 'Export Completo',
            'description' => 'Tutti i dati disponibili',
            'fields' => ['*']
        ]
    ],
    
    // Limiti
    'limits' => [
        'max_records_per_export' => 5000,
        'max_exports_per_day' => 50,
        'file_retention_days' => 7
    ]
];

// ============================================
// CONFIGURAZIONI INTEGRAZIONE
// ============================================

const CLIENTI_INTEGRATION_CONFIG = [
    // API esterne
    'external_apis' => [
        'agenzia_entrate' => [
            'enabled' => false, // Da attivare quando disponibile
            'endpoint' => 'https://www1.agenziaentrate.gov.it/ws/',
            'verify_partita_iva' => true,
            'verify_codice_fiscale' => true
        ],
        'infocamere' => [
            'enabled' => false,
            'endpoint' => 'https://www.registroimprese.it/api/',
            'verify_company_data' => true
        ]
    ],
    
    // Synchronization
    'sync' => [
        'auto_backup' => [
            'enabled' => true,
            'frequency' => 'daily',
            'retention_days' => 30
        ],
        'external_crm' => [
            'enabled' => false,
            'format' => 'json',
            'endpoint' => null
        ]
    ],
    
    // Webhook
    'webhooks' => [
        'enabled' => false,
        'events' => [
            'cliente.created',
            'cliente.updated',
            'cliente.deleted',
            'documento.uploaded',
            'comunicazione.added'
        ]
    ]
];

// ============================================
// CONFIGURAZIONI UI/UX
// ============================================

const CLIENTI_UI_CONFIG = [
    // Layout
    'layout' => [
        'items_per_page' => 25,
        'compact_mode' => true,
        'grid_columns' => 7,
        'enable_infinite_scroll' => false
    ],
    
    // Search
    'search' => [
        'min_chars' => 2,
        'max_results' => 50,
        'enable_fuzzy' => true,
        'autocomplete_delay' => 300
    ],
    
    // Filters
    'filters' => [
        'remember_state' => true,
        'quick_filters' => [
            'attivi_mese' => 'Attivi ultimo mese',
            'nuovi_trimestre' => 'Nuovi ultimo trimestre',
            'non_assegnati' => 'Non assegnati',
            'con_pratiche_attive' => 'Con pratiche attive'
        ]
    ],
    
    // Colors e Icons
    'theme' => [
        'primary_color' => '#2c6e49',
        'secondary_color' => '#4a9d6f',
        'accent_color' => '#1e40af',
        'success_color' => '#059669',
        'warning_color' => '#d97706',
        'danger_color' => '#dc2626'
    ]
];

// ============================================
// CONFIGURAZIONI NOTIFICHE
// ============================================

const CLIENTI_NOTIFICATIONS_CONFIG = [
    // Alert automatici
    'alerts' => [
        'documenti_scadenza' => [
            'enabled' => true,
            'days_before' => [30, 15, 7, 1],
            'recipients' => ['operatore_responsabile', 'admin']
        ],
        'comunicazioni_mancanti' => [
            'enabled' => true,
            'max_days_silence' => 90,
            'check_frequency' => 'weekly'
        ],
        'followup_scaduti' => [
            'enabled' => true,
            'check_frequency' => 'daily',
            'escalation_after_days' => 3
        ]
    ],
    
    // Email templates
    'email_templates' => [
        'welcome_new_client' => [
            'subject' => 'Benvenuto in Re.De Consulting',
            'enabled' => false // Da personalizzare
        ],
        'document_request' => [
            'subject' => 'Richiesta documentazione - {RAGIONE_SOCIALE}',
            'enabled' => true
        ]
    ]
];

// ============================================
// FUNZIONI CONFIGURAZIONE
// ============================================

/**
 * Carica configurazione modulo
 */
function getClientiConfig($section = null) {
    $config = [
        'module' => CLIENTI_MODULE_CONFIG,
        'business_rules' => CLIENTI_BUSINESS_RULES,
        'documents' => CLIENTI_DOCUMENTS_CONFIG,
        'communications' => CLIENTI_COMMUNICATIONS_CONFIG,
        'export' => CLIENTI_EXPORT_CONFIG,
        'integration' => CLIENTI_INTEGRATION_CONFIG,
        'ui' => CLIENTI_UI_CONFIG,
        'notifications' => CLIENTI_NOTIFICATIONS_CONFIG
    ];
    
    return $section ? ($config[$section] ?? null) : $config;
}

/**
 * Valida configurazione modulo
 */
function validateClientiConfig() {
    $errors = [];
    
    // Verifica directory upload
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . CLIENTI_DOCUMENTS_CONFIG['upload']['upload_path'];
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $errors[] = "Impossibile creare directory upload: $uploadDir";
        }
    }
    
    if (!is_writable($uploadDir)) {
        $errors[] = "Directory upload non scrivibile: $uploadDir";
    }
    
    // Verifica estensioni PHP necessarie
    $requiredExtensions = ['pdo', 'pdo_mysql', 'fileinfo', 'gd'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Estensione PHP mancante: $ext";
        }
    }
    
    // Verifica limiti PHP
    $uploadMaxSize = ini_get('upload_max_filesize');
    $configMaxSize = CLIENTI_DOCUMENTS_CONFIG['upload']['max_file_size'];
    if (parse_size($uploadMaxSize) < $configMaxSize) {
        $errors[] = "upload_max_filesize PHP troppo basso per configurazione modulo";
    }
    
    return $errors;
}

/**
 * Converte stringa dimensione in bytes
 */
function parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    }
    
    return round($size);
}

/**
 * Ottieni versione modulo
 */
function getClientiModuleVersion() {
    return CLIENTI_MODULE_CONFIG['version'];
}

/**
 * Verifica compatibilità database
 */
function checkClientiDatabaseCompatibility() {
    global $db;
    
    try {
        // Verifica esistenza tabelle principali
        $requiredTables = ['clienti', 'documenti_clienti', 'note_clienti'];
        $existingTables = [];
        
        $result = $db->select("SHOW TABLES LIKE 'clienti%'");
        foreach ($result as $row) {
            $existingTables[] = array_values($row)[0];
        }
        
        $missingTables = array_diff($requiredTables, $existingTables);
        
        if (!empty($missingTables)) {
            return [
                'compatible' => false,
                'missing_tables' => $missingTables,
                'message' => 'Database non compatibile - tabelle mancanti'
            ];
        }
        
        return [
            'compatible' => true,
            'message' => 'Database compatibile'
        ];
        
    } catch (Exception $e) {
        return [
            'compatible' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore verifica compatibilità database'
        ];
    }
}

// ============================================
// INIZIALIZZAZIONE MODULO
// ============================================

// Registra autoloader se necessario
if (!function_exists('clienti_autoload')) {
    function clienti_autoload($className) {
        $prefix = 'Clienti\\';
        $baseDir = __DIR__ . '/classes/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $className, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($className, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
    
    spl_autoload_register('clienti_autoload');
}

// Log inizializzazione modulo
if (function_exists('error_log')) {
    error_log("Modulo Clienti v" . CLIENTI_MODULE_CONFIG['version'] . " inizializzato");
}

// ============================================
// EXPORT CONFIGURAZIONI PER JAVASCRIPT
// ============================================

/**
 * Esporta configurazioni per frontend JavaScript
 */
function getClientiConfigForJS() {
    return [
        'upload' => [
            'maxFileSize' => CLIENTI_DOCUMENTS_CONFIG['upload']['max_file_size'],
            'allowedTypes' => CLIENTI_DOCUMENTS_CONFIG['upload']['allowed_extensions']
        ],
        'ui' => [
            'itemsPerPage' => CLIENTI_UI_CONFIG['layout']['items_per_page'],
            'searchMinChars' => CLIENTI_UI_CONFIG['search']['min_chars'],
            'autocompleteDelay' => CLIENTI_UI_CONFIG['search']['autocomplete_delay']
        ],
        'validation' => [
            'patterns' => CLIENTI_BUSINESS_RULES['validation_patterns']
        ]
    ];
}

// Fine configurazione modulo clienti
?>