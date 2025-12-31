<?php
/**
 * Includo - Sistema di Audit Accessibilità WCAG 2.2
 * Classe principale per l'audit di accessibilità
 *
 * @version 2.2.0
 * @author Franco Aquini - Web Salad
 */

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}
require_once 'Logger.php';

class IncludoAuditor {
    private $pdo;
    private $baseUrl;
    private $visitedUrls = [];
    private $maxPages = 100;

    public function __construct($host, $dbname, $username, $password) {
        Logger::info("Inizializzazione IncludoAuditor", [
            'host' => $host,
            'dbname' => $dbname,
            'username' => $username
        ]);

        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            Logger::info("Connessione database stabilita con successo");

            $this->initDatabase();
            Logger::info("Database inizializzato con successo");

        } catch(PDOException $e) {
            Logger::critical("Errore connessione database", [
                'error' => $e->getMessage(),
                'host' => $host,
                'dbname' => $dbname
            ]);
            throw new Exception("Errore connessione database: " . $e->getMessage());
        }
    }

    private function initDatabase() {
        Logger::debug("Inizializzazione schema database");

        $sql = "
        CREATE TABLE IF NOT EXISTS audit_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            site_url VARCHAR(255) NOT NULL,
            start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            end_time DATETIME NULL,
            total_pages INT DEFAULT 0,
            total_issues INT DEFAULT 0,
            status ENUM('running', 'completed', 'error') DEFAULT 'running',
            user_agent VARCHAR(255) DEFAULT 'Includo WCAG Auditor 2.1',
            max_pages_limit INT DEFAULT 100,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_site_url (site_url),
            INDEX idx_status (status),
            INDEX idx_start_time (start_time)
        );

        CREATE TABLE IF NOT EXISTS page_audits (
            id INT PRIMARY KEY AUTO_INCREMENT,
            session_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            title VARCHAR(255),
            audit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            response_time FLOAT,
            status_code INT,
            content_length INT,
            total_issues INT DEFAULT 0,
            wcag_level_a_issues INT DEFAULT 0,
            wcag_level_aa_issues INT DEFAULT 0,
            wcag_level_aaa_issues INT DEFAULT 0,
            load_time FLOAT DEFAULT 0,
            redirects_count INT DEFAULT 0,
            final_url VARCHAR(500),
            meta_description TEXT,
            h1_count INT DEFAULT 0,
            img_count INT DEFAULT 0,
            link_count INT DEFAULT 0,
            form_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
            INDEX idx_session_id (session_id),
            INDEX idx_url (url(100)),
            INDEX idx_total_issues (total_issues),
            INDEX idx_status_code (status_code)
        );

        CREATE TABLE IF NOT EXISTS accessibility_issues (
            id INT PRIMARY KEY AUTO_INCREMENT,
            page_audit_id INT NOT NULL,
            issue_type VARCHAR(100) NOT NULL,
            wcag_criterion VARCHAR(20) NOT NULL,
            wcag_level ENUM('A', 'AA', 'AAA') NOT NULL,
            wcag_version ENUM('2.0', '2.1', '2.2') DEFAULT '2.1',
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            confidence ENUM('low', 'medium', 'high') DEFAULT 'medium',
            element_selector VARCHAR(500),
            element_html TEXT,
            description TEXT NOT NULL,
            recommendation TEXT NOT NULL,
            help_url VARCHAR(500),
            line_number INT,
            column_number INT,
            xpath VARCHAR(1000),
            impact_score INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (page_audit_id) REFERENCES page_audits(id) ON DELETE CASCADE,
            INDEX idx_page_audit_id (page_audit_id),
            INDEX idx_wcag_criterion (wcag_criterion),
            INDEX idx_wcag_level (wcag_level),
            INDEX idx_severity (severity),
            INDEX idx_issue_type (issue_type)
        );
        ";

        try {
            $this->pdo->exec($sql);
            Logger::debug("Schema database creato/verificato con successo");
        } catch (PDOException $e) {
            Logger::error("Errore creazione schema database", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function auditSite($siteUrl, $maxPages = 100) {
        Logger::info("=== INIZIO AUDIT SITO ===", [
            'site_url' => $siteUrl,
            'max_pages' => $maxPages
        ]);

        $this->baseUrl = rtrim($siteUrl, '/');
        $this->maxPages = $maxPages;
        $this->visitedUrls = [];

        // Crea sessione di audit
        try {
            $stmt = $this->pdo->prepare("INSERT INTO audit_sessions (site_url, max_pages_limit) VALUES (?, ?)");
            $stmt->execute([$siteUrl, $maxPages]);
            $sessionId = $this->pdo->lastInsertId();
            Logger::info("Sessione audit creata", ['session_id' => $sessionId]);
        } catch (PDOException $e) {
            Logger::error("Errore creazione sessione audit", ['error' => $e->getMessage()]);
            throw new Exception("Errore creazione sessione audit: " . $e->getMessage());
        }

        try {
            $urlsToVisit = [$this->baseUrl];
            $totalIssues = 0;
            $pagesAudited = 0;

            Logger::info("Avvio crawling", ['starting_url' => $this->baseUrl]);
            $this->showProgressStart($siteUrl);

            while (!empty($urlsToVisit) && $pagesAudited < $this->maxPages) {
                $currentUrl = array_shift($urlsToVisit);

                if (in_array($currentUrl, $this->visitedUrls)) {
                    Logger::debug("URL già visitato, skip", ['url' => $currentUrl]);
                    continue;
                }

                $this->visitedUrls[] = $currentUrl;
                Logger::debug("Elaborazione URL", [
                    'url' => $currentUrl,
                    'progress' => $pagesAudited + 1,
                    'total' => $this->maxPages
                ]);

                $this->updateProgress($currentUrl, $pagesAudited, $maxPages);

                $pageAudit = $this->auditPage($currentUrl, $sessionId);
                if ($pageAudit) {
                    $totalIssues += $pageAudit['total_issues'];
                    $pagesAudited++;

                    Logger::info("Pagina auditata", [
                        'url' => $currentUrl,
                        'issues_found' => $pageAudit['total_issues'],
                        'pages_completed' => $pagesAudited
                    ]);

                    // Trova nuovi link
                    $newUrls = $this->extractLinks($pageAudit['content']);
                    $newUrlsCount = 0;
                    foreach ($newUrls as $newUrl) {
                        if (!in_array($newUrl, $this->visitedUrls) && !in_array($newUrl, $urlsToVisit)) {
                            $urlsToVisit[] = $newUrl;
                            $newUrlsCount++;
                        }
                    }

                    if ($newUrlsCount > 0) {
                        Logger::debug("Nuovi link trovati", [
                            'new_urls' => $newUrlsCount,
                            'total_queue' => count($urlsToVisit)
                        ]);
                    }
                } else {
                    Logger::warning("Audit pagina fallito", ['url' => $currentUrl]);
                }
            }

            // Aggiorna sessione - determina se è veramente completata o solo pausata
            $hasMoreUrls = !empty($urlsToVisit); // Ci sono ancora URL da visitare?
            $reachedLimit = ($pagesAudited >= $this->maxPages); // Abbiamo raggiunto il limite?
            
            // Determina lo status corretto
            if ($reachedLimit && $hasMoreUrls) {
                $status = 'paused'; // Raggiunto limite ma ci sono ancora pagine
                Logger::info("Sessione pausata - raggiunto limite batch", [
                    'session_id' => $sessionId,
                    'pages_audited' => $pagesAudited,
                    'urls_remaining' => count($urlsToVisit)
                ]);
            } else {
                $status = 'completed'; // Scansione veramente completata
                Logger::info("Sessione completata - nessun URL rimanente", [
                    'session_id' => $sessionId,
                    'pages_audited' => $pagesAudited
                ]);
            }
            
            try {
                $stmt = $this->pdo->prepare("UPDATE audit_sessions SET end_time = NOW(), total_pages = ?, total_issues = ?, status = ? WHERE id = ?");
                $stmt->execute([$pagesAudited, $totalIssues, $status, $sessionId]);
                Logger::info("Sessione audit aggiornata", [
                    'session_id' => $sessionId,
                    'pages_audited' => $pagesAudited,
                    'total_issues' => $totalIssues
                ]);
            } catch (PDOException $e) {
                Logger::error("Errore aggiornamento sessione", ['error' => $e->getMessage()]);
            }

            $this->showProgressComplete($pagesAudited, $totalIssues);

            Logger::info("=== AUDIT COMPLETATO ===", [
                'session_id' => $sessionId,
                'pages_audited' => $pagesAudited,
                'total_issues' => $totalIssues,
                'urls_visited' => count($this->visitedUrls)
            ]);

            return $sessionId;

        } catch (Exception $e) {
            Logger::error("Errore durante audit", [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'pages_completed' => $pagesAudited ?? 0
            ]);

            try {
                $stmt = $this->pdo->prepare("UPDATE audit_sessions SET status = 'error' WHERE id = ?");
                $stmt->execute([$sessionId]);
            } catch (PDOException $dbError) {
                Logger::error("Errore aggiornamento status sessione", ['error' => $dbError->getMessage()]);
            }

            throw $e;
        }
    }

    private function auditPage($url, $sessionId) {
        Logger::debug("Inizio audit pagina", ['url' => $url]);
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Includo WCAG Accessibility Auditor 2.1',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_MAXREDIRS => 5
        ]);

        $content = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = microtime(true) - $startTime;
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $curlError = curl_error($ch);
        curl_close($ch);

        Logger::debug("Response ricevuta", [
            'url' => $url,
            'status_code' => $statusCode,
            'response_time' => round($responseTime, 3),
            'content_length' => strlen($content ?: ''),
            'redirects' => $redirectCount,
            'curl_error' => $curlError
        ]);

        if (!$content || $statusCode >= 400) {
            Logger::warning("Pagina non accessibile", [
                'url' => $url,
                'status_code' => $statusCode,
                'curl_error' => $curlError
            ]);
            return null;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!empty($errors)) {
            Logger::debug("Errori parsing HTML", [
                'url' => $url,
                'errors_count' => count($errors)
            ]);
        }

        $xpath = new DOMXPath($dom);

        // Estrai metadati
        $pageData = $this->extractPageMetadata($xpath, $content);
        Logger::debug("Metadati estratti", array_merge(['url' => $url], $pageData));

        // Inserisci audit pagina
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO page_audits
                (session_id, url, title, response_time, status_code, content_length, final_url,
                 redirects_count, meta_description, h1_count, img_count, link_count, form_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sessionId, $url, $pageData['title'], $responseTime, $statusCode, strlen($content),
                $finalUrl, $redirectCount, $pageData['meta_description'], $pageData['h1_count'],
                $pageData['img_count'], $pageData['link_count'], $pageData['form_count']
            ]);
            $pageAuditId = $this->pdo->lastInsertId();
            Logger::debug("Page audit record creato", [
                'page_audit_id' => $pageAuditId,
                'url' => $url
            ]);
        } catch (PDOException $e) {
            Logger::error("Errore inserimento page audit", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }

        // Esegui controlli WCAG
        try {
            Logger::debug("Avvio controlli WCAG", ['url' => $url]);
            require_once 'WCAGChecker.php';
            $checker = new WCAGChecker($dom, $xpath, $content, $url);
            $issues = $checker->performAllChecks();
            Logger::info("Controlli WCAG completati", [
                'url' => $url,
                'issues_found' => count($issues)
            ]);
        } catch (Exception $e) {
            Logger::error("Errore controlli WCAG", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            $issues = [];
        }

        // Salva problemi
        $totalIssues = 0;
        $levelCounts = ['A' => 0, 'AA' => 0, 'AAA' => 0];

        foreach ($issues as $issue) {
            try {
                $this->saveIssue($pageAuditId, $issue);
                $totalIssues++;
                $levelCounts[$issue['level']]++;
            } catch (Exception $e) {
                Logger::warning("Errore salvataggio issue", [
                    'url' => $url,
                    'issue_type' => $issue['type'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Aggiorna contatori
        try {
            $stmt = $this->pdo->prepare("
                UPDATE page_audits
                SET total_issues = ?, wcag_level_a_issues = ?, wcag_level_aa_issues = ?, wcag_level_aaa_issues = ?
                WHERE id = ?
            ");
            $stmt->execute([$totalIssues, $levelCounts['A'], $levelCounts['AA'], $levelCounts['AAA'], $pageAuditId]);

            Logger::debug("Contatori aggiornati", [
                'page_audit_id' => $pageAuditId,
                'total_issues' => $totalIssues,
                'level_counts' => $levelCounts
            ]);
        } catch (PDOException $e) {
            Logger::error("Errore aggiornamento contatori", [
                'page_audit_id' => $pageAuditId,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'page_audit_id' => $pageAuditId,
            'total_issues' => $totalIssues,
            'content' => $content
        ];
    }

    private function extractPageMetadata($xpath, $content) {
        $titleNodes = $xpath->query('//title');
        $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';

        $metaDescNodes = $xpath->query('//meta[@name="description"]');
        $metaDescription = $metaDescNodes->length > 0 ? $metaDescNodes->item(0)->getAttribute('content') : '';

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'h1_count' => $xpath->query('//h1')->length,
            'img_count' => $xpath->query('//img')->length,
            'link_count' => $xpath->query('//a[@href]')->length,
            'form_count' => $xpath->query('//form')->length
        ];
    }

    private function saveIssue($pageAuditId, $issue) {
        $errorMessage = getErrorMessage($issue['type']);

        $stmt = $this->pdo->prepare("
            INSERT INTO accessibility_issues
            (page_audit_id, issue_type, wcag_criterion, wcag_level, severity, element_selector,
             description, recommendation, line_number, help_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pageAuditId,
            $issue['type'],
            $issue['criterion'],
            $issue['level'],
            $issue['severity'],
            $issue['selector'],
            $issue['description'],
            $issue['recommendation'],
            $issue['line_number'],
            $errorMessage['help_url'] ?? null
        ]);
    }

    private function extractLinks($content) {
        Logger::debug("Estrazione link dalla pagina");

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        $xpath = new DOMXPath($dom);

        $links = [];
        $linkElements = $xpath->query('//a[@href]');

        foreach ($linkElements as $link) {
            $href = $link->getAttribute('href');
            $absoluteUrl = $this->makeAbsoluteUrl($href);

            if ($this->isInternalUrl($absoluteUrl) && $this->isValidUrl($absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }

        $uniqueLinks = array_unique($links);
        Logger::debug("Link estratti", [
            'total_links_found' => count($links),
            'unique_internal_links' => count($uniqueLinks)
        ]);

        return $uniqueLinks;
    }

    private function makeAbsoluteUrl($url) {
    // Validazione input
    if (empty($url) || !is_string($url)) {
        Logger::debug("makeAbsoluteUrl: URL vuoto", ['url' => $url]);
        return '';
    }
    
    // Se è già assoluto
    if (strpos($url, 'http') === 0) {
        return $url;
    }
    
    // Se inizia con /
    if (strpos($url, '/') === 0) {
        $parsed = parse_url($this->baseUrl);
        if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
            return $parsed['scheme'] . '://' . $parsed['host'] . $url;
        }
    }
    
    // URL relativo
    if (!empty($this->baseUrl)) {
        return $this->baseUrl . '/' . ltrim($url, '/');
    }
    
    return $url;
}

    private function isInternalUrl($url) {
        $baseHost = parse_url($this->baseUrl, PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        return $baseHost === $urlHost &&
               strpos($url, '#') !== 0 &&
               strpos($url, 'mailto:') !== 0 &&
               strpos($url, 'tel:') !== 0 &&
               !$this->isFileDownload($url);
    }

    private function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isFileDownload($url) {
    // Validazione input
    if (empty($url) || !is_string($url)) {
        Logger::debug("isFileDownload: URL vuoto o non valido", ['url' => $url]);
        return false;
    }
    
    $extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z'];
    
    try {
        // Estrai il path dall'URL
        $urlPath = parse_url($url, PHP_URL_PATH);
        
        // Se il path è vuoto, null o false, non è un file download
        if (empty($urlPath) || $urlPath === false) {
            return false;
        }
        
        // Estrai l'estensione in modo sicuro
        $pathInfo = pathinfo($urlPath);
        $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : '';
        
        $isDownload = in_array($extension, $extensions);
        
        if ($isDownload) {
            Logger::debug("File download rilevato", [
                'url' => $url,
                'extension' => $extension
            ]);
        }
        
        return $isDownload;
        
    } catch (Exception $e) {
        Logger::warning("Errore controllo file download", [
            'url' => $url,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

    private function showProgressStart($siteUrl) {
        echo "<div class='audit-progress'>";
        echo "<h3>🔍 Avvio Audit Includo</h3>";
        echo "<p>Analisi di: <strong>" . htmlspecialchars($siteUrl) . "</strong></p>";
        echo "<div id='progress-container'>";
        echo "<div id='progress-bar' style='width: 0%; background: linear-gradient(45deg, #007bff, #0056b3); height: 25px; border-radius: 10px; transition: width 0.3s; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;'></div>";
        echo "</div>";
        echo "<p id='current-page'>Preparazione...</p>";
        echo "</div>";

        if (ob_get_level()) {
            ob_flush();
            flush();
        }
    }

    private function updateProgress($currentUrl, $pagesAudited, $maxPages) {
        $percentage = round(($pagesAudited / $maxPages) * 100);
        echo "<script>";
        echo "if(document.getElementById('current-page')) document.getElementById('current-page').innerHTML = 'Analizzando: " . htmlspecialchars($currentUrl) . "';";
        echo "if(document.getElementById('progress-bar')) { ";
        echo "document.getElementById('progress-bar').style.width = '$percentage%'; ";
        echo "document.getElementById('progress-bar').innerHTML = '$percentage%'; ";
        echo "}";
        echo "</script>";

        if (ob_get_level()) {
            ob_flush();
            flush();
        }
    }

    private function showProgressComplete($pagesAudited, $totalIssues) {
        echo "<script>";
        echo "if(document.getElementById('progress-bar')) {";
        echo "document.getElementById('progress-bar').style.width = '100%';";
        echo "document.getElementById('progress-bar').innerHTML = '100%';";
        echo "}";
        echo "if(document.getElementById('current-page')) document.getElementById('current-page').innerHTML = '✅ Audit completato: $pagesAudited pagine analizzate, $totalIssues problemi rilevati';";
        echo "</script>";
    }

    // Metodi pubblici

    public function generateReport($sessionId, $format = 'html') {
        Logger::info("Richiesta generazione report", [
            'session_id' => $sessionId,
            'format' => $format
        ]);

        try {
            require_once 'ReportGenerator.php';
            $generator = new ReportGenerator($this->pdo);
            $report = $generator->generateReport($sessionId, $format);

            Logger::info("Report generato con successo", [
                'session_id' => $sessionId,
                'format' => $format,
                'size' => strlen($report)
            ]);

            return $report;
        } catch (Exception $e) {
            Logger::error("Errore generazione report", [
                'session_id' => $sessionId,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPDO() {
        return $this->pdo;
    }

    public function getAuditStatistics($sessionId) {
        Logger::debug("Richiesta statistiche audit", ['session_id' => $sessionId]);

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id, s.site_url, s.start_time, s.end_time, s.total_pages, s.total_issues, s.status,
                    COUNT(DISTINCT ai.wcag_criterion) as unique_violations,
                    AVG(pa.response_time) as avg_response_time,
                    COUNT(CASE WHEN pa.status_code >= 400 THEN 1 END) as error_pages,
                    COUNT(CASE WHEN ai.severity = 'critical' THEN 1 END) as critical_issues,
                    COUNT(CASE WHEN ai.severity = 'high' THEN 1 END) as high_issues,
                    COUNT(CASE WHEN ai.severity = 'medium' THEN 1 END) as medium_issues,
                    COUNT(CASE WHEN ai.severity = 'low' THEN 1 END) as low_issues
                FROM audit_sessions s
                LEFT JOIN page_audits pa ON s.id = pa.session_id
                LEFT JOIN accessibility_issues ai ON pa.id = ai.page_audit_id
                WHERE s.id = ?
                GROUP BY s.id
            ");

            $stmt->execute([$sessionId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stats) {
                Logger::debug("Statistiche recuperate", $stats);
            } else {
                Logger::warning("Nessuna statistica trovata", ['session_id' => $sessionId]);
            }

            return $stats;
        } catch (PDOException $e) {
            Logger::error("Errore recupero statistiche", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verifica se una sessione è veramente completata (tutto il sito scansionato)
     * o solo pausata (raggiunto limite batch)
     */
    private function isSessionTrulyComplete($sessionId) {
        Logger::debug("Verifica completamento reale sessione", ['session_id' => $sessionId]);
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_urls,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_urls,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as error_urls,
                    MAX(discovered_at) as last_discovery
                FROM crawl_queue 
                WHERE session_id = ?
            ");
            
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            // Una sessione è veramente completa se:
            // 1. Non ci sono URL in attesa (pending)
            // 2. Abbiamo processato almeno qualche URL
            $isComplete = ($result['pending_urls'] == 0 && $result['completed_urls'] > 0);
            
            Logger::debug("Risultato verifica completamento", [
                'session_id' => $sessionId,
                'pending_urls' => $result['pending_urls'],
                'completed_urls' => $result['completed_urls'],
                'is_truly_complete' => $isComplete
            ]);
            
            return $isComplete;
            
        } catch (PDOException $e) {
            Logger::error("Errore verifica completamento", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function cleanupOldAudits($daysOld = 30) {
        Logger::info("Pulizia audit vecchi", ['days_old' => $daysOld]);

        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM audit_sessions
                WHERE start_time < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status IN ('completed', 'error')
            ");

            $result = $stmt->execute([$daysOld]);
            $deletedRows = $stmt->rowCount();

            Logger::info("Pulizia completata", [
                'days_old' => $daysOld,
                'deleted_sessions' => $deletedRows
            ]);

            return $result;
        } catch (PDOException $e) {
            Logger::error("Errore pulizia audit", [
                'days_old' => $daysOld,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
?>
