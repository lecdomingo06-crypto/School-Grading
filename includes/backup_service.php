<?php
const BACKUP_TABLES=['users','courses','school_years','terms','sections','subjects','students','grading_policies','offerings','enrollments','grades','grade_history','activity_logs','login_attempts','interventions','notifications','settings','schema_migrations'];
function backup_directory():string{
    $root=realpath(dirname(__DIR__));if(getenv('APP_ENV')==='production'&&!getenv('BACKUP_DIR'))throw new DomainException('Configure private BACKUP_DIR outside public_html before production backup operations.');$dir=getenv('BACKUP_DIR')?:dirname($root).DIRECTORY_SEPARATOR.'.crystal-private-'.substr(hash('sha256',$root),0,12);
    if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot create private backup storage.');$real=realpath($dir);
    $normalized=strtolower(str_replace('\\','/',$real?:''));$documentRoot=!empty($_SERVER['DOCUMENT_ROOT'])?realpath($_SERVER['DOCUMENT_ROOT']):false;
    if(!$real||str_starts_with(strtolower($real.DIRECTORY_SEPARATOR),strtolower($root.DIRECTORY_SEPARATOR))||($documentRoot&&str_starts_with(strtolower($real.DIRECTORY_SEPARATOR),strtolower($documentRoot.DIRECTORY_SEPARATOR)))||str_contains($normalized,'/public_html/')||str_ends_with($normalized,'/public_html'))throw new DomainException('Backup storage must be outside the application and public document root.');return $real;
}
function table_columns(string $table):array{static $cache=[];if(!in_array($table,BACKUP_TABLES,true))throw new DomainException('Unsupported backup table.');return $cache[$table]??=array_column(rows("SHOW COLUMNS FROM `$table`"),'Field');}
function canonical_rows(array $data):array{return array_map(fn($r)=>array_map(fn($v)=>$v===null?null:(string)$v,array_values($r)),$data);}
require_once __DIR__.'/backup_format.php';
function save_private_backup(string $content,string $extension):string{$path=backup_directory().DIRECTORY_SEPARATOR.'backup_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).'.'.$extension;if(file_put_contents($path,$content,LOCK_EX)===false)throw new RuntimeException('Cannot write private backup.');chmod($path,0600);return $path;}
function restore_database(array $backup):string{
    $actor=current_user();$recovery=save_private_backup(database_backup(),'sql');
    transaction(function()use($backup,$actor){
        foreach(array_reverse(BACKUP_TABLES)as $table)query("DELETE FROM `$table`");
        $studentLinks=[];
        foreach(BACKUP_TABLES as $table){$cols=table_columns($table);$sql="INSERT INTO `$table` (`".implode('`,`',$cols).'`) VALUES('.implode(',',array_fill(0,count($cols),'?')).')';foreach($backup['data'][$table]as $values){
            if($table==='users'&&student_schema_ready()){$linkIndex=array_search('student_id',$cols,true);$idIndex=array_search('id',$cols,true);if($values[$linkIndex]!==null){$studentLinks[$values[$idIndex]]=$values[$linkIndex];$values[$linkIndex]=null;}}
            query($sql,$values);
        }}
        foreach($studentLinks as $userId=>$studentId)query('UPDATE users SET student_id=? WHERE id=?',[$studentId,$userId]);
        if(student_schema_ready()&&scalar("SELECT COUNT(*) FROM users WHERE student_id IS NOT NULL AND role<>'Student'"))throw new DomainException('Backup contains an invalid student identity link.');
        foreach(BACKUP_TABLES as $table)if((int)scalar("SELECT COUNT(*) FROM `$table`")!==$backup['manifest']['tables'][$table]['count'])throw new RuntimeException('Restoration row verification failed.');
        if(scalar('SELECT COUNT(*) FROM grades g LEFT JOIN enrollments e ON e.student_id=g.student_id AND e.offering_id=g.offering_id WHERE g.offering_id IS NOT NULL AND e.id IS NULL'))throw new DomainException('Backup contains grades without matching enrollment.');
        query("INSERT INTO settings(setting_key,setting_value) VALUES('session_epoch',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[bin2hex(random_bytes(16))]);query('UPDATE users SET auth_version=auth_version+1');
        $actorId=$actor&&scalar('SELECT COUNT(*) FROM users WHERE id=? AND username=?',[$actor['id'],$actor['username']])?$actor['id']:null;
        query("INSERT INTO activity_logs(user_id,full_name,activity_description,log_date,log_time,ip_address,action,entity) VALUES(?,?,?,CURRENT_DATE,CURRENT_TIME,?,'database_restore','backups')",[$actorId,$actor['full_name']??'CLI administrator','Restored and verified application database backup',$_SERVER['REMOTE_ADDR']??'CLI']);
    });return $recovery;
}
function zip_available():void{if(!class_exists('ZipArchive'))throw new DomainException('Enable the PHP Zip extension to use file backups and restoration.');}
function source_file_allowed(string $relative):bool{
    if($relative==='.htaccess'||$relative==='README.md'||$relative==='schema.sql')return true;
    if(preg_match('~^[a-z][a-z_]*\.php$~D',$relative))return is_file(dirname(__DIR__).'/'.$relative);
    if(str_starts_with($relative,'config/'))return in_array($relative,['config/.htaccess','config/production.example.php','config/database.php','config/connection.php'],true);
    return (bool)preg_match('~^(includes|assets|uploads|migrations|bin|docs)/[A-Za-z0-9_./-]+$~D',$relative)&&!preg_match('~(^|/)(\.env|private|backups|node_modules|\.git)(/|$)~i',$relative);
}
function safe_archive_path(string $name):string{
    if($name===''||strlen($name)>240||str_contains($name,'\\')||str_contains($name,':')||str_starts_with($name,'/')||str_contains($name,"\0")||preg_match('~(^|/)\.{1,2}(/|$)~',$name)||preg_match('~[^A-Za-z0-9_./-]~',$name))throw new DomainException('Unsafe archive path.');return $name;
}
function safe_uploaded_content(string $path,string $bytes):void{
    if(!preg_match('~^uploads/[A-Za-z0-9_-]+\.(png|jpg|jpeg|pdf|txt)$~iD',$path,$m))throw new DomainException('File restore permits only PNG, JPEG, PDF, and text files directly inside uploads/. Use the offline CLI for source restoration.');
    $ext=strtolower($m[1]);if(in_array($ext,['png','jpg','jpeg'],true)){$info=@getimagesizefromstring($bytes);if(!$info||!in_array($info['mime'],['image/png','image/jpeg'],true)||$info[0]>6000||$info[1]>6000)throw new DomainException('Invalid image in file backup.');}
    elseif($ext==='pdf'&&!str_starts_with($bytes,'%PDF-'))throw new DomainException('Invalid PDF in backup.');
    elseif($ext==='txt'&&(str_contains($bytes,"\0")||!mb_check_encoding($bytes,'UTF-8')))throw new DomainException('Invalid UTF-8 text file.');
}
function create_file_backup(string $kind='application'):string{
    zip_available();if(!in_array($kind,['application','files'],true))throw new DomainException('Invalid archive kind.');$root=dirname(__DIR__);$manifest=['application'=>'crystal-dds','version'=>backup_format_version(),'schema'=>backup_schema(),'kind'=>$kind,'created_at'=>gmdate('c'),'files'=>[]];
    $target=backup_directory().DIRECTORY_SEPARATOR.'backup_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).'.zip';$zip=new ZipArchive();if($zip->open($target,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create ZIP backup.');
    try{$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($iterator as $f){if(!$f->isFile()||$f->isLink())continue;$relative=str_replace('\\','/',substr($f->getPathname(),strlen($root)+1));if(!source_file_allowed($relative))continue;if($kind==='files'&&!preg_match('~^uploads/[A-Za-z0-9_-]+\.(png|jpg|jpeg|pdf|txt)$~iD',$relative))continue;$bytes=file_get_contents($f->getPathname());if($kind==='files')safe_uploaded_content($relative,$bytes);$manifest['files'][$relative]=['size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];$zip->addFromString($relative,$bytes);}$zip->addFromString('manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));$zip->close();chmod($target,0600);return $target;}catch(Throwable $e){$zip->close();@unlink($target);throw $e;}
}
function stage_file_backup(string $file,bool $allowSource=false):array{
    zip_available();if(filesize($file)>25*1024*1024)throw new DomainException('Archive exceeds 25 MB.');$zip=new ZipArchive();if($zip->open($file)!==true)throw new DomainException('Invalid ZIP archive.');$stage=backup_directory().DIRECTORY_SEPARATOR.'stage-'.bin2hex(random_bytes(10));mkdir($stage,0700);
    try{
        if($zip->numFiles>1500)throw new DomainException('Archive has too many entries.');$names=[];$total=0;
        for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$name=safe_archive_path($stat['name']);$key=strtolower($name);if(isset($names[$key]))throw new DomainException('Duplicate archive path.');$names[$key]=true;$zip->getExternalAttributesIndex($i,$opsys,$attr);if(($attr>>16&0170000)===0120000)throw new DomainException('Archive symlinks are forbidden.');$total+=$stat['size'];if($total>50*1024*1024||$stat['size']>10*1024*1024)throw new DomainException('Expanded archive is too large.');}
        $json=$zip->getFromName('manifest.json');if($json===false||strlen($json)>1024*1024)throw new DomainException('Archive manifest missing.');
        try{$manifest=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new DomainException('Invalid archive manifest.');}
        if(($manifest['application']??'')!=='crystal-dds'||($manifest['version']??0)!==backup_format_version()||($manifest['schema']??'')!==backup_schema()||!in_array($manifest['kind']??'',['files','application'],true)||!is_array($manifest['files']??null))throw new DomainException('Incompatible archive.');
        if($manifest['kind']==='application'&&!$allowSource)throw new DomainException('Application/source backups require the documented offline CLI restore with --allow-source-code. Browser restoration accepts file-only backups.');
        if(count($manifest['files'])+1!==$zip->numFiles)throw new DomainException('Archive contains unexpected entries.');
        foreach($manifest['files']as $path=>$expected){safe_archive_path($path);if(!source_file_allowed($path)||($manifest['kind']==='files'&&!str_starts_with($path,'uploads/')))throw new DomainException('Unexpected archive file.');$bytes=$zip->getFromName($path);if($bytes===false||strlen($bytes)!==$expected['size']||!hash_equals($expected['sha256'],hash('sha256',$bytes)))throw new DomainException('Archive integrity check failed.');if($manifest['kind']==='files')safe_uploaded_content($path,$bytes);$dest=$stage.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);file_put_contents($dest,$bytes);}
        return ['directory'=>$stage,'manifest'=>$manifest];
    }catch(Throwable $e){remove_staging_directory($stage);throw $e;}finally{$zip->close();}
}
function remove_staging_directory(string $dir):void{
    $base=realpath(backup_directory());$real=realpath($dir);if(!$real||!str_starts_with(strtolower($real),strtolower($base.DIRECTORY_SEPARATOR))||!preg_match('/^(stage|rollback)-[a-f0-9]+$/',basename($real)))throw new RuntimeException('Refusing unsafe staging cleanup.');
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isLink()||$f->isFile())unlink($f->getPathname());else rmdir($f->getPathname());}rmdir($real);
}
function apply_staged_files(array $stage,bool $allowSource=false):string{
    $root=realpath(dirname(__DIR__));$manifest=$stage['manifest'];if($manifest['kind']==='application'&&!$allowSource)throw new DomainException('Source restore requires offline authorization.');$staged=realpath($stage['directory']);if(!$staged||!str_starts_with(strtolower($staged),strtolower(backup_directory().DIRECTORY_SEPARATOR.'stage-')))throw new DomainException('Invalid restoration stage.');
    $rollback=backup_directory().DIRECTORY_SEPARATOR.'rollback-'.bin2hex(random_bytes(10));mkdir($rollback,0700);$written=[];$new=[];
    try{foreach($manifest['files']as $path=>$expected){safe_archive_path($path);$from=$staged.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);$bytes=file_get_contents($from);if(!hash_equals($expected['sha256'],hash('sha256',$bytes)))throw new DomainException('Staged file changed.');if($manifest['kind']==='files')safe_uploaded_content($path,$bytes);$dest=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);$parent=dirname($dest);while(!is_dir($parent))$parent=dirname($parent);$resolved=realpath($parent);if(!str_starts_with(strtolower($resolved.DIRECTORY_SEPARATOR),strtolower($root.DIRECTORY_SEPARATOR))||is_link($dest))throw new DomainException('Unsafe destination.');
            if(is_file($dest)){$old=$rollback.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);if(!is_dir(dirname($old)))mkdir(dirname($old),0700,true);if(!copy($dest,$old))throw new RuntimeException('Cannot preserve rollback file.');}else$new[]=$path;
            if(!is_dir(dirname($dest)))mkdir(dirname($dest),0750,true);$temp=$dest.'.restore-'.bin2hex(random_bytes(4));if(file_put_contents($temp,$bytes)===false)throw new RuntimeException('Cannot stage replacement.');if(!rename($temp,$dest)){@unlink($temp);throw new RuntimeException('Cannot replace file.');}$written[]=$path;if(!hash_equals($expected['sha256'],hash_file('sha256',$dest)))throw new RuntimeException('Restored file verification failed.');
        }audit('files_restore','backups',null,'Restored and verified '.$manifest['kind'].' archive',null,['files'=>count($written)]);return $rollback;
    }catch(Throwable $e){foreach(array_reverse($written)as $path){$dest=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);$old=$rollback.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);if(is_file($old))copy($old,$dest);elseif(in_array($path,$new,true))@unlink($dest);}throw $e;}
}
