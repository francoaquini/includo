<?php
require __DIR__ . '/../config.php';

// Render navbar + header partials without initializing the auditor/DB
ob_start();
require __DIR__ . '/../partials/navbar.php';
require __DIR__ . '/../partials/header.php';
$html = ob_get_clean();

// Output a short snippet to verify both elements are present
echo "--- START SNIPPET ---\n";
echo (strip_tags($html) ? 'Partials rendered (text):' : 'No output');
echo "\n";
echo "Contains <nav>: " . (strpos($html, '<nav') !== false ? 'YES' : 'NO') . "\n";
echo "Contains <header>: " . (strpos($html, '<header') !== false ? 'YES' : 'NO') . "\n";
echo "--- END SNIPPET ---\n";
