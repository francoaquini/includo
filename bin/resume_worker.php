<?php
// CLI worker to resume paused Includo audit sessions.
// Usage: php bin/resume_worker.php

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI.\n";
    http_response_code(403);
    exit;
}

chdir(__DIR__ . '/..');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../IncludoAuditor.php';
require_once __DIR__ . '/../Logger.php';

$loggerFile = Logger::getLogFile();
Logger::info('resume_worker started');

try {
    $auditor = new IncludoAuditor(DB_HOST, DB_NAME, DB_USER, DB_PASS);
    $pdo = $auditor->getPDO();

    // Find paused sessions with remaining queue
    $stmt = $pdo->prepare("SELECT id FROM audit_sessions WHERE status IN ('paused','running') AND remaining_queue IS NOT NULL");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (empty($sessions)) {
        Logger::info('No paused sessions found');
        echo "No paused sessions found.\n";
        exit(0);
    }

    foreach ($sessions as $sid) {
        try {
            Logger::info('Resuming session via worker', ['session_id' => $sid]);
            echo "Resuming session: $sid\n";
            $auditor->resumeAudit((int)$sid);
            echo "Finished session: $sid\n";
        } catch (Throwable $e) {
            Logger::error('Worker failed to resume session', ['session_id' => $sid, 'error' => $e->getMessage()]);
            echo "Error resuming session $sid: " . $e->getMessage() . "\n";
        }
    }

    Logger::info('resume_worker finished');
} catch (Throwable $e) {
    Logger::critical('resume_worker fatal error: ' . $e->getMessage());
    echo "Fatal: " . $e->getMessage() . "\n";
    exit(2);
}

exit(0);
