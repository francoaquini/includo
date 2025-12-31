<?php
/**
 * Test Sistema Includo
 * Verifica configurazione e funzionamento
 */

// Abilita tutti gli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🧪 Test Sistema Includo</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .test-section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #007bff; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    pre { background: #333; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .test-item { margin: 10px 0; padding: 8px; background: rgba(255,255,255,0.7); border-radius: 4px; }
</style>";

$tests = [];
$errors = [];

// Test 1: Verifica file necessari
echo "<div class='test-section'>";
echo "<h2>📁 Test 1: Verifica File Necessari</h2>";

$requiredFiles = [
    'config.php' => 'File di configurazione',
    'Logger.php' => 'Sistema di logging',
    'IncludoAuditor.php' => 'Classe principale',
    'WCAGChecker.php' => 'Controlli WCAG',
    'ReportGenerator.php' => 'Generatore report'
];

foreach ($requiredFiles as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? '✅' : '❌';
    echo "<div class='test-item'>$status <strong>$file</strong> - $description</div>";
    
    if (!$exists) {
        $errors[] = "File mancante: $file";
    }
}
echo "</div>";

// Test 2: Verifica estensioni PHP
echo "<div class='test-section'>";
echo "<h2>🔧 Test 2: Estensioni PHP</h2>";

$requiredExtensions = [
    'curl' => 'Per scaricare pagine web',
    'dom' => 'Per parsing HTML',
    'libxml' => 'Per elaborazione XML/HTML',
    'json' => 'Per formato dati',
    'pdo_mysql' => 'Per database MySQL'
];

foreach ($requiredExtensions as $ext => $description) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "<div class='test-item'>$status <strong>$ext</strong> - $description</div>";
    
    if (!$loaded) {
        $errors[] = "Estensione PHP mancante: $ext";
    }
}

echo "<div class='test-item'><strong>Versione PHP:</strong> " . PHP_VERSION . "</div>";
echo "</div>";

// Test 3: Verifica configurazione
echo "<div class='test-section'>";
echo "<h2>⚙️ Test 3: Configurazione</h2>";

if (file_exists('config.php')) {
    require_once 'config.php';
    
    $configTests = [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'NON DEFINITO',
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'NON DEFINITO',
        'DB_USER' => defined('DB_USER') ? DB_USER : 'NON DEFINITO',
        'DB_PASS' => defined('DB_PASS') ? (DB_PASS ? '[CONFIGURATO]' : '[VUOTO]') : 'NON DEFINITO'
    ];
    
    foreach ($configTests as $key => $value) {
        $status = ($value !== 'NON DEFINITO') ? '✅' : '❌';
        echo "<div class='test-item'>$status <strong>$key:</strong> $value</div>";
    }
} else {
    echo "<div class='test-item'>❌ File config.php non trovato</div>";
    $errors[] = "File config.php mancante";
}
echo "</div>";

// Test 4: Test connessione database
echo "<div class='test-section'>";
echo "<h2>🗄️ Test 4: Connessione Database</h2>";

if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<div class='test-item'>✅ Connessione database riuscita</div>";
        
        // Test query
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        echo "<div class='test-item'>📊 <strong>MySQL Versione:</strong> " . $result['version'] . "</div>";
        
        // Verifica tabelle
        $stmt = $pdo->query("SHOW TABLES LIKE 'audit_sessions'");
        if ($stmt->fetch()) {
            echo "<div class='test-item'>✅ Tabelle database presenti</div>";
        } else {
            echo "<div class='test-item'>⚠️ Tabelle database non trovate - verranno create automaticamente</div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='test-item'>❌ Errore connessione database: " . $e->getMessage() . "</div>";
        $errors[] = "Connessione database fallita: " . $e->getMessage();
    }
} else {
    echo "<div class='test-item'>❌ Configurazione database incompleta</div>";
    $errors[] = "Configurazione database mancante";
}
echo "</div>";

// Test 5: Test directory e permessi
echo "<div class='test-section'>";
echo "<h2>📂 Test 5: Directory e Permessi</h2>";

$directories = [
    __DIR__ => 'Directory principale',
    __DIR__ . '/logs' => 'Directory log',
    __DIR__ . '/reports' => 'Directory report',
    __DIR__ . '/cache' => 'Directory cache'
];

foreach ($directories as $dir => $description) {
    if (!is_dir($dir)) {
        $created = mkdir($dir, 0755, true);
        $status = $created ? '✅ Creata' : '❌ Creazione fallita';
    } else {
        $status = '✅ Esistente';
    }
    
    $writable = is_writable($dir);
    $writeStatus = $writable ? '✅ Scrivibile' : '❌ Non scrivibile';
    
    echo "<div class='test-item'><strong>$description:</strong> $status | $writeStatus</div>";
    
    if (!$writable) {
        $errors[] = "Directory non scrivibile: $dir";
    }
}
echo "</div>";

// Test 6: Test Logger
echo "<div class='test-section'>";
echo "<h2>📝 Test 6: Sistema di Logging</h2>";

if (file_exists('Logger.php')) {
    try {
        require_once 'Logger.php';
        Logger::info("Test sistema logging");
        echo "<div class='test-item'>✅ Logger inizializzato</div>";
        echo "<div class='test-item'>📋 <strong>File log:</strong> " . Logger::getLogFile() . "</div>";
        
        // Test scrittura log
        if (file_exists(Logger::getLogFile())) {
            echo "<div class='test-item'>✅ File log creato e accessibile</div>";
        } else {
            echo "<div class='test-item'>⚠️ File log non ancora creato</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='test-item'>❌ Errore logger: " . $e->getMessage() . "</div>";
        $errors[] = "Errore sistema logging: " . $e->getMessage();
    }
} else {
    echo "<div class='test-item'>❌ File Logger.php non trovato</div>";
}
echo "</div>";

// Test 7: Test connessione esterna
echo "<div class='test-section'>";
echo "<h2>🌐 Test 7: Connessione Esterna</h2>";

$testUrl = 'https://httpbin.org/status/200';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$result = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($result !== false && $statusCode === 200) {
    echo "<div class='test-item'>✅ Connessione esterna funzionante</div>";
} else {
    echo "<div class='test-item'>❌ Problema connessione esterna - Status: $statusCode, Error: $curlError</div>";
    $errors[] = "Connessione esterna fallita";
}
echo "</div>";

// Test 8: Test caricamento classi principali
echo "<div class='test-section'>";
echo "<h2>🏗️ Test 8: Caricamento Classi</h2>";

$classes = [
    'IncludoAuditor' => 'IncludoAuditor.php',
    'WCAGChecker' => 'WCAGChecker.php',
    'ReportGenerator' => 'ReportGenerator.php'
];

foreach ($classes as $className => $file) {
    if (file_exists($file)) {
        try {
            require_once $file;
            echo "<div class='test-item'>✅ <strong>$className</strong> caricata correttamente</div>";
        } catch (Exception $e) {
            echo "<div class='test-item'>❌ Errore caricamento <strong>$className</strong>: " . $e->getMessage() . "</div>";
            $errors[] = "Errore caricamento classe $className";
        }
    } else {
        echo "<div class='test-item'>❌ File <strong>$file</strong> non trovato</div>";
    }
}
echo "</div>";

// Risultato finale
echo "<div class='test-section " . (empty($errors) ? 'success' : 'error') . "'>";
echo "<h2>🎯 Risultato Finale</h2>";

if (empty($errors)) {
    echo "<div style='font-size: 1.2em;'><strong>✅ TUTTI I TEST SUPERATI!</strong></div>";
    echo "<p>Il sistema Includo è configurato correttamente e pronto all'uso.</p>";
    echo "<p><a href='includo.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Avvia Includo</a></p>";
} else {
    echo "<div style='font-size: 1.2em;'><strong>❌ PROBLEMI RILEVATI:</strong></div>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "<p><strong>Risolvi questi problemi prima di utilizzare Includo.</strong></p>";
}
echo "</div>";

// Informazioni debug aggiuntive
if (isset($_GET['debug'])) {
    echo "<div class='test-section'>";
    echo "<h2>🔧 Informazioni Debug Aggiuntive</h2>";
    
    echo "<h3>Variabili Server:</h3>";
    echo "<pre>";
    $serverInfo = [
        'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'PHP_SAPI' => php_sapi_name(),
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A'
    ];
    print_r($serverInfo);
    echo "</pre>";
    
    echo "<h3>Configurazione PHP:</h3>";
    echo "<pre>";
    $phpConfig = [
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No'
    ];
    print_r($phpConfig);
    echo "</pre>";
    
    echo "</div>";
}

echo "<div style='text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;'>";
echo "<p>Aggiungi <code>?debug=1</code> all'URL per informazioni debug aggiuntive.</p>";
echo "<p><strong>Includo v2.1.0</strong> - Sistema Professionale di Audit Accessibilità</p>";
echo "</div>";
?>