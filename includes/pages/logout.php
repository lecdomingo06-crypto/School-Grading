<?php
require_once __DIR__.'/../app.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');exit('POST required.');}
verify_csrf();if(current_user())audit('logout','users',(int)current_user()['id'],'Signed out');$_SESSION=[];session_destroy();setcookie(session_name(),'',time()-3600,'/');redirect('login.php');
