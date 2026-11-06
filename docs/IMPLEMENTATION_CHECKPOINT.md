# UI and Student portal checkpoint

Task started November 6, 2026. Implement the user's attached UI/homepage/Student-role specification locally; preserve existing data, faculty ownership, ISU policy and XAMPP configuration.

Work areas:
- Root: schema 003, explicit staff authorization, safe student-account linking and registration, profile protection, publication notifications, backup/migration compatibility, local upgrade and verification.
- UI: public homepage, shared navigation/layout and responsive green/cream/gold styling.
- Student portal: own enrollment, published reviewed grades only, student reports and scoped notifications.
- Verification: dedicated isolated Student access-control regression tests.

If execution is interrupted, preserve all files and inspect this checkpoint plus current source before continuing. Do not rerun a completed migration, reseed the installed database, or replace existing user data. Update this file with verification results and remaining work as implementation progresses.

Status: implementation and local verification completed November 7, 2026 (Asia/Manila); Help/demo additions below are also complete.

Latest addition:
- `help.php` provides Product and Technical Requirement Documents through public Help navigation and a Help group for all three roles. Documents include contents links and print/PDF output.
- `includes/demo_service.php` and `php bin/console.php demo-data --apply` add 100 fictional students, 100 enrollments, 100 grades, and separate Administrator/Faculty/Student demo accounts. The login page advertises only working, enabled sample credentials. Production disables demo loading, sign-ins/sessions, and credential display.
- The local sample load preserved the original 2 accounts and 2 students. Current totals: 5 accounts, 102 students, 100 grades; one linked demo Student account. The Demo term/training policy are selected. Pre-load recovery backup: `C:\xampp\crystal-local\backups\backup_20261107_005315_7c75e20d92e4.sql`.
- 500 checks passed for this addition (77 core, 11 CLI, 28 Student service, 178 Student HTTP, 24 backup, 42 Help/demo service, 140 Help/demo browser). PHP syntax passed for all 75 files. See [VALIDATION.md](VALIDATION.md). Demo loading is complete; another run is idempotent and must not reset accounts.
- Source/documentation updates are intended for the existing GitHub `main` branch. Private configuration, database dumps, and backup archives remain outside Git.

Completed:
- Public homepage, authentication Home links, and shared responsive green/cream/gold layout.
- Explicit Administrator/Faculty/Student authorization and role navigation.
- Administrator-verified unique Student account links, public Pending requests, read-only academic identity, and immediate session revocation on access changes.
- Student subjects, reviewed Published grades/history/GWA, scoped notifications, printable reports and safe CSV export.
- Migration 003, complete fresh schema, v3 backup restoration with Student links, and safe v2 database-backup conversion.
- Updated README, setup/user/migration/deployment guides and requirement checklist.
- Added an original green/cream/gold academic system logo; see [LOGO.md](LOGO.md) for the asset and generation prompt.

The installed `crystal_local` database is already at 003. Original migration recovery dump: `C:\xampp\crystal-local\backups\backup_20261107_000055_956056c729f4.sql`. At that upgrade the existing accounts (2), academic student records (2), and grade count (0) were preserved; no Student account was inferred. The later authorized sample-data load is recorded above. Do not reinstall or apply 003 again. Apache serves the workspace directly at `http://localhost/Crystal/`.

Verification: 500 automated checks passed across core services, staff HTTP, CLI, Student services/HTTP, Student backup compatibility, and authenticated browser QA; plus 12 public/authentication layout checks. All 70 PHP files and three application JavaScript files pass syntax checks. See [VALIDATION.md](VALIDATION.md) for details and retained artifact paths. Temporary test servers stopped; keep the installed Apache/MySQL services available.

School setup remaining: approve the institutional policy inputs and actual academic calendar, verify/create Student accounts and their links, then enter/review/publish real academic records. These are administrator data/configuration steps; no identities or official policy approvals were fabricated. No website was published.
