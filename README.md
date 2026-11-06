# Student Grade & Performance Analysis DDS

PHP 8.1+ and MySQL/MariaDB academic-record management with Administrator, Faculty, and Student access. Includes a public homepage, a responsive green/cream/gold interface, and a private Student portal. Uses the ISU Section 18 numerical conversion table. Academic weighting, decimal conversion, and GWA interpretation are explicit application policies requiring school approval.

Already using this computer's XAMPP installation? Open **http://localhost/Crystal/** and follow [LOCAL_XAMPP.md](docs/LOCAL_XAMPP.md). Do not reinstall an existing database.

For a complete beginner walkthrough from class setup to a student viewing published grades, use the [step-by-step tutorial](docs/TUTORIAL.md).

## New local installation

Requirements: PHP with PDO MySQL, mbstring, fileinfo, OpenSSL, sessions, and Zip; MySQL/MariaDB; Apache for deployment. Assets are vendored so charts and styling do not require an internet connection.

1. Create a **new empty** UTF-8 database, for example `crystal_local`. Do not select an existing production database for installation.
2. Configure the process environment. PowerShell example:

```powershell
$env:DB_NAME = 'crystal_local'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_USER = 'root'
$env:BACKUP_DIR = 'C:\private-crystal-backups'
$env:ADMIN_INITIAL_PASSWORD = 'CHOOSE-YOUR-OWN-UNIQUE-PASSWORD'
php -d extension=zip bin/console.php install
Remove-Item Env:ADMIN_INITIAL_PASSWORD
php -d extension=zip -S 127.0.0.1:8080 bin/router.php
```

Use `-d extension=zip` only if Zip is not already enabled in `php.ini`. Do not enable it twice. For XAMPP/Apache enable `extension=zip` in the PHP configuration and restart Apache. Set `DB_PASSWORD` when the database account requires it.

3. Open `http://127.0.0.1:8080`. Sign in as `admin` (or the value of `ADMIN_INITIAL_USERNAME`) with the password you selected. Change the initial password when prompted.
4. Configure school years, terms, programs, sections, subjects, and grading policies in Academic setup.
5. Create or approve faculty accounts, create subject offerings, enroll students, and configure the active term and default GWA interpretation in Settings.
6. In Users, create Student accounts and link each to the verified existing student record. Alternatively, review a pending Student registration, save its verified link, then approve it. One student record can be linked to one account. No student receives access merely by supplying a student number.

For demonstration data add `--demo` to **the initial install command** and set a unique `DEMO_FACULTY_PASSWORD`. This creates one program, section, class, a faculty account named `teacher`, and two student academic records; it does not create Student login accounts or fabricate official grades. Password change is mandatory. The demo policy stays unapproved. Demo calendar dates are illustrations, not the ISU calendar. Do not use demonstration accounts on a public server.

`schema.sql` is the complete version `003_student_portal` fresh schema without default accounts. If importing it through phpMyAdmin, create the first administrator through an authorized CLI/database process using `password_hash()`; the normal recommended installer handles this securely. Do not run `install` after manually importing the schema: it deliberately refuses nonempty databases.

## Existing installation

Follow [MIGRATION.md](docs/MIGRATION.md). The CLI previews pending work with `php bin/console.php migrate`; `migrate --apply` preserves a private recovery dump before changing schema. Migration 003 retains existing accounts and academic records without inferring student links or publishing grades. Installation and migration are different operations.

## Main routes

| Route | Use |
|---|---|
| `index.php` | Public homepage; signed-in users can open their role's dashboard |
| `student.php` | Student overview, own subjects, published grades/history, reports, notifications |
| `academic.php` | Programs, calendars, policies, assignments, enrollment |
| `students.php`, `subjects.php` | Student and subject records |
| `grades.php` | Drafts, INC, submission, approval, publication, change history |
| `reports.php` | Report builder, printable preview, PDF via browser, CSV |
| `interventions.php` | Advisers, follow-ups, actions, resolution |
| `import.php` | CSV templates, preview, transactional confirmation, exports |
| `users.php`, `settings.php`, `logs.php`, `backup.php` | Administrator operations |
| `profile.php`, `notifications.php` | Account and scoped updates |

Student grades and GWA use only their own Published, reviewed results. Draft, submitted, approved-but-unpublished, and unreviewed legacy marks are excluded from views, aggregates, notifications, and exports. Student reports are system-generated student copies, not certified university transcripts.

See [USER_GUIDE.md](docs/USER_GUIDE.md), [GRADING_POLICY.md](docs/GRADING_POLICY.md), [DEPLOYMENT.md](docs/DEPLOYMENT.md), and [REQUIREMENTS.md](docs/REQUIREMENTS.md).

The app's original green/cream/gold academic logo and its generation prompt are documented in [LOGO.md](docs/LOGO.md). Administrators can upload an approved school logo in Settings.

## Validation

```powershell
php -d extension=zip tests/run.php
php -d extension=zip tests/console.php
php -d extension=zip tests/student.php
php -d extension=zip tests/student-backup.php
```

The integration runners use uniquely named `crystal_test_*` databases and retain them for review. The original runner writes its database names to `%TEMP%\crystal-test-latest.json`. The Student runner requires a new `crystal_test_student_*` database, automatically starts/stops its own isolated PHP server, and runs `tests/student-http.mjs` through Node.js. It prints the retained fixture/state path. Clear a normal application's `DB_NAME` from the test shell before running Student tests. No normal application database is changed.

Start the development server using the retained test database and backup directory from that file, then run `node tests/http.mjs`. These HTTP tests intentionally change test accounts/passwords and import test data, so use fresh service-test fixtures for another full HTTP run.

For optional real-browser verification, make Playwright available outside the source tree and set `PLAYWRIGHT_PATH` to its package directory, then run `node tests/browser.cjs`. Chrome is used through `CHROME_PATH` or its default Windows installation path. The browser script expects the HTTP test fixture after `tests/http.mjs` and writes screenshots plus a printable PDF to the temporary QA directory.

For the homepage redesign and all three authenticated roles, `node tests/student-browser.cjs` creates its own disposable Student fixtures and starts/stops its own server. It verifies responsive navigation, keyboard controls, Student views, and printable reports. It uses the same Playwright/Chrome configuration and saves screenshots, a PDF, and its QA summary under `%TEMP%\crystal-browser-qa\artifacts\<test-database>`. The separate Student backup suite verifies actual restore and v2 compatibility using only fresh test databases.

After review, remove only the specifically named disposable test databases. The test runner never drops databases automatically.

Current verified results and any remaining checks are recorded in [VALIDATION.md](docs/VALIDATION.md).

Bootstrap 5.3.3 and Chart.js 4.4.7 are included under `assets/vendor` with their distributed license headers.
