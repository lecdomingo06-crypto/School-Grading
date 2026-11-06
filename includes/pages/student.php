<?php
declare(strict_types=1);

require_once __DIR__.'/../app.php';
require_once __DIR__.'/../ui.php';
require_once __DIR__.'/../student_service.php';
require_once __DIR__.'/../csv.php';

require_role('Student');
student_guard_scope($_GET);
student_guard_scope($_POST);
$view = request_string($_GET, 'view') ?: 'overview';
$views = ['overview'=>'My overview', 'subjects'=>'My subjects', 'grades'=>'My grades', 'history'=>'Academic history', 'reports'=>'My reports', 'notifications'=>'My notifications'];
if (!isset($views[$view])) deny();
$student = student_identity();
$token = request_string($_GET, 'report');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = request_string($_POST, 'action');
    try {
        if ($action === 'generate' && $view === 'reports') {
            $token = student_save_report(request_string($_POST, 'report_type'), $_POST);
            redirect('student.php?view=reports&report='.rawurlencode($token));
        } elseif ($action === 'export' && $view === 'reports') {
            $report = student_load_report(request_string($_POST, 'report'));
            audit('report_export', 'reports', (int)$report['student']['id'], 'Exported '.$report['title'], null, ['filters'=>$report['filters'], 'rows'=>count($report['data']), 'student_id'=>(int)$report['student']['id']]);
            output_csv('my-published-academic-report-'.date('Ymd-His').'.csv', $report['data'], $report['columns']);
        } elseif ($action === 'read' && $view === 'notifications') {
            student_mark_notification(positive_id($_POST['id'] ?? null));
            flash('success', 'Notification marked as read.');
            redirect('student.php?view=notifications');
        } else {
            deny();
        }
    } catch (DomainException $e) {
        flash('danger', $e->getMessage());
    }
}

if (!$student) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['linked'=>false, 'message'=>'Your student record is awaiting administrator verification.'], JSON_THROW_ON_ERROR);
        exit;
    }
    page_start('Your student account', 'Your academic information becomes available after your account is verified.');
    ?><section class="panel"><p class="eyebrow">ACCOUNT APPROVED · RECORD LINK PENDING</p><h2>We are preparing your student access</h2><p>An administrator needs to link this account to your official student record. Contact your school office and provide your account username. Your account cannot claim or choose a student number.</p><a class="btn btn-primary" href="profile.php">Review my profile</a></section><?php
    page_end();
    return;
}

$filters = student_filters($_GET);
$policy = student_gwa_policy();
$report = null; $data = []; $records = []; $subjects = []; $notifications = [];
if ($view === 'overview') $data = student_dashboard_data();
if ($view === 'subjects') $subjects = student_subject_rows($filters);
if (in_array($view, ['grades', 'history'], true)) $records = student_grade_rows($filters);
if ($view === 'notifications') $notifications = student_notifications();
if ($view === 'reports' && $token !== '') $report = student_load_report($token);

if (isset($_GET['api'])) {
    $response = ['linked'=>true, 'view'=>$view];
    if ($view === 'overview') $response += $data;
    elseif ($view === 'subjects') $response['subjects'] = $subjects;
    elseif (in_array($view, ['grades', 'history'], true)) {
        $response['grades'] = $records;
        $response['terms'] = student_term_summaries($records, $policy);
        $response['gwa'] = weighted_average($records, $policy);
    } elseif ($view === 'notifications') $response['notifications'] = $notifications;
    elseif ($view === 'reports') $response['report'] = $report;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_THROW_ON_ERROR);
    exit;
}

function student_portal_filter_form(array $filters, string $view, bool $reportBuilder = false): void
{
    $options = student_filter_options();
    if (!$reportBuilder) echo '<input type="hidden" name="view" value="'.h($view).'">';
    input_field('q', 'Subject code or title', $filters['q'] ?? '', 'search', false);
    select_field('school_year_id', 'School year', $options['school_year_id'], $filters['school_year_id'] ?? '', false);
    select_field('term_id', 'Academic term', $options['term_id'], $filters['term_id'] ?? '', false);
    select_field('subject_id', 'Subject', $options['subject_id'], $filters['subject_id'] ?? '', false);
    if ($view !== 'subjects') {
        select_field('status', 'Published result', ['Passed'=>'Passed', 'Failed'=>'Failed', 'INC'=>'INC'], $filters['status'] ?? '', false);
        input_field('from', 'Published from', $filters['from'] ?? '', 'date', false);
        input_field('to', 'Published through', $filters['to'] ?? '', 'date', false);
    }
}

function student_portal_grade_table(array $records): void
{
    $display = [];
    foreach ($records as $record) {
        $record['gpa'] = $record['gpa'] === null ? '—' : number_format((float)$record['gpa'], 2);
        $record['term_label'] = ($record['school_year'] ?? 'Earlier record').' · '.($record['term_name'] ?? 'Term awaiting review');
        $record['inc_deadline'] = $record['remarks'] === 'INC' ? ($record['inc_deadline'] ?? 'Contact faculty') : '—';
        $display[] = $record;
    }
    data_table($display, ['term_label'=>'Academic term', 'subject_code'=>'Subject code', 'title'=>'Subject', 'units_snapshot'=>'Units', 'average'=>'Percentage', 'gpa'=>'ISU grade', 'remarks'=>'Result', 'inc_deadline'=>'INC deadline']);
}

$subtitles = [
    'overview'=>'Your enrollment, published results, and academic reminders in one place.',
    'subjects'=>'Your enrolled subjects, faculty, and academic terms.',
    'grades'=>'Only published, reviewed results are available in your student account.',
    'history'=>'Your published academic results across school years and terms.',
    'reports'=>'Generate and print a report of your own published academic results.',
    'notifications'=>'Account updates, published-result notices, and INC reminders.'
];
page_start($views[$view], $subtitles[$view]);
?>
<?php if ($view === 'overview'): ?>
<section class="panel student-identity">
    <p class="eyebrow">STUDENT PORTAL</p>
    <h2><?=h($student['full_name'])?></h2>
    <p><?=h($student['student_number'])?> · <?=h($student['program_name'] ?: $student['course'])?> · Year <?=(int)$student['year_level']?></p>
    <p class="text-secondary">Section: <?=h($student['section_name'] ?: 'Not yet assigned')?> · Academic status: <?=h($student['academic_status'])?></p>
    <p class="small text-secondary">Official identity and enrollment are maintained by your school office. <a href="profile.php">Manage account details</a></p>
</section>
<?php if ($data['term']): ?>
<p class="metric-help">Current academic term: <strong><?=h($data['term']['school_year'].' · '.$data['term']['name'])?></strong> · <?=h($data['term']['starts_on'])?> to <?=h($data['term']['ends_on'])?></p>
<?php else: ?><div class="alert alert-info" role="status">Your school has not selected a current academic term. Previous published results remain available in My Grades and Academic History.</div><?php endif; ?>
<div class="stats-grid">
    <article class="stat-card"><span>Current-term subjects</span><strong><?=count($data['subjects'])?></strong></article>
    <article class="stat-card"><span>Enrolled credit units</span><strong><?=h(array_sum(array_map(fn($s)=>$s['enrollment_status']==='Enrolled' ? (float)$s['units'] : 0, $data['subjects'])))?></strong></article>
    <article class="stat-card"><span>Published results</span><strong><?=count($data['grades'])?></strong></article>
    <article class="stat-card"><span>Published-result GWA</span><strong><?=h($data['gwa'] === null ? '—' : number_format($data['gwa'], 3))?></strong></article>
</div>
<p class="metric-help">A lower numerical grade indicates higher performance: 1.00 is highest, 3.00 is the lowest passing grade, and 5.00 is failed. INC means requirements remain incomplete. GWA: <?=h(student_policy_description($policy))?></p>
<div class="two-column">
    <section class="panel"><h2>Current subjects</h2><?php data_table(array_slice($data['subjects'], 0, 8), ['subject_code'=>'Subject', 'title'=>'Title', 'enrollment_status'=>'Enrollment', 'result_status'=>'Results']); ?><a class="btn btn-outline-primary" href="student.php?view=subjects">View all my subjects</a></section>
    <section class="panel"><h2>Updates and reminders</h2>
        <?php $updates = array_slice(array_merge($data['notifications']['reminders'], $data['notifications']['stored']), 0, 5); foreach ($updates as $update): ?><p class="notification"><?=h($update['message'])?></p><?php endforeach; ?>
        <?php if (!$updates): ?><p class="empty-state">You have no new academic reminders.</p><?php endif; ?>
        <a class="btn btn-outline-primary" href="student.php?view=notifications">View my notifications</a>
    </section>
</div>
<section class="panel"><h2>Published results this term</h2><?php student_portal_grade_table($data['grades']); ?><a class="btn btn-primary" href="student.php?view=grades">View my grades</a> <a class="btn btn-outline-primary" href="student.php?view=reports">Generate my report</a></section>
<p class="small text-secondary">Updated <?=h($data['refreshed_at'])?>. Refresh this page to load the latest published results.</p>

<?php elseif (in_array($view, ['subjects', 'grades', 'history'], true)): ?>
<details class="panel no-print" open><summary>Find my <?=$view==='subjects' ? 'subjects' : 'published results'?></summary><form method="get" class="filter-grid mt-3"><?php student_portal_filter_form($filters, $view); ?><div class="field align-self-end"><button class="btn btn-primary" type="submit">Apply filters</button> <a href="student.php?view=<?=h($view)?>" class="btn btn-outline-secondary">Clear</a></div></form></details>
<?php if ($view === 'subjects'): ?>
<section class="panel"><h2>My enrolled subjects</h2><p class="small text-secondary">Results become visible after administrator publication. An unpublished result does not indicate a pass, a failure, or a missing faculty submission.</p><?php
    $page = max(1, (int)request_string($_GET, 'page'));
    data_table(array_slice($subjects, ($page - 1) * 15, 15), ['school_year'=>'School year', 'term_name'=>'Term', 'subject_code'=>'Code', 'title'=>'Subject', 'units'=>'Units', 'section_name'=>'Section', 'faculty_name'=>'Faculty', 'enrollment_status'=>'Enrollment', 'result_status'=>'Results']);
    pagination(count($subjects), $page);
?></section>
<?php else: ?>
<div class="stats-grid"><article class="stat-card"><span>Matching published results</span><strong><?=count($records)?></strong></article><article class="stat-card"><span>Published-result GWA</span><strong><?=h(($gwa=weighted_average($records, $policy)) === null ? '—' : number_format($gwa, 3))?></strong></article></div>
<p class="metric-help">Calculated for the displayed filters using <?=h($policy['name'])?>: <?=h(student_policy_description($policy))?></p>
<?php if ($view === 'history'): ?><section class="panel"><h2>Term summaries</h2><?php data_table(student_term_summaries($records, $policy), ['term'=>'Academic term', 'results'=>'Published results', 'units'=>'Published units', 'passed'=>'Passed', 'failed'=>'Failed', 'inc'=>'INC', 'gwa'=>'GWA']); ?></section><?php endif; ?>
<section class="panel"><h2><?=$view==='history' ? 'Published academic history' : 'My published grades'?></h2><?php
    $page = max(1, (int)request_string($_GET, 'page'));
    student_portal_grade_table(array_slice($records, ($page - 1) * 15, 15));
    pagination(count($records), $page);
?><p class="small text-secondary">1.00 is the highest grade; 3.00 is passing; 5.00 is failed. INC has unmet requirements and is excluded from GWA. For corrections or INC completion, contact your assigned faculty or school office.</p><a class="btn btn-primary" href="student.php?view=reports">Generate an academic report</a></section>
<?php endif; ?>

<?php elseif ($view === 'reports'): ?>
<details class="panel no-print" <?=$report ? '' : 'open'?>><summary>Build my report</summary><form method="post" action="student.php?view=reports" class="filter-grid mt-3"><?php csrf_field(); select_field('report_type', 'Report type', student_report_types(), 'grade_card'); student_portal_filter_form($filters, 'reports', true); ?><div class="field align-self-end"><button class="btn btn-primary" name="action" value="generate">Generate report</button></div><p class="wide text-secondary">Reports contain your published, reviewed results only. Publication date filters apply to the date results were published. A report preview expires after 30 minutes.</p></form></details>
<?php if ($report): ?>
<section class="panel report-document">
    <div class="report-toolbar no-print"><button class="btn btn-primary" type="button" onclick="window.print()">Print preview / Save as PDF</button><form method="post" action="student.php?view=reports"><?php csrf_field(); ?><input type="hidden" name="report" value="<?=h($token)?>"><button class="btn btn-outline-primary" name="action" value="export">Export CSV</button></form></div>
    <header class="report-header"><img src="<?=h(school_logo())?>" alt="School logo" width="68" height="68"><div><h2><?=h(setting('school_name'))?></h2><p><?=h(setting('campus'))?></p><p><?=h(setting('report_header'))?></p></div></header>
    <h2><?=h($report['title'])?></h2><p class="report-label">PUBLISHED ACADEMIC RESULTS · System-generated student copy. Subject to school certification; this is not a certified transcript.</p>
    <p><strong><?=h($report['student']['full_name'])?></strong> · <?=h($report['student']['student_number'])?></p>
    <p><?=h($report['student']['program_name'] ?: $report['student']['course'])?> · Year <?=(int)$report['student']['year_level']?></p>
    <p>Generated <?=h($report['generated_at'])?> · Prepared by <?=h($report['prepared_by'])?></p>
    <p class="small">Filters: <?=h(student_report_filter_text($report['filters']))?></p>
    <?php foreach ($report['summary'] as $label=>$value): ?><p><strong><?=h($label)?>:</strong> <?=h($value)?></p><?php endforeach; ?>
    <?php data_table($report['data'], $report['columns']); ?>
    <div class="report-signatures"><p>Prepared by: ____________________</p><p>School verification: ____________________</p></div>
</section>
<?php endif; ?>

<?php elseif ($view === 'notifications'): ?>
<div class="two-column"><section class="panel"><h2>INC completion reminders</h2><?php foreach ($notifications['reminders'] as $reminder): ?><article class="notification"><p><?=h($reminder['message'])?></p><a href="student.php?view=grades&amp;status=INC">View my published INC results</a></article><?php endforeach; if (!$notifications['reminders']): ?><p class="empty-state">You have no published INC results awaiting completion.</p><?php endif; ?></section>
<section class="panel"><h2>My account and academic updates</h2><?php foreach ($notifications['stored'] as $notification): ?><article class="notification"><small><?=h($notification['created_at'])?> · <?=$notification['is_read'] ? 'Read' : 'Unread'?></small><p><?=h($notification['message'])?></p><?php if (!$notification['is_read']): ?><form method="post" action="student.php?view=notifications"><?php csrf_field(); ?><input type="hidden" name="id" value="<?=(int)$notification['id']?>"><button class="btn btn-sm btn-outline-secondary" name="action" value="read">Mark as read</button></form><?php endif; ?></article><?php endforeach; if (!$notifications['stored']): ?><p class="empty-state">No account or published-result updates yet.</p><?php endif; ?></section></div>
<?php endif;
page_end();
