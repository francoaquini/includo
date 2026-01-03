<?php
/**
 * Pagina Storico Scansioni
 */

if (!isset($auditor)) {
    die('Accesso non autorizzato');
}

// Ottieni tutte le sessioni dal database
try {
    $stmt = $auditor->getPDO()->prepare("
        SELECT 
            s.id,
            s.site_url,
            s.start_time,
            s.end_time,
            s.total_pages,
            s.total_issues,
            s.status,
            s.max_pages_limit,
            CASE 
                WHEN s.status = 'completed' THEN 100
                WHEN s.total_pages > 0 THEN ROUND((s.total_pages / s.max_pages_limit) * 100, 1)
                ELSE 0
            END as completion_percentage
        FROM audit_sessions s
        ORDER BY s.start_time DESC
        LIMIT 50
    ");
    
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    Logger::error("Errore recupero sessioni: " . $e->getMessage());
    $sessions = [];
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storico Scansioni - Includo</title>
    <link rel="stylesheet" href="<?php echo INCLUDO_BASE_PATH; ?>assets/navbar.css">
    <style>
        .main-container {
            max-width: 1200px; margin: 0 auto; padding: 20px;
        }
        .content-card {
            background: white; padding: 40px; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); margin-bottom: 20px;
        }
        
        .sessions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px; margin: 25px 0;
        }
        .session-card {
            border: 2px solid #dee2e6; border-radius: 15px; padding: 25px;
            transition: all 0.3s; background: linear-gradient(135deg, #f8f9fa, #ffffff);
        }
        .session-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .session-card.completed {
            border-color: #28a745; background: linear-gradient(135deg, #d4edda, #f8fff9);
        }
        .session-card.running {
            border-color: #007bff; background: linear-gradient(135deg, #cce7ff, #f0f8ff);
        }
        .session-card.error {
            border-color: #dc3545; background: linear-gradient(135deg, #f8d7da, #fff5f5);
        }
        
        .session-card.incomplete {
            border-color: #ffc107; background: linear-gradient(135deg, #fff3cd, #fef9e7);
        }
        .status-incomplete { background: #fff3cd; color: #856404; }
        
        .session-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #dee2e6;
        }
        .session-url {
            font-weight: 600; color: #333; font-size: 1.1em;
            word-break: break-all; flex: 1; margin-right: 15px;
        }
        .session-status {
            padding: 6px 12px; border-radius: 20px; font-size: 0.85em;
            font-weight: 600; white-space: nowrap;
        }
        .status-completed { background: #d4edda; color: #155724; }
        .status-running { background: #cce7ff; color: #004085; }
        .status-error { background: #f8d7da; color: #721c24; }
        
        .session-stats {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 15px; margin: 20px 0;
        }
        .stat-item {
            text-align: center; padding: 15px; border-radius: 10px;
            background: rgba(255,255,255,0.7);
        }
        .stat-number {
            display: block; font-size: 1.8em; font-weight: bold;
            color: #007bff; margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9em; color: #666;
        }
        
        .session-actions {
            display: flex; gap: 10px; margin-top: 20px;
        }
        .btn {
            flex: 1; padding: 12px 16px; border: none; border-radius: 8px;
            text-decoration: none; text-align: center; font-weight: 500;
            transition: all 0.3s; cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3); color: white;
        }
        .btn-secondary {
            background: #6c757d; color: white;
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997); color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn:disabled {
            opacity: 0.5; cursor: not-allowed;
            transform: none; box-shadow: none;
        }
        
        .empty-state {
            text-align: center; padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px; margin: 25px 0;
        }
        .empty-icon {
            font-size: 4em; margin-bottom: 20px; opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .sessions-grid { grid-template-columns: 1fr; }
            .session-stats { grid-template-columns: 1fr; }
            .session-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../partials/navbar.php'; ?>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <div class="main-container">
        <div class="content-card">
            <h2>📊 Storico delle Scansioni</h2>
            <p>Gestisci e monitora tutte le tue scansioni di accessibilità</p>
            
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔍</div>
                    <h3>Nessuna scansione trovata</h3>
                    <p>Non hai ancora eseguito alcuna scansione di accessibilità.</p>
                    <a href="?page=new" class="btn btn-primary" style="display: inline-block; margin-top: 15px;">
                        🚀 Inizia la Prima Scansione
                    </a>
                </div>
            <?php else: ?>
                <div class="sessions-grid">
                    <?php foreach ($sessions as $session): ?>
                        <?php
                        $statusClass = '';
                        $statusText = '';
                        $statusIcon = '';
                        
                        switch($session['status']) {
                            case 'completed':
                                $statusClass = 'completed';
                                $statusText = 'Completata';
                                $statusIcon = '✅';
                                break;
                                case 'paused':
                                $statusClass = 'incomplete';
                                $statusText = 'Incompleta';
                                $statusIcon = '⚠️';
                                break;
                            case 'running':
                                $statusClass = 'running';
                                $statusText = 'In Corso';
                                $statusIcon = '🔄';
                                break;
                            case 'error':
                                $statusClass = 'error';
                                $statusText = 'Errore';
                                $statusIcon = '❌';
                                break;
                        }
                        ?>
                        
                        <div class="session-card <?= $statusClass ?>">
                            <div class="session-header">
                                <div class="session-url"><?= htmlspecialchars($session['site_url']) ?></div>
                                <div class="session-status status-<?= $session['status'] ?>">
                                    <?= $statusIcon ?> <?= $statusText ?>
                                </div>
                            </div>
                            
                            <div class="session-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= $session['total_pages'] ?></span>
                                    <div class="stat-label">Pagine Analizzate</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $session['total_issues'] ?></span>
                                    <div class="stat-label">Problemi Rilevati</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $session['completion_percentage'] ?>%</span>
                                    <div class="stat-label">Completamento</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= date('d/m/Y', strtotime($session['start_time'])) ?></span>
                                    <div class="stat-label">Data Scansione</div>
                                </div>
                            </div>
                            
                            <div class="session-actions">
                                <a href="?report=<?= $session['id'] ?>" class="btn btn-primary">
                                    📊 Report
                                </a>
                                
                                <?php if ($session['status'] === 'running' && $session['completion_percentage'] < 100): ?>
                                    <a href="<?php echo INCLUDO_BASE_PATH; ?>?resume=<?= $session['id'] ?>" class="btn btn-success">
                                        ▶️ Continua
                                    </a>
                                <?php elseif ($session['status'] === 'paused'): ?>
                                    <a href="<?php echo INCLUDO_BASE_PATH; ?>?resume=<?= $session['id'] ?>" class="btn btn-success">
                                        ▶️ Continua
                                    </a>
                                <?php elseif ($session['status'] === 'error'): ?>
                                    <a href="<?php echo INCLUDO_BASE_PATH; ?>?resume=<?= $session['id'] ?>" class="btn btn-secondary">
                                        🔄 Riprova
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        ✅ Completata
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 40px;">
                    <a href="?page=new" class="btn btn-success" style="display: inline-block; padding: 15px 30px; font-size: 1.1em;">
                        🚀 Nuova Scansione
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>