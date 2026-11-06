// Help and demo browser checks use only disposable crystal_test_help_* fixtures.
const { chromium } = require(process.env.PLAYWRIGHT_PATH || require('path').join(require('os').tmpdir(), 'crystal-browser-qa', 'node_modules', 'playwright'));
const { spawn, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.CRYSTAL_TEST_PHP || 'C:/xampp/php/php.exe';
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; console.log('PASS ' + label); }
async function freePort() {
    const probe = net.createServer();
    await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port;
    await new Promise(resolve => probe.close(resolve));
    return port;
}
(async () => {
    const environment = { ...process.env, APP_ENV: 'test', FORCE_HTTPS: '0' };
    delete environment.DB_NAME; delete environment.CRYSTAL_CONFIG_FILE;
    let statePath = process.env.CRYSTAL_HELP_TEST_STATE;
    if (!statePath) {
        const fixture = spawnSync(php, ['tests/help.php'], { cwd: root, env: environment, encoding: 'utf8', windowsHide: true });
        assert.equal(fixture.status, 0, fixture.error?.message || fixture.stderr || fixture.stdout);
        statePath = fixture.stdout.match(/State: (.+)\r?\n/)[1].trim();
    }
    const state = JSON.parse(fs.readFileSync(statePath, 'utf8'));
    assert.match(state.database, /^crystal_test_help_[a-z0-9_]+$/);
    const output = path.join(os.tmpdir(), 'crystal-browser-qa', 'artifacts', state.database);
    const sessions = path.join(output, 'sessions');
    fs.mkdirSync(sessions, { recursive: true });
    const logPath = path.join(output, 'server.log');
    const logHandle = fs.openSync(logPath, 'a');
    const port = await freePort();
    const origin = 'http://127.0.0.1:' + port;
    const server = spawn(php, ['-d', 'session.save_path="' + sessions + '"', '-S', '127.0.0.1:' + port, 'bin/router.php'], {
        cwd: root, env: { ...environment, DB_NAME: state.database, BACKUP_DIR: state.backup_dir },
        stdio: ['ignore', logHandle, logHandle], windowsHide: true,
    });
    let browser;
    const errors = [];
    try {
        for (let attempt = 0; ; attempt++) {
            try { await fetch(origin + '/'); break; }
            catch (error) { if (attempt > 60) throw error; await new Promise(resolve => setTimeout(resolve, 100)); }
        }
        browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
        const guest = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        guest.on('pageerror', error => errors.push(error.message));
        async function layout(page, label) {
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), label + ' fits viewport');
            check(!/Fatal error|Warning:|Unable to complete this request/.test(await page.locator('body').innerText()), label + ' has no PHP errors');
            check(await page.locator('h1').count() === 1, label + ' has one main heading');
            check(await page.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map(node => node.id); return new Set(ids).size === ids.length; }), label + ' has unique IDs');
        }
        for (const width of [1440, 390, 320]) {
            await guest.setViewportSize({ width, height: 1000 });
            for (const route of ['/', '/login.php', '/help.php', '/help.php?document=product', '/help.php?document=technical']) {
                const response = await guest.goto(origin + route);
                check(response.status() === 200, route + ' responds at ' + width);
                await layout(guest, route + ' at ' + width);
            }
        }
        await guest.goto(origin + '/login.php');
        check(await guest.getByRole('heading', { name: 'Try a demo account' }).isVisible(), 'Login explains demo access');
        for (const username of ['demo_admin', 'demo_faculty', 'demo_student']) check(await guest.locator('.demo-access').innerText().then(text => text.includes(username)), 'Login advertises ' + username);
        await guest.screenshot({ path: path.join(output, 'demo-login-mobile.png'), fullPage: true });
        await guest.getByRole('link', { name: 'Help', exact: true }).click();
        await guest.getByRole('link', { name: 'Product requirements', exact: true }).click();
        check(await guest.getByRole('heading', { level: 1 }).innerText() === 'Product Requirement Document', 'Public Help opens Product Requirement Document');
        await guest.setViewportSize({ width: 1440, height: 1000 });
        await guest.emulateMedia({ media: 'print' });
        check(await guest.locator('h1').isVisible() && !(await guest.locator('.topbar').isVisible()), 'Requirements print retains document title and hides navigation');
        await guest.pdf({ path: path.join(output, 'product-requirements.pdf'), format: 'A4', printBackground: true });
        await guest.emulateMedia({ media: 'screen' });
        await guest.screenshot({ path: path.join(output, 'product-requirements-desktop.png'), fullPage: true });
        const invalid = await guest.goto(origin + '/help.php?document=../../config/connection');
        check(invalid.status() === 404 && !(await guest.locator('body').innerText()).includes('DB_PASSWORD'), 'Unknown Help document cannot load a source file');
        const roles = [
            ['Administrator', 'demo_admin', 'DemoAdmin2026!', 'dashboard.php'],
            ['Faculty', 'demo_faculty', 'DemoFaculty2026!', 'dashboard.php'],
            ['Student', 'demo_student', 'DemoStudent2026!', 'student.php'],
        ];
        for (const [role, username, password, landing] of roles) {
            const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
            const page = await context.newPage();
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(origin + '/login.php');
            await page.getByLabel('Username', { exact: true }).fill(username);
            await page.getByLabel('Password', { exact: true }).fill(password);
            const question = await page.locator('label[for="captcha"]').innerText();
            const values = question.match(/(\d+) \+ (\d+)/).slice(1).map(Number);
            await page.getByLabel(/Solve/).fill(String(values[0] + values[1]));
            await Promise.all([page.waitForURL('**/' + landing), page.getByRole('button', { name: 'Sign in', exact: true }).click()]);
            check(true, role + ' demo credentials reach the right dashboard');
            const navigation = page.locator('#workspace-navigation');
            for (const [label, title] of [['Product requirements', 'Product Requirement Document'], ['Technical requirements', 'Technical Requirement Document']]) {
                await navigation.getByRole('link', { name: label, exact: true }).click();
                check(await page.locator('h1').innerText() === title, role + ' Help opens ' + title);
                check(await navigation.locator('[aria-current="page"]').count() === 1, role + ' Help highlights one current page');
                await layout(page, role + ' ' + title);
            }
            if (role === 'Student') {
                const api = await context.request.get(origin + '/student.php?view=grades&api=1');
                const data = await api.json();
                check(data.grades.length === 1 && Number(data.grades[0].student_id) === state.seed.student_id, 'Demo Student API has only its own reviewed Published result');
                check((await context.request.get(origin + '/users.php')).status() === 403, 'Demo Student cannot open administrator tools');
            }
            await page.setViewportSize({ width: 390, height: 844 });
            await page.locator('#workspace-nav-toggle').click();
            check(await navigation.getByRole('link', { name: 'Product requirements', exact: true }).isVisible(), role + ' mobile menu exposes Help documents');
            await navigation.getByRole('link', { name: 'Product requirements', exact: true }).click();
            await layout(page, role + ' mobile Product document');
            await context.close();
        }
        check(errors.length === 0, 'No uncaught browser errors');
        const log = fs.readFileSync(logPath, 'utf8');
        check(!/PHP (Warning|Fatal|Parse)|Crystal:/.test(log), 'Test server has no PHP warnings or exceptions');
        fs.writeFileSync(path.join(output, 'qa-summary.json'), JSON.stringify({ checks, database: state.database, errors, output }, null, 2));
        console.log(`\n${checks} browser checks passed. Artifacts: ${output}`);
    } finally {
        if (browser) await browser.close();
        server.kill();
        await new Promise(resolve => server.exitCode !== null ? resolve() : server.once('exit', resolve));
        fs.closeSync(logHandle);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
