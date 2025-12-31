<?php
/**
 * SISTEMA DI AUDIT ACCESSIBILITÀ WCAG 2.2
 * Conforme all'European Accessibility Act
 * 
 * ESEMPIO COMPLETO DI UTILIZZO
 * 
 * Questo file dimostra come utilizzare il sistema di audit
 * per verificare la conformità WCAG di un sito web.
 */

require_once 'config.php';
require_once 'wcag_audit_system.php';
require_once 'advanced_checks.php';

/**
 * INSTALLAZIONE E CONFIGURAZIONE
 * 
 * 1. Creare il database MySQL:
 *    mysql -u root -p < database_setup.sql
 * 
 * 2. Configurare le credenziali in config.php:
 *    - DB_HOST, DB_NAME, DB_USER, DB_PASS
 * 
 * 3. Assicurarsi che le directory siano scrivibili:
 *    - reports/
 *    - logs/
 *    - cache/
 * 
 * 4. Installare le estensioni PHP necessarie:
 *    - curl, dom, libxml, json, pdo_mysql
 */

try {
    echo "<h1>Sistema di Audit Accessibilità WCAG 2.2</h1>\n";
    echo "<p>Avvio del sistema di audit...</p>\n";
    
    // Inizializza l'auditor
    $auditor = new WCAGAuditor(DB_HOST, DB_NAME, DB_USER, DB_PASS);
    
    // ESEMPIO 1: Audit semplice
    echo "<h2>Esempio 1: Audit semplice</h2>\n";
    
    $siteUrl = 'https://esempio.com';
    $maxPages = 10;
    
    echo "<p>Analizzando $siteUrl (max $maxPages pagine)...</p>\n";
    
    $sessionId = $auditor->auditSite($siteUrl, $maxPages);
    
    echo "<p>✅ Audit completato! Sessione ID: $sessionId</p>\n";
    
    // Genera report HTML
    $htmlReport = $auditor->generateReport($sessionId, 'html');
    file_put_contents(REPORTS_DIR . "audit_$sessionId.html", $htmlReport);
    echo "<p>📄 Report HTML salvato in: reports/audit_$sessionId.html</p>\n";
    
    // Genera report JSON per API
    $jsonReport = $auditor->generateReport($sessionId, 'json');
    file_put_contents(REPORTS_DIR . "audit_$sessionId.json", $jsonReport);
    echo "<p>📊 Report JSON salvato in: reports/audit_$sessionId.json</p>\n";
    
    // ESEMPIO 2: Audit con controlli avanzati
    echo "<h2>Esempio 2: Audit con controlli avanzati</h2>\n";
    
    // Simula l'analisi di una singola pagina con controlli avanzati
    $pageUrl = 'https://esempio.com/contatti';
    echo "<p>Analisi avanzata di: $pageUrl</p>\n";
    
    // Scarica la pagina
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $pageUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => USER_AGENT
    ]);
    
    $content = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($content && $statusCode < 400) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        
        // Esegui controlli avanzati
        $advancedChecker = new AdvancedWCAGChecker($dom, $xpath, $content, $pageUrl);
        
        $contrastIssues = $advancedChecker->checkColorContrast();
        $semanticIssues = $advancedChecker->checkSemanticStructure();
        $keyboardIssues = $advancedChecker->checkKeyboardAccessibility();
        $formIssues = $advancedChecker->checkFormAccessibility();
        $imageIssues = $advancedChecker->checkAdvancedImageAccessibility();
        $ariaIssues = $advancedChecker->checkARIAImplementation();
        
        $totalAdvancedIssues = count($contrastIssues) + count($semanticIssues) + 
                             count($keyboardIssues) + count($formIssues) + 
                             count($imageIssues) + count($ariaIssues);
        
        echo "<p>🔍 Controlli avanzati completati:</p>\n";
        echo "<ul>\n";
        echo "<li>Contrasto colori: " . count($contrastIssues) . " problemi</li>\n";
        echo "<li>Struttura semantica: " . count($semanticIssues) . " problemi</li>\n";
        echo "<li>Accessibilità tastiera: " . count($keyboardIssues) . " problemi</li>\n";
        echo "<li>Accessibilità form: " . count($formIssues) . " problemi</li>\n";
        echo "<li>Accessibilità immagini: " . count($imageIssues) . " problemi</li>\n";
        echo "<li>Implementazione ARIA: " . count($ariaIssues) . " problemi</li>\n";
        echo "</ul>\n";
        echo "<p><strong>Totale problemi avanzati: $totalAdvancedIssues</strong></p>\n";
    }
    
    // ESEMPIO 3: Controlli specifici European Accessibility Act
    echo "<h2>Esempio 3: Controlli European Accessibility Act</h2>\n";
    
    $siteTypes = ['public_sector', 'ecommerce', 'banking', 'media_services'];
    
    foreach ($siteTypes as $siteType) {
        echo "<h3>Controlli per settore: $siteType</h3>\n";
        
        $eaaChecker = new EAAComplianceChecker($dom, $xpath, $content, $siteType);
        
        $eaaIssues = [];
        switch ($siteType) {
            case 'public_sector':
                $eaaIssues = $eaaChecker->checkPublicSectorCompliance();
                break;
            case 'ecommerce':
                $eaaIssues = $eaaChecker->checkECommerceCompliance();
                break;
            case 'banking':
                $eaaIssues = $eaaChecker->checkBankingCompliance();
                break;
            case 'media_services':
                $eaaIssues = $eaaChecker->checkMediaServicesCompliance();
                break;
        }
        
        echo "<p>🏛️ Problemi EAA per $siteType: " . count($eaaIssues) . "</p>\n";
    }
    
    // ESEMPIO 4: Report avanzati
    echo "<h2>Esempio 4: Report avanzati</h2>\n";
    
    $reportGenerator = new AdvancedReportGenerator($auditor->getPDO());
    
    // Matrice di conformità
    $complianceMatrix = $reportGenerator->generateComplianceMatrix($sessionId);
    echo "<h3>Matrice di Conformità WCAG</h3>\n";
    foreach ($complianceMatrix as $level => $criteria) {
        echo "<h4>Livello $level (" . count($criteria) . " criteri con problemi)</h4>\n";
        if (!empty($criteria)) {
            echo "<ul>\n";
            foreach (array_slice($criteria, 0, 5) as $criterion) { // Prime 5
                echo "<li>WCAG {$criterion['wcag_criterion']}: {$criterion['issue_count']} problemi su {$criterion['affected_pages']} pagine</li>\n";
            }
            echo "</ul>\n";
        }
    }
    
    // Report priorità
    $priorityReport = $reportGenerator->generatePriorityReport($sessionId);
    echo "<h3>Problemi per Priorità</h3>\n";
    echo "<ol>\n";
    foreach (array_slice($priorityReport, 0, 5) as $item) {
        echo "<li>{$item['issue_type']} (WCAG {$item['wcag_criterion']} - {$item['wcag_level']}): {$item['occurrences']} occorrenze, priorità {$item['priority_score']}</li>\n";
    }
    echo "</ol>\n";
    
    // Stima costi
    $costEstimate = $reportGenerator->estimateFixingCost($sessionId);
    echo "<h3>Stima Costi di Correzione</h3>\n";
    echo "<p><strong>Tempo stimato:</strong> {$costEstimate['total_hours']} ore</p>\n";
    echo "<p><strong>Costo stimato:</strong> €{$costEstimate['estimated_cost_eur']}</p>\n";
    
    // ESEMPIO 5: Utilizzo programmatico tramite API
    echo "<h2>Esempio 5: Utilizzo tramite API</h2>\n";
    
    // Simula chiamata API
    $apiRequest = [
        'action' => 'audit_site',
        'site_url' => 'https://esempio.com',
        'max_pages' => 20,
        'site_type' => 'ecommerce',
        'include_advanced_checks' => true,
        'report_format' => 'json'
    ];
    
    echo "<p>📡 Richiesta API simulata:</p>\n";
    echo "<pre>" . json_encode($apiRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
    
    // Risposta API simulata
    $apiResponse = [
        'status' => 'success',
        'session_id' => $sessionId,
        'summary' => [
            'pages_analyzed' => 10,
            'total_issues' => 45,
            'critical_issues' => 3,
            'high_issues' => 12,
            'medium_issues' => 20,
            'low_issues' => 10
        ],
        'compliance_levels' => [
            'A' => 'partial',
            'AA' => 'fail',
            'AAA' => 'fail'
        ],
        'report_urls' => [
            'html' => "reports/audit_{$sessionId}.html",
            'json' => "reports/audit_{$sessionId}.json",
            'csv' => "reports/audit_{$sessionId}.csv"
        ],
        'next_steps' => [
            'Fix critical issues first',
            'Focus on Level A compliance', 
            'Review form accessibility',
            'Improve color contrast'
        ],
        'eaa_compliance' => [
            'public_sector' => 'requires_review',
            'ecommerce' => 'partial_compliance',
            'banking' => 'not_applicable',
            'media_services' => 'not_applicable'
        ]
    ];
    
    echo "<p>📄 Risposta API:</p>\n";
    echo "<pre>" . json_encode($apiResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
    
    echo "<h2>✅ Sistema di Audit Completato</h2>\n";
    echo "<p>Il sistema è ora configurato e funzionante per:</p>\n";
    echo "<ul>\n";
    echo "<li>🔍 Audit completi conformi WCAG 2.2</li>\n";
    echo "<li>🏛️ Verifiche European Accessibility Act</li>\n";
    echo "<li>📊 Report dettagliati multi-formato</li>\n";
    echo "<li>🔄 Monitoraggio continuo accessibilità</li>\n";
    echo "<li>🚀 Integrazione CI/CD e API</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; background: #ffebee; border: 1px solid #f44336; border-radius: 5px;'>\n";
    echo "<h3>❌ Errore durante l'esecuzione:</h3>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Verifica:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Configurazione database in config.php</li>\n";
    echo "<li>Connessione internet</li>\n";
    echo "<li>Permessi directory (reports/, logs/, cache/)</li>\n";
    echo "<li>Estensioni PHP richieste</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
}

/**
 * GUIDA RAPIDA ALL'INSTALLAZIONE
 * ================================
 * 
 * 1. REQUISITI SISTEMA:
 *    - PHP 7.4+ con estensioni: curl, dom, libxml, json, pdo_mysql
 *    - MySQL 5.7+ o MariaDB 10.2+
 *    - 512MB RAM minimo, 1GB raccomandato
 *    - Connessione internet per crawling
 * 
 * 2. INSTALLAZIONE DATABASE:
 *    mysql -u root -p < database_setup.sql
 * 
 * 3. CONFIGURAZIONE:
 *    - Modifica config.php con credenziali DB
 *    - Crea directory: mkdir reports logs cache
 *    - Imposta permessi: chmod 755 reports logs cache
 * 
 * 4. TEST RAPIDO:
 *    php usage_example.php --url=https://esempio.com --max-pages=5
 * 
 * 5. UTILIZZO WEB:
 *    Accedi via browser al file wcag_audit_system.php
 */

// Funzioni CLI avanzate
if (php_sapi_name() === 'cli') {
    $options = getopt("u:p:f:t:hv", ["url:", "max-pages:", "format:", "type:", "help", "version", "validate", "cleanup:"]);
    
    if (isset($options['v']) || isset($options['version'])) {
        echo "Sistema Audit WCAG versione 2.1.0\n";
        echo "Conforme European Accessibility Act\n";
        exit(0);
    }
    
    if (isset($options['validate'])) {
        echo "Validazione configurazione sistema...\n";
        $errors = validateConfiguration();
        if (empty($errors)) {
            echo "✅ Configurazione valida!\n";
            exit(0);
        } else {
            echo "❌ Errori rilevati:\n";
            foreach ($errors as $error) {
                echo "  - $error\n";
            }
            exit(1);
        }
    }
    
    if (isset($options['cleanup'])) {
        $days = intval($options['cleanup']);
        echo "Pulizia audit più vecchi di $days giorni...\n";
        cleanupOldAudits($days);
        exit(0);
    }
    
    if (isset($options['h']) || isset($options['help'])) {
        echo "Sistema Audit Accessibilità WCAG 2.2\n";
        echo "=========================================\n\n";
        echo "Utilizzo: php usage_example.php [opzioni]\n\n";
        echo "Opzioni principali:\n";
        echo "  -u, --url URL          URL sito da analizzare\n";
        echo "  -p, --max-pages N      Numero massimo pagine (default: 50)\n";
        echo "  -f, --format FORMAT    Formato report: html,json,csv (default: json)\n";
        echo "  -t, --type TYPE        Tipo sito: public_sector,ecommerce,banking,media\n\n";
        echo "Opzioni utilità:\n";
        echo "  --validate             Valida configurazione sistema\n";
        echo "  --cleanup N            Pulisce audit più vecchi di N giorni\n";
        echo "  -v, --version          Mostra versione\n";
        echo "  -h, --help             Mostra questo aiuto\n\n";
        echo "Esempi:\n";
        echo "  # Audit base\n";
        echo "  php usage_example.php -u https://esempio.com\n\n";
        echo "  # Audit e-commerce completo\n";
        echo "  php usage_example.php -u https://shop.com -p 100 -t ecommerce\n\n";
        echo "  # Audit settore pubblico con report HTML\n";
        echo "  php usage_example.php -u https://comune.gov.it -f html -t public_sector\n\n";
        echo "  # Pulizia dati vecchi\n";
        echo "  php usage_example.php --cleanup 30\n\n";
        exit(0);
    }
    
    if (isset($options['u']) || isset($options['url'])) {
        $url = $options['u'] ?? $options['url'];
        $maxPages = intval($options['p'] ?? $options['max-pages'] ?? 50);
        $format = $options['f'] ?? $options['format'] ?? 'json';
        $siteType = $options['t'] ?? $options['type'] ?? 'general';
        
        echo "🚀 Avvio audit WCAG 2.2\n";
        echo "=============================\n";
        echo "URL: $url\n";
        echo "Pagine max: $maxPages\n";
        echo "Formato: $format\n";
        echo "Tipo sito: $siteType\n";
        echo "Data: " . date('Y-m-d H:i:s') . "\n\n";
        
        try {
            $auditor = new WCAGAuditor(DB_HOST, DB_NAME, DB_USER, DB_PASS);
            
            echo "📥 Avvio scansione...\n";
            $sessionId = $auditor->auditSite($url, $maxPages);
            
            echo "📊 Generazione report $format...\n";
            $report = $auditor->generateReport($sessionId, $format);
            
            $filename = "audit_{$sessionId}_" . date('Ymd_His') . ".$format";
            file_put_contents($filename, $report);
            
            // Statistiche finali
            $stats = $auditor->getAuditStatistics($sessionId);
            
            echo "\n✅ Audit completato!\n";
            echo "==================\n";
            echo "Sessione ID: $sessionId\n";
            echo "Pagine analizzate: {$stats['pages_analyzed']}\n";
            echo "Problemi totali: {$stats['total_issues_detailed']}\n";
            echo "Criteri violati: {$stats['unique_violations']}\n";
            echo "Tempo medio risposta: " . round($stats['avg_response_time'], 2) . "s\n";
            echo "Pagine con errori: {$stats['error_pages']}\n";
            echo "Report salvato: $filename\n\n";
            
            // Raccomandazioni basate sul tipo di sito
            echo "🎯 Raccomandazioni per $siteType:\n";
            $siteConfig = getSiteConfiguration($siteType);
            if (isset($siteConfig['priority_criteria'])) {
                echo "Criteri prioritari: " . implode(', ', $siteConfig['priority_criteria']) . "\n";
            }
            if (isset($siteConfig['focus_areas'])) {
                echo "Aree di focus: " . implode(', ', $siteConfig['focus_areas']) . "\n";
            }
            
            exit(0);
            
        } catch (Exception $e) {
            echo "\n❌ Errore durante l'audit:\n";
            echo $e->getMessage() . "\n";
            echo "\nVerifica:\n";
            echo "- Connessione database\n";
            echo "- URL accessibile\n";
            echo "- Permessi directory\n";
            exit(1);
        }
    } else {
        echo "❌ URL richiesto. Usa --help per informazioni.\n";
        exit(1);
    }
}

// Funzioni helper per CLI
function validateConfiguration() {
    $errors = [];
    
    // Test connessione DB
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    } catch (PDOException $e) {
        $errors[] = "Database: " . $e->getMessage();
    }
    
    // Verifica directory
    $dirs = [REPORTS_DIR, dirname(LOG_FILE), CACHE_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            $errors[] = "Directory mancante: $dir";
        } elseif (!is_writable($dir)) {
            $errors[] = "Directory non scrivibile: $dir";
        }
    }
    
    // Verifica estensioni PHP
    $extensions = ['curl', 'dom', 'libxml', 'json', 'pdo_mysql'];
    foreach ($extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Estensione PHP mancante: $ext";
        }
    }
    
    return $errors;
}

function cleanupOldAudits($days) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("CALL CleanOldAudits(?)");
        $stmt->execute([$days]);
        echo "✅ Pulizia completata\n";
    } catch (Exception $e) {
        echo "❌ Errore pulizia: " . $e->getMessage() . "\n";
    }
}

echo "\n🎉 Sistema Audit WCAG pronto all'uso!\n";
echo "Documentazione completa nei commenti del codice.\n";
echo "Per supporto: https://www.w3.org/WAI/WCAG21/Understanding/\n";
?>
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; background: #ffebee; border: 1px solid #f44336; border-radius: 5px;'>\n";
    echo "<h3>❌ Errore durante l'esecuzione:</h3>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Verifica:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Configurazione database in config.php</li>\n";
    echo "<li>Connessione internet per scaricare le pagine</li>\n";
    echo "<li>Permessi di scrittura nelle directory reports/, logs/, cache/</li>\n";
    echo "<li>Estensioni PHP: curl, dom, libxml, json, pdo_mysql</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
}

/**
 * DOCUMENTAZIONE TECNICA
 * 
 * STRUTTURA DEL SISTEMA:
 * 
 * 1. WCAGAuditor (classe principale)
 *    - Gestisce l'audit completo del sito
 *    - Crawling automatico delle pagine
 *    - Controlli WCAG base
 *    - Generazione report in multiple format
 * 
 * 2. AdvancedWCAGChecker
 *    - Controlli WCAG avanzati e specifici
 *    - Analisi del contrasto colori
 *    - Verifica struttura semantica
 *    - Controllo accessibilità tastiera e ARIA
 * 
 * 3. EAAComplianceChecker
 *    - Controlli specifici European Accessibility Act
 *    - Verifiche per settori specifici (pubblico, e-commerce, banking, media)
 *    - Controlli di conformità normativa
 * 
 * 4. AdvancedReportGenerator
 *    - Generazione report avanzati
 *    - Matrici di conformità
 *    - Analisi priorità e costi
 * 
 * DATABASE SCHEMA:
 * 
 * - audit_sessions: Sessioni di audit
 * - page_audits: Audit delle singole pagine
 * - accessibility_issues: Problemi di accessibilità rilevati
 * - technical_details: Dettagli tecnici aggiuntivi
 * - audit_configurations: Configurazioni del sistema
 * 
 * CRITERI WCAG VERIFICATI:
 * 
 * Livello A:
 * - 1.1.1 Contenuto non testuale
 * - 1.3.1 Informazioni e relazioni
 * - 2.1.1 Tastiera
 * - 2.4.1 Bypass dei blocchi
 * - 2.4.2 Titoli delle pagine
 * - 3.1.1 Lingua della pagina
 * - 3.3.2 Etichette o istruzioni
 * - 4.1.1 Parsing
 * - 4.1.2 Nome, ruolo, valore
 * 
 * Livello AA:
 * - 1.4.3 Contrasto (minimo)
 * - 1.4.4 Ridimensionamento del testo
 * - 2.4.6 Intestazioni ed etichette
 * - 2.4.7 Focus visibile
 * - 3.2.3 Navigazione coerente
 * - 3.2.4 Identificazione coerente
 * - 3.3.3 Suggerimenti per gli errori
 * 
 * CONTROLLI EUROPEAN ACCESSIBILITY ACT:
 * 
 * Settore Pubblico:
 * - Dichiarazione di accessibilità
 * - Meccanismo di feedback
 * - Conformità obbligatoria AA
 * 
 * E-commerce:
 * - Accessibilità processo checkout
 * - Informazioni prodotti accessibili
 * - Alternative per CAPTCHA
 * 
 * Banking:
 * - Autenticazione sicura e accessibile
 * - Alternative per verifiche biometriche
 * 
 * Media Services:
 * - Sottotitoli per contenuti video
 * - Audiodescrizione quando necessaria
 * 
 * UTILIZZO:
 * 
 * 1. Audit semplice:
 *    $auditor = new WCAGAuditor($host, $db, $user, $pass);
 *    $sessionId = $auditor->auditSite('https://esempio.com', 50);
 *    $report = $auditor->generateReport($sessionId, 'html');
 * 
 * 2. Controlli avanzati:
 *    $checker = new AdvancedWCAGChecker($dom, $xpath, $content, $url);
 *    $issues = $checker->checkColorContrast();
 * 
 * 3. Conformità EAA:
 *    $eaaChecker = new EAAComplianceChecker($dom, $xpath, $content, 'public_sector');
 *    $eaaIssues = $eaaChecker->checkPublicSectorCompliance();
 * 
 * CONFIGURAZIONE:
 * 
 * Modificare config.php per:
 * - Credenziali database
 * - Timeout e limiti
 * - Configurazioni WCAG
 * - Impostazioni email e logging
 * 
 * ESTENSIONI FUTURE:
 * 
 * - Integrazione con API esterne (Google PageSpeed, aXe)
 * - Generazione PDF dei report
 * - Dashboard web per gestione audit
 * - Plugin per CMS popolari (WordPress, Drupal)
 * - App mobile per audit rapidi
 * - Integrazione machine learning per detection automatica
 * 
 * BEST PRACTICES:
 * 
 * 1. Eseguire audit regolari (settimanali/mensili)
 * 2. Focalizzarsi prima sui problemi critici e di livello A
 * 3. Testare sempre manualmente i risultati automatici
 * 4. Coinvolgere utenti con disabilità nei test
 * 5. Documentare le correzioni e verificare i miglioramenti
 * 6. Integrare controlli nel processo di sviluppo
 * 7. Formare il team sui principi di accessibilità
 * 
 * LIMITAZIONI:
 * 
 * - Non può rilevare tutti i problemi di accessibilità
 * - Alcuni controlli richiedono valutazione umana
 * - JavaScript complesso potrebbe non essere analizzato completamente
 * - Contenuti dinamici potrebbero sfuggire all'analisi
 * - I test con utenti reali rimangono indispensabili
 * 
 * SUPPORTO E MANUTENZIONE:
 * 
 * - Aggiornare regolarmente i criteri WCAG
 * - Monitorare le modifiche normative EAA
 * - Aggiornare la configurazione per nuovi standard
 * - Pulire periodicamente i dati vecchi (procedura CleanOldAudits)
 * - Backup regolari del database
 * 
 * CONFORMITÀ NORMATIVA:
 * 
 * Il sistema è progettato per supportare:
 * - WCAG 2.2 e 2.1 (livelli A, AA, AAA)
 * - European Accessibility Act (EAA)
 * - Direttiva UE 2016/2102 (settore pubblico)
 * - EN 301 549 (standard europeo)
 * - Section 508 (USA, compatibilità base)
 * 
 * Per supporto tecnico o domande specifiche,
 * consultare la documentazione ufficiale WCAG:
 * https://www.w3.org/WAI/WCAG21/Understanding/
 */

// Funzioni di utilità per debugging e manutenzione

function debugAuditSession($sessionId) {
    global $auditor;
    
    echo "<h3>🔧 Debug Sessione $sessionId</h3>\n";
    
    $pdo = $auditor->getPDO();
    
    // Informazioni sessione
    $stmt = $pdo->prepare("SELECT * FROM audit_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "<p><strong>URL:</strong> {$session['site_url']}</p>\n";
        echo "<p><strong>Status:</strong> {$session['status']}</p>\n";
        echo "<p><strong>Pagine:</strong> {$session['total_pages']}</p>\n";
        echo "<p><strong>Problemi:</strong> {$session['total_issues']}</p>\n";
        
        // Top 5 problemi
        $stmt = $pdo->prepare("
            SELECT issue_type, COUNT(*) as count 
            FROM accessibility_issues ai
            JOIN page_audits pa ON ai.page_audit_id = pa.id
            WHERE pa.session_id = ?
            GROUP BY issue_type
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute([$sessionId]);
        $topIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Top 5 problemi:</strong></p>\n<ul>\n";
        foreach ($topIssues as $issue) {
            echo "<li>{$issue['issue_type']}: {$issue['count']} occorrenze</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p>❌ Sessione non trovata</p>\n";
    }
}

function exportAuditData($sessionId, $format = 'csv') {
    global $auditor;
    
    $pdo = $auditor->getPDO();
    
    $stmt = $pdo->prepare("
        SELECT 
            pa.url,
            pa.title,
            ai.wcag_criterion,
            ai.wcag_level,
            ai.severity,
            ai.issue_type,
            ai.description,
            ai.recommendation,
            ai.element_selector
        FROM page_audits pa
        JOIN accessibility_issues ai ON pa.id = ai.page_audit_id
        WHERE pa.session_id = ?
        ORDER BY pa.url, ai.wcag_criterion
    ");
    
    $stmt->execute([$sessionId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = REPORTS_DIR . "export_{$sessionId}_{$format}";
    
    if ($format === 'csv') {
        $fp = fopen($filename, 'w');
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }
        }
        fclose($fp);
    } elseif ($format === 'json') {
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    echo "<p>📁 Dati esportati in: $filename</p>\n";
    return $filename;
}

function cleanupOldAudits($daysOld = 30) {
    global $auditor;
    
    $pdo = $auditor->getPDO();
    
    $stmt = $pdo->prepare("CALL CleanOldAudits(?)");
    $stmt->execute([$daysOld]);
    
    echo "<p>🧹 Pulizia audit più vecchi di $daysOld giorni completata</p>\n";
}

function validateSystemHealth() {
    $errors = validateConfiguration();
    
    if (empty($errors)) {
        echo "<p>✅ Sistema in salute - tutte le verifiche superate</p>\n";
    } else {
        echo "<p>⚠️ Problemi rilevati:</p>\n<ul>\n";
        foreach ($errors as $error) {
            echo "<li>$error</li>\n";
        }
        echo "</ul>\n";
    }
    
    return empty($errors);
}

// Se eseguito da riga di comando
if (php_sapi_name() === 'cli') {
    echo "Sistema di Audit Accessibilità WCAG - Modalità CLI\n";
    echo "===============================================\n\n";
    
    $options = getopt("u:p:f:h", ["url:", "max-pages:", "format:", "help"]);
    
    if (isset($options['h']) || isset($options['help'])) {
        echo "Utilizzo: php usage_example.php [opzioni]\n\n";
        echo "Opzioni:\n";
        echo "  -u, --url URL        URL del sito da analizzare\n";
        echo "  -p, --max-pages N    Numero massimo di pagine (default: 50)\n";
        echo "  -f, --format FORMAT  Formato report: html, json, csv (default: json)\n";
        echo "  -h, --help           Mostra questo aiuto\n\n";
        echo "Esempi:\n";
        echo "  php usage_example.php -u https://esempio.com -p 25 -f html\n";
        echo "  php usage_example.php --url=https://esempio.com --format=json\n";
        exit(0);
    }
    
    if (isset($options['u']) || isset($options['url'])) {
        $url = $options['u'] ?? $options['url'];
        $maxPages = $options['p'] ?? $options['max-pages'] ?? 50;
        $format = $options['f'] ?? $options['format'] ?? 'json';
        
        echo "Avvio audit per: $url\n";
        echo "Pagine massime: $maxPages\n";
        echo "Formato: $format\n\n";
        
        try {
            $auditor = new WCAGAuditor(DB_HOST, DB_NAME, DB_USER, DB_PASS);
            $sessionId = $auditor->auditSite($url, intval($maxPages));
            
            $report = $auditor->generateReport($sessionId, $format);
            $filename = "audit_{$sessionId}.$format";
            file_put_contents($filename, $report);
            
            echo "✅ Audit completato!\n";
            echo "Sessione ID: $sessionId\n";
            echo "Report salvato: $filename\n";
            
        } catch (Exception $e) {
            echo "❌ Errore: " . $e->getMessage() . "\n";
            exit(1);
        }
    } else {
        echo "❌ URL richiesto. Usa --help per maggiori informazioni.\n";
        exit(1);
    }
}
?>