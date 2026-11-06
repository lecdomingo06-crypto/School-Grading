<?php
$user = current_user();
$flashMessage = get_flash();
$title = $GLOBALS['pageTitle'] ?? 'Student Grade DDS';
$currentFile = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$homePage = ($GLOBALS['pageLayout'] ?? '') === 'home';
$authPage = !$homePage && in_array($currentFile, ['login.php', 'register.php'], true);
$workspacePage = $user !== null && !$homePage && !$authPage;
$schoolName = setting('school_name', 'Student Grade DDS');
$campusName = setting('campus');
if ($campusName === 'Campus name - configure before issuing reports') $campusName = '';
$schoolLogo = school_logo();
$registrationOpen = setting('registration_open', '1') === '1';
$bodyClass = $homePage ? 'home-page' : ($authPage ? 'auth-page' : 'workspace-page');
$navigationIcon = static function (string $kind): string {
    $paths = [
        'overview' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5"/>',
        'book' => '<path d="M12 5v16M12 5C8 2 4 3 2 4v15c3-1 6-1 10 2 4-3 7-3 10-2V4c-3-1-6-2-10 1Z"/>',
        'record' => '<path d="M7 3H4v18h16V3h-3M8 2h8v4H8zM8 11h8M8 16h5"/>',
        'chart' => '<path d="M4 3v18h18M8 16v-5M13 16V7M18 16v-8"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/>',
        'settings' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/><circle cx="12" cy="12" r="5"/>',
        'archive' => '<rect x="3" y="3" width="18" height="5" rx="1"/><path d="M5 8v13h14V8M9 12h6"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$kind] ?? $paths['record']).'</svg>';
};
$activeNavigation = static function (string $url) use ($currentFile): bool {
    $linkFile = basename((string) parse_url($url, PHP_URL_PATH));
    if ($currentFile === 'records.php') {
        $entity = request_string($_GET, 'entity') ?: 'students';
        $aliases = ['students'=>'students.php', 'subjects'=>'subjects.php', 'interventions'=>'interventions.php'];
        if (($aliases[$entity] ?? '') === $linkFile) return true;
        if ($linkFile === 'academic.php' && in_array($entity, ['courses', 'school_years', 'terms', 'sections', 'grading_policies'], true)) return true;
    }
    if ($linkFile !== $currentFile) return false;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
    foreach ($parameters as $key => $value) {
        $default = $currentFile === 'student.php' && $key === 'view' ? 'overview' : '';
        if (request_string($_GET, $key) !== (string) $value && !(request_string($_GET, $key) === '' && $value === $default)) return false;
    }
    return true;
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#173f35">
    <meta name="description" content="Student Grade DDS brings academic records, reviewed grades, and performance insights into one secure school workspace.">
    <title><?= h($title) ?> · Student Grade DDS</title>
    <link rel="icon" href="assets/student-grade-logo.png" type="image/png">
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/workspace.css">
    <?php if ($homePage): ?><link rel="stylesheet" href="assets/css/home.css"><?php endif; ?>
    <script src="assets/js/app.js" defer></script>
</head>
<body class="<?= h($bodyClass) ?>">
<a class="skip-link no-print" href="#main-content">Skip to content</a>
<header class="topbar no-print">
    <a class="brand" href="index.php" aria-label="Student Grade DDS home">
        <span class="brand-logo"><img src="<?= h($schoolLogo) ?>" alt="" width="40" height="40"></span>
        <span class="brand-copy">Student Grade <small>PERFORMANCE & RECORDS</small></span>
    </a>
    <?php if ($workspacePage): ?>
        <button class="nav-toggle btn btn-outline-light" id="workspace-nav-toggle" type="button" aria-controls="workspace-navigation" aria-expanded="false"><span aria-hidden="true">☰</span> Menu</button>
        <div class="account">
            <a class="account-profile" href="profile.php"><span class="account-avatar" aria-hidden="true"><?= h(mb_substr($user['full_name'], 0, 1)) ?></span><span class="account-identity"><?= h($user['full_name']) ?><small><?= h($user['role']) ?></small></span></a>
            <form method="post" action="logout.php"><?php csrf_field(); ?><button class="btn btn-sm btn-outline-light" type="submit">Sign out</button></form>
        </div>
    <?php else: ?>
        <nav class="public-nav" aria-label="Public navigation">
            <a href="index.php"<?= $homePage ? ' aria-current="page"' : '' ?>>Home</a>
            <a class="public-workflow-link" href="index.php#workflow">How it works</a>
            <?php if ($user): ?>
                <a class="btn btn-gold" href="<?= h(dashboard_url($user)) ?>">My dashboard <span aria-hidden="true">↗</span></a>
            <?php else: ?>
                <a class="<?= $currentFile === 'login.php' ? 'public-current' : '' ?>" href="login.php"<?= $currentFile === 'login.php' ? ' aria-current="page"' : '' ?>>Sign in</a>
                <?php if ($registrationOpen): ?><a class="btn btn-gold" href="register.php"<?= $currentFile === 'register.php' ? ' aria-current="page"' : '' ?>>Request an account <span aria-hidden="true">↗</span></a><?php endif; ?>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</header>
<?php if ($workspacePage): ?>
    <div class="nav-backdrop no-print" id="workspace-nav-backdrop" hidden></div>
    <nav class="app-nav no-print" id="workspace-navigation" aria-label="Main navigation">
        <div class="nav-school"><span class="eyebrow">YOUR WORKSPACE</span><strong><?= h($schoolName) ?></strong><?php if ($campusName !== ''): ?><small><?= h($campusName) ?></small><?php endif; ?></div>
        <?php
        if (is_student()) {
            $navigationGroups = [
                'My academics' => [
                    ['student.php?view=overview', 'Overview', 'overview'],
                    ['student.php?view=subjects', 'My subjects', 'book'],
                    ['student.php?view=grades', 'My grades', 'record'],
                    ['student.php?view=history', 'Academic history', 'archive'],
                    ['student.php?view=reports', 'My reports', 'chart'],
                ],
                'My account' => [
                    ['student.php?view=notifications', 'Notifications', 'bell'],
                    ['profile.php', 'Profile & password', 'profile'],
                ],
            ];
        } else {
            $navigationGroups = ['Workspace' => [['dashboard.php', 'Dashboard', 'overview']]];
            $navigationGroups['Academic records'] = [
                ['students.php', 'Students', 'people'],
                ['subjects.php', 'Subjects', 'book'],
                ['records.php?entity=offerings', 'Assigned classes', 'book'],
                ['records.php?entity=enrollments', 'Enrollment', 'record'],
                ['grades.php', is_admin() ? 'Grade review' : 'Grade entry', 'record'],
            ];
            if (is_admin()) {
                $navigationGroups['Academic records'][2][1] = 'Classes & assignments';
                array_unshift($navigationGroups['Academic records'], ['academic.php', 'Academic setup', 'settings']);
            }
            $navigationGroups['Insights & support'] = [
                ['reports.php', 'Reports', 'chart'],
                ['interventions.php', 'Interventions', 'people'],
                ['import.php', 'Import / export', 'archive'],
                ['notifications.php', 'Notifications', 'bell'],
            ];
            if (is_admin()) $navigationGroups['Administration'] = [
                ['users.php', 'Accounts', 'people'],
                ['logs.php', 'Audit trail', 'record'],
                ['backup.php', 'Backups', 'archive'],
                ['settings.php', 'System settings', 'settings'],
            ];
            $navigationGroups['My account'] = [['profile.php', 'Profile & password', 'profile']];
        }
        foreach ($navigationGroups as $groupLabel => $navigationLinks): ?>
            <div class="nav-group"><p class="nav-group-label"><?= h($groupLabel) ?></p>
                <?php foreach ($navigationLinks as [$url, $label, $icon]): $selected = $activeNavigation($url); ?>
                    <a href="<?= h($url) ?>"<?= $selected ? ' class="selected" aria-current="page"' : '' ?>><?= $navigationIcon($icon) ?><span><?= h($label) ?></span><?php if ($selected): ?><span class="nav-current-dot" aria-hidden="true"></span><?php endif; ?></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <a class="nav-home-link" href="index.php">Visit public homepage <span aria-hidden="true">↗</span></a>
    </nav>
<?php endif; ?>
<main class="app-main" id="main-content" tabindex="-1">
<?php if ($flashMessage): ?><div class="alert alert-<?= h($flashMessage['type']) ?>" role="alert"><?= h($flashMessage['message']) ?></div><?php endif; ?>
