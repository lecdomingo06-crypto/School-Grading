<?php
declare(strict_types=1);
function help_documents(): array
{
    return [
        'product'=>[
            'code'=>'PRD', 'title'=>'Product Requirement Document', 'short_title'=>'Product requirements',
            'summary'=>'What the system does, who uses it, and the requirements for managing academic records from enrollment to published results.',
            'sections'=>[
                ['title'=>'Purpose and goals','paragraphs'=>[
                    'The Student Grade & Performance Analysis DDS gives administrators, faculty, and students a shared academic-record system with access appropriate to each role.',
                    'The product organizes classes and enrollment, calculates grades using saved policies, records review decisions, and provides private published results and printable reports.',
                ]],
                ['title'=>'Users and responsibilities','columns'=>['role'=>'User','access'=>'Required access'],'rows'=>[
                    ['role'=>'Administrator','access'=>'Manage academic setup, users, verified Student links, assignments, enrollment, grade review/publication, reports, interventions, audit trail, settings, and backup/restore.'],
                    ['role'=>'Faculty','access'=>'Work with assigned classes and permitted student records; enter Draft grades, submit for review, follow returned grades, generate scoped reports, and record assigned interventions.'],
                    ['role'=>'Student','access'=>'View own subjects, reviewed Published grades, history, GWA, reports, and permitted notifications; update contact email and password. Official academic identity stays read-only.'],
                ]],
                ['title'=>'Functional requirements','columns'=>['id'=>'ID','feature'=>'Feature','requirement'=>'Requirement'],'rows'=>[
                    ['id'=>'PR-01','feature'=>'Homepage and access','requirement'=>'Provide a public homepage, role-aware sign-in, arithmetic CAPTCHA, logout, and optional Pending account requests. Public requests cannot grant Administrator access.'],
                    ['id'=>'PR-02','feature'=>'Academic records','requirement'=>'Create, search, filter, paginate, update, and archive permitted programs, years, terms, sections, subjects, students, classes, enrollments, and interventions. Retain academic history.'],
                    ['id'=>'PR-03','feature'=>'Grade workflow','requirement'=>'Support Draft → Submitted → Approved → Published, administrator return/reopening, required correction references, deadline checks, and change history.'],
                    ['id'=>'PR-04','feature'=>'Student identity','requirement'=>'An administrator verifies and links one login to one academic student record. Pending or unlinked accounts cannot read academic records. Reassignment revokes existing sessions.'],
                    ['id'=>'PR-05','feature'=>'Private results','requirement'=>'Student pages, summaries, reports, notifications, and exports use only the linked student’s reviewed Published results; never reveal another student or unpublished marks.'],
                    ['id'=>'PR-06','feature'=>'Dashboards','requirement'=>'Show role-scoped statistics, charts, activity, deadlines, and notifications. Student overview summarizes the selected current term; refreshing loads new results.'],
                    ['id'=>'PR-07','feature'=>'Reports and imports','requirement'=>'Generate authorized printable reports with school identity and timestamps; support browser PDF saving and safe CSV export. Preview and validate CSV imports before committing.'],
                    ['id'=>'PR-08','feature'=>'Operations','requirement'=>'Record account and data actions, report generation/export, settings, and backup/restore. Administrators can create SQL/ZIP backups and validate a restoration preview.'],
                    ['id'=>'PR-09','feature'=>'Help and demonstration','requirement'=>'Make Product and Technical Requirement Documents available through Help. Local demonstration mode provides three sample role accounts and 100 fictional students with enrollment and grade data.'],
                ]],
                ['title'=>'Primary user workflow','items'=>[
                    'Administrator prepares programs, calendar, subjects, grading-policy versions, Faculty accounts, classes, and enrollment.',
                    'Administrator creates or verifies a Student account and saves its academic-record link.',
                    'Assigned faculty enter marks, inspect the calculation, save a Draft, and Submit within the allowed period.',
                    'Administrator reviews the submission, returns it for correction or approves it, then publishes the approved result.',
                    'Student signs in to view their own published result, academic history, notifications, and printable report.',
                ]],
                ['title'=>'Grading and business rules','items'=>[
                    'Preserve the implemented ISU numerical conversion: 1.00 is highest, 3.00 is the lowest passing numerical grade, and 5.00 means failed.',
                    'Assessment weights, percentage rounding, repeated-subject treatment, nonacademic inclusion, and academic-calendar dates require the school’s approved configuration. Initial defaults and demonstration policies do not establish official institutional rules.',
                    'Each grade retains its policy, credit-unit, and academic-subject snapshots. A changed formula requires a new policy version.',
                    'INC requires passing standing and unmet requirements. Completion uses the configured deadline and authorized registrar reference; pending expired INC results follow the existing expiration process. INC is excluded from GWA.',
                    'Published corrections must be reopened, corrected, submitted, approved, and published again. Reopening hides the result from Student views until republished.',
                ]],
                ['title'=>'Usability and quality requirements','items'=>[
                    'Use a consistent green, cream, and gold interface on desktop, tablet, and mobile.',
                    'Provide clear labels, keyboard navigation, visible focus, readable feedback, reduced-motion support, and scrollable wide tables.',
                    'Confirm sensitive actions and show meaningful empty states. Identify unpublished results neutrally.',
                    'Keep private records off the public homepage. Enforce permissions on the server for pages, APIs, submissions, and downloads.',
                ]],
                ['title'=>'Acceptance criteria','items'=>[
                    'Each approved role reaches its correct workspace and receives only its permitted records.',
                    'An assigned faculty member can save and submit a grade; an administrator can approve/publish it; the linked student can then view and print it.',
                    'Direct requests cannot bypass Faculty ownership or Student identity/publication restrictions.',
                    'Both requirements documents open from Help, and the login page displays working demo credentials when local demo mode is enabled.',
                    'The sample-data command creates 100 fictional students with matching enrollment and grades, preserves existing records, and does not duplicate records on another run.',
                    'Reports, backups, account revocation, and mobile navigation pass the relevant automated and browser checks.',
                ]],
                ['title'=>'Scope and limitations','paragraphs'=>[
                    'The delivered application runs locally through PHP and MySQL/MariaDB. Hosting, HTTPS certificate installation, and scheduler setup are environment tasks. The source is maintained in GitHub.',
                    'Email-based password recovery, tuition/payment processing, automatic honors/graduation certification, and predictive AI are outside the implemented scope. Administrators provide temporary-password resets. Student reports are system-generated copies subject to school certification.',
                    'Demonstration records are fictional and visibly named Demo. Published demo credentials are intended only for local demonstrations; production mode disables demo logins and credential display.',
                ]],
            ],
        ],
        'technical'=>[
            'code'=>'TRD', 'title'=>'Technical Requirement Document', 'short_title'=>'Technical requirements',
            'summary'=>'How the PHP/MySQL application implements its features, stores academic data, enforces access, and supports installation, testing, and recovery.',
            'sections'=>[
                ['title'=>'Architecture and runtime','paragraphs'=>[
                    'The application uses server-rendered PHP pages, PDO database services, MySQL/MariaDB relational storage, and browser JavaScript for charts, navigation, confirmation, and grade previews. It does not require a JavaScript build step.',
                    'Use PHP 8.1 or later with PDO MySQL, mbstring, fileinfo, OpenSSL, sessions, and Zip. The verified local environment uses PHP 8.2, MariaDB 10.4, and Apache/XAMPP. Bootstrap and Chart.js assets are stored in the project for offline use.',
                ]],
                ['title'=>'Source organization and routes','columns'=>['area'=>'Area','responsibility'=>'Responsibility'],'rows'=>[
                    ['area'=>'Root PHP routes','responsibility'=>'Public homepage/authentication, dashboard, academic management, accounts, reports, backups, Student portal, and help.php.'],
                    ['area'=>'includes/pages and includes/layout','responsibility'=>'Page controllers/templates and shared header, role navigation, footer, and messages.'],
                    ['area'=>'includes/*_service.php','responsibility'=>'Record, grade, account, Student, report, import, backup, notification, dashboard, and migration rules.'],
                    ['area'=>'assets','responsibility'=>'Shared CSS/JavaScript, vendored libraries, and the system logo.'],
                    ['area'=>'schema.sql and migrations','responsibility'=>'Complete empty-database schema and versioned, data-preserving upgrades through 003_student_portal.'],
                    ['area'=>'bin and tests','responsibility'=>'CLI installation/migration/maintenance/demo commands, restricted development router, and isolated integration/browser checks.'],
                ]],
                ['title'=>'Database requirements','paragraphs'=>[
                    'Use InnoDB, UTF-8, foreign keys, and unique constraints. Core tables are users, students, subjects, courses, school_years, terms, sections, grading_policies, offerings, enrollments, grades, grade_history, activity_logs, login_attempts, interventions, notifications, settings, and schema_migrations.',
                    'users.student_id is an optional unique foreign key to students.id. An enrollment is unique by student/offering; a grade is unique by student/offering. A class references its subject, term, section, Faculty account, and policy. Existing academic identities are never automatically inferred during migration.',
                    'Grades store decimal marks, numerical result, workflow, revision, review flag, policy and credit-unit snapshots, publication timestamps, and INC completion details. History records before/after snapshots and the actor/reason.',
                    'The optional local demo-data command adds 100 fictional students, 100 enrollments, 100 grade records, and three separate demo accounts. A completion marker makes it idempotent. Existing records are preserved and a private backup precedes the initial load.',
                ]],
                ['title'=>'Authentication and authorization','items'=>[
                    'Store passwords using bcrypt through password_hash(); validate with password_verify(). Never store actual user passwords as database plaintext.',
                    'Validate arithmetic CAPTCHA, active/Approved status, and the five-failure/15-minute login limiter. Registration creates Pending inactive requests.',
                    'Use strict HTTP-only, SameSite session cookies; regenerate sessions on login/password change. Use secure cookies and HSTS when HTTPS is active.',
                    'Enforce Administrator/Faculty/Student roles in controllers and services. Scope Faculty by assigned reviewed offerings and Student by the authenticated account’s verified link.',
                    'Use auth_version for account/link/role/password revocation, session_epoch after restoration, and the configured inactivity timeout (default 1800 seconds).',
                    'Validate CSRF tokens for state-changing forms and require POST logout. Escape rendered data and use PDO prepared statements for request values.',
                    'Display sample credentials only when explicitly seeded local demo mode is enabled. Known demo account logins are disabled in production.',
                ]],
                ['title'=>'Grade processing and Student data','items'=>[
                    'The server calculates a weighted percentage from marks in 0–100, applies the saved floor/nearest integer-conversion policy, and maps to the existing ISU numerical table. JavaScript preview is informational; the server result is authoritative.',
                    'Enforce active enrollment, usable class/term, approved policy before submission, deadline rules, valid workflow transitions, revision checks, and required correction references.',
                    'Student grade queries require the linked student ID, Published workflow, reviewed grade, reviewed offering, and matching enrollment. The same eligibility is applied to aggregates, reports, notifications, and exports.',
                    'GWA uses eligible saved grade/unit snapshots and the configured repeat/nonacademic interpretation. INC does not enter numeric GWA.',
                    'Student report tokens are bound to session, user, student link, auth_version, and session_epoch. Previews/exports requery eligibility and expire after 30 minutes.',
                ]],
                ['title'=>'Reports, APIs, and file handling','items'=>[
                    'Dashboard and Student JSON responses use the same role/ownership checks as their pages. The Student API does not accept a client-supplied identity override.',
                    'Generate school-branded HTML print previews with timestamps; browser Print / Save as PDF supplies PDF output. Audit report generation and CSV export.',
                    'CSV imports require exact template columns, at most 500 rows/2 MB, a rollback-only preview, and confirmation within 15 minutes. Revalidate and commit the complete batch transactionally.',
                    'Escape spreadsheet formula prefixes in CSV output. School-logo uploads accept validated PNG/JPEG, at most 1 MB and 3000 pixels per side.',
                    'Restrict direct web access to config, includes, migrations, bin, tests, and private/dump files. Uploaded content must not execute as server code.',
                ]],
                ['title'=>'Configuration, backups, and deployment','paragraphs'=>[
                    'Provide DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, BACKUP_DIR, APP_ENV, and FORCE_HTTPS through the environment or an external CRYSTAL_CONFIG_FILE. Keep credentials and backup storage outside the public application directory. Do not commit live credentials, backups, or student records.',
                    'The current SQL backup format is v3/schema 003 with strict table/column manifests and SHA-256 checksums. Supported v2 data backups are validated and converted without inventing Student links. Arbitrary uploaded SQL is not executed.',
                    'Browser restoration requires a validated SQL or file-only ZIP preview, a confirmation token, and the text RESTORE within 15 minutes. SQL restoration is transactional, verifies relationships/counts, retains recovery data, and invalidates sessions. Full source restoration uses the explicit offline CLI.',
                    'On public hosting configure HTTPS, private credentials/storage, and daily INC maintenance. Optional scheduled backups require an actual server scheduler. Keep local demo accounts disabled in production.',
                ]],
                ['title'=>'CLI commands and verification','items'=>[
                    'php bin/console.php install — install into a new empty database with a unique ADMIN_INITIAL_PASSWORD; never reinstall an existing database.',
                    'php bin/console.php migrate — preview upgrades; add --apply to preserve a recovery snapshot and apply pending migrations.',
                    'php bin/console.php demo-data --apply — load the fictional local demonstration dataset and role accounts once.',
                    'php bin/console.php backup / expire-inc — create private backups and process expired INC records; source restoration uses restore-source with explicit apply/allow-source flags.',
                    'Run PHP syntax checks and the core, console, Student, backup, and Help/demo integration suites against new crystal_test_* databases. Browser checks cover role navigation, data scope, mobile layouts, and printing.',
                    'Production acceptance also requires the school’s approved policies/calendar and a successful complete enrollment-to-publication walkthrough. No software test result guarantees that an unconfigured school policy is correct.',
                ]],
            ],
        ],
    ];
}
