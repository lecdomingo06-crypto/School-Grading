<?php
declare(strict_types=1);

/** Public sample passwords belong only to these separate local demo accounts. */
function demo_accounts(): array
{
    return [
        ['role'=>'Administrator', 'username'=>'demo_admin', 'password'=>'DemoAdmin2026!'],
        ['role'=>'Faculty', 'username'=>'demo_faculty', 'password'=>'DemoFaculty2026!'],
        ['role'=>'Student', 'username'=>'demo_student', 'password'=>'DemoStudent2026!'],
    ];
}

function demo_enabled(): bool
{
    return in_array(getenv('APP_ENV'), ['local', 'development', 'test'], true)
        && setting('demo_access_enabled', '0') === '1';
}

function available_demo_accounts(): array
{
    if (!demo_enabled()) return [];
    $available = [];
    foreach (demo_accounts() as $demo) {
        $account = row('SELECT password_hash,role,is_active,registration_status,must_change_password FROM users WHERE username=?', [$demo['username']]);
        if ($account && $account['role'] === $demo['role'] && $account['is_active']
            && $account['registration_status'] === 'Approved' && !$account['must_change_password']
            && password_verify($demo['password'], $account['password_hash'])) $available[] = $demo;
    }
    return $available;
}

function demo_login_allowed(string $username): bool
{
    foreach (demo_accounts() as $demo) {
        if (strcasecmp($username, $demo['username']) === 0) return demo_enabled();
    }
    return true;
}

function demo_insert(string $table, array $values): int
{
    $allowed = ['users','courses','school_years','terms','sections','subjects','grading_policies','offerings'];
    if (!in_array($table, $allowed, true)) throw new DomainException('Invalid demo table.');
    $columns = array_keys($values);
    query('INSERT INTO `'.$table.'` (`'.implode('`,`', $columns).'`) VALUES ('.implode(',', array_fill(0, count($values), '?')).')', array_values($values));
    return (int) db()->lastInsertId();
}

function demo_actor(int $id): void
{
    $_SESSION = ['user'=>['id'=>$id, 'auth_version'=>1], 'epoch'=>setting('session_epoch', '1'), 'last_activity'=>time()];
}

function seed_demo_dataset(): array
{
    if (PHP_SAPI !== 'cli' || !in_array(getenv('APP_ENV'), ['local','development','test'], true)) {
        throw new DomainException('Sample data can only be loaded from the CLI in local/development/test mode.');
    }
    if (!student_schema_ready()) throw new DomainException('Apply migration 003 before loading sample data.');
    $saved = setting('demo_dataset_v1');
    if ($saved !== '') return array_merge(json_decode($saved, true, 512, JSON_THROW_ON_ERROR), ['created'=>false]);
    require_once __DIR__.'/ui.php';
    require_once __DIR__.'/records_service.php';
    $previousSession = $_SESSION ?? [];
    try {
        return transaction(function (): array {
            foreach (demo_accounts() as $demo) {
                if (scalar('SELECT COUNT(*) FROM users WHERE username=?', [$demo['username']])) {
                    throw new DomainException('A reserved demo username already exists. Existing accounts were not changed.');
                }
            }
            if (scalar("SELECT COUNT(*) FROM students WHERE student_number LIKE 'DEMO-%'")
                || scalar("SELECT COUNT(*) FROM courses WHERE code='DEMO-IT'")) {
                throw new DomainException('Demo-prefixed records already exist without a completion marker. Existing records were not changed.');
            }
            $accounts = demo_accounts();
            $admin = demo_insert('users', ['full_name'=>'Demo Administrator', 'username'=>$accounts[0]['username'], 'password_hash'=>password_hash($accounts[0]['password'], PASSWORD_BCRYPT), 'role'=>'Administrator']);
            $faculty = demo_insert('users', ['full_name'=>'Demo Faculty', 'username'=>$accounts[1]['username'], 'password_hash'=>password_hash($accounts[1]['password'], PASSWORD_BCRYPT), 'role'=>'Faculty']);
            query("INSERT INTO settings(setting_key,setting_value) VALUES('demo_access_enabled','1') ON DUPLICATE KEY UPDATE setting_value='1'");
            demo_actor($admin);
            $year = (int) date('Y');
            $course = demo_insert('courses', ['code'=>'DEMO-IT', 'name'=>'Demo Information Technology']);
            $schoolYear = demo_insert('school_years', ['name'=>'Demo school year '.$year, 'starts_on'=>"$year-01-01", 'ends_on'=>"$year-12-31"]);
            $term = demo_insert('terms', ['school_year_id'=>$schoolYear, 'name'=>'Demo term '.$year, 'starts_on'=>"$year-01-01", 'ends_on'=>"$year-12-31", 'submission_deadline'=>"$year-12-31", 'inc_deadline'=>($year+1).'-12-31']);
            $section = demo_insert('sections', ['course_id'=>$course, 'name'=>'DEMO 1-A', 'year_level'=>1]);
            $subject = demo_insert('subjects', ['subject_code'=>'DEMO101', 'title'=>'Demo Computing Fundamentals', 'units'=>3]);
            $policy = demo_insert('grading_policies', ['name'=>'Demo policy v1 - training only', 'midterm_weight'=>50, 'rounding'=>'floor', 'repeat_rule'=>'all', 'include_nonacademic'=>0, 'is_approved'=>1, 'notes'=>'Demonstration review only: 50/50 weighting and floor conversion. This does not record official institutional approval. Fictional data only.']);
            $offering = demo_insert('offerings', ['subject_id'=>$subject, 'term_id'=>$term, 'section_id'=>$section, 'faculty_id'=>$faculty, 'policy_id'=>$policy]);
            $firstStudent = null;
            for ($number=1; $number<=100; $number++) {
                demo_actor($admin);
                $student = save_record('students', ['student_number'=>sprintf('DEMO-%04d', $number), 'full_name'=>sprintf('Demo Student %03d', $number), 'course_id'=>(string)$course, 'section_id'=>(string)$section, 'year_level'=>'1', 'academic_status'=>'Active', 'honorable_dismissal_on'=>'']);
                $firstStudent ??= $student;
                save_record('enrollments', ['student_id'=>(string)$student, 'offering_id'=>(string)$offering, 'status'=>'Enrolled']);
                demo_actor($faculty);
                $mark = $number === 1 ? 85 : ($number % 20 === 0 ? 80 : 60 + (($number * 7) % 41));
                $grade = save_grade(['offering_id'=>(string)$offering, 'student_id'=>(string)$student, 'midterm'=>(string)$mark, 'final'=>(string)$mark, 'result_type'=>$number%20===0?'INC':'Numeric', 'inc_reason'=>'Demo missing requirement - fictional example', 'reason'=>'Fictional demonstration grade']);
                if ($number <= 80 || $number > 90) transition_grade($grade, 'submit', 'Demonstration submission', 1);
                if ($number <= 80 || $number > 95) {
                    demo_actor($admin);
                    transition_grade($grade, 'approve', 'Demonstration review only - not institutional certification', 2);
                }
                if ($number <= 80) transition_grade($grade, 'publish', 'Publish fictional demo result', 3);
            }
            $studentAccount = demo_insert('users', ['full_name'=>'Demo Student 001', 'username'=>$accounts[2]['username'], 'password_hash'=>password_hash($accounts[2]['password'], PASSWORD_BCRYPT), 'role'=>'Student', 'student_id'=>$firstStudent]);
            demo_actor($admin);
            notify_published_grade((int)scalar('SELECT id FROM grades WHERE student_id=? AND offering_id=?', [$firstStudent,$offering]));
            foreach (['active_term_id'=>(string)$term, 'gwa_policy_id'=>(string)$policy, 'demo_access_enabled'=>'1'] as $key=>$value) {
                $before = setting($key);
                query('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$key,$value]);
                audit('settings_update', 'settings', null, 'Selected local demonstration setting: '.$key, [$key=>$before], [$key=>$value]);
            }
            $result = ['students'=>100, 'enrollments'=>100, 'grades'=>100, 'admin_id'=>$admin, 'faculty_id'=>$faculty, 'student_account_id'=>$studentAccount, 'student_id'=>$firstStudent, 'offering_id'=>$offering, 'term_id'=>$term, 'policy_id'=>$policy];
            query('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)', ['demo_dataset_v1', json_encode($result, JSON_THROW_ON_ERROR)]);
            audit('demo_seed', 'system', null, 'Added 100 fictional students, enrollments, grades, and three separate demo accounts', null, $result);
            return array_merge($result, ['created'=>true]);
        });
    } finally {
        $_SESSION = $previousSession;
    }
}
