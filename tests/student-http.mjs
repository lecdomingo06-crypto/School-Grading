import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

const origin = process.env.CRYSTAL_TEST_URL;
assert.ok(origin && /^http:\/\/127\.0\.0\.1:\d+$/.test(origin), 'Use an isolated local test server.');
assert.match(process.env.DB_NAME || '', /^crystal_test_student_[a-z0-9_]+$/);
const php = process.env.CRYSTAL_TEST_PHP || 'php';
let checks = 0;

function check(value, label) {
    assert.ok(value, label);
    checks++;
    console.log('PASS ' + label);
}

function fixture() {
    const result = spawnSync(php, ['tests/student.php', 'state'], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.error?.message || result.stderr || result.stdout);
    return JSON.parse(result.stdout);
}

class Client {
    cookies = new Map();
    async request(path, options = {}) {
        const headers = new Headers(options.headers);
        headers.set('Cookie', [...this.cookies].map(([name, value]) => name + '=' + value).join('; '));
        const response = await fetch(new URL(path, origin), { ...options, headers, redirect: 'manual' });
        for (const raw of response.headers.getSetCookie()) {
            const pair = raw.split(';')[0];
            const equals = pair.indexOf('=');
            this.cookies.set(pair.slice(0, equals), pair.slice(equals + 1));
        }
        return { status: response.status, headers: response.headers, text: await response.text() };
    }
    get(path) { return this.request(path); }
    post(path, data) {
        return this.request(path, { method: 'POST', body: new URLSearchParams(Object.entries(data).map(([key, value]) => [key, String(value)])) });
    }
}

function token(html) {
    const match = html.match(/name="csrf_token" value="([a-f0-9]+)"/);
    assert.ok(match, 'CSRF token is present.');
    return match[1];
}

function captcha(html) {
    const match = html.match(/Solve (\d+) \+ (\d+)/);
    assert.ok(match, 'Arithmetic CAPTCHA is present.');
    return Number(match[1]) + Number(match[2]);
}

async function login(client, username, password = state.password) {
    const page = await client.get('/login.php');
    return client.post('/login.php', { csrf_token: token(page.text), username, password, captcha: captcha(page.text) });
}

async function adminAction(data) {
    const page = await admin.get('/users.php');
    return admin.post('/users.php', { csrf_token: token(page.text), ...data });
}

function clean(page, label) {
    check(page.status === 200 && !/Fatal error|Warning:|Unable to complete this request/.test(page.text), label + ' renders');
}

const secretValues = [
    'Student Beta Confidential', 'ST-BETA', '77.77',
    '91.11', '92.22', '93.33', '94.44', '95.55',
    'INTERNAL_REQUIREMENT_NOTE', 'INTERNAL_STAFF_ONLY_SENTINEL',
    'OTHER_STUDENT_NOTIFICATION_SENTINEL', 'DRAFT_NOTIFICATION_SENTINEL',
    'LEGACY_NOTIFICATION_SENTINEL', 'REVIEW_NOTIFICATION_SENTINEL',
    'CROSS_GRADE_NOTIFICATION_SENTINEL', 'password_hash',
];

function privateResult(page, label) {
    check(!secretValues.some(value => page.text.includes(value)), label + ' excludes other students, unpublished marks, and staff-only details');
}

for (let retry = 0; ; retry++) {
    try { await fetch(origin + '/'); break; }
    catch (error) {
        if (retry >= 50) throw error;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
}

const state = fixture();
const guest = new Client(), admin = new Client(), faculty = new Client();
const alpha = new Client(), beta = new Client(), alphaSecond = new Client(), unlinked = new Client();

const home = await guest.get('/');
clean(home, 'Public homepage');
check(home.text.includes('login.php') && home.text.includes('register.php'), 'Homepage links to sign in and account requests');
check(!home.text.includes('Student Alpha') && !home.text.includes('ST-ALPHA'), 'Public homepage exposes no student academic records');
check((await guest.get('/student.php')).status === 302, 'Guest Student portal requires sign in');
check((await guest.get('/student.php?api=1')).status === 401, 'Guest Student API returns unauthorized');

check((await login(admin, 'student_test_admin')).status === 302, 'Administrator signs in');
check((await login(faculty, 'student_test_faculty')).status === 302, 'Faculty signs in');
check((await login(alpha, 'student_test_alpha')).headers.get('location') === 'student.php', 'Student signs in to its dedicated portal');
check((await login(alphaSecond, 'student_test_alpha')).status === 302, 'Student can establish a separate session for revocation checks');
check((await login(beta, 'student_test_beta')).status === 302, 'Second Student signs in');
check((await login(unlinked, 'student_test_unlinked')).status === 302, 'Approved unlinked Student signs in without academic access');
check((await admin.get('/student.php')).status === 403, 'Administrator cannot impersonate the Student portal');
check((await faculty.get('/student.php')).status === 403, 'Faculty cannot impersonate the Student portal');

for (const view of ['overview', 'subjects', 'grades', 'history', 'reports', 'notifications']) {
    const page = await alpha.get('/student.php?view=' + view);
    clean(page, 'Student ' + view);
    privateResult(page, 'Student ' + view);
    check(/no-store/.test(page.headers.get('cache-control') || ''), 'Student ' + view + ' disables caching of private results');
    const api = await alpha.get('/student.php?view=' + view + '&api=1');
    clean(api, 'Student ' + view + ' API');
    privateResult(api, 'Student ' + view + ' API');
    check(JSON.parse(api.text).linked === true, 'Student ' + view + ' API uses a verified identity');
}

const gradeApi = JSON.parse((await alpha.get('/student.php?view=grades&api=1')).text);
check(gradeApi.grades.length === 2 && gradeApi.grades.every(row => Number(row.student_id) === state.students[0]), 'Grade API includes only the linked student’s reviewed Published grades');
check(gradeApi.grades.every(row => row.workflow === 'Published' && Number(row.migration_review) === 0), 'No unpublished or migration-review rows enter Student aggregates');
check(Number(gradeApi.gwa) === 2.25, 'Student GWA includes eligible published numeric grades and excludes INC');
const subjectApi = JSON.parse((await alpha.get('/student.php?view=subjects&api=1')).text);
check(subjectApi.subjects.length === 7, 'Student sees its own enrolled subjects including unpublished offerings');
check(subjectApi.subjects.filter(row => row.result_status === 'Results are not yet published').length === 5, 'Unpublished and unreviewed results use the same neutral availability message');
const otherFilter = await alpha.get('/student.php?view=grades&subject_id=' + state.subjects['PUB-BETA'] + '&api=1');
check(otherFilter.status === 200 && JSON.parse(otherFilter.text).grades.length === 0, 'A subject outside Student enrollment returns no grade data');
const missingTerm = await alpha.get('/student.php?view=grades&term_id=999999&api=1');
check(missingTerm.status === 200 && JSON.parse(missingTerm.text).grades.length === 0, 'A term without owned results returns an empty set');

for (const query of [
    'student_id=' + state.students[1],
    'student_id%5B%5D=' + state.students[0],
    'workflow=Draft', 'workflow=Submitted', 'workflow=Approved', 'migration_review=1',
]) {
    check((await alpha.get('/student.php?view=grades&api=1&' + query)).status === 403, 'Crafted Student query is rejected: ' + query);
}
check((await alpha.get('/student.php?view=grades&from=2027-12-31&to=2027-01-01')).status === 422, 'Student filters reject an inverted date range');
check((await alpha.get('/student.php?view=grades&term_id=invalid')).status === 422, 'Student filters reject malformed record IDs');

for (const path of [
    '/students.php', '/students.php?edit=' + state.students[1], '/subjects.php',
    '/grades.php', '/grades.php?history=' + state.grades['PUB-BETA'],
    '/records.php?entity=enrollments', '/records.php?entity=offerings', '/academic.php',
    '/reports.php', '/interventions.php', '/import.php', '/users.php', '/logs.php', '/backup.php', '/settings.php',
]) check((await alpha.get(path)).status === 403, 'Student cannot open staff route: ' + path);
const staffDashboard = await alpha.get('/dashboard.php');
check(staffDashboard.status === 403 || (staffDashboard.status === 302 && staffDashboard.headers.get('location') === 'student.php'), 'Staff dashboard never returns staff statistics to a Student');
const staffApi = await alpha.get('/dashboard.php?api=1');
check(staffApi.status === 403 || (staffApi.status === 302 && staffApi.headers.get('location') === 'student.php'), 'Staff dashboard API cannot reveal school-wide statistics to a Student');

const profile = await alpha.get('/profile.php');
clean(profile, 'Student profile');
const csrf = token(profile.text);
for (const [path, data] of [
    ['/grades.php', { action: 'save', id: state.grades['PUB-ALPHA'], student_id: state.students[0], midterm: 100, final: 100 }],
    ['/grades.php', { action: 'publish', id: state.grades['DRAFT-ALPHA'], revision: 1 }],
    ['/records.php?entity=enrollments', { action: 'save', student_id: state.students[1], offering_id: state.offerings['PUB-BETA'], status: 'Enrolled' }],
    ['/users.php', { action: 'role', id: state.accounts.student_test_alpha, role: 'Administrator' }],
    ['/reports.php', { action: 'generate', report_type: 'users' }],
    ['/backup.php', { action: 'download_sql' }],
    ['/settings.php', { school_name: 'Unauthorized change' }],
]) check((await alpha.post(path, { csrf_token: csrf, ...data })).status === 403, 'Crafted Student POST cannot perform staff action: ' + path + (data.action ? ' ' + data.action : ''));
check((await alpha.post('/student.php?view=reports', { action: 'generate', report_type: 'grade_card' })).status === 400, 'Student report generation requires CSRF validation');
check((await alpha.post('/student.php?view=reports', { csrf_token: csrf, action: 'generate', report_type: 'grade_card', student_id: state.students[1] })).status === 403, 'Student report POST cannot target another student');
check((await alpha.post('/student.php?view=reports', { csrf_token: csrf, action: 'generate', report_type: 'users' })).status === 403, 'Student cannot request administrative report types');

const initialAudit = fixture();
const reportTokens = [];
for (const reportType of ['grade_card', 'summary', 'academic_history']) {
    const response = await alpha.post('/student.php?view=reports', { csrf_token: csrf, action: 'generate', report_type: reportType });
    check(response.status === 302 && response.headers.get('location').includes('report='), 'Student generates an audited ' + reportType + ' preview');
    const location = new URL(response.headers.get('location'), origin + '/');
    const reportToken = location.searchParams.get('report');
    reportTokens.push(reportToken);
    const preview = await alpha.get(location.pathname + location.search);
    clean(preview, 'Student ' + reportType + ' preview');
    privateResult(preview, 'Student ' + reportType + ' preview');
    check(preview.text.includes('System-generated student copy') && preview.text.includes('Student Alpha'), 'Student report identifies its owner and certification limitation');
    const exported = await alpha.post('/student.php?view=reports', { csrf_token: csrf, action: 'export', report: reportToken });
    check(exported.status === 200 && exported.headers.get('content-type').includes('text/csv'), 'Student exports its own ' + reportType + ' CSV');
    privateResult(exported, 'Student ' + reportType + ' CSV');
    check((await beta.get('/student.php?view=reports&report=' + reportToken)).status === 403, 'Another Student cannot reuse a ' + reportType + ' preview token');
    const betaPage = await beta.get('/profile.php');
    check((await beta.post('/student.php?view=reports', { csrf_token: token(betaPage.text), action: 'export', report: reportToken })).status === 403, 'Another Student cannot export a ' + reportType + ' token');
}
const reportAudit = fixture();
check(reportAudit.reports_generated === initialAudit.reports_generated + 3, 'All Student report generations have server-side audit entries');
check(reportAudit.reports_exported === initialAudit.reports_exported + 3, 'All Student report exports have server-side audit entries');

const notificationApi = JSON.parse((await alpha.get('/student.php?view=notifications&api=1')).text);
check(notificationApi.notifications.stored.length === 2, 'Student sees only its two permitted fixture notifications');
check(notificationApi.notifications.reminders.length === 1 && notificationApi.notifications.reminders[0].message.includes('2099-12-31'), 'Published INC generates a completion reminder without internal notes');
check((await alpha.post('/student.php?view=notifications', { csrf_token: csrf, action: 'read', id: state.notifications.SAFE_ACCOUNT_NOTICE })).status === 302, 'Student can mark its own permitted notification as read');
for (const message of ['INTERNAL_STAFF_ONLY_SENTINEL', 'OTHER_STUDENT_NOTIFICATION_SENTINEL', 'DRAFT_NOTIFICATION_SENTINEL', 'CROSS_GRADE_NOTIFICATION_SENTINEL']) {
    check((await alpha.post('/student.php?view=notifications', { csrf_token: csrf, action: 'read', id: state.notifications[message] })).status === 403, 'Student cannot mark unauthorized notification: ' + message);
}
const notificationState = fixture();
check(notificationState.read_notification_ids.includes(state.notifications.SAFE_ACCOUNT_NOTICE) && !notificationState.read_notification_ids.includes(state.notifications.DRAFT_NOTIFICATION_SENTINEL), 'Crafted notification requests leave unauthorized records unchanged');

const unlinkedPage = await unlinked.get('/student.php');
clean(unlinkedPage, 'Unlinked Student account notice');
check(!unlinkedPage.text.includes('ST-ALPHA') && !unlinkedPage.text.includes('85.00'), 'Unlinked Student sees no academic identity or grades');
const unlinkedApi = await unlinked.get('/student.php?api=1');
check(unlinkedApi.status === 200 && JSON.parse(unlinkedApi.text).linked === false, 'Unlinked Student API reports no verified link');
const unlinkedProfile = await unlinked.get('/profile.php');
const unlinkedReport = await unlinked.post('/student.php?view=reports', { csrf_token: token(unlinkedProfile.text), action: 'generate', report_type: 'grade_card' });
check(unlinkedReport.status !== 302, 'Unlinked Student cannot generate an academic report');
check((await unlinked.get('/student.php?student_id=' + state.students[0])).status === 403, 'Unlinked Student cannot claim a record through a URL');

const beforeProfile = fixture();
const profileAttack = await alpha.post('/profile.php', {
    csrf_token: csrf, action: 'profile', full_name: 'Changed Official Identity', email: 'student-profile@example.test',
    student_id: state.students[1], student_number: 'ATTACK-NUMBER', course_id: 99999, year_level: 5,
});
check(profileAttack.status === 200 || profileAttack.status === 422, 'Student profile rejects crafted official-identity changes');
const afterProfile = fixture();
check(JSON.stringify(afterProfile.student_records) === JSON.stringify(beforeProfile.student_records) && afterProfile.users.find(user => user.username === 'student_test_alpha').student_id === state.students[0], 'Student profile attack does not alter academic records or its verified account link');
const currentProfile = await alpha.get('/profile.php');
const emailUpdate = await alpha.post('/profile.php', { csrf_token: token(currentProfile.text), action: 'profile', email: 'student-contact@example.test' });
check(emailUpdate.status === 302, 'Student may update permitted contact details');
const wrongPassword = await alpha.post('/profile.php', { csrf_token: token(currentProfile.text), action: 'password', current_password: 'WrongPassword2026!', password: 'NewStudentPassword2026!', confirm_password: 'NewStudentPassword2026!' });
check(wrongPassword.status === 200 && wrongPassword.text.includes('current password'), 'Student password change verifies the current password');
const passwordProfile = await alpha.get('/profile.php');
check((await alpha.post('/profile.php', { csrf_token: token(passwordProfile.text), action: 'password', current_password: state.password, password: 'NewStudentPassword2026!', confirm_password: 'NewStudentPassword2026!' })).status === 302, 'Student changes its password securely');
check((await alphaSecond.get('/student.php')).status === 302, 'Password change revokes the Student’s other session');
check((await alpha.get('/student.php')).status === 200, 'Current Student session remains usable after password change');
check((await alpha.get('/student.php?view=reports&report=' + reportTokens[0])).status === 403, 'Password change invalidates previously generated report tokens');
const newReportPage = await alpha.get('/student.php?view=reports');
const newReport = await alpha.post('/student.php?view=reports', { csrf_token: token(newReportPage.text), action: 'generate', report_type: 'grade_card' });
check(newReport.status === 302, 'Student regenerates a report after changing its password');
const reopenReportToken = new URL(newReport.headers.get('location'), origin + '/').searchParams.get('report');

const adminGrades = await admin.get('/grades.php');
check((await admin.post('/grades.php', { csrf_token: token(adminGrades.text), id: state.grades['PUB-ALPHA'], revision: 1, action: 'reopen', reason: 'Isolated Student visibility regression check' })).status === 302, 'Administrator reopens a published fixture grade');
const reopenedGrades = JSON.parse((await alpha.get('/student.php?view=grades&api=1')).text);
check(reopenedGrades.grades.length === 1 && reopenedGrades.grades[0].remarks === 'INC' && reopenedGrades.gwa === null, 'Reopened grade immediately leaves Student grades and GWA');
const refreshedPreview = await alpha.get('/student.php?view=reports&report=' + reopenReportToken);
const refreshedReportApi = await alpha.get('/student.php?view=reports&report=' + reopenReportToken + '&api=1');
check(refreshedPreview.status === 200 && refreshedReportApi.status === 200 && JSON.parse(refreshedReportApi.text).report.data.every(row => row.subject_code !== 'PUB-ALPHA'), 'Existing Student report preview re-queries visibility after grade reopening');
const refreshedExport = await alpha.post('/student.php?view=reports', { csrf_token: token(refreshedPreview.text), action: 'export', report: reopenReportToken });
check(refreshedExport.status === 200 && !refreshedExport.text.includes('PUB-ALPHA'), 'Existing Student CSV token cannot export a reopened grade');
const refreshedNotices = await alpha.get('/student.php?view=notifications&api=1');
check(!refreshedNotices.text.includes('SAFE_PUBLISHED_NOTICE'), 'Reopening removes grade-linked Student notifications immediately');

check((await adminAction({ action: 'link', id: state.accounts.student_test_alpha, student_id: state.students[3] })).status === 302, 'Administrator can relink a Student only through verified account management');
check((await alpha.get('/student.php')).status === 302, 'Changing the verified Student link revokes an existing session');
check((await login(alpha, 'student_test_alpha', 'NewStudentPassword2026!')).status === 302, 'Relinked Student may sign in again');
const relinkedGrades = JSON.parse((await alpha.get('/student.php?view=grades&api=1')).text);
check(relinkedGrades.grades.length === 0, 'Relinked account cannot see its previous academic record');
check((await alpha.get('/student.php?view=reports&report=' + reportTokens[0])).status === 403, 'Old report tokens are unusable after identity linking changes');
check((await adminAction({ action: 'toggle', id: state.accounts.student_test_alpha })).status === 302, 'Administrator deactivates a Student account');
check((await alpha.get('/student.php?api=1')).status === 401, 'Deactivation immediately revokes Student API access');

check((await adminAction({ action: 'role', id: state.accounts.student_test_beta, role: 'Faculty' })).status === 302, 'Administrator can change a Student role to Faculty');
const betaAfterRole = fixture().users.find(user => user.username === 'student_test_beta');
check(betaAfterRole.role === 'Faculty' && betaAfterRole.student_id === null, 'Changing Student role clears its academic identity link');
check((await beta.get('/student.php')).status === 302, 'Student role change revokes the existing session');
check((await login(beta, 'student_test_beta')).status === 302, 'Changed Faculty account signs in through its new role');
check((await beta.get('/student.php')).status === 403, 'Changed Faculty account cannot keep Student portal access');

for (const path of ['/students.php?edit=' + state.students[1], '/grades.php?edit=' + state.grades['PUB-BETA'], '/grades.php?history=' + state.grades['PUB-BETA']]) {
    check((await faculty.get(path)).status === 403, 'Existing Faculty ownership protections survive Student addition: ' + path);
}

const studentSignup = new Client();
const signupPage = await studentSignup.get('/register.php');
const signup = await studentSignup.post('/register.php', {
    csrf_token: token(signupPage.text), full_name: 'Public Student Request', username: 'student_public_request',
    email: 'public-student@example.test', password: state.password, confirm_password: state.password,
    captcha: captcha(signupPage.text), account_type: 'Student', role: 'Administrator', student_id: state.students[1],
});
check(signup.status === 302, 'Public Student account request is accepted for review');
const registeredStudent = fixture().users.find(user => user.username === 'student_public_request');
check(registeredStudent.role === 'Student' && registeredStudent.student_id === null && registeredStudent.registration_status === 'Pending' && !Number(registeredStudent.is_active), 'Student registration cannot self-link or grant elevated access');
const facultySignup = new Client();
const facultySignupPage = await facultySignup.get('/register.php');
check((await facultySignup.post('/register.php', {
    csrf_token: token(facultySignupPage.text), full_name: 'Public Faculty Request', username: 'faculty_public_request',
    email: 'public-faculty@example.test', password: state.password, confirm_password: state.password,
    captcha: captcha(facultySignupPage.text), role: 'Administrator', student_id: state.students[1],
})).status === 302, 'Existing Faculty account-request behavior is preserved');
const registeredFaculty = fixture().users.find(user => user.username === 'faculty_public_request');
check(registeredFaculty.role === 'Faculty' && registeredFaculty.student_id === null && registeredFaculty.registration_status === 'Pending' && !Number(registeredFaculty.is_active), 'Crafted role=Administrator cannot elevate a Faculty registration');
const badSignup = new Client();
const badSignupPage = await badSignup.get('/register.php');
check((await badSignup.post('/register.php', {
    csrf_token: token(badSignupPage.text), full_name: 'Invalid Administrator Request', username: 'admin_public_request',
    password: state.password, confirm_password: state.password, captcha: captcha(badSignupPage.text), account_type: 'Administrator',
})).status !== 302, 'Public registration rejects an Administrator account type');
check(!fixture().users.some(user => user.username === 'admin_public_request'), 'Rejected Administrator registration creates no account');

check((await adminAction({ action: 'approve', id: registeredStudent.id })).status === 200, 'Administrator cannot approve an unlinked Student registration');
check((await adminAction({ action: 'link', id: registeredStudent.id, student_id: state.students[1] })).status === 302, 'Administrator verifies and links the pending Student to an available academic record');
check((await adminAction({ action: 'approve', id: registeredStudent.id })).status === 302, 'Administrator approves the verified Student request');
check((await login(studentSignup, 'student_public_request')).status === 302, 'Approved linked Student request can sign in');
const approvedApi = JSON.parse((await studentSignup.get('/student.php?view=grades&api=1')).text);
check(approvedApi.grades.length === 1 && approvedApi.grades.every(row => Number(row.student_id) === state.students[1]), 'New Student sees exactly its administrator-verified published result');

const pending = new Client(), inactive = new Client();
check((await login(pending, 'student_test_pending_login')).status === 200, 'Pending Student cannot sign in even if its active flag is set');
check((await login(inactive, 'student_test_inactive')).status === 200, 'Inactive Student cannot sign in');
check((await pending.get('/student.php')).status === 302, 'Rejected sign-in grants no Student session');
check((await inactive.get('/student.php?api=1')).status === 401, 'Inactive Student gains no API access');

const logoutPage = await studentSignup.get('/profile.php');
check((await studentSignup.get('/logout.php')).status === 405, 'Student logout requires POST');
check((await studentSignup.post('/logout.php', { csrf_token: token(logoutPage.text) })).status === 302, 'Student POST logout succeeds');
check((await studentSignup.get('/student.php')).status === 302, 'Student session is unavailable after logout');

console.log(`\n${checks} Student HTTP checks passed.`);
