<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/app.php';require_once __DIR__.'/../includes/backup_service.php';
require_once __DIR__.'/../includes/migration_service.php';
$command=$argv[1]??'help';$apply=in_array('--apply',$argv,true);$root=dirname(__DIR__);
function execute_sql_file(string $file):void{db()->exec(file_get_contents($file));}
function legacy_recovery_dump():string{
    $content="-- Pre-upgrade recovery; restore manually into an EMPTY recovery database. Contains password hashes; keep private.\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach(rows('SHOW TABLES')as $entry){$table=array_values($entry)[0];if(!preg_match('/^[a-zA-Z0-9_]+$/',$table))throw new RuntimeException('Unexpected table name.');$create=array_values(row("SHOW CREATE TABLE `$table`"))[1];$content.=$create.";\n";foreach(rows("SELECT * FROM `$table`")as $r){$values=array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),array_values($r));$content.="INSERT INTO `$table` VALUES (".implode(',',$values).");\n";}}return $content."SET FOREIGN_KEY_CHECKS=1;\n";
}
switch($command){
case 'install':
    if(in_array('--demo',$argv,true)&&getenv('APP_ENV')==='production')throw new DomainException('Demo data is disabled in production.');
    if(rows('SHOW TABLES'))throw new DomainException('Install requires an empty selected database. Use migrate for existing installations.');
    $password=getenv('ADMIN_INITIAL_PASSWORD')?:'';if(strlen($password)<12||strlen($password)>72)throw new DomainException('Set ADMIN_INITIAL_PASSWORD to a unique 12–72 byte password before installing.');
    execute_sql_file($root.'/schema.sql');
    query("INSERT INTO users(full_name,username,password_hash,role,must_change_password) VALUES('System Administrator',?,?,'Administrator',1)",[getenv('ADMIN_INITIAL_USERNAME')?:'admin',password_hash($password,PASSWORD_BCRYPT)]);
    if(in_array('--demo',$argv,true)){if(getenv('APP_ENV')==='production')throw new DomainException('Demo data is disabled in production.');require __DIR__.'/demo.php';seed_demo();}
    echo "Installed schema 003. Sign in with the configured administrator and change the initial password.\n";break;
case 'migrate':
    $pending=migrate_database($apply);
    if(!$pending)echo "Migration 003 is already present. No changes made.\n";
    elseif(!$apply)echo 'Pending: '.implode(', ',$pending).". Run migrate --apply after reviewing docs/MIGRATION.md.\n";
    else echo "Migration complete. Existing identities and grades were preserved; no student accounts were linked automatically.\n";
    break;
case 'expire-inc':echo 'Converted '.expire_incomplete_grades()." expired INC results.\n";break;
case 'backup':
    $scheduled=in_array('--scheduled',$argv,true);$dir=backup_directory();$schedule=setting('backup_schedule','manual');$marker=$dir.'/schedule-last-run.json';$stamp=is_file($marker)?json_decode(file_get_contents($marker),true):[];
    $key=match($schedule){'daily'=>date('Y-m-d'),'weekly'=>date('o-W'),'monthly'=>date('Y-m'),default=>''};
    if($scheduled&&($key===''||($stamp['period']??'')===$key&&($stamp['schedule']??'')===$schedule)){echo "No scheduled backup due.\n";break;}
    $lock=fopen($dir.'/backup.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))throw new DomainException('Another backup is running.');
    $path=save_private_backup(database_backup(),'sql');$zip=create_file_backup('application');audit('backup_cli','backups',null,'Created CLI SQL and application backup');
    if($scheduled)file_put_contents($marker,json_encode(['schedule'=>$schedule,'period'=>$key]));
    $days=(int)setting('backup_retention_days','30');foreach(glob($dir.'/backup_*.{sql,zip}',GLOB_BRACE)as $old)if(filemtime($old)<time()-$days*86400)unlink($old);
    echo "Backups created:\n$path\n$zip\n";flock($lock,LOCK_UN);fclose($lock);break;
case 'restore-source':
    if(!$apply||!in_array('--allow-source-code',$argv,true))throw new DomainException('Offline source restore requires --apply --allow-source-code and a trusted archive path as the second argument.');
    $file=$argv[2]??'';if(!is_file($file))throw new DomainException('Source backup not found.');$stage=stage_file_backup($file,true);$recovery=apply_staged_files($stage,true);remove_staging_directory($stage['directory']);echo 'Source restored. Recovery copies: '.$recovery.PHP_EOL;break;
default:echo "Commands:\n install [--demo] (empty DB; ADMIN_INITIAL_PASSWORD required)\n migrate [--apply]\n expire-inc\n backup [--scheduled]\n restore-source ARCHIVE --apply --allow-source-code\nSet DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD and BACKUP_DIR or CRYSTAL_CONFIG_FILE.\n";
}
