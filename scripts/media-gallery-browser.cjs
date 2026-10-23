/* Real Gutenberg and native dialog acceptance. Disposable :8091 only. */
const fs=require('node:fs'), assert=require('node:assert/strict');
const {chromium,firefox,webkit}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const auth=JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH,'utf8'));
const fixture=JSON.parse(fs.readFileSync(process.env.ALOHA_MEDIA_GALLERY_FIXTURES,'utf8'));
assert.equal(auth.url,'http://127.0.0.1:8091');
const out=process.env.EVIDENCE_DIR, report={checks:[],pageErrors:[],viewports:[],otherEngines:[]}, created=[];
let browser,page,front;
const check=label=>{report.checks.push(label);console.log('PASS:',label);};
const all=bs=>bs.flatMap(b=>[b,...all(b.innerBlocks||[])]);
async function api(method,path,data){return page.evaluate(async({method,path,data})=>wp.apiFetch({path:'/wp/v2/'+path,method,data}),{method,path,data});}
async function editor(id){
 await page.goto(auth.url+'/wp-admin/post.php?post='+id+'&action=edit');
 await page.waitForFunction(()=>window.wp?.data?.select('core/editor')?.getCurrentPostId());
 await page.evaluate(()=>wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false));
 const welcome=page.getByRole('button',{name:'Get started',exact:true});if(await welcome.isVisible())await welcome.click();
 await page.waitForFunction(()=>wp.data.select('core/block-editor').getBlocks().length>0);
}
async function save(){await page.evaluate(()=>wp.data.dispatch('core/editor').savePost());await page.waitForFunction(()=>!wp.data.select('core/editor').isSavingPost());}
(async()=>{
 browser=await chromium.launch({headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});page=await context.newPage();page.on('pageerror',e=>report.pageErrors.push(e.message));
 try{
  await page.goto(auth.url+'/wp-login.php?redirect_to='+encodeURIComponent(auth.url+'/wp-admin/edit.php?post_type=area'));await page.waitForTimeout(400);
  await page.locator('#user_login').fill(auth.user);await page.locator('#user_pass').fill(auth.password);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  const record=await api('POST','pages',{title:'Gallery editor fixture',status:'draft',content:'<!-- wp:paragraph --><p>Gallery editor insertion fixture.</p><!-- /wp:paragraph -->'});created.push(record.id);await editor(record.id);
  await page.evaluate(()=>wp.data.dispatch('core/block-editor').insertBlocks(wp.blocks.createBlock('aloha/media-gallery')));
  const frame=page.frameLocator('iframe[name="editor-canvas"]');
  await frame.getByRole('button',{name:'Select photos',exact:true}).click();
  const library=page.getByRole('tab',{name:'Media Library',exact:true});if(await library.isVisible())await library.click();
  await page.locator('.attachments .attachment').first().waitFor();
  // Actual standard multi-select Media Library, not an injected array in the editor store.
  for(const id of fixture.ids)await page.locator('.attachments .attachment[data-id="'+id+'"]').click();
  const select=page.locator('.media-modal .media-button-select');await select.click();
  await page.waitForFunction(()=>wp.data.select('core/block-editor').getBlocks().find(b=>b.name==='aloha/media-gallery')?.attributes.ids.length===24);
  check('One inserted Media Gallery selects 24 photographs through the native Media Library');
  const getIds=()=>page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().find(b=>b.name==='aloha/media-gallery').attributes.ids);
  const selected=await getIds();assert.equal(selected.length,24);
  assert.equal(await frame.getByRole('button',{name:'Move earlier',exact:true}).first().isDisabled(),true);
  assert.equal(await frame.getByRole('button',{name:'Move later',exact:true}).last().isDisabled(),true);
  await frame.getByRole('button',{name:'Move earlier',exact:true}).nth(1).click();assert.equal((await getIds())[0],selected[1]);
  await frame.getByRole('button',{name:'Move later',exact:true}).first().focus();await page.keyboard.press('Enter');assert.deepEqual(await getIds(),selected);
  check('Icon buttons move earlier/later and correctly disable the first/last boundary actions');
  await frame.getByRole('button',{name:'Make lead image',exact:true}).nth(23).click();
  assert.equal((await getIds())[0],selected[23]);
  await frame.getByRole('button',{name:'Remove from gallery',exact:true}).nth(23).click();assert.equal((await getIds()).length,23);
  await frame.getByRole('button',{name:'Add photos',exact:true}).click();await page.locator('.attachments .attachment').first().waitFor();
  await page.locator('.attachments .attachment[data-id="'+selected[22]+'"]').click();await select.click();assert.equal((await getIds()).length,24);
  check('Make lead, remove and append operations preserve a single ordered ID array');
  // Replace with an already used item removes the duplicate instead of copying its URL.
  await frame.getByRole('button',{name:'Replace photo',exact:true}).first().click();await page.locator('.attachments .attachment').first().waitFor();
  await page.locator('.attachments .attachment[data-id="'+fixture.ids[0]+'"]').click();await select.click();
  assert.equal((await getIds())[0],fixture.ids[0]);assert.equal((await getIds()).length,23);
  // Use a test-owned attachment for shared metadata editing, preserving the Area regression originals.
  const position=(await getIds()).indexOf(fixture.ids[5]);
  await frame.getByRole('button',{name:'Edit image details',exact:true}).nth(position).click();
  await page.getByRole('dialog',{name:'Edit Media Library details'}).waitFor();assert.match(await page.getByRole('dialog',{name:'Edit Media Library details'}).innerText(),/everywhere it is used/);
  await page.getByRole('textbox',{name:'Alt text',exact:true}).fill('Accessible gallery metadata edited in Gutenberg');
  await page.getByRole('textbox',{name:'Caption',exact:true}).fill('A long editorial caption. '.repeat(30));
  await page.getByRole('button',{name:'Save media details',exact:true}).click();
  await page.getByRole('dialog',{name:'Edit Media Library details'}).waitFor({state:'hidden'});
  const updated=await api('GET','media/'+fixture.ids[5]+'?context=edit');assert.equal(updated.alt_text,'Accessible gallery metadata edited in Gutenberg');assert.match(updated.caption.raw,/A long editorial caption/);
  check('Replace and native attachment alt/caption editing update Media Library data without new post meta');
  await save();const saved=(await api('GET','pages/'+record.id+'?context=edit')).content.raw;
  assert.ok(!saved.includes('source_url')&&!saved.includes('wp:image'));
  const savedIds=await getIds();await editor(record.id);assert.deepEqual(await getIds(),savedIds);
  const invalid=await page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().filter(b=>b.isValid===false||b.name==='core/missing').map(b=>b.name));assert.deepEqual(invalid,[]);
  await page.evaluate(()=>{const b=wp.data.select('core/block-editor').getBlocks().find(b=>b.name==='aloha/media-gallery');wp.data.dispatch('core/block-editor').selectBlock(b.clientId);});
  await frame.locator('.aloha-gallery-mosaic img').first().waitFor();await page.screenshot({path:out+'/gallery-editor.png',fullPage:true});
  check('Saving and reopening preserve the dynamic ID-only block without invalid markup');
  for(const width of [1440,1024,768,390,320]){
   await page.setViewportSize({width,height:1000});const card=frame.locator('.aloha-gallery-editor-list li').first();await card.scrollIntoViewIfNeeded();await card.locator('.dashicons').first().evaluate(async el=>{await el.ownerDocument.fonts.load('20px dashicons');});
   const layout=await card.locator('.aloha-gallery-editor-actions button').evaluateAll(bs=>bs.map(b=>{const r=b.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,label:b.getAttribute('aria-label'),title:b.title,icon:(()=>{const icon=b.querySelector('.dashicons');return !!b.querySelector('svg path') || !!icon && getComputedStyle(icon,'::before').content!=='none' && getComputedStyle(icon,'::before').content!=='normal' && getComputedStyle(icon).fontFamily.toLowerCase().includes('dashicons');})()};}));
   assert.equal(layout.length,6);assert.ok(layout.every(b=>Math.abs(b.y-layout[0].y)<2&&b.w>=36&&b.h>=36&&b.label&&b.title&&b.icon),'One accessible 36px icon row at '+width);
   assert.ok(await card.evaluate(el=>el.scrollWidth<=el.clientWidth),'Card overflow at '+width);
   assert.ok(await frame.locator('body').evaluate(el=>el.scrollWidth<=el.clientWidth),'Editor canvas overflow at '+width);
   assert.equal(await card.locator('.aloha-gallery-lead-label').innerText(),'Lead image');
   await card.screenshot({path:out+'/gallery-toolbar-'+width+'.png'});
  }
  await page.setViewportSize({width:1440,height:1000});check('Six native icon controls, lead badge and nonwrapping toolbars fit 1440/1024/768/390/320 editor canvases');

  const visual=await browser.newContext({viewport:{width:1440,height:1000}});front=await visual.newPage();front.on('pageerror',e=>report.pageErrors.push(e.message));
  const requested=[];front.on('request',r=>requested.push(r.url()));
  await front.route('https://www.youtube-nocookie.com/**',r=>r.fulfill({contentType:'text/html',body:'<!doctype html><title>Isolated YouTube test double</title><button>Video test fixture</button>'}));
  assert.equal((await front.goto(fixture.five_url)).status(),200);await front.locator('.aloha-gallery-mosaic').scrollIntoViewIfNeeded();await front.evaluate(()=>document.fonts.ready);
  assert.equal(await front.locator('.aloha-gallery-all').count(),0);assert.equal(await front.getByRole('link',{name:'View all 5 photos',exact:true}).innerText(),'View all photos (5)');
  assert.equal(await front.locator('.aloha-gallery-mosaic img').count(),5);assert.equal(await front.locator('iframe').count(),0);assert.ok(!requested.some(u=>u.includes('youtube')));
  await front.evaluate(()=>window.scrollBy(0,-120));await front.locator('[data-aloha-gallery]').screenshot({path:out+'/gallery-five.png'});
  await front.getByRole('link',{name:'View all 5 photos',exact:true}).click();await front.locator('dialog[open]').waitFor();assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'1 / 5');await front.keyboard.press('Escape');
  await front.locator('.aloha-gallery-photo').nth(4).click({position:{x:10,y:10}});assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'5 / 5');await front.keyboard.press('Escape');
  await front.locator('.aloha-gallery-photo').first().click();const dialog=front.locator('.aloha-media-dialog[open]');await dialog.waitFor();
  await front.waitForFunction(()=>document.querySelector('.aloha-media-stage img')?.naturalWidth>0);
  assert.equal(await dialog.locator('[role="status"]').innerText(),'1 / 5');assert.equal(await dialog.locator('img').count(),1);
  await front.keyboard.press('ArrowRight');assert.equal(await dialog.locator('[role="status"]').innerText(),'2 / 5');await front.keyboard.press('ArrowLeft');assert.equal(await dialog.locator('[role="status"]').innerText(),'1 / 5');
  await front.screenshot({path:out+'/gallery-lightbox.png'});
  for(let i=0;i<8;i++){await front.keyboard.press('Tab');assert.equal(await front.evaluate(()=>document.activeElement===document.body||document.activeElement.closest('dialog')!==null),true);}
  await front.keyboard.press('Escape');assert.equal(await front.locator('dialog[open]').count(),0);assert.equal(await front.evaluate(()=>document.activeElement.matches('.aloha-gallery-photo')),true);
  check('Native photo dialog supports arrows, Tab containment, Escape, focus return and one active image');
  await front.keyboard.press('Enter');await front.locator('.aloha-media-dialog[open] img').waitFor();await front.keyboard.press('ArrowRight');assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'2 / 5');await front.keyboard.press('Escape');
  await front.locator('.aloha-gallery-film').click();await front.locator('.aloha-media-dialog iframe').waitFor();assert.equal(await front.locator('.aloha-media-dialog').count(),1);
  await front.screenshot({path:out+'/gallery-film.png'});await front.getByRole('button',{name:'Close',exact:true}).click();await front.waitForFunction(()=>!document.querySelector('.aloha-media-stage iframe'));assert.equal(await front.locator('iframe').count(),0);
  check('Separate lead film control reuses the one lazy YouTube dialog and removes its iframe on close');
  requested.length=0;await front.goto(fixture.many_url);const first=front.locator('[data-aloha-gallery]').first();await first.scrollIntoViewIfNeeded();
  assert.equal(await first.locator('.aloha-gallery-photo').count(),5);assert.equal(await first.locator('.aloha-gallery-more').innerText(),'+19 photos');
  assert.ok(!requested.some(u=>/gallery-test-\d+\.jpg/.test(u)),'Hidden full images must not load on the initial page');
  await first.locator('.aloha-gallery-more').click();assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'1 / 24');for(let i=0;i<5;i++)await front.keyboard.press('ArrowRight');
  await front.waitForFunction(()=>document.querySelector('.aloha-media-stage img')?.naturalWidth>0);
  assert.match(await front.locator('.aloha-media-caption').innerText(),/A long editorial caption/);await front.setViewportSize({width:390,height:844});await front.locator('.aloha-media-caption').focus();await front.keyboard.press('ArrowDown');await front.waitForFunction(()=>document.querySelector('.aloha-media-caption').scrollTop>0);await front.setViewportSize({width:1440,height:1000});
  await front.keyboard.press('Escape');await front.locator('[data-aloha-gallery]').nth(1).locator('.aloha-gallery-photo').first().click();assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'1 / 2');await front.keyboard.press('Escape');
  assert.equal(await front.locator('.aloha-media-dialog').count(),1);
  check('24-photo count, remaining-photo action, long caption and multiple galleries use the correct collection');
  for(const width of [1440,1024,768,390,320]){
   await front.setViewportSize({width,height:1000});await first.scrollIntoViewIfNeeded();await front.evaluate(()=>window.scrollBy(0,-120));await front.waitForTimeout(100);
   await first.locator('img').evaluateAll(imgs=>imgs.forEach(i=>i.loading='eager'));await front.waitForFunction(()=>[...document.querySelectorAll('.aloha-gallery-mosaic img')].every(i=>i.complete&&i.naturalWidth));
   assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+width);
   await first.screenshot({path:out+'/gallery-24-'+width+'.png'});report.viewports.push(width);
   if(width===1440){const tiles=await first.locator('figure').evaluateAll(es=>es.map(e=>({x:e.offsetLeft,y:e.offsetTop,w:e.clientWidth,h:e.clientHeight})));assert.ok(tiles[0].w>tiles[1].w*1.9);assert.equal(tiles[0].h,530);}
  }
  await front.setViewportSize({width:390,height:844});await first.locator('.aloha-gallery-photo').first().click();
  const stage=front.locator('.aloha-media-stage'),box=await stage.boundingBox();const cdp=await front.context().newCDPSession(front);await cdp.send('Emulation.setTouchEmulationEnabled',{enabled:true});await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:box.x+box.width*.8,y:box.y+box.height*.5}]});await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:box.x+box.width*.2,y:box.y+box.height*.5}]});await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
  assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'2 / 24');await front.screenshot({path:out+'/gallery-mobile-lightbox.png'});await front.keyboard.press('Escape');
  await front.evaluate(()=>document.documentElement.style.fontSize='200%');await first.locator('.aloha-gallery-more').click();assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await front.screenshot({path:out+'/gallery-text-200.png'});await front.keyboard.press('Escape');
  check('1440/1024/768/390/320 mosaics, touch swipe and 200% text remain usable without horizontal overflow');
  const variable=await api('POST','pages',{title:'Variable size gallery fixture',status:'publish',content:'<!-- wp:aloha/media-gallery /-->'});created.push(variable.id);
  for(const n of [0,1,2,4,5,6,12,24]){
   await api('POST','pages/'+variable.id,{content:'<!-- wp:aloha/media-gallery '+JSON.stringify({ids:fixture.ids.slice(0,n)})+' /-->'});
   await front.goto(variable.link);assert.equal(await front.locator('.aloha-gallery-tile').count(),Math.min(5,n));assert.equal(await front.locator('.aloha-gallery-more').count(),n>=5?1:0);assert.equal(await front.locator('.aloha-gallery-all').count(),0);if(n>=5)assert.equal(await front.locator('figure').last().locator('.aloha-gallery-more').count(),1);
   for(const width of [1440,320]){await front.setViewportSize({width,height:1000});assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));if(n)assert.ok(await front.locator('.aloha-gallery-tile').evaluateAll(es=>es.every(e=>e.clientWidth>0&&e.clientHeight>0)));}
  }
  check('Counts 0/1/2/4/5/6/12/24 render nonempty tiles and no overflow on desktop and phone');
  await front.goto(fixture.empty_url);assert.equal(await front.locator('[data-aloha-gallery]').count(),0);
  await front.goto(fixture.legacy_url);assert.equal(await front.locator('.aloha-area-gallery img').count(),5);
  const nojs=await browser.newContext({javaScriptEnabled:false});const simple=await nojs.newPage();await simple.goto(fixture.many_url);assert.equal(await simple.locator('noscript li a').count(),26);const original=await simple.locator('noscript li a').first().getAttribute('href');const originalResponse=await simple.goto(original);assert.equal(originalResponse.status(),200);assert.match(originalResponse.headers()['content-type'],/image\//);await nojs.close();
  check('Empty gallery renders no slots, legacy Core galleries survive, and all photo links work without JS');
  // A removed attachment is filtered by SSR, including both collection and actual count.
  await api('DELETE','media/'+fixture.ids.at(-1)+'?force=true');await front.goto(fixture.many_url);assert.equal(await front.locator('.aloha-gallery-more').innerText(),'+18 photos');
  // Simulate a remote or subsequently missing file after the SSR validity check.
  await front.route('**/gallery-test-6*',r=>r.abort());await front.goto(fixture.many_url);await front.locator('.aloha-gallery-more').click();for(let i=0;i<5;i++)await front.keyboard.press('ArrowRight');await front.getByText('This photo is currently unavailable.',{exact:true}).waitFor();await front.keyboard.press('ArrowRight');assert.equal(await front.locator('.aloha-media-dialog [role="status"]').innerText(),'7 / 23');await front.keyboard.press('Escape');
  check('Deleted attachments adjust counts; a failed image keeps the viewer navigable');
  await front.close();assert.deepEqual(report.pageErrors,[]);
  for(const [name,engine] of [['firefox',firefox],['webkit',webkit]]){
   if(!fs.existsSync(engine.executablePath())){report.otherEngines.push({engine:name,status:'not installed; no browser installed for this task'});continue;}
   const alternate=await engine.launch({headless:true});const p=await alternate.newPage();await p.goto(fixture.five_url);await p.locator('.aloha-gallery-photo').first().click();await p.locator('dialog[open] img').waitFor();await p.keyboard.press('Escape');assert.equal(await p.locator('dialog[open]').count(),0);await alternate.close();report.otherEngines.push({engine:name,status:'photo/Escape smoke passed'});
  }
 }catch(e){report.error=e.message;if(front&&!front.isClosed()){await front.screenshot({path:out+'/gallery-frontend-failure.png'});report.dialogFailure=await front.locator('dialog').evaluateAll(es=>es.map(el=>({open:el.open,html:el.innerHTML})));}if(page&&!page.isClosed())await page.screenshot({path:out+'/gallery-failure.png',fullPage:true,mask:[page.locator('#user_login'),page.locator('#user_pass')]});throw e;}
 finally{if(page&&!page.isClosed()){for(const id of created)await api('DELETE','pages/'+id+'?force=true').catch(()=>{});}fs.writeFileSync(out+'/media-gallery-browser-report.json',JSON.stringify(report,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
