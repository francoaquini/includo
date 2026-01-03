<?php
/**
 * Includo - Sistema di Audit Accessibilità WCAG 2.2
 * Classe principale per l'audit di accessibilità
 *
 * @version 2.2.0
 * @author Franco Aquini - Web Salad
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

require_once __DIR__ . '/Logger.php';

class IncludoAuditor
{
    private PDO $pdo;
    private string $baseUrl = '';
    private array $visitedUrls = [];
    private int $maxPages = 100;

    public function getAuditStatistics(int $sessionId): ?array
    {
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
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            Logger::error("Errore recupero statistiche audit", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function __construct(string $host, string $dbname, string $username, string $password)
    {
        Logger::info("Inizializzazione IncludoAuditor", [
            'host' => $host,
            'dbname' => $dbname,
            'username' => $username
        ]);

        try {
            $this->pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            Logger::info("Connessione database stabilita con successo");

            $this->initDatabase();
            Logger::info("Database inizializzato con successo");

        } catch (PDOException $e) {
            Logger::critical("Errore connessione database", [
                'error' => $e->getMessage(),
                'host' => $host,
                'dbname' => $dbname
            ]);
            throw new RuntimeException("Errore connessione database: " . $e->getMessage(), 0, $e);
        }
    }

    private function initDatabase(): void
    {
        Logger::debug("Inizializzazione schema database");

        $sql = "
        CREATE TABLE IF NOT EXISTS audit_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            site_url VARCHAR(255) NOT NULL,
            start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            end_time DATETIME NULL,
            total_pages INT DEFAULT 0,
            total_issues INT DEFAULT 0,
            status ENUM('running', 'paused', 'completed', 'error') DEFAULT 'running',
            user_agent VARCHAR(255) DEFAULT 'Includo WCAG Auditor 2.2',
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
            wcag_version ENUM('2.0', '2.1', '2.2') DEFAULT '2.2',
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

        CREATE TABLE IF NOT EXISTS crawl_queue (
            id INT PRIMARY KEY AUTO_INCREMENT,
            session_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            status ENUM('pending','completed','error') DEFAULT 'pending',
            discovered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            FOREIGN KEY (session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
            INDEX idx_session (session_id),
            INDEX idx_status (status),
            INDEX idx_url (url(100))
        );
        ";

        $this->pdo->exec($sql);

        // Ensure audit_sessions has a column to store remaining queue (JSON/text)
        try {
            $this->pdo->exec("ALTER TABLE audit_sessions ADD COLUMN IF NOT EXISTS remaining_queue TEXT NULL;");
        } catch (Throwable $e) {
            // ignore if ALTER not supported; resume will still work if column exists
            Logger::debug('Could not ensure remaining_queue column: ' . $e->getMessage());
        }
    }

    public function auditSite(string $siteUrl, int $maxPages = 100): int
    {
        Logger::info("=== INIZIO AUDIT SITO ===", [
            'site_url' => $siteUrl,
            'max_pages' => $maxPages
        ]);

        $this->baseUrl = rtrim($siteUrl, '/');
        $this->maxPages = max(1, $maxPages);
        $this->visitedUrls = [];

        $stmt = $this->pdo->prepare("INSERT INTO audit_sessions (site_url, max_pages_limit) VALUES (?, ?)");
        $stmt->execute([$siteUrl, $this->maxPages]);
        $sessionId = (int)$this->pdo->lastInsertId();

        $urlsToVisit = [$this->baseUrl];
        $totalIssues = 0;
        $pagesAudited = 0;

        $this->showProgressStart($siteUrl);

        try {
            while (!empty($urlsToVisit) && $pagesAudited < $this->maxPages) {
                $currentUrl = array_shift($urlsToVisit);
                if (!$currentUrl || in_array($currentUrl, $this->visitedUrls, true)) {
                    continue;
                }

                $this->visitedUrls[] = $currentUrl;
                $this->updateProgress($currentUrl, $pagesAudited, $this->maxPages);

                $pageAudit = $this->auditPage($currentUrl, $sessionId);
                if ($pageAudit) {
                    $totalIssues += (int)$pageAudit['total_issues'];
                    $pagesAudited++;

                    $newUrls = $this->extractLinks($pageAudit['content']);
                    foreach ($newUrls as $newUrl) {
                        if (!in_array($newUrl, $this->visitedUrls, true) && !in_array($newUrl, $urlsToVisit, true)) {
                            $urlsToVisit[] = $newUrl;
                        }
                    }
                }
            }

            $hasMoreUrls = !empty($urlsToVisit);
            $reachedLimit = ($pagesAudited >= $this->maxPages);
            $status = ($reachedLimit && $hasMoreUrls) ? 'paused' : 'completed';

            // Persist remaining queue (if any) so the session can be resumed later
            $remainingJson = $hasMoreUrls ? json_encode(array_values($urlsToVisit)) : null;

            $stmt = $this->pdo->prepare("UPDATE audit_sessions SET end_time = NOW(), total_pages = ?, total_issues = ?, status = ?, remaining_queue = ? WHERE id = ?");
            $stmt->execute([$pagesAudited, $totalIssues, $status, $remainingJson, $sessionId]);

            $this->showProgressComplete($pagesAudited, $totalIssues);

            return $sessionId;

        } catch (Throwable $e) {
            Logger::error("Errore durante audit", ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            $stmt = $this->pdo->prepare("UPDATE audit_sessions SET status = 'error' WHERE id = ?");
            $stmt->execute([$sessionId]);
            throw $e;
        }
    }

    private function auditPage(string $url, int $sessionId): ?array
    {
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Includo WCAG Accessibility Auditor 2.2',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_MAXREDIRS => 5
        ]);

        $content = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = microtime(true) - $startTime;
        $finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectCount = (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $curlError = (string)curl_error($ch);
        curl_close($ch);

        if (!$content || $statusCode >= 400) {
            Logger::warning("Pagina non accessibile", ['url' => $url, 'status_code' => $statusCode, 'curl_error' => $curlError]);
            return null;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $pageData = $this->extractPageMetadata($xpath);

        // Insert page audit
        $stmt = $this->pdo->prepare("
            INSERT INTO page_audits
            (session_id, url, title, response_time, status_code, content_length, final_url,
             redirects_count, meta_description, h1_count, img_count, link_count, form_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $url,
            $pageData['title'],
            $responseTime,
            $statusCode,
            strlen($content),
            $finalUrl,
            $redirectCount,
            $pageData['meta_description'],
            $pageData['h1_count'],
            $pageData['img_count'],
            $pageData['link_count'],
            $pageData['form_count']
        ]);

        $pageAuditId = (int)$this->pdo->lastInsertId();

        // WCAG checks
        $issues = [];
        try {
            require_once __DIR__ . '/WCAGChecker.php';
            $checker = new WCAGChecker($dom, $xpath, $content, $url);
            $issues = $checker->performAllChecks();
        } catch (Throwable $e) {
            Logger::error("Errore controlli WCAG", ['url' => $url, 'error' => $e->getMessage()]);
        }

        $totalIssues = 0;
        $levelCounts = ['A' => 0, 'AA' => 0, 'AAA' => 0];

        foreach ($issues as $issue) {
            try {
                $this->saveIssue($pageAuditId, $issue);
                $totalIssues++;
                $lvl = strtoupper((string)($issue['level'] ?? 'A'));
                if (isset($levelCounts[$lvl])) $levelCounts[$lvl]++;
            } catch (Throwable $e) {
                Logger::warning("Errore salvataggio issue", ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        $stmt = $this->pdo->prepare("UPDATE page_audits SET total_issues=?, wcag_level_a_issues=?, wcag_level_aa_issues=?, wcag_level_aaa_issues=? WHERE id=?");
        $stmt->execute([$totalIssues, $levelCounts['A'], $levelCounts['AA'], $levelCounts['AAA'], $pageAuditId]);

        return ['page_audit_id' => $pageAuditId, 'total_issues' => $totalIssues, 'content' => $content];
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * Resume an existing audit session using the saved remaining_queue.
     * Continues the audit appending page audits to the existing session.
     */
    public function resumeAudit(int $sessionId): int
    {
        Logger::info("Resume audit requested", ['session_id' => $sessionId]);

        $stmt = $this->pdo->prepare("SELECT site_url, max_pages_limit, total_pages, total_issues, remaining_queue FROM audit_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("Session not found: $sessionId");
        }

        $this->baseUrl = rtrim($row['site_url'], '/');
        $this->maxPages = max(1, (int)$row['max_pages_limit']);
        $pagesAudited = (int)$row['total_pages'];
        $totalIssues = (int)$row['total_issues'];

        // Reconstruct visitedUrls from page_audits
        $stmt = $this->pdo->prepare("SELECT url FROM page_audits WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $visited = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $this->visitedUrls = $visited ?: [];

        $urlsToVisit = [];
        if (!empty($row['remaining_queue'])) {
            $decoded = json_decode($row['remaining_queue'], true);
            if (is_array($decoded)) $urlsToVisit = $decoded;
        }

        $this->showProgressStart($this->baseUrl);

        try {
            while (!empty($urlsToVisit) && $pagesAudited < $this->maxPages) {
                $currentUrl = array_shift($urlsToVisit);
                if (!$currentUrl || in_array($currentUrl, $this->visitedUrls, true)) {
                    continue;
                }

                $this->visitedUrls[] = $currentUrl;
                $this->updateProgress($currentUrl, $pagesAudited, $this->maxPages);

                $pageAudit = $this->auditPage($currentUrl, $sessionId);
                if ($pageAudit) {
                    $totalIssues += (int)$pageAudit['total_issues'];
                    $pagesAudited++;

                    $newUrls = $this->extractLinks($pageAudit['content']);
                    foreach ($newUrls as $newUrl) {
                        if (!in_array($newUrl, $this->visitedUrls, true) && !in_array($newUrl, $urlsToVisit, true)) {
                            $urlsToVisit[] = $newUrl;
                        }
                    }
                }
            }

            $hasMoreUrls = !empty($urlsToVisit);
            $reachedLimit = ($pagesAudited >= $this->maxPages);
            $status = ($reachedLimit && $hasMoreUrls) ? 'paused' : 'completed';
            $remainingJson = $hasMoreUrls ? json_encode(array_values($urlsToVisit)) : null;

            $stmt = $this->pdo->prepare("UPDATE audit_sessions SET end_time = NOW(), total_pages = ?, total_issues = ?, status = ?, remaining_queue = ? WHERE id = ?");
            $stmt->execute([$pagesAudited, $totalIssues, $status, $remainingJson, $sessionId]);

            $this->showProgressComplete($pagesAudited, $totalIssues);

            return $sessionId;

        } catch (Throwable $e) {
            Logger::error("Errore durante resume audit", ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            $stmt = $this->pdo->prepare("UPDATE audit_sessions SET status = 'error' WHERE id = ?");
            $stmt->execute([$sessionId]);
            throw $e;
        }
    }

    private function extractPageMetadata(DOMXPath $xpath): array
    {
        $titleNodes = $xpath->query('//title');
        $title = ($titleNodes && $titleNodes->length > 0) ? trim($titleNodes->item(0)->textContent) : '';

        $metaDescNodes = $xpath->query('//meta[@name="description"]');
        $metaDescription = ($metaDescNodes && $metaDescNodes->length > 0)
            ? (string)$metaDescNodes->item(0)->getAttribute('content')
            : '';

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'h1_count' => $xpath->query('//h1')->length,
            'img_count' => $xpath->query('//img')->length,
            'link_count' => $xpath->query('//a[@href]')->length,
            'form_count' => $xpath->query('//form')->length
        ];
    }

    private function getIssueHelpUrl(string $issueType, string $criterion = ''): ?string
    {
        $issueType = strtolower(trim($issueType));

        $map = [
            'missing_alt' => 'https://www.w3.org/WAI/WCAG22/Understanding/non-text-content.html',
            'low_contrast' => 'https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html',
            'missing_label' => 'https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html',
        ];

        return $map[$issueType] ?? ($criterion !== '' ? 'https://www.w3.org/WAI/WCAG22/Understanding/' : null);
    }

    private function saveIssue(int $pageAuditId, array $issue): void
    {
        $type = (string)($issue['type'] ?? 'unknown');
        $criterion = (string)($issue['criterion'] ?? '0.0.0');
        $level = strtoupper((string)($issue['level'] ?? 'A'));
        $severity = strtolower((string)($issue['severity'] ?? 'low'));

        if (!in_array($level, ['A','AA','AAA'], true)) $level = 'A';
        if (!in_array($severity, ['low','medium','high','critical'], true)) $severity = 'low';

        $selector = isset($issue['selector']) && is_string($issue['selector']) ? $issue['selector'] : null;
        $description = (string)($issue['description'] ?? 'Issue detected');
        $recommendation = (string)($issue['recommendation'] ?? 'Review and fix according to WCAG.');
        $lineNumber = isset($issue['line_number']) ? (int)$issue['line_number'] : null;
        $helpUrl = isset($issue['help_url']) ? (string)$issue['help_url'] : $this->getIssueHelpUrl($type, $criterion);

        $stmt = $this->pdo->prepare("
            INSERT INTO accessibility_issues
            (page_audit_id, issue_type, wcag_criterion, wcag_level, severity, element_selector,
             description, recommendation, line_number, help_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $pageAuditId,
            $type,
            $criterion,
            $level,
            $severity,
            $selector,
            $description,
            $recommendation,
            $lineNumber,
            $helpUrl
        ]);
    }

    private function extractLinks(string $content): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $links = [];
        $linkElements = $xpath->query('//a[@href]');
        if (!$linkElements) return [];

        foreach ($linkElements as $link) {
            $href = (string)$link->getAttribute('href');
            $absoluteUrl = $this->makeAbsoluteUrl($href);

            if ($absoluteUrl !== '' && $this->isInternalUrl($absoluteUrl) && $this->isValidUrl($absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }

        return array_values(array_unique($links));
    }

    private function makeAbsoluteUrl($url): string
    {
        if (empty($url) || !is_string($url)) return '';

        if (strpos($url, 'http') === 0) return $url;
        if (strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0 || strpos($url, 'javascript:') === 0) return '';
        if (strpos($url, '#') === 0) return '';

        if (strpos($url, '/') === 0) {
            $parsed = parse_url($this->baseUrl);
            if ($parsed && isset($parsed['scheme'], $parsed['host'])) {
                return $parsed['scheme'] . '://' . $parsed['host'] . $url;
            }
        }

        return $this->baseUrl !== '' ? $this->baseUrl . '/' . ltrim($url, '/') : $url;
    }

    private function isInternalUrl(string $url): bool
    {
        $baseHost = (string)parse_url($this->baseUrl, PHP_URL_HOST);
        $urlHost = (string)parse_url($url, PHP_URL_HOST);

        if ($baseHost === '' || $urlHost === '') return false;
        return $baseHost === $urlHost && !$this->isFileDownload($url);
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isFileDownload($url): bool
    {
        if (empty($url) || !is_string($url)) return false;

        $extensions = ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','rar','7z'];
        $urlPath = parse_url($url, PHP_URL_PATH);
        if (empty($urlPath) || $urlPath === false) return false;

        $pathInfo = pathinfo($urlPath);
        $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : '';

        return in_array($extension, $extensions, true);
    }

    private function showProgressStart(string $siteUrl): void
    {
        echo "<div class='audit-progress'>";
        echo "<h3>🔍 Avvio Audit Includo</h3>";
        echo "<p>Analisi di: <strong>" . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . "</strong></p>";
        echo "<div id='progress-container'>";
        echo "<div id='progress-bar' style='width: 0%; background: linear-gradient(45deg, #007bff, #0056b3); height: 25px; border-radius: 10px; transition: width 0.3s; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;'></div>";
        echo "</div>";
        echo "<p id='current-page'>Preparazione...</p>";
        echo "</div>";

        if (ob_get_level()) { @ob_flush(); }
        @flush();
    }

    private function updateProgress(string $currentUrl, int $pagesAudited, int $maxPages): void
    {
        $maxPages = max(1, $maxPages);
        $percentage = (int)round(($pagesAudited / $maxPages) * 100);
        $safeUrl = htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');

        echo "<script>";
        echo "if(document.getElementById('current-page')) document.getElementById('current-page').innerHTML = 'Analizzando: {$safeUrl}';";
        echo "if(document.getElementById('progress-bar')) { ";
        echo "document.getElementById('progress-bar').style.width = '{$percentage}%'; ";
        echo "document.getElementById('progress-bar').innerHTML = '{$percentage}%'; ";
        echo "}";
        echo "</script>";

        if (ob_get_level()) { @ob_flush(); }
        @flush();
    }

    private function showProgressComplete(int $pagesAudited, int $totalIssues): void
    {
        $msg = "✅ Audit completato: {$pagesAudited} pagine analizzate, {$totalIssues} problemi rilevati";
        $safeMsg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');

        echo "<script>";
        echo "if(document.getElementById('progress-bar')) {";
        echo "document.getElementById('progress-bar').style.width = '100%';";
        echo "document.getElementById('progress-bar').innerHTML = '100%';";
        echo "}";
        echo "if(document.getElementById('current-page')) document.getElementById('current-page').innerHTML = '{$safeMsg}';";
        echo "</script>";
    }

    public function generateReport(int $sessionId, string $format = 'html'): string
    {
        require_once __DIR__ . '/ReportGenerator.php';
        $generator = new ReportGenerator($this->pdo);
        return $generator->generateReport($sessionId, $format);
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }
}
