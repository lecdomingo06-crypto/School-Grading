<?php
declare(strict_types=1);

require_once __DIR__.'/app.php';

/** The session's current database identity is the sole source of student ownership. */
function student_identity(): ?array
{
    $account = require_role('Student');
    if (empty($account['student_id'])) return null;
    return row('SELECT s.id,s.student_number,s.full_name,s.course,s.year_level,s.academic_status,c.name AS program_name,se.name AS section_name FROM students s LEFT JOIN courses c ON c.id=s.course_id LEFT JOIN sections se ON se.id=s.section_id WHERE s.id=?', [(int)$account['student_id']]);
}

function student_require_identity(): array
{
    return student_identity() ?? throw new DomainException('Your student record is awaiting administrator verification. No academic information is available until your account is linked.');
}

/** Reject attempts to broaden ownership or view an unpublished workflow. */
function student_guard_scope(array $input): void
{
    $student = student_identity();
    if (isset($input['student_id']) && $input['student_id'] !== '') {
        if (!is_string($input['student_id']) && !is_int($input['student_id'])) deny();
        if (!$student || filter_var($input['student_id'], FILTER_VALIDATE_INT) === false || (int)$input['student_id'] !== (int)$student['id']) deny();
    }
    if (isset($input['workflow']) && $input['workflow'] !== '' && $input['workflow'] !== 'Published') deny();
    if (isset($input['migration_review']) && !in_array($input['migration_review'], ['', '0', 0], true)) deny();
}

function student_filters(array $input): array
{
    student_guard_scope($input);
    $filters = [];
    foreach (['term_id', 'school_year_id', 'subject_id'] as $key) {
        if (isset($input[$key]) && $input[$key] !== '') $filters[$key] = positive_id($input[$key]);
    }
    $keyword = trim(request_string($input, 'q'));
    if (mb_strlen($keyword) > 120) throw new DomainException('Use a subject search of up to 120 characters.');
    if ($keyword !== '') $filters['q'] = $keyword;
    $status = request_string($input, 'status');
    if ($status !== '') {
        if (!in_array($status, ['Passed', 'Failed', 'INC'], true)) throw new DomainException('Choose a valid published result status.');
        $filters['status'] = $status;
    }
    foreach (['from', 'to'] as $key) {
        if (request_string($input, $key) !== '') {
            $date = request_string($input, $key);
            if (!valid_date($date)) throw new DomainException('Choose valid publication dates.');
            $filters[$key] = $date;
        }
    }
    if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) throw new DomainException('The starting date must be before the ending date.');
    return $filters;
}

function student_grade_select(): string
{
    return "SELECT g.id,g.student_id,g.subject_id,g.offering_id,g.policy_id,g.units_snapshot,g.academic_snapshot,g.workflow,g.migration_review,g.midterm,g.final,g.average,g.gpa,g.remarks,g.inc_deadline,g.completion_status,g.published_at,su.subject_code,su.title,t.id AS term_id,t.name AS term_name,t.starts_on AS term_start,y.name AS school_year,se.name AS section_name,COALESCE(u.full_name,'Not yet assigned') AS faculty_name,p.name AS grading_policy
        FROM grades g
        JOIN enrollments e ON e.student_id=g.student_id AND e.offering_id=g.offering_id
        JOIN offerings o ON o.id=g.offering_id AND o.subject_id=g.subject_id
        JOIN subjects su ON su.id=g.subject_id
        LEFT JOIN terms t ON t.id=o.term_id
        LEFT JOIN school_years y ON y.id=t.school_year_id
        LEFT JOIN sections se ON se.id=o.section_id
        LEFT JOIN users u ON u.id=o.faculty_id
        LEFT JOIN grading_policies p ON p.id=g.policy_id";
}

/** Only published and reviewed rows enter the application, including aggregate inputs. */
function student_grade_rows(array $input = []): array
{
    $student = student_require_identity();
    $filters = student_filters($input);
    $where = ["g.student_id=?", "g.workflow='Published'", 'g.migration_review=0', 'o.needs_review=0'];
    $params = [(int)$student['id']];
    foreach (['term_id'=>'o.term_id', 'school_year_id'=>'t.school_year_id', 'subject_id'=>'g.subject_id', 'status'=>'g.remarks'] as $key=>$column) {
        if (isset($filters[$key])) { $where[] = "$column=?"; $params[] = $filters[$key]; }
    }
    if (isset($filters['q'])) {
        $where[] = '(su.subject_code LIKE ? OR su.title LIKE ?)';
        array_push($params, '%'.$filters['q'].'%', '%'.$filters['q'].'%');
    }
    if (isset($filters['from'])) { $where[] = 'g.published_at>=?'; $params[] = $filters['from'].' 00:00:00'; }
    if (isset($filters['to'])) { $where[] = 'g.published_at<=?'; $params[] = $filters['to'].' 23:59:59'; }
    $records = rows(student_grade_select().' WHERE '.implode(' AND ', $where).' ORDER BY t.starts_on DESC,o.id DESC,su.subject_code LIMIT 10001', $params);
    if (count($records) > 10000) throw new DomainException('Choose a narrower school year or term to view at most 10,000 results.');
    return $records;
}

function student_subject_rows(array $input = []): array
{
    $student = student_require_identity();
    $filters = student_filters($input);
    $where = ['e.student_id=?']; $params = [(int)$student['id']];
    foreach (['term_id'=>'o.term_id', 'school_year_id'=>'t.school_year_id', 'subject_id'=>'o.subject_id'] as $key=>$column) {
        if (isset($filters[$key])) { $where[] = "$column=?"; $params[] = $filters[$key]; }
    }
    if (isset($filters['q'])) {
        $where[] = '(su.subject_code LIKE ? OR su.title LIKE ?)';
        array_push($params, '%'.$filters['q'].'%', '%'.$filters['q'].'%');
    }
    // The join never loads a grade that has not been published and reviewed.
    return rows("SELECT e.id,e.status AS enrollment_status,o.id AS offering_id,su.subject_code,su.title,su.units,t.id AS term_id,t.name AS term_name,y.name AS school_year,se.name AS section_name,COALESCE(u.full_name,'Not yet assigned') AS faculty_name,CASE WHEN g.id IS NULL THEN 'Results are not yet published' ELSE 'Published results available' END AS result_status
        FROM enrollments e JOIN offerings o ON o.id=e.offering_id JOIN subjects su ON su.id=o.subject_id
        LEFT JOIN terms t ON t.id=o.term_id LEFT JOIN school_years y ON y.id=t.school_year_id
        LEFT JOIN sections se ON se.id=o.section_id LEFT JOIN users u ON u.id=o.faculty_id
        LEFT JOIN grades g ON g.student_id=e.student_id AND g.offering_id=e.offering_id AND g.subject_id=o.subject_id AND g.workflow='Published' AND g.migration_review=0 AND o.needs_review=0
        WHERE ".implode(' AND ', $where).' ORDER BY t.starts_on DESC,su.subject_code', $params);
}

function student_filter_options(): array
{
    $student = student_require_identity();
    $data = rows('SELECT DISTINCT t.id AS term_id,t.name AS term_name,t.starts_on,y.id AS school_year_id,y.name AS school_year,su.id AS subject_id,su.subject_code,su.title FROM enrollments e JOIN offerings o ON o.id=e.offering_id JOIN subjects su ON su.id=o.subject_id LEFT JOIN terms t ON t.id=o.term_id LEFT JOIN school_years y ON y.id=t.school_year_id WHERE e.student_id=? ORDER BY t.starts_on DESC,su.subject_code', [(int)$student['id']]);
    $options = ['term_id'=>[], 'school_year_id'=>[], 'subject_id'=>[]];
    foreach ($data as $record) {
        if ($record['term_id']) $options['term_id'][$record['term_id']] = $record['school_year'].' · '.$record['term_name'];
        if ($record['school_year_id']) $options['school_year_id'][$record['school_year_id']] = $record['school_year'];
        $options['subject_id'][$record['subject_id']] = $record['subject_code'].' · '.$record['title'];
    }
    return $options;
}

function student_gwa_policy(): array
{
    return policy(positive_id(setting('gwa_policy_id', '1')));
}

function student_policy_description(array $policy): string
{
    $repeats = ['all'=>'all numeric attempts', 'latest'=>'latest attempt per subject', 'best'=>'best numerical attempt per subject'];
    return ($repeats[$policy['repeat_rule']] ?? 'configured attempts').'; nonacademic subjects '.((int)$policy['include_nonacademic'] ? 'included' : 'excluded').'; INC and unpublished or unreviewed results excluded. Credit units use the saved grade snapshots.';
}

function student_current_term(): ?array
{
    $id = (int)setting('active_term_id', '0');
    if ($id < 1) return null;
    return row('SELECT t.id,t.name,t.starts_on,t.ends_on,y.name AS school_year FROM terms t JOIN school_years y ON y.id=t.school_year_id WHERE t.id=?', [$id]);
}

function student_term_summaries(array $records, array $policy): array
{
    $groups = [];
    foreach ($records as $record) {
        $key = (string)($record['term_id'] ?? 'legacy');
        $groups[$key] ??= ['label'=>($record['school_year'] ?? 'Earlier record').' · '.($record['term_name'] ?? 'Term awaiting review'), 'rows'=>[]];
        $groups[$key]['rows'][] = $record;
    }
    $summary = [];
    foreach ($groups as $group) {
        $summary[] = ['term'=>$group['label'], 'results'=>count($group['rows']), 'units'=>array_sum(array_column($group['rows'], 'units_snapshot')), 'passed'=>count(array_filter($group['rows'], fn($r)=>$r['remarks']==='Passed')), 'failed'=>count(array_filter($group['rows'], fn($r)=>$r['remarks']==='Failed')), 'inc'=>count(array_filter($group['rows'], fn($r)=>$r['remarks']==='INC')), 'gwa'=>weighted_average($group['rows'], $policy) ?? 'Not available'];
    }
    return $summary;
}

/** A message linked to a grade disappears if that result is reopened or unpublished. */
function student_notification_where(): array
{
    $student = student_identity(); $account = require_role('Student');
    $studentId = $student ? (int)$student['id'] : 0;
    return ["n.user_id=? AND n.audience='student' AND (n.student_id IS NULL OR n.student_id=?) AND (n.grade_id IS NULL OR (n.student_id=? AND EXISTS(SELECT 1 FROM grades g JOIN enrollments e ON e.student_id=g.student_id AND e.offering_id=g.offering_id JOIN offerings o ON o.id=g.offering_id AND o.subject_id=g.subject_id WHERE g.id=n.grade_id AND g.student_id=? AND g.workflow='Published' AND g.migration_review=0 AND o.needs_review=0)))", [(int)$account['id'], $studentId, $studentId, $studentId]];
}

function student_notifications(): array
{
    [$where, $params] = student_notification_where();
    $stored = rows('SELECT n.id,n.message,n.is_read,n.created_at FROM notifications n WHERE '.$where.' ORDER BY n.id DESC LIMIT 100', $params);
    $reminders = [];
    if (student_identity() && setting('notifications_enabled', '1') === '1') {
        foreach (student_grade_rows(['status'=>'INC']) as $grade) {
            if (!$grade['inc_deadline'] || $grade['completion_status'] !== 'Pending') continue;
            $reminders[] = ['message'=>$grade['subject_code'].' has an INC completion deadline of '.$grade['inc_deadline'].'. Contact your assigned faculty about the approved completion process.', 'deadline'=>$grade['inc_deadline']];
        }
        usort($reminders, fn($a,$b)=>strcmp($a['deadline'], $b['deadline']));
    }
    return ['stored'=>$stored, 'reminders'=>$reminders];
}

function student_mark_notification(int $id): void
{
    [$where, $params] = student_notification_where();
    $notification = row('SELECT n.id FROM notifications n WHERE n.id=? AND '.$where, array_merge([$id], $params));
    if (!$notification) deny();
    query('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$id, (int)current_user()['id']]);
}

function student_dashboard_data(): array
{
    $student = student_require_identity();
    $term = student_current_term();
    $filters = $term ? ['term_id'=>(int)$term['id']] : [];
    // No active term means no current-term statistics; historical results remain in My Grades.
    $subjects = $term ? student_subject_rows($filters) : [];
    $grades = $term ? student_grade_rows($filters) : [];
    $policy = student_gwa_policy();
    return ['student'=>$student, 'term'=>$term, 'subjects'=>$subjects, 'grades'=>$grades, 'gwa'=>weighted_average($grades, $policy), 'policy'=>$policy, 'notifications'=>student_notifications(), 'refreshed_at'=>date('Y-m-d H:i:s').' Asia/Manila'];
}

function student_report_types(): array
{
    return ['grade_card'=>'My published grade card', 'summary'=>'My academic summary', 'academic_history'=>'My academic history'];
}

function student_report(string $type, array $input): array
{
    $student = student_require_identity();
    if (!isset(student_report_types()[$type])) deny();
    $filters = student_filters($input);
    $data = student_grade_rows($filters);
    $policy = student_gwa_policy();
    $summary = ['Published results'=>count($data), 'Published-result GWA'=>weighted_average($data, $policy) ?? 'Not available', 'GWA interpretation'=>$policy['name'].' — '.student_policy_description($policy)];
    $columns = ['school_year'=>'School year', 'term_name'=>'Term', 'subject_code'=>'Subject code', 'title'=>'Subject', 'units_snapshot'=>'Credit units', 'average'=>'Percentage', 'gpa'=>'ISU grade', 'remarks'=>'Result', 'inc_deadline'=>'INC deadline', 'grading_policy'=>'Grading policy'];
    foreach ($data as &$record) {
        if ($record['gpa'] !== null) $record['gpa'] = number_format((float)$record['gpa'], 2);
        if ($record['remarks'] !== 'INC') $record['inc_deadline'] = '';
    }
    unset($record);
    if ($type === 'summary') {
        $data = student_term_summaries($data, $policy);
        $columns = ['term'=>'Academic term', 'results'=>'Published results', 'units'=>'Published credit units', 'passed'=>'Passed', 'failed'=>'Failed', 'inc'=>'INC', 'gwa'=>'Published-result GWA'];
    }
    return ['title'=>student_report_types()[$type], 'student'=>$student, 'data'=>$data, 'columns'=>$columns, 'summary'=>$summary, 'filters'=>$filters, 'generated_at'=>date('Y-m-d H:i:s').' Asia/Manila', 'prepared_by'=>current_user()['full_name']];
}

function student_save_report(string $type, array $input): string
{
    $report = student_report($type, $input);
    $account = require_role('Student'); $token = bin2hex(random_bytes(24));
    $_SESSION['student_reports'] ??= [];
    $_SESSION['student_reports'][$token] = ['type'=>$type, 'filters'=>$report['filters'], 'user_id'=>(int)$account['id'], 'student_id'=>(int)$report['student']['id'], 'auth_version'=>(int)$account['auth_version'], 'epoch'=>setting('session_epoch', '1'), 'created'=>time()];
    if (count($_SESSION['student_reports']) > 10) array_shift($_SESSION['student_reports']);
    audit('report_generate', 'reports', (int)$report['student']['id'], 'Generated '.$report['title'], null, ['filters'=>$report['filters'], 'rows'=>count($report['data']), 'student_id'=>(int)$report['student']['id']]);
    return $token;
}

function student_load_report(string $token): array
{
    $account = require_role('Student'); $student = student_require_identity();
    $saved = $_SESSION['student_reports'][$token] ?? null;
    if (!$saved || (int)$saved['user_id'] !== (int)$account['id'] || (int)$saved['student_id'] !== (int)$student['id'] || (int)$saved['auth_version'] !== (int)$account['auth_version'] || !hash_equals((string)$saved['epoch'], setting('session_epoch', '1'))) deny();
    if (time() - (int)$saved['created'] > 1800) throw new DomainException('This report preview has expired. Generate a new report.');
    // Re-query on every preview/export so reopening a result immediately removes it.
    return student_report($saved['type'], $saved['filters']);
}

function student_report_filter_text(array $filters): string
{
    $options = student_filter_options();
    $labels = ['term_id'=>'Term', 'school_year_id'=>'School year', 'subject_id'=>'Subject', 'q'=>'Subject search', 'status'=>'Result', 'from'=>'Published from', 'to'=>'Published to'];
    $parts = [];
    foreach ($filters as $key=>$value) if (isset($labels[$key])) $parts[] = $labels[$key].': '.($options[$key][$value] ?? $value);
    return $parts ? implode(' · ', $parts) : 'All my published, reviewed results';
}
