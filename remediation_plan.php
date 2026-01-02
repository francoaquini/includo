<?php
/**
 * Includo – Remediation Plan Export (CSV)
 * Generates an actionable plan from audit issues.
 *
 * Author: Franco Aquini - Web Salad
 * License: MIT
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($sessionId <= 0) {
    http_response_code(400);
    echo "Missing or invalid session_id";
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo "DB connection failed";
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        s.site_url,
        pa.url AS page_url,
        ai.issue_type,
        ai.wcag_criterion,
        ai.wcag_level,
        ai.severity,
        ai.confidence,
        ai.description,
        ai.recommendation,
        ai.element_selector,
        ai.help_url,
        ai.line_number,
        ai.created_at
    FROM audit_sessions s
    JOIN page_audits pa ON pa.session_id = s.id
    JOIN accessibility_issues ai ON ai.page_audit_id = pa.id
    WHERE s.id = ?
    ORDER BY
        FIELD(ai.severity,'critical','high','medium','low'),
        ai.wcag_level,
        ai.wcag_criterion,
        pa.url
");
$stmt->execute([$sessionId]);
$rows = $stmt->fetchAll();

$filename = "includo-remediation-session-{$sessionId}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, [
    'site_url',
    'page_url',
    'severity',
    'wcag_level',
    'wcag_criterion',
    'issue_type',
    'description',
    'recommendation',
    'element_selector',
    'line_number',
    'help_url',
    'confidence',
    'detected_at',
    'owner',
    'due_date',
    'status',
    'notes'
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['site_url'] ?? '',
        $r['page_url'] ?? '',
        $r['severity'] ?? '',
        $r['wcag_level'] ?? '',
        $r['wcag_criterion'] ?? '',
        $r['issue_type'] ?? '',
        $r['description'] ?? '',
        $r['recommendation'] ?? '',
        $r['element_selector'] ?? '',
        $r['line_number'] ?? '',
        $r['help_url'] ?? '',
        $r['confidence'] ?? '',
        $r['created_at'] ?? '',
        '', // owner
        '', // due_date
        'open',
        ''
    ]);
}

fclose($out);
exit;
