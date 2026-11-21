/** Real public catalog / real Maps screenshots. No seeding or Rental writes. */
const assert=require('node:assert/strict');const {mkdirSync,writeFileSync}=require('node:fs');const {join}=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const evidence=process.env.CATALOG_UX_EVIDENCE||'.local/evidence/m2-5a2',base='http://127.0.0.1:8088/vacation-rentals/';
(async()=>{const browser=await chromium.launch();try{
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});let guesty=0,sdk=0;const errors=[];
 await context.route('**/*',r=>{const u=new URL(r.request().url());if(u.hostname.includes('guesty')){guesty++;return r.abort();}if(u.hostname==='maps.googleapis.com'&&u.pathname==='/maps/api/js')sdk++;return r.continue();});
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await page.goto(base);await page.evaluate(()=>document.fonts.ready);
 const root=page.locator('[data-rental-catalog]'),map=root.locator('#catalog-map'),sheet=root.locator('#catalog-filter-sheet');mkdirSync(evidence,{recursive:true});
 await root.locator('.aloha-catalog-marker').first().waitFor({timeout:30000});await page.waitForTimeout(1500);
 const take=async(name,fullPage=true)=>{await page.mouse.move(1,1);await page.evaluate(()=>document.activeElement?.blur());await page.screenshot({path:join(evidence,name+'.png'),fullPage});await page.waitForTimeout(200);};
 await take('desktop-map');await root.locator('[data-catalog-toggle]').click();await take('desktop-three-columns');await root.locator('[data-catalog-toggle]').click();
 await root.locator('.aloha-catalog-help').scrollIntoViewIfNeeded();await page.waitForTimeout(350);await take('cta-and-map',false);await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(400);
 const windows=[];
 for(let index=0;index<await root.locator('.aloha-catalog-marker').count();index++){
  console.log('Live marker selection',index);
  await root.locator('.aloha-catalog-marker').nth(index).click();await root.locator('[data-catalog-map-preview]').waitFor();await page.waitForTimeout(900);
  const bubble=await root.locator('.gm-style-iw-c').boundingBox(),frame=await root.locator('.aloha-catalog-map-sticky').boundingBox();
  assert.ok(bubble.x>=frame.x-2&&bubble.x+bubble.width<=frame.x+frame.width+2,'InfoWindow horizontal edge');
  assert.ok(bubble.y>=frame.y-2&&bubble.y+bubble.height<=frame.y+frame.height+2,'InfoWindow vertical edge');
  windows.push({bubble,frame});await take('infowindow-'+index,false);await root.getByRole('button',{name:'Close home preview',exact:true}).click();
 }
 for(const width of[320,390,768]){
  await page.setViewportSize({width,height:844});await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(250);await take('catalog-'+width);
  if(width<768){
   await root.locator('.aloha-catalog-mobile-filters').click();await sheet.getByRole('button',{name:'More adults',exact:true}).click();await sheet.getByRole('button',{name:'More children',exact:true}).click();await take('filter-sheet-'+width,false);
   await sheet.getByRole('button',{name:'Show homes',exact:true}).click();await page.waitForTimeout(120);await take('compact-selected-'+width,false);
   await root.locator('[data-catalog-toggle]').click();await page.waitForTimeout(1500);await take('fullscreen-map-'+width,false);
   await root.locator('.aloha-catalog-marker').first().click();await page.waitForTimeout(900);await take('fullscreen-infowindow-'+width,false);
   await root.getByRole('button',{name:'Close home preview',exact:true}).click();await root.getByRole('button',{name:'Close map',exact:true}).click();await page.waitForTimeout(120);
   if(await root.locator('[data-catalog-clear]').count())await root.locator('[data-catalog-clear]').click();
  }
 }
 await page.setViewportSize({width:390,height:844});await root.locator('.aloha-catalog-mobile-filters').click();await sheet.locator('[name=adults]').fill('100');await sheet.getByRole('button',{name:'Show homes',exact:true}).click();await page.waitForTimeout(100);assert.equal(await root.locator('[data-catalog-item]:not([hidden])').count(),0);await take('empty-with-cta');
 assert.equal(guesty,0);assert.equal(sdk,1);assert.deepEqual(errors,[]);writeFileSync(join(evidence,'live-map-results.json'),JSON.stringify({liveMap:true,guestyRequests:guesty,sdkLoads:sdk,errors,windows},null,2));
 console.log('PASS real Maps: anchored InfoWindow at both edges, mobile fullscreen, one SDK load, local photos/facts, Guesty=0; all requested screenshots saved');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
