<?php
declare(strict_types=1);
require_once __DIR__.'/../app.php';
require_once __DIR__.'/../ui.php';
require_once __DIR__.'/../help_documents.php';
$documents = help_documents();
$document = request_string($_GET, 'document') ?: 'overview';
if ($document !== 'overview' && !isset($documents[$document])) {
    http_response_code(404);
    page_start('Document not found', 'Choose a document from the Help menu.');
    echo '<a href="help.php">Open Help</a>';
    page_end();
    return;
}
$GLOBALS['pageLayout'] = current_user() ? 'workspace' : 'public';
page_start($document === 'overview' ? 'Help & documentation' : $documents[$document]['title'],
    'Student Grade & Performance Analysis DDS · Requirements and everyday use.');
?>
<nav class="document-tabs no-print" aria-label="Help documents">
    <a class="btn <?=$document==='overview'?'btn-primary':'btn-outline-primary'?>" href="help.php" <?=$document==='overview'?'aria-current="page"':''?>>Help overview</a>
    <?php foreach ($documents as $key=>$entry): ?>
        <a class="btn <?=$document===$key?'btn-primary':'btn-outline-primary'?>" href="help.php?document=<?=h($key)?>" <?=$document===$key?'aria-current="page"':''?>><?=h($entry['short_title'])?></a>
    <?php endforeach; ?>
</nav>
<?php if ($document === 'overview'): ?>
<section class="panel"><p class="eyebrow">START HERE</p><h2>How the system works</h2><p>Administrators set up classes and enrollment. Faculty save and submit grades. Administrators review and publish results. Students view their own published grades and reports.</p><p><strong>Draft → Submitted → Approved → Published</strong></p><p>Use the Help menu to open either requirements document. Both describe the features already implemented in this application.</p></section>
<div class="two-column">
    <?php foreach ($documents as $key=>$entry): ?><article class="panel"><p class="eyebrow"><?=h($entry['code'])?></p><h2><?=h($entry['title'])?></h2><p><?=h($entry['summary'])?></p><a class="btn btn-outline-primary" href="help.php?document=<?=h($key)?>">Read document</a></article><?php endforeach; ?>
</div>
<section class="panel"><h2>Quick guide by role</h2><?php data_table([
    ['role'=>'Administrator','steps'=>'Academic setup → Accounts → Students → Classes & assignments → Enrollment → Grade review → Publish'],
    ['role'=>'Faculty','steps'=>'Assigned classes → Grade entry → Load class roster → Save draft → Submit'],
    ['role'=>'Student','steps'=>'Overview → My subjects → My grades → My reports → Generate report → Print / Save as PDF'],
], ['role'=>'Role','steps'=>'Where to start']); ?><p>Student access needs an administrator-verified link to an academic record. Approval alone does not publish a grade.</p><a href="login.php">Open sign-in</a></section>
<?php else: $entry=$documents[$document]; ?>
<article class="requirements-document">
    <section class="panel document-introduction"><p class="eyebrow"><?=h($entry['code'])?> · VERSION 1.0</p><h2>Document overview</h2><p><?=h($entry['summary'])?></p><p class="text-secondary">Baseline: the implemented local application. Updated November 7, 2026.</p><button class="btn btn-outline-primary no-print" type="button" onclick="window.print()">Print / Save as PDF</button></section>
    <nav class="panel document-contents no-print" aria-label="Document contents"><h2>Contents</h2><ol><?php foreach ($entry['sections'] as $index=>$section): ?><li><a href="#section-<?=$index+1?>"><?=h($section['title'])?></a></li><?php endforeach; ?></ol></nav>
    <?php foreach ($entry['sections'] as $index=>$section): ?>
        <section class="panel document-section" id="section-<?=$index+1?>"><h2><?=$index+1?>. <?=h($section['title'])?></h2>
            <?php foreach ($section['paragraphs']??[] as $paragraph): ?><p><?=h($paragraph)?></p><?php endforeach; ?>
            <?php if (isset($section['items'])): ?><ul><?php foreach ($section['items'] as $item): ?><li><?=h($item)?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if (isset($section['rows'])) data_table($section['rows'], $section['columns']); ?>
        </section>
    <?php endforeach; ?>
</article>
<?php endif; page_end();
