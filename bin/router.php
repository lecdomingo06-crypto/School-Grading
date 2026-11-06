<?php
// Local development only. Apache uses the supplied .htaccess rules.
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));$root=realpath(dirname(__DIR__));
if($path==='/')$path='/index.php';$real=realpath($root.$path);
if(!$real||!str_starts_with(strtolower($real),strtolower($root.DIRECTORY_SEPARATOR))||str_contains($path,'\\')||preg_match('~(^|/)\.~',$path)){http_response_code(404);exit('Not found.');}
$public=['index','login','logout','register','profile','dashboard','student','students','subjects','grades','records','academic','reports','interventions','import','notifications','users','logs','backup','settings'];
if(preg_match('~^/([a-z_]+)\.php$~D',$path,$m)&&in_array($m[1],$public,true)){require $real;return true;}
if(preg_match('~^/assets/[A-Za-z0-9_./-]+\.(css|js|svg|png|jpg|jpeg|woff2?)$~iD',$path)||preg_match('~^/uploads/[A-Za-z0-9_-]+\.(png|jpg|jpeg|pdf|txt)$~iD',$path))return false;
http_response_code(404);echo 'Not found.';
