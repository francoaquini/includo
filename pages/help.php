<?php
/**
 * Pagina Guida - spiegazioni su funzionamento, logica e norme rispettate
 */

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Guida - Includo</title>
    <link rel="stylesheet" href="<?php echo INCLUDO_BASE_PATH; ?>assets/navbar.css">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0;color:#222}
        .wrap{max-width:1100px;margin:24px auto;padding:18px}
        .card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.05);margin-bottom:18px}
        h1{margin:0 0 8px}
        .muted{color:#666}
        pre{background:#f6f8fa;padding:12px;border-radius:8px;overflow:auto}
    </style>
</head>
<body>
<?php require __DIR__ . '/../partials/navbar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="wrap">
    <div class="card">
        <h1>🧭 Guida a Includo</h1>
        <p class="muted">Questa guida spiega come utilizzare Includo, la logica del crawler, le regole WCAG applicate e il comportamento del sistema.</p>
    </div>

    <div class="card">
        <h2>Come funziona</h2>
        <p>Includo esegue un crawl del sito di partenza e analizza ogni pagina in base ai criteri WCAG 2.2. Puoi impostare il numero massimo di pagine da analizzare per singola sessione; se il sito è molto grande puoi eseguire più sessioni consecutive.</p>
        <ul>
            <li><strong>Nuova Scansione:</strong> inserisci l'URL iniziale e il numero massimo di pagine da processare.</li>
            <li><strong>Storico Scansioni:</strong> vedi sessioni precedenti, stato e report.</li>
            <li><strong>Riprendi scansione:</strong> quando una sessione non ha completato il sito, puoi riprenderla dalla posizione in cui si era fermata.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Logica del crawler</h2>
        <p>Il crawler visita le pagine scoperte in modo breadth-first, estrae link interni e li aggiunge alla coda. Per evitare duplicati Includo controlla gli URL già visitati. Puoi limitare il numero di pagine per sessione per distribuire l'analisi su più step.</p>
    </div>

    <div class="card">
        <h2>Norme e standard applicati</h2>
        <ul>
            <li><strong>WCAG 2.2</strong> — criteri di accessibilità valutati automaticamente.</li>
            <li><strong>European Accessibility Act (EAA)</strong> — verifica dei requisiti rilevanti per i settori indicati.</li>
            <li><strong>EN 301 549</strong> — linee guida tecniche di riferimento.</li>
        </ul>
    </div>

    <div class="card">
        <h2>Interpretazione dei risultati</h2>
        <p>Le issue sono classificate per gravità (critical, high, medium, low) e per livello WCAG (A, AA, AAA). Usa il report per identificare le priorità di intervento.</p>
    </div>

    <div class="card">
        <h2>Riprendere una scansione (consigli)</h2>
        <p>Se la scansione è marcata come "Incompleta", clicca su <strong>Continua</strong> nello storico per riprendere il processo; Includo riprenderà dalla coda salvata e continuerà fino al limite di pagine impostato.</p>
    </div>

    <div class="card">
        <h2>Domande frequenti</h2>
        <p class="muted">Per dubbi tecnici o per segnalare problemi, contatta l'amministratore indicato nella configurazione.</p>
    </div>
</div>
</body>
</html>
