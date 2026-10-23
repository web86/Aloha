/* Real Gutenberg + responsive Area acceptance. Fixtures only on disposable :8091. */
const fs = require('node:fs'), assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const auth = JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH, 'utf8'));
const fixture = JSON.parse(fs.readFileSync(process.env.ALOHA_AREA_PRESENTATION_FIXTURES, 'utf8'));
assert.equal(auth.url, 'http://127.0.0.1:8091', 'Refuse the primary local WordPress');
const out = process.env.EVIDENCE_DIR, report = {checks: [], pageErrors: [], viewports: []};
const created = [];
let browser, page;
const check = label => { report.checks.push(label); console.log('PASS:', label); };
const flatten = blocks => blocks.flatMap(b => [b, ...flatten(b.innerBlocks || [])]);
async function api(method, path, data) {
  return page.evaluate(async ({method, path, data}) => wp.apiFetch({path: '/wp/v2/' + path, method, data}), {method, path, data});
}
async function editor(id) {
  await page.goto(auth.url + '/wp-admin/' + (id ? `post.php?post=${id}&action=edit` : 'post-new.php?post_type=area'));
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostType() === 'area');
  await page.evaluate(() => wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false));
  const welcome = page.getByRole('button', {name: 'Get started', exact: true});
  if (await welcome.isVisible()) await welcome.click();
  if (!id) {
    id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId()); created.push(id);
    await page.waitForFunction(() => {
      const all = bs => bs.flatMap(b => [b, ...all(b.innerBlocks || [])]);
      const blocks = all(wp.data.select('core/block-editor').getBlocks());
      return blocks.length > 30 && !blocks.some(b => b.name === 'core/pattern');
    });
  }
  return id;
}
async function save(fields = {}) {
  await page.evaluate(async fields => {
    wp.data.dispatch('core/editor').editPost(fields);
    await wp.data.dispatch('core/editor').savePost();
  }, fields);
  await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost());
}
async function validBlocks() {
  const errors = await page.evaluate(() => {
    const all = bs => bs.flatMap(b => [b, ...all(b.innerBlocks || [])]);
    return all(wp.data.select('core/block-editor').getBlocks()).filter(b => b.isValid === false || b.name === 'core/missing').map(b => ({name:b.name, original:b.originalContent, errors:b.validationIssues}));
  });
  assert.deepEqual(errors, [], 'All native starter blocks must validate');
}
(async () => {
  browser = await chromium.launch({headless: true});
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
  page = await context.newPage();
  page.on('pageerror', e => report.pageErrors.push(e.message));
  try {
    await page.goto(auth.url + '/wp-login.php?redirect_to=' + encodeURIComponent(auth.url + '/wp-admin/edit.php?post_type=area'));
    await page.waitForTimeout(400);
    await page.locator('#user_login').fill(auth.user); await page.locator('#user_pass').fill(auth.password);
    assert.ok(await page.locator('#user_pass').inputValue() === auth.password, 'Login focus race');
    await page.locator('#wp-submit').click(); await page.waitForURL('**/wp-admin/**', {waitUntil: 'domcontentloaded'});
    const second = await editor();
    await validBlocks();
    const initial = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
    assert.match(initial, /What this area/); assert.ok(!initial.includes('Mauna Lani'));
    check('New Area expands the unlocked native starter into valid blocks with generic copy');
    const imageIds = [fixture.media.hero, fixture.media.golf, fixture.media.whale];
    const images = [];
    for (const id of imageIds) {
      const media = await api('GET', 'media/' + id);
      images.push({id, url:media.media_details.sizes.large?.source_url || media.source_url, alt:media.alt_text, sizeSlug:'large', linkDestination:'none'});
    }
    // Type in a native RichText paragraph, rather than replacing the entire composition.
    const frame = page.frameLocator('iframe[name="editor-canvas"]');
    await frame.locator('.aloha-area-introduction p[contenteditable="true"]').first().fill('An editable editorial introduction.');
    await page.evaluate(images => {
      const store = wp.data.select('core/block-editor'), actions = wp.data.dispatch('core/block-editor');
      const all = bs => bs.flatMap(b => [b, ...all(b.innerBlocks || [])]);
      const blocks = all(store.getBlocks());
      const gallery = blocks.find(b => b.name === 'aloha/media-gallery');
      actions.updateBlockAttributes(gallery.clientId, {ids:images.map(a=>a.id)});
      const faq = blocks.find(b => b.attributes.className === 'aloha-area-faq');
      actions.insertBlocks(Array.from({length:7},(_,i) => wp.blocks.createBlock('core/details', {summary:'Additional question '+i}, [wp.blocks.createBlock('core/paragraph',{content:'An editable verified-answer placeholder.'})])), faq.innerBlocks.length, faq.clientId);
      actions.removeBlocks(faq.innerBlocks.find(b => b.name === 'core/details').clientId, false);
      const beaches = blocks.find(b => b.name === 'aloha/beaches');
      actions.updateBlockAttributes(beaches.clientId,{cards:images.map((image,i)=>({title:i?'Editorial beach '+i:'Reordered beach card',description:'Verified-answer placeholder.',imageId:image.id,url:'',focalPoint:{x:.5,y:.5}})).reverse()});
      const owner = blocks.find(b => b.attributes.className === 'aloha-area-owner');
      actions.removeBlocks(owner.clientId, false);
      actions.insertBlocks(wp.blocks.createBlock('core/paragraph',{content:'An extra manager-authored block.'}));
    }, images);
    await save({title:'Second island area', slug:'second-island-area', excerpt:'An independent Area with editable native blocks.', featured_media:fixture.media.hero});
    await validBlocks();
    let result = await api('GET','area/'+second+'?context=edit');
    assert.match(result.content.raw,/An editable editorial introduction/);
    assert.match(result.content.raw,/Additional question 6/);
    assert.equal((result.content.raw.match(/<!-- wp:details /g)||[]).length,11);
    assert.ok(!result.content.raw.includes('core/pattern') && !result.content.raw.includes('wp:pattern'));
    check('Text, unlimited native FAQ, card order, optional section removal and added blocks save normally');
    // Remove and restore a gallery image through native block actions, preserving order.
    await page.evaluate(() => {
      const all=bs=>bs.flatMap(b=>[b,...all(b.innerBlocks||[])]);
      const gallery=all(wp.data.select('core/block-editor').getBlocks()).find(b=>b.name==='aloha/media-gallery');
      wp.data.dispatch('core/block-editor').updateBlockAttributes(gallery.clientId,{ids:gallery.attributes.ids.filter((_,i)=>i!==1)});
    });
    await save();
    await page.evaluate(image => {
      const all=bs=>bs.flatMap(b=>[b,...all(b.innerBlocks||[])]);
      const gallery=all(wp.data.select('core/block-editor').getBlocks()).find(b=>b.name==='aloha/media-gallery');
      wp.data.dispatch('core/block-editor').updateBlockAttributes(gallery.clientId,{ids:[image.id,...gallery.attributes.ids]});
    },images[1]);
    await save();
    const saved = (await api('GET','area/'+second+'?context=edit')).content.raw;
    await editor(second); await validBlocks();
    assert.equal((await api('GET','area/'+second+'?context=edit')).content.raw,saved);
    const blocks=await page.evaluate(()=>wp.data.select('core/block-editor').getBlocks());
    const gallery=flatten(blocks).find(b=>b.name==='aloha/media-gallery');
    assert.deepEqual(gallery.attributes.ids,[imageIds[1],imageIds[0],imageIds[2]]);
    check('Gallery add/remove/reorder, photograph and all edits survive saving and reopening');
    const previewUrl=await page.evaluate(()=>wp.data.select('core/editor').getEditedPostPreviewLink());
    let front=await context.newPage();
    front.on('pageerror',e=>report.pageErrors.push(e.message));
    assert.equal((await front.goto(previewUrl)).status(),200);
    assert.match(await front.locator('h1').innerText(),/Second island area/);
    assert.match(await front.locator('.aloha-area-introduction').innerText(),/An editable editorial introduction/);
    check('Authorized native draft preview uses the Area display template and saved body');
    await save({status:'publish',title:'Second independent area',slug:'second-independent-area'});
    result=await api('GET','area/'+second+'?context=edit');
    assert.equal(result.content.raw,saved);
    assert.match(result.link,/second-independent-area\/$/);
    assert.equal((await front.goto(result.link)).status(),200);
    assert.match(await front.locator('h1').innerText(),/Second independent area/);
    assert.equal(await front.locator('.aloha-area-owner').count(),0);
    check('Title/slug changes update the Hero/permalink without reseeding or restoring deleted sections');
    const intentionallyEmpty=await editor();
    await save({title:'Intentionally empty editorial draft'});
    await editor(intentionallyEmpty);
    await page.evaluate(()=>wp.data.dispatch('core/block-editor').resetBlocks([]));
    await save();
    await editor(intentionallyEmpty);
    assert.equal(await page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().length),0);
    assert.equal((await api('GET','area/'+intentionallyEmpty+'?context=edit')).content.raw,'');
    check('An intentionally cleared and saved Area stays empty when reopened');
    // Approved composition for visual QA: isolated fixture, never a second main-site Area.
    await editor(fixture.area); await save({title:'Mauna Lani'});
    await validBlocks();
    await page.screenshot({path:out+'/area-editor.png',fullPage:true});
    await front.close();
    const visualContext=await browser.newContext({viewport:{width:1440,height:1000}});
    front=await visualContext.newPage();
    front.on('pageerror',e=>report.pageErrors.push(e.message));
    await front.goto(fixture.url); await front.evaluate(()=>document.fonts.ready);
    assert.equal(await front.locator('.aloha-site-header').count(),1);
    assert.equal(await front.locator('h1').count(),1);
    assert.equal(await front.locator('.aloha-area-community').count(),3);
    assert.equal(await front.locator('.aloha-area-hero-image').getAttribute('fetchpriority'),'high');
    assert.equal(await front.locator('.aloha-gallery-mosaic img').first().getAttribute('loading'),'lazy');
    for(const width of [1440,1024,768,390,320]) {
      await front.setViewportSize({width,height:1000});
      await front.locator('img[loading="lazy"]').evaluateAll(imgs=>imgs.forEach(img=>img.loading='eager'));
      await front.waitForFunction(()=>[...document.images].every(img=>img.complete));
      assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth), 'Horizontal overflow at '+width);
      assert.equal(await front.locator('img').evaluateAll(imgs=>imgs.filter(img=>!img.naturalWidth).length),0);
      if(width===1440) assert.ok(await front.locator('.aloha-gallery-mosaic figure').first().evaluate(el=>el.clientWidth>el.parentElement.clientWidth*.45),'The gallery lead photograph must fill its large grid column');
      assert.ok(await front.locator('.aloha-gallery-mosaic figure').evaluateAll(figures=>figures.every(f=>Math.abs(f.querySelector('img').getBoundingClientRect().width-f.getBoundingClientRect().width)<2)),'Gallery photographs must fill their grid cells');
      await front.screenshot({path:out+'/area-'+width+'.png',fullPage:true});
      report.viewports.push(width);
    }
    check('1440/1024/768/390/320 layouts render with intact images and no horizontal overflow');
    await front.setViewportSize({width:1440,height:1000});
    const summary=front.locator('.aloha-area-faq summary').first();
    await summary.focus(); await front.keyboard.press('Enter');
    assert.equal(await summary.evaluate(el=>el.parentElement.open),true);
    assert.notEqual(await summary.evaluate(el=>getComputedStyle(el).outlineStyle),'none');
    await front.screenshot({path:out+'/area-keyboard.png',fullPage:true});
    check('Native FAQ supports keyboard Enter and visible focus');
    await front.locator('.aloha-area-advisor a').click();
    await front.locator('.aloha-demo-dialog[open]').waitFor();
    await front.keyboard.press('Escape');
    assert.equal(await front.locator('.aloha-demo-dialog[open]').count(),0);
    check('Local CTA reuses the existing demo dialog and Escape behavior');
    await front.evaluate(()=>document.documentElement.style.fontSize='200%');
    assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await front.screenshot({path:out+'/area-text-200.png',fullPage:true});
    await front.evaluate(()=>document.documentElement.style.fontSize='');
    await editor(fixture.empty); await save({title:'A very long area title that still needs to remain readable on a narrow phone screen without any photograph'});
    const empty = await api('GET','area/'+fixture.empty+'?context=edit');
    await front.setViewportSize({width:320,height:900}); await front.goto(empty.link);
    assert.equal(await front.locator('.aloha-area-hero img').count(),0);
    assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await front.screenshot({path:out+'/area-no-media-long-title.png',fullPage:true});
    check('200% text, long titles and absent media keep a usable layout');
    await api('DELETE','media/'+fixture.media.lava+'?force=true');
    await front.goto(fixture.url);
    assert.equal(await front.locator('img').evaluateAll(imgs=>imgs.filter(img=>img.src.includes('area-reference-lava')).length),0);
    check('Deleting an attachment removes stale frontend image markup safely');
    await front.close();
    assert.deepEqual(report.pageErrors,[]);
    report.secondArea=second;
  } catch(e) {
    report.error=e.message;
    if(page&&!page.isClosed()) {
      report.failureURL=page.url(); report.failureText=await page.locator('body').innerText();
      await page.screenshot({path:out+'/area-failure.png',fullPage:true,mask:[page.locator('#user_login'),page.locator('#user_pass')]});
    }
    throw e;
  } finally {
    if(page&&!page.isClosed()) {
      if(!page.url().includes('/wp-admin/')) await editor(fixture.area);
      for(const id of created) await api('DELETE','area/'+id+'?force=true').catch(()=>{});
    }
    fs.writeFileSync(out+'/area-presentation-browser-report.json',JSON.stringify(report,null,2));
    await browser.close();
  }
})().catch(e=>{console.error(e);process.exitCode=1;});
