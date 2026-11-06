<?php
function grade_filters(array $input,bool $publishedDefault=false):array{require_staff();
    [$scope,$params]=scope_sql();$where=[$scope];$q=trim(request_string($input,'q'));
    if($q!==''){$where[]='(s.full_name LIKE ? OR s.student_number LIKE ? OR su.subject_code LIKE ?)';array_push($params,'%'.$q.'%','%'.$q.'%','%'.$q.'%');}
    $mapping=['student_id'=>'g.student_id','offering_id'=>'g.offering_id','course_id'=>'s.course_id','year_level'=>'s.year_level','section_id'=>'o.section_id','subject_id'=>'o.subject_id','faculty_id'=>'o.faculty_id','school_year_id'=>'t.school_year_id','term_id'=>'o.term_id'];
    foreach($mapping as $key=>$column)if(request_string($input,$key)!==''){$where[]="$column=?";$params[]=positive_id($input[$key]);}
    $workflow=request_string($input,'workflow')?:($publishedDefault?'Published':'All');
    if(!in_array($workflow,['All','Draft','Submitted','Approved','Published'],true))throw new DomainException('Invalid workflow filter.');
    if($workflow!=='All'){$where[]='g.workflow=?';$params[]=$workflow;}
    if($workflow==='Published')$where[]='g.migration_review=0';
    $status=request_string($input,'status');if($status!==''){if(!in_array($status,['Passed','Failed','INC','At-Risk'],true))throw new DomainException('Invalid grade status.');$where[]='g.remarks=?';$params[]=$status==='At-Risk'?'Failed':$status;if($status==='At-Risk')$where[]="s.academic_status='Active'";}
    $dates=date_filters($input);if(isset($dates['from'])){$where[]='g.created_at>=?';$params[]=$dates['from'].' 00:00:00';}if(isset($dates['to'])){$where[]='g.created_at<=?';$params[]=$dates['to'].' 23:59:59';}
    if(request_string($input,'migration_review')==='1')$where[]='g.migration_review=1';
    return [implode(' AND ',$where),$params];
}
function grade_select():string{return 'SELECT g.*,s.student_number,s.full_name,s.course,s.year_level,s.academic_status,su.subject_code,su.title,o.faculty_id,o.term_id,o.needs_review,t.name AS term_name,t.starts_on AS term_start,se.name AS section_name,y.name AS school_year FROM grades g JOIN students s ON s.id=g.student_id JOIN subjects su ON su.id=g.subject_id JOIN offerings o ON o.id=g.offering_id LEFT JOIN terms t ON t.id=o.term_id LEFT JOIN sections se ON se.id=o.section_id LEFT JOIN school_years y ON y.id=t.school_year_id';}
function grade_filter_form(array $values):void{
    input_field('q','Student / subject keyword',$values['q']??'','search',false);
    $maps=['course_id'=>['Program',choices('courses')],'school_year_id'=>['School year',choices('school_years')],'term_id'=>['Term',choices('terms')],'offering_id'=>['Subject offering',offerings_choices()],'section_id'=>['Section',choices('sections')],'subject_id'=>['Subject',choices('subjects','title')]];
    if(is_admin())$maps['faculty_id']=['Faculty',choices('users','full_name',"role='Faculty'")];
    foreach($maps as $key=>[$label,$opts])select_field($key,$label,$opts,$values[$key]??'',false);
    input_field('student_id','Student ID (optional)',$values['student_id']??'','number',false,'min="1"');
    select_field('year_level','Year level',array_combine(range(1,8),range(1,8)),$values['year_level']??'',false);
    select_field('workflow','Workflow',array_combine(['All','Draft','Submitted','Approved','Published'],['All','Draft','Submitted','Approved','Published']),$values['workflow']??'All',false);
    select_field('status','Grade status',array_combine(['Passed','Failed','INC','At-Risk'],['Passed','Failed','INC','At-Risk']),$values['status']??'',false);
    input_field('from','Created from',$values['from']??'','date',false);input_field('to','Created to',$values['to']??'','date',false);
}
function audit_filters(array $input):array{require_staff();
    $where=['1=1'];$params=[];$q=trim(request_string($input,'q'));if($q!==''){$where[]='(a.full_name LIKE ? OR a.activity_description LIKE ?)';array_push($params,'%'.$q.'%','%'.$q.'%');}
    foreach(['action','entity']as $key)if(request_string($input,$key)!==''){$where[]="a.`$key`=?";$params[]=text_value($input,$key,50);}
    $dates=date_filters($input);if(isset($dates['from'])){$where[]='a.log_date>=?';$params[]=$dates['from'];}if(isset($dates['to'])){$where[]='a.log_date<=?';$params[]=$dates['to'];}
    return [implode(' AND ',$where),$params];
}
