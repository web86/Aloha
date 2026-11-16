// Native form and anonymous HTTP acceptance on the disposable site only.
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {randomBytes} = require('node:crypto');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const auth = JSON.parse(readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH, 'utf8'));
const fixtures = JSON.parse(readFileSync(process.env.ALOHA_AREA_TEST_FIXTURES, 'utf8'));
assert.equal(auth.url, 'http://127.0.0.1:8091');

(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    const context = await browser.newContext({viewport: {width: 390, height: 844}});
    const page = await context.newPage();
    await context.request.get(auth.url + '/wp-login.php');
    const login = await context.request.post(auth.url + '/wp-login.php', {
      maxRedirects: 0,
      form: {log: auth.user, pwd: auth.password, testcookie: '1'},
    });
    assert.equal(login.status(), 302, 'Fixture login failed');

    assert.equal((await page.goto(auth.url + '/wp-admin/post-new.php?post_type=nearby_place')).status(), 200);
    await page.getByRole('heading', {name: 'Add Nearby Place', exact: true}).waitFor();
    assert.equal(await page.getByLabel('Name', {exact: true}).inputValue(), '');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'Form overflows at 390px');
    const form = page.locator('form[action$="/admin-post.php"]');
    assert.equal(await form.locator('input[name=place_id]').inputValue(), '0');
    const nonce = await form.locator('input[name=_wpnonce]').inputValue();
    const area = String(fixtures.areas['mauna-lani']);
    const suffix = randomBytes(4).toString('hex');
    const fields = {
      action: 'aloha_save_nearby_place', place_id: '0', _wpnonce: nonce,
      name: 'Nearby browser fixture ' + suffix, latitude: '', longitude: '', category: '', area_id: area, place_status: 'publish',
    };
    const submit = async (changes) => context.request.post(auth.url + '/wp-admin/admin-post.php', {
      maxRedirects: 0,
      form: {...fields, ...changes},
    });
    const missing = await submit({});
    assert.equal(missing.status(), 400);
    assert.match(await missing.text(), /Enter both coordinates/);
    assert.equal((await submit({latitude: '91', longitude: '0'})).status(), 400);
    assert.equal((await submit({latitude: '1', longitude: '0', area_id: String(fixtures.community)})).status(), 400);
    assert.equal((await submit({_wpnonce: 'invalid', latitude: '1', longitude: '0'})).status(), 403);

    await page.getByLabel('Name', {exact: true}).fill(fields.name);
    await page.getByLabel('Area', {exact: true}).selectOption(area);
    await page.getByRole('button', {name: 'Save Draft', exact: true}).click();
    await page.getByText('Nearby Place saved.', {exact: true}).waitFor();
    const id = await form.locator('input[name=place_id]').inputValue();
    assert.ok(Number(id) > 0, 'Draft did not get a WordPress post ID');
    assert.equal(await page.getByLabel('Latitude', {exact: true}).inputValue(), '');
    assert.equal(await page.getByLabel('Area', {exact: true}).inputValue(), area);
    await page.getByLabel('Latitude', {exact: true}).fill('19.81');
    await page.getByLabel('Longitude', {exact: true}).fill('-155.12');
    await page.getByLabel('Category', {exact: true}).fill('Beach');
    await page.getByRole('button', {name: 'Publish', exact: true}).click();
    await page.getByRole('button', {name: 'Update', exact: true}).waitFor();
    await page.getByLabel('Name', {exact: true}).fill(fields.name + ' updated');
    await page.getByRole('button', {name: 'Update', exact: true}).click();
    await page.getByText('Nearby Place saved.', {exact: true}).waitFor();
    assert.equal(await page.getByLabel('Longitude', {exact: true}).inputValue(), '-155.12');
    assert.equal(await page.getByLabel('Category', {exact: true}).inputValue(), 'Beach');

    const restNonce = await context.request.get(auth.url + '/wp-admin/admin-ajax.php?action=rest-nonce');
    assert.equal(restNonce.status(), 200);
    const selector = await context.request.get(auth.url + '/wp-json/aloha-content/v1/nearby-places', {
      params: {search: fields.name, areaId: area},
      headers: {'X-WP-Nonce': await restNonce.text()},
    });
    assert.equal(selector.status(), 200);
    const cacheDirectives = selector.headers()['cache-control'].split(',').map(value => value.trim());
    assert.ok(cacheDirectives.includes('private') && cacheDirectives.includes('no-store'));
    assert.deepEqual(await selector.json(), [{
      id: Number(id), name: fields.name + ' updated', category: 'Beach', areaId: Number(area), status: 'publish',
    }]);

    const anonymous = await browser.newContext();
    try {
      const denied = await anonymous.request.get(auth.url + '/wp-json/aloha-content/v1/nearby-places');
      assert.equal(denied.status(), 401);
      for (const path of ['/?p=' + id, '/?post_type=nearby_place&p=' + id, '/nearby_place/nearby-browser-fixture-' + suffix + '/']) {
        const response = await anonymous.request.get(auth.url + path);
        assert.notEqual(response.headers()['x-aloha-test-object-id'], id, path + ' exposed a place');
        assert.ok(!(await response.text()).includes(fields.name), path + ' exposed the title');
      }
    } finally {
      await anonymous.close();
    }

    await page.getByRole('link', {name: 'All Nearby Places', exact: true}).click();
    const row = page.getByRole('row').filter({hasText: fields.name + ' updated'});
    await row.waitFor();
    await row.locator('.trash a').evaluate(link => link.click());
    await page.waitForURL(/edit\.php\?.*trashed=1/);
    assert.equal(await page.getByRole('link', {name: fields.name + ' updated', exact: true}).count(), 0);
    console.log('PASS: Nearby Places admin draft, validation, nonce, publication, update, private URLs and trash at 390px.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
