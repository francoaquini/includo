<?php
/**
 * Includo - Sistema Professionale di Audit Accessibilità WCAG 2.2
 * European Accessibility Act Compliance
 * 
 * @version 2.2.0
 * @author Franco Aquini - Web Salad
 */

// Abilita visualizzazione errori per debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includi sistema di logging
require_once 'Logger.php';

Logger::info("=== INCLUDO AVVIO ===");
Logger::debug("Request Method: " . $_SERVER['REQUEST_METHOD']);
Logger::debug("POST Data: " . json_encode($_POST));
Logger::debug("GET Data: " . json_encode($_GET));

// Verifica sistema
$systemCheck = Logger::systemCheck();

// Includi i file necessari
try {
    Logger::debug("Caricamento config.php");
    if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}
    Logger::info("Config caricato con successo");
    
    Logger::debug("Caricamento IncludoAuditor.php");
    require_once 'IncludoAuditor.php';
    Logger::info("IncludoAuditor caricato con successo");
    
} catch (Exception $e) {
    Logger::critical("Errore caricamento file: " . $e->getMessage());
    die("Errore critico nel caricamento dei file. Controllare i log.");
}

// Gestione delle richieste
try {
    Logger::debug("Inizializzazione IncludoAuditor");
    $auditor = new IncludoAuditor(DB_HOST, DB_NAME, DB_USER, DB_PASS);
    Logger::info("IncludoAuditor inizializzato con successo");
    
    // DEBUG: Controlla POST
Logger::debug("POST audit_site check", [
    'audit_site_isset' => isset($_POST['audit_site']),
    'post_keys' => array_keys($_POST),
    'audit_site_value' => $_POST['audit_site'] ?? 'NOT_SET'
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_url'])) {
    Logger::info("ENTRANDO IN AUDIT LOGIC");
} else {
    Logger::warning("NON ENTRA IN AUDIT - audit_site non trovato");
}
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_url'])) {
        Logger::info("=== AVVIO NUOVO AUDIT ===");
        
        $siteUrl = $_POST['site_url'] ?? '';
        $maxPages = $_POST['max_pages'] ?? 50;
        
        Logger::info("Parametri audit", [
            'site_url' => $siteUrl,
            'max_pages' => $maxPages,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Validazione URL
        Logger::debug("Validazione URL: $siteUrl");
        $siteUrl = filter_var($siteUrl, FILTER_VALIDATE_URL);
        $maxPages = max(1, min(500, intval($maxPages)));
        
        if (!$siteUrl) {
            Logger::error("URL non valido fornito");
            throw new Exception("URL non valido. Inserire un URL completo come https://esempio.com");
        }
        
        Logger::info("URL validato: $siteUrl");
        
        // Verifica raggiungibilità sito
        Logger::debug("Test raggiungibilità sito");
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $siteUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Includo WCAG Auditor 2.1'
        ]);
        
        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        Logger::info("Test connessione completato", [
            'status_code' => $statusCode,
            'curl_error' => $curlError,
            'result_length' => strlen($result ?: '')
        ]);
        
        if ($result === false || $statusCode >= 400) {
            Logger::error("Sito non raggiungibile", [
                'status_code' => $statusCode,
                'curl_error' => $curlError
            ]);
            throw new Exception("Impossibile raggiungere il sito $siteUrl (Status: $statusCode, Error: $curlError). Verificare che sia accessibile pubblicamente.");
        }
        
        Logger::info("Sito raggiungibile, avvio interfaccia audit");
        
        echo "<div class='audit-container'>";
        echo "<style>
            .audit-container { 
                background: white; padding: 30px; border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin: 20px 0;
            }
            #progress-container { 
                background: #e9ecef; border-radius: 10px; overflow: hidden;
                margin: 20px 0; height: 30px;
            }
            #progress-bar { 
                height: 100%; background: linear-gradient(45deg, #007bff, #0056b3);
                transition: width 0.5s ease; display: flex; align-items: center;
                justify-content: center; color: white; font-weight: bold;
            }
            #current-page { 
                font-size: 1.1em; margin: 15px 0; padding: 10px;
                background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;
            }
            .debug-info {
                background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 10px 0;
                font-family: monospace; font-size: 0.9em;
            }
        </style>";
        
        // Mostra info debug se necessario
        if (isset($_GET['debug'])) {
            echo "<div class='debug-info'>";
            echo "<strong>🔧 Informazioni Debug:</strong><br>";
            echo "URL validato: $siteUrl<br>";
            echo "Pagine max: $maxPages<br>";
            echo "Status HTTP: $statusCode<br>";
            echo "Log file: " . Logger::getLogFile() . "<br>";
            echo "</div>";
        }
        
        Logger::info("Avvio audit effettivo");
        
        try {
            $sessionId = $auditor->auditSite($siteUrl, $maxPages);
            Logger::info("Audit completato con successo", ['session_id' => $sessionId]);
        } catch (Exception $e) {
            Logger::error("Errore durante audit: " . $e->getMessage());
            throw $e;
        }
        
        echo "</div>";
        echo "<div class='audit-complete'>";
        echo "<h3>✅ Audit Completato con Successo!</h3>";
        echo "<p><strong>Sessione ID:</strong> $sessionId</p>";
        
        // Statistiche finali
        try {
            Logger::debug("Recupero statistiche finali");
            $stats = $auditor->getAuditStatistics($sessionId);
            if ($stats) {
                Logger::info("Statistiche recuperate", $stats);
                echo "<div style='margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 10px;'>";
                echo "<h4>📊 Riepilogo Risultati:</h4>";
                echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;'>";
                echo "<div><strong>Pagine analizzate:</strong> {$stats['total_pages']}</div>";
                echo "<div><strong>Problemi totali:</strong> {$stats['total_issues']}</div>";
                echo "<div><strong>Problemi critici:</strong> {$stats['critical_issues']}</div>";
                echo "<div><strong>Criteri violati:</strong> {$stats['unique_violations']}</div>";
                echo "<div><strong>Tempo medio:</strong> " . round($stats['avg_response_time'], 2) . "s</div>";
                echo "<div><strong>Pagine con errori:</strong> {$stats['error_pages']}</div>";
                echo "</div></div>";
            }
        } catch (Exception $e) {
            Logger::warning("Errore recupero statistiche: " . $e->getMessage());
        }
        
        echo "<div class='report-buttons' style='margin-top: 25px;'>";
        echo "<a href='?report=$sessionId' class='btn btn-primary'>📊 Visualizza Report Completo</a>";
        echo "<a href='?report=$sessionId&format=json' class='btn btn-secondary'>💾 Scarica JSON</a>";
        echo "<a href='?report=$sessionId&format=csv' class='btn btn-secondary'>📋 Esporta CSV</a>";
        echo "<a href='accessibility_statement.php?session_id=$sessionId&lang=it' class='btn btn-secondary'>📄 Dichiarazione (IT)</a>";
        echo "<a href='accessibility_statement.php?session_id=$sessionId&lang=en' class='btn btn-secondary'>📄 Statement (EN)</a>";
        echo "<a href='accessibility_statement.php?session_id=$sessionId&lang=it&export=1' class='btn btn-secondary'>⬇️ Esporta HTML</a>";

        
        // Link debug
        if (isset($_GET['debug'])) {
            echo "<a href='?debug=1&view_log=1' class='btn btn-secondary'>📋 Visualizza Log</a>";
        }
        
        echo "</div>";
        echo "</div>";
        
        echo "<style>
            .audit-complete { 
                background: linear-gradient(135deg, #d4edda, #c3e6cb); 
                border-left: 5px solid #28a745; padding: 30px; border-radius: 15px;
                margin: 20px 0; box-shadow: 0 5px 20px rgba(40,167,69,0.2);
            }
            .btn { 
                display: inline-block; padding: 12px 20px; margin: 8px;
                text-decoration: none; border-radius: 8px; font-weight: 500;
                transition: all 0.3s;
            }
            .btn-primary { 
                background: linear-gradient(45deg, #007bff, #0056b3); color: white; 
                font-size: 1.1em; padding: 15px 25px;
            }
            .btn-secondary { 
                background: #6c757d; color: white; 
            }
            .btn:hover { 
                transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); 
            }
        </style>";
        
        Logger::info("=== AUDIT COMPLETATO ===");
    }
    
    if (isset($_GET['report'])) {
        Logger::info("Richiesta generazione report");
        
        $sessionId = intval($_GET['report']);
        $format = strtolower($_GET['format'] ?? 'html');
        
        Logger::debug("Parametri report", [
            'session_id' => $sessionId,
            'format' => $format
        ]);
        
        if ($sessionId <= 0) {
            Logger::error("ID sessione non valido: $sessionId");
            throw new Exception("ID sessione non valido");
        }
        
        try {
            Logger::debug("Generazione report formato: $format");
            $report = $auditor->generateReport($sessionId, $format);
            
            // INCLUDO_STATEMENT_BANNER: inject quick links for Accessibility Statement in HTML reports
            if ($format === 'html') {
                $banner = "<div style='padding:14px 16px;margin:14px 0;border-radius:14px;border:2px solid #c62828;background:linear-gradient(90deg,#ffebee,#ffffff);box-shadow:0 10px 35px rgba(198,40,40,.18)'>"
                        . "<strong>⚠️ Dichiarazione di accessibilità:</strong> "
                        . "<a href='accessibility_statement.php?session_id={$sessionId}&lang=it' style='margin-left:10px'>Apri (IT)</a>"
                        . " · <a href='accessibility_statement.php?session_id={$sessionId}&lang=en'>Open (EN)</a>"
                        . " · <a href='accessibility_statement.php?session_id={$sessionId}&lang=it&export=1'>Export HTML</a>"
                        . "</div>";
                // Insert banner right after <body> if possible
                $report = preg_replace('/<body[^>]*>/i', '$0' . $banner, $report, 1);
            }
Logger::info("Report generato con successo", [
                'session_id' => $sessionId,
                'format' => $format,
                'size' => strlen($report)
            ]);
        } catch (Exception $e) {
            Logger::error("Errore generazione report: " . $e->getMessage());
            throw $e;
        }
        
        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="includo_audit_' . $sessionId . '_' . date('Ymd_His') . '.json"');
            echo $report;
            exit;
        } elseif ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="includo_audit_' . $sessionId . '_' . date('Ymd_His') . '.csv"');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM per Excel
            echo $report;
            exit;
        } else {
            echo $report;
            exit;
        }
    }
    
    // Visualizza log se richiesto
    if (isset($_GET['view_log']) && isset($_GET['debug'])) {
        echo "<div style='background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
        echo "<h3>📋 Log Sistema</h3>";
        $logContent = file_get_contents(Logger::getLogFile());
        echo "<pre style='background: #333; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 400px;'>";
        echo htmlspecialchars($logContent);
        echo "</pre>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    Logger::logException($e, [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    echo "<div class='error-container'>";
    echo "<h3>❌ Errore durante l'operazione</h3>";
    echo "<p><strong>Dettaglio:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<div class='error-help'>";
    echo "<h4>💡 Possibili soluzioni:</h4>";
    echo "<ul>";
    echo "<li>Verificare la configurazione del database in <code>config.php</code></li>";
    echo "<li>Assicurarsi che il sito target sia accessibile pubblicamente</li>";
    echo "<li>Controllare i permessi delle directory (reports/, logs/, cache/)</li>";
    echo "<li>Verificare le estensioni PHP: curl, dom, libxml, json, pdo_mysql</li>";
    echo "<li>Controllare il file di log: <code>" . Logger::getLogFile() . "</code></li>";
    echo "</ul>";
    echo "</div>";
    
    // Informazioni debug
    echo "<details style='margin-top: 20px;'>";
    echo "<summary style='cursor: pointer; font-weight: bold;'>🔧 Informazioni Debug (clicca per espandere)</summary>";
    echo "<div style='background: #f8f9fa; padding: 15px; margin-top: 10px; border-radius: 5px;'>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Riga:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Trace:</strong><br>";
    echo "<pre style='background: #333; color: #fff; padding: 10px; border-radius: 3px; font-size: 0.8em; overflow-x: auto;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
    echo "</div>";
    echo "</details>";
    
    echo "</div>";
    
    echo "<style>
        .error-container { 
            background: linear-gradient(135deg, #f8d7da, #f1aeb5); 
            border-left: 5px solid #dc3545; padding: 25px; border-radius: 15px;
            margin: 20px 0; box-shadow: 0 5px 20px rgba(220,53,69,0.2);
        }
        .error-help { 
            margin-top: 20px; padding: 20px; 
            background: rgba(255,255,255,0.8); border-radius: 10px;
        }
        .error-help code { 
            background: #f8f9fa; padding: 2px 6px; 
            border-radius: 4px; font-family: monospace;
        }
    </style>";
}

// Interfaccia web principale
// ========================================
// GESTIONE PAGINE DEL MENU
// ========================================

// Determina quale pagina mostrare
$currentPage = $_GET['page'] ?? 'home';

// Solo mostra l'interfaccia se non ci sono POST o GET specifici
if (!isset($_POST['audit_site']) && !isset($_GET['report'])) {
    
    // Gestisci le diverse pagine
    switch ($currentPage) {
        case 'sessions':
            // Pagina storico scansioni
            require_once 'pages/sessions.php';
            exit;
            break;
            
        case 'new':
            // Pagina nuova scansione - per ora usa la home
            $showInterface = true;
            break;
            
        case 'help':
            // Pagina guida - per ora usa la home
            $showInterface = true;
            break;
            
        default:
            // Home page
            $showInterface = true;
            break;
    }
} else {
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Includo - Sistema Professionale Audit Accessibilità WCAG 2.2</title>
    <meta name="description" content="Includo - Sistema completo per audit di accessibilità web conforme WCAG 2.2 e European Accessibility Act.">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1000px; margin: 0 auto; padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; color: #333;
        }
        .container {
            background: white; padding: 40px; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center; margin-bottom: 40px;
        }
        .header h1 {
            font-size: 3.5em; margin: 0; 
            background: linear-gradient(45deg, #007bff, #0056b3);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header .subtitle {
            color: #666; margin-top: 15px; font-size: 1.3em; font-weight: 300;
        }
        .header .version {
            color: #888; font-size: 0.9em; margin-top: 10px;
        }
        .debug-panel {
            background: #e3f2fd; border: 1px solid #2196f3; 
            padding: 15px; border-radius: 8px; margin-bottom: 20px;
        }
        .debug-panel h4 { margin: 0 0 10px 0; color: #1976d2; }
        .debug-info { font-family: monospace; font-size: 0.9em; }
        .form-group {
            margin-bottom: 30px;
        }
        label {
            display: block; margin-bottom: 10px; font-weight: 600;
            color: #333; font-size: 1.15em;
        }
        input[type="url"], input[type="number"] {
            width: 100%; padding: 18px; border: 2px solid #e1e5e9;
            border-radius: 12px; font-size: 16px; transition: all 0.3s;
            background: #fafbfc;
        }
        input[type="url"]:focus, input[type="number"]:focus {
            outline: none; border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0,123,255,0.1);
            background: white;
        }
        .btn-primary {
            width: 100%; padding: 20px; 
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white; border: none; border-radius: 12px; 
            font-size: 18px; font-weight: 600; cursor: pointer; 
            transition: all 0.3s; text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-primary:hover {
            transform: translateY(-3px); 
            box-shadow: 0 10px 25px rgba(0,123,255,0.4);
        }
        .info-box {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 5px solid #2196f3; padding: 25px; border-radius: 12px;
            margin-bottom: 30px;
        }
        .warning-box {
            background: linear-gradient(135deg, #fff3e0, #ffcc02);
            border-left: 5px solid #ff9800; padding: 25px; border-radius: 12px;
            margin-bottom: 30px;
        }
        .feature-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px; margin: 40px 0;
        }
        .feature-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef); 
            padding: 25px; border-radius: 15px;
            border: 1px solid #dee2e6; transition: transform 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .feature-card h4 {
            color: #007bff; margin-bottom: 20px; font-size: 1.3em;
        }
        .feature-list {
            list-style: none; padding: 0;
        }
        .feature-list li {
            padding: 10px 0; border-bottom: 1px solid #dee2e6;
            position: relative; padding-left: 30px;
        }
        .feature-list li:before {
            content: "✓"; position: absolute; left: 0;
            color: #28a745; font-weight: bold; font-size: 1.2em;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin: 40px 0;
        }
        .stat-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white; padding: 20px; border-radius: 15px;
            text-align: center; font-weight: bold;
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        .stat-number {
            font-size: 2.5em; display: block;
        }
        .footer-info {
            background: linear-gradient(135deg, #343a40, #495057); 
            color: white; padding: 30px;
            border-radius: 15px; text-align: center; margin-top: 40px;
        }
        .footer-info h3 {
            margin-top: 0; font-size: 1.8em;
        }
        .compliance-badges {
            display: flex; justify-content: center; gap: 15px;
            margin-top: 20px; flex-wrap: wrap;
        }
        .compliance-badge {
            background: rgba(255,255,255,0.2); padding: 8px 15px;
            border-radius: 8px; font-size: 0.9em; font-weight: 500;
        }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .container { padding: 25px; }
            .header h1 { font-size: 2.5em; }
            .feature-grid, .stats-grid { grid-template-columns: 1fr; }
            .compliance-badges { flex-direction: column; align-items: center; }
        }
        
        /* Menu di navigazione */
        .navbar {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
            padding: 15px 0; box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 1000;
        }
        .nav-container {
            max-width: 1200px; margin: 0 auto; padding: 0 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .nav-brand {
            font-size: 1.8em; font-weight: bold;
            background: linear-gradient(45deg, #007bff, #0056b3);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; text-decoration: none;
        }
        .nav-menu {
            display: flex; gap: 0; list-style: none; margin: 0; padding: 0;
        }
        .nav-item {
            position: relative;
        }
        .nav-link {
            display: block; padding: 12px 20px; text-decoration: none;
            color: #333; font-weight: 500; border-radius: 8px;
            transition: all 0.3s; white-space: nowrap;
        }
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white; transform: translateY(-2px);
        }
        .nav-link.active {
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
    </style>
</head>
<body>

    <!-- Menu di navigazione -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="?" class="nav-brand">🎯 Includo</a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="?" class="nav-link <?php echo (!isset($_GET['page']) ? 'active' : ''); ?>">
                        🏠 Home
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=sessions" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'sessions' ? 'active' : ''); ?>">
                        📊 Storico Scansioni
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=new" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'new' ? 'active' : ''); ?>">
                        🚀 Nuova Scansione
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=help" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'help' ? 'active' : ''); ?>">
                        💡 Guida
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <div class="container">
        <div class="header">
            <h1>🎯 Includo</h1>
            <div class="subtitle">Sistema Professionale di Audit Accessibilità Web</div>
            <div class="version">Versione 2.1.0 | Conforme WCAG 2.2 & European Accessibility Act</div>
        </div>
        
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-panel">
            <h4>🔧 Pannello Debug</h4>
            <div class="debug-info">
                <strong>Sistema:</strong><br>
                PHP: <?= PHP_VERSION ?> | 
                Estensioni: <?= implode(', ', array_filter(['curl' => extension_loaded('curl'), 'dom' => extension_loaded('dom'), 'pdo_mysql' => extension_loaded('pdo_mysql')], function($v) { return $v; })) ?><br>
                <strong>Database:</strong> <?= defined('DB_HOST') ? DB_HOST . ':' . DB_NAME : 'Non configurato' ?><br>
                <strong>Log:</strong> <?= Logger::getLogFile() ?><br>
                <strong>Directory scrivibili:</strong> 
                logs: <?= is_writable(dirname(Logger::getLogFile())) ? '✅' : '❌' ?>, 
                current: <?= is_writable(__DIR__) ? '✅' : '❌' ?><br>
                <a href="?debug=1&view_log=1" style="color: #1976d2;">📋 Visualizza Log Completo</a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>🚀 La Soluzione Completa per l'Accessibilità Web</h3>
            <p><strong>Includo</strong> è il sistema più avanzato per verificare la conformità del tuo sito web ai criteri WCAG 2.2 e all'European Accessibility Act. Progettato per sviluppatori, aziende, enti pubblici e professionisti dell'accessibilità digitale.</p>
        </div>
        
        <form method="post" action="" novalidate>
            <div class="form-group">
                <label for="site_url">🌐 URL del sito da analizzare</label>
                <input type="url" id="site_url" name="site_url" required 
                       placeholder="https://www.iltuosito.com" 
                       value="<?php echo htmlspecialchars($_POST['site_url'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="max_pages">📄 Numero massimo di pagine da analizzare</label>
                <input type="number" id="max_pages" name="max_pages" 
                       min="1" max="500" value="50">
                <small style="color: #666; font-size: 0.95em; margin-top: 8px; display: block;">
                    📍 <strong>Raccomandazione:</strong> 50-100 pagine per audit completi, fino a 500 per analisi approfondite.
                </small>
            </div>
            
            <button type="submit" name="audit_site" class="btn-primary">
                🔍 Avvia Audit Professionale
            </button>
        </form>
        
        <div class="warning-box">
            <strong>⚠️ Informazioni Importanti:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>L'audit può richiedere da 2 a 15 minuti a seconda delle dimensioni del sito</li>
                <li>Il sito deve essere accessibile pubblicamente (non protetto da password)</li>
                <li>Verranno analizzati automaticamente tutti i link interni raggiungibili</li>
                <li>I risultati includeranno raccomandazioni specifiche per ogni problema rilevato</li>
            </ul>
            <p style="margin-top: 15px;">
                <strong>🔧 Problemi?</strong> Aggiungi <code>?debug=1</code> all'URL per informazioni di debug dettagliate.
            </p>
        </div>
        
        <div class="feature-grid">
            <div class="feature-card">
                <h4>📋 Criteri WCAG Completi</h4>
                <ul class="feature-list">
                    <li>35+ criteri WCAG 2.2 verificati automaticamente</li>
                    <li>Controllo contrasto colori preciso</li>
                    <li>Analisi struttura semantica HTML5</li>
                    <li>Verifica accessibilità tastiera e focus</li>
                    <li>Controllo implementazione ARIA</li>
                    <li>Validazione form e gestione errori</li>
                    <li>Analisi multimedia e contenuti dinamici</li>
                </ul>
            </div>
            
            <div class="feature-card">
                <h4>🏛️ European Accessibility Act</h4>
                <ul class="feature-list">
                    <li>Controlli specifici per settore pubblico</li>
                    <li>Verifiche e-commerce e servizi bancari</li>
                    <li>Conformità telecomunicazioni e media</li>
                    <li>Dichiarazione di accessibilità obbligatoria</li>
                    <li>Meccanismi di feedback per cittadini</li>
                    <li>Preparazione scadenza 28 giugno 2025</li>
                </ul>
            </div>
            
            <div class="feature-card">
                <h4>📊 Report e Analytics</h4>
                <ul class="feature-list">
                    <li>Report HTML interattivi con grafici</li>
                    <li>Export JSON strutturato per API</li>
                    <li>File CSV per analisi in Excel</li>
                    <li>Statistiche dettagliate per livello WCAG</li>
                    <li>Prioritizzazione problemi per gravità</li>
                    <li>Sistema di logging completo</li>
                    <li>Debug avanzato per troubleshooting</li>
                </ul>
            </div>
            
            <div class="feature-card">
                <h4>🔧 Tecnologia Avanzata</h4>
                <ul class="feature-list">
                    <li>Crawler intelligente ottimizzato</li>
                    <li>Analisi fino a 500 pagine per audit</li>
                    <li>Algoritmi di quality assessment</li>
                    <li>Database MySQL ottimizzato</li>
                    <li>Logging completo operazioni</li>
                    <li>Sistema di debug integrato</li>
                    <li>Interfaccia responsive e accessibile</li>
                </ul>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-badge">
                <span class="stat-number">35+</span>
                <div>Criteri WCAG</div>
            </div>
            <div class="stat-badge">
                <span class="stat-number">4</span>
                <div>Settori EAA</div>
            </div>
            <div class="stat-badge">
                <span class="stat-number">500</span>
                <div>Pagine Max</div>
            </div>
            <div class="stat-badge">
                <span class="stat-number">3</span>
                <div>Formati Export</div>
            </div>
        </div>
        
        <div class="footer-info">
            <h3>🎯 Includo - Eccellenza nell'Accessibilità Digitale</h3>
            <p>Il sistema più avanzato per audit di accessibilità web, sviluppato da esperti per garantire la piena conformità normativa e la migliore esperienza utente.</p>
            
            <div class="compliance-badges">
                <div class="compliance-badge">WCAG 2.2 Level AA</div>
                <div class="compliance-badge">European Accessibility Act</div>
                <div class="compliance-badge">EN 301 549</div>
                <div class="compliance-badge">Direttiva UE 2016/2102</div>
                <div class="compliance-badge">Section 508</div>
            </div>
            
            <p style="margin-top: 25px; font-size: 1.1em;">
                <strong>⏰ Scadenza EAA: 28 giugno 2025</strong><br>
                Assicurati che il tuo sito sia conforme in tempo con Includo!
            </p>
        </div>
    </div>
    
    <script>
        // Validazione form
        document.querySelector('form').addEventListener('submit', function(e) {
            const urlInput = document.getElementById('site_url');
            const pagesInput = document.getElementById('max_pages');
            
            console.log('Form submission started');
            
            try {
                new URL(urlInput.value);
            } catch {
                alert('⚠️ Inserire un URL valido (es: https://esempio.com)');
                e.preventDefault();
                urlInput.focus();
                return;
            }
            
            const pages = parseInt(pagesInput.value);
            if (pages < 1 || pages > 500) {
                alert('⚠️ Il numero di pagine deve essere tra 1 e 500');
                e.preventDefault();
                pagesInput.focus();
                return;
            }
            
            // Mostra loader
            const button = document.querySelector('.btn-primary');
            button.innerHTML = '🔄 Avvio Audit in Corso...';
            button.disabled = true;
            
            console.log('Form validated, submitting audit for:', urlInput.value);
        });
        
        // Animazioni
        window.addEventListener('load', function() {
            console.log('Page loaded, initializing animations');
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(function(stat, index) {
                setTimeout(function() {
                    stat.style.animation = 'pulse 2s ease-in-out infinite';
                }, index * 200);
            });
        });
        
        // CSS animazioni
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
        `;
        document.head.appendChild(style);
        
        document.querySelector('form').addEventListener('submit', function(e) {
    console.log('Form data being sent:');
    const formData = new FormData(this);
    for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
    }
});

    </script>
</body>
</html>
<?php
Logger::info("=== FINE RICHIESTA ===");
?>