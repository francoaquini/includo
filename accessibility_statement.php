<?php
/**
 * Includo – Accessibility Statement Generator (AgID-ready)
 * - Produces an Accessibility Statement aligned to AgID "Allegato 1" structure (IT)
 * - Also supports an EN version (generic EU-style statement)
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

require_once __DIR__ . '/Logger.php';

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$lang = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : (defined('INCLUDO_LANG') ? (string)INCLUDO_LANG : 'it');
$lang = in_array($lang, ['it','en'], true) ? $lang : 'it';
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;
$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

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

/**
 * Ensure table exists (soft-migration).
 */
$pdo->exec("
CREATE TABLE IF NOT EXISTS accessibility_statements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    lang ENUM('it','en') NOT NULL DEFAULT 'it',
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_lang (session_id, lang),
    FOREIGN KEY (session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function fetchSession(PDO $pdo, int $sessionId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM audit_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchAutoSummary(PDO $pdo, int $sessionId): array {
    // counts by level/severity + top criteria
    $summary = [
        'pages' => 0,
        'issues' => 0,
        'level' => ['A'=>0,'AA'=>0,'AAA'=>0],
        'severity' => ['critical'=>0,'high'=>0,'medium'=>0,'low'=>0],
        'top_criteria' => [],
        'top_pages' => [],
    ];

    $stmt = $pdo->prepare("SELECT total_pages, total_issues FROM audit_sessions WHERE id=?");
    $stmt->execute([$sessionId]);
    if ($s = $stmt->fetch()) {
        $summary['pages'] = (int)$s['total_pages'];
        $summary['issues'] = (int)$s['total_issues'];
    }

    $stmt = $pdo->prepare("
        SELECT ai.wcag_level, ai.severity, COUNT(*) c
        FROM page_audits pa
        JOIN accessibility_issues ai ON ai.page_audit_id = pa.id
        WHERE pa.session_id = ?
        GROUP BY ai.wcag_level, ai.severity
    ");
    $stmt->execute([$sessionId]);
    foreach ($stmt->fetchAll() as $r) {
        $lvl = strtoupper((string)$r['wcag_level']);
        $sev = strtolower((string)$r['severity']);
        $c = (int)$r['c'];
        if (isset($summary['level'][$lvl])) $summary['level'][$lvl] += $c;
        if (isset($summary['severity'][$sev])) $summary['severity'][$sev] += $c;
    }

    $stmt = $pdo->prepare("
        SELECT ai.wcag_criterion, ai.wcag_level, COUNT(*) c
        FROM page_audits pa
        JOIN accessibility_issues ai ON ai.page_audit_id = pa.id
        WHERE pa.session_id = ?
        GROUP BY ai.wcag_criterion, ai.wcag_level
        ORDER BY c DESC
        LIMIT 10
    ");
    $stmt->execute([$sessionId]);
    $summary['top_criteria'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT pa.url, pa.total_issues
        FROM page_audits pa
        WHERE pa.session_id = ?
        ORDER BY pa.total_issues DESC
        LIMIT 10
    ");
    $stmt->execute([$sessionId]);
    $summary['top_pages'] = $stmt->fetchAll();

    return $summary;
}

function loadStatement(PDO $pdo, int $sessionId, string $lang): ?array {
    $stmt = $pdo->prepare("SELECT payload FROM accessibility_statements WHERE session_id=? AND lang=?");
    $stmt->execute([$sessionId, $lang]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $payload = json_decode((string)$row['payload'], true);
    return is_array($payload) ? $payload : null;
}

function saveStatement(PDO $pdo, int $sessionId, string $lang, array $payload): void {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $pdo->prepare("
        INSERT INTO accessibility_statements (session_id, lang, payload)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE payload=VALUES(payload)
    ");
    $stmt->execute([$sessionId, $lang, $json]);
}

function computeComplianceStatus(array $auto): string {
    // Simple heuristic:
    // - If no issues: compliant
    // - If there are any AA issues or critical/high: partially compliant
    // - If huge amount critical/high: non compliant
    $aa = (int)($auto['level']['AA'] ?? 0);
    $critical = (int)($auto['severity']['critical'] ?? 0);
    $high = (int)($auto['severity']['high'] ?? 0);
    $total = (int)($auto['issues'] ?? 0);

    if ($total === 0) return 'conforme';
    if ($critical + $high > 50) return 'non_conforme';
    if ($aa > 0 || $critical + $high > 0) return 'parzialmente_conforme';
    return 'parzialmente_conforme';
}

$session = fetchSession($pdo, $sessionId);
if (!$session) {
    http_response_code(404);
    echo "Session not found";
    exit;
}

$auto = fetchAutoSummary($pdo, $sessionId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitation
    $payload = [
        'site_url' => (string)($session['site_url'] ?? ''),
        'entity_name' => trim((string)($_POST['entity_name'] ?? '')),
        'entity_type' => trim((string)($_POST['entity_type'] ?? '')),
        'contact_email' => trim((string)($_POST['contact_email'] ?? '')),
        'contact_form_url' => trim((string)($_POST['contact_form_url'] ?? '')),
        'responsible_office' => trim((string)($_POST['responsible_office'] ?? '')),
        'last_review_date' => trim((string)($_POST['last_review_date'] ?? '')),
        'statement_date' => trim((string)($_POST['statement_date'] ?? date('Y-m-d'))),
        'assessment_method' => trim((string)($_POST['assessment_method'] ?? '')),
        'assessment_tool' => trim((string)($_POST['assessment_tool'] ?? 'Includo (WCAG 2.2)')),
        'wcag_target' => trim((string)($_POST['wcag_target'] ?? 'WCAG 2.2 AA')),
        'compliance_status' => trim((string)($_POST['compliance_status'] ?? '')),
        'non_accessible_content' => trim((string)($_POST['non_accessible_content'] ?? '')),
        'alternatives' => trim((string)($_POST['alternatives'] ?? '')),
        'disproportionate_burden' => trim((string)($_POST['disproportionate_burden'] ?? '')),
        'enforcement_procedure_url' => trim((string)($_POST['enforcement_procedure_url'] ?? 'https://form.agid.gov.it/')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
        'auto_summary' => $auto,
    ];

    if ($payload['compliance_status'] === '') {
        $payload['compliance_status'] = computeComplianceStatus($auto);
    }

    saveStatement($pdo, $sessionId, $lang, $payload);

    header("Location: accessibility_statement.php?session_id={$sessionId}&lang={$lang}");
    exit;
}

// Load saved statement or create defaults
$payload = loadStatement($pdo, $sessionId, $lang);
if (!$payload) {
    $payload = [
        'site_url' => (string)($session['site_url'] ?? ''),
        'entity_name' => '',
        'entity_type' => ($lang === 'it') ? 'Privato / PA (compilare)' : 'Organization',
        'contact_email' => '',
        'contact_form_url' => '',
        'responsible_office' => '',
        'statement_date' => date('Y-m-d'),
        'last_review_date' => date('Y-m-d'),
        'assessment_method' => ($lang === 'it')
            ? 'Valutazione tecnica con test automatici + verifiche manuali sui criteri non automatizzabili.'
            : 'Technical assessment using automated tests plus manual checks where automation is not sufficient.',
        'assessment_tool' => 'Includo (WCAG 2.2)',
        'wcag_target' => 'WCAG 2.2 AA',
        'compliance_status' => computeComplianceStatus($auto),
        'non_accessible_content' => '',
        'alternatives' => '',
        'disproportionate_burden' => '',
        'enforcement_procedure_url' => 'https://form.agid.gov.it/',
        'notes' => '',
        'auto_summary' => $auto,
    ];
}

function complianceLabel(string $code, string $lang): string {
    $mapIt = [
        'conforme' => 'Conforme',
        'parzialmente_conforme' => 'Parzialmente conforme',
        'non_conforme' => 'Non conforme',
    ];
    $mapEn = [
        'conforme' => 'Compliant',
        'parzialmente_conforme' => 'Partially compliant',
        'non_conforme' => 'Non-compliant',
    ];
    return $lang === 'it' ? ($mapIt[$code] ?? $code) : ($mapEn[$code] ?? $code);
}

function footerSnippet(int $sessionId): string {
    $url = "accessibility_statement.php?session_id={$sessionId}&lang=it";
    return '<a href="' . h($url) . '">Dichiarazione di accessibilità</a>';
}

ob_start();

if ($edit === 1) {
    // Builder form
    ?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Includo – Builder Dichiarazione</title>
  <link rel="stylesheet" href="<?php echo INCLUDO_BASE_PATH; ?>assets/global.css">
  <link rel="stylesheet" href="<?php echo INCLUDO_BASE_PATH; ?>assets/navbar.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:24px;max-width:980px}
    .card{border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin:14px 0;box-shadow:0 1px 6px rgba(0,0,0,.05)}
    label{display:block;font-weight:600;margin:10px 0 6px}
    input,textarea,select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:10px}
    textarea{min-height:120px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #111827;text-decoration:none}
    .btn.primary{background:#111827;color:#fff}
    .muted{color:#6b7280}
    code{background:#f3f4f6;padding:2px 6px;border-radius:6px}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<h1>🧩 Dichiarazione di accessibilità – Builder</h1>
<p class="muted">
Sessione: <strong>#<?= (int)$sessionId ?></strong> — Sito: <strong><?= h((string)$payload['site_url']) ?></strong>
</p>

<div class="card">
  <h2>Snippet footer</h2>
  <p>Inserisci nel footer del sito questo link (label consigliata):</p>
  <p><code><?= h(footerSnippet($sessionId)) ?></code></p>
</div>

<form method="post" class="card">
  <h2>Dati richiesti</h2>

  <div class="row">
    <div>
      <label>Nome soggetto erogatore / organizzazione</label>
      <input name="entity_name" value="<?= h((string)$payload['entity_name']) ?>" placeholder="Es. Comune di..., Azienda...">
    </div>
    <div>
      <label>Tipologia (PA / privato / altro)</label>
      <input name="entity_type" value="<?= h((string)$payload['entity_type']) ?>" placeholder="Es. PA, art. 3, comma 1-bis...">
    </div>
  </div>

  <div class="row">
    <div>
      <label>Email contatto accessibilità</label>
      <input name="contact_email" value="<?= h((string)$payload['contact_email']) ?>" placeholder="accessibilita@...">
    </div>
    <div>
      <label>URL form contatto (opzionale)</label>
      <input name="contact_form_url" value="<?= h((string)$payload['contact_form_url']) ?>" placeholder="https://.../contatti">
    </div>
  </div>

  <label>Ufficio / referente (opzionale)</label>
  <input name="responsible_office" value="<?= h((string)$payload['responsible_office']) ?>" placeholder="Es. RTD / Ufficio comunicazione / Digital team">

  <div class="row">
    <div>
      <label>Data dichiarazione</label>
      <input type="date" name="statement_date" value="<?= h((string)$payload['statement_date']) ?>">
    </div>
    <div>
      <label>Ultimo aggiornamento</label>
      <input type="date" name="last_review_date" value="<?= h((string)$payload['last_review_date']) ?>">
    </div>
  </div>

  <label>Metodo di valutazione</label>
  <textarea name="assessment_method"><?= h((string)$payload['assessment_method']) ?></textarea>

  <div class="row">
    <div>
      <label>Strumento</label>
      <input name="assessment_tool" value="<?= h((string)$payload['assessment_tool']) ?>">
    </div>
    <div>
      <label>Target</label>
      <input name="wcag_target" value="<?= h((string)$payload['wcag_target']) ?>">
    </div>
  </div>

  <label>Stato di conformità</label>
  <select name="compliance_status">
    <?php
      $opts = ['conforme','parzialmente_conforme','non_conforme'];
      foreach ($opts as $o) {
        $sel = ($payload['compliance_status'] === $o) ? 'selected' : '';
        echo "<option value='".h($o)."' {$sel}>".h(complianceLabel($o,$lang))."</option>";
      }
    ?>
  </select>

  <label>Contenuti non accessibili</label>
  <textarea name="non_accessible_content" placeholder="Indicare pagine / funzionalità / contenuti non conformi e motivazioni."><?= h((string)$payload['non_accessible_content']) ?></textarea>

  <label>Alternative accessibili (se presenti)</label>
  <textarea name="alternatives" placeholder="Es. documenti alternativi, canali alternativi, supporto..."><?= h((string)$payload['alternatives']) ?></textarea>

  <label>Onere sproporzionato (se applicato)</label>
  <textarea name="disproportionate_burden" placeholder="Se invocato, descrivere motivazioni e ambito."><?= h((string)$payload['disproportionate_burden']) ?></textarea>

  <label>Procedura di attuazione / segnalazione (link)</label>
  <input name="enforcement_procedure_url" value="<?= h((string)$payload['enforcement_procedure_url']) ?>">

  <label>Note (opzionale)</label>
  <textarea name="notes"><?= h((string)$payload['notes']) ?></textarea>

  <p class="muted">
    Suggerimento: puoi precompilare “Contenuti non accessibili” partendo dal report Includo della sessione e dal piano CSV.
  </p>

  <button class="btn primary" type="submit">💾 Salva dichiarazione</button>
  <a class="btn" href="accessibility_statement.php?session_id=<?= (int)$sessionId ?>&lang=<?= h($lang) ?>">Anteprima</a>
</form>

<div class="card">
  <h2>Auto-summary (dal report)</h2>
  <p class="muted">Questi dati vengono calcolati automaticamente dalla sessione audit.</p>
  <pre><?= h(json_encode($auto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
</div>

</body>
</html>
<?php
} else {
    // Render statement
    $title = ($lang === 'it') ? 'Dichiarazione di accessibilità' : 'Accessibility Statement';
    $site = (string)$payload['site_url'];
    $statusLabel = complianceLabel((string)$payload['compliance_status'], $lang);

    ?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($title) ?> – <?= h($site) ?></title>
  <link rel="stylesheet" href="<?php echo INCLUDO_BASE_PATH; ?>assets/navbar.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:24px;max-width:980px;line-height:1.45}
    h1{margin-bottom:6px}
    .muted{color:#6b7280}
    .card{border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin:14px 0;box-shadow:0 1px 6px rgba(0,0,0,.05)}
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#f3f4f6}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #111827;text-decoration:none;margin-right:8px}
    .btn.primary{background:#111827;color:#fff}
    ul{padding-left:18px}
    code{background:#f3f4f6;padding:2px 6px;border-radius:6px}
    table{border-collapse:collapse;width:100%}
    th,td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left;vertical-align:top}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<h1><?= h($title) ?></h1>
<p class="muted">
Sito: <strong><?= h($site) ?></strong><br>
Data dichiarazione: <strong><?= h((string)$payload['statement_date']) ?></strong> — Ultimo aggiornamento: <strong><?= h((string)$payload['last_review_date']) ?></strong>
</p>

<div class="card">
  <a class="btn primary" href="accessibility_statement.php?session_id=<?= (int)$sessionId ?>&lang=<?= h($lang) ?>&edit=1">✏️ Modifica</a>
  <a class="btn" href="accessibility_statement.php?session_id=<?= (int)$sessionId ?>&lang=<?= h($lang) ?>&export=1">⬇️ Esporta HTML</a>
  <a class="btn" href="remediation_plan.php?session_id=<?= (int)$sessionId ?>">🛠️ Piano CSV</a>
</div>

<div class="card">
  <h2>1. Informazioni generali</h2>
  <table>
    <tr><th>Soggetto erogatore</th><td><?= h((string)$payload['entity_name']) ?></td></tr>
    <tr><th>Tipologia</th><td><?= h((string)$payload['entity_type']) ?></td></tr>
    <tr><th>Contatto</th><td><?= h((string)$payload['contact_email']) ?><?php if(!empty($payload['contact_form_url'])): ?> — <a href="<?= h((string)$payload['contact_form_url']) ?>"><?= h((string)$payload['contact_form_url']) ?></a><?php endif; ?></td></tr>
    <?php if(!empty($payload['responsible_office'])): ?>
      <tr><th>Referente</th><td><?= h((string)$payload['responsible_office']) ?></td></tr>
    <?php endif; ?>
  </table>
</div>

<div class="card">
  <h2>2. Stato di conformità</h2>
  <p>Questo sito è: <span class="pill"><strong><?= h($statusLabel) ?></strong></span> rispetto a <strong><?= h((string)$payload['wcag_target']) ?></strong>.</p>
</div>

<div class="card">
  <h2>3. Contenuti non accessibili</h2>
  <?php if (trim((string)$payload['non_accessible_content']) !== ''): ?>
    <p><?= nl2br(h((string)$payload['non_accessible_content'])) ?></p>
  <?php else: ?>
    <p class="muted">Da compilare: elencare pagine/contenuti non conformi, motivazione e (se possibile) scadenza di adeguamento.</p>
  <?php endif; ?>

  <?php if (trim((string)$payload['alternatives']) !== ''): ?>
    <h3>Alternative accessibili</h3>
    <p><?= nl2br(h((string)$payload['alternatives'])) ?></p>
  <?php endif; ?>

  <?php if (trim((string)$payload['disproportionate_burden']) !== ''): ?>
    <h3>Onere sproporzionato</h3>
    <p><?= nl2br(h((string)$payload['disproportionate_burden'])) ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>4. Redazione della dichiarazione</h2>
  <p><strong>Metodo:</strong> <?= nl2br(h((string)$payload['assessment_method'])) ?></p>
  <p><strong>Strumento:</strong> <?= h((string)$payload['assessment_tool']) ?> (sessione audit #<?= (int)$sessionId ?>)</p>

  <h3>Indicatori sintetici (dalla sessione Includo)</h3>
  <ul>
    <li>Pagine analizzate: <strong><?= (int)($auto['pages'] ?? 0) ?></strong></li>
    <li>Issue rilevate: <strong><?= (int)($auto['issues'] ?? 0) ?></strong></li>
    <li>AA: <strong><?= (int)($auto['level']['AA'] ?? 0) ?></strong> — Critical: <strong><?= (int)($auto['severity']['critical'] ?? 0) ?></strong> — High: <strong><?= (int)($auto['severity']['high'] ?? 0) ?></strong></li>
  </ul>
</div>

<div class="card">
  <h2>5. Feedback e contatti</h2>
  <p>Per segnalare problemi di accessibilità o richiedere contenuti in formato accessibile, contattare:</p>
  <p><strong><?= h((string)$payload['contact_email']) ?></strong></p>
</div>

<div class="card">
  <h2>6. Procedura di attuazione</h2>
  <p>Se la risposta non è soddisfacente, è possibile utilizzare la procedura di attuazione prevista (link):</p>
  <p><a href="<?= h((string)$payload['enforcement_procedure_url']) ?>"><?= h((string)$payload['enforcement_procedure_url']) ?></a></p>
</div>

<?php if (trim((string)$payload['notes']) !== ''): ?>
<div class="card">
  <h2>Note</h2>
  <p><?= nl2br(h((string)$payload['notes'])) ?></p>
</div>
<?php endif; ?>

<div class="card">
  <h2>Snippet footer</h2>
  <p>Label consigliata nel footer:</p>
  <p><code><?= h(footerSnippet($sessionId)) ?></code></p>
</div>

</body>
</html>
<?php
}

$html = ob_get_clean();

if ($export === 1) {
    $fn = "includo-accessibility-statement-{$lang}-session-{$sessionId}.html";
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo $html;
    exit;
}

echo $html;
