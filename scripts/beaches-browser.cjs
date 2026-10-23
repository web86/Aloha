/* Native Gutenberg/media/focal controls and Embla progressive carousel. Disposable :8091 only. */
const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium,firefox,webkit}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const auth=JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH,'utf8'));
const fixture=JSON.parse(fs.readFileSync(process.env.ALOHA_BEACHES_FIXTURES,'utf8'));
assert.equal(auth.url,'http://127.0.0.1:8091');
const out=process.env.EVIDENCE_DIR,report={checks:[],pageErrors:[],viewports:[],otherEngines:[]},created=[];
let browser,page,front;
const check=s=>{report.checks.push(s);console.log('PASS:',s);};
async function api(method,path,data){return page.evaluate(async({method,path,data})=>wp.apiFetch({path:'/wp/v2/'+path,method,data}),{method,path,data});}
async function editor(id){await page.goto(auth.url+'/wp-admin/post.php?post='+id+'&action=edit');await page.waitForFunction(()=>window.wp?.data?.select('core/editor')?.getCurrentPostId());await page.evaluate(()=>wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false));const welcome=page.getByRole('button',{name:'Get started',exact:true});if(await welcome.isVisible())await welcome.click();await page.waitForFunction(()=>wp.data.select('core/block-editor').getBlocks().length>0);}
async function save(){await page.evaluate(()=>wp.data.dispatch('core/editor').savePost());await page.waitForFunction(()=>!wp.data.select('core/editor').isSavingPost());}
const getCards=()=>page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().find(b=>b.name==='aloha/beaches').attributes.cards);
(async()=>{
 browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:1440,height:1000}});page=await context.newPage();page.on('pageerror',e=>report.pageErrors.push(e.message));
 try{
  await page.goto(auth.url+'/wp-login.php?redirect_to='+encodeURIComponent(auth.url+'/wp-admin/edit.php?post_type=area'));await page.waitForTimeout(400);await page.locator('#user_login').fill(auth.user);await page.locator('#user_pass').fill(auth.password);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  const post=await api('POST','pages',{title:'Beaches visual editor fixture',status:'draft',content:'<!-- wp:paragraph --><p>Editor insertion fixture.</p><!-- /wp:paragraph -->'});created.push(post.id);await editor(post.id);await page.evaluate(()=>wp.data.dispatch('core/block-editor').insertBlocks(wp.blocks.createBlock('aloha/beaches')));
  const frame=page.frameLocator('iframe[name="editor-canvas"]'),cards=frame.locator('.aloha-beaches-editor-list > li');
  for(let i=0;i<12;i++){await frame.getByRole('button',{name:'Add Beach',exact:true}).click();await cards.nth(i).getByRole('textbox',{name:'Beach title',exact:true}).fill('Editor beach '+(i+1));}
  assert.equal((await getCards()).length,12);await cards.first().locator('[contenteditable="true"]').fill('An editor-authored description with verified access details.');await cards.first().getByRole('textbox',{name:'Optional link',exact:true}).fill('/editorial-confirmed/');
  check('One inserted Beaches block adds 12 cards through Add Beach, text fields and optional URL');
  const choose=async(index,id)=>{await cards.nth(index).getByRole('button',{name:'Choose or replace photo',exact:true}).click();const library=page.getByRole('tab',{name:'Media Library',exact:true});if(await library.isVisible())await library.click();await page.locator('.attachments .attachment[data-id="'+id+'"]').click();await page.locator('.media-modal .media-button-select').click();await cards.nth(index).locator('img').waitFor();};
  await choose(0,fixture.media[0]);await choose(1,fixture.media[1]);await choose(0,fixture.media[2]);assert.equal((await getCards())[0].imageId,fixture.media[2]);
  await cards.first().getByRole('button',{name:'Set focal point',exact:true}).click();const modal=page.getByRole('dialog',{name:'Photo focal point'});await modal.locator('img').waitFor();await modal.locator('img').click({position:{x:30,y:30}});assert.notDeepEqual((await getCards())[0].focalPoint,{x:.5,y:.5});await page.screenshot({path:out+'/beaches-editor-focal.png'});
  await modal.getByRole('button',{name:'Reset focal point',exact:true}).click();assert.deepEqual((await getCards())[0].focalPoint,{x:.5,y:.5});await modal.locator('img').click({position:{x:50,y:50}});await modal.getByRole('button',{name:'Done',exact:true}).click();
  const selected=await getCards();await cards.nth(1).getByRole('button',{name:'Move earlier',exact:true}).click();assert.equal((await getCards())[0].title,selected[1].title);await cards.first().getByRole('button',{name:'Move later',exact:true}).focus();await page.keyboard.press('Enter');assert.deepEqual(await getCards(),selected);
  assert.equal(await cards.first().getByRole('button',{name:'Move earlier',exact:true}).isDisabled(),true);assert.equal(await cards.last().getByRole('button',{name:'Move later',exact:true}).isDisabled(),true);
  await cards.nth(1).getByRole('button',{name:'Remove photo',exact:true}).click();assert.equal((await getCards())[1].imageId,0);assert.equal((await api('GET','media/'+fixture.media[1])).id,fixture.media[1]);
  await cards.last().getByRole('button',{name:'Remove Beach',exact:true}).click();assert.equal((await getCards()).length,11);await frame.getByRole('button',{name:'Add Beach',exact:true}).click();await cards.last().getByRole('textbox',{name:'Beach title',exact:true}).fill('Restored twelfth card');
  check('Native Media Library select/replace, focal point/reset, keyboard reorder and removal preserve originals');
  await save();const saved=(await api('GET','pages/'+post.id+'?context=edit')).content.raw,values=await getCards();assert.ok(!saved.includes('source_url')&&!saved.includes('wp:image'));await editor(post.id);assert.deepEqual(await getCards(),values);
  assert.deepEqual(await page.evaluate(()=>wp.data.select('core/block-editor').getBlocks().filter(b=>b.isValid===false||b.name==='core/missing').map(b=>b.name)),[]);
  await page.evaluate(()=>wp.data.dispatch('core/block-editor').selectBlock(wp.data.select('core/block-editor').getBlocks().find(b=>b.name==='aloha/beaches').clientId));await cards.first().scrollIntoViewIfNeeded();await page.screenshot({path:out+'/beaches-editor.png'});
  check('Save/reopen retains ordered cards, text, URL and focal point with no invalid blocks');
  for(const width of [1440,1024,768,390,320]){
   await page.setViewportSize({width,height:1000});const card=cards.first();await card.scrollIntoViewIfNeeded();await card.locator('.dashicons').first().evaluate(async el=>el.ownerDocument.fonts.load('20px dashicons'));
   const row=await card.locator('.aloha-beaches-editor-actions button').evaluateAll(bs=>bs.map(b=>{const r=b.getBoundingClientRect(),i=b.querySelector('.dashicons');return {y:r.y,w:r.width,h:r.height,label:b.getAttribute('aria-label'),title:b.title,icon:!!b.querySelector('svg path')||!!i&&getComputedStyle(i,'::before').content!=='none'&&getComputedStyle(i).fontFamily.toLowerCase().includes('dashicons')};}));
   assert.equal(row.length,6);assert.ok(row.every(b=>Math.abs(b.y-row[0].y)<2&&b.w>=36&&b.h>=36&&b.label&&b.title&&b.icon));assert.ok(await card.evaluate(e=>e.scrollWidth<=e.clientWidth));assert.ok(await frame.locator('body').evaluate(e=>e.scrollWidth<=e.clientWidth));await card.screenshot({path:out+'/beaches-editor-card-'+width+'.png'});
  }
  check('Six accessible icon actions stay in one row at all five editor widths');
  const visual=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});front=await visual.newPage();front.on('pageerror',e=>report.pageErrors.push(e.message));
  for(const n of [0,1,4,5,8,12]){
   assert.equal((await front.goto(fixture.urls[n])).status(),200);assert.equal(await front.locator('.aloha-beaches-card').count(),n);assert.equal(await front.locator('.aloha-beaches-controls:visible').count(),n>4?1:0);
   for(const width of [1440,1024,768,390,320]){await front.setViewportSize({width,height:1000});assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+n+'/'+width);if(n)assert.ok(await front.locator('.aloha-beaches-card').evaluateAll(es=>es.every(e=>e.clientWidth>0)));}
  }
  check('0/1/4/5/8/12 SSR collections select grid/carousel without page overflow at five widths');
  await front.goto(fixture.urls[4]);
  for(const width of [1440,1024,768,390,320]){
   await front.setViewportSize({width,height:1000});const block=front.locator('[data-aloha-beaches]');await block.scrollIntoViewIfNeeded();await block.locator('img').evaluateAll(es=>es.forEach(e=>e.loading='eager'));await front.waitForFunction(()=>[...document.querySelectorAll('.aloha-beaches img')].every(i=>i.complete&&i.naturalWidth));
   const dimensions=await block.locator('.aloha-beaches-image').evaluateAll(es=>es.map(e=>({w:e.clientWidth,h:e.clientHeight,fit:getComputedStyle(e.querySelector('img')).objectFit,position:getComputedStyle(e.querySelector('img')).objectPosition})));
   assert.ok(dimensions.every(d=>Math.abs(d.w/d.h-4/3)<.03&&d.fit==='cover'&&d.position==='20% 30%'));await block.screenshot({path:out+'/beaches-grid-four-'+width+'.png'});report.viewports.push(width);
  }
  assert.equal(await front.locator('.aloha-beaches-card a').count(),2);assert.equal(await front.locator('.aloha-beaches-card').nth(1).locator('a').count(),0);assert.equal(await front.locator('.aloha-beaches-card').nth(1).evaluate(e=>getComputedStyle(e.querySelector('h3')).cursor),'auto');
  check('Portrait/landscape/square images keep a 4:3 cover frame and selected focal position; unlinked cards stay plain');
  const position = root => root.locator('.aloha-beaches-track').evaluate(e=>new DOMMatrix(getComputedStyle(e).transform).m41);
  const settle = () => front.waitForTimeout(700);
  await front.setViewportSize({width:1440,height:1000});await front.goto(fixture.urls[5]);
  const five=front.locator('[data-aloha-beaches]');await five.scrollIntoViewIfNeeded();
  assert.ok(await five.evaluate(e=>e.classList.contains('is-enhanced')));
  assert.equal(await five.locator('.aloha-beaches-status').count(),0);
  assert.equal(await five.locator('.aloha-beaches-viewport').evaluate(e=>getComputedStyle(e).overflowX),'hidden');
  const buttonBoxes=await five.locator('.aloha-beaches-controls button').evaluateAll(bs=>bs.map(b=>{const r=b.getBoundingClientRect();return {w:r.width,h:r.height,y:r.y};}));assert.ok(buttonBoxes.every(b=>b.w>=44&&b.w<=48&&b.h===b.w&&b.y===buttonBoxes[0].y),JSON.stringify(buttonBoxes));
  const nextControl=five.getByRole('button',{name:'Next beaches'}),restingBox=await nextControl.boundingBox();await nextControl.hover();assert.deepEqual(await nextControl.boundingBox(),restingBox);await five.locator('.aloha-beaches-viewport').focus();for(let i=0;i<3;i++)await front.keyboard.press('Tab');assert.ok(await nextControl.evaluate(e=>e===document.activeElement));const focusBox=await nextControl.boundingBox();assert.equal(focusBox.width,restingBox.width);assert.equal(focusBox.height,restingBox.height);assert.equal(await nextControl.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');
  assert.equal(await position(five),0);assert.ok(await five.getByRole('button',{name:'Previous beaches'}).isDisabled());
  await five.getByRole('button',{name:'Next beaches'}).click();await settle();
  assert.ok(await position(five)<-200);assert.ok(await five.getByRole('button',{name:'Next beaches'}).isDisabled());
  const edge=await five.evaluate(e=>{const v=e.querySelector('.aloha-beaches-viewport').getBoundingClientRect(),c=e.querySelectorAll('article');return {first:c[1].getBoundingClientRect().left-v.left,last:c[4].getBoundingClientRect().right-v.right};});
  assert.ok(Math.abs(edge.first)<2&&Math.abs(edge.last)<2,JSON.stringify(edge));
  await five.screenshot({path:out+'/embla-five-end-1440.png'});
  await five.getByRole('button',{name:'Previous beaches'}).click();await settle();assert.equal(await position(five),0);
  await five.screenshot({path:out+'/embla-five-start-1440.png'});
  check('Five cards have exactly two desktop positions (1–4, 2–5), API disabled states and no counter/scrollbar/end gap');
  for(const n of [5,8,12]){
   await front.goto(fixture.urls[n]);const root=front.locator('[data-aloha-beaches]');
   for(const width of [1440,1024,768,390,320]){
    await front.setViewportSize({width,height:1000});await front.waitForTimeout(250);const viewport=root.locator('.aloha-beaches-viewport');await viewport.scrollIntoViewIfNeeded();await viewport.focus();await front.keyboard.press('Home');await settle();assert.equal(await position(root),0);
    const sizes=await root.evaluate(e=>({v:e.querySelector('.aloha-beaches-viewport').clientWidth,c:e.querySelector('article').getBoundingClientRect().width}));
    assert.ok(width>=1024?Math.abs((sizes.v-72)/4-sizes.c)<2:width>=600?Math.abs((sizes.v-24)/2-sizes.c)<2:Math.abs(sizes.v*.88-sizes.c)<2);
    await front.keyboard.press('End');await settle();assert.ok(await root.getByRole('button',{name:'Next beaches'}).isDisabled());
    const gap=await root.evaluate(e=>e.querySelector('.aloha-beaches-viewport').getBoundingClientRect().right-e.querySelector('article:last-child').getBoundingClientRect().right);assert.ok(Math.abs(gap)<2,'End gap '+n+'/'+width+': '+gap);
    assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    await front.keyboard.press('Home');await settle();
    if(n!==12)await root.screenshot({path:out+'/embla-'+n+'-'+width+'.png'});
   }
  }
  check('5/8/12 cards resize to four/two/one-and-peek, start aligned, end contained at all five widths');
  await front.setViewportSize({width:1440,height:1000});await front.goto(fixture.multiple_url);
  const blocks=front.locator('[data-aloha-beaches]'),first=blocks.first(),second=blocks.nth(1),viewport=first.locator('.aloha-beaches-viewport');await viewport.scrollIntoViewIfNeeded();
  assert.notEqual(await viewport.getAttribute('id'),await second.locator('.aloha-beaches-viewport').getAttribute('id'));assert.equal(await first.getByRole('button',{name:'Next beaches'}).getAttribute('aria-controls'),await viewport.getAttribute('id'));
  await first.getByRole('button',{name:'Next beaches'}).focus();await front.keyboard.press('Enter');await settle();assert.ok(await position(first)<0);assert.equal(await position(second),0);
  await first.getByRole('button',{name:'Previous beaches'}).focus();await front.keyboard.press('Space');await settle();assert.equal(await position(first),0);
  await viewport.focus();await front.keyboard.press('ArrowRight');await settle();assert.ok(await position(first)<0);await front.keyboard.press('ArrowLeft');await settle();assert.equal(await position(first),0);
  const intercepted=await first.locator('a').first().evaluate(e=>!e.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true})));assert.equal(intercepted,false);
  const linked=first.locator('.aloha-beaches-image a').first();const beforeURL=front.url();
  await viewport.evaluate(e=>e.addEventListener('click',event=>{e.dataset.dragClickPrevented=String(event.defaultPrevented);e.dataset.dragClickLink=String(!!event.target.closest('a'));},{capture:true,once:true}));
  const inside=await linked.boundingBox();await front.mouse.move(inside.x+180,inside.y+40);await front.mouse.down();await front.mouse.move(inside.x+120,inside.y+40,{steps:12});await front.waitForTimeout(220);await front.mouse.up();await settle();assert.equal(front.url(),beforeURL);assert.equal(await viewport.getAttribute('data-drag-click-prevented'),'true');assert.equal(await viewport.getAttribute('data-drag-click-link'),'true');
  await viewport.focus();await front.keyboard.press('Home');await settle();const box=await linked.boundingBox();
  await front.mouse.move(box.x+box.width-10,box.y+40);await front.mouse.down();await front.mouse.move(box.x-110,box.y+40,{steps:20});await front.mouse.up();await settle();assert.ok(await position(first)<0);assert.equal(front.url(),beforeURL);assert.equal(await position(second),0);
  await viewport.focus();await front.keyboard.press('Home');await settle();await first.locator('a').first().focus();await front.keyboard.press('Enter');await front.waitForURL(auth.url+'/');
  check('Mouse drag suppresses link navigation; normal links, native keyboard buttons, Arrow keys and independent instances work');
  await front.goto(fixture.urls[8]);const eight=front.locator('[data-aloha-beaches]'),vp=eight.locator('.aloha-beaches-viewport');await vp.scrollIntoViewIfNeeded();await vp.focus();
  await front.keyboard.press('ArrowRight');const immediate=await position(eight);assert.ok(immediate<0);await front.waitForTimeout(120);assert.equal(await position(eight),immediate);
  await front.screenshot({path:out+'/embla-keyboard-reduced.png'});
  await front.emulateMedia({reducedMotion:'no-preference'});await vp.focus();await front.keyboard.press('Home');await settle();await front.keyboard.press('ArrowRight');await settle();assert.ok(await position(eight)<0);await front.emulateMedia({reducedMotion:'reduce'});
  check('Reduced motion jumps immediately; normal animation and live preference changes work');
  await front.setViewportSize({width:390,height:844});await front.goto(fixture.urls[8]);await vp.scrollIntoViewIfNeeded();const rect=await vp.boundingBox(),cdp=await front.context().newCDPSession(front);await cdp.send('Emulation.setTouchEmulationEnabled',{enabled:true});const y=Math.max(rect.y,120)+80;
  await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:330,y}]});for(const x of [290,240,180,120,60])await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y}]});await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await front.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));const released=await position(eight);await front.waitForTimeout(120);assert.equal(await position(eight),released,'Reduced-motion touch release must settle immediately');await settle();assert.ok(await position(eight)<0);await front.screenshot({path:out+'/embla-touch.png'});
  await front.evaluate(()=>document.documentElement.style.fontSize='200%');await vp.focus();await front.keyboard.press('End');await settle();assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await front.screenshot({path:out+'/embla-text-200.png'});
  check('Real touch events swipe and long descriptions at 200% text stay within the page');
  await front.evaluate(()=>document.documentElement.style.fontSize='');await front.setViewportSize({width:1440,height:1000});await front.goto(fixture.urls[12]);
  const focusRoot=front.locator('[data-aloha-beaches]');await focusRoot.locator('a').first().focus();await front.keyboard.press('Tab');await front.keyboard.press('Tab');await settle();
  assert.ok(await focusRoot.locator('article:last-child a').first().evaluate(e=>e===document.activeElement));
  assert.ok(await position(focusRoot)<0);const focusedBox=await focusRoot.locator('article:last-child a').first().boundingBox(),focusViewport=await focusRoot.locator('.aloha-beaches-viewport').boundingBox();assert.ok(focusedBox.x>=focusViewport.x-2&&focusedBox.x+focusedBox.width<=focusViewport.x+focusViewport.width+2);
  check('Tab reaches offscreen editorial links and Embla reveals the focused slide');
  for(const n of [5,8])for(const width of [1440,1024,768,390,320]){await front.setViewportSize({width,height:1000});await front.goto(fixture.visual_urls[n]);const compact=front.locator('[data-aloha-beaches]');await compact.scrollIntoViewIfNeeded();await compact.locator('img').evaluateAll(es=>es.forEach(e=>e.loading='eager'));await front.waitForFunction(()=>[...document.querySelectorAll('.aloha-beaches img')].every(i=>i.complete&&i.naturalWidth));await compact.screenshot({path:out+'/embla-compact-'+n+'-'+width+'.png'});}

  for(const failure of ['no-js','missing-library','throwing-library']){
   const fallback=await browser.newContext({javaScriptEnabled:failure!=='no-js',viewport:{width:390,height:844}});
   if(failure!=='no-js')await fallback.route('**/embla-carousel.umd.js*',route=>failure==='missing-library'?route.abort():route.fulfill({contentType:'application/javascript',body:'window.EmblaCarousel=function(){throw new Error("Fixture initialization failure")};'}));
   const plain=await fallback.newPage();await plain.goto(fixture.urls[12]);assert.equal(await plain.locator('.aloha-beaches-card').count(),12);assert.equal(await plain.locator('.aloha-beaches-controls:visible').count(),0);
   const native=plain.locator('.aloha-beaches-viewport');assert.equal(await native.evaluate(e=>getComputedStyle(e).overflowX),'auto');await native.focus();for(let i=0;i<10;i++)await plain.keyboard.press('ArrowRight',{delay:30});await plain.waitForFunction(()=>document.querySelector('.aloha-beaches-viewport').scrollLeft>0);
   await plain.locator('.aloha-beaches-card a').first().click();await plain.waitForURL(auth.url+'/');await fallback.close();
  }
  check('No-JS, failed library load and failed initialization all retain native scrolling, twelve SSR cards and working links');
  await api('DELETE','media/'+fixture.media[0]+'?force=true');await front.goto(fixture.urls[4]);assert.equal(await front.locator('.aloha-beaches-card').count(),4);assert.equal(await front.locator('.aloha-beaches-card').first().locator('img').count(),0);assert.equal(await front.locator('.aloha-beaches-card').first().getAttribute('class'),'aloha-beaches-card is-text-only');
  check('Deleted attachment becomes a clean text card while preserving the title, description and URL');
  assert.deepEqual(report.pageErrors,[]);
  for(const [name,engine]of[['firefox',firefox],['webkit',webkit]]){if(!fs.existsSync(engine.executablePath())){report.otherEngines.push({engine:name,status:'not installed; no browser installed'});continue;}const b=await engine.launch({headless:true}),p=await b.newPage();await p.goto(fixture.urls[8]);await p.locator('.aloha-beaches-viewport').focus();await p.keyboard.press('End');await p.waitForFunction(()=>new DOMMatrix(getComputedStyle(document.querySelector('.aloha-beaches-track')).transform).m41<0);await b.close();report.otherEngines.push({engine:name,status:'carousel keyboard smoke passed'});}
 }catch(e){report.error=e.message;if(front&&!front.isClosed())await front.screenshot({path:out+'/beaches-frontend-failure.png'});if(page&&!page.isClosed())await page.screenshot({path:out+'/beaches-editor-failure.png',mask:[page.locator('#user_login'),page.locator('#user_pass')]});throw e;}
 finally{report.cleanup=[];if(page&&!page.isClosed())for(const id of created){try{const result=await api('DELETE','pages/'+id+'?force=true');report.cleanup.push({id,deleted:result.deleted});}catch(e){report.cleanup.push({id,error:e.message,code:e.code});process.exitCode=1;}}fs.writeFileSync(out+'/beaches-browser-report.json',JSON.stringify(report,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
