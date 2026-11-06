# Upgrading an existing installation

1. Stop application traffic and scheduled jobs. Keep the existing source and a private database backup.
2. Point the environment at the intended database and configure private `BACKUP_DIR` outside the application directory.
3. Run `php bin/console.php migrate` to display the planned operation without changing data. The current target is `003_student_portal`. An original installation needs 002 and 003; an existing 002 installation needs only 003. An up-to-date installation reports no changes.
4. Run `php bin/console.php migrate --apply` during maintenance. A private pre-upgrade recovery SQL dump is written first. MySQL DDL is not transactional; do not interrupt this command. If interrupted, recover into a separate empty database from the snapshot before retrying. Do not repeatedly execute a partially applied migration.
5. Verify student, enrollment, grade, account, and publication counts against the previous installation. Confirm both `002_academic_workflows` and `003_student_portal` in `schema_migrations`. Test Administrator and Faculty sign-in before enabling Student access.

## Student portal upgrade from 002

Migration 003 extends `users.role` with Student and adds nullable `users.student_id`, a unique index, and a foreign key to `students`. Existing staff roles and credentials remain unchanged. The unique nullable link permits unlinked accounts but prevents two accounts from claiming one student record. Deleting a student record clears the link rather than assigning another identity.

Notifications gain audience, student, and grade metadata. Existing messages default to staff-only; the upgrade does not reveal old staff comments to students. No existing account is linked to a student and no mark is published automatically.

After upgrading, create or review Student accounts in Users. Verify identity and save the correct academic-record link before approving a pending Student registration. Link and role changes are audited and revoke prior sessions. Public registration cannot establish ownership.

The CLI checks for an inconsistent migration marker/student column and refuses to continue a partial upgrade. Recover the pre-upgrade snapshot into a separate empty database rather than rerunning incomplete DDL.

## Original academic records upgrade

The migration adds structures rather than dropping original data. Programs are derived from existing course names. Each legacy subject/term combination becomes an unassigned offering with enrollments preserved. No teacher, section, or institutional calendar is guessed. Existing marks, original GPA, and average remain intact, with `migration_review=1` and Draft workflow. They cannot be submitted or treated as published results until reviewed.

In Academic setup, an administrator creates the correct calendars, sections, and policy versions. Edit each legacy offering and explicitly confirm its term, section, and faculty assignment. If a legacy combination contains multiple actual sections, resolve it with a reviewed data migration; do not falsely attest it belongs to one section. The application does not guess that split.

On Grades, open each legacy record and use **Review & recalculate** with a reason. Its original values and the ISU recalculation are saved in history. Then follow normal submission, approval, and publication.

Policy formulas are immutable. Create a new policy version for new weighting or rounding. An administrator can record an institutional approval reference for an existing version without changing its formula. Offerings with grades retain their policy, subject, term, and section. Faculty reassignment remains possible and is audited; the previous faculty member immediately loses access.

## Backup compatibility

Initial V1 recovery SQL and the pre-upgrade schema/data snapshot are for controlled manual recovery into an empty database. They are deliberately not accepted by the browser data-restore parser.

Schema 003 generates v3 SQL data backups. The strict parser accepts v3 backups after upgrade and also accepts supported v2/schema-002 backups: legacy data is adapted to the current schema with no Student links and staff-only notification audiences. It never infers links or publishes old grades. Restoring v2 replaces current records, including any Student accounts created after that backup, so inspect the preview and preserve a current backup first. Source recovery requires the source version matching the installed schema. See [DEPLOYMENT.md](DEPLOYMENT.md).
