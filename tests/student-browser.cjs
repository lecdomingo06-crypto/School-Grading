// Browser QA creates only a fresh crystal_test_student_* fixture database.
// It never changes the configured local school's accounts or academic data.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || require('path').join(require('os').tmpdir(), 'crystal-browser-qa', 'node_modules', 'playwright'));
const { spawn, spawnSync } = require('node:child_process');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const php = process.env.CRYSTAL_TEST_PHP || 'C:/xampp/php/php.exe';
let checks = 0;
const failures = [];
function check(value, label) {
    if (!value) { failures.push(label); console.error('FAIL ' + label); return; }
    checks++;
    console.log('PASS ' + label);
}

async function unusedPort() {
    const probe = net.createServer();
    await new Promise((resolve, reject) => { probe.once('error', reject); probe.listen(0, '127.0.0.1', resolve); });
    const port = probe.address().port;
    await new Promise(resolve => probe.close(resolve));
    return port;
}

(async () => {
    const fixtureEnv = { ...process.env, APP_ENV: 'test', FORCE_HTTPS: '0' };
    delete fixtureEnv.DB_NAME;
    delete fixtureEnv.CRYSTAL_CONFIG_FILE;
    const fixture = spawnSync(php, ['tests/student.php', '--fixtures-only'], { cwd: root, env: fixtureEnv, encoding: 'utf8' });
    assert.equal(fixture.status, 0, fixture.error?.message || fixture.stderr || fixture.stdout);
    const statePath = fixture.stdout.match(/State: (.+)\r?\n/)[1].trim();
    const state = JSON.parse(fs.readFileSync(statePath, 'utf8'));
    assert.match(state.database, /^crystal_test_student_[a-z0-9_]+$/);
    const output = path.join(os.tmpdir(), 'crystal-browser-qa', 'artifacts', state.database);
    fs.mkdirSync(output, { recursive: true });
    const serverLog = path.join(output, 'server.log');
    const logHandle = fs.openSync(serverLog, 'a');
    const port = await unusedPort();
    const origin = 'http://127.0.0.1:' + port;
    const server = spawn(php, ['-S', '127.0.0.1:' + port, 'bin/router.php'], {
        cwd: root, env: { ...fixtureEnv, DB_NAME: state.database, BACKUP_DIR: state.backup_dir },
        stdio: ['ignore', logHandle, logHandle], windowsHide: true,
    });
    let browser;
    const browserErrors = [];

    try {
        for (let retry = 0; ; retry++) {
            try { await fetch(origin + '/'); break; }
            catch (error) { if (retry >= 50) throw error; await new Promise(resolve => setTimeout(resolve, 100)); }
        }
        browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
        const contexts = [];
        async function rolePage(username, landing) {
            const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
            contexts.push(context);
            const page = await context.newPage();
            page.on('pageerror', error => browserErrors.push(error.message));
            page.on('console', message => { if (message.type() === 'error') browserErrors.push(message.text()); });
            await page.goto(origin + '/login.php');
            await page.getByLabel('Username', { exact: true }).fill(username);
            await page.getByLabel('Password', { exact: true }).fill(state.password);
            const question = await page.getByLabel(/Solve/).getAttribute('id');
            const label = await page.locator('label[for="' + question + '"]').textContent();
            const terms = label.match(/(\d+) \+ (\d+)/).slice(1).map(Number);
            await page.getByLabel(/Solve/).fill(String(terms[0] + terms[1]));
            await Promise.all([page.waitForURL('**/' + landing), page.getByRole('button', { name: 'Sign in', exact: true }).click()]);
            check(true, username + ' signs in through the browser');
            return page;
        }
        async function layout(page, label) {
            await page.waitForLoadState('networkidle');
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), label + ' has no page-level horizontal overflow');
            check(!/Fatal error|Warning:|Undefined variable|Unable to complete this request/.test(await page.locator('body').innerText()), label + ' has no rendered PHP errors');
            const idCheck = await page.evaluate(() => {
                const ids = [...document.querySelectorAll('[id]')].map(node => node.id);
                return ids.length === new Set(ids).size;
            });
            check(idCheck, label + ' has unique element IDs');
        }
        async function screenshot(page, name) { await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true }); }

        const admin = await rolePage('student_test_admin', 'dashboard.php');
        const faculty = await rolePage('student_test_faculty', 'dashboard.php');
        const student = await rolePage('student_test_alpha', 'student.php');
        for (const [role, page] of [['Administrator', admin], ['Faculty', faculty], ['Student', student]]) {
            check(!(await page.locator('#workspace-nav-toggle').isVisible()), role + ' desktop navigation does not display a redundant mobile Menu button');
        }
        check(await admin.locator('#workspace-navigation a[href="users.php"]').count() === 1 && await admin.locator('#workspace-navigation a[href="backup.php"]').count() === 1, 'Administrator navigation includes accounts and backups');
        check(await faculty.locator('#workspace-navigation a[href="users.php"],#workspace-navigation a[href="backup.php"],#workspace-navigation a[href="academic.php"]').count() === 0, 'Faculty navigation excludes administrator tools');
        check((await faculty.locator('#workspace-navigation').innerText()).includes('Assigned classes') && (await faculty.locator('#workspace-navigation').innerText()).includes('Grade entry'), 'Faculty navigation focuses on assigned classes and grade entry');
        check(await student.locator('#workspace-navigation a[href="grades.php"],#workspace-navigation a[href="students.php"],#workspace-navigation a[href="users.php"]').count() === 0, 'Student navigation excludes staff academic and account tools');
        check(await student.locator('#workspace-navigation a[aria-current="page"]').count() === 1, 'Student overview has one active navigation link');
        check(await student.locator('#workspace-navigation a[aria-current="page"]').getAttribute('href') === 'student.php?view=overview', 'Student default overview highlights its own navigation item');

        for (const [role, page] of [['admin', admin], ['faculty', faculty]]) {
            await layout(page, role + ' desktop dashboard');
            await screenshot(page, role + '-dashboard-desktop');
            await page.setViewportSize({ width: 390, height: 844 });
            await layout(page, role + ' mobile dashboard');
            await screenshot(page, role + '-dashboard-mobile');
        }
        await admin.goto(origin + '/records.php?entity=courses');
        await layout(admin, 'Administrator mobile academic setup');
        check(await admin.locator('#workspace-navigation a[aria-current="page"]').getAttribute('href') === 'academic.php', 'Academic setup remains active when opening its course records');
        await faculty.goto(origin + '/grades.php?offering_id=' + state.offerings['PUB-ALPHA']);
        await layout(faculty, 'Faculty mobile grade entry');
        await screenshot(faculty, 'faculty-grade-entry-mobile');

        for (const width of [1440, 390]) {
            await student.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
            for (const view of ['overview', 'subjects', 'grades', 'history', 'reports', 'notifications']) {
                await student.goto(origin + '/student.php?view=' + view);
                await layout(student, 'Student ' + view + ' at ' + width + 'px');
                check(await student.locator('#workspace-navigation a[aria-current="page"]').getAttribute('href') === 'student.php?view=' + view, 'Student ' + view + ' highlights the current navigation link at ' + width + 'px');
                const content = await student.locator('#main-content').innerText();
                check(!['Student Beta Confidential', '91.11', '92.22', '93.33', '94.44', '95.55', 'INTERNAL_REQUIREMENT_NOTE'].some(secret => content.includes(secret)), 'Student ' + view + ' browser view excludes private and unpublished details at ' + width + 'px');
                if (['overview', 'grades', 'reports'].includes(view)) await screenshot(student, 'student-' + view + '-' + width);
            }
        }

        await student.goto(origin + '/student.php?view=grades');
        const menu = student.locator('#workspace-nav-toggle');
        const navigation = student.locator('#workspace-navigation');
        check(await menu.getAttribute('aria-expanded') === 'false' && await navigation.evaluate(node => node.inert), 'Mobile sidebar starts closed and inert');
        await menu.click();
        check(await menu.getAttribute('aria-expanded') === 'true' && !(await navigation.evaluate(node => node.inert)), 'Mobile Menu button opens an interactive sidebar');
        await screenshot(student, 'student-mobile-navigation-open');
        check(await student.evaluate(() => document.activeElement === document.querySelector('#workspace-navigation a[aria-current="page"]')), 'Opening the mobile sidebar focuses the current navigation item');
        await student.keyboard.press('Escape');
        check(await menu.getAttribute('aria-expanded') === 'false' && await navigation.evaluate(node => node.inert), 'Escape closes and disables the mobile sidebar');
        check(await menu.evaluate(node => document.activeElement === node), 'Escape returns focus to the Menu button');
        await menu.click();
        const links = navigation.locator('a[href]');
        await links.last().focus();
        await student.keyboard.press('Tab');
        check(await links.first().evaluate(node => document.activeElement === node), 'Mobile sidebar wraps forward keyboard focus');
        await student.keyboard.press('Shift+Tab');
        check(await links.last().evaluate(node => document.activeElement === node), 'Mobile sidebar wraps backward keyboard focus');
        await Promise.all([student.waitForURL('**/student.php?view=reports'), navigation.locator('a[href="student.php?view=reports"]').click()]);
        check(await menu.getAttribute('aria-expanded') === 'false' && await navigation.evaluate(node => node.inert), 'Selecting a navigation link dismisses the mobile sidebar');

        await student.locator('select[name="report_type"]').selectOption('grade_card');
        await Promise.all([student.waitForURL('**/student.php?view=reports&report=*'), student.getByRole('button', { name: 'Generate report', exact: true }).click()]);
        await layout(student, 'Student mobile generated report');
        await screenshot(student, 'student-report-mobile');
        check(await student.locator('.report-header').isVisible(), 'Student report preview displays its school header');
        check((await student.locator('.report-document').innerText()).includes('Student Alpha') && !(await student.locator('.report-document').innerText()).includes('Student Beta Confidential'), 'Student print preview identifies only its own academic record');
        await student.setViewportSize({ width: 1440, height: 1000 });
        await layout(student, 'Student desktop generated report');
        await screenshot(student, 'student-report-desktop');
        await student.emulateMedia({ media: 'print' });
        check(!(await navigation.isVisible()) && !(await student.locator('.topbar').isVisible()), 'Student print layout hides workspace navigation');
        check(await student.locator('.report-header').isVisible() && !(await student.locator('.report-toolbar').isVisible()), 'Student print layout retains the school header and hides action controls');
        const pdf = await student.pdf({ path: path.join(output, 'student-published-grade-card.pdf'), format: 'A4', landscape: true, printBackground: true });
        check(pdf.length > 3000 && pdf.subarray(0, 4).toString() === '%PDF', 'Student grade card exports a valid printable PDF artifact');
        await student.emulateMedia({ media: 'screen' });
        for (const context of contexts) await context.close();
        check(browserErrors.length === 0, 'Authenticated browser sessions have no JavaScript console or page errors: ' + browserErrors.join('; '));
        check(!/PHP (?:Warning|Fatal error)|Undefined variable|Uncaught/.test(fs.readFileSync(serverLog, 'utf8')), 'Isolated browser server log has no PHP warnings or fatal errors');
        fs.writeFileSync(path.join(output, 'qa-summary.json'), JSON.stringify({ checks, failures, database: state.database, state_file: statePath, browser_errors: browserErrors }, null, 2));
        console.log(`\n${checks} authenticated browser checks passed; ${failures.length} failed. Artifacts: ${output}`);
        assert.equal(failures.length, 0, failures.join('; '));
    } finally {
        if (browser) await browser.close();
        const stopped = new Promise(resolve => server.once('exit', resolve));
        server.kill();
        await Promise.race([stopped, new Promise(resolve => setTimeout(resolve, 2000))]);
        fs.closeSync(logHandle);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
