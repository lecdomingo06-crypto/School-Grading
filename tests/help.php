<?php
declare(strict_types=1);
// Creates only a fresh disposable database. Never seeds the installed application.
if (PHP_SAPI !== 'cli') exit;
$dbName='crystal_test_help_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
putenv('CRYSTAL_CONFIG_FILE'); putenv('DB_NAME='.$dbName); putenv('APP_ENV=test');
putenv('BACKUP_DIR='.sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'_backups');
$server=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
require_once __DIR__.'/../includes/app.php';
require_once __DIR__.'/../includes/help_documents.php';
require_once __DIR__.'/../includes/student_service.php';
require_once __DIR__.'/../includes/dashboard_service.php';
require_once __DIR__.'/../includes/backup_service.php';
db()->exec(file_get_contents(__DIR__.'/../schema.sql'));
$checks=0;
function help_check(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException('FAILED: '.$label);$checks++;echo "PASS $label\n";}
query("INSERT INTO users(full_name,username,password_hash,role) VALUES('Existing owner','existing_owner',?,'Administrator')",[password_hash('ExistingOwnerPassword2026!',PASSWORD_BCRYPT)]);
$owner=row("SELECT * FROM users WHERE username='existing_owner'");
query("INSERT INTO students(student_number,full_name,course,year_level) VALUES('EXISTING-1','Existing academic record','Existing program',1)");
$existingStudent=row("SELECT * FROM students WHERE student_number='EXISTING-1'");
$_SESSION=['test_preserved'=>'existing session'];
$result=seed_demo_dataset();
help_check($result['created']===true,'Sample dataset is created explicitly');
help_check((int)scalar("SELECT COUNT(*) FROM students WHERE student_number LIKE 'DEMO-%'")===100,'Exactly 100 fictional student records');
help_check((int)scalar('SELECT COUNT(*) FROM enrollments WHERE offering_id=?',[$result['offering_id']])===100,'All 100 sample students have enrollment');
help_check((int)scalar('SELECT COUNT(*) FROM grades WHERE offering_id=?',[$result['offering_id']])===100,'All 100 sample students have grades');
help_check(row("SELECT * FROM users WHERE username='existing_owner'")===$owner,'Existing account and password are unchanged');
help_check(row("SELECT * FROM students WHERE student_number='EXISTING-1'")===$existingStudent,'Existing academic record is unchanged');
help_check($_SESSION===['test_preserved'=>'existing session'],'Seeder restores its previous CLI session');
foreach (demo_accounts() as $demo) {
    $account=row('SELECT * FROM users WHERE username=?',[$demo['username']]);
    help_check($account['role']===$demo['role'] && password_verify($demo['password'],$account['password_hash']) && $account['password_hash']!==$demo['password'],'Hashed working credentials for '.$demo['role']);
    help_check($account['registration_status']==='Approved' && (int)$account['is_active']===1 && (int)$account['must_change_password']===0,'Demo '.$demo['role'].' can sign in without changing public sample password');
}
help_check(count(available_demo_accounts())===3,'Login can display all three working demo credentials');
$counts=[];foreach(['users','students','grades','enrollments','grade_history','activity_logs','notifications'] as $table)$counts[$table]=(int)scalar("SELECT COUNT(*) FROM `$table`");
$again=seed_demo_dataset();
help_check($again['created']===false,'Running sample seed again is idempotent');
foreach($counts as $table=>$count)help_check((int)scalar("SELECT COUNT(*) FROM `$table`")===$count,'No duplicate rows on rerun: '.$table);
demo_actor($result['student_account_id']);
$ownGrades=student_grade_rows([]);
help_check(count($ownGrades)===1 && (int)$ownGrades[0]['student_id']===$result['student_id'],'Demo Student sees only its own published grade');
help_check((float)$ownGrades[0]['gpa']===2.25,'Demo sample follows existing grade calculation');
help_check(count(student_subject_rows([]))===1,'Demo Student has a subject to view');
help_check(count(student_notifications()['stored'])===1,'Demo Student has an authorized publication notice');
demo_actor($result['faculty_id']);
[$scope,$parameters]=scope_sql();
help_check((int)scalar("SELECT COUNT(*) FROM offerings o WHERE $scope",$parameters)===1,'Demo Faculty is scoped to its one class');
foreach(['Published'=>80,'Draft'=>10,'Submitted'=>5,'Approved'=>5] as $workflow=>$expected)help_check((int)scalar('SELECT COUNT(*) FROM grades WHERE offering_id=? AND workflow=?',[$result['offering_id'],$workflow])===$expected,'Sample workflow has '.$expected.' '.$workflow.' results');
demo_actor($result['admin_id']);
help_check(current_user()['role']==='Administrator','Demo Administrator is usable');
$snapshot=parse_database_backup(database_backup());
help_check($snapshot['manifest']['tables']['students']['count']===101,'Sample records remain compatible with database backup');
$documents=help_documents();
help_check(isset($documents['product'],$documents['technical']),'Both requirements documents exist');
help_check(count($documents['product']['sections'])>=7 && count($documents['technical']['sections'])>=7,'Documents include product and technical requirements beyond a title');
putenv('APP_ENV=production');
help_check(available_demo_accounts()===[],'Production hides public sample passwords');
help_check(!demo_login_allowed('demo_admin') && !demo_login_allowed('DEMO_STUDENT'),'Production rejects demo logins regardless of username casing');
help_check(current_user()===null,'Production revokes an existing demo session');
help_check(demo_login_allowed('existing_owner'),'Normal accounts remain allowed in production');
try{seed_demo_dataset();throw new RuntimeException('Production seeding was allowed.');}catch(DomainException){help_check(true,'Production refuses demo data loading');}
putenv('APP_ENV=test');
query('UPDATE users SET password_hash=? WHERE username=?',[password_hash('DifferentDemoPassword2026!',PASSWORD_BCRYPT),'demo_faculty']);
help_check(count(available_demo_accounts())===2,'Changed sample passwords are not advertised as working');
query("UPDATE settings SET setting_value='0' WHERE setting_key='demo_access_enabled'");
help_check(available_demo_accounts()===[] && !demo_login_allowed('demo_admin'),'Disabling local demo access hides credentials and blocks login');
query("UPDATE settings SET setting_value='1' WHERE setting_key='demo_access_enabled'");
// Keep standard fixture passwords for the browser/HTTP checks that follow.
query('UPDATE users SET password_hash=? WHERE username=?',[password_hash('DemoFaculty2026!',PASSWORD_BCRYPT),'demo_faculty']);
$state=['database'=>$dbName,'backup_dir'=>getenv('BACKUP_DIR'),'seed'=>$result,'checks'=>$checks];
$statePath=sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'.json';
file_put_contents($statePath,json_encode($state,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "\n$checks checks passed. State: $statePath\n";
