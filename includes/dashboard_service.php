<?php
require_once __DIR__.'/notifications_service.php';
function dashboard_data():array{require_staff();
    [$scope,$params]=scope_sql();$gradeRows=rows(grade_select()." WHERE $scope",$params);$term=(int)setting('active_term_id','0');
    $students=rows("SELECT DISTINCT s.id FROM students s JOIN enrollments e ON e.student_id=s.id JOIN offerings o ON o.id=e.offering_id WHERE $scope",$params);
    $subjectCount=(int)scalar("SELECT COUNT(DISTINCT o.subject_id) FROM offerings o WHERE $scope",$params);
    $enrollmentCount=(int)scalar("SELECT COUNT(*) FROM enrollments e JOIN offerings o ON o.id=e.offering_id WHERE $scope",$params);
    if(is_admin()){$students=rows('SELECT id FROM students');$subjectCount=(int)scalar('SELECT COUNT(*) FROM subjects');}
    $auditScope=is_admin()?'1=1':'(a.user_id=? AND a.offering_id IS NULL) OR EXISTS(SELECT 1 FROM offerings o WHERE o.id=a.offering_id AND o.faculty_id=? AND o.needs_review=0)';$auditParams=is_admin()?[]:[current_user()['id'],current_user()['id']];
    $activity=rows("SELECT a.id,a.full_name,a.activity_description,a.action,a.created_at FROM activity_logs a WHERE ($auditScope) ORDER BY a.id DESC LIMIT 10",$auditParams);
    $daily=(int)scalar("SELECT COUNT(*) FROM activity_logs a WHERE ($auditScope) AND a.log_date=CURRENT_DATE",$auditParams);
    $gradeTransactions=(int)scalar("SELECT COUNT(*) FROM activity_logs a WHERE ($auditScope) AND a.action LIKE 'grade_%'",$auditParams);
    $monthly=rows("SELECT DATE_FORMAT(a.created_at,'%Y-%m') label,COUNT(*) value FROM activity_logs a WHERE ($auditScope) AND a.action LIKE 'grade_%' AND a.created_at>=CURRENT_DATE-INTERVAL 6 MONTH GROUP BY label ORDER BY label",$auditParams);
    $dailyGraph=rows("SELECT a.log_date label,COUNT(*) value FROM activity_logs a WHERE ($auditScope) AND a.log_date>=CURRENT_DATE-INTERVAL 14 DAY GROUP BY label ORDER BY label",$auditParams);
    $userActivity=rows("SELECT a.full_name label,COUNT(*) value FROM activity_logs a WHERE ($auditScope) AND a.created_at>=CURRENT_DATE-INTERVAL 30 DAY GROUP BY a.user_id,a.full_name ORDER BY value DESC LIMIT 10",$auditParams);
    $usage=rows("SELECT DATE_FORMAT(a.created_at,'%Y-%m') label,COUNT(DISTINCT a.user_id) value FROM activity_logs a WHERE ($auditScope) AND a.created_at>=CURRENT_DATE-INTERVAL 6 MONTH GROUP BY label ORDER BY label",$auditParams);
    $risk=[];$distribution=[];$failure=[];$performance=[];$pending=0;
    foreach($gradeRows as $g){if($g['workflow']==='Submitted')$pending++;if($g['workflow']!=='Published'||$g['migration_review'])continue;$distribution[$g['remarks']]=($distribution[$g['remarks']]??0)+1;
        if($g['remarks']==='Failed'&&$g['academic_status']==='Active'&&(int)$g['offering_id']>0&&(int)$g['term_id']===$term)$risk[$g['student_id']]=$g;
        if($g['remarks']!=='INC'){$key=$g['subject_code'];$failure[$key]??=['failed'=>0,'total'=>0];$failure[$key]['total']++;if($g['remarks']==='Failed')$failure[$key]['failed']++;$label=$g['school_year'].' '.$g['term_name'];$performance[$label][]=$g;}
    }
    $toSeries=fn(array $map)=>array_map(fn($key,$value)=>['label'=>$key,'value'=>$value],array_keys($map),array_values($map));
    $policyId=(int)setting('gwa_policy_id','1');$gwa=weighted_average($gradeRows,policy($policyId));
    $cards=['Academic records'=>count($students)+$subjectCount+$enrollmentCount+count($gradeRows),'Grade transactions'=>$gradeTransactions,'Active accounts'=>is_admin()?(int)scalar("SELECT COUNT(*) FROM users WHERE is_active=1 AND registration_status='Approved'"):1,'Daily activities'=>$daily,'Students'=>count($students),'Subjects'=>$subjectCount,'Current-term at risk'=>count($risk),'Published weighted average'=>$gwa===null?'—':number_format($gwa,3)];
    $classes=rows("SELECT o.id,s.subject_code,COALESCE(t.name,o.legacy_term) term_name,t.submission_deadline FROM offerings o JOIN subjects s ON s.id=o.subject_id LEFT JOIN terms t ON t.id=o.term_id WHERE $scope AND o.is_active=1 ORDER BY t.submission_deadline DESC LIMIT 10",$params);
    $recentTransactions=rows("SELECT a.full_name,a.activity_description,a.action,a.created_at FROM activity_logs a WHERE ($auditScope) AND a.action LIKE 'grade_%' ORDER BY a.id DESC LIMIT 8",$auditParams);
    return ['cards'=>$cards,'charts'=>['monthly'=>$monthly,'daily'=>$dailyGraph,'users'=>$userActivity,'usage'=>$usage,'distribution'=>$toSeries($distribution),'failure'=>$toSeries(array_map(fn($v)=>round(100*$v['failed']/$v['total'],2),$failure)),'performance'=>$toSeries(array_map(fn($v)=>weighted_average($v,policy($policyId)),$performance))],'activities'=>$activity,'transactions'=>$recentTransactions,'risk'=>array_values($risk),'classes'=>$classes,'notifications'=>user_notifications(),'pending_grades'=>$pending,'pending_registrations'=>is_admin()?(int)scalar("SELECT COUNT(*) FROM users WHERE registration_status='Pending'"):0,'system'=>is_admin()?['database'=>'Connected','php'=>PHP_VERSION,'zip'=>class_exists('ZipArchive')?'Available':'Enable PHP zip extension','https'=>(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'Enabled':'Local HTTP; configure AutoSSL for production']:null,'refreshed_at'=>date('Y-m-d H:i:s').' Asia/Manila','gwa_policy'=>policy($policyId)['name']];
}
