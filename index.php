<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/ui.php';
$GLOBALS['pageTitle'] = 'Home';
$GLOBALS['pageLayout'] = 'home';
require __DIR__ . '/includes/layout/header.php';
require __DIR__ . '/includes/pages/home.php';
require __DIR__ . '/includes/layout/footer.php';
