# Validation results

## Help documents and populated demonstration — November 7, 2026

The latest change adds public and role-sidebar Help navigation, printable Product/Technical Requirement Documents, local demo-login instructions, and an explicit sample-data loader. The following checks were run for this change; **500 passed**:

| Suite | Passed |
|---|---:|
| `php tests/run.php` — grading, workflow, reports, files, sessions, migration | 77 |
| `php tests/console.php` — installer and CLI regression | 11 |
| `php tests/student.php` — Student services | 28 |
| `php tests/student.php` — Student HTTP access controls and reports | 178 |
| `php tests/student-backup.php` — Student-bearing and legacy backup restore | 24 |
| `php tests/help.php` — 100 datasets, preserved records, repeat-run behavior, hashes, demo roles, Student scope, production restrictions | 42 |
| `node tests/help-browser.cjs` — guest/all-role Help, demo login, desktop/390/320 layouts, active navigation, PDF, no PHP/browser errors | 140 |
| **Current verification total** | **500** |

All 75 PHP files passed syntax checks. The new browser runner passed `node --check`; Git whitespace checks passed. Its artifacts, including a Product Requirements PDF, are under `%TEMP%\crystal-browser-qa\artifacts\crystal_test_help_20261106175034_39e71d`. Test servers stopped after completion. These tests used only fresh disposable test databases.

The installed `crystal_local` database was then populated through `demo-data --apply`, after a private SQL recovery backup. It now contains **5 accounts, 102 students, and 100 grades**: the original 2 accounts/2 students were retained, and 3 separate demo accounts/100 fictional students were added. Each sample student has an enrollment and grade. The sample class contains 80 Published, 10 Draft, 5 Submitted, and 5 Approved results. The demo Student is linked to Demo Student 001; demo Faculty owns the sample class. The Demo term and training-only GWA policy are selected. No official policy approval was inferred.

The installed login, Product Requirement Document, and Technical Requirement Document each returned HTTP 200 without rendered PHP errors; the login advertised the verified sample credentials. The same dataset is reproducible from source with the documented CLI command. No private configuration, live data dump, or recovery backup is committed to GitHub.

## Previous UI and Student-portal milestone

Verified locally November 6–7, 2026 (Asia/Manila), using PHP 8.2.12, MariaDB 10.4.32, Node.js 24.13.0, and headless Google Chrome through Playwright. PHP Zip is enabled in this computer's XAMPP installation. On other installations, enable Zip before running backup checks; do not load the extension twice.

| Suite | Passed | Coverage |
|---|---:|---|
| `php tests/run.php` | 77 | ISU conversion boundaries, decimal conversion and weights, enrollment/grade uniqueness, grade submission/review/publication/correction history, INC completion/expiry, GWA rules, eleven staff report types, intervention history, CSV preview/commit, backup restoration/rollback, sessions, ZIP safety, legacy migration |
| `node tests/http.mjs` | 70 | Administrator screens, internal-file protection, CSRF, settings/policy approval, interventions, Faculty direct-URL and crafted-POST isolation, scoped dashboard API, reports/auditing/export, imports, v3 backup download, registration privilege protection, account revocation, forced password change, logout, login limiter |
| `php tests/console.php` | 11 | Empty-database install at schema 003, explicit demo seeding, refusal to overwrite existing databases, scheduled/manual backups, duplicate-period suppression, INC maintenance, source-restore authorization, migration preview/recovery/idempotence |
| `php tests/student.php` — service checks | 28 | Verified unique links, pending/unlinked accounts, Student ownership, reviewed Published results, unpublished/legacy exclusion, authorized notifications, own reports/GWA, backup link/audience metadata |
| `php tests/student.php` — HTTP checks | 178 | All Student views/APIs, cross-student requests, unpublished-result exclusion, report/export scope, notification scope, staff routes and crafted POST denial, read-only identity, registration role/link injection, approval/linking, password/link/role/deactivation session revocation |
| `php tests/student-backup.php` | 24 | Actual v3 restore of linked Student accounts and notifications, missing/duplicate/invalid link rollback, sessions on failure, authentic v2 parsing/restoration into schema 003 without inferred identity links or student-visible staff messages, checksums |
| `node tests/student-browser.cjs` | 112 | All three role layouts/navigation, Student views at desktop/mobile widths, unique IDs, no page overflow, desktop Menu visibility, mobile current-link focus/Escape/Tab trapping/link dismissal, own report scope, school print header/PDF, clean browser/PHP logs |
| **Automated total** | **500** | **All passed** |

Additionally, the public homepage, login, and registration passed **12 browser layout checks** at 1440, 768, 390, and 320 pixels: no overflow, duplicate IDs, missing resources, PHP warnings, or browser errors; one main heading and a usable keyboard skip link. All 70 PHP files passed `php -l`; all three application JavaScript files passed `node --check`. The earlier ten-check staff browser suite is recorded in the previous milestone and is not counted again above.

The subsequently requested logo was checked separately: the new PNG serves as `image/png` with HTTP 200, the installed homepage and sign-in page reference it, and neither page renders PHP errors. The shared fallback retains uploaded school logos.

During that check, one initial homepage request returned HTTP 500 with a fallback-database connection error. It did not recur in 20 sequential and 40 concurrent read-only requests across the homepage, login, and registration; all 60 returned HTTP 200 with the new logo and no rendered warnings. No server configuration was changed during logo integration. If this returns, inspect the Apache external-configuration environment before changing database data.

Tests create only newly named `crystal_test_*` databases and retain them for inspection. They do not reset installed application accounts or restore the school's database. File tests used a uniquely named temporary upload and removed it afterward. Temporary HTTP servers were stopped after verification. Screenshots, logs, and PDFs are under `%TEMP%\crystal-browser-qa\artifacts`; the final authenticated browser run is in `crystal_test_student_20261106171203_ad46b3`.

## Installed local application

The configured local database `crystal_local` was separately upgraded to `003_student_portal` after saving a private recovery SQL dump. Its two accounts and two academic student records were preserved; its grade count remained zero. No Student accounts were inferred or automatically linked. The existing Apache workspace alias remains in use; `http://localhost/Crystal/` returns the public homepage with HTTP 200 and no rendered PHP errors. This local upgrade is distinct from disposable test databases.

The administrator must verify and link Student accounts in Users before granting academic access. School policy approval, campus calendar dates, assessment weights, rounding, and GWA interpretation still require the school's decisions. cPanel SSL/cron configuration belongs to a future deployment; no production website was published.
