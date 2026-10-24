/* Real Gutenberg and responsive frontend, disposable WordPress :8091 only. */
const fs = require('node:fs'), assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const auth = JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH));
const f = JSON.parse(fs.readFileSync(process.env.ALOHA_COMMUNITY_FIXTURES));
assert.equal(auth.url, 'http://127.0.0.1:8091');
const out = process.env.EVIDENCE_DIR, report = {checks: [], pageErrors: [], viewports: [], textResize: []}, created = [];
let browser, page, front;
const check = s => { report.checks.push(s); console.log('PASS:', s); };
async function api(method, path, data) { return page.evaluate(({method, path, data}) => wp.apiFetch({path:'/wp/v2/'+path, method, data}), {method, path, data}); }
async function editor(id) {
 await page.goto(auth.url+'/wp-admin/'+(id ? `post.php?post=${id}&action=edit` : 'post-new.php?post_type=community'));
 await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostType() === 'community');
 await page.evaluate(() => wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false));
 const welcome = page.getByRole('button', {name:'Get started',exact:true}); if (await welcome.isVisible()) await welcome.click();
 if (!id) {
  id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId()); created.push(id);
  await page.waitForFunction(() => { const bs=wp.data.select('core/block-editor').getBlocks();return bs.length===4&&!bs.some(b=>b.name==='core/pattern'); });
 }
 return id;
}
async function save(fields={}) {
 await page.evaluate(async fields => { wp.data.dispatch('core/editor').editPost(fields); await wp.data.dispatch('core/editor').savePost(); }, fields);
 await page.waitForFunction(() => !wp.data.select('core/editor').isSavingPost());
 assert.equal(await page.evaluate(() => wp.data.select('core/editor').didPostSaveRequestSucceed()), true);
}
async function valid() {
 assert.deepEqual(await page.evaluate(() => { const walk=bs=>bs.flatMap(b=>[b,...walk(b.innerBlocks)]); return walk(wp.data.select('core/block-editor').getBlocks()).filter(b=>b.isValid===false||b.name==='core/missing').map(b=>b.name); }), []);
}
async function documentSidebar() {
 const settings=page.getByRole('button',{name:'Settings',exact:true});if(await settings.getAttribute('aria-pressed')!=='true')await settings.click();
 await page.getByRole('tab',{name:'Community',exact:true}).click();
}
async function chooseArea(title) {
 await documentSidebar();
 const toggle=page.getByRole('button',{name:'Area',exact:true});await toggle.waitFor();if(await toggle.getAttribute('aria-expanded')!=='true')await toggle.click();
 const combo=page.getByRole('combobox',{name:'Area',exact:true});await combo.fill(title);await page.getByRole('option',{name:title,exact:true}).click();
}
async function noOverflow(label) {
 const m=await front.evaluate(() => ({width:innerWidth,scroll:document.documentElement.scrollWidth,clipped:[...document.querySelectorAll('.aloha-community-hero-copy,.aloha-community-hero-actions,.aloha-community-hero-actions button')].filter(e=>e.scrollWidth>e.clientWidth+1||e.scrollHeight>e.clientHeight+1).map(e=>e.className)}));
 assert.ok(m.scroll<=m.width, label+': document overflow '+JSON.stringify(m));assert.deepEqual(m.clipped,[],label+': text clipping');return m;
}
(async()=>{
 browser=await chromium.launch({headless:true});
 try {
  const context=await browser.newContext({viewport:{width:1440,height:1000}});page=await context.newPage();page.on('pageerror',e=>report.pageErrors.push(e.message));
  await page.goto(auth.url+'/wp-login.php?redirect_to='+encodeURIComponent(auth.url+'/wp-admin/edit.php?post_type=community'));await page.waitForTimeout(400);await page.locator('#user_login').fill(auth.user);await page.locator('#user_pass').fill(auth.password);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  const id=await editor();await valid();
  assert.deepEqual(await page.evaluate(body=>{const walk=bs=>bs.flatMap(b=>[b,...walk(b.innerBlocks)]);return walk(wp.blocks.parse(body)).filter(b=>b.isValid===false||b.name==='core/missing').map(b=>b.name);},f.template),[]);
  const initial=await page.evaluate(()=>wp.data.select('core/editor').getEditedPostContent());assert.match(initial,/About the community/);assert.ok(!/Ka Milo|Mauna Lani|wp:pattern/.test(initial));
  check('Creating Community expands generic native starter into four unlocked, valid Core sections');
  const frame=page.frameLocator('iframe[name="editor-canvas"]');await frame.locator('.aloha-community-section p[contenteditable="true"]').first().fill('Manager edited introduction — preserved when the Area changes.');
  await chooseArea(f.editor_area_title);await save({title:'Independent Community',excerpt:'Native editable excerpt.',featured_media:f.media});
  assert.equal((await api('GET','community/'+id+'?context=edit')).meta.aloha_area_id,f.editor_area);
  await editor(id);await valid();const saved=(await api('GET','community/'+id+'?context=edit')).content.raw;assert.match(saved,/Manager edited introduction/);assert.equal(await frame.locator('.aloha-community-section').count(),4);
  await documentSidebar();const areaPanel=page.getByRole('button',{name:'Area',exact:true});if(await areaPanel.getAttribute('aria-expanded')!=='true')await areaPanel.click();await page.getByRole('combobox',{name:'Area',exact:true}).waitFor();
  await page.screenshot({path:out+'/community-gutenberg.png'});check('Existing Area picker, native excerpt and Featured Image save; Core content survives editor reopen');
  front=await browser.newPage({viewport:{width:1440,height:1000}});front.on('pageerror',e=>report.pageErrors.push(e.message));
  // The authenticated preview uses the same native single-community template.
  const preview=await page.evaluate(()=>wp.data.select('core/editor').getEditedPostPreviewLink());const previewPage=await context.newPage();assert.equal((await previewPage.goto(preview)).status(),200);assert.match(await previewPage.locator('h1').innerText(),/Independent Community/);await previewPage.close();
  await save({status:'publish'});let record=await api('GET','community/'+id+'?context=edit');const old=record.link;assert.match(old,/\/community-editor-area\/independent-community\/$/);
  assert.equal((await front.goto(old)).status(),200);assert.equal(await front.locator('h1').count(),1);assert.equal(await front.locator('.aloha-site-header').count(),1);assert.equal(await front.locator('.aloha-site-footer').count(),1);assert.match(await front.locator('.aloha-community-content').innerText(),/Manager edited introduction/);
  assert.deepEqual(await front.locator('.aloha-community-breadcrumb li').allTextContents(),['Home','Big Island',f.editor_area_title,'Independent Community']);assert.equal(await front.locator('.aloha-community-hero-intro').innerText(),'Native editable excerpt.');
  check('Draft preview and published Community use shared shell, one dynamic H1, correct breadcrumbs and saved Post Content');
  await chooseArea(f.other_title);await save();await editor(id);await valid();record=await api('GET','community/'+id+'?context=edit');assert.equal(record.content.raw,saved);assert.equal(record.meta.aloha_area_id,f.other);assert.match(record.link,/\/another-island-area\/independent-community\/$/);
  assert.equal((await front.goto(old)).status(),404);assert.equal((await front.goto(record.link)).status(),200);assert.match(await front.locator('h1').innerText(),new RegExp(f.other_title));
  const wrong=record.link.replace('/another-island-area/','/missing-area/');assert.equal((await front.goto(wrong)).status(),404);
  check('Changing Area through existing UI preserves Gutenberg bytes; new permalink 200, former and wrong relations 404');
  await page.evaluate(()=>wp.data.dispatch('core/block-editor').removeBlocks(wp.data.select('core/block-editor').getBlocks().slice(-1).map(b=>b.clientId)));await save();await editor(id);await valid();assert.equal(await page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().length),3);
  check('Deleted starter section stays deleted after save/reopen');
  const emptyId=await editor();await save({title:'Intentionally empty community'});await page.evaluate(()=>wp.data.dispatch('core/block-editor').resetBlocks([]));await save();await editor(emptyId);assert.equal(await page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().length),0);assert.equal((await api('GET','community/'+emptyId+'?context=edit')).content.raw,'');
  check('An intentionally cleared saved Community is never reseeded');
  for(const width of [1440,1024,768,390,320]) {
   await front.setViewportSize({width,height:1000});
   for(const [kind,url] of [['image',f.visual_url],['no-image',f.empty_url]]) {
    assert.equal((await front.goto(url)).status(),200);await front.evaluate(()=>document.fonts.ready);
    const image=front.locator('.aloha-community-hero-image');
    if(kind==='image'){await image.evaluate(i=>i.decode());assert.ok(await image.getAttribute('srcset'));assert.ok(await image.getAttribute('alt'));}else assert.equal(await image.count(),0);
    const metrics=await front.locator('.aloha-community-hero-grid').evaluate(e=>{const r=x=>{const b=x.getBoundingClientRect();return {x:b.x,y:b.y,width:b.width,height:b.height};};return {grid:r(e),copy:r(e.children[0]),media:r(e.children[1]),font:getComputedStyle(e.querySelector('h1')).fontSize};});
    if(width>=900){assert.ok(metrics.media.x>metrics.copy.x);assert.ok(Math.abs(metrics.media.width/metrics.copy.width-1.15)<.02);assert.equal(metrics.media.height,560);}else assert.ok(metrics.media.y>=metrics.copy.y+metrics.copy.height);
    if(width===1440){assert.ok(Math.abs(metrics.media.x-712.37)<1);assert.equal(metrics.font,'84px');await front.screenshot({path:out+'/community-'+kind+'-1440-viewport.png'});}
    report.viewports.push({width,kind,...metrics,...await noOverflow(kind)});
    await front.locator('.aloha-community-hero').screenshot({path:out+'/community-'+kind+'-'+width+'.png'});
   }
   for(const percent of [100,200]) {
    await front.goto(f.long_url);const normalSize=await front.locator('.aloha-community-hero h1').evaluate(e=>parseFloat(getComputedStyle(e).fontSize));await front.evaluate(percent=>{document.documentElement.style.fontSize=percent+'%';},percent);await front.evaluate(()=>document.fonts.ready);const actualSize=await front.locator('.aloha-community-hero h1').evaluate(e=>parseFloat(getComputedStyle(e).fontSize));assert.ok(Math.abs(actualSize-normalSize*percent/100)<.1);report.textResize.push({width,percent,normalSize,actualSize,...await noOverflow('long '+percent+'% '+width)});
    await front.locator('.aloha-community-hero').screenshot({path:out+'/community-long-text'+percent+'-'+width+'.png'});
   }
  }
  check('Split geometry, images/no-image, long and unbroken copy pass at 1440/1024/768/390/320px and 200% text without clipping or horizontal overflow');
  await front.setViewportSize({width:1440,height:1000});await front.goto(f.visual_url);
  for(const index of [0,1]) {
   const button=front.locator('.aloha-community-hero-actions button').nth(index);await button.focus();assert.equal(await button.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');await button.press('Enter');const dialog=front.locator('.aloha-demo-dialog');await dialog.waitFor({state:'visible'});assert.match(await dialog.innerText(),index===0?/not available to browse/:/form is not available/);await front.keyboard.press('Escape');assert.equal(await button.evaluate(e=>document.activeElement===e),true);
  }
  check('Both CTA open shared accessible dialog with honest future state; keyboard focus, Escape and focus return work');
  assert.equal(await front.locator('.aloha-community-hero iframe,.aloha-community-hero video').count(),0);assert.deepEqual(report.pageErrors,[]);report.passed=true;
 }catch(e){report.error=e.message;if(page&&!page.isClosed())await page.screenshot({path:out+'/community-editor-failure.png',mask:[page.locator('#user_login'),page.locator('#user_pass')]});throw e;}
 finally{if(page&&!page.isClosed())for(const id of created.reverse())await api('DELETE','community/'+id+'?force=true').catch(()=>{});fs.writeFileSync(out+'/community-browser-report.json',JSON.stringify(report,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
