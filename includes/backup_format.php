<?php
declare(strict_types=1);

function backup_schema(): string
{
    return student_schema_ready() ? '003_student_portal' : '002_academic_workflows';
}

function backup_format_version(): int
{
    return student_schema_ready() ? 3 : 2;
}

function database_backup(): string
{
    return transaction(function (): string {
        $version = backup_format_version();
        $manifest = ['application'=>'crystal-dds', 'version'=>$version, 'schema'=>backup_schema(), 'created_at'=>gmdate('c'), 'tables'=>[]];
        $lines = [];
        foreach (BACKUP_TABLES as $table) {
            $columns = table_columns($table);
            $order = $table === 'settings' ? 'setting_key' : ($table === 'schema_migrations' ? 'version' : 'id');
            $data = canonical_rows(rows("SELECT * FROM `$table` ORDER BY `$order`"));
            $manifest['tables'][$table] = ['columns'=>$columns, 'count'=>count($data), 'sha256'=>hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
            $lines[] = '-- TABLE: ' . $table;
            foreach ($data as $record) {
                $tokens = array_map(fn($value)=>$value === null ? 'NULL' : "X'" . strtoupper(bin2hex($value)) . "'", $record);
                $lines[] = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $tokens) . ');';
            }
        }
        return "-- Crystal DDS data backup v$version\n-- MANIFEST: " . base64_encode(json_encode($manifest, JSON_THROW_ON_ERROR)) . "\n" . implode("\n", $lines) . "\n";
    });
}

function backup_source_columns(string $table, int $version): array
{
    $columns = table_columns($table);
    if ($version === 2 && student_schema_ready()) {
        $newColumns = ['users'=>['student_id'], 'notifications'=>['audience', 'student_id', 'grade_id']];
        $columns = array_values(array_diff($columns, $newColumns[$table] ?? []));
    }
    return $columns;
}

function parse_database_backup(string $content): array
{
    if (strlen($content) > 25 * 1024 * 1024) throw new DomainException('SQL backup exceeds 25 MB.');
    $lines = preg_split('/\r\n|\n|\r/', $content);
    $header = array_shift($lines);
    if (!preg_match('/^-- Crystal DDS data backup v([23])$/D', $header ?? '', $match)) throw new DomainException('Only Crystal v2 and v3 application backups are supported.');
    $version = (int)$match[1];
    if ($version === 3 && !student_schema_ready()) throw new DomainException('Upgrade the database before restoring a Student portal backup.');
    $manifestLine = array_shift($lines);
    if (!str_starts_with($manifestLine ?? '', '-- MANIFEST: ')) throw new DomainException('Backup manifest missing.');
    try {
        $manifest = json_decode(base64_decode(substr($manifestLine, 13), true) ?: '', true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) { throw new DomainException('Invalid backup manifest.'); }
    $schema = $version === 3 ? '003_student_portal' : '002_academic_workflows';
    if (!is_array($manifest) || ($manifest['application'] ?? '') !== 'crystal-dds' || ($manifest['version'] ?? 0) !== $version || ($manifest['schema'] ?? '') !== $schema || !is_array($manifest['tables'] ?? null) || array_keys($manifest['tables']) !== BACKUP_TABLES) throw new DomainException('Incompatible or incomplete database backup.');

    $sourceColumns = [];
    foreach (BACKUP_TABLES as $table) {
        $sourceColumns[$table] = backup_source_columns($table, $version);
        $details = $manifest['tables'][$table];
        if (!is_array($details) || ($details['columns'] ?? null) !== $sourceColumns[$table] || !is_int($details['count'] ?? null) || !is_string($details['sha256'] ?? null)) throw new DomainException('Backup columns or metadata are incompatible.');
    }
    $data = array_fill_keys(BACKUP_TABLES, []);
    $seen = []; $current = null;
    foreach ($lines as $line) {
        if ($line === '') continue;
        if (str_starts_with($line, '-- TABLE: ')) {
            $current = substr($line, 10);
            if (!in_array($current, BACKUP_TABLES, true) || isset($seen[$current])) throw new DomainException('Invalid or duplicate table marker.');
            $seen[$current] = true;
            continue;
        }
        if (!preg_match('/^INSERT INTO `([a-z_]+)` \(([^)]*)\) VALUES \((.*)\);$/D', $line, $parts) || $parts[1] !== $current) throw new DomainException('Unsupported SQL statement.');
        $columns = $sourceColumns[$current];
        if ($parts[2] !== '`' . implode('`, `', $columns) . '`') throw new DomainException('Backup columns do not match its schema.');
        preg_match_all("/X'([0-9A-F]*)'|NULL/", $parts[3], $tokens, PREG_SET_ORDER);
        if (count($tokens) !== count($columns) || implode(', ', array_column($tokens, 0)) !== $parts[3]) throw new DomainException('Malformed backup values.');
        $values = [];
        foreach ($tokens as $token) {
            if ($token[0] === 'NULL') $values[] = null;
            else {
                if (strlen($token[1]) % 2 !== 0) throw new DomainException('Malformed hexadecimal value.');
                $values[] = hex2bin($token[1]);
            }
        }
        $data[$current][] = $values;
    }
    if (array_keys($seen) !== BACKUP_TABLES) throw new DomainException('Backup tables are incomplete.');
    foreach (BACKUP_TABLES as $table) {
        $details = $manifest['tables'][$table];
        if ($details['count'] !== count($data[$table]) || !hash_equals($details['sha256'], hash('sha256', json_encode($data[$table], JSON_THROW_ON_ERROR)))) throw new DomainException('Backup integrity verification failed for ' . $table);
    }

    if ($version === 2 && student_schema_ready()) {
        // Legacy restores never infer student identity or turn staff comments into student messages.
        foreach (BACKUP_TABLES as $table) {
            $targetColumns = table_columns($table);
            foreach ($data[$table] as &$values) {
                $record = array_combine($sourceColumns[$table], $values);
                $values = array_map(fn($column)=>array_key_exists($column, $record) ? $record[$column] : ($column === 'audience' ? 'staff' : null), $targetColumns);
            }
            unset($values);
        }
        $data['schema_migrations'][] = ['003_student_portal', date('Y-m-d H:i:s')];
        $manifest['source_schema'] = $schema;
        $manifest['schema'] = '003_student_portal';
        $manifest['version'] = 3;
        foreach (BACKUP_TABLES as $table) $manifest['tables'][$table] = ['columns'=>table_columns($table), 'count'=>count($data[$table]), 'sha256'=>hash('sha256', json_encode($data[$table], JSON_THROW_ON_ERROR))];
    }
    return ['manifest'=>$manifest, 'data'=>$data];
}
