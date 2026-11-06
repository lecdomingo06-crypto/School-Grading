<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/grading.php';
if(PHP_SAPI!=='cli'){
    header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');header('Cache-Control: no-store, private');
    $https=isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
    if(getenv('FORCE_HTTPS')==='1'&&!$https){http_response_code(403);exit('HTTPS is required. Enable AutoSSL and the web-server HTTPS redirect.');}
    if($https)header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if(session_status()!==PHP_SESSION_ACTIVE){ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_name('crystal_session');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);session_start();}
}
set_exception_handler(function(Throwable $e):void{error_log('Crystal: '.$e);if(PHP_SAPI==='cli'){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}http_response_code($e instanceof DomainException?422:500);$message=$e instanceof DomainException?$e->getMessage():'Unable to complete this request. Check the installation and contact the administrator.';echo '<!doctype html><meta charset="utf-8"><title>Request could not be completed</title><p>'.h($message).'</p><a href="dashboard.php">Return to dashboard</a>';});
function h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function request_string(array $source,string $key):string{return is_string($source[$key]??null)?$source[$key]:'';}
function redirect(string $url):never{header('Location: '.$url);exit;}
function query(string $sql,array $params=[]):PDOStatement{$s=db()->prepare($sql);$s->execute($params);return $s;}
function rows(string $sql,array $params=[]):array{return query($sql,$params)->fetchAll();}
function row(string $sql,array $params=[]):?array{return query($sql,$params)->fetch()?:null;}
function scalar(string $sql,array $params=[]):mixed{return query($sql,$params)->fetchColumn();}
function setting(string $key,string $default=''):string{$v=scalar('SELECT setting_value FROM settings WHERE setting_key=?',[$key]);return $v===false?$default:(string)$v;}
function csrf_token():string{return $_SESSION['csrf_token']??=bin2hex(random_bytes(32));}
function verify_csrf():void{if(!hash_equals(csrf_token(),request_string($_POST,'csrf_token'))){http_response_code(400);exit('Invalid request token. Reload the page.');}}
function csrf_field():void{echo '<input type="hidden" name="csrf_token" value="'.h(csrf_token()).'">';}
function current_user():?array{
    $s=$_SESSION['user']??null;if(!$s)return null;
    $linkColumn=student_schema_ready()?'student_id':'NULL AS student_id';
    $u=row("SELECT id,full_name,username,email,role,is_active,registration_status,must_change_password,auth_version,$linkColumn FROM users WHERE id=?",[$s['id']]);
    if($u&&!demo_login_allowed($u['username'])){unset($_SESSION['user']);return null;}
    if(!$u||!$u['is_active']||$u['registration_status']!=='Approved'||(int)$u['auth_version']!==(int)($s['auth_version']??0)||($_SESSION['epoch']??'')!==setting('session_epoch','1')||time()-(int)($_SESSION['last_activity']??0)>(int)setting('session_timeout','1800')){unset($_SESSION['user']);return null;}return $u;
}
function student_schema_ready():bool{
    static $ready;
    return $ready ??= (bool)scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='student_id'");
}
function is_student():bool{return (current_user()['role']??'')==='Student';}
function dashboard_url(?array $user=null):string{
    $user ??= current_user();
    if(!$user)return 'login.php';
    return $user['role']==='Student'?'student.php':'dashboard.php';
}
function require_login():array{$u=current_user();if(!$u){if(isset($_GET['api'])){http_response_code(401);exit;}redirect('login.php');}$_SESSION['last_activity']=time();if(time()-(int)($_SESSION['last_seen_write']??0)>60){query('UPDATE users SET last_seen_at=NOW() WHERE id=?',[$u['id']]);$_SESSION['last_seen_write']=time();}if($u['must_change_password']&&!in_array(basename($_SERVER['SCRIPT_NAME']??''),['profile.php','logout.php'],true))redirect('profile.php');return $u;}
function is_admin():bool{return(current_user()['role']??'')==='Administrator';}
function require_role(string ...$roles):array{$u=require_login();if(!in_array($u['role'],$roles,true))deny();return $u;}
function deny():never{http_response_code(403);exit('You do not have permission to access this record.');}
function require_admin():array{return require_role('Administrator');}
function require_staff():array{return require_role('Administrator','Faculty');}
function valid_date(string $s):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);return $d!==false&&$d->format('Y-m-d')===$s;}
function text_value(array $source,string $key,int $max=150,bool $required=true):string{$s=trim(request_string($source,$key));if(($required&&$s==='')||mb_strlen($s)>$max)throw new DomainException("Enter a valid $key (maximum $max characters).");return $s;}
function positive_id(mixed $v):int{if(filter_var($v,FILTER_VALIDATE_INT)===false||(int)$v<1)throw new DomainException('Choose a valid record.');return(int)$v;}
function date_value(array $source,string $key):string{$s=request_string($source,$key);if(!valid_date($s))throw new DomainException("Enter a valid $key.");return $s;}
function flash(string $type,string $message):void{$_SESSION['flash']=['type'=>$type,'message'=>$message];}
function get_flash():?array{$f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f;}
function transaction(callable $fn):mixed{if(db()->inTransaction())return $fn();db()->beginTransaction();try{$r=$fn();db()->commit();return $r;}catch(Throwable $e){db()->rollBack();throw $e;}}
function safe_snapshot(?array $data):?string{if($data===null)return null;foreach(['password','password_hash','token','auth_version']as $k)unset($data[$k]);return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
function audit(string $action,string $entity,?int $id,string $description,?array $before=null,?array $after=null,?int $offering=null):void{$u=current_user();query('INSERT INTO activity_logs(user_id,full_name,activity_description,log_date,log_time,ip_address,action,entity,record_id,before_data,after_data,offering_id) VALUES(?,?,?,CURRENT_DATE,CURRENT_TIME,?,?,?,?,?,?,?)',[$u['id']??null,$u['full_name']??'System / unauthenticated',mb_substr($description,0,500),mb_substr($_SERVER['REMOTE_ADDR']??'CLI',0,45),$action,$entity,$id,safe_snapshot($before),safe_snapshot($after),$offering]);}
function log_activity(string $description):void{audit('activity','system',null,$description);}
function notify(int $userId,string $message,?int $offering=null):void{
    if(setting('notifications_enabled','1')!=='1')return;
    $target=row('SELECT role FROM users WHERE id=?',[$userId]);if(!$target)return;
    if(student_schema_ready()){
        // Staff workflow comments must never be forwarded to student accounts.
        if($target['role']==='Student'&&$offering!==null)return;
        query('INSERT INTO notifications(user_id,message,offering_id,audience) VALUES(?,?,?,?)',[$userId,mb_substr($message,0,500),$offering,$target['role']==='Student'?'student':'staff']);
    }else query('INSERT INTO notifications(user_id,message,offering_id) VALUES(?,?,?)',[$userId,mb_substr($message,0,500),$offering]);
}
function notify_published_grade(int $gradeId):void{
    if(!student_schema_ready()||setting('notifications_enabled','1')!=='1')return;
    $g=row("SELECT g.id,g.student_id,g.offering_id,s.subject_code FROM grades g JOIN subjects s ON s.id=g.subject_id WHERE g.id=? AND g.workflow='Published' AND g.migration_review=0",[$gradeId]);if(!$g)return;
    foreach(rows("SELECT id FROM users WHERE role='Student' AND student_id=? AND is_active=1 AND registration_status='Approved'",[$g['student_id']])as $u){
        query("INSERT INTO notifications(user_id,message,offering_id,audience,student_id,grade_id) VALUES(?,?,?,'student',?,?)",[$u['id'],'A published result is available for '.$g['subject_code'].'.',$g['offering_id'],$g['student_id'],$g['id']]);
    }
}
function notify_admins(string $message,?int $offering=null):void{foreach(rows("SELECT id FROM users WHERE role='Administrator' AND is_active=1 AND registration_status='Approved'")as $u)notify((int)$u['id'],$message,$offering);}
function scope_sql(string $alias='o'):array{require_staff();return is_admin()?['1=1',[]]:["$alias.faculty_id=? AND $alias.needs_review=0",[(int)current_user()['id']]];}
function offering(int $id,bool $lock=false):array{require_staff();$o=row('SELECT o.*,s.subject_code,s.title,s.units,s.is_academic,t.name AS term_name,t.starts_on AS term_start,t.ends_on,t.submission_deadline,t.inc_deadline,t.is_active AS term_active FROM offerings o JOIN subjects s ON s.id=o.subject_id LEFT JOIN terms t ON t.id=o.term_id WHERE o.id=?'.($lock?' FOR UPDATE':''),[$id]);if(!$o)throw new DomainException('Subject offering not found.');if(!is_admin()&&((int)$o['faculty_id']!==(int)current_user()['id']||$o['needs_review']))deny();return $o;}
function visible_student(int $id):array{require_staff();$s=row('SELECT * FROM students WHERE id=?',[$id]);if(!$s)throw new DomainException('Student not found.');if(!is_admin()&&!scalar('SELECT COUNT(*) FROM enrollments e JOIN offerings o ON o.id=e.offering_id WHERE e.student_id=? AND o.faculty_id=? AND o.needs_review=0',[$id,current_user()['id']]))deny();return $s;}
function policy(int $id):array{return row('SELECT * FROM grading_policies WHERE id=?',[$id])??throw new DomainException('Grading policy not found.');}
function handle_post(callable $fn,string $url):void{if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;verify_csrf();try{transaction($fn);flash('success','Changes saved.');redirect($url);}catch(DomainException $e){flash('danger',$e->getMessage());}catch(PDOException $e){if($e->getCode()!=='23000')throw $e;flash('danger','This record already exists or is linked to other records. Archive it instead of deleting it.');}}
require_once __DIR__.'/grade_service.php';
require_once __DIR__.'/demo_service.php';
