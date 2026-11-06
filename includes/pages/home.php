<?php
/** Public content: never query or expose private academic data here. */
$homeDashboard = $user ? dashboard_url($user) : 'login.php';
?>
<section class="home-hero" aria-labelledby="home-title">
    <div class="home-hero-copy">
        <p class="eyebrow home-kicker"><span aria-hidden="true"></span> ACADEMIC RECORDS, CONNECTED</p>
        <p class="home-school"><?= h($schoolName) ?><?php if ($campusName !== ''): ?><span><?= h($campusName) ?></span><?php endif; ?></p>
        <h1 id="home-title">A clearer view of<br>every academic <em>journey.</em></h1>
        <p class="home-lead">Bring class records, reviewed grades, and academic support together. One organized workspace for administrators, faculty, and students.</p>
        <div class="home-actions">
            <a class="btn btn-gold" href="<?= h($homeDashboard) ?>"><?= $user ? 'Open my dashboard' : 'Sign in to your workspace' ?> <span aria-hidden="true">&#8599;</span></a>
            <a class="home-text-link" href="#workflow">See how it works <span aria-hidden="true">&#8595;</span></a>
        </div>
        <p class="home-access-note">Access is based on your role. Academic records stay private.</p>
    </div>
    <div class="home-journey" aria-label="Academic workflow overview">
        <div class="journey-heading"><span class="eyebrow">FROM CLASS TO CLARITY</span><span class="journey-emblem" aria-hidden="true">SG</span></div>
        <div class="journey-step"><span class="journey-number" aria-hidden="true">01</span><div><h2>Organize the term</h2><p>Subjects, faculty assignments, and student enrollment.</p></div><span class="journey-symbol" aria-hidden="true">&#10003;</span></div>
        <div class="journey-step"><span class="journey-number" aria-hidden="true">02</span><div><h2>Record &amp; review</h2><p>Faculty enter grades. Administrators review and publish.</p></div><span class="journey-symbol" aria-hidden="true">&#10003;</span></div>
        <div class="journey-step"><span class="journey-number" aria-hidden="true">03</span><div><h2>Understand &amp; support</h2><p>Students view published results. Staff follow up where needed.</p></div><span class="journey-symbol" aria-hidden="true">&#8599;</span></div>
        <div class="journey-footer"><span class="journey-dot" aria-hidden="true"></span> Clear responsibilities. A traceable process.</div>
    </div>
</section>

<section class="home-features home-section" aria-labelledby="features-title">
    <div class="home-section-heading"><div><p class="eyebrow">ONE CONNECTED WORKSPACE</p><h2 id="features-title">Less searching.<br>More understanding.</h2></div><p>Follow the academic record from enrollment to published results, with the right information available to the right people.</p></div>
    <div class="home-feature-grid">
        <article><span class="feature-index" aria-hidden="true">01 / RECORDS</span><h3>Organized academics</h3><p>Manage terms, subjects, class assignments, and enrollment in one consistent place.</p></article>
        <article><span class="feature-index" aria-hidden="true">02 / GRADES</span><h3>A reviewed grade process</h3><p>Move grades from draft to publication, with recorded approvals and correction history.</p></article>
        <article><span class="feature-index" aria-hidden="true">03 / INSIGHTS</span><h3>Support that follows through</h3><p>Use performance summaries, printable reports, and intervention records to guide follow-ups.</p></article>
    </div>
</section>

<section class="home-roles home-section" aria-labelledby="roles-title">
    <div class="home-section-heading"><div><p class="eyebrow">BUILT AROUND YOUR ROLE</p><h2 id="roles-title">Your work. Your workspace.</h2></div><p>Each role has its own tools and permissions. Students see their own published results; faculty work with assigned classes.</p></div>
    <div class="home-role-grid">
        <article class="home-role"><span class="role-monogram" aria-hidden="true">A</span><h3>Administrator</h3><p>Keep the academic process moving.</p><ul><li>Set up terms, subjects, and enrollment</li><li>Manage accounts and faculty assignments</li><li>Review grades, reports, and system records</li></ul></article>
        <article class="home-role"><span class="role-monogram" aria-hidden="true">F</span><h3>Faculty</h3><p>Focus on your classes and students.</p><ul><li>Access assigned classes</li><li>Enter grades and track submissions</li><li>Generate authorized reports and follow-ups</li></ul></article>
        <article class="home-role"><span class="role-monogram" aria-hidden="true">S</span><h3>Student</h3><p>Stay informed about your progress.</p><ul><li>View your enrolled subjects</li><li>Read your published grades and history</li><li>Print your reports and review reminders</li></ul></article>
    </div>
</section>

<section class="home-workflow home-section" id="workflow" aria-labelledby="workflow-title">
    <div class="home-section-heading"><div><p class="eyebrow">HOW IT WORKS</p><h2 id="workflow-title">A clear path, from<br>enrollment to results.</h2></div><p>Published results follow a review process. Student access starts only after an administrator verifies the account and its student-record link.</p></div>
    <ol class="workflow-list"><li><span aria-hidden="true">01</span><h3>Set up &amp; enroll</h3><p>The administrator prepares classes, assigns faculty, and enrolls students.</p></li><li><span aria-hidden="true">02</span><h3>Enter &amp; submit</h3><p>Assigned faculty record marks and submit grades for review.</p></li><li><span aria-hidden="true">03</span><h3>Review &amp; publish</h3><p>The administrator checks submissions and publishes approved results.</p></li><li><span aria-hidden="true">04</span><h3>View &amp; follow up</h3><p>Students view their results while staff manage reports and academic support.</p></li></ol>
</section>

<section class="home-start" aria-labelledby="start-title"><div><p class="eyebrow">YOUR NEXT STEP</p><h2 id="start-title"><?= $user ? 'Pick up where you left off.' : 'Ready for a clearer academic view?' ?></h2><p><?php if ($user): ?>Your role determines the records and tools available in your dashboard.<?php elseif ($registrationOpen): ?>Already have an account? Sign in. New account requests need administrator approval.<?php else: ?>Sign in with your school account. For new access, contact your school administrator.<?php endif; ?></p></div><div class="home-start-actions"><a class="btn btn-gold" href="<?= h($homeDashboard) ?>"><?= $user ? 'My dashboard' : 'Sign in' ?> <span aria-hidden="true">&#8599;</span></a><?php if (!$user && $registrationOpen): ?><a class="btn btn-outline-light" href="register.php">Request an account</a><?php endif; ?></div></section>
