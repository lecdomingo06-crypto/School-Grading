<?php
require_once __DIR__.'/../app.php';require_once __DIR__.'/../ui.php';
if(current_user())redirect(dashboard_url());
if(setting('registration_open','1')!=='1'){http_response_code(403);exit('Registration is currently closed.');}
if(!isset($_SESSION['register_captcha'])){$a=random_int(2,9);$b=random_int(2,9);$_SESSION['register_question']="$a + $b";$_SESSION['register_captcha']=(string)($a+$b);}
handle_post(function(){
    if(!hash_equals($_SESSION['register_captcha'],trim(request_string($_POST,'captcha'))))throw new DomainException('The arithmetic answer is incorrect.');
    $ip=mb_substr($_SERVER['REMOTE_ADDR']??'unknown',0,45);
    if((int)scalar("SELECT COUNT(*) FROM login_attempts WHERE username='#registration' AND ip_address=? AND attempted_at>=CURRENT_TIMESTAMP-INTERVAL 15 MINUTE",[$ip])>=5)throw new DomainException('Registration limit reached. Try again later.');
    $name=text_value($_POST,'full_name',150);$username=text_value($_POST,'username',80);$email=text_value($_POST,'email',190,false);$password=request_string($_POST,'password');
    if(!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/',$username))throw new DomainException('Username must contain 3–80 letters, numbers, dots, underscores, or hyphens.');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email address.');
    if(strlen($password)<10||strlen($password)>72||$password!==request_string($_POST,'confirm_password'))throw new DomainException('Use matching passwords of 10–72 bytes.');
    $accountType=request_string($_POST,'account_type')?:'Faculty';
    if(!in_array($accountType,['Faculty','Student'],true))throw new DomainException('Choose a Faculty or Student account request.');
    if($accountType==='Student'&&!student_schema_ready())throw new DomainException('Student registration will be available after the database upgrade.');
    // Student numbers and submitted role/student_id fields never establish ownership.
    query("INSERT INTO users(full_name,username,email,password_hash,role,is_active,registration_status) VALUES(?,?,?,?,?,0,'Pending')",[$name,$username,$email?:null,password_hash($password,PASSWORD_BCRYPT),$accountType]);
    $id=(int)db()->lastInsertId();query("INSERT INTO login_attempts(username,ip_address) VALUES('#registration',?)",[$ip]);audit('registration','users',$id,$accountType.' registration awaiting approval');notify_admins('New '.strtolower($accountType).' registration: '.$name);unset($_SESSION['register_captcha']);
},'login.php');
page_start('Request an account','An administrator will verify your identity and approve access.');?><section class="panel auth-panel"><form method="post" class="form-grid"><?php csrf_field();$types=['Faculty'=>'Faculty'];if(student_schema_ready())$types['Student']='Student';select_field('account_type','Account request',$types,'Faculty');input_field('full_name','Full name');input_field('username','Username','','text',true,'maxlength="80"');input_field('email','Email (optional)','','email',false);input_field('password','Password (10–72 bytes)','','password');input_field('confirm_password','Confirm password','','password');input_field('captcha','Solve '.$_SESSION['register_question'],'','number');?><p class="small text-secondary">Student academic records are linked only after administrator identity verification. Submitting a request does not grant access to grades.</p><button class="btn btn-primary">Submit registration</button></form><a class="d-block mt-3" href="login.php">Return to sign in</a></section><?php page_end();
