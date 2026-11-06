<?php
require_once __DIR__.'/filters.php';
function report_types():array{return ['performance'=>'Filtered performance report','transactions'=>'Grade transaction report','users'=>'User report','activity'=>'Activity report','summary'=>'Academic summary','grade_card'=>'Student grade card','academic_history'=>'Student academic history','semester'=>'Semester summary','class_record'=>'Faculty class record','subject_performance'=>'Subject performance','interventions'=>'Intervention report'];}
function readable_report_filters(array $filters):string{
    $parts=[];$labels=['q'=>'Keyword','student_id'=>'Student ID','offering_id'=>'Class','course_id'=>'Program','year_level'=>'Year level','section_id'=>'Section','subject_id'=>'Subject','faculty_id'=>'Faculty','school_year_id'=>'School year','term_id'=>'Term','workflow'=>'Workflow','status'=>'Grade status','from'=>'From','to'=>'To','role'=>'Account role','is_active'=>'Account active','action'=>'Transaction type','entity'=>'Category','intervention_status'=>'Intervention status','gwa_policy_id'=>'GWA policy'];
    $references=['offering_id'=>offerings_choices(),'course_id'=>choices('courses'),'section_id'=>choices('sections'),'subject_id'=>choices('subjects','title'),'school_year_id'=>choices('school_years'),'term_id'=>choices('terms'),'gwa_policy_id'=>choices('grading_policies')];if(is_admin())$references['faculty_id']=choices('users','full_name');
    foreach($labels as $key=>$label){$value=$filters[$key]??'';if($value==='')continue;if($key==='is_active')$value=$value==='1'?'Yes':'No';elseif(isset($references[$key]))$value=$references[$key][$value]??('ID '.$value);$parts[]=$label.': '.$value;}
    return $parts?implode(' · ',$parts):'All authorized records';
}
function build_report(string $type,array $filters):array{require_staff();
    if(!isset(report_types()[$type]))throw new DomainException('Choose a valid report.');
    if(in_array($type,['users','activity'],true)&&!is_admin())deny();
    if(in_array($type,['grade_card','academic_history'],true)&&request_string($filters,'student_id')==='')throw new DomainException('Choose a student ID for this report.');
    if($type==='class_record'&&request_string($filters,'offering_id')==='')throw new DomainException('Choose a subject offering for a class record.');
    if($type==='semester'&&request_string($filters,'term_id')==='')throw new DomainException('Choose an academic term for a semester summary.');
    $dates=date_filters($filters);$data=[];$columns=[];$summary=[];
    if($type==='users'){
        $where=['1=1'];$params=[];foreach(['role'=>['Administrator','Faculty'],'is_active'=>['0','1']]as $key=>$allowed){$v=request_string($filters,$key);if($v!==''){if(!in_array($v,$allowed,true))throw new DomainException('Invalid account filter.');$where[]="$key=?";$params[]=$v;}}
        if(request_string($filters,'q')!==''){$where[]='(full_name LIKE ? OR username LIKE ?)';array_push($params,'%'.$filters['q'].'%','%'.$filters['q'].'%');}
        foreach(['from'=>'>=','to'=>'<=']as $key=>$op)if(isset($dates[$key])){$where[]="DATE(created_at)$op?";$params[]=$dates[$key];}
        $data=rows('SELECT id,full_name,username,role,is_active,registration_status,created_at FROM users WHERE '.implode(' AND ',$where).' ORDER BY full_name',$params);$columns=['id'=>'ID','full_name'=>'Full name','username'=>'Username','role'=>'Role','is_active'=>'Active','registration_status'=>'Approval','created_at'=>'Created'];
    }elseif(in_array($type,['activity','transactions'],true)){
        [$where,$params]=audit_filters($filters);
        if($type==='transactions'){$where.=" AND a.action LIKE 'grade_%'";if(!is_admin()){$where.=' AND EXISTS(SELECT 1 FROM offerings o WHERE o.id=a.offering_id AND o.faculty_id=? AND o.needs_review=0)';$params[]=current_user()['id'];}}
        $data=rows("SELECT a.user_id,a.full_name,a.action,a.entity,a.record_id,a.activity_description,a.log_date,a.log_time,a.ip_address FROM activity_logs a WHERE $where ORDER BY a.id DESC",$params);$columns=['user_id'=>'User ID','full_name'=>'Actor','action'=>'Transaction type','entity'=>'Category','record_id'=>'Record','activity_description'=>'Activity','log_date'=>'Date','log_time'=>'Time','ip_address'=>'IP address'];
    }elseif($type==='interventions'){
        [$scope,$params]=scope_sql();$where=[$scope];foreach(['student_id'=>'i.student_id','offering_id'=>'i.offering_id','term_id'=>'o.term_id','course_id'=>'s.course_id','section_id'=>'o.section_id']as $key=>$col)if(request_string($filters,$key)!==''){$where[]="$col=?";$params[]=positive_id($filters[$key]);}
        $status=request_string($filters,'intervention_status');if($status!==''){if(!in_array($status,['Open','In Progress','Resolved'],true))throw new DomainException('Invalid intervention status.');$where[]='i.status=?';$params[]=$status;}
        foreach(['from'=>'>=','to'=>'<=']as $key=>$op)if(isset($dates[$key])){$where[]="i.follow_up_on$op?";$params[]=$dates[$key];}
        $data=rows('SELECT s.student_number,s.full_name,u.full_name AS adviser,su.subject_code,i.reason,i.follow_up_on,i.status,i.notes FROM interventions i JOIN students s ON s.id=i.student_id JOIN offerings o ON o.id=i.offering_id JOIN subjects su ON su.id=o.subject_id JOIN users u ON u.id=i.adviser_id WHERE '.implode(' AND ',$where).' ORDER BY i.follow_up_on',$params);$columns=['student_number'=>'Student number','full_name'=>'Student','subject_code'=>'Subject','adviser'=>'Responsible faculty','reason'=>'Reason','follow_up_on'=>'Follow-up','status'=>'Status','notes'=>'Actions taken'];
    }else{
        [$where,$params]=grade_filters($filters,true);$data=rows(grade_select()." WHERE $where ORDER BY s.full_name,t.starts_on,su.subject_code",$params);
        $p=policy(positive_id(($filters['gwa_policy_id']??'')?:setting('gwa_policy_id','1')));$summary=['Records'=>count($data),'Published weighted average'=>weighted_average($data,$p)??'N/A','GWA interpretation'=>$p['name'].'; repeats: '.$p['repeat_rule'].'; nonacademic: '.($p['include_nonacademic']?'included':'excluded').'; INC and unreviewed legacy excluded'];
        $columns=['student_number'=>'Student no.','full_name'=>'Student','course'=>'Program','year_level'=>'Year','section_name'=>'Section','subject_code'=>'Subject','school_year'=>'School year','term_name'=>'Term','units_snapshot'=>'Units','midterm'=>'Midterm','final'=>'Final','average'=>'Percentage','gpa'=>'ISU grade','remarks'=>'Result','workflow'=>'Workflow','migration_review'=>'Legacy review'];
        if(in_array($type,['summary','semester','subject_performance'],true)){
            $groups=[];foreach($data as $r){$key=$type==='subject_performance'?$r['subject_code']:($r['school_year'].' · '.$r['term_name'].' · '.$r['course']);$groups[$key][]=$r;}
            $data=[];foreach($groups as $label=>$records){$data[]=['group'=>$label,'records'=>count($records),'passed'=>count(array_filter($records,fn($r)=>$r['remarks']==='Passed')),'failed'=>count(array_filter($records,fn($r)=>$r['remarks']==='Failed')),'inc'=>count(array_filter($records,fn($r)=>$r['remarks']==='INC')),'gwa'=>weighted_average($records,$p)??'N/A'];}
            $columns=['group'=>'Group','records'=>'Grade records','passed'=>'Passed','failed'=>'Failed','inc'=>'INC','gwa'=>'Published weighted average'];
        }
    }
    if(count($data)>10000)throw new DomainException('This report exceeds 10,000 rows. Choose a narrower date range or class filter.');
    return ['title'=>report_types()[$type],'data'=>$data,'columns'=>$columns,'summary'=>$summary,'filters'=>$filters,'generated_at'=>date('Y-m-d H:i:s').' Asia/Manila','prepared_by'=>current_user()['full_name'],'official'=>!in_array($type,['users','activity','transactions','interventions'],true)&&($filters['workflow']??'Published')==='Published'];
}
