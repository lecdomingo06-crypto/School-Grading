<?php
if(PHP_SAPI!=='cli')exit;
$state=json_decode(file_get_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'crystal-test-latest.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^crystal_test_[a-z0-9_]+$/D',$state['database']))throw new RuntimeException('Invalid test database.');
putenv('DB_NAME='.$state['database']);putenv('BACKUP_DIR='.$state['backup_dir']);require_once __DIR__.'/../includes/app.php';
if(($argv[1]??'')==='reset-attempts'){query('DELETE FROM login_attempts');echo 'Test attempts cleared.';exit;}
$state['course']=(int)scalar('SELECT id FROM courses ORDER BY id LIMIT 1');$state['section']=(int)scalar('SELECT id FROM sections ORDER BY id LIMIT 1');$state['term']=(int)scalar('SELECT id FROM terms ORDER BY id LIMIT 1');$state['policy']=(int)scalar('SELECT id FROM grading_policies ORDER BY id DESC LIMIT 1');$state['intervention']=(int)scalar('SELECT id FROM interventions ORDER BY id DESC LIMIT 1');$state['audit_reports']=(int)scalar("SELECT COUNT(*) FROM activity_logs WHERE action='report_generate'");$state['users']=rows('SELECT id,username,role,is_active,registration_status,must_change_password FROM users ORDER BY id');echo json_encode($state,JSON_THROW_ON_ERROR);
