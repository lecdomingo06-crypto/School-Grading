**Step-by-step tutorial: Student Grade & Performance Analysis DDS**

Follow this walkthrough in order. It takes one subject and one student through setup, enrollment, grade entry, review, publication, and Student viewing. Reuse existing correct records instead of adding duplicates. Use clearly identified practice records for a demonstration, and use the school's approved information for actual academic work.

For a quick tour, use the demo credentials shown on the login page. The Administrator, Faculty, and Student sample accounts already have a fictional class, 100 student records, enrollment, and grades prepared. The Student demo sees only Demo Student 001. **Help → Product requirements / Technical requirements** opens the printable requirements webpages. Follow the steps below when you want to set up your own records.

**1. Open the website**

Open XAMPP Control Panel. Start Apache and MySQL if they are stopped, then open [http://localhost/Crystal/](http://localhost/Crystal/). Select **Sign in** on the homepage. This computer already has an installed database; no installation or database import is needed to start using it.

On a phone or narrow window, select **Menu** to open the sidebar. **Visit public homepage** returns to the public site. Use **Sign out** at the top when changing accounts during this tutorial.

**2. Sign in as administrator**

Enter username `admin`, your current administrator password, and the answer to the **Solve …** arithmetic question. Select **Sign in**.

If you are directed to the password screen, enter your current temporary password and a different new password, then select **Update password**. Passwords must be 10–72 bytes; a long unique password made from ordinary letters, numbers, and symbols fits this requirement. After changing it, open **Dashboard**.

The dashboard summarizes permitted records, grade transactions, activities, published academic performance, and deadlines. An empty chart can simply mean no relevant records exist yet.

**3. Prepare the academic structure**

Open **Academic setup**. Each management screen has a collapsed **Add …** panel. Expand it, fill in the fields, and select **Save record**. Select a valid value in every required dropdown, including Active/Yes/No fields.

Create or check these records in order:

| Academic setup area | Information to enter |
|---|---|
| Courses / programs | Program code, program name, Active = Yes |
| School years | School-year name, starting date, ending date, Active = Yes |
| Terms & deadlines | School year, term name, dates, submission deadline, INC deadline, Active = Yes |
| Sections | Program, section name, year level, Active = Yes |
| Subjects | Subject code, title, credit units, whether it is academic, Active = Yes |
| Grading policy versions | Unique version name, assessment weights, percentage conversion, repeated-subject GWA rule, nonacademic inclusion, approval status, notes |

For example, a program might be `BSIT`, a section `BSIT 1-A`, and a subject `IT101 — Introduction to Computing`, with 3 credit units. These are practice examples; use your actual program and subject information.

Term dates must fit inside their school year. The INC deadline must be later than the term's ending date. The submission deadline cannot precede the term's starting date. Use the approved academic calendar.

Use an institutionally approved grading-policy version for actual submissions. The initial 50/50 weights and percentage-conversion settings are application defaults requiring school confirmation. When an existing policy has actually been approved, enter its **Institutional approval reference** and select **Record approval**. Submission is blocked while a policy is unapproved. For practice, identify demonstration policies clearly rather than recording a fictitious institutional approval.

Policy formulas cannot be edited after creation. Create a new version for a changed formula; saved grades retain their original policy and credit-unit snapshots. See [GRADING_POLICY.md](GRADING_POLICY.md) for the meanings of these settings.

**4. Give the faculty member access**

Open **Accounts**, then expand **Create an account**. Enter the faculty member's full name, unique username, and temporary password. Choose **Faculty** as the role and leave the Student academic record field blank. Select **Create account**. Provide the temporary password privately; the faculty member must change it after signing in.

Alternatively, the faculty member can select **Request an account** on the public site, choose Faculty, and submit the form. An administrator then opens **Accounts**, finds the Pending request, verifies it, and selects **Approve**. Only approved, active Faculty accounts appear when assigning a class.

An existing approved Faculty account may be reused. Account requests are available only when **Allow account requests** is enabled in **System settings**.

**5. Add the student's academic record**

Open **Students**, expand **Add Students**, and enter the student number, full name, program, section, year level, and academic status. Choose **Active** for a currently enrolled student. Leave the honorable dismissal date blank when none applies. Select **Save record**.

The program and year level must match the chosen section. Check existing student numbers before creating another record.

A student academic record stores academic identity. It does not create a Student username or password; that happens in step 8.

**6. Create the class and enroll the student**

Open **Classes & assignments** in the sidebar, or **Academic setup → Faculty assignments**. Expand the Add form. Select the subject, term, section, assigned faculty member, grading-policy version, and Active = Yes. Select **Save record**.

This combination is called a *subject offering*: a particular subject taught by a particular faculty member to a section in a term.

Next open **Enrollment**, expand **Add Enrollment**, select that subject offering and the student, choose **Enrolled**, and select **Save record**. Repeat for each student and subject. Creating a class does not automatically enroll everyone in its section.

**7. Select the current term and school settings**

Open **System settings**. Complete the school name, campus, and report header. Choose the **Active academic term** and **Dashboard / default report GWA interpretation**. Review the other required settings, then select **Save settings**. The logo-upload field is optional; the bundled system logo is available by default.

Creating a term marked Active and selecting it as the current term are separate steps. The selected current term controls current-term dashboard and Student overview summaries.

The Settings GWA interpretation controls summaries; the grading-policy version on a class controls the grade calculation saved for that class. A school-logo upload must be PNG or JPEG, at most 1 MB and 3000 pixels on either side.

**8. Create and link the Student login**

Open **Accounts → Create an account**. Enter the student's name, unique username, and temporary password. Choose **Student**, select the verified existing **Student academic record (Student accounts only)** created in step 5, and select **Create account**.

For a public Student account request, verify the student's identity through the school process first. In **Accounts**, expand **Verify student link**, choose the correct academic record, and select **Save verified link**. Then select **Approve**. A Pending Student request cannot be approved until the record is linked.

Each academic student record can be linked to one login account. Entering a student number on a public form never claims a record. Pending requests cannot sign in; an approved but unlinked Student account cannot read academic information.

**9. Enter a grade as faculty**

Select **Sign out**, then sign in with the Faculty account. Change the temporary password if prompted. Open **Assigned classes** to check that the assignment exists, then open **Grade entry**.

Choose the class under **Choose an assigned offering** and select **Load class roster**. Choose the **Enrolled student**, enter **Midterm / passing standing** and **Final / passing standing**, and choose **Numeric** under Result. Review the calculated preview and select **Save draft**.

For an easy practice calculation, enter 85 for both marks. The percentage remains 85 for any configured split of midterm/final weights, and the numerical equivalent is 2.25. These are practice marks, not actual student results. The server calculates the saved grade from the class's policy.

Saving returns to the grade list. Use **Open** to edit the draft, or load the class again to enter another student's grade. Editing an existing draft requires a **Change reason / correction reference**. **History** shows earlier actions and changes.

**10. Submit the grade for review**

In the saved grade's row, select **Submit** and accept the confirmation prompt. The workflow becomes **Submitted** and ordinary editing is locked.

Submission requires an approved policy and an open submission period. If corrections are needed, follow the administrator return/reopening process in step 14.

**11. Approve and publish as administrator**

Sign out of the Faculty account and sign back in as administrator. Open **Grade review**. Use **Search and filter grades** to find the student, class, and Submitted workflow, then select **Apply filters**.

Review the marks and calculation. In the row's **Reason / approval reference** field, enter the review reference or reason and select **Approve**. Accept the confirmation. The grade becomes **Approved**.

Enter the publication reason/reference and select **Publish**, then confirm again. The grade becomes **Published**. Approval and publication are separate actions. Students see the result only after publication and review requirements have been satisfied.

The complete workflow is:

`Faculty saves Draft → Faculty submits → Administrator approves → Administrator publishes → Student views`

**12. View the result as the student**

Sign out and sign in with the linked Student account. Change its temporary password when prompted. The Student overview shows current-term subjects, enrolled credit units, published results, GWA, and permitted reminders.

| Student menu | What to do |
|---|---|
| Overview | Check current-term enrollment and published summaries; refresh the page for the latest results |
| My subjects | Check subject, units, term, section, faculty, and enrollment status |
| My grades | Find published grades using school-year, term, subject, result, or publication-date filters |
| Academic history | Review published results and summaries across terms |
| My reports | Generate a published grade card, academic summary, or academic history |
| Notifications | Read account/publication updates and applicable published INC reminders |
| Profile & password | Update permitted contact details or change the password using the current password |

The student can see only their own reviewed Published results. Draft, Submitted, and Approved-but-unpublished marks are excluded. **Results are not yet published** is a neutral availability message; it does not identify the student's eventual result or the staff review stage.

Official name, student number, program, section, enrollment, and grades are read-only for Students. Request corrections through authorized school staff.

**13. Generate, print, and export reports**

As administrator or faculty, open **Reports**, expand **Report builder**, choose a report type, select its filters, and select **Generate report**. Faculty reports cover assigned records. Choose a student for a Student grade card/history, an offering for a Faculty class record, or a term for a Semester summary.

As a student, open **My reports**, choose a report type and filters, then select **Generate report**. The system supplies your own identity automatically.

From either report preview, select **Print preview / Save as PDF**. In the browser print dialog, choose your printer or **Save as PDF**, review the layout, and print/save. Use **Export CSV** to download a spreadsheet-compatible file. A preview expires after 30 minutes; generate it again if needed.

School identity, logo, and generation time appear in the report. Student copies are system-generated and subject to school certification. Report generation and exports are recorded in the audit trail. Student report date filters refer to publication dates; staff grade-report date filters refer to grade-entry dates.

**14. Handle returned grades, corrections, and INC**

For a Submitted or Approved grade requiring changes, the administrator supplies a reason and selects **Return**. The grade returns to Draft, allowing the assigned faculty member to correct it, save, and submit again within the permitted period.

For a published correction or an authorized deadline extension, the administrator supplies a correction/approval reference and selects **Reopen / extend**. The reopened result is hidden from Student grade views until it completes review and publication again. The faculty member corrects the Draft, provides the change reason, and resubmits. The administrator approves and publishes again. **History** retains the actions.

For an eligible incomplete result, faculty choose **INC — unmet requirements**, retain passing-standing marks, and enter **Unmet requirements (required for INC)**. The term must have a valid future INC deadline. INC is excluded from GWA. Completion requires the school's authorized process and a **Registrar completion form / receipt reference**; a published INC must be reopened before it is changed to Numeric, resubmitted, approved, and published. Expired pending INC records are converted by the system's expiration process.

**15. Track follow-ups and review activity**

Open **Interventions** to record an assigned student/class, responsible faculty member, reason, follow-up date, notes/actions, and status. Update status from Open to In Progress or Resolved as appropriate, and use **Follow-up history** to review changes. Faculty work only with assigned records. Internal notes remain restricted to authorized staff.

Administrators can open **Audit trail** and filter activity by user, action/category, and date. Grade changes also have their dedicated **History** on the grade list.

**16. Import larger batches when needed**

Administrators and authorized faculty can open **Import / export**. Download the appropriate CSV template, keep its exact headings, and use the IDs from the permitted student/class/master-data lists. Fill in your new records, choose the import type, upload the CSV, and select **Validate and preview**.

Resolve all reported errors, preview again, then select **Confirm import** within 15 minutes and accept the confirmation. The maximum is 500 rows and 2 MB. The batch is validated together and does not commit when errors remain. Importing grades creates Drafts; it does not publish results. The Export authorized records area provides **Download CSV**.

**17. Make a backup and sign out**

As administrator, open **Backups**. Select **Backup database (.sql)** to save accounts, academic records, settings, and activity data. Use **Backup application & assets (.zip)** for source/assets and **Backup uploaded documents (.zip)** for uploaded files. Keep dated copies in an appropriate private location.

Restoration is for an actual recovery task. It is not required to complete this tutorial. When needed, select a supported application SQL or file-only ZIP under **Validate a backup**, then **Validate and preview**. Review the contents, enter `RESTORE`, and select **Confirm restoration** within 15 minutes. SQL restoration replaces current application data and signs everyone out. Full application/source ZIP restoration uses the documented offline process rather than the browser. See [DEPLOYMENT.md](DEPLOYMENT.md).

Use **Sign out** after finishing. Account deactivation, password resets, role changes, and Student-link changes revoke earlier sessions. Administrators reset another user's password from **Accounts → Reset password / change role → Reset password**; the user must change the temporary password on their next sign-in.

**When something does not appear**

| What you see | What to check |
|---|---|
| Website will not load | Apache and MySQL are running; address is `http://localhost/Crystal/` |
| Username/password rejected | Correct credentials and arithmetic answer; account is Approved and active; repeated failures can require a 15-minute wait after the latest failed attempt |
| Password screen keeps appearing | Complete the temporary-password change using the current password |
| Empty faculty class list | Administrator assigned this exact active Faculty account to the class; assignment is reviewed |
| Empty grade roster | Student is enrolled in that exact offering with status Enrolled |
| Grade entry blocked | Offering and term are active/reviewed, student is active, and an Enrolled record exists |
| Submit is blocked | Policy is approved and deadline has not passed, or an administrator has authorized reopening |
| Student access is awaiting verification | Administrator saved the correct account-to-student link and approved/activated the account |
| Student subjects exist but a grade is missing | Correct account link and offering; grade and offering are reviewed; workflow is Published; clear filters |
| Current-term overview is empty | Administrator selected the correct Active academic term in System settings |
| Report is empty or expired | Correct filters and Published results exist; generate a fresh preview |
| Wrong official Student details | Authorized staff correct the academic record; Student profile cannot edit it |
| A record cannot be deleted | It has academic history; use the permitted Archive / remove action and retain that history |

For your first walkthrough, the finish point is simple: the same enrolled student's grade has moved from Draft to Published, that student's linked account can view it, and the student can print their own grade card.

Additional references: [User guide](USER_GUIDE.md), [local XAMPP setup](LOCAL_XAMPP.md), [grading-policy explanation](GRADING_POLICY.md).
