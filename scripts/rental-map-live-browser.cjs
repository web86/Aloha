const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const {mkdirSync,writeFileSync}=require('node:fs');
const {join}=require('node:path');
const {randomBytes}=require('node:crypto');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base='http://127.0.0.1:8088',key='m24-fixture-'+randomBytes(8).toString('hex');
function fixture(action){const r=spawnSync('./bin/local',['wp','eval-file','/var/www/m24-tests/rental-template-browser-fixture.php',action,key],{encoding:'utf8',timeout:60000});assert.equal(r.status,0,'Controlled Maps fixture failed: '+action);return r.stdout.trim();}
(async()=>{const browser=await chromium.launch({args:['--enable-unsafe-swiftshader']});try{
 const data=JSON.parse(fixture('create'));fixture('map-close');
 const context=await browser.newContext({viewport:{width:390,height:1000}});
 const errors=new Set();await context.route('**/*',r=>{const u=new URL(r.request().url());return u.origin===base||/(^|\.)(googleapis\.com|gstatic\.com|google\.com)$/.test(u.hostname)?r.continue():r.abort();});
 const page=await context.newPage();page.on('console',msg=>{const code=msg.text().match(/Google Maps JavaScript API (?:error|warning): ([A-Za-z]+)/)?.[1];if(code)errors.add(code);});
 await page.goto(data.public.url);
 const section=page.locator('[data-rental-map]');const result={configured:await section.count()>0,loaded:false,errors:[]};
 if(process.env.MAP_REQUIRED==='1')assert.ok(result.configured,'Configured Maps fixture is unavailable');
 const shots=process.env.MAP_SCREENSHOTS;if(shots)mkdirSync(shots,{recursive:true});
 if(result.configured){
  await section.scrollIntoViewIfNeeded();
  try{await page.waitForFunction(()=>document.querySelectorAll('.aloha-rental-map-label').length===3||document.querySelector('[data-map-status]')?.textContent.includes('could not be loaded'),{},{timeout:30000});}catch{errors.add('LoadTimeout');}
  result.loaded=await section.locator('.aloha-rental-map-label').count()===3;
  if(result.loaded){
   const labels=section.locator('.aloha-rental-map-label');await labels.first().waitFor();
   assert.equal(await section.locator('.aloha-rental-map-marker.is-home').count(),1);
   assert.equal(await section.locator('.gm-style-iw,.gm-style-iw-c').count(),0);
   await section.locator('[data-map-surface][aria-busy="false"]').waitFor({timeout:30000});
   await section.locator('.gm-style').waitFor();
   assert.equal(await section.locator('.gm-style').count()>0,true,'Google Maps surface was not rendered');
   for(const width of [390,1440,390]){
    await page.setViewportSize({width,height:1000});await section.scrollIntoViewIfNeeded();
    await page.waitForFunction(()=>{const surface=document.querySelector('[data-map-surface]');return surface.dataset.mapWidth===String(surface.clientWidth)&&surface.dataset.mapLayout==='ready'&&surface.getAttribute('aria-busy')==='false';});
    const surfaceBox=await section.locator('[data-map-surface]').boundingBox();
    for(const label of await labels.all()){const box=await label.boundingBox();assert.ok(box.x>=surfaceBox.x-2 && box.x+box.width<=surfaceBox.x+surfaceBox.width+2,'A marker label is clipped at '+width);}
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
    if(shots)await section.screenshot({path:join(shots,'maps-live-'+width+'.png')});
   }
   const button=section.locator('[data-map-point]').first();await button.click();assert.equal(await button.getAttribute('aria-pressed'),'true');
   const marker=section.locator('.aloha-rental-map-marker:not(.is-home)').last().locator('..');
   await marker.dispatchEvent('gmp-click');assert.equal(await section.locator('[data-map-point]').nth(1).getAttribute('aria-pressed'),'true');
   result.labels=await labels.count();result.infoWindows=0;
  }else if(shots)await section.screenshot({path:join(shots,'maps-live-unavailable.png')});
 }
 result.errors=[...errors];if(shots)writeFileSync(join(shots,'maps-live-result.json'),JSON.stringify(result,null,2));
 console.log('Live Maps result (no keys):',JSON.stringify(result));
 }finally{fixture('remove');await browser.close();}})().catch(e=>{console.error(e.name+': '+String(e.message).replace(/AIza[\w-]+/g,'[redacted]').replace(/([?&]key=)[^&\s"']+/g,'$1[redacted]').slice(0,800));process.exitCode=1;});
