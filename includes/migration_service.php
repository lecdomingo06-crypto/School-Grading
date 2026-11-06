<?php
declare(strict_types=1);

const CURRENT_SCHEMA = '003_student_portal';

function pending_migrations(): array
{
    $hasVersions = (bool) scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'");
    if (!$hasVersions) return ['002_academic_workflows', '003_student_portal'];
    if (!scalar("SELECT COUNT(*) FROM schema_migrations WHERE version='002_academic_workflows'")) {
        throw new DomainException('An incomplete base migration was detected. Recover the pre-upgrade snapshot into an empty database before retrying.');
    }
    $hasStudentMarker = (bool) scalar("SELECT COUNT(*) FROM schema_migrations WHERE version='003_student_portal'");
    $hasStudentColumn = (bool) scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='student_id'");
    if ($hasStudentMarker !== $hasStudentColumn) {
        throw new DomainException('A partial Student migration was detected. Recover the pre-upgrade snapshot before retrying.');
    }
    return $hasStudentMarker ? [] : ['003_student_portal'];
}

function migrate_database(bool $apply): array
{
    $pending = pending_migrations();
    if (!$pending || !$apply) return $pending;
    // DDL is not transactional in MySQL. Preserve schema and data before the first ALTER.
    $snapshot = save_private_backup(legacy_recovery_dump(), 'sql');
    echo 'Pre-upgrade recovery snapshot: ' . $snapshot . PHP_EOL;
    foreach ($pending as $version) {
        db()->exec(file_get_contents(dirname(__DIR__) . '/migrations/' . $version . '.sql'));
        echo 'Applied migration ' . $version . PHP_EOL;
    }
    return $pending;
}
