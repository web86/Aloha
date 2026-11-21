const assert=require('node:assert/strict');const {mkdirSync,writeFileSync}=require('node:fs');const {join}=require('node:path');
const pw=require(process.env.PLAYWRIGHT_MODULE||'playwright');const mockMaps=require('./lib/catalog-map-mock.cjs');
const base='http://127.0.0.1:8088/vacation-rentals/',evidence=process.env.CATALOG_UX_EVIDENCE||'.local/evidence/m2-5a2';mkdirSync(evidence,{recursive:true});
(async()=>{const results=[];
for(const engine of(process.env.CATALOG_UI_ENGINES||'chromium,firefox').split(',')){
 const browser=await pw[engine].launch();try{
 const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});await context.addInitScript(mockMaps);
 let external=0;await context.route('**/*',r=>new URL(r.request().url()).origin==='http://127.0.0.1:8088'?r.continue():(external++,r.abort()));
 const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(base);await page.evaluate(()=>document.fonts.ready);
 const root=page.locator('[data-rental-catalog]'),form=root.locator('form'),toggle=root.locator('[data-catalog-toggle]'),sheet=root.locator('#catalog-filter-sheet'),mapDialog=root.locator('#catalog-map-dialog');
 const closed=async dialog=>{await dialog.waitFor({state:'hidden'});await page.waitForTimeout(80);};
 const gridColumns=()=>root.locator('.aloha-catalog-grid').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length);
 const initialNodes=await root.locator('[data-catalog-item]').evaluateAll(es=>{es.forEach((e,i)=>e.dataset.nodeIdentity=String(i));return es.length;});
 assert.equal(await root.locator('.aloha-catalog-results .aloha-catalog-help').count(),1);assert.equal(await page.locator('.aloha-catalog-help').count(),1);
 const geometries=[];
 for(const width of[320,390,768,1024,1280,1440,1920]){
  await page.setViewportSize({width,height:900});await page.evaluate(()=>{document.activeElement?.blur();scrollTo(0,0)});await page.waitForTimeout(100);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Overflow '+width);
  assert.equal(await gridColumns(),width<768?1:2);
  const button=form.getByRole('button',{name:'Search',exact:true});const measured=await button.boundingBox();
  if(width<768){
   assert.ok((await form.boundingBox()).height<=80);assert.equal(measured.height,48);assert.equal(await root.locator('#catalog-map').isVisible(),false);
   await root.locator('.aloha-catalog-mobile-filters').click();assert.equal(await sheet.isVisible(),true);assert.equal(await page.evaluate(()=>document.body.style.position),'fixed');
   assert.equal(await page.locator('[name=adults]').count(),1);assert.equal(await page.locator('[name=area_id]').count(),1);
   assert.equal(await root.locator('#catalog-guests').isVisible(),true);
   for(const step of await sheet.locator('[data-guest-step]').all()){const b=await step.boundingBox();assert.ok(Math.abs(b.width-44)<.01&&Math.abs(b.height-44)<.01);}
   const close=sheet.getByRole('button',{name:'Close filters',exact:true});await close.focus();await page.keyboard.press('Shift+Tab');assert.equal(await sheet.locator('.aloha-catalog-sheet-apply').evaluate(e=>e===document.activeElement),true);await page.keyboard.press('Tab');assert.equal(await close.evaluate(e=>e===document.activeElement),true);
   await page.keyboard.press('Escape');await closed(sheet);assert.equal(await root.locator('.aloha-catalog-mobile-filters').evaluate(e=>e===document.activeElement),true);
   const before=await page.evaluate(()=>scrollY);await toggle.click();assert.equal(await mapDialog.isVisible(),true);assert.ok((await mapDialog.boundingBox()).height>=899);assert.equal(await root.locator('#catalog-map').isVisible(),true);
   await root.locator('.aloha-catalog-marker').first().waitFor();await root.locator('.aloha-catalog-marker').first().click();assert.equal(await page.evaluate(()=>window.catalogMapState.anchored),true);
   await page.keyboard.press('Escape');assert.equal(await mapDialog.isVisible(),true,'InfoWindow Escape closed map');assert.equal(await page.evaluate(()=>window.catalogMapState.infoOpen),false);
   await mapDialog.getByRole('button',{name:'Close map',exact:true}).click();await closed(mapDialog);assert.equal(await page.evaluate(()=>scrollY),before);assert.equal(await toggle.evaluate(e=>e===document.activeElement),true);assert.equal(await page.evaluate(()=>document.body.style.position),'');
  }else{
   assert.equal(measured.height,56);await toggle.click();assert.equal(await gridColumns(),width>=1024?3:2);assert.equal(await root.locator('#catalog-map').isVisible(),false);
   assert.equal(await root.locator('[data-node-identity]').count(),initialNodes,'Cards recreated');
   const sizes=await root.locator('[data-catalog-item]:not([hidden])').evaluateAll(es=>es.filter(e=>e.querySelector('img')).map(e=>({width:Math.ceil(e.querySelector('.aloha-related-gallery').getBoundingClientRect().width),sizes:e.querySelector('img').sizes})));
   assert.ok(sizes.every(s=>s.sizes===s.width+'px'),'Responsive sizes '+width);
   await toggle.click();assert.equal(await gridColumns(),2);
   if(width>=1024){const map=await root.locator('#catalog-map').boundingBox(),help=await root.locator('.aloha-catalog-help').boundingBox();assert.ok(map.y+map.height>=help.y+help.height-1,'Map ends before CTA');}
  }
  geometries.push({width,columns:await gridColumns(),searchHeight:measured.height});
 }
 await page.setViewportSize({width:390,height:844});await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(100);
 await root.locator('.aloha-catalog-mobile-filters').click();await sheet.getByRole('button',{name:'More adults',exact:true}).click();await sheet.getByRole('button',{name:'More adults',exact:true}).click();
 const first=JSON.parse(await root.locator('[data-catalog-fields]').first().getAttribute('data-catalog-fields'));
 if(first.area)await sheet.locator('[name=area_id]').selectOption(String(first.area));const beds=first.bedrooms>=4?'4':String(first.bedrooms);if(['2','3','4'].includes(beds))await sheet.locator(`[name=bedrooms][value="${beds}"]`).check();
 await sheet.getByRole('button',{name:'Show homes',exact:true}).click();await closed(sheet);const filtered=page.url();assert.equal(new URL(filtered).searchParams.get('adults'),'2');assert.ok(await root.locator('.aloha-catalog-mobile-filters').innerText()!=='Filters');
 await page.goBack();assert.equal(page.url(),base);await page.goForward();assert.equal(page.url(),filtered);assert.equal(await sheet.isVisible(),false);
 await root.locator('.aloha-catalog-mobile-filters').click();assert.equal(await sheet.locator('[name=adults]').inputValue(),'2');await page.goBack();await closed(sheet);await page.goForward();assert.equal(await sheet.isVisible(),true);
 await sheet.getByRole('button',{name:'Clear all',exact:true}).click();await sheet.getByRole('button',{name:'Show homes',exact:true}).click();await closed(sheet);assert.equal(page.url(),base);assert.equal(await root.locator('.aloha-catalog-mobile-filters').innerText(),'Filters');
 await root.locator('.aloha-catalog-mobile-filters').click();await page.setViewportSize({width:844,height:390});await closed(sheet);assert.equal(await page.evaluate(()=>document.body.style.position),'');assert.equal(await root.locator('[data-catalog-filter-fields]').evaluate(e=>e.closest('form')!==null),true);
 await page.setViewportSize({width:390,height:844});await toggle.click();await page.setViewportSize({width:1024,height:768});await closed(mapDialog);assert.equal(await root.locator('#catalog-map').isVisible(),true);assert.equal(await page.evaluate(()=>document.body.style.position),'');
 await page.setViewportSize({width:1440,height:1000});await page.evaluate(()=>scrollTo(0,0));
 await root.locator('.aloha-catalog-marker').first().click();assert.equal(await page.evaluate(()=>window.catalogMapState.anchored),true);assert.equal(await root.locator('[data-catalog-map-preview]').isVisible(),true);
 await form.locator('[data-guest-toggle]').click();await form.locator('[name=adults]').fill('100');await form.locator('[data-guest-done]').click();await form.getByRole('button',{name:'Search',exact:true}).click();assert.equal(await root.locator('[data-catalog-item]:not([hidden])').count(),0);assert.equal(await root.locator('.aloha-catalog-help').isVisible(),true);assert.equal(await page.evaluate(()=>window.catalogMapState.infoOpen),false);
 await root.locator('.aloha-catalog-marker.is-neutral').first().click();assert.ok(!/booked|not available for/.test((await root.locator('[data-catalog-map-preview]').innerText()).toLowerCase()));await toggle.click();assert.equal(await page.evaluate(()=>window.catalogMapState.infoOpen),false);await toggle.click();
 assert.equal(await page.evaluate(()=>window.catalogMapState.instances),1);assert.deepEqual(errors,[]);assert.equal(external,0);
 await context.close();
 const sparse=await browser.newContext({viewport:{width:1440,height:900},reducedMotion:'reduce'});await sparse.addInitScript(mockMaps);
 await sparse.addInitScript(()=>{const observer=new MutationObserver(()=>{const node=document.querySelector('[data-catalog-map-config]');if(!node?.textContent)return;observer.disconnect();const data=JSON.parse(node.textContent);data.points[0].image=null;data.points[0].price='';data.points[0].facts={bedrooms:null,guests:null};data.points[0].title='<img src=x onerror=window.badMarker=true> '+ 'Long public title '.repeat(30);node.textContent=JSON.stringify(data);});observer.observe(document,{childList:true,subtree:true});});
 await sparse.route('**/*',r=>new URL(r.request().url()).origin==='http://127.0.0.1:8088'?r.continue():r.abort());const sp=await sparse.newPage();await sp.goto(base);await sp.locator('.aloha-catalog-marker').first().click();const preview=sp.locator('[data-catalog-map-preview]');assert.equal(await preview.locator('img').count(),0);assert.ok((await preview.innerText()).includes('Check dates for pricing'));assert.ok((await preview.innerText()).includes('<img src=x'));assert.equal(await sp.evaluate(()=>window.badMarker),undefined);assert.ok(!(await preview.innerText()).includes('0 guests'));const pb=await preview.boundingBox(),mb=await sp.locator('[data-catalog-map-surface]').boundingBox();assert.ok(pb.height<=mb.height-89&&pb.width<=mb.width);await sparse.close();
 for(const mode of['gpc','error']){
  const c=await browser.newContext({viewport:{width:390,height:844}});if(mode==='gpc')await c.addInitScript(()=>Object.defineProperty(navigator,'globalPrivacyControl',{value:true}));let sdk=0;
  await c.route('**/*',r=>{if(new URL(r.request().url()).origin==='http://127.0.0.1:8088')return r.continue();if(r.request().url().includes('maps.googleapis'))sdk++;return r.abort();});const p=await c.newPage();await p.goto(base);assert.equal(sdk,0,'Mobile loaded SDK before opening');await p.locator('[data-catalog-toggle]').click();await p.waitForFunction(()=>/privacy|unavailable/.test(document.querySelector('[data-catalog-map-message]').textContent));if(mode==='gpc')assert.equal(sdk,0);else assert.equal(sdk,1);await p.getByRole('button',{name:'Close map',exact:true}).click();await p.waitForTimeout(100);await p.locator('[data-catalog-toggle]').click();assert.equal(sdk,mode==='gpc'?0:1);await c.close();
 }
 const noJS=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}}),plain=await noJS.newPage();await plain.goto(base);assert.equal(await plain.locator('[name=adults]').isVisible(),true);assert.equal(await plain.locator('#catalog-map').isVisible(),true);await plain.locator('[name=adults]').fill('100');await Promise.all([plain.waitForNavigation(),plain.getByRole('button',{name:'Search',exact:true}).click()]);assert.equal(await plain.locator('[data-catalog-item]:not([hidden])').count(),0);assert.equal(await plain.locator('.aloha-catalog-help').count(),1);await noJS.close();
 results.push({engine,version:browser.version(),status:'PASS',geometries});console.log('PASS catalog UX',engine,'7 widths, 2/3/1 columns, sizes/identity, editable CTA scope, sheet/history/focus/lock, fullscreen/orientation, anchored preview, neutral/empty, GPC/error, SDK reuse, no-JS');
 }finally{await browser.close();writeFileSync(join(evidence,'catalog-ux-results.json'),JSON.stringify(results,null,2));}
}
})().catch(e=>{console.error(e);process.exitCode=1;});
