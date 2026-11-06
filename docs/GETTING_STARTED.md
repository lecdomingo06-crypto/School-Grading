# Getting started

For complete prerequisites and installation commands, use the [README](../README.md). The current schema is `003_student_portal`. The installer requires a new empty database; existing installations use the [migration guide](MIGRATION.md).

On this configured computer, start Apache and MySQL in XAMPP and open **http://localhost/Crystal/**. The Apache alias serves this workspace directly. See [LOCAL_XAMPP.md](LOCAL_XAMPP.md); do not copy the project again or rerun installation.

1. Open the public homepage and select Sign In. Use an existing account or select Request Account when registration is open.
2. An administrator approves Faculty requests. For Student requests, the administrator verifies identity, saves the link to the correct student academic record in Users, then approves the request.
3. Administrators configure programs, calendars, grading policies, subjects, offerings, faculty assignments, and enrollment. Select the active term in Settings.
4. Faculty enter grades for their assigned offerings and submit them for review. Administrators approve and publish results.
5. Students open their private overview, subjects, published grades, academic history, reports, notifications, and profile.

No Student login account is seeded by the demonstration installer. Existing students in the academic records do not automatically have login access. Student accounts must be created or approved and linked by an administrator. Pending or unlinked accounts cannot read academic information.

The initial 50/50 assessment weights, decimal convention, GWA interpretation, and sample calendar dates require school approval. See [GRADING_POLICY.md](GRADING_POLICY.md). Student reports contain only their own published, reviewed results and are not certified transcripts.

Use the [user guide](USER_GUIDE.md) for account linking, grade workflow, and reports; the [deployment guide](DEPLOYMENT.md) for cPanel, HTTPS, backup, and recovery; and the [requirement checklist](REQUIREMENTS.md) for feature coverage. Verification results are in [VALIDATION.md](VALIDATION.md).
