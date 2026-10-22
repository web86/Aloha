/* Gutenberg/REST acceptance in the disposable test site. No main-site credentials. */
const fs = require('node:fs'), assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const auth = JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH, 'utf8'));
const routes = process.env.ALOHA_AREA_TEST_FIXTURES ? JSON.parse(fs.readFileSync(process.env.ALOHA_AREA_TEST_FIXTURES, 'utf8')) : null;
const guide = process.env.ALOHA_GUIDE_TEST_FIXTURES ? JSON.parse(fs.readFileSync(process.env.ALOHA_GUIDE_TEST_FIXTURES, 'utf8')) : null;
const investor = process.env.ALOHA_INVESTOR_TEST_FIXTURES ? JSON.parse(fs.readFileSync(process.env.ALOHA_INVESTOR_TEST_FIXTURES, 'utf8')) : null;
assert.equal(auth.url, 'http://127.0.0.1:8091', 'Refuse the main database');
const out = process.env.EVIDENCE_DIR;
const report = {checks: [], editorStyles: [], pageErrors: []};
const fixtures = [], media = [];
let browser, page;
const check = message => { report.checks.push(message); console.log('PASS:', message); };
async function api(method, path, data) {
  return page.evaluate(async ({method, path, data}) => {
    try { return {ok: true, value: await wp.apiFetch({path: '/wp/v2/' + path, method, data})}; }
    catch (e) { return {ok: false, code: e.code, message: e.message}; }
  }, {method, path, data});
}
async function editor(type, id) {
  await page.goto(auth.url + '/wp-admin/' + (id ? `post.php?post=${id}&action=edit` : `post-new.php?post_type=${type}`));
  await page.waitForFunction(type => window.wp?.data?.select('core/editor')?.getCurrentPostType() === type, type);
  await page.evaluate(() => {
    wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
  });
  const welcome = page.getByRole('button', {name: 'Get started', exact: true});
  if (await welcome.isVisible()) await welcome.click();
  if (!id) {
    id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
    fixtures.push({type, id});
  }
  return id;
}
async function edit(fields) {
  await page.evaluate(fields => wp.data.dispatch('core/editor').editPost(fields), fields);
}
async function save() {
  await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
  await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost());
}
async function chooseArea(title) {
  const combo = page.getByRole('combobox', {name: 'Area', exact: true});
  const toggle = page.getByRole('button', {name: 'Area', exact: true});
  await toggle.waitFor({state:'visible'});
  // A reopened expanded panel can still be fetching Areas. Do not collapse it
  // just because the Combobox has not replaced its loading Spinner yet.
  if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  await combo.waitFor({state:'visible'});
  await combo.fill(title);
  await page.getByRole('option', {name: title, exact: true}).click();
}
async function categoryCheckbox(name) {
  const toggle = page.getByRole('button', {name: 'Categories', exact: true});
  await toggle.waitFor({state: 'visible'});
  // On reopening an editor the expanded panel may still be fetching terms.
  // Check disclosure state, not the temporary absence of its checkboxes.
  if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  const checkbox = page.getByRole('checkbox', {name, exact: true});
  await checkbox.waitFor({state: 'visible'});
  return checkbox;
}
async function styles(type) {
  const frame = page.frameLocator('iframe[name="editor-canvas"]');
  await frame.locator('.wp-block-paragraph').first().waitFor();
  const result = await frame.locator('body').evaluate(async body => {
    await document.fonts.ready;
    const css = selector => {
      const element = body.querySelector(selector), s = getComputedStyle(element);
      return {family: s.fontFamily, color: s.color, size: s.fontSize};
    };
    return {heading: css('h2'), paragraph: css('.wp-block-paragraph'), brand: getComputedStyle(body).getPropertyValue('--wp--preset--color--deep-sea-green').trim()};
  });
  assert.match(result.heading.family, /Instrument Serif/);
  assert.match(result.paragraph.family, /Outfit/);
  assert.equal(result.brand.toLowerCase(), '#075056');
  report.editorStyles.push({type, ...result});
  check(type + ' inherits Aloha fonts and palette');
}
(async () => {
  browser = await chromium.launch({headless: true});
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
  page = await context.newPage();
  page.on('pageerror', e => report.pageErrors.push(e.message));
  try {
    // Avoid Dashboard's automatic Quick Draft: every test-created record must
    // belong to our explicit fixture cleanup, including auto-drafts.
    await page.goto(auth.url + '/wp-login.php?redirect_to=' + encodeURIComponent(auth.url + '/wp-admin/edit.php?post_type=area'));
    // Core schedules wp_attempt_focus() after load. Let that finish before typing
    // so it cannot redirect password keystrokes into the username field.
    await page.waitForTimeout(400);
    await page.locator('#user_login').fill(auth.user);
    await page.locator('#user_pass').fill(auth.password);
    assert.ok(await page.locator('#user_login').inputValue() === auth.user, 'Username field lost focus');
    assert.ok(await page.locator('#user_pass').inputValue() === auth.password, 'Password field lost focus');
    await page.locator('#wp-submit').click();
    await page.waitForURL('**/wp-admin/**', {waitUntil: 'domcontentloaded'});
    const areaA = await editor('area');
    const content = '<!-- wp:heading --><h2 class="wp-block-heading">Editorial heading</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Isolated Gutenberg content.</p><!-- /wp:paragraph -->';
    await edit({title: 'Isolated Area A', excerpt: 'Editable excerpt', content});
    await page.evaluate(content => wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.parse(content)), content);
    await save();
    await styles('area');
    await edit({status: 'publish'}); await save();
    let result = await api('GET', 'area/' + areaA + '?context=edit');
    assert.ok(result.ok); assert.equal(result.value.status, 'publish'); assert.match(result.value.excerpt.raw, /Editable excerpt/);
    check('Area created and published through Gutenberg');
    if (routes) {
      assert.equal(result.value.link, auth.url + '/big-island/isolated-area-a/');
      assert.equal(await page.evaluate(() => wp.data.select('core/editor').getPermalink()), result.value.link);
      check('Area permalink in Gutenberg uses /big-island/{slug}/');
    }
    // Upload test media using Core Media API; the thumbnail remains a native field.
    const attachment = await page.evaluate(async () => {
      const bytes = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='), c => c.charCodeAt(0));
      const form = new FormData(); form.append('file', new Blob([bytes], {type: 'image/png'}), 'isolated-featured.png');
      return await wp.apiFetch({path: '/wp/v2/media', method: 'POST', body: form});
    });
    media.push(attachment.id);
    await edit({featured_media: attachment.id}); await save();
    result = await api('GET', 'area/' + areaA + '?context=edit');
    assert.equal(result.value.featured_media, attachment.id);
    check('Native featured image and excerpt persist');
    await page.screenshot({path: out + '/area-editor.png', fullPage: true});
    const areaB = await editor('area');
    await edit({title: 'Isolated Area B', content});
    await page.evaluate(content => wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.parse(content)), content);
    await save();
    if (routes) {
      const previewUrl = await page.evaluate(() => wp.data.select('core/editor').getEditedPostPreviewLink());
      assert.ok(previewUrl && previewUrl.includes('preview=true'));
      const preview = await page.context().newPage();
      const response = await preview.goto(previewUrl);
      assert.equal(response.status(), 200);
      assert.equal(response.headers()['x-aloha-test-object-id'], String(areaB));
      assert.equal(response.headers()['x-aloha-test-is-preview'], '1');
      assert.match(await preview.locator('body').innerText(), /Isolated Gutenberg content/);
      await preview.screenshot({path: out + '/area-draft-preview.png', fullPage: true});
      check('Native Gutenberg draft preview works without a public draft URL');
      const privateResponse = await preview.goto(auth.url + '/big-island/fixture-private-area/');
      assert.equal(privateResponse.status(), 200);
      assert.equal(privateResponse.headers()['x-aloha-test-object-id'], String(routes.private));
      await preview.close();
      check('Authenticated private Area respects Core permissions');
    }
    await edit({status: 'publish'}); await save();
    const community = await editor('community');
    await edit({title: 'Isolated Community', excerpt: 'Community excerpt', content});
    await page.evaluate(content => wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.parse(content)), content);
    await save();
    result = await api('GET', 'community/' + community + '?context=edit');
    assert.equal(result.value.status, 'draft'); assert.equal(result.value.meta.aloha_area_id, 0);
    check('Gutenberg draft without Area saves');
    await styles('community');
    if (routes) {
      const previewUrl = await page.evaluate(() => wp.data.select('core/editor').getEditedPostPreviewLink());
      const preview = await page.context().newPage();
      const response = await preview.goto(previewUrl);
      assert.equal(response.status(), 200);
      assert.equal(response.headers()['x-aloha-test-object-id'], String(community));
      assert.equal(response.headers()['x-aloha-test-is-preview'], '1');
      assert.match(await preview.locator('body').innerText(), /Isolated Gutenberg content/);
      await preview.screenshot({path: out + '/community-draft-preview.png', fullPage: true});
      await preview.close();
      check('Native Community draft preview works before choosing an Area');
    }
    await edit({status: 'publish'}); await save();
    assert.equal((await api('GET', 'community/' + community + '?context=edit')).value.status, 'draft');
    assert.ok(await page.evaluate(() => wp.data.select('core/notices').getNotices().some(n => /Choose an existing Area/.test(n.content))));
    check('Gutenberg publication without Area shows server validation');
    await page.screenshot({path: out + '/publication-blocked.png', fullPage: true});
    await chooseArea('Isolated Area A');
    await save();
    if (routes) {
      const previewUrl = await page.evaluate(() => wp.data.select('core/editor').getEditedPostPreviewLink());
      const preview = await page.context().newPage();
      const response = await preview.goto(previewUrl);
      assert.equal(response.status(), 200);
      assert.equal(response.headers()['x-aloha-test-object-id'], String(community));
      assert.equal(response.headers()['x-aloha-test-is-preview'], '1');
      await preview.close();
      check('Assigned Community draft keeps the native authenticated preview');
    }
    await edit({status: 'publish'}); await save();
    result = await api('GET', 'community/' + community + '?context=edit');
    assert.equal(result.value.status, 'publish'); assert.equal(result.value.meta.aloha_area_id, areaA);
    if (routes) {
      assert.equal(result.value.link, auth.url + '/big-island/isolated-area-a/isolated-community/');
      assert.equal(await page.evaluate(() => wp.data.select('core/editor').getPermalink()), result.value.link);
      const front = await page.context().newPage();
      const response = await front.goto(result.value.link);
      assert.equal(response.status(), 200);
      assert.equal(response.headers()['x-aloha-test-object-id'], String(community));
      await front.screenshot({path: out + '/community-route-fallback.png', fullPage: true});
      await front.close();
      check('Gutenberg shows the nested Community permalink and opens its correct object');
    }
    await editor('community', community);
    assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostAttribute('meta').aloha_area_id), areaA);
    await chooseArea('Isolated Area B'); await save();
    assert.equal((await api('GET', 'community/' + community + '?context=edit')).value.meta.aloha_area_id, areaB);
    if (routes) {
      result = await api('GET', 'community/' + community + '?context=edit');
      assert.equal(result.value.link, auth.url + '/big-island/isolated-area-b/isolated-community/');
      assert.equal(await page.evaluate(() => wp.data.select('core/editor').getPermalink()), result.value.link);
      check('Changing Area in Gutenberg updates the Community permalink');
    }
    check('Native Area picker saves, reopens and changes the relationship');
    await page.screenshot({path: out + '/community-picker.png', fullPage: true});
    const revisions = await api('GET', 'community/' + community + '/revisions?context=edit');
    assert.ok(revisions.ok);
    const revisionA = revisions.value.find(r => r.meta?.aloha_area_id === areaA);
    assert.ok(revisionA, 'REST revisions include the relationship');
    await page.goto(auth.url + '/wp-admin/revision.php?revision=' + revisionA.id);
    await page.getByRole('button', {name: 'Restore This Revision', exact: true}).click();
    await page.waitForURL('**/post.php?**');
    await page.waitForFunction(() => window.wp?.apiFetch);
    assert.equal((await api('GET', 'community/' + community + '?context=edit')).value.meta.aloha_area_id, areaA);
    check('Core revision screen restores Area relationship');
    result = await api('DELETE', 'area/' + areaA + '?force=true');
    assert.equal(result.code, 'aloha_area_in_use');
    check('Used Area cannot be permanently deleted via REST');
    await edit({status: 'draft', meta: {aloha_area_id: 0}}); await save();
    assert.equal((await api('GET', 'community/' + community + '?context=edit')).value.meta.aloha_area_id, 0);
    check('Unpublish and clear relationship through Gutenberg');
    if (guide) {
      const article = await editor('post');
      await edit({title: 'Isolated Guide Article', content});
      await page.evaluate(content => wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.parse(content)), content);
      await save();
      const things = await categoryCheckbox('Things to Do');
      await things.check(); await save();
      result = await api('GET', 'posts/' + article + '?context=edit');
      assert.ok(result.value.categories.includes(guide.map.rubrics['things-to-do']));
      check('Guide rubric is assigned using the native Categories panel');
      const previewUrl = await page.evaluate(() => wp.data.select('core/editor').getEditedPostPreviewLink());
      const preview = await page.context().newPage();
      const previewResponse = await preview.goto(previewUrl);
      assert.equal(previewResponse.status(), 200);
      assert.equal(previewResponse.headers()['x-aloha-test-object-id'], String(article));
      assert.equal(previewResponse.headers()['x-aloha-test-is-preview'], '1');
      assert.match(await preview.locator('head').innerHTML(), /noindex/);
      await preview.close();
      check('Guide draft uses the authenticated native preview with noindex');
      const events = page.getByRole('checkbox', {name: 'Events', exact: true});
      await events.check(); await edit({status: 'publish'}); await save();
      assert.equal((await api('GET', 'posts/' + article + '?context=edit')).value.status, 'draft');
      assert.ok(await page.evaluate(() => wp.data.select('core/notices').getNotices().some(n => /Choose exactly one Big Island Guide rubric/.test(n.content))));
      await page.screenshot({path: out + '/guide-ambiguous-rubric.png', fullPage: true});
      check('Gutenberg blocks ambiguous Guide publication with an actionable error');
      await events.uncheck(); await edit({status: 'publish'}); await save();
      result = await api('GET', 'posts/' + article + '?context=edit');
      assert.equal(result.value.status, 'publish');
      assert.equal(result.value.link, auth.url + '/big-island/guide/things-to-do/isolated-guide-article/');
      assert.equal(await page.evaluate(() => wp.data.select('core/editor').getPermalink()), result.value.link);
      await page.screenshot({path: out + '/guide-editor-permalink.png', fullPage: true});
      const front = await page.context().newPage();
      const response = await front.goto(result.value.link);
      assert.equal(response.status(), 200);
      assert.equal(response.headers()['x-aloha-test-object-id'], String(article));
      assert.equal(await front.locator('link[rel="canonical"]').getAttribute('href'), result.value.link);
      await front.screenshot({path: out + '/guide-route-fallback.png', fullPage: true});
      await front.close();
      check('Published Guide permalink and canonical open the correct Post');
      await editor('post', article);
      await edit({title: 'Isolated Guide Article Updated'}); await save();
      result = await api('GET', 'posts/' + article + '?context=edit');
      assert.equal(result.value.title.raw, 'Isolated Guide Article Updated');
      assert.equal(result.value.link, auth.url + '/big-island/guide/things-to-do/isolated-guide-article/');
      check('Guide article reopens and edits through Gutenberg with its route intact');
    }
    if (investor) {
      const article = await editor('post');
      const investorContent = content + `<!-- wp:image {"id":${attachment.id}} --><figure class="wp-block-image"><img src="${attachment.source_url}" alt="Isolated Investor image" class="wp-image-${attachment.id}"/></figure><!-- /wp:image -->`;
      await edit({title: 'Isolated Investor Article', content: investorContent});
      await page.evaluate(content => wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.parse(content)), investorContent);
      await save();
      const category = await categoryCheckbox('Investor Guides');
      await category.check(); await save();
      result = await api('GET', 'posts/' + article + '?context=edit');
      assert.ok(result.value.categories.includes(investor.category));
      assert.match(result.value.content.raw, /wp:image/); assert.match(result.value.content.raw, /Isolated Gutenberg content/);
      check('Investor category uses native Categories; text and image blocks persist');
      const previewUrl = await page.evaluate(() => wp.data.select('core/editor').getEditedPostPreviewLink());
      const preview = await page.context().newPage();
      const previewResponse = await preview.goto(previewUrl);
      assert.equal(previewResponse.status(), 200);
      assert.equal(previewResponse.headers()['x-aloha-test-object-id'], String(article));
      assert.equal(previewResponse.headers()['x-aloha-test-is-preview'], '1');
      assert.equal(await preview.locator('img[alt="Isolated Investor image"]').count(), 1);
      await preview.close();
      check('Investor draft preview preserves Core authorization and image content');
      const things = page.getByRole('checkbox', {name: 'Things to Do', exact: true});
      await things.check(); await edit({status: 'publish'}); await save();
      assert.equal((await api('GET', 'posts/' + article + '?context=edit')).value.status, 'draft');
      assert.ok(await page.evaluate(() => wp.data.select('core/notices').getNotices().some(n => /single article family/.test(n.content))));
      await page.screenshot({path: out + '/investor-family-conflict.png', fullPage: true});
      check('Gutenberg blocks mixed Guide/Investor publication with an explicit error');
      await things.uncheck(); await edit({status: 'publish'}); await save();
      result = await api('GET', 'posts/' + article + '?context=edit');
      assert.equal(result.value.status, 'publish');
      const link = auth.url + '/homeowners/invest/guides/isolated-investor-article/';
      assert.equal(result.value.link, link);
      assert.equal(await page.evaluate(() => wp.data.select('core/editor').getPermalink()), link);
      await page.screenshot({path: out + '/investor-editor-permalink.png', fullPage: true});
      const front = await page.context().newPage();
      const response = await front.goto(link);
      assert.equal(response.status(), 200);
      assert.equal(response.headers()['x-aloha-test-object-id'], String(article));
      assert.equal(await front.locator('link[rel="canonical"]').getAttribute('href'), link);
      await front.screenshot({path: out + '/investor-route-fallback.png', fullPage: true});
      await front.close();
      check('Investor permalink and canonical open the correct published Post');
      await editor('post', article);
      const categoryAgain = await categoryCheckbox('Investor Guides');
      await categoryAgain.uncheck(); await save();
      result = await api('GET', 'posts/' + article + '?context=edit');
      assert.equal(result.value.link, auth.url + '/isolated-investor-article/');
      assert.match(result.value.content.raw, /wp:image/);
      assert.equal((await page.context().request.get(link, {maxRedirects: 0})).status(), 404);
      check('Removing Investor membership restores the ordinary URL without historical redirect');
    }
    // Fresh requests verify disabled plugin and theme remain functional, with data retained.
    await page.goto(auth.url + '/wp-admin/plugins.php');
    await page.locator('tr[data-plugin="aloha-content/aloha-content.php"]').getByRole('link', {name: /Deactivate/}).click();
    await page.waitForLoadState('domcontentloaded');
    const front = await page.goto(auth.url + '/'); assert.equal(front.status(), 200);
    assert.equal(await page.locator('.aloha-site-header').count(), 1);
    await page.goto(auth.url + '/wp-admin/plugins.php');
    await page.locator('tr[data-plugin="aloha-content/aloha-content.php"]').getByRole('link', {name: /^Activate/}).click();
    await page.waitForLoadState('domcontentloaded');
    await editor('community', community);
    result = await api('GET', 'community/' + community + '?context=edit');
    assert.equal(result.value.title.raw, 'Isolated Community'); assert.equal(result.value.meta.aloha_area_id, 0);
    check('Plugin off/on retains content; Aloha frontend works without plugin');
    assert.deepEqual(report.pageErrors, []);
  } catch (e) {
    report.error = e.message;
    if (page && !page.isClosed()) {
      await page.screenshot({path: out + '/failure.png', fullPage: true, mask: [page.locator('#user_login'), page.locator('#user_pass')]});
      report.failurePage = await page.locator('body').innerText();
    }
    throw e;
  } finally {
    if (page && !page.isClosed()) {
      if (!page.url().includes('/wp-admin/')) await page.goto(auth.url + '/wp-admin/edit.php');
      await page.waitForFunction(() => window.wp?.apiFetch).catch(() => {});
      for (const type of ['community', 'area', 'post']) for (const fixture of fixtures.filter(x => x.type === type)) await api('DELETE', (type === 'post' ? 'posts' : type) + '/' + fixture.id + '?force=true');
      for (const id of media) await api('DELETE', 'media/' + id + '?force=true');
    }
    fs.writeFileSync(out + '/editor-report.json', JSON.stringify(report, null, 2));
    await browser.close();
  }
})().catch(e => { console.error(e); process.exitCode = 1; });
