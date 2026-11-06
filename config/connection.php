<?php
declare(strict_types=1);
$externalConfig = getenv('CRYSTAL_CONFIG_FILE');
if ($externalConfig) {
    $real=realpath($externalConfig); $root=realpath(__DIR__.'/..');
    $normalized=strtolower(str_replace('\\','/',$real?:''));$documentRoot=!empty($_SERVER['DOCUMENT_ROOT'])?realpath($_SERVER['DOCUMENT_ROOT']):false;
    if (!$real || str_starts_with(strtolower($real),strtolower($root.DIRECTORY_SEPARATOR)) || str_contains($normalized,'/public_html/') || ($documentRoot&&str_starts_with(strtolower($real),strtolower($documentRoot.DIRECTORY_SEPARATOR)))) throw new RuntimeException('CRYSTAL_CONFIG_FILE must point outside the application and public document root.');
    $values=require $real;
    foreach(['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','BACKUP_DIR','APP_ENV','FORCE_HTTPS'] as $k) if(isset($values[$k])&&getenv($k)===false) putenv($k.'='.$values[$k]);
}
date_default_timezone_set('Asia/Manila');
function db(): PDO {
    static $pdo; if($pdo instanceof PDO) return $pdo;
    $host=getenv('DB_HOST')?:'127.0.0.1'; $port=getenv('DB_PORT')?:'3306'; $name=getenv('DB_NAME')?:'student_grade_dds';
    $pdo=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",getenv('DB_USER')?:'root',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone = '+08:00'"); return $pdo;
}
