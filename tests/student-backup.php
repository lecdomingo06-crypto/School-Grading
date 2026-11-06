<?php
declare(strict_types=1);

// Destructive restore checks are confined to newly created crystal_test_backup_* databases.
if (PHP_SAPI !== 'cli') exit;
$dbName = getenv('DB_NAME') ?: 'crystal_test_backup_'.date('YmdHis').'_'.bin2hex(random_bytes(3));
if (!preg_match('/^crystal_test_backup_[a-z0-9_]+$/D', $dbName)) throw new RuntimeException('Clear DB_NAME or choose a NEW crystal_test_backup_* database.');
putenv('DB_NAME='.$dbName);
putenv('CRYSTAL_CONFIG_FILE');
putenv('APP_ENV=test');
putenv('FORCE_HTTPS=0');
putenv('BACKUP_DIR='.sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'_backups');
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false];
$server = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", getenv('DB_USER')?:'root', getenv('DB_PASSWORD')?:'', $options);
$legacyName = $dbName.'_legacy';
foreach ([$dbName, $legacyName] as $name) {
    $exists = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
    $exists->execute([$name]);
    if ($exists->fetchColumn()) throw new RuntimeException('Test database already exists; refusing to replace it.');
}
foreach ([$dbName, $legacyName] as $name) $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
require_once __DIR__.'/../includes/app.php';
require_once __DIR__.'/../includes/backup_service.php';
$checks = 0;
function backup_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: '.$label);
    $checks++;
    echo "PASS $label\n";
}
function backup_rejects(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException|PDOException $error) { backup_check(true, $label); return; }
    backup_check(false, $label);
}
function backup_actor(int $id): void {
    $_SESSION = ['user'=>row('SELECT id,auth_version FROM users WHERE id=?', [$id]), 'epoch'=>setting('session_epoch'), 'last_activity'=>time()];
}
function backup_set_value(array &$backup, string $table, int $row, string $column, mixed $value): void {
    $position = array_search($column, $backup['manifest']['tables'][$table]['columns'], true);
    if ($position === false) throw new RuntimeException('Missing fixture column: '.$column);
    $backup['data'][$table][$row][$position] = $value;
}
function backup_rows_snapshot(): array {
    $snapshot = [];
    foreach (BACKUP_TABLES as $table) {
        $order = $table === 'settings' ? 'setting_key' : ($table === 'schema_migrations' ? 'version' : 'id');
        $snapshot[$table] = canonical_rows(rows("SELECT * FROM `$table` ORDER BY `$order`"));
    }
    return $snapshot;
}
// Produce the old application's real column order and data representation from a separate 002 database.
function legacy_backup_v2(PDO $source): string {
    $manifest = ['application'=>'crystal-dds', 'version'=>2, 'schema'=>'002_academic_workflows', 'created_at'=>gmdate('c'), 'tables'=>[]];
    $lines = [];
    foreach (BACKUP_TABLES as $table) {
        $columns = array_column($source->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
        $order = $table === 'settings' ? 'setting_key' : ($table === 'schema_migrations' ? 'version' : 'id');
        $data = canonical_rows($source->query("SELECT * FROM `$table` ORDER BY `$order`")->fetchAll());
        $manifest['tables'][$table] = ['columns'=>$columns, 'count'=>count($data), 'sha256'=>hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
        $lines[] = '-- TABLE: '.$table;
        foreach ($data as $record) {
            $tokens = array_map(fn($value)=>$value === null ? 'NULL' : "X'".strtoupper(bin2hex($value))."'", $record);
            $lines[] = 'INSERT INTO `'.$table.'` (`'.implode('`, `', $columns).'`) VALUES ('.implode(', ', $tokens).');';
        }
    }
    return "-- Crystal DDS data backup v2\n-- MANIFEST: ".base64_encode(json_encode($manifest, JSON_THROW_ON_ERROR))."\n".implode("\n", $lines)."\n";
}

db()->exec(file_get_contents(__DIR__.'/../schema.sql'));
backup_check(scalar('SELECT DATABASE()') === $dbName && student_schema_ready(), 'Restore target is the newly created schema 003 fixture');
$hash = password_hash('BackupFixturePassword2026!', PASSWORD_BCRYPT);
foreach ([['Backup Administrator','backup_admin','Administrator'], ['Backup Faculty','backup_faculty','Faculty'], ['Linked Student','backup_student','Student'], ['Unlinked Student','backup_unlinked','Student']] as [$name,$username,$role]) {
    query('INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,?)', [$name,$username,$hash,$role]);
}
query("INSERT INTO students(student_number,full_name,course,year_level) VALUES('BACKUP-001','Verified Student','Backup Course',2)");
query('UPDATE users SET student_id=1 WHERE id=3');
query("INSERT INTO subjects(subject_code,title,units) VALUES('BKP101','Backup Subject',3)");
query("INSERT INTO grades(student_id,subject_id,academic_term,midterm,final,average,gpa,remarks,entered_by,workflow,migration_review,published_at) VALUES(1,1,'Backup term',85,85,85,2.25,'Passed',2,'Published',0,NOW())");
query("INSERT INTO notifications(user_id,message,audience,student_id,grade_id) VALUES(3,'Published result available','student',1,1),(2,'Internal faculty message','staff',NULL,NULL)");
backup_actor(1);
$original = database_backup();
$v3 = parse_database_backup($original);
backup_check(str_starts_with($original, "-- Crystal DDS data backup v3\n") && $v3['manifest']['schema'] === '003_student_portal', 'New backup declares version 3 and the Student schema');
backup_check(in_array('student_id', $v3['manifest']['tables']['users']['columns'], true) && in_array('audience', $v3['manifest']['tables']['notifications']['columns'], true), 'Backup manifest includes identity links and notification audience');
query("UPDATE students SET full_name='Changed after backup' WHERE id=1");
query('UPDATE users SET student_id=NULL WHERE id=3');
query("UPDATE notifications SET audience='staff',student_id=NULL,grade_id=NULL WHERE id=1");
$recovery = restore_database($v3);
backup_check(is_file($recovery) && str_starts_with(file_get_contents($recovery), '-- Crystal DDS data backup v3'), 'Restore writes a private version 3 recovery backup');
backup_check((int)scalar('SELECT student_id FROM users WHERE id=3') === 1 && scalar('SELECT student_id FROM users WHERE id=4') === null, 'Successful restore preserves the verified unique Student link and unlinked account');
backup_check(scalar('SELECT full_name FROM students WHERE id=1') === 'Verified Student' && (int)scalar('SELECT entered_by FROM grades WHERE id=1') === 2, 'Restore handles Student identity foreign keys together with grade authorship');
$notice = row('SELECT audience,student_id,grade_id FROM notifications WHERE id=1');
backup_check($notice['audience'] === 'student' && (int)$notice['student_id'] === 1 && (int)$notice['grade_id'] === 1 && scalar('SELECT audience FROM notifications WHERE id=2') === 'staff', 'Student and staff notification scope survives the restore');
backup_check(current_user() === null, 'Successful restore revokes the prior authenticated session');
backup_actor(1);
$beforeInvalid = backup_rows_snapshot();
$bad = $v3;
backup_set_value($bad, 'users', 2, 'student_id', '999999');
backup_rejects(fn()=>restore_database($bad), 'Restore rejects a missing Student identity foreign key');
backup_check(backup_rows_snapshot() === $beforeInvalid && current_user() !== null, 'Missing-link failure rolls back all records and preserves the current session');
$bad = $v3;
backup_set_value($bad, 'users', 3, 'student_id', '1');
backup_rejects(fn()=>restore_database($bad), 'Restore rejects two accounts linked to the same Student record');
backup_check(backup_rows_snapshot() === $beforeInvalid, 'Duplicate-link failure rolls back the full database');
$bad = $v3;
backup_set_value($bad, 'users', 2, 'student_id', null);
backup_set_value($bad, 'users', 1, 'student_id', '1');
backup_rejects(fn()=>restore_database($bad), 'Restore rejects an academic identity assigned to a Faculty account');
backup_check(backup_rows_snapshot() === $beforeInvalid, 'Invalid-role link failure rolls back the full database');

$legacy = new PDO("mysql:host=$host;port=$port;dbname=$legacyName;charset=utf8mb4", getenv('DB_USER')?:'root', getenv('DB_PASSWORD')?:'', $options);
$legacy->exec(file_get_contents(__DIR__.'/../migrations/001_base.sql'));
$legacy->exec(file_get_contents(__DIR__.'/../migrations/002_academic_workflows.sql'));
$insertUser = $legacy->prepare('INSERT INTO users(full_name,username,password_hash,role) VALUES(?,?,?,?)');
$insertUser->execute(['Legacy Administrator','backup_admin',$hash,'Administrator']);
$insertUser->execute(['Verified Student','legacy_faculty',$hash,'Faculty']);
$legacy->exec("INSERT INTO students(student_number,full_name,course,year_level) VALUES('LEGACY-001','Verified Student','Legacy Course',1)");
$legacy->exec("INSERT INTO notifications(user_id,message) VALUES(2,'Legacy internal faculty notification')");
$source = legacy_backup_v2($legacy);
$normalized = parse_database_backup($source);
backup_check($normalized['manifest']['version'] === 3 && $normalized['manifest']['source_schema'] === '002_academic_workflows' && $normalized['manifest']['schema'] === '003_student_portal', 'Authentic version 2 data backup is normalized to the installed schema');
$legacyLinks = array_column($normalized['data']['users'], array_search('student_id', table_columns('users'), true));
backup_check($legacyLinks === [null,null], 'Version 2 conversion does not infer identity from matching Student and account names');
$normalizedNotice = array_combine(table_columns('notifications'), $normalized['data']['notifications'][0]);
backup_check($normalizedNotice['audience'] === 'staff' && $normalizedNotice['student_id'] === null && $normalizedNotice['grade_id'] === null, 'Legacy notifications convert to staff audience without Student or grade links');
backup_check(array_column($normalized['data']['schema_migrations'], 0) === ['002_academic_workflows','003_student_portal'], 'Version 2 conversion retains the old marker and records installed migration 003');
backup_rejects(fn()=>parse_database_backup(str_replace("X'4C65676163792041646D696E6973747261746F72'", "X'4368616E676564'", $source)), 'Legacy backup checksum validation runs before normalization');
$legacyRecovery = restore_database($normalized);
backup_check(is_file($legacyRecovery) && (int)scalar('SELECT COUNT(*) FROM users') === 2 && (int)scalar('SELECT COUNT(*) FROM students') === 1, 'Normalized version 2 backup restores and verifies all legacy records');
backup_check((int)scalar('SELECT COUNT(*) FROM users WHERE student_id IS NOT NULL') === 0 && (int)scalar("SELECT COUNT(*) FROM users WHERE role='Student'") === 0 && scalar('SELECT username FROM users WHERE id=2') === 'legacy_faculty', 'Legacy restore never creates Student accounts or academic links');
$restoredNotice = row('SELECT audience,student_id,grade_id,message FROM notifications WHERE id=1');
backup_check($restoredNotice['audience'] === 'staff' && $restoredNotice['student_id'] === null && $restoredNotice['grade_id'] === null && $restoredNotice['message'] === 'Legacy internal faculty notification', 'Legacy notification text remains confined to staff after actual restoration');
backup_check(array_column(rows('SELECT version FROM schema_migrations ORDER BY version'), 'version') === ['002_academic_workflows','003_student_portal'] && student_schema_ready(), 'Legacy data restore retains both migration markers and installed Student schema');
backup_check(parse_database_backup(database_backup())['manifest']['version'] === 3, 'A new backup after legacy restoration remains a valid version 3 backup');
file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.$dbName.'.json', json_encode(['database'=>$dbName, 'legacy_database'=>$legacyName, 'backup_dir'=>getenv('BACKUP_DIR'), 'checks'=>$checks], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "\n$checks checks passed. Isolated databases retained: $dbName and $legacyName\n";
