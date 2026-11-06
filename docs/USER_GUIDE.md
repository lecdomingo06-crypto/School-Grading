# Administrator, Faculty, and Student guide

New to the system? Start with the [step-by-step tutorial](TUTORIAL.md), which follows one class and student through setup, grade entry, publication, and Student viewing.

For a quick demonstration, open the login page's **Try a demo account** instructions. Choose Administrator, Faculty, or Student, enter the listed credentials, and answer the arithmetic question. The local sample dataset contains 100 fictional students with enrollment and grades. Your normal account remains separate. Open **Help** for the Product and Technical Requirement Documents; select **Print / Save as PDF** to save either document.

## Homepage and navigation

The homepage explains the system without showing private records or internal statistics. Sign In opens authentication; Request Account appears when registration is enabled. Signed-in users can open their role's dashboard. The responsive navigation highlights the current area and provides profile, notifications, and POST-based sign out.

## Administrator

1. Change your initial password; configure school identity and logo.
2. Add programs, school years, terms, sections, and subjects. Term dates must fit their school year; the INC deadline is the approved later academic-calendar deadline.
3. Create a grading-policy version, documenting weights, rounding, GWA rules, and actual approval reference. Policies cannot have formulas edited after creation.
4. Approve Faculty registrations or create accounts. Public registration accepts Faculty or Student requests; both start Pending and inactive. Public requests never create Administrator privileges. New administrator-created accounts and password resets require a password change.
5. Create subject offerings with a subject, term, section, faculty member, and policy. Enroll active students. Faculty assignment changes immediately change record visibility.
6. Configure active term and GWA interpretation in Settings.
7. Review submitted grades. Return with comments, approve, and publish. Reopening requires an approval/correction reference and is recorded in history. Reopening also permits a correction after the original submission deadline. This application workflow supports internal review; it does not replace the university's actual Dean/Registrar correction process.
8. Track at-risk follow-ups, review logs, generate reports, and maintain private backups.

### Create or approve Student access

Student academic records and login accounts are separate. The small legacy `install --demo` fixture creates academic student records without Student logins. The separate `demo-data --apply` loader creates the three advertised role accounts and links only its fictional Demo Student 001; real students still require verified administrator linking.

- To create access, open Users, expand Create an account, choose Student, and select the verified existing academic record. Set a unique temporary password and deliver it privately. The student must change it after signing in.
- For a pending Student request, verify identity through the school process. Open Verify student link, choose the correct record, and save. Then approve the registration. Approval is refused until the record is linked.
- Each academic student record can belong to only one login account. Students cannot claim a record by typing a student number.
- To correct or remove a link, use Verify student link. Changes are audited and existing sessions are revoked. Review the student's identity carefully before reassignment.
- Deactivation, role changes, and administrator password resets revoke previous sessions. A Student account without a link can access account details but cannot obtain academic records.

Use archive/deactivate for historical records. Student removal marks the student Inactive. An enrollment can be permanently deleted only before any grade history exists. Grades that have ever been submitted or published cannot be deleted. Offerings with grades cannot change their subject, term, section, or policy; create another offering for new academic arrangements.

## Faculty

- Register and wait for approval, or use the account created by your administrator.
- Update your profile and change temporary passwords when prompted.
- Open Classes and Enrollment to view/manage permitted records. Student creation and academic master-data management remain administrator duties; faculty can update details for students already assigned to them.
- Open Grades and load an assigned offering. Only enrolled students can receive grades.
- Enter numeric marks or qualifying INC standing plus unmet requirements. Preview the calculation and save a Draft.
- Submit before the deadline. Submitted, approved, and published results are locked against ordinary editing.
- Read returned-grade comments and notifications. Request administrator reopening for published corrections or approved INC completion.
- Generate reports for assigned records. Published is the default; choosing another workflow produces a visibly labeled working report.
- Record assigned intervention follow-ups and resolution actions.

## Reports and CSV

Generate reports from the report builder. Grade cards/history require a student ID; class records require an offering; semester summaries require a term. User/activity reports are administrator-only. Report generation and CSV exports are logged. A printable preview retains the school header and signature areas. Browser Print / Save as PDF provides PDF output without a separate PDF package.

Download CSV templates from Import / Export. Retain the exact headers. Use IDs from authorized class and master-data lists. Upload up to 500 rows, inspect row-level errors and the first 50 displayed rows, and confirm within 15 minutes. Every row is revalidated during confirmation; the batch commits together or rolls back. Preview uses rollback-only validation and does not create records, although MySQL may consume auto-increment values during validation. Imports create new records/draft grades, not silent updates or published grades. CSV exports escape potential spreadsheet formulas.

## Student

- Open My overview to see the active term, enrolled subjects/units, published results, GWA, and permitted reminders. Refresh the page for the latest results. With no active term configured, current-term statistics remain empty; historical published grades remain available.
- My subjects lists your enrollments, subject units, term, section, and assigned faculty. “Results are not yet published” reveals no draft mark or review status.
- My grades and Academic history show only your own Published results whose grade and offering have been reviewed. Filter by school year, term, subject, result, or publication date. Unpublished and unreviewed legacy results never enter these totals or GWA.
- Numerical grade 1.00 is highest; 3.00 is the lowest passing grade; 5.00 is failed. INC represents unmet requirements and is excluded from GWA. Eligible numeric results use saved credit-unit snapshots and the configured repeated-subject/nonacademic policy. Contact faculty or the school office about corrections or INC completion.
- My reports generates a published grade card, academic summary, or academic history. Generate a report before using print preview/Save as PDF or CSV. The preview expires after 30 minutes and always rechecks the current records; a reopened grade disappears from the preview and export. Generation and exports are audited.
- Student reports are system-generated student copies subject to school certification. They are not certified university transcripts.
- Notifications show account updates, permitted publication notices, and your published INC deadlines. Staff comments and intervention notes remain private. Reopening a grade removes its associated student notice from view.
- Profile permits email and password updates. Registered name and official academic details are read-only; request corrections from authorized school staff. Password changes require the current password.

Students cannot edit grades or enrollment, open class rosters, access staff tools, view other students' records, or see draft/submitted/approved-but-unpublished marks. Changing a student ID in a URL or form does not change ownership.

## Passwords and audit evidence

Passwords are bcrypt hashes. Example format: `$2y$12$gItjbGXcXNgNQHdRVbVa5eaACiDwVyRcFwz85tjV8vovZASk1POHG`. This is a demonstration hash, not a production credential.

For assignment documentation, create a demonstration account, open its row in phpMyAdmin's `users` table, and capture the `password_hash` column to demonstrate hashed storage. Use demonstration data, obscure unrelated identities, and keep actual credential material out of public documentation. Reports and audit snapshots never include password hashes.

Email-based forgotten-password delivery is not enabled. Administrators provide the implemented temporary-password reset. The Student portal is available after migration 003 and administrator account linking.
