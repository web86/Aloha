const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const {mkdirSync}=require('node:fs');
const {join}=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base='http://127.0.0.1:8088',key='m24-fixture-'+randomBytes(8).toString('hex');
function fixture(action,...args){const r=spawnSync('./bin/local',['wp','eval-file','/var/www/m24-tests/rental-template-browser-fixture.php',action,key,...args],{encoding:'utf8',timeout:60000});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout.trim();}
(async()=>{const browser=await chromium.launch();try{
 const data=JSON.parse(fixture('create'));const related=JSON.parse(fixture('related'));
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
 const external=[];await context.route('**/*',r=>{const url=new URL(r.request().url());if(url.origin===base)return r.continue();external.push(url.hostname);return r.abort();});
 const page=await context.newPage();let cls=0;await page.addInitScript(()=>{window.fixtureCLS=0;new PerformanceObserver(list=>{for(const e of list.getEntries())if(!e.hadRecentInput)window.fixtureCLS+=e.value;}).observe({type:'layout-shift',buffered:true});});
 await page.goto(data.public.url);
 const section=page.locator('.aloha-rental-related');const cards=section.locator('.aloha-rental-related-card');
 assert.equal(await cards.count(),3);
 assert.deepEqual(await cards.locator('h3').allTextContents(),['Access to private Beach Club','A peaceful island home with a deliberately long title overlooking the coastline','Morning light']);
 assert.equal(await section.getByRole('heading',{name:'More vacation rentals in Keauhou'}).count(),1);
 assert.equal(await section.locator('h2 em').innerText(),'in Keauhou');
 assert.ok((await section.getByRole('link',{name:'See all homes'}).getAttribute('href')).includes(key+'-area'));
 assert.equal(await cards.nth(0).locator('.aloha-related-slide').count(),6);
 assert.equal(await cards.nth(1).locator('.aloha-related-slide').count(),2);
 assert.equal(await cards.nth(2).locator('img').count(),0);
 assert.equal(await cards.nth(2).locator('[data-related-controls]').count(),0);
 assert.equal(await cards.nth(0).locator('.aloha-related-badge').innerText(),'NEW');
 assert.equal(await cards.nth(1).locator('.aloha-related-badge').innerText(),'LUXE');
 assert.equal(await cards.nth(2).locator('.aloha-related-badge').count(),0);
 assert.equal(await section.locator('.aloha-related-price').filter({hasText:'Check dates for pricing'}).count(),3);
 assert.equal(await section.locator('a button').count(),0);
 assert.ok(!await section.getByText(/Excluded/).count());
 for(const [selector,color] of [['.aloha-rental-amenities-section [data-rental-more]','rgb(22, 35, 42)'],['.aloha-rental-contact > a','rgb(243, 246, 246)']]){const button=page.locator(selector);await button.scrollIntoViewIfNeeded();const before=await button.boundingBox();assert.equal(await button.evaluate(el=>getComputedStyle(el).borderTopColor),color);await button.hover();await button.focus();const after=await button.boundingBox();assert.equal(after.width,before.width);assert.equal(after.height,before.height);}
 const first=cards.nth(0).locator('[data-related-carousel]'),second=cards.nth(1).locator('[data-related-carousel]');
 assert.equal(await first.locator('img[src]').count(),1,'Inactive photos were eagerly assigned src');
 await first.getByRole('button',{name:/Next photo/}).click();
 await page.waitForFunction(()=>document.querySelector('[data-related-carousel]').dataset.relatedIndex==='1');
 assert.equal(await second.getAttribute('data-related-index'),'0');
 assert.equal(await first.locator('img[src]').count(),2);
 await first.locator('[data-related-dot="3"]').click();assert.equal(await first.getAttribute('data-related-index'),'3');
 await first.locator('[data-related-viewport]').focus();await page.keyboard.press('End');assert.equal(await first.getAttribute('data-related-index'),'5');
 await page.keyboard.press('Home');assert.equal(await first.getAttribute('data-related-index'),'0');
 assert.equal(page.url(),data.public.url);
 await first.getByRole('button',{name:/Next photo/}).click();
 await first.locator('img').nth(1).evaluate(image=>image.decode());
 await first.getByRole('button',{name:/Previous photo/}).click();
 await first.locator('img').first().evaluate(image=>image.decode());
 const cachedImages=new Set(await first.locator('img').evaluateAll(images=>images.slice(0,2).map(image=>image.currentSrc)));
 const requests=[];page.on('request',r=>requests.push(r.url()));
 await first.getByRole('button',{name:/Next photo/}).click();
 await first.getByRole('button',{name:/Previous photo/}).click();
 assert.equal(requests.filter(u=>cachedImages.has(u)).length,0,'Returning to loaded slide caused another request');
 const screenshots=process.env.RELATED_SCREENSHOTS;if(screenshots)mkdirSync(screenshots,{recursive:true});
 for(const width of [320,390,768,1024,1440]){
  await page.setViewportSize({width,height:1000});await section.scrollIntoViewIfNeeded();await page.evaluate(()=>document.fonts.ready);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'Overflow at '+width);
  assert.equal((await section.locator('.aloha-rental-related-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns)).split(' ').length,width>=1024?3:width>=600?2:1);
  for(const card of await cards.all()){
   const usp=card.locator('.aloha-related-usp');assert.deepEqual(await usp.evaluate(el=>{const s=getComputedStyle(el);return[s.whiteSpace,s.textOverflow,s.overflow];}),['nowrap','ellipsis','hidden']);
   assert.equal(await usp.getAttribute('title'),await usp.innerText());
   const box=await card.locator('.aloha-related-gallery').boundingBox();assert.ok(Math.abs(box.width/box.height-4/3)<.01);
  }
  if(width>=1024){for(const selector of ['h3','.aloha-related-area','.aloha-related-usp','.aloha-related-facts','.aloha-related-price']){const ys=await cards.locator(selector).evaluateAll(els=>els.map(el=>el.getBoundingClientRect().y));assert.ok(Math.max(...ys)-Math.min(...ys)<2,'Uneven rows: '+selector);}}
  await page.mouse.move(1,1);await page.evaluate(()=>document.activeElement?.blur());
  if(screenshots&&[390,1440].includes(width))await section.screenshot({path:join(screenshots,'related-'+width+'.png'),style:'.aloha-site-header,.aloha-bottom-bar{visibility:hidden}'});
  console.log('PASS related layout',width);
 }
 // Mouse swipe on the second independent carousel.
 await page.setViewportSize({width:390,height:900});await second.scrollIntoViewIfNeeded();const box=await second.locator('[data-related-viewport]').boundingBox();
 await page.mouse.move(box.x+box.width*.85,box.y+box.height*.5);await page.mouse.down();await page.mouse.move(box.x+box.width*.15,box.y+box.height*.5,{steps:15});await page.mouse.up();
 await page.waitForFunction(()=>document.querySelectorAll('[data-related-carousel]')[1].dataset.relatedIndex==='1');
 const metrics=await cards.locator('img[src]').evaluateAll(els=>els.filter(el=>el.currentSrc && el.complete && el.naturalWidth).map(el=>({src:new URL(el.currentSrc).pathname,intrinsicWidth:el.naturalWidth,sourceWidth:Number(el.currentSrc.match(/-(\d+)x\d+\./)?.[1]||el.naturalWidth),bytes:performance.getEntriesByName(el.currentSrc).at(-1)?.encodedBodySize||0})));
 assert.ok(metrics.every(m=>m.sourceWidth<=1024 && m.bytes<500000));console.log('Related image measurements:',JSON.stringify(metrics));
 cls=await page.evaluate(()=>window.fixtureCLS);assert.ok(cls<.1,'Unexpected CLS '+cls);
 fixture('related-fallback');await page.reload();assert.equal(await cards.nth(2).locator('img').count(),1);assert.equal(await cards.nth(2).locator('[data-related-controls]').count(),0);
 fixture('related-expire');await page.reload();assert.equal(await cards.first().locator('.aloha-related-badge').count(),0);assert.equal(await cards.nth(1).locator('.aloha-related-badge').innerText(),'LUXE');
 fixture('review-link','unknown-count');await page.reload();assert.equal(await page.getByRole('link',{name:'View all reviews for this home on Airbnb (opens in a new tab)'}).innerText(),'All reviews on Airbnb');
 fixture('review-link','no-url');await page.reload();assert.equal(await page.getByRole('link',{name:'View all reviews for this home on Airbnb (opens in a new tab)'}).count(),0);
 assert.equal(await page.locator('.aloha-rental-review figcaption').first().innerText(),'Anna · Family trip · October 2026');
 assert.equal(await page.locator('.aloha-rental-review figcaption').last().innerText(),'October 2026');
 fixture('related-minimum');await page.reload();
 assert.match(await cards.first().locator('.aloha-related-price').innerText(),/from.*345.*night/);
 assert.equal(await section.locator('.aloha-related-price').filter({hasText:'Check dates for pricing'}).count(),2);
 assert.equal(external.filter(h=>h.includes('guesty')).length,0);
 console.log('PASS: related Area selection/exclusions, local visible ordered photos, two independent carousels, arrows/dots/swipe/keyboard/cache, 0/1/multiple images, NEW expiry/LUXE, accessible highlights, image sizes, CLS='+cls.toFixed(4)+', Airbnb confirmed/unknown/missing link states.');
 }finally{fixture('remove');await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
