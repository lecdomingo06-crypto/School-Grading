<?php
function user_notifications():array{require_staff();
    $u=current_user();[$scope,$params]=scope_sql();
    $stored=rows('SELECT n.* FROM notifications n WHERE n.user_id=?'.(student_schema_ready()?" AND n.audience='staff'":'').' AND (n.offering_id IS NULL OR EXISTS(SELECT 1 FROM offerings o WHERE o.id=n.offering_id AND '.(is_admin()?'1=1':'o.faculty_id=? AND o.needs_review=0').')) ORDER BY n.id DESC LIMIT 30',is_admin()?[$u['id']]:[$u['id'],$u['id']]);
    $reminders=[];
    foreach(rows("SELECT o.id,s.subject_code,t.submission_deadline FROM offerings o JOIN subjects s ON s.id=o.subject_id JOIN terms t ON t.id=o.term_id WHERE $scope AND o.is_active=1 AND t.submission_deadline BETWEEN CURRENT_DATE AND CURRENT_DATE+INTERVAL 7 DAY",$params)as $o)$reminders[]=['message'=>$o['subject_code'].' grades due '.$o['submission_deadline'],'offering_id'=>$o['id']];
    foreach(rows("SELECT g.id,g.offering_id,g.inc_deadline FROM grades g JOIN offerings o ON o.id=g.offering_id WHERE $scope AND g.remarks='INC' AND g.inc_deadline<=CURRENT_DATE+INTERVAL 30 DAY",$params)as $g)$reminders[]=['message'=>'INC completion due '.$g['inc_deadline'].' for grade #'.$g['id'],'offering_id'=>$g['offering_id']];
    foreach(rows("SELECT i.id,i.offering_id,i.follow_up_on FROM interventions i JOIN offerings o ON o.id=i.offering_id WHERE $scope AND i.is_active=1 AND i.status<>'Resolved' AND i.follow_up_on<=CURRENT_DATE+INTERVAL 7 DAY",$params)as $i)$reminders[]=['message'=>'Intervention #'.$i['id'].' follow-up due '.$i['follow_up_on'],'offering_id'=>$i['offering_id']];
    return ['stored'=>$stored,'reminders'=>$reminders];
}
