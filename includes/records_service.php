<?php
function entity_definitions():array{return [
 'courses'=>['title'=>'Courses / programs','admin'=>true,'fields'=>['code'=>['Code','text',30],'name'=>['Program name','text',120],'is_active'=>['Active','bool']]],
 'school_years'=>['title'=>'School years','admin'=>true,'fields'=>['name'=>['School year','text',40],'starts_on'=>['Starts on','date'],'ends_on'=>['Ends on','date'],'is_active'=>['Active','bool']]],
 'terms'=>['title'=>'Academic terms','admin'=>true,'fields'=>['school_year_id'=>['School year','ref','school_years'],'name'=>['Term name','text',40],'starts_on'=>['Starts on','date'],'ends_on'=>['Ends on','date'],'submission_deadline'=>['Submission deadline','date'],'inc_deadline'=>['INC deadline (following academic calendar)','date'],'is_active'=>['Active','bool']]],
 'sections'=>['title'=>'Sections','admin'=>true,'fields'=>['course_id'=>['Program','ref','courses'],'name'=>['Section name','text',60],'year_level'=>['Year level','number',1,8],'is_active'=>['Active','bool']]],
 'subjects'=>['title'=>'Subjects','admin'=>true,'fields'=>['subject_code'=>['Subject code','text',30],'title'=>['Subject title','text',150],'units'=>['Credit units','number',1,12],'is_academic'=>['Academic subject','bool'],'is_active'=>['Active','bool']]],
 'students'=>['title'=>'Students','admin'=>false,'fields'=>['student_number'=>['Student number','text',40],'full_name'=>['Full name','text',150],'course_id'=>['Program','ref','courses'],'section_id'=>['Section','ref','sections'],'year_level'=>['Year level','number',1,8],'academic_status'=>['Academic status','enum',['Active','Inactive','Graduated','On Leave']],'honorable_dismissal_on'=>['Honorable dismissal date (optional)','optional_date']]],
 'grading_policies'=>['title'=>'Grading policy versions','admin'=>true,'fields'=>['name'=>['Unique policy version name','text',100],'midterm_weight'=>['Midterm weight % (final = remaining %)','decimal',0,100],'rounding'=>['Percentage conversion','enum',['floor','nearest']],'repeat_rule'=>['Repeated-subject GWA rule','enum',['all','latest','best']],'include_nonacademic'=>['Include nonacademic subjects in GWA','bool'],'is_approved'=>['Institutionally reviewed / approved','bool'],'notes'=>['Approval reference / policy assumptions','text',1000]]],
 'offerings'=>['title'=>'Subject offerings / faculty assignments','admin'=>false,'fields'=>['subject_id'=>['Subject','ref','subjects','title'],'term_id'=>['Term','ref','terms'],'section_id'=>['Section','ref','sections'],'faculty_id'=>['Assigned faculty','faculty'],'policy_id'=>['Grading policy version','ref','grading_policies'],'is_active'=>['Active','bool']]],
 'enrollments'=>['title'=>'Enrollment','admin'=>false,'fields'=>['offering_id'=>['Subject offering','offering'],'student_id'=>['Student','student'],'status'=>['Enrollment status','enum',['Enrolled','Completed','Withdrawn']]]],
 'interventions'=>['title'=>'Academic interventions','admin'=>false,'fields'=>['offering_id'=>['Subject offering','offering'],'student_id'=>['Student','student'],'adviser_id'=>['Responsible faculty','faculty'],'reason'=>['Intervention reason','text',1000],'follow_up_on'=>['Follow-up date','date'],'notes'=>['Follow-up notes / actions','text',5000],'status'=>['Status','enum',['Open','In Progress','Resolved']],'is_active'=>['Active','bool']]],
];}
function entity_definition(string $entity):array{return entity_definitions()[$entity]??throw new DomainException('Unknown record type.');}
function record_scope(string $entity):array{require_staff();
    if(is_admin())return ['1=1',[]];$uid=(int)current_user()['id'];
    return match($entity){
        'students'=>['EXISTS(SELECT 1 FROM enrollments e JOIN offerings o ON o.id=e.offering_id WHERE e.student_id=r.id AND o.faculty_id=? AND o.needs_review=0)',[$uid]],
        'offerings'=>['r.faculty_id=? AND r.needs_review=0',[$uid]],
        'enrollments','interventions'=>['EXISTS(SELECT 1 FROM offerings o WHERE o.id=r.offering_id AND o.faculty_id=? AND o.needs_review=0)',[$uid]],
        'subjects'=>['EXISTS(SELECT 1 FROM offerings o WHERE o.subject_id=r.id AND o.faculty_id=? AND o.needs_review=0)',[$uid]],
        default=>['0=1',[]]
    };
}
function allowed_record(string $entity,int $id,bool $lock=false):array{[$scope,$params]=record_scope($entity);$r=row("SELECT r.* FROM `$entity` r WHERE r.id=? AND $scope".($lock?' FOR UPDATE':''),array_merge([$id],$params));if(!$r)deny();return $r;}
function record_options(array $field):array{return match($field[1]){
    'bool'=>[1=>'Yes',0=>'No'],'enum'=>array_combine($field[2],$field[2]),
    'ref'=>choices($field[2],$field[3]??'name'),
    'faculty'=>choices('users','full_name',"role='Faculty' AND is_active=1 AND registration_status='Approved'"),
    'offering'=>offerings_choices(),
    'student'=>is_admin()?choices('students','full_name'):array_column(rows('SELECT DISTINCT s.id,s.full_name FROM students s JOIN enrollments e ON e.student_id=s.id JOIN offerings o ON o.id=e.offering_id WHERE o.faculty_id=? AND o.needs_review=0 ORDER BY s.full_name',[current_user()['id']]),'full_name','id'),
    default=>[]};}
function parse_record_values(string $entity,array $input):array{
    $def=entity_definition($entity);$values=[];
    foreach($def['fields']as $key=>$f){$type=$f[1];
        if($type==='text')$values[$key]=text_value($input,$key,$f[2],$key!=='notes');
        elseif($type==='date')$values[$key]=date_value($input,$key);
        elseif($type==='optional_date'){$v=request_string($input,$key);if($v!==''&&!valid_date($v))throw new DomainException('Enter a valid date.');$values[$key]=$v?:null;}
        elseif(in_array($type,['number','decimal'],true)){$v=filter_var($input[$key]??null,$type==='number'?FILTER_VALIDATE_INT:FILTER_VALIDATE_FLOAT);if($v===false||$v===null||$v<$f[2]||$v>$f[3])throw new DomainException('Invalid '.$f[0]);$values[$key]=$v;}
        else{$opts=record_options($f);$v=request_string($input,$key);if(!array_key_exists($v,$opts))throw new DomainException('Choose a valid '.$f[0]);$values[$key]=in_array($type,['enum'],true)?$v:(int)$v;}
    }return $values;
}
function save_record(string $entity,array $input):int{require_staff();return transaction(function()use($entity,$input){
    $def=entity_definition($entity);$id=(int)($input['id']??0);$before=$id?allowed_record($entity,$id,true):null;
    if(!is_admin()&&($def['admin']||$entity==='offerings'||($entity==='students'&&!$id)))deny();
    if($entity==='grading_policies'&&$id)throw new DomainException('Policies are immutable. Create a new version instead.');
    if(in_array($entity,['enrollments','interventions'],true))offering(positive_id($input['offering_id']??null),true);
    $v=parse_record_values($entity,$input);
    if(isset($v['starts_on'])&&$v['starts_on']>$v['ends_on'])throw new DomainException('Start date must precede the end date.');
    if($entity==='terms'){
        $year=row('SELECT * FROM school_years WHERE id=?',[$v['school_year_id']]);
        if($v['starts_on']<$year['starts_on']||$v['ends_on']>$year['ends_on']||$v['inc_deadline']<=$v['ends_on']||$v['submission_deadline']<$v['starts_on'])throw new DomainException('Term dates must fit the school year; INC deadline must follow term end; submission deadline must follow term start.');
        if($before&&scalar('SELECT COUNT(*) FROM grades g JOIN offerings o ON o.id=g.offering_id WHERE o.term_id=? AND g.remarks="INC"',[$id])&&$v['inc_deadline']!==$before['inc_deadline'])throw new DomainException('Existing INC deadlines are snapshots. Keep this calendar deadline and create a new term for future rules.');
    }
    if($entity==='school_years'&&$before&&scalar('SELECT COUNT(*) FROM terms WHERE school_year_id=? AND (starts_on<? OR ends_on>?)',[$id,$v['starts_on'],$v['ends_on']]))throw new DomainException('Existing term dates must remain inside this school year.');
    if($entity==='students'){
        $section=row('SELECT * FROM sections WHERE id=?',[$v['section_id']]);if((int)$section['course_id']!==$v['course_id']||(int)$section['year_level']!==$v['year_level'])throw new DomainException('Student program and year must match the selected section.');
        $v['course']=(string)scalar('SELECT name FROM courses WHERE id=?',[$v['course_id']]);
    }
    if($entity==='offerings'){
        $hasGrades=$before&&(int)scalar('SELECT COUNT(*) FROM grades WHERE offering_id=?',[$id]);
        if($hasGrades){foreach(['subject_id','term_id','section_id','policy_id']as $key)if($before[$key]!==null&&(int)$before[$key]!==$v[$key])throw new DomainException('An offering with grades cannot change its subject, term, section, or grading policy. Create another offering.');}
        if($before&&$before['needs_review']&&!isset($input['review_confirm']))throw new DomainException('Confirm that the legacy records belong to the selected term, section, and faculty.');
        $v['needs_review']=0;
        if($before&&((int)$before['faculty_id']!==$v['faculty_id']||$before['needs_review']))text_value($input,'reason',1000);
    }
    if(in_array($entity,['enrollments','interventions'],true)){
        $o=offering($v['offering_id'],true);usable_offering($o);visible_student($v['student_id']);
        if($before&&((int)$before['student_id']!==$v['student_id']||(int)$before['offering_id']!==$v['offering_id']))throw new DomainException('Keep the original student and offering. Create another record instead.');
        if($entity==='enrollments'){
            $s=row('SELECT * FROM students WHERE id=?',[$v['student_id']]);if($s['academic_status']!=='Active'||$s['honorable_dismissal_on'])throw new DomainException('Only active students without honorable dismissal can be enrolled.');
            if($v['status']!=='Enrolled'&&scalar('SELECT COUNT(*) FROM grades WHERE offering_id=? AND student_id=?',[$v['offering_id'],$v['student_id']]))throw new DomainException('An enrollment with grade history cannot be withdrawn or completed through CRUD. Retain it for academic records.');
        }else{
            if(!scalar('SELECT COUNT(*) FROM enrollments WHERE student_id=? AND offering_id=?',[$v['student_id'],$v['offering_id']]))throw new DomainException('The student must belong to this offering.');
            if($v['adviser_id']!==(int)$o['faculty_id'])throw new DomainException('Choose the faculty member currently responsible for this offering. Reassign the offering first if another adviser should manage it.');
        }
    }
    $columns=array_keys($v);
    if($id){$set=implode(',',array_map(fn($k)=>"`$k`=?",$columns));query("UPDATE `$entity` SET $set WHERE id=?",array_merge(array_values($v),[$id]));}
    else{query("INSERT INTO `$entity` (`".implode('`,`',$columns).'`) VALUES('.implode(',',array_fill(0,count($v),'?')).')',array_values($v));$id=(int)db()->lastInsertId();}
    $action=$before?'record_edit':'record_create';$offeringId=$entity==='offerings'?$id:($v['offering_id']??null);
    audit($action,$entity,$id,($before?'Updated ':'Created ').$def['title'].': '.request_string($input,'reason'),$before,$v,$offeringId);
    if($entity==='offerings')notify($v['faculty_id'],'Subject offering #'.$id.' assigned/updated',$id);
    if($entity==='interventions')notify($v['adviser_id'],'Intervention #'.$id.' — '.$v['status'],$v['offering_id']);
    return $id;
});}
function remove_record(string $entity,int $id):void{require_staff();transaction(function()use($entity,$id){
    $r=allowed_record($entity,$id,true);if(!is_admin()&&!in_array($entity,['enrollments','interventions'],true))deny();
    if($entity==='grading_policies')throw new DomainException('Grading policies are retained permanently.');
    if(array_key_exists('is_active',$r)){query("UPDATE `$entity` SET is_active=0 WHERE id=?",[$id]);$action='record_archive';}
    elseif($entity==='students'){query("UPDATE students SET academic_status='Inactive' WHERE id=?",[$id]);$action='record_archive';}
    elseif($entity==='enrollments'){
        if(scalar('SELECT COUNT(*) FROM grades WHERE student_id=? AND offering_id=?',[$r['student_id'],$r['offering_id']]))throw new DomainException('Cannot delete enrollment with grade history.');query('DELETE FROM enrollments WHERE id=?',[$id]);$action='record_delete';
    }else throw new DomainException('This historical record cannot be deleted.');
    audit($action,$entity,$id,'Archived / removed record',$r,null,$r['offering_id']??($entity==='offerings'?$id:null));
});}
