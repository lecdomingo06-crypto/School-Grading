</main>
<?php
$footerSchoolName = setting('school_name', 'Student Grade DDS');
$footerCampusName = setting('campus');
if ($footerCampusName === 'Campus name - configure before issuing reports') $footerCampusName = '';
?>
<footer class="app-footer no-print">
    <span><strong>Student Grade DDS</strong><span class="footer-separator" aria-hidden="true"> / </span>Academic records & performance</span>
    <span><?= h($footerSchoolName) ?><?php if ($footerCampusName !== ''): ?> · <?= h($footerCampusName) ?><?php endif; ?></span>
</footer>
</body>
</html>
