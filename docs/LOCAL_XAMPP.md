# This computer's local XAMPP setup

Local address: **http://localhost/Crystal/**

The application database is `crystal_local`, with existing Administrator/Faculty accounts and sample academic records. The current application schema is `003_student_portal`; the upgrade preserves those records and creates no Student account or link automatically. For the recorded local upgrade result, see [VALIDATION.md](VALIDATION.md). The administrator must change any initial temporary password on first sign-in. Sample policy weights and calendar dates require actual school configuration before real use.

The local address now opens the public homepage. Sign In takes each approved account to its role's dashboard. To enable student access, sign in as an administrator, open Users, create or review a Student account, and verify its academic-record link. For a public request, save the link before approval. A Student academic record alone is not a login account. See [USER_GUIDE.md](USER_GUIDE.md).

Apache serves the current workspace through an alias. There is no separate copy to keep synchronized in `htdocs`; changes made to this project are served directly.

Local machine configuration:

- Apache alias: `C:\xampp\apache\conf\extra\httpd-crystal-local.conf`.
- The alias is included from `C:\xampp\apache\conf\httpd.conf` and permits local access only.
- External application configuration: `C:\xampp\crystal-local\config.php`.
- Private backups: `C:\xampp\crystal-local\backups`.
- PHP Zip is enabled in `C:\xampp\php\php.ini`.
- Original Apache/PHP configuration snapshots were preserved in `C:\xampp\crystal-local` before setup.

For future sessions, open XAMPP Control Panel, start Apache and MySQL if necessary, and visit the local address. No PHP terminal server is required. Do not rerun the installer on the installed database.

To check schema without changing data, run `php bin/console.php migrate` with the existing external configuration loaded. An applied 003 migration reports that it is already present. If an upgrade is pending, follow [MIGRATION.md](MIGRATION.md); `migrate --apply` writes a private pre-upgrade recovery dump before DDL. Do not change database credentials or global XAMPP configuration to run this application again.

For another computer or public hosting, use the general README and deployment guide rather than copying this machine's absolute-path Apache configuration.
