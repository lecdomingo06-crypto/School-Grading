<?php
function csv_safe(mixed $v):string{$s=(string)($v??'');return preg_match('/^[\s\x00-\x1F]*[=+@-]/u',$s)?"'".$s:$s;}
function output_csv(string $filename,array $data,array $columns):never{header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,array_values($columns));foreach($data as $r)fputcsv($out,array_map(fn($key)=>csv_safe($r[$key]??''),array_keys($columns)));fclose($out);exit;}
