<?php
declare(strict_types=1);

// Integration tests create only new crystal_test_* databases. They never target the local school database.
if (PHP_SAPI !== 'cli') exit;
function student_check(bool $condition, string $label): void {
    global $studentChecks;
    if (!$condition) throw new RuntimeException('FAILED: '.$label);
    $studentChecks++;
    echo "PASS $label\n";
}
function student_fails(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException|PDOException $error) { student_check(true, $label); return; }
    student_check(false, $label);
}
function student_actor(int $id): void {
    $account = row('SELECT id,auth_version,role,student_id FROM users WHERE id=?', [$id]);
    $_SESSION = ['user'=>$account, 'epoch'=>setting('session_epoch'), 'last_activity'=>time()];
}
function student_test_state(): array {
    $file = getenv('CRYSTAL_STUDENT_TEST_STATE');
    if (!$file || !is_file($file)) throw new RuntimeException('Student test state is missing. Run php tests/student.php.');
    $state = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!preg_match('/^crystal_test_student_[a-z0-9_]+$/D', $state['database']??'')) throw new RuntimeException('Unsafe student test database.');
    if (getenv('DB_NAME') !== $state['database']) throw new RuntimeException('Student test state and database must match.');
    return $state;
}

$studentChecks = 0;
if (($argv[1]??'') === 'state') {
    $state = student_test_state();
    putenv('CRYSTAL_CONFIG_FILE');
    require_once __DIR__.'/../includes/app.php';
    $state['users'] = rows('SELECT id,username,role,student_id,is_active,registration_status,must_change_password,auth_version FROM users ORDER BY id');
    $state['student_records'] = rows('SELECT id,student_number,full_name,course_id,year_level FROM students ORDER BY id');
    $state['reports_generated'] = (int)scalar("SELECT COUNT(*) FROM activity_logs WHERE action='report_generate'");
    $state['reports_exported'] = (int)scalar("SELECT COUNT(*) FROM activity_logs WHERE action='report_export'");
    $state['read_notification_ids'] = array_map('intval', array_column(rows('SELECT id FROM notifications WHERE is_read=1'), 'id'));
    echo json_encode($state, JSON_THROW_ON_ERROR);
    exit;
}

$dbName = getenv('DB_NAME') ?: 'crystal_test_student_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
if (!preg_match('/^crystal_test_student_[a-z0-9_]+$/D', $dbName)) throw new RuntimeException('Student tests require a NEW crystal_test_student_* database. Clear DB_NAME to create one automatically.');
putenv('DB_NAME='.$dbName);
putenv('CRYSTAL_CONFIG_FILE');
putenv('APP_ENV=test');
putenv('FORCE_HTTPS=0');
putenv('BACKUP_DIR='.sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'_backups');
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$server = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", getenv('DB_USER')?:'root', getenv('DB_PASSWORD')?:'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exists = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
$exists->execute([$dbName]);
if ((int)$exists->fetchColumn()) throw new RuntimeException('Test database already exists; refusing to replace it.');
$server->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
require_once __DIR__.'/../includes/app.php';
require_once __DIR__.'/../includes/ui.php';
require_once __DIR__.'/../includes/account_service.php';
require_once __DIR__.'/../includes/student_service.php';
require_once __DIR__.'/../includes/backup_service.php';
db()->exec(file_get_contents(__DIR__.'/../schema.sql'));

$password = password_hash('StudentTestPassword2026!', PASSWORD_BCRYPT);
foreach ([['Test Administrator','student_test_admin','Administrator'], ['Faculty Alpha','student_test_faculty','Faculty'], ['Faculty Beta','student_test_faculty_b','Faculty']] as [$name,$username,$role]) {
    query('INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,?)', [$name,$username,$password,$role]);
}
student_actor(1);
query("INSERT INTO courses(code,name) VALUES('STEST','Student Test Program')"); $course=(int)db()->lastInsertId();
query("INSERT INTO school_years(name,starts_on,ends_on) VALUES('Student Test Calendar','2020-01-01','2099-12-31')"); $year=(int)db()->lastInsertId();
query("INSERT INTO terms(school_year_id,name,starts_on,ends_on,submission_deadline,inc_deadline) VALUES(?,'Student Test Term','2020-01-01','2098-12-31','2098-12-31','2099-12-31')", [$year]); $term=(int)db()->lastInsertId();
query("UPDATE settings SET setting_value=? WHERE setting_key='active_term_id'", [(string)$term]);
query("UPDATE grading_policies SET is_approved=1 WHERE id=1");
foreach (['Student Alpha Section','Student Beta Section'] as $name) query('INSERT INTO sections(course_id,name,year_level) VALUES(?,?,2)', [$course,$name]);
$sections=array_map('intval',array_column(rows('SELECT id FROM sections ORDER BY id'),'id'));
foreach ([['ST-ALPHA','Student Alpha', $sections[0]], ['ST-BETA','Student Beta Confidential', $sections[1]], ['ST-GAMMA','Student Gamma', $sections[0]], ['ST-DELTA','Student Delta', $sections[0]]] as [$number,$name,$section]) {
    query('INSERT INTO students(student_number,full_name,course,year_level,course_id,section_id) VALUES(?,?,?,2,?,?)', [$number,$name,'Student Test Program',$course,$section]);
}
$students=array_map('intval',array_column(rows('SELECT id FROM students ORDER BY id'),'id'));
foreach ([['Student Alpha Login','student_test_alpha',$students[0],1,'Approved'], ['Student Beta Login','student_test_beta',$students[1],1,'Approved'], ['Unlinked Student','student_test_unlinked',null,1,'Approved'], ['Pending Student','student_test_pending',null,0,'Pending'], ['Inactive Student','student_test_inactive',null,0,'Approved'], ['Pending Login Student','student_test_pending_login',null,1,'Pending']] as [$name,$username,$linked,$active,$approval]) {
    query("INSERT INTO users(full_name,username,password_hash,role,student_id,is_active,registration_status) VALUES(?,?,?,'Student',?,?,?)", [$name,$username,$password,$linked,$active,$approval]);
}
$accounts=[]; foreach (rows('SELECT id,username FROM users') as $u) $accounts[$u['username']]=(int)$u['id'];
student_check(current_user()['role']==='Administrator', 'Administrator session survives schema 003');
student_fails(fn()=>query("INSERT INTO users(full_name,username,password_hash,role,student_id) VALUES('Duplicate','duplicate_student',?,'Student',?)", [$password,$students[0]]), 'Database rejects two accounts linked to the same student');
student_fails(fn()=>query("UPDATE users SET student_id=999999 WHERE username='student_test_unlinked'"), 'Database rejects a nonexistent student link');
student_fails(fn()=>manage_account(['action'=>'approve','id'=>$accounts['student_test_pending']]), 'Pending Student approval requires a verified student link');
student_fails(fn()=>manage_account(['action'=>'link','id'=>$accounts['student_test_unlinked'],'student_id'=>(string)$students[0]]), 'Administrator cannot duplicate an existing student-account link');
student_fails(fn()=>manage_account(['action'=>'link','id'=>$accounts['student_test_unlinked'],'student_id'=>'999999']), 'Administrator link validates the academic student record');
$linked = manage_account(['action'=>'link','id'=>$accounts['student_test_pending'],'student_id'=>(string)$students[2]]);
manage_account(['action'=>'approve','id'=>$accounts['student_test_pending']]);
student_check((int)scalar('SELECT student_id FROM users WHERE id=?',[$linked])===$students[2] && scalar('SELECT registration_status FROM users WHERE id=?',[$linked])==='Approved', 'Administrator links and approves a pending Student');
manage_account(['action'=>'toggle','id'=>$accounts['student_test_pending']]);
manage_account(['action'=>'link','id'=>$accounts['student_test_pending'],'student_id'=>'']);
student_actor($accounts['student_test_alpha']);
student_check(student_identity()['id']===$students[0] || (int)student_identity()['id']===$students[0], 'Student identity comes from its verified account link');
student_actor(1);

$gradeIds=[]; $offeringIds=[]; $subjectIds=[];
$fixtures = [
    ['PUB-ALPHA',$students[0],2,$sections[0],85.00,'Published',0,0,'Numeric'],
    ['DRAFT-ALPHA',$students[0],2,$sections[0],91.11,'Draft',0,0,'Numeric'],
    ['SUBMIT-ALPHA',$students[0],2,$sections[0],92.22,'Submitted',0,0,'Numeric'],
    ['APPROVE-ALPHA',$students[0],2,$sections[0],93.33,'Approved',0,0,'Numeric'],
    ['LEGACY-ALPHA',$students[0],2,$sections[0],94.44,'Published',1,0,'Numeric'],
    ['REVIEW-ALPHA',$students[0],2,$sections[0],95.55,'Published',0,1,'Numeric'],
    ['INC-ALPHA',$students[0],2,$sections[0],80.00,'Published',0,0,'INC'],
    ['PUB-BETA',$students[1],3,$sections[1],77.77,'Published',0,0,'Numeric'],
];
foreach ($fixtures as [$code,$student,$faculty,$section,$mark,$workflow,$legacy,$review,$result]) {
    query('INSERT INTO subjects(subject_code,title,units) VALUES(?,?,3)', [$code,'Subject '.$code]); $subject=(int)db()->lastInsertId();
    query('INSERT INTO offerings(subject_id,term_id,section_id,faculty_id,policy_id,needs_review) VALUES(?,?,?,?,1,?)', [$subject,$term,$section,$faculty,$review]); $offering=(int)db()->lastInsertId();
    query('INSERT INTO enrollments(student_id,offering_id) VALUES(?,?)', [$student,$offering]);
    $values=grade_values($mark,$mark);
    query('INSERT INTO grades(student_id,subject_id,academic_term,midterm,final,average,gpa,remarks,entered_by,offering_id,policy_id,units_snapshot,academic_snapshot,workflow,migration_review,inc_reason,inc_issued_on,inc_deadline,completion_status) VALUES(?,?,?,?,?,?,?,?,?,?,1,3,1,?,?,?,?,?,?)', [$student,$subject,'Student Test Term',$mark,$mark,$mark,$result==='INC'?null:$values['gpa'],$result==='INC'?'INC':$values['remarks'],$faculty,$offering,$workflow,$legacy,$result==='INC'?'INTERNAL_REQUIREMENT_NOTE':null,$result==='INC'?date('Y-m-d'):null,$result==='INC'?'2099-12-31':null,$result==='INC'?'Pending':'None']);
    $gradeIds[$code]=(int)db()->lastInsertId(); $offeringIds[$code]=$offering; $subjectIds[$code]=$subject;
}
student_fails(fn()=>manage_account(['action'=>'role','id'=>2,'role'=>'Student','student_id'=>(string)$students[3]]), 'Assigned faculty must be reassigned before changing to Student');
$notificationIds=[];
foreach ([
    ['SAFE_ACCOUNT_NOTICE','student',$students[0],null,null],
    ['SAFE_PUBLISHED_NOTICE','student',$students[0],$gradeIds['PUB-ALPHA'],$offeringIds['PUB-ALPHA']],
    ['INTERNAL_STAFF_ONLY_SENTINEL','staff',$students[0],null,null],
    ['OTHER_STUDENT_NOTIFICATION_SENTINEL','student',$students[1],null,null],
    ['DRAFT_NOTIFICATION_SENTINEL','student',$students[0],$gradeIds['DRAFT-ALPHA'],$offeringIds['DRAFT-ALPHA']],
    ['LEGACY_NOTIFICATION_SENTINEL','student',$students[0],$gradeIds['LEGACY-ALPHA'],$offeringIds['LEGACY-ALPHA']],
    ['REVIEW_NOTIFICATION_SENTINEL','student',$students[0],$gradeIds['REVIEW-ALPHA'],$offeringIds['REVIEW-ALPHA']],
    ['CROSS_GRADE_NOTIFICATION_SENTINEL','student',$students[0],$gradeIds['PUB-BETA'],$offeringIds['PUB-BETA']],
] as [$message,$audience,$student,$grade,$offering]) {
    query('INSERT INTO notifications(user_id,message,audience,student_id,grade_id,offering_id) VALUES(?,?,?,?,?,?)', [$accounts['student_test_alpha'],$message,$audience,$student,$grade,$offering]);
    $notificationIds[$message]=(int)db()->lastInsertId();
}
student_actor($accounts['student_test_alpha']);
$published=student_grade_rows([]);
student_check(count($published)===2, 'Student grade service returns only reviewed Published grades from reviewed offerings');
student_check(array_unique(array_map('intval',array_column($published,'student_id')))===[$students[0]], 'Student grade service returns only its linked academic identity');
$dashboard=student_dashboard_data();
student_check(!str_contains(json_encode($dashboard,JSON_THROW_ON_ERROR),'Student Beta Confidential'), 'Student overview excludes another student identity');
$notificationJson=json_encode(student_notifications(),JSON_THROW_ON_ERROR);
student_check(str_contains($notificationJson,'SAFE_ACCOUNT_NOTICE')&&str_contains($notificationJson,'SAFE_PUBLISHED_NOTICE'), 'Student notifications include permitted account and Published-grade messages');
foreach (['INTERNAL_STAFF_ONLY_SENTINEL','OTHER_STUDENT_NOTIFICATION_SENTINEL','DRAFT_NOTIFICATION_SENTINEL','LEGACY_NOTIFICATION_SENTINEL','REVIEW_NOTIFICATION_SENTINEL','CROSS_GRADE_NOTIFICATION_SENTINEL'] as $sentinel) student_check(!str_contains($notificationJson,$sentinel),'Student notification service excludes '.$sentinel);
foreach (['grade_card','summary','academic_history'] as $type) {
    $report=student_report($type,[]);
    student_check(count($report['data'])===($type==='summary'?1:2), 'Student report generates eligible '.$type);
    if ($type === 'summary') student_check((int)$report['data'][0]['results']===2 && (int)$report['data'][0]['inc']===1 && (float)$report['data'][0]['gwa']===2.25, 'Student academic summary excludes unpublished marks and INC from its GWA');
    student_check(!str_contains(json_encode($report,JSON_THROW_ON_ERROR),'Student Beta Confidential'), 'Student '.$type.' report excludes other students');
}
student_actor(1);
$backup=parse_database_backup(database_backup());
student_check(isset($backup['manifest']['tables']['users']) && in_array('student_id',$backup['manifest']['tables']['users']['columns'],true), 'Backup manifest preserves Student account links');
student_check(in_array('audience',$backup['manifest']['tables']['notifications']['columns'],true), 'Backup manifest preserves notification audience');

$state=['database'=>$dbName,'backup_dir'=>getenv('BACKUP_DIR'),'course'=>$course,'term'=>$term,'year'=>$year,'sections'=>$sections,'students'=>$students,'accounts'=>$accounts,'grades'=>$gradeIds,'subjects'=>$subjectIds,'offerings'=>$offeringIds,'notifications'=>$notificationIds,'password'=>'StudentTestPassword2026!'];
$stateFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'.json';
file_put_contents($stateFile,json_encode($state,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
putenv('CRYSTAL_STUDENT_TEST_STATE='.$stateFile);
if (in_array('--fixtures-only',$argv,true)) {
    echo "$studentChecks service checks passed. State: $stateFile\n";
    exit;
}

$listenPort=random_int(16000,24000);
$environment=getenv(); unset($environment['CRYSTAL_CONFIG_FILE']);
$environment['CRYSTAL_TEST_URL']='http://127.0.0.1:'.$listenPort;
$environment['CRYSTAL_TEST_PHP']=PHP_BINARY;
$logFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'_server.log';
$serverCommand=[PHP_BINARY];
if (!extension_loaded('zip')) array_push($serverCommand,'-d','extension=zip');
array_push($serverCommand,'-S','127.0.0.1:'.$listenPort,'bin/router.php');
$process=proc_open($serverCommand, [0=>['pipe','r'],1=>['file',$logFile,'a'],2=>['file',$logFile,'a']], $pipes, dirname(__DIR__),$environment);
if (!is_resource($process)) throw new RuntimeException('Unable to start isolated HTTP test server.');
fclose($pipes[0]);
try {
    $node=proc_open(['node','tests/student-http.mjs'], [0=>['pipe','r'],1=>STDOUT,2=>STDERR], $nodePipes, dirname(__DIR__),$environment);
    if (!is_resource($node)) throw new RuntimeException('Unable to start Student HTTP tests.');
    fclose($nodePipes[0]);
    $status=proc_close($node);
    if ($status!==0) throw new RuntimeException('Student HTTP checks failed. Server log: '.$logFile);
} finally { proc_terminate($process); proc_close($process); }
echo "\n$studentChecks service checks passed. Disposable database retained: $dbName\nState: $stateFile\n";
