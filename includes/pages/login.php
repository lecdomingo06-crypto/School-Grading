<?php
require_once __DIR__.'/../app.php';require_once __DIR__.'/../ui.php';
if(current_user())redirect(dashboard_url());
function new_captcha():void{$a=random_int(2,9);$b=random_int(2,9);$_SESSION['captcha_question']="$a + $b";$_SESSION['captcha_answer']=(string)($a+$b);}
if(!isset($_SESSION['captcha_answer']))new_captcha();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();$username=mb_substr(trim(request_string($_POST,'username')),0,80);$ip=mb_substr($_SERVER['REMOTE_ADDR']??'unknown',0,45);
    $blocked=(int)scalar("SELECT COUNT(*) FROM login_attempts WHERE username<>'#registration' AND (username=? OR ip_address=?) AND attempted_at>=CURRENT_TIMESTAMP-INTERVAL 15 MINUTE",[$username,$ip])>=5;
    $account=row('SELECT * FROM users WHERE username=?',[$username]);
    $validCaptcha=hash_equals($_SESSION['captcha_answer'],trim(request_string($_POST,'captcha')));
    $validPassword=password_verify(request_string($_POST,'password'),$account['password_hash']??'$2y$12$gItjbGXcXNgNQHdRVbVa5eaACiDwVyRcFwz85tjV8vovZASk1POHG');
    if(!$blocked&&$validCaptcha&&$validPassword&&$account&&$account['is_active']&&$account['registration_status']==='Approved'){
        session_regenerate_id(true);$_SESSION=['user'=>['id'=>$account['id'],'auth_version'=>$account['auth_version']],'epoch'=>setting('session_epoch','1'),'last_activity'=>time()];
        transaction(function()use($username){query('DELETE FROM login_attempts WHERE username=?',[$username]);audit('login','users',(int)current_user()['id'],'Signed in');});redirect(dashboard_url());
    }
    transaction(function()use($username,$ip,$blocked){query('INSERT INTO login_attempts(username,ip_address) VALUES(?,?)',[$username,$ip]);audit($blocked?'login_blocked':'login_failed','users',null,'Unsuccessful sign-in for '.$username);});
    flash('danger',$blocked?'Too many failed attempts. Wait 15 minutes after the latest failed attempt.':'Sign-in failed. Check credentials, approval status, and the arithmetic answer.');new_captcha();
}
page_start('Welcome back','Sign in to manage academic records and performance.');
?><section class="panel auth-panel"><img class="auth-logo" src="<?=h(school_logo())?>" alt="School emblem" width="76" height="76"><h2>Sign in to Student Grade DDS</h2><form method="post" class="form-grid"><?php csrf_field();input_field('username','Username','','text',true,'maxlength="80" autocomplete="username"');input_field('password','Password','','password',true,'autocomplete="current-password"');input_field('captcha','Solve '.$_SESSION['captcha_question'],'','number');?><button class="btn btn-primary" type="submit">Sign in</button></form><?php if(setting('registration_open','1')==='1'):?><p class="mt-3"><a href="register.php">Request an account</a></p><?php endif;?><p class="small text-secondary">Need a password reset? Contact your administrator.</p></section><?php page_end();
