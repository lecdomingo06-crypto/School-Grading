<?php
require_once __DIR__.'/../app.php';require_once __DIR__.'/../ui.php';$user=require_login();
handle_post(function()use($user){
    $account=row('SELECT * FROM users WHERE id=? FOR UPDATE',[$user['id']]);
    $action=request_string($_POST,'action');
    if($action==='password'){
        if(!password_verify(request_string($_POST,'current_password'),$account['password_hash']))throw new DomainException('Your current password is incorrect.');
        $password=request_string($_POST,'password');if(strlen($password)<10||strlen($password)>72||$password!==request_string($_POST,'confirm_password'))throw new DomainException('Use matching passwords of 10–72 bytes.');if(password_verify($password,$account['password_hash']))throw new DomainException('Choose a new password different from your current password.');
        query('UPDATE users SET password_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT),$user['id']]);
        $_SESSION['user']['auth_version']=(int)$account['auth_version']+1;session_regenerate_id(true);audit('password_change','users',(int)$user['id'],'Changed own password');
    }else{
        if($user['must_change_password'])throw new DomainException('Change your temporary password first.');
        if(is_student()){
            foreach(['student_id','student_number','course','course_id','year_level','section_id','academic_status','role']as $field)if(array_key_exists($field,$_POST))throw new DomainException('Official academic details can only be changed by authorized school staff.');
            if(isset($_POST['full_name'])&&request_string($_POST,'full_name')!==$account['full_name'])throw new DomainException('Your registered name is read-only. Contact the school office for a correction.');
            $name=$account['full_name'];
        }else $name=text_value($_POST,'full_name',150);
        $email=text_value($_POST,'email',190,false);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email.');
        query('UPDATE users SET full_name=?,email=? WHERE id=?',[$name,$email?:null,$user['id']]);audit('profile_update','users',(int)$user['id'],'Updated profile',['full_name'=>$account['full_name'],'email'=>$account['email']],['full_name'=>$name,'email'=>$email]);
    }
},'profile.php');
page_start('Your profile','Manage your account contact details and password.');if($user['must_change_password'])echo '<div class="alert alert-warning">Change your temporary password before accessing other pages.</div>';
?><?php if(is_student()){require_once __DIR__.'/../student_service.php';$identity=student_identity();echo '<section class="panel"><h2>Official academic identity</h2>';if($identity)data_table([$identity],['student_number'=>'Student number','full_name'=>'Official name','course'=>'Program','year_level'=>'Year level','section_name'=>'Section','academic_status'=>'Academic status']);else echo '<p>Your academic record is awaiting administrator verification.</p>';echo '<p class="small text-secondary">Contact your school office for corrections to official records.</p></section>';}?><div class="two-column"><section class="panel"><h2>Personal details</h2><form method="post" class="form-grid"><?php csrf_field();input_field('full_name','Registered name',$user['full_name'],'text',true,is_student()?'readonly aria-readonly="true"':'');input_field('email','Email',$user['email'],'email',false);?><button class="btn btn-primary" name="action" value="profile">Save profile</button></form></section><section class="panel"><h2>Change password</h2><form method="post" class="form-grid"><?php csrf_field();input_field('current_password','Current password','','password');input_field('password','New password (10–72 bytes)','','password');input_field('confirm_password','Confirm new password','','password');?><button class="btn btn-primary" name="action" value="password">Update password</button></form></section></div><?php page_end();
