<?php
/**
 * Configurazione Sistema Audit WCAG 2.2
 * Conforme all'European Accessibility Act
 */

// Configurazione Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'includo');
define('DB_USER', 'root');  // Update with your credentials  // Modifica con le tue credenziali
define('DB_PASS', '');  // Update with your credentials  // Modifica con le tue credenziali
define('DB_CHARSET', 'utf8mb4');

// Base path for web assets and links. If Includo is served from a subfolder,
// this will contain the folder path (with trailing slash), e.g. "/includo/".
// Can be overridden in config.local.php.
if (!defined('INCLUDO_BASE_PATH')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '' || $dir === '.') {
        $dir = '';
    } else {
        $dir = $dir . '/';
    }
    define('INCLUDO_BASE_PATH', $dir);
}

// Configurazioni Audit
define('DEFAULT_MAX_PAGES', 50);
define('MAX_PAGES_LIMIT', 500);
define('REQUEST_TIMEOUT', 30);
define('MAX_REDIRECTS', 5);
define('USER_AGENT', 'Includo WCAG 2.2 Auditor (EU 2019/882 EAA)');

// Configurazioni WCAG
define('WCAG_VERSION', '2.2');
define('CONTRAST_RATIO_AA', 4.5);
define('CONTRAST_RATIO_AAA', 7.0);
define('LARGE_TEXT_THRESHOLD', 18); // pt
define('LARGE_TEXT_BOLD_THRESHOLD', 14); // pt

// Configurazioni Performance
define('MAX_EXECUTION_TIME', 3600); // 1 ora
define('MEMORY_LIMIT', '512M');
define('MAX_CONCURRENT_REQUESTS', 3);

// Configurazioni Report
define('REPORTS_DIR', __DIR__ . '/reports/');
define('ENABLE_PDF_EXPORT', true);
define('ENABLE_EMAIL_REPORTS', false);
define('ADMIN_EMAIL', 'admin@tuodominio.com');

// Configurazioni Sicurezza
define('ALLOWED_PROTOCOLS', ['http', 'https']);
define('BLOCKED_DOMAINS', [
    'localhost',
    '127.0.0.1', 
    '192.168.',
    '10.',
    '172.'
]); // Domini da bloccare per sicurezza

// Configurazioni Logging
define('ENABLE_LOGGING', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/logs/audit.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// Configurazioni Cache
define('ENABLE_CACHE', true);
define('CACHE_DIR', __DIR__ . '/cache/');
define('CACHE_DURATION', 3600); // 1 ora

// Criteri WCAG da verificare
$WCAG_CRITERIA = [
    // Livello A
    '1.1.1' => [
        'name' => 'Contenuto non testuale',
        'level' => 'A',
        'description' => 'Tutto il contenuto non testuale ha un\'alternativa testuale',
        'techniques' => ['H37', 'H36', 'H24', 'H2']
    ],
    '1.2.1' => [
        'name' => 'Solo audio e solo video (preregistrati)',
        'level' => 'A',
        'description' => 'Alternative per media solo audio e solo video preregistrati',
        'techniques' => ['G158', 'G159', 'G166']
    ],
    '1.3.1' => [
        'name' => 'Informazioni e relazioni',
        'level' => 'A',
        'description' => 'Le informazioni e le relazioni trasmesse dalla presentazione possono essere determinate programmaticamente',
        'techniques' => ['H42', 'H43', 'H44', 'H65', 'H71', 'H85']
    ],
    '1.3.2' => [
        'name' => 'Sequenza significativa',
        'level' => 'A',
        'description' => 'La sequenza di lettura corretta può essere determinata programmaticamente',
        'techniques' => ['G57', 'C6', 'C8']
    ],
    '1.4.1' => [
        'name' => 'Uso del colore',
        'level' => 'A',
        'description' => 'Il colore non è usato come unico mezzo per trasmettere informazioni',
        'techniques' => ['G14', 'G111', 'G182']
    ],
    '2.1.1' => [
        'name' => 'Tastiera',
        'level' => 'A',
        'description' => 'Tutte le funzionalità sono disponibili da tastiera',
        'techniques' => ['G202', 'H91', 'SCR20', 'SCR35']
    ],
    '2.2.1' => [
        'name' => 'Regolabile',
        'level' => 'A',
        'description' => 'I limiti di tempo sono regolabili dall\'utente',
        'techniques' => ['G133', 'G198', 'SCR16']
    ],
    '2.4.1' => [
        'name' => 'Salto di blocchi',
        'level' => 'A',
        'description' => 'È disponibile un meccanismo per saltare blocchi di contenuto ripetuti',
        'techniques' => ['G1', 'G123', 'G124', 'H69', 'SCR28']
    ],
    '2.4.2' => [
        'name' => 'Titolo delle pagine',
        'level' => 'A',
        'description' => 'Le pagine web hanno titoli che descrivono argomento o scopo',
        'techniques' => ['H25', 'G88']
    ],
    '3.1.1' => [
        'name' => 'Lingua della pagina',
        'level' => 'A',
        'description' => 'La lingua di ogni pagina web può essere determinata programmaticamente',
        'techniques' => ['H57', 'H58']
    ],
    '3.2.1' => [
        'name' => 'Al focus',
        'level' => 'A',
        'description' => 'Il ricevimento del focus non innesca un cambiamento di contesto',
        'techniques' => ['G107', 'SCR26']
    ],
    '3.3.1' => [
        'name' => 'Identificazione di errori',
        'level' => 'A',
        'description' => 'Gli errori di inserimento sono identificati automaticamente',
        'techniques' => ['G83', 'G84', 'G85', 'SCR18', 'SCR32']
    ],
    '3.3.2' => [
        'name' => 'Etichette o istruzioni',
        'level' => 'A',
        'description' => 'Etichette o istruzioni sono fornite quando il contenuto richiede input dell\'utente',
        'techniques' => ['G131', 'G89', 'G184', 'H44', 'H65', 'H71']
    ],
    '4.1.1' => [
        'name' => 'Parsing',
        'level' => 'A',
        'description' => 'Il markup è valido secondo le specifiche',
        'techniques' => ['G134', 'G192', 'H74', 'H93', 'H94']
    ],
    '4.1.2' => [
        'name' => 'Nome, ruolo, valore',
        'level' => 'A',
        'description' => 'Nome, ruolo e valore sono disponibili programmaticamente',
        'techniques' => ['G108', 'H91', 'SCR21']
    ],
    
    // Livello AA
    '1.2.4' => [
        'name' => 'Sottotitoli (dal vivo)',
        'level' => 'AA',
        'description' => 'I sottotitoli sono forniti per tutto l\'audio dal vivo',
        'techniques' => ['G9', 'G87', 'G93']
    ],
    '1.2.5' => [
        'name' => 'Audiodescrizione (preregistrata)',
        'level' => 'AA',
        'description' => 'L\'audiodescrizione è fornita per tutto il contenuto video preregistrato',
        'techniques' => ['G78', 'G173', 'G8']
    ],
    '1.4.3' => [
        'name' => 'Contrasto (minimo)',
        'level' => 'AA',
        'description' => 'Il rapporto di contrasto è almeno 4.5:1',
        'techniques' => ['G17', 'G18', 'G145', 'G148', 'G174']
    ],
    '1.4.4' => [
        'name' => 'Ridimensionamento del testo',
        'level' => 'AA',
        'description' => 'Il testo può essere ridimensionato fino al 200% senza perdita di funzionalità',
        'techniques' => ['G142', 'G146', 'C12', 'C13', 'C14']
    ],
    '1.4.5' => [
        'name' => 'Immagini di testo',
        'level' => 'AA',
        'description' => 'Se si possono ottenere le stesse informazioni con il testo, non usare immagini di testo',
        'techniques' => ['G140', 'C22', 'C30']
    ],
    '2.4.5' => [
        'name' => 'Molteplici modalità',
        'level' => 'AA',
        'description' => 'Sono disponibili più modalità per individuare una pagina web',
        'techniques' => ['G125', 'G64', 'G63', 'G161', 'G126']
    ],
    '2.4.6' => [
        'name' => 'Intestazioni ed etichette',
        'level' => 'AA',
        'description' => 'Le intestazioni e le etichette descrivono chiaramente argomento o scopo',
        'techniques' => ['G130', 'G131']
    ],
    '2.4.7' => [
        'name' => 'Focus visibile',
        'level' => 'AA',
        'description' => 'Il focus della tastiera è sempre visibile',
        'techniques' => ['G149', 'C15', 'G165', 'G195', 'SCR31']
    ],
    '3.1.2' => [
        'name' => 'Parti in lingua straniera',
        'level' => 'AA',
        'description' => 'La lingua di ogni passaggio o frase può essere determinata programmaticamente',
        'techniques' => ['H58']
    ],
    '3.2.3' => [
        'name' => 'Navigazione coerente',
        'level' => 'AA',
        'description' => 'I meccanismi di navigazione sono presentati nello stesso ordine relativo',
        'techniques' => ['G61']
    ],
    '3.2.4' => [
        'name' => 'Identificazione coerente',
        'level' => 'AA',
        'description' => 'I componenti con la stessa funzionalità sono identificati in modo coerente',
        'techniques' => ['G197']
    ],
    '3.3.3' => [
        'name' => 'Suggerimenti per gli errori',
        'level' => 'AA',
        'description' => 'Vengono forniti suggerimenti per correggere gli errori di inserimento',
        'techniques' => ['G83', 'G84', 'G85', 'G177', 'SCR18', 'SCR32']
    ],
    '3.3.4' => [
        'name' => 'Prevenzione degli errori (legali, finanziari, dati)',
        'level' => 'AA',
        'description' => 'Per le pagine che comportano impegni legali o transazioni finanziarie per l\'utente sono disponibili meccanismi di prevenzione errori',
        'techniques' => ['G98', 'G99', 'G155', 'G164', 'G168']
    ],
    
    // Livello AAA (esempi)
    '1.4.6' => [
        'name' => 'Contrasto (avanzato)',
        'level' => 'AAA',
        'description' => 'Il rapporto di contrasto è almeno 7:1',
        'techniques' => ['G17', 'G18', 'G145', 'G148', 'G174']
    ],
    '2.4.8' => [
        'name' => 'Posizione',
        'level' => 'AAA',
        'description' => 'Sono disponibili informazioni sulla posizione dell\'utente in un insieme di pagine web',
        'techniques' => ['G65', 'G127', 'G128']
    ],
    '3.1.3' => [
        'name' => 'Parole inusuali',
        'level' => 'AAA',
        'description' => 'È disponibile un meccanismo per identificare definizioni specifiche di parole usate in modo inusuale',
        'techniques' => ['G55', 'G62', 'G70', 'H40', 'H60']
    ]
];

// Mappatura severità per tipo di problema
$SEVERITY_MAPPING = [
    'missing_alt_text' => 'high',
    'missing_page_title' => 'high',
    'missing_page_language' => 'medium',
    'heading_sequence' => 'medium',
    'color_contrast' => 'medium',
    'keyboard_accessibility' => 'high',
    'form_labels' => 'high',
    'duplicate_ids' => 'high',
    'html_validity' => 'low',
    'skip_links' => 'medium',
    'focus_visible' => 'medium',
    'link_purpose' => 'medium'
];

// Configurazioni per diversi tipi di siti
$SITE_CONFIGURATIONS = [
    'ecommerce' => [
        'focus_areas' => ['form_labels', 'keyboard_accessibility', 'color_contrast'],
        'additional_checks' => ['payment_forms', 'product_accessibility'],
        'priority_criteria' => ['1.1.1', '1.4.3', '2.1.1', '3.3.1', '3.3.2']
    ],
    'government' => [
        'focus_areas' => ['all'],
        'compliance_level' => 'AA',
        'mandatory_criteria' => ['1.1.1', '1.4.3', '2.1.1', '2.4.1', '2.4.2', '3.1.1'],
        'additional_checks' => ['document_accessibility', 'video_captions']
    ],
    'educational' => [
        'focus_areas' => ['multimedia', 'reading_comprehension', 'navigation'],
        'priority_criteria' => ['1.2.1', '1.2.2', '2.4.6', '3.1.1', '3.1.2'],
        'additional_checks' => ['multimedia_alternatives', 'simple_language']
    ],
    'news' => [
        'focus_areas' => ['content_structure', 'multimedia', 'navigation'],
        'priority_criteria' => ['1.1.1', '1.3.1', '2.4.2', '2.4.6'],
        'additional_checks' => ['article_structure', 'media_alternatives']
    ]
];

// Messaggi di errore localizzati (Italiano)
$ERROR_MESSAGES = [
    'missing_alt_text' => [
        'description' => 'Immagine senza testo alternativo. Le immagini informative devono avere un attributo alt che descriva il contenuto.',
        'recommendation' => 'Aggiungere un attributo alt descrittivo. Per immagini decorative usare alt="".',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/non-text-content.html'
    ],
    'missing_page_title' => [
        'description' => 'La pagina non ha un titolo o il titolo è vuoto. Ogni pagina deve avere un titolo descrittivo.',
        'recommendation' => 'Aggiungere un elemento <title> nella sezione <head> con un titolo che descriva il contenuto o lo scopo della pagina.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/page-titled.html'
    ],
    'missing_page_language' => [
        'description' => 'La lingua principale della pagina non è specificata.',
        'recommendation' => 'Aggiungere l\'attributo lang al tag <html>, esempio: <html lang="it">.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/language-of-page.html'
    ],
    'heading_sequence' => [
        'description' => 'La sequenza dei titoli (h1, h2, h3...) non segue un ordine logico.',
        'recommendation' => 'Utilizzare i titoli in sequenza gerarchica senza saltare livelli.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html'
    ],
    'color_contrast' => [
        'description' => 'Il contrasto tra testo e sfondo potrebbe non essere sufficiente.',
        'recommendation' => 'Assicurarsi che il rapporto di contrasto sia almeno 4.5:1 per testo normale e 3:1 per testo grande.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html'
    ],
    'keyboard_accessibility' => [
        'description' => 'Elemento interattivo non accessibile tramite tastiera.',
        'recommendation' => 'Assicurarsi che tutti gli elementi interattivi siano raggiungibili e utilizzabili con la tastiera.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/keyboard.html'
    ],
    'form_labels' => [
        'description' => 'Campo di input senza etichetta associata.',
        'recommendation' => 'Associare ogni campo di input con un\'etichetta usando <label> o attributi aria-label/aria-labelledby.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/labels-or-instructions.html'
    ],
    'duplicate_ids' => [
        'description' => 'Attributi ID duplicati trovati nella pagina.',
        'recommendation' => 'Assicurarsi che ogni attributo id sia unico all\'interno della pagina.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/parsing.html'
    ],
    'skip_links' => [
        'description' => 'Mancano i link per saltare al contenuto principale.',
        'recommendation' => 'Aggiungere un link "Salta al contenuto principale" all\'inizio della pagina.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/Understanding/bypass-blocks.html'
    ]
];

// Funzioni helper per la configurazione
function getWCAGCriteria($level = null) {
    global $WCAG_CRITERIA;
    
    if ($level === null) {
        return $WCAG_CRITERIA;
    }
    
    return array_filter($WCAG_CRITERIA, function($criterion) use ($level) {
        return $criterion['level'] === $level;
    });
}

function getSeverityForIssueType($issueType) {
    global $SEVERITY_MAPPING;
    return $SEVERITY_MAPPING[$issueType] ?? 'medium';
}

function getErrorMessage($issueType) {
    global $ERROR_MESSAGES;
    return $ERROR_MESSAGES[$issueType] ?? [
        'description' => 'Problema di accessibilità rilevato.',
        'recommendation' => 'Verificare la conformità ai criteri WCAG.',
        'help_url' => 'https://www.w3.org/WAI/WCAG21/'
    ];
}

function getSiteConfiguration($siteType) {
    global $SITE_CONFIGURATIONS;
    return $SITE_CONFIGURATIONS[$siteType] ?? $SITE_CONFIGURATIONS['government'];
}

// Configurazioni avanzate per European Accessibility Act
define('EAA_COMPLIANCE_DATE', '2025-06-28');
define('EAA_SECTORS', [
    'public_sector',
    'banking',
    'ecommerce',
    'transport',
    'telecommunications',
    'media_services'
]);

$EAA_REQUIREMENTS = [
    'public_sector' => [
        'mandatory_level' => 'AA',
        'additional_requirements' => [
            'document_accessibility',
            'video_captions',
            'sign_language',
            'easy_language_version'
        ]
    ],
    'ecommerce' => [
        'mandatory_level' => 'AA',
        'additional_requirements' => [
            'payment_accessibility',
            'product_information_accessibility',
            'customer_service_accessibility'
        ]
    ]
];

// Configurazione email per notifiche
$EMAIL_CONFIG = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => '',  // Configurare
    'smtp_pass' => '',  // Configurare
    'from_email' => 'noreply@tuodominio.com',
    'from_name' => 'Sistema Audit WCAG'
];

// Template per diversi tipi di report
$REPORT_TEMPLATES = [
    'executive_summary' => [
        'sections' => ['overview', 'key_issues', 'recommendations', 'compliance_status'],
        'max_pages' => 5,
        'target_audience' => 'management'
    ],
    'technical_detail' => [
        'sections' => ['full_analysis', 'code_examples', 'step_by_step_fixes'],
        'max_pages' => null,
        'target_audience' => 'developers'
    ],
    'compliance_report' => [
        'sections' => ['legal_requirements', 'gaps_analysis', 'timeline', 'cost_estimation'],
        'max_pages' => 10,
        'target_audience' => 'legal_compliance'
    ]
];

// Configurazione schedulazione audit automatici
$SCHEDULER_CONFIG = [
    'enable_scheduling' => false,
    'default_frequency' => 'monthly',
    'notification_email' => '',
    'retry_attempts' => 3,
    'retry_delay' => 3600 // 1 ora
];

// Whitelist e blacklist per controlli specifici
$AUDIT_FILTERS = [
    'exclude_file_types' => ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.zip'],
    'exclude_url_patterns' => [
        '/admin/',
        '/wp-admin/',
        '/cms/',
        '/backend/',
        '/_private/'
    ],
    'include_only_patterns' => [], // Se specificato, include solo URL che corrispondono
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'check_external_resources' => false
];

// Configurazione per API esterne (opzionale)
$EXTERNAL_APIS = [
    'google_pagespeed' => [
        'enabled' => false,
        'api_key' => '', // Configurare se necessario
        'url' => 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
    ],
    'wave_api' => [
        'enabled' => false,
        'api_key' => '', // Configurare se necessario
        'url' => 'https://wave.webaim.org/api/'
    ]
];

// Funzione per validare la configurazione
function validateConfiguration() {
    $errors = [];
    
    // Controlla connessione database
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS
        );
    } catch (PDOException $e) {
        $errors[] = "Errore connessione database: " . $e->getMessage();
    }
    
    // Controlla directory necessarie
    $dirs = [REPORTS_DIR, dirname(LOG_FILE), CACHE_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $errors[] = "Impossibile creare directory: $dir";
            }
        }
        if (!is_writable($dir)) {
            $errors[] = "Directory non scrivibile: $dir";
        }
    }
    
    // Controlla estensioni PHP necessarie
    $extensions = ['curl', 'dom', 'libxml', 'json', 'pdo_mysql'];
    foreach ($extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Estensione PHP mancante: $ext";
        }
    }
    
    return $errors;
}

// Inizializzazione
if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);
    
    // Imposta configurazioni PHP
    ini_set('max_execution_time', MAX_EXECUTION_TIME);
    ini_set('memory_limit', MEMORY_LIMIT);
    
    // Crea directory se non esistono
    $dirs = [REPORTS_DIR, dirname(LOG_FILE), CACHE_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Validazione configurazione
    $configErrors = validateConfiguration();
    if (!empty($configErrors)) {
        foreach ($configErrors as $error) {
            error_log("WCAG Auditor Config Error: $error");
        }
    }
}
?>

// Accessibility Statement defaults (Point 3)
define('INCLUDO_STATEMENT_CONTACT_EMAIL', ''); // e.g. accessibilita@example.com
define('INCLUDO_STATEMENT_ORG_NAME', ''); // e.g. Your Organisation
define('INCLUDO_LANG', 'it');
