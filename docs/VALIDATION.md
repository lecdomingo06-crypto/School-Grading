# Validation results

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
