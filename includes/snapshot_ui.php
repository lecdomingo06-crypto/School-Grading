<?php
function academic_snapshot(?string $json):void{
    if(!$json){echo '<p class="text-secondary">No previous record / removed draft.</p>';return;}
    $snapshot=json_decode($json,true);if(!is_array($snapshot)){echo '<p>Snapshot unavailable.</p>';return;}
    $labels=['midterm'=>'Midterm / standing','final'=>'Final / standing','average'=>'Percentage','gpa'=>'ISU grade','remarks'=>'Result','workflow'=>'Workflow','units_snapshot'=>'Credit units','inc_reason'=>'Unmet requirements','inc_issued_on'=>'INC issued','inc_deadline'=>'Completion deadline','completion_status'=>'Completion status','completion_reference'=>'Registrar reference','reason'=>'Reason','notes'=>'Follow-up actions','status'=>'Status','follow_up_on'=>'Follow-up date','adviser_id'=>'Responsible faculty ID'];
    echo '<dl class="snapshot-values">';foreach($labels as $key=>$label)if(array_key_exists($key,$snapshot)){echo '<dt>'.h($label).'</dt><dd>'.h($snapshot[$key]??'—').'</dd>';}
    if(!empty($snapshot['policy_id']))echo '<dt>Policy version</dt><dd>'.h(policy((int)$snapshot['policy_id'])['name']).'</dd>';echo '</dl>';
}
