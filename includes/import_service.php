<?php
require_once __DIR__.'/records_service.php';require_once __DIR__.'/csv.php';
function import_templates():array{return [
 'students'=>['student_number','full_name','course_id','section_id','year_level','academic_status','honorable_dismissal_on'],
 'enrollments'=>['offering_id','student_id','status'],
 'grades'=>['offering_id','student_id','midterm','final','result_type','inc_reason','reason']
];}
function read_import_csv(string $file,string $kind):array{
    $expected=import_templates()[$kind]??throw new DomainException('Invalid import type.');$fp=fopen($file,'rb');$headers=fgetcsv($fp,0,',','"','');if(!$headers)throw new DomainException('The CSV is empty.');$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]);if($headers!==$expected)throw new DomainException('CSV headers must exactly match the downloaded template.');$data=[];
    while(($line=fgetcsv($fp,0,',','"',''))!==false){if($line===[null])continue;if(count($line)!==count($headers))throw new DomainException('Column count mismatch at CSV row '.(count($data)+2));$data[]=array_combine($headers,$line);if(count($data)>500)throw new DomainException('Import at most 500 rows per batch.');}fclose($fp);if(!$data)throw new DomainException('The CSV contains no records.');return $data;
}
function import_one(string $kind,array $data):int{require_staff();if($kind==='students'&&!is_admin())deny();return $kind==='grades'?save_grade($data):save_record($kind,$data);}
function preview_import(string $kind,array $data):array{require_staff();
    if(!isset(import_templates()[$kind]))throw new DomainException('Invalid import type.');if($kind==='students'&&!is_admin())deny();
    $errors=[];$seen=[];db()->beginTransaction();try{foreach($data as $i=>$r){$key=match($kind){'students'=>$r['student_number'],default=>$r['offering_id'].':'.$r['student_id']};if(isset($seen[$key])){$errors[$i+2]='Duplicate record within this file.';continue;}$seen[$key]=true;query('SAVEPOINT import_row');try{import_one($kind,$r);}catch(DomainException|PDOException $e){$errors[$i+2]=$e instanceof PDOException?'Duplicate record or invalid relationship.':$e->getMessage();}finally{query('ROLLBACK TO SAVEPOINT import_row');}}}finally{db()->rollBack();}return $errors;
}
function commit_import(string $kind,array $data):int{require_staff();return transaction(function()use($kind,$data){foreach($data as $r)import_one($kind,$r);audit('bulk_import',$kind,null,'Imported '.count($data).' records',null,['rows'=>count($data)]);return count($data);});}
