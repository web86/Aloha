/* Native admin screen acceptance, disposable site only. */
const fs = require('node:fs'), assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const auth = JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH, 'utf8'));
assert.equal(auth.url, 'http://127.0.0.1:8091', 'Refuse the primary local WordPress');
const out = process.env.EVIDENCE_DIR;
const report = {checks: [], pageErrors: []};
const check = label => { report.checks.push(label); console.log('PASS:', label); };
(async () => {
  const browser = await chromium.launch({headless: true});
  let page;
  try {
    const context = await browser.newContext({viewport: {width: 1440, height: 1050}});
    page = await context.newPage();
    page.on('pageerror', e => report.pageErrors.push({url: page.url(), message: e.message, stack: e.stack}));
    const login = async (page, user) => {
      // Avoid the admin Dashboard's automatic Quick Draft and unrelated Profile
      // scripts. A Subscriber's Dashboard has no Quick Draft (no edit_posts).
      const landing = user === auth.user ? '/wp-admin/tools.php?page=aloha-content-routing' : '/wp-admin/index.php';
      await page.goto(auth.url + '/wp-login.php?redirect_to=' + encodeURIComponent(auth.url + landing));
      // Reuse the existing Gutenberg stand's guard against Core's delayed focus.
      await page.waitForTimeout(400);
      await page.locator('#user_login').fill(user);
      await page.locator('#user_pass').fill(auth.password);
      assert.ok(await page.locator('#user_login').inputValue() === user, 'Username field lost focus');
      assert.ok(await page.locator('#user_pass').inputValue() === auth.password, 'Password field lost focus');
      await page.locator('#wp-submit').click();
      await page.waitForURL('**/wp-admin/**', {waitUntil: 'domcontentloaded'});
    };
    await login(page, auth.user);
    const url = auth.url + '/wp-admin/tools.php?page=aloha-content-routing';
    assert.equal((await page.goto(url)).status(), 200);
    await page.getByRole('heading', {name: 'Aloha Content — Routing Overview', exact: true}).waitFor();
    const wrap = page.locator('#wpbody-content > .wrap');
    assert.equal(await page.locator('#menu-tools').getByRole('link', {name: 'Aloha Content: Routing', exact: true}).count(), 1);
    for (const name of ['Area', 'Community', 'Guide Hub', 'Guide Rubric', 'Guide Article', 'Investor Article']) {
      assert.equal(await wrap.getByRole('rowheader', {name, exact: true}).count(), 1);
    }
    assert.equal(await wrap.locator('form,input,button').count(), 0);
    assert.match(await wrap.innerText(), /Matches expected configuration/);
    assert.match(await wrap.innerText(), /No active rental route/);
    await page.screenshot({path: out + '/routing-overview-desktop.png', fullPage: true});
    check('Administrator opens Tools → Aloha Content: Routing; six native table rows, no editing controls');
    const before = await wrap.innerText();
    await page.reload();
    assert.equal(await wrap.innerText(), before);
    check('Repeated overview requests display stable diagnostics');
    await page.setViewportSize({width: 390, height: 844});
    await page.screenshot({path: out + '/routing-overview-mobile.png', fullPage: true});
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'Admin screen has horizontal page overflow');
    assert.ok(await wrap.locator('td.column-primary').first().evaluate(cell => cell.clientWidth > cell.closest('table').clientWidth * 0.8), 'Mobile family names must occupy their own readable row');
    check('Native admin screen adapts to 390px without horizontal page overflow');
    const restricted = await browser.newContext();
    const denied = await restricted.newPage();
    await login(denied, 'aloha_routing_reader');
    assert.equal((await denied.goto(url)).status(), 403);
    assert.match(await denied.locator('body').innerText(), /not allowed|higher level of permission/i);
    await denied.screenshot({path: out + '/routing-overview-denied.png', fullPage: true});
    check('Subscriber without manage_options gets native HTTP 403');
    assert.deepEqual(report.pageErrors, []);
  } catch (error) {
    report.error = error.message;
    if (page && !page.isClosed()) {
      report.failureURL = page.url();
      report.failureText = await page.locator('body').innerText();
      await page.screenshot({path: out + '/routing-overview-failure.png', fullPage: true, mask: [page.locator('#user_login'), page.locator('#user_pass')]});
    }
    throw error;
  } finally {
    fs.writeFileSync(out + '/routing-overview-browser-report.json', JSON.stringify(report, null, 2));
    await browser.close();
  }
})().catch(e => { console.error(e); process.exitCode = 1; });
