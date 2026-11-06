# cPanel deployment and recovery

## Hosting configuration

1. Back up an existing installation and take it out of traffic before migration.
2. Create a subdomain such as `grades.yourwebsite.com` and a dedicated MySQL/MariaDB database/user. Grant only the privileges needed for installation/migration; the runtime account needs ordinary application CRUD privileges, not global server administration.
3. Upload the application, including `.htaccess`. Apache 2.4 with overrides must honor the root and internal-directory rules. The PHP built-in server uses `bin/router.php` for local development only; do not use it as a production server.
4. Use PHP 8.1+ with PDO MySQL, mbstring, fileinfo, OpenSSL, sessions, and Zip. Disable production error display and send error logs to private storage outside `public_html`.
5. Copy `config/production.example.php` **outside public_html**. Set credentials, private `BACKUP_DIR`, `APP_ENV=production`, and `FORCE_HTTPS=1`. Configure `CRYSTAL_CONFIG_FILE` in the PHP/Apache environment to point to that file. The application refuses an external configuration file inside its source directory. Never put real secrets in source files or downloadable archives.
6. Run installation against an empty database with a unique `ADMIN_INITIAL_PASSWORD`, or run the reviewed migration against the existing database. The current schema is `003_student_portal`. Preview with `migrate` before `migrate --apply`; preserve the private recovery snapshot. Remove the initial password from the shell environment afterward.
7. Enable Let's Encrypt/AutoSSL in cPanel. Confirm certificate issuance, then enable cPanel **Force HTTPS Redirect** for the subdomain. The application requires HTTPS in production and enables secure cookies/HSTS on HTTPS. Do not trust an arbitrary client-supplied forwarding header to spoof HTTPS.
8. Set restrictive owner-based permissions (typically directories 750/755, files 640/644 as the host requires, private backups/configuration 700/600). Only uploaded-file storage and private backup storage need runtime write access. Do not make the application world-writable.
9. Configure the actual campus identity, academic calendars, policies, assignments, and accounts. Remove demonstration accounts/data from public installations. Verify each Student identity and link in Users before approval; migration and demo installation create no Student login access automatically. The homepage is public, while academic information remains authenticated.
10. Verify HTTPS, all three role dashboards, unauthorized direct URLs/exports, unpublished-grade exclusion, reports, and an isolated recovery test. This repository does not itself install a certificate or deploy hosting.

## Scheduled maintenance

Use your hosting account's actual PHP binary and external configuration path. Example daily scheduler at 02:00 in the hosting cron timezone:

```cron
0 2 * * * CRYSTAL_CONFIG_FILE=/home/USER/private/crystal.php /usr/local/bin/php /home/USER/public_html/grades/bin/console.php backup --scheduled
15 2 * * * CRYSTAL_CONFIG_FILE=/home/USER/private/crystal.php /usr/local/bin/php /home/USER/public_html/grades/bin/console.php expire-inc
```

The backup command reads the configured manual/daily/weekly/monthly schedule, tracks the last completed period, and uses a process lock. A daily cron invocation supports all schedules. Alternatively, use `0 2 * * 0` for a weekly job or `0 2 1 * *` for a monthly job. Backups must remain outside the public document root. Retention removes dated generated backup files older than the configured period; copy important recovery sets elsewhere before expiration. Protect scheduled command output privately. PHP/application dates use Asia/Manila; confirm the cPanel cron account timezone separately.

## Database backup and restoration

The SQL download contains table data, a v3/schema-003 manifest, required columns, row counts, and per-table checksums. It does not contain schema DDL. Keep matching source and `schema.sql`. Browser restore accepts supported v3 backups and legacy v2/schema-002 backups. A v2 restore into schema 003 adds no Student links and treats all legacy notifications as staff-only. It replaces the records from the chosen backup; it does not merge later accounts or grades.

Restore previews table counts, requires RESTORE confirmation, saves a pre-restore private data backup, then restores in foreign-key-safe order inside a transaction. Student account links are deferred until student rows exist, resolving the users/students reference cycle without assigning new ownership. Database constraints validate linked student identities; the restore also checks row counts, account roles, and grade/enrollment relationships. The restore records the actor where still present, rotates the global session epoch and account versions, and requires everyone to sign in again. Failure rolls back database changes.

For a lost database, install/import the matching schema in a new empty database, create a temporary recovery administrator through the secure installer, then use the supported browser restoration flow. The recovered users replace that temporary account. Keep backup archives private: SQL backups contain sensitive academic data and password hashes.

## File and source recovery

File-only ZIP backups contain supported PNG/JPEG/PDF/text documents directly inside `uploads/`. They carry a manifest with file sizes and SHA-256 checksums. Browser restoration rejects executable/source archives, unsafe paths, symlinks, duplicate paths, unexpected entries, incompatible versions, and oversized expanded archives. Files are staged privately, validated, copied with before-images retained, and verified; copy failures trigger rollback. Restoration replaces listed files and does not delete unrelated files.

Application ZIP backups contain source, assets, uploaded files, migrations, and configuration templates. Runtime secrets, external production configuration, old backup archives, logs, and temporary files are excluded. Restore actual production credentials separately from the secured external copy.

To restore application source, stop traffic and jobs, verify that the archive is a **trusted operator-owned source backup**, keep an independent current copy, and run offline:

```text
php bin/console.php restore-source /PRIVATE/PATH/application.zip --apply --allow-source-code
```

Checksums detect corruption; they do not authenticate the creator of a malicious source archive. Source restoration is intentionally an explicit CLI operation with deployment-level trust. It preserves overwritten files in a private `rollback-*` directory and never accepts a source archive through the browser. Restore only a version matching the installed schema. Retain rollback data until verification is complete; never copy rollback directories into the public document root.
