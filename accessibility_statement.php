<?php
/**
 * Includo – Accessibility Statement Generator
 * WCAG 2.2 (A/AA) + European Accessibility Act (EU 2019/882)
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

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$lang = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : (defined('INCLUDO_LANG') ? INCLUDO_LANG : 'it');
$lang = in_array($lang, ['it','en'], true) ? $lang : 'it';
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;

if ($sessionId <= 0) {
    http_response_code(400);
    echo "Missing or invalid session_id";
    exit;
}

// DB connect (same config constants)
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

// Fetch session
$stmt = $pdo->prepare("SELECT * FROM audit_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo "Audit session not found.";
    exit;
}

// Fetch pages count
$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM page_audits WHERE session_id = ?");
$stmt->execute([$sessionId]);
$pageCount = (int)($stmt->fetch()['c'] ?? 0);

// Fetch issues aggregated by criterion/type
$stmt = $pdo->prepare("
    SELECT ai.wcag_level, ai.wcag_criterion, ai.issue_type, ai.severity,
           COUNT(*) AS cnt,
           COUNT(DISTINCT pa.url) AS affected_pages
    FROM accessibility_issues ai
    JOIN page_audits pa ON pa.id = ai.page_audit_id
    WHERE pa.session_id = ?
      AND ai.wcag_level IN ('A','AA')
    GROUP BY ai.wcag_level, ai.wcag_criterion, ai.issue_type, ai.severity
    ORDER BY 
      FIELD(ai.severity,'critical','high','medium','low'),
      cnt DESC
");
$stmt->execute([$sessionId]);
$issues = $stmt->fetchAll();

// Compliance status heuristic
$totalA_AA = 0;
$criticalHigh = 0;
$affectedPagesSet = [];
foreach ($issues as $it) {
    $totalA_AA += (int)$it['cnt'];
    if (in_array($it['severity'], ['critical','high'], true)) $criticalHigh += (int)$it['cnt'];
    // we don't have the page URLs in this query; approximate with affected_pages
}
$ratioAffected = ($pageCount > 0) ? min(1.0, array_sum(array_map(fn($x)=>(int)$x['affected_pages'], $issues)) / $pageCount) : 0.0;

$status = 'partially';
if ($totalA_AA === 0) $status = 'compliant';
if ($criticalHigh >= 10 || $ratioAffected >= 0.30) $status = ($totalA_AA === 0) ? 'compliant' : 'not_compliant';

// Statement metadata (from config if available)
$orgName = defined('INCLUDO_STATEMENT_ORG_NAME') ? INCLUDO_STATEMENT_ORG_NAME : '';
$contactEmail = defined('INCLUDO_STATEMENT_CONTACT_EMAIL') ? INCLUDO_STATEMENT_CONTACT_EMAIL : '';
$siteUrl = $session['base_url'] ?? ($session['target_url'] ?? '');
$generatedAt = date('Y-m-d');
$publicationDate = isset($_GET['publication_date']) ? trim((string)$_GET['publication_date']) : ($session['publication_date'] ?? '');
$lastUpdate = isset($_GET['last_update']) ? trim((string)$_GET['last_update']) : ($session['last_update'] ?? $generatedAt);

function t_it($key, $vars = []) {
    $map = [
      'title' => 'Dichiarazione di accessibilità',
      'intro' => 'La presente dichiarazione di accessibilità si applica al sito',
      'legal' => 'La valutazione è stata effettuata secondo le WCAG 2.2 (livelli A e AA) e i requisiti del European Accessibility Act (Direttiva UE 2019/882).',

      'site_info_h' => 'Informazioni sul sito',
      'drafting_h' => 'Redazione della dichiarazione di accessibilità',
      'drafting' => 'La presente dichiarazione è stata redatta il {date}. La valutazione si basa sui risultati dell’audit eseguito con Includo e su verifiche assistite. Alcuni controlli richiedono ulteriore verifica manuale.',
      'procedure_h' => 'Procedura di attuazione',
      'procedure' => 'In caso di risposta insoddisfacente o di mancata risposta entro 30 giorni alla segnalazione, è possibile inoltrare una segnalazione all’autorità competente secondo le modalità previste dalla normativa nazionale (AgID per l’Italia).',
      'update_h' => 'Ultimo aggiornamento',

      'status_h' => 'Stato di conformità',
      'status_compliant' => 'Conforme',
      'status_partially' => 'Parzialmente conforme',
      'status_not' => 'Non conforme',
      'non_accessible_h' => 'Contenuti non accessibili',
      'method_h' => 'Metodo di valutazione',
      'method' => 'Analisi automatizzata e verifiche assistite tramite il tool Includo.',
      'feedback_h' => 'Feedback e contatti',
      'feedback' => 'È possibile segnalare eventuali problemi di accessibilità scrivendo a',
      'date_h' => 'Data',
      'date' => 'Data di redazione/ultimo aggiornamento',
      'footer_snippet_h' => 'Snippet footer',
      'footer_snippet' => 'Inserire questo link nel footer del sito:',
    ];
    $s = $map[$key] ?? $key;
    foreach ($vars as $k=>$v) $s = str_replace('{'.$k.'}', (string)$v, $s);
    return $s;
}

function t_en($key, $vars = []) {
    $map = [
      'title' => 'Accessibility statement',
      'intro' => 'This accessibility statement applies to',
      'legal' => 'The evaluation was performed against WCAG 2.2 (Level A and AA) and the requirements of the European Accessibility Act (EU Directive 2019/882).',
      'status_h' => 'Compliance status',
      'status_compliant' => 'Compliant',
      'status_partially' => 'Partially compliant',
      'status_not' => 'Not compliant',
      'non_accessible_h' => 'Non-accessible content',
      'method_h' => 'Evaluation method',
      'method' => 'Automated analysis and assisted checks performed with the Includo tool.',
      'feedback_h' => 'Feedback and contact',
      'feedback' => 'To report accessibility issues, please contact',
      'date_h' => 'Date',
      'date' => 'Statement date / last update',
      'footer_snippet_h' => 'Footer snippet',
      'footer_snippet' => 'Add this link to your website footer:',
    ];
    $s = $map[$key] ?? $key;
    foreach ($vars as $k=>$v) $s = str_replace('{'.$k.'}', (string)$v, $s);
    return $s;
}

$T = ($lang === 'en') ? 't_en' : 't_it';

$statusLabel = ($status === 'compliant') ? $T('status_compliant') : (($status === 'not_compliant') ? $T('status_not') : $T('status_partially'));

// Build non-accessible list (top items)
$top = array_slice($issues, 0, 12);

$statementHtml = '<!doctype html><html lang="'.h($lang).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
  . '<title>'.h($T('title')).'</title>'
  . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:24px;line-height:1.5;color:#111}'
  . 'h1{font-size:28px;margin:0 0 12px}h2{margin-top:20px;font-size:18px}'
  . '.card{background:#fff;border:1px solid #e7e7e7;border-radius:14px;padding:16px;margin:12px 0}'
  . '.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#f1f3f6;font-weight:700}'
  . 'code{background:#f1f3f6;padding:2px 6px;border-radius:8px}'
  . '</style></head><body>';

$statementHtml .= '<h1>'.h($T('title')).'</h1>';

if ($orgName) {
  $statementHtml .= '<div class="card"><strong>'.h($orgName).'</strong></div>';
}

$statementHtml .= '<div class="card"><p>'.$T('intro').' <strong>'.h($siteUrl).'</strong>.</p>'
  . '<p>'.h($T('legal')).'</p></div>';


$statementHtml .= '<h2>'.$T('site_info_h').'</h2><div class="card"><ul>'
  . '<li><strong>URL:</strong> '.h($siteUrl).'</li>'
  . ($orgName ? '<li><strong>Organizzazione:</strong> '.h($orgName).'</li>' : '')
  . ($publicationDate ? '<li><strong>Data di pubblicazione:</strong> '.h($publicationDate).'</li>' : '')
  . '<li><strong>'.$T('date').':</strong> '.h($generatedAt).'</li>'
  . '<li><strong>'.$T('update_h').':</strong> '.h($lastUpdate).'</li>'
  . '<li><strong>Standard:</strong> WCAG 2.2 (A/AA) · European Accessibility Act (EU 2019/882)</li>'
  . '</ul></div>';

$statementHtml .= '<h2>'.$T('drafting_h').'</h2><div class="card"><p>'.h($T('drafting', ['date'=>$generatedAt])).'</p></div>';

$statementHtml .= '<h2>'.$T('procedure_h').'</h2><div class="card"><p>'.h($T('procedure')).'</p></div>';

$statementHtml .= '<h2>'.$T('status_h').'</h2><div class="card"><span class="badge">'.h($statusLabel).'</span></div>';

$statementHtml .= '<h2>'.$T('non_accessible_h').'</h2><div class="card">';
if (count($top) === 0) {
  $statementHtml .= '<p>—</p>';
} else {
  $statementHtml .= '<ul>';
  foreach ($top as $it) {
    $statementHtml .= '<li><strong>'.h($it['wcag_criterion']).' ('.$it['wcag_level'].')</strong> — '
      . h($it['issue_type'])
      . ' <em>(' . h($it['severity']) . ', ' . (int)$it['cnt'] . ')</em>'
      . '</li>';
  }
  $statementHtml .= '</ul>';
  $statementHtml .= '<p><em>Nota:</em> elenco sintetico basato sui risultati dell’audit (livelli A/AA). Consigliata revisione manuale e verifica puntuale.</p>';
}
$statementHtml .= '</div>';

$statementHtml .= '<h2>'.$T('method_h').'</h2><div class="card"><p>'.h($T('method')).'</p></div>';

$statementHtml .= '<h2>'.$T('feedback_h').'</h2><div class="card"><p>'.h($T('feedback')).' ';
$statementHtml .= $contactEmail ? '<a href="mailto:'.h($contactEmail).'">'.h($contactEmail).'</a>' : '<strong>[inserire contatto]</strong>';
$statementHtml .= '.</p></div>';

$statementHtml .= '<h2>'.$T('date_h').'</h2><div class="card"><p>'.h($T('date')).': <strong>'.h($generatedAt).'</strong></p></div>';

$statementPath = '/accessibilita.html';
$snippet = '<a href="'.$statementPath.'" title="'.(($lang==='en')?'Accessibility statement':'Dichiarazione di accessibilità').'">'.(($lang==='en')?'Accessibility statement':'Dichiarazione di accessibilità').'</a>';

$statementHtml .= '<h2>'.$T('footer_snippet_h').'</h2><div class="card"><p>'.h($T('footer_snippet')).'</p><pre><code>'.h($snippet).'</code></pre></div>';

$statementHtml .= '</body></html>';

if ($export === 1) {
    $dir = __DIR__ . '/reports';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $out = $dir . '/accessibility_statement_session_' . $sessionId . '_' . $lang . '.html';
    file_put_contents($out, $statementHtml, LOCK_EX);
    header('Content-Type: text/html; charset=utf-8');
    echo $statementHtml;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo $statementHtml;
