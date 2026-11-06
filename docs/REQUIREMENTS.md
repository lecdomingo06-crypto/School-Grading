# Implementation checklist

| Requirement | Implementation |
|---|---|
| Registration, login/logout, CAPTCHA, bcrypt, lockout | `includes/pages/login.php`, `register.php`, `logout.php` |
| Pending Faculty/Student requests; administrator account management | `includes/pages/users.php`, `account_service.php`; public registration cannot grant Administrator |
| Public homepage; role-aware responsive navigation and forms | `index.php`, shared layout, vendored assets; no public academic statistics |
| Product and Technical Requirement Documents via Help | `help.php`, `includes/help_documents.php`; public and role sidebar links, document contents and print/PDF |
| Visible demo credentials and populated sample database | Local `demo-data --apply` creates three separate sample role accounts and 100 fictional students/enrollments/grades; login instructions, idempotence, production denial |
| Verified Student account links; unique ownership; revocation | Migration 003, `account_service.php`; no automatic student-number claims |
| Student overview, subjects, published grades, academic history | `student.php`, `student_service.php`; Published/reviewed rows only |
| Student grade cards, summaries, PDF/CSV | Session-bound report tokens; ownership and current publication rechecked on preview/export |
| Student profile and private notifications | Read-only registered/academic identity; email/password updates; scoped publication and INC reminders |
| Session timeout, deactivation, role updates, reset revocation | `includes/core.php`, `profile.php`, `users.php` |
| Programs, years, terms, sections, subjects, offerings, enrollment CRUD | `records_service.php`, `includes/pages/records.php`, `academic.php` |
| Assigned-record permissions | Shared offering/student scopes and server-side service checks |
| ISU conversion, configurable weighting/rounding, policy versions | `grading.php`, policy management, `docs/GRADING_POLICY.md` |
| INC eligibility, completion evidence, calendar deadline, expiry | `grade_service.php`; daily `expire-inc` job |
| Draft/submitted/approved/published workflow and correction history | `grade_service.php`, `includes/pages/grades.php`, `grade_history` |
| Real-time cards, seven charts, activities, notifications, status | `dashboard_service.php`, polling `assets/js/dashboard.js` |
| Search, category, date, status, class/term/faculty filters | Record lists, `filters.php`, users, audit trail, reports |
| Eleven report types; school header, timestamp, print/PDF, CSV | `report_service.php`, `includes/pages/reports.php` |
| Interventions, advisers, follow-ups, statuses, resolution history | Intervention CRUD plus audit before/after records |
| CSV templates, validation preview, transactional commit, safe export | `import_service.php`, `includes/pages/import.php`, `csv.php` |
| Audit login/logout, mutations, reports, settings, backup/restore | Shared `audit()` and dedicated grade history |
| SQL backup/manifest, preview, rollback, session invalidation | `backup_service.php`, `backup_format.php`; v3/schema-003 plus safe v2 compatibility |
| Application/file ZIP backup; staged files; offline source restore | Backup services and `bin/console.php` |
| Optional scheduled backup | CLI schedule, lock, retention, cPanel cron instructions |
| Identity/logo/settings, active term, default GWA policy | `includes/pages/settings.php` |
| Profile/password changes and scoped notifications | Profile and notification services/pages |
| Safe upgrade and fresh installation | Schema 003; preview/apply migration, pre-upgrade recovery snapshot, no inferred Student links |
| SSL / cPanel | Production HTTPS requirement and deployment instructions; certificate/hosting setup remains an external deployment task |
| Email forgot-password | Optional; not enabled. Administrator temporary-password resets are implemented. |

The service suite uses isolated databases for grading boundaries, workflow locks, INC, weighted averages, CSV validation, restoration, archive safety, deactivation, and legacy migration. Student checks cover unique links, published-result scope, report tokens, notification isolation, and student-bearing backups. HTTP checks exercise direct URLs, crafted cross-faculty and cross-student requests, staff-route denial, CSRF, registration privilege restrictions, profile protection, reports, exports, password-change enforcement, session revocation, and lockout. Browser verification covers navigation, forms, charts, mobile layouts, grade preview, and print. See [VALIDATION.md](VALIDATION.md) for actual run results and limitations; listed coverage is not a claim that every browser was tested.

Institutional confirmation still required: approved assessment weights, decimal conversion convention, repeat/nonacademic GWA treatment, actual academic-calendar completion dates, and the school's approval/correction authorities. Configuration records support these decisions without silently rewriting historical grade values.
