<?php
/**
 * Logger - Sistema di Logging per Includo
 * Traccia tutte le operazioni e errori
 */

class Logger {
    private static $logFile;
    private static $debugMode = true;
    private static $buffered = [];
    
    public static function init($logFile = null) {
        if ($logFile) {
            self::$logFile = $logFile;
        } else {
            self::$logFile = __DIR__ . '/logs/includo_' . date('Y-m-d') . '.log';
        }
        
        // Crea directory logs se non esiste
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Test scrittura
        if (!is_writable($logDir)) {
            error_log("Includo: Directory log non scrivibile: $logDir");
        }
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
        // Log anche su error_log di PHP
        error_log("Includo Error: $message");
    }
    
    public static function debug($message, $context = []) {
        if (self::$debugMode) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    public static function critical($message, $context = []) {
        self::log('CRITICAL', $message, $context);
        error_log("Includo Critical: $message");
    }
    
    private static function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logLine = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        // Scrivi su file se possibile
        if (self::$logFile && is_writable(dirname(self::$logFile))) {
            file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        }
        
        // In modalità debug, bufferizza i messaggi da mostrare a schermo
        if (self::$debugMode && ($level === 'ERROR' || $level === 'CRITICAL')) {
            $html = "<div style='background: #ffebee; color: #c62828; padding: 10px; margin: 5px 0; border-radius: 5px; font-family: monospace;'>";
            $html .= "<strong>[$level]</strong> " . htmlspecialchars((string)$message);
            if (!empty($context)) {
                $html .= "<br><small>" . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT)) . "</small>";
            }
            $html .= "</div>";
            self::$buffered[] = $html;
        }
    }

    public static function renderBuffered()
    {
        if (!empty(self::$buffered)) {
            foreach (self::$buffered as $b) {
                echo $b;
            }
            // clear after rendering
            self::$buffered = [];
        }
    }
    
    public static function logException($exception, $context = []) {
        $context['file'] = $exception->getFile();
        $context['line'] = $exception->getLine();
        $context['trace'] = $exception->getTraceAsString();
        
        self::error($exception->getMessage(), $context);
    }
    
    public static function setDebugMode($enabled) {
        self::$debugMode = $enabled;
    }
    
    public static function getLogFile() {
        return self::$logFile;
    }
    
    // Metodo per verificare lo stato del sistema
    public static function systemCheck() {
        $checks = [];
        
        // Verifica PHP
        $checks['php_version'] = PHP_VERSION;
        $checks['php_extensions'] = [
            'curl' => extension_loaded('curl'),
            'dom' => extension_loaded('dom'),
            'libxml' => extension_loaded('libxml'),
            'json' => extension_loaded('json'),
            'pdo_mysql' => extension_loaded('pdo_mysql')
        ];
        
        // Verifica directory
        $checks['directories'] = [
            'logs_writable' => is_writable(dirname(self::$logFile)),
            'current_dir_writable' => is_writable(__DIR__)
        ];
        
        // Verifica configurazione
        if (defined('DB_HOST')) {
            $checks['config'] = [
                'db_host' => DB_HOST,
                'db_name' => DB_NAME,
                'db_user' => DB_USER
            ];
        }
        
        self::info('System Check', $checks);
        
        return $checks;
    }
}

// Inizializza logger
Logger::init();

// Handler per errori PHP
set_error_handler(function($severity, $message, $file, $line) {
    Logger::error("PHP Error: $message", [
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
});

// Handler per eccezioni non catturate
set_exception_handler(function($exception) {
    Logger::logException($exception, ['uncaught' => true]);
});
?>