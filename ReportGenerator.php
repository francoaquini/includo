<?php
/**
 * ReportGenerator - Generazione Report per Includo
 * HTML, JSON, CSV formats
 */

class ReportGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function generateReport($sessionId, $format = 'html') {
        switch (strtolower($format)) {
            case 'html':
                return $this->generateHTMLReport($sessionId);
            case 'json':
                return $this->generateJSONReport($sessionId);
            case 'csv':
                return $this->generateCSVReport($sessionId);
            default:
                throw new Exception("Formato non supportato: $format");
        }
    }
    
    private function generateHTMLReport($sessionId) {
        // Recupera dati sessione
        $stmt = $this->pdo->prepare("SELECT * FROM audit_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            throw new Exception("Sessione audit non trovata: $sessionId");
        }
        
        // Recupera pagine auditate
        $stmt = $this->pdo->prepare("SELECT * FROM page_audits WHERE session_id = ? ORDER BY total_issues DESC, url");
        $stmt->execute([$sessionId]);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recupera statistiche
        $stmt = $this->pdo->prepare("
            SELECT 
                wcag_level,
                severity,
                COUNT(*) as count
            FROM accessibility_issues ai
            JOIN page_audits pa ON ai.page_audit_id = pa.id
            WHERE pa.session_id = ?
            GROUP BY wcag_level, severity
            ORDER BY wcag_level, FIELD(severity, 'critical', 'high', 'medium', 'low')
        ");
        $stmt->execute([$sessionId]);
        $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $duration = $this->calculateDuration($session['start_time'], $session['end_time']);
        $avgIssuesPerPage = $session['total_pages'] > 0 ? 
            number_format($session['total_issues'] / $session['total_pages'], 1) : '0';
        
        $html = $this->getHTMLTemplate($session, $pages, $statistics, $duration, $avgIssuesPerPage, $sessionId);
        
        return $html;
    }
    
    private function getHTMLTemplate($session, $pages, $statistics, $duration, $avgIssuesPerPage, $sessionId) {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Includo - Report Accessibilità WCAG | <?= htmlspecialchars($session['site_url']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            margin: 0; padding: 20px; line-height: 1.6; 
            background: #f8f9fa; color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { 
            background: linear-gradient(135deg, #007bff, #0056b3); 
            color: white; padding: 30px; border-radius: 15px; 
            margin-bottom: 30px; text-align: center;
            box-shadow: 0 10px 30px rgba(0,123,255,0.3);
        }
        .header h1 { margin: 0; font-size: 2.8em; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .header .subtitle { opacity: 0.95; margin-top: 10px; font-size: 1.1em; }
        .summary { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; margin-bottom: 30px; 
        }
        .stat-card { 
            background: white; padding: 25px; border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center;
            border-left: 5px solid #007bff; transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-number { 
            font-size: 2.8em; font-weight: bold; 
            color: #007bff; margin-bottom: 5px; 
        }
        .stat-label { color: #666; font-size: 0.95em; font-weight: 500; }
        .compliance-overview {
            background: white; padding: 30px; border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px;
        }
        .compliance-levels {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-top: 25px;
        }
        .compliance-level {
            padding: 20px; border-radius: 12px; text-align: center;
            font-weight: bold; transition: transform 0.3s;
        }
        .compliance-level:hover { transform: scale(1.05); }
        .level-a { background: #d4edda; border: 3px solid #28a745; color: #155724; }
        .level-aa { background: #fff3cd; border: 3px solid #ffc107; color: #856404; }
        .level-aaa { background: #f8d7da; border: 3px solid #dc3545; color: #721c24; }
        .page-list { margin-bottom: 30px; }
        .page-item { 
            background: white; margin-bottom: 20px; border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden;
            transition: transform 0.3s;
        }
        .page-item:hover { transform: translateY(-2px); }
        .page-header { 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef); 
            padding: 25px; cursor: pointer; 
            border-bottom: 1px solid #dee2e6;
        }
        .page-header:hover { background: linear-gradient(135deg, #e9ecef, #dee2e6); }
        .page-title { margin: 0 0 10px 0; color: #333; font-size: 1.3em; }
        .page-url { 
            color: #666; font-size: 0.9em; word-break: break-all; 
            background: rgba(0,123,255,0.1); padding: 5px 10px; 
            border-radius: 5px; display: inline-block;
        }
        .page-stats { 
            display: flex; gap: 15px; margin-top: 15px; 
            flex-wrap: wrap;
        }
        .page-stat { 
            background: white; padding: 10px 15px; border-radius: 8px; 
            font-size: 0.9em; border: 2px solid #e9ecef;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .page-details { 
            padding: 30px; display: none; 
            border-top: 1px solid #dee2e6; background: #fafbfc;
        }
        .issue { 
            background: white; border-left: 5px solid #ffc107; 
            padding: 20px; margin: 15px 0; border-radius: 0 10px 10px 0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .issue.critical { border-left-color: #dc3545; }
        .issue.high { border-left-color: #fd7e14; }
        .issue.medium { border-left-color: #ffc107; }
        .issue.low { border-left-color: #6c757d; }
        .issue-header { 
            display: flex; justify-content: space-between; 
            align-items: center; margin-bottom: 15px; flex-wrap: wrap;
        }
        .issue-title { font-weight: bold; color: #333; font-size: 1.1em; }
        .wcag-badge { 
            display: inline-block; padding: 6px 12px; 
            border-radius: 6px; font-size: 0.85em; 
            color: white; font-weight: bold; margin: 2px;
        }
        .wcag-a { background: #28a745; }
        .wcag-aa { background: #ffc107; color: #000; }
        .wcag-aaa { background: #dc3545; }
        .severity-critical { background: #721c24; }
        .severity-high { background: #fd7e14; }
        .severity-medium { background: #ffc107; color: #000; }
        .severity-low { background: #6c757d; }
        .issue-description { margin: 15px 0; font-size: 1.05em; }
        .issue-recommendation { 
            background: linear-gradient(135deg, #e3f2fd, #bbdefb); 
            padding: 15px; border-radius: 8px; margin-top: 15px;
            border-left: 4px solid #2196f3;
        }
        .issue-recommendation strong { color: #1976d2; }
        .footer { 
            background: linear-gradient(135deg, #343a40, #495057); 
            color: white; padding: 30px; 
            border-radius: 15px; text-align: center; margin-top: 30px;
        }
        .btn { 
            display: inline-block; padding: 12px 25px; 
            background: linear-gradient(45deg, #007bff, #0056b3); 
            color: white; text-decoration: none; 
            border-radius: 8px; margin: 8px;
            font-weight: 500; transition: all 0.3s;
        }
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,123,255,0.4); 
        }
        .toggle-icon { 
            float: right; font-size: 1.5em; 
            transition: transform 0.3s; color: #007bff;
        }
        .toggle-icon.open { transform: rotate(180deg); }
        .no-issues {
            text-align: center; padding: 30px; 
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-radius: 10px; color: #155724; font-size: 1.2em;
        }
        @media (max-width: 768px) {
            .summary { grid-template-columns: 1fr; }
            .compliance-levels { grid-template-columns: 1fr; }
            .page-stats { flex-direction: column; }
            .issue-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🎯 Includo</h1>
            <div class='subtitle'>Report Completo Accessibilità WCAG 2.2 - European Accessibility Act</div>
            <div style='margin-top: 16px; display:flex; gap:10px; flex-wrap:wrap;'>
                <a class='btn' href='accessibility_statement.php?session_id=<?= (int)$sessionId ?>&lang=it&export=1' target='_blank' rel='noopener'>📄 Genera Dichiarazione (IT)</a>
                <a class='btn' href='accessibility_statement.php?session_id=<?= (int)$sessionId ?>&lang=en&export=1' target='_blank' rel='noopener'>📄 Generate Statement (EN)</a>
            </div>

            <div style='margin-top: 20px; font-size: 1.1em;'>
                <strong>Sito Analizzato:</strong> <?= htmlspecialchars($session['site_url']) ?><br>
                <strong>Data Audit:</strong> <?= date('d/m/Y H:i', strtotime($session['start_time'])) ?>
            </div>
        </div>
        
        <div class='summary'>
            <div class='stat-card'>
                <div class='stat-number'><?= $session['total_pages'] ?></div>
                <div class='stat-label'>Pagine Analizzate</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'><?= $session['total_issues'] ?></div>
                <div class='stat-label'>Problemi Totali</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'><?= $avgIssuesPerPage ?></div>
                <div class='stat-label'>Media Problemi/Pagina</div>
            </div>
            <div class='stat-card'>
                <div class='stat-number'><?= $duration ?></div>
                <div class='stat-label'>Durata Audit</div>
            </div>
        </div>
        
        <div class='compliance-overview'>
            <h2>📊 Panoramica Conformità WCAG</h2>
            <div class='compliance-levels'>
                <div class='compliance-level level-a'>
                    <div>Livello A (Fondamentale)</div>
                    <div style='font-size: 2em; margin-top: 10px;'><?= $this->getIssuesCountByLevel($statistics, 'A') ?></div>
                    <div style='font-size: 0.9em; margin-top: 5px;'>problemi rilevati</div>
                </div>
                <div class='compliance-level level-aa'>
                    <div>Livello AA (Standard)</div>
                    <div style='font-size: 2em; margin-top: 10px;'><?= $this->getIssuesCountByLevel($statistics, 'AA') ?></div>
                    <div style='font-size: 0.9em; margin-top: 5px;'>problemi rilevati</div>
                </div>
                <div class='compliance-level level-aaa'>
                    <div>Livello AAA (Avanzato)</div>
                    <div style='font-size: 2em; margin-top: 10px;'><?= $this->getIssuesCountByLevel($statistics, 'AAA') ?></div>
                    <div style='font-size: 0.9em; margin-top: 5px;'>problemi rilevati</div>
                </div>
            </div>
        </div>

        <?php if (!empty($pages)): ?>
        <div class='page-list'>
            <h2>📄 Analisi Dettagliata per Pagina</h2>
            <p>Clicca su ogni pagina per visualizzare i dettagli dei problemi rilevati:</p>
            
            <?php foreach ($pages as $page): ?>
            <div class='page-item'>
                <div class='page-header' onclick='togglePageDetails(<?= $page['id'] ?>)'>
                    <h3 class='page-title'>
                        <?= htmlspecialchars($page['title'] ?: 'Pagina senza titolo') ?>
                        <span class='toggle-icon' id='icon-<?= $page['id'] ?>'>▼</span>
                    </h3>
                    <div class='page-url'><?= htmlspecialchars($page['url']) ?></div>
                    <div class='page-stats'>
                        <div class='page-stat'>
                            <strong>Problemi Totali:</strong> <?= $page['total_issues'] ?>
                        </div>
                        <div class='page-stat'>
                            <strong>Livello A:</strong> <?= $page['wcag_level_a_issues'] ?>
                        </div>
                        <div class='page-stat'>
                            <strong>Livello AA:</strong> <?= $page['wcag_level_aa_issues'] ?>
                        </div>
                        <div class='page-stat'>
                            <strong>Livello AAA:</strong> <?= $page['wcag_level_aaa_issues'] ?>
                        </div>
                        <div class='page-stat'>
                            <strong>Tempo:</strong> <?= round($page['response_time'], 2) ?>s
                        </div>
                    </div>
                </div>
                <div id='details-<?= $page['id'] ?>' class='page-details'>
                    <?php
                    $stmt = $this->pdo->prepare("
                        SELECT * FROM accessibility_issues 
                        WHERE page_audit_id = ? 
                        ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), wcag_level, wcag_criterion
                    ");
                    $stmt->execute([$page['id']]);
                    $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($issues)): ?>
                    <div class='no-issues'>
                        <strong>✅ Eccellente! Nessun problema di accessibilità rilevato!</strong>
                        <br><small>Questa pagina rispetta tutti i criteri WCAG verificati automaticamente.</small>
                    </div>
                    <?php else: ?>
                    <h4>🔍 Problemi Rilevati (<?= count($issues) ?>)</h4>
                    
                    <?php foreach ($issues as $issue): ?>
                    <div class='issue <?= $issue['severity'] ?>'>
                        <div class='issue-header'>
                            <div class='issue-title'><?= ucfirst(str_replace('_', ' ', $issue['issue_type'])) ?></div>
                            <div>
                                <span class='wcag-badge wcag-<?= strtolower($issue['wcag_level']) ?>'>
                                    WCAG <?= $issue['wcag_criterion'] ?> (<?= $issue['wcag_level'] ?>)
                                </span>
                                <span class='wcag-badge severity-<?= $issue['severity'] ?>'>
                                    <?= strtoupper($issue['severity']) ?>
                                </span>
                            </div>
                        </div>
                        <div class='issue-description'><?= htmlspecialchars($issue['description']) ?></div>
                        
                        <?php if (!empty($issue['element_selector'])): ?>
                        <div style='margin: 10px 0;'>
                            <strong>Elemento:</strong> 
                            <code style='background: #f8f9fa; padding: 2px 6px; border-radius: 4px;'>
                                <?= htmlspecialchars($issue['element_selector']) ?>
                            </code>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($issue['line_number'])): ?>
                        <div style='margin: 10px 0;'><strong>Riga:</strong> <?= $issue['line_number'] ?></div>
                        <?php endif; ?>
                        
                        <div class='issue-recommendation'>
                            <strong>💡 Raccomandazione:</strong> <?= htmlspecialchars($issue['recommendation']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class='footer'>
            <h3>🎯 Includo - Sistema Professionale di Audit Accessibilità</h3>
            <p>Report generato il <?= date('d/m/Y H:i:s') ?> | Conforme WCAG 2.2 Level AA e European Accessibility Act</p>
            <p><strong>Sessione ID:</strong> <?= $sessionId ?> | <strong>Versione:</strong> 2.1.0</p>
            <div style='margin-top: 20px;'>
                <a href='?report=<?= $sessionId ?>&format=json' class='btn'>📊 Scarica Dati JSON</a>
                <a href='?report=<?= $sessionId ?>&format=csv' class='btn'>📋 Esporta CSV</a>
                <a href='javascript:window.print()' class='btn'>🖨️ Stampa Report</a>
            </div>
        </div>
    </div>

    <script>
        function togglePageDetails(pageId) {
            const details = document.getElementById('details-' + pageId);
            const icon = document.getElementById('icon-' + pageId);
            
            if (details.style.display === 'none' || details.style.display === '') {
                details.style.display = 'block';
                icon.classList.add('open');
                icon.innerHTML = '▲';
            } else {
                details.style.display = 'none';
                icon.classList.remove('open');
                icon.innerHTML = '▼';
            }
        }
        
        window.addEventListener('load', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(function(stat) {
                const finalValue = parseInt(stat.textContent);
                if (!isNaN(finalValue) && finalValue > 0) {
                    let currentValue = 0;
                    const increment = Math.max(1, Math.ceil(finalValue / 50));
                    const timer = setInterval(function() {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            stat.textContent = currentValue;
                        }
                    }, 30);
                }
            });
        });
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
    
    private function generateJSONReport($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.*,
                (SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', p.id,
                        'url', p.url,
                        'title', p.title,
                        'status_code', p.status_code,
                        'response_time', p.response_time,
                        'total_issues', p.total_issues,
                        'wcag_levels', JSON_OBJECT(
                            'A', p.wcag_level_a_issues,
                            'AA', p.wcag_level_aa_issues,
                            'AAA', p.wcag_level_aaa_issues
                        ),
                        'issues', (
                            SELECT JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    'type', i.issue_type,
                                    'criterion', i.wcag_criterion,
                                    'level', i.wcag_level,
                                    'severity', i.severity,
                                    'description', i.description,
                                    'recommendation', i.recommendation,
                                    'element_selector', i.element_selector,
                                    'line_number', i.line_number
                                )
                            )
                            FROM accessibility_issues i 
                            WHERE i.page_audit_id = p.id
                        )
                    )
                ) FROM page_audits p WHERE p.session_id = s.id) as pages
            FROM audit_sessions s 
            WHERE s.id = ?
        ");
        
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("Sessione audit non trovata: $sessionId");
        }
        
        $result['pages'] = json_decode($result['pages'] ?: '[]', true);
        
        $result['report_metadata'] = [
            'generated_at' => date('c'),
            'generated_by' => 'Includo WCAG Auditor 2.1',
            'wcag_version' => '2.1',
            'eaa_compliance' => true,
            'report_format' => 'json'
        ];
        
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    private function generateCSVReport($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pa.url,
                pa.title,
                pa.status_code,
                pa.response_time,
                ai.wcag_criterion,
                ai.wcag_level,
                ai.severity,
                ai.issue_type,
                ai.description,
                ai.recommendation,
                ai.element_selector,
                ai.line_number
            FROM page_audits pa
            LEFT JOIN accessibility_issues ai ON pa.id = ai.page_audit_id
            WHERE pa.session_id = ?
            ORDER BY pa.url, ai.wcag_criterion
        ");
        
        $stmt->execute([$sessionId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $csv = "URL,Titolo,Status,Tempo Risposta,Criterio WCAG,Livello,Gravità,Tipo Problema,Descrizione,Raccomandazione,Elemento,Riga\n";
        
        foreach ($results as $row) {
            $csv .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field ?? '') . '"';
            }, $row)) . "\n";
        }
        
        return $csv;
    }
    
    private function calculateDuration($start, $end) {
        if (!$end) return 'In corso...';
        
        $startTime = new DateTime($start);
        $endTime = new DateTime($end);
        $interval = $startTime->diff($endTime);
        
        if ($interval->h > 0) {
            return $interval->format('%H:%I:%S');
        } else {
            return $interval->format('%I:%S');
        }
    }
    
    private function getIssuesCountByLevel($statistics, $level) {
        $count = 0;
        foreach ($statistics as $stat) {
            if ($stat['wcag_level'] === $level) {
                $count += $stat['count'];
            }
        }
        return $count;
    }
}
?>