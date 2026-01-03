<?php
// Partial: shared navigation bar
// Can be required from pages in different folders. The active class depends on $_GET['page']
$current = isset($_GET['page']) ? $_GET['page'] : null;
$base = defined('INCLUDO_BASE_PATH') ? INCLUDO_BASE_PATH : '/';
?>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?php echo $base; ?>" class="nav-brand">🎯 Includo</a>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?php echo $base; ?>" class="nav-link <?php echo ($current === null ? 'active' : ''); ?>">🏠 Home</a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base; ?>?page=sessions" class="nav-link <?php echo ($current === 'sessions' ? 'active' : ''); ?>">📊 Storico Scansioni</a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base; ?>?page=new" class="nav-link <?php echo ($current === 'new' ? 'active' : ''); ?>">🚀 Nuova Scansione</a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base; ?>?page=help" class="nav-link <?php echo ($current === 'help' ? 'active' : ''); ?>">💡 Guida</a>
            </li>
        </ul>
    </div>
</nav>
