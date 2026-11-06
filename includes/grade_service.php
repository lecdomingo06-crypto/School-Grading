<?php
declare(strict_types=1);
function grade_record(int $id,bool $lock=false):array{require_staff();
    $g=row('SELECT * FROM grades WHERE id=?'.($lock?' FOR UPDATE':''),[$id]);if(!$g)throw new DomainException('Grade not found.');offering((int)$g['offering_id']);return $g;
}
function grade_history(int $id,string $action,string $reason,?array $before):void{
    $after=row('SELECT * FROM grades WHERE id=?',[$id]);$o=(int)($after['offering_id']??$before['offering_id']);
    query('INSERT INTO grade_history(grade_id,actor_id,action,reason,before_data,after_data) VALUES(?,?,?,?,?,?)',[$id,current_user()['id']??null,$action,$reason,safe_snapshot($before),safe_snapshot($after)]);
    audit($action,'grades',$id,$reason?:"Grade $action",$before,$after,$o);
}
function grade_editable(array $g):void{
    if($g['workflow']!=='Draft')throw new DomainException('Only draft grades can be edited. Request a return or authorized reopening.');
    if($g['migration_review'])throw new DomainException('An administrator must review and recalculate this legacy record first.');
}
function revision_check(array $g,array $input):void{if((int)($input['revision']??0)!==(int)$g['revision'])throw new DomainException('This record changed. Reload before saving.');}
function usable_offering(array $o):void{
    if(!$o['is_active']||!$o['term_active']||$o['needs_review']||!$o['policy_id'])throw new DomainException('The offering must be active, assigned, and reviewed before grade entry.');
}
function mark_value(array $input,string $key):float{ $v=filter_var($input[$key]??null,FILTER_VALIDATE_FLOAT);if($v===false||$v===null||!is_finite((float)$v)||$v<0||$v>100)throw new DomainException('Enter numeric marks between 0 and 100.');return round((float)$v,2); }
function save_grade(array $input):int{require_staff();return transaction(function()use($input){
    $id=(int)($input['id']??0);$before=$id?grade_record($id,true):null;
    if($before){revision_check($before,$input);grade_editable($before);}
    $offeringId=$before?(int)$before['offering_id']:positive_id($input['offering_id']??null);
    $o=offering($offeringId,true);usable_offering($o);
    $studentId=$before?(int)$before['student_id']:positive_id($input['student_id']??null);
    if(!scalar("SELECT COUNT(*) FROM enrollments WHERE offering_id=? AND student_id=? AND status='Enrolled'",[$offeringId,$studentId]))throw new DomainException('The student must have an active enrollment in this offering.');
    $p=policy((int)($before['policy_id']??$o['policy_id']));
    $midterm=mark_value($input,'midterm');$final=mark_value($input,'final');$calc=grade_values($midterm,$final,$p);
    $inc=request_string($input,'result_type')==='INC';$reason=text_value($input,'reason',1000,$before!==null);
    $incReason=null;$issued=null;$deadline=null;$completion=$before['completion_status']??'None';$reference=$before['completion_reference']??null;
    if($inc){
        if($calc['remarks']!=='Passed')throw new DomainException('INC requires passing class standing. Enter the passing standing marks and unmet requirements.');
        $incReason=text_value($input,'inc_reason',1000);$issued=$before['inc_issued_on']??date('Y-m-d');$deadline=$before['inc_deadline']??$o['inc_deadline'];
        if(!$deadline||$deadline<$issued)throw new DomainException('Configure a future academic-calendar completion deadline before issuing INC.');
        $completion='Pending';$calc['gpa']=null;$calc['remarks']='INC';
    }elseif(($before['remarks']??'')==='INC'){
        if($before['inc_deadline']<date('Y-m-d'))throw new DomainException('The INC completion period has expired. Run the expiration process.');
        $s=visible_student($studentId);
        if($s['honorable_dismissal_on']||$s['academic_status']!=='Active'||!scalar("SELECT COUNT(*) FROM enrollments e JOIN offerings o ON o.id=e.offering_id JOIN terms t ON t.id=o.term_id WHERE e.student_id=? AND e.status='Enrolled' AND t.starts_on<=CURRENT_DATE AND t.ends_on>=CURRENT_DATE",[$studentId]))throw new DomainException('Completion requires current official enrollment, active student status, and no honorable dismissal.');
        $reference=text_value($input,'completion_reference',190);$completion='Completed';$incReason=$before['inc_reason'];$issued=$before['inc_issued_on'];$deadline=$before['inc_deadline'];
    }
    if($before){query('UPDATE grades SET midterm=?,final=?,average=?,gpa=?,remarks=?,entered_by=?,inc_reason=?,inc_issued_on=?,inc_deadline=?,completion_status=?,completion_reference=?,revision=revision+1 WHERE id=?',[$midterm,$final,$calc['average'],$calc['gpa'],$calc['remarks'],current_user()['id'],$incReason,$issued,$deadline,$completion,$reference,$id]);}
    else{query('INSERT INTO grades(student_id,subject_id,academic_term,midterm,final,average,gpa,remarks,entered_by,offering_id,policy_id,units_snapshot,academic_snapshot,migration_review,inc_reason,inc_issued_on,inc_deadline,completion_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?)',[$studentId,$o['subject_id'],$o['term_name'],$midterm,$final,$calc['average'],$calc['gpa'],$calc['remarks'],current_user()['id'],$offeringId,$p['id'],$o['units'],$o['is_academic'],$incReason,$issued,$deadline,$completion]);$id=(int)db()->lastInsertId();}
    grade_history($id,$before?'grade_edit':'grade_create',$reason?:'Initial grade entry',$before);return $id;
});}
function transition_grade(int $id,string $action,string $reason,int $revision):void{require_staff();transaction(function()use($id,$action,$reason,$revision){
    $g=grade_record($id,true);revision_check($g,['revision'=>$revision]);$o=offering((int)$g['offering_id'],true);
    if($g['migration_review'])throw new DomainException('Review and recalculate the legacy grade before workflow actions.');
    $target=null;
    if($action==='submit'){
        grade_editable($g);usable_offering($o);
        if(date('Y-m-d')>$o['submission_deadline']&&!$g['correction_open'])throw new DomainException('Submission deadline passed. An administrator must reopen the record with a reason.');
        if(!policy((int)$g['policy_id'])['is_approved'])throw new DomainException('The grading policy must be institutionally reviewed and approved before submission.');
        $target='Submitted';
    }else{
        if(!is_admin())deny();
        if($action==='approve'&&$g['workflow']==='Submitted')$target='Approved';
        elseif($action==='publish'&&$g['workflow']==='Approved')$target='Published';
        elseif($action==='return'&&in_array($g['workflow'],['Submitted','Approved'],true))$target='Draft';
        elseif($action==='reopen'&&in_array($g['workflow'],['Draft','Submitted','Approved','Published'],true))$target='Draft';
        else throw new DomainException('Invalid grade workflow transition.');
        if(trim($reason)===''||mb_strlen($reason)>1000)throw new DomainException('Enter a reason or approval reference (maximum 1000 characters).');
    }
    query('UPDATE grades SET workflow=?,revision=revision+1,submitted_at=IF(?="Submitted",NOW(),submitted_at),published_at=IF(?="Published",NOW(),published_at),correction_open=? WHERE id=?',[$target,$target,$target,$action==='reopen'?1:($action==='publish'?0:$g['correction_open']),$id]);
    grade_history($id,'grade_'.$action,$reason?:'Submitted for approval',$g);
    if($o['faculty_id'])notify((int)$o['faculty_id'],"Grade #$id: $target".($reason?" — $reason":''),(int)$o['id']);
    if($target==='Published')notify_published_grade($id);
    if($target==='Submitted')notify_admins("Grade #$id submitted for approval",(int)$o['id']);
});}
function review_legacy_grade(int $id,string $reason):void{if(!is_admin())deny();transaction(function()use($id,$reason){
    $g=grade_record($id,true);$o=offering((int)$g['offering_id'],true);usable_offering($o);
    if(!$g['migration_review']||trim($reason)==='')throw new DomainException('Choose a legacy grade and enter the review reason.');
    $p=policy((int)$o['policy_id']);$c=grade_values((float)$g['midterm'],(float)$g['final'],$p);
    query('UPDATE grades SET policy_id=?,units_snapshot=?,academic_snapshot=?,average=?,gpa=?,remarks=?,academic_term=?,migration_review=0,revision=revision+1 WHERE id=?',[$p['id'],$o['units'],$o['is_academic'],$c['average'],$c['gpa'],$c['remarks'],$o['term_name'],$id]);
    grade_history($id,'grade_migration_review',$reason,$g);
});}
function expire_incomplete_grades():int{return transaction(function(){
    $count=0;
    foreach(rows("SELECT * FROM grades WHERE remarks='INC' AND completion_status='Pending' AND inc_deadline<CURRENT_DATE FOR UPDATE")as $g){
        query("UPDATE grades SET gpa=5.00,remarks='Failed',completion_status='Expired',revision=revision+1 WHERE id=?",[$g['id']]);
        grade_history((int)$g['id'],'grade_inc_expired','Academic-calendar completion period expired',$g);$o=row('SELECT faculty_id FROM offerings WHERE id=?',[$g['offering_id']]);
        if($o&&$o['faculty_id'])notify((int)$o['faculty_id'],'INC expired and converted to 5.00 for grade #'.$g['id'],(int)$g['offering_id']);if($g['workflow']==='Published'&&!$g['migration_review'])notify_published_grade((int)$g['id']);$count++;
    }return $count;
});}
