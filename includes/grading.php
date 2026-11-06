<?php
declare(strict_types=1);
const ISU_POLICY_SOURCE='https://isu.edu.ph/wp-content/uploads/2025/10/Student-Manual.pdf';
function grade_values(float $midterm,float $final,array $policy=[]): array {
    if(!is_finite($midterm)||!is_finite($final)||$midterm<0||$midterm>100||$final<0||$final>100) throw new DomainException('Marks must be between 0 and 100.');
    $weight=(float)($policy['midterm_weight']??50); if($weight<0||$weight>100) throw new DomainException('Invalid assessment weight.');
    $average=round(($midterm*$weight+$final*(100-$weight))/100,2);
    $score=($policy['rounding']??'floor')==='nearest'?(int)round($average,0,PHP_ROUND_HALF_UP):(int)floor($average);
    $gpa=match(true){$score>=98=>1.00,$score>=95=>1.25,$score>=92=>1.50,$score>=89=>1.75,$score>=86=>2.00,$score>=83=>2.25,$score>=80=>2.50,$score>=77=>2.75,$score>=75=>3.00,default=>5.00};
    return ['average'=>$average,'gpa'=>$gpa,'remarks'=>$gpa<=3?'Passed':'Failed'];
}
function weighted_average(array $rows,array $policy): ?float {
    $eligible=[];
    foreach($rows as $r){
        if($r['workflow']!=='Published'||$r['gpa']===null||!empty($r['migration_review'])||$r['remarks']==='INC'||(!(int)$policy['include_nonacademic']&&!(int)$r['academic_snapshot'])) continue;
        $key=$r['student_id'].':'.$r['subject_id']; if($policy['repeat_rule']==='all') $key.=':'.$r['id'];
        if(!isset($eligible[$key])||($policy['repeat_rule']==='best'&&(float)$r['gpa']<(float)$eligible[$key]['gpa'])||($policy['repeat_rule']==='latest'&&[$r['term_start'],$r['id']]>[$eligible[$key]['term_start'],$eligible[$key]['id']])) $eligible[$key]=$r;
    }
    $units=0.0;$points=0.0;foreach($eligible as $r){$u=(float)$r['units_snapshot'];$units+=$u;$points+=$u*(float)$r['gpa'];}return $units>0?round($points/$units,3):null;
}
