<?php
function seed_demo():void{transaction(function(){
    $year=(int)date('Y');$teacherPass=getenv('DEMO_FACULTY_PASSWORD')?:'FacultyDemo2026!';
    query("INSERT INTO users(full_name,username,password_hash,role,must_change_password) VALUES('Demo Faculty','teacher',?,'Faculty',1)",[password_hash($teacherPass,PASSWORD_BCRYPT)]);$faculty=(int)db()->lastInsertId();
    query("INSERT INTO courses(code,name) VALUES('BSIT','BS Information Technology')");$course=(int)db()->lastInsertId();
    query('INSERT INTO school_years(name,starts_on,ends_on) VALUES(?,?,?)',[($year-1).'-'.($year+1),'2025-01-01',($year+1).'-12-31']);$sy=(int)db()->lastInsertId();
    query("INSERT INTO terms(school_year_id,name,starts_on,ends_on,submission_deadline,inc_deadline) VALUES(?,'Demonstration semester',?,?,?,?)",[$sy,($year-1).'-01-01',($year+1).'-06-30',($year+1).'-07-15',($year+2).'-06-30']);$term=(int)db()->lastInsertId();
    query("UPDATE settings SET setting_value=? WHERE setting_key='active_term_id'",[(string)$term]);query("INSERT INTO sections(course_id,name,year_level) VALUES(?,'BSIT 2-A',2)",[$course]);$section=(int)db()->lastInsertId();
    query("INSERT INTO subjects(subject_code,title,units) VALUES('IT201','Database Systems',3)");$subject=(int)db()->lastInsertId();
    query('INSERT INTO offerings(subject_id,term_id,section_id,faculty_id,policy_id) VALUES(?,?,?,?,1)',[$subject,$term,$section,$faculty]);$offering=(int)db()->lastInsertId();
    foreach([['2024-0001','Avery Santos'],['2024-0002','Jordan Reyes']]as [$number,$name]){query("INSERT INTO students(student_number,full_name,course,course_id,section_id,year_level) VALUES(?,?,'BS Information Technology',?,?,2)",[$number,$name,$course,$section]);$student=(int)db()->lastInsertId();query('INSERT INTO enrollments(student_id,offering_id) VALUES(?,?)',[$student,$offering]);}
    audit('demo_seed','system',null,'Loaded explicitly requested demonstration records; policy remains unapproved');
});}
