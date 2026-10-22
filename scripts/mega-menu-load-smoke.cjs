/* Initial-frame/no-JS acceptance, independent of the normal interaction regression. */
const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base='http://127.0.0.1:8088',out=process.env.EVIDENCE_DIR||'.local/evidence/m1-2-2';
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const b=await chromium.launch({headless:true}),report={widths:[],noJs:[],errors:[]};
 try{
  const c=await b.newContext(),p=await c.newPage();p.on('pageerror',e=>report.errors.push(e.message));
  await p.addInitScript(()=>{window.alohaFirstFrames=[];const frame=()=>{const n=document.querySelector('nav.aloha-primary-nav');if(n){const h=document.querySelector('.aloha-site-header').getBoundingClientRect();const panels=[...n.querySelectorAll('li.has-child > ul')];window.alohaFirstFrames.push({time:performance.now(),ready:n.classList.contains('aloha-nav-ready'),height:h.height,visible:panels.filter(e=>e.checkVisibility({checkVisibilityCSS:true,checkOpacity:true})).length,width:document.documentElement.scrollWidth});}if(window.alohaFirstFrames.length<400)requestAnimationFrame(frame)};requestAnimationFrame(frame)});
  await p.route('**/assets/js/shell.js*',async r=>{await new Promise(resolve=>setTimeout(resolve,1100));await r.continue()});
  const cdp=await c.newCDPSession(p);await cdp.send('Network.setCacheDisabled',{cacheDisabled:true});await cdp.send('Emulation.setCPUThrottlingRate',{rate:3});
  for(const width of [320,390,768,1024,1280,1440,1920]){
   await p.setViewportSize({width,height:1000});await p.mouse.move(1,700);
   const loaded=p.goto(base+'/?aloha-shell-preview=1',{waitUntil:'load'});
   await p.locator('nav.aloha-primary-nav').waitFor({state:'attached'});await p.waitForTimeout(250);
   assert.equal(await p.locator('nav.aloha-primary-nav').evaluate(el=>el.classList.contains('aloha-nav-ready')),false,'script still delayed');
   await p.screenshot({path:`${out}/initial-before-js-${width}.png`});await loaded;await p.locator('.aloha-nav-ready').waitFor();await p.waitForTimeout(100);
   let frames=await p.evaluate(()=>alohaFirstFrames);assert.ok(frames.some(f=>!f.ready));assert.ok(frames.every(f=>f.visible===0));assert.ok(frames.every(f=>f.height===(width>=1280?92:68)));assert.ok(frames.every(f=>f.width===width));
   if(width>=1280){await p.locator('li.aloha-mega-rentals > a').hover();await p.waitForTimeout(350);assert.equal(await p.locator('li.aloha-mega-rentals > a').getAttribute('aria-expanded'),'true');await p.mouse.move(1,700);}
   await p.reload({waitUntil:'load'});frames=await p.evaluate(()=>alohaFirstFrames);assert.ok(frames.some(f=>!f.ready));assert.ok(frames.every(f=>f.visible===0));
   report.widths.push({width,initialFrames:frames.length,closedBeforeJs:true,noHeaderShift:true,reloadClosed:true});
  }
  // Delay the HTML response as well: initial rendered frames still have a closed shell.
  await p.route(base+'/?aloha-shell-preview=1',async r=>{const response=await r.fetch();await new Promise(resolve=>setTimeout(resolve,700));await r.fulfill({response})});
  await p.goto(base+'/?aloha-shell-preview=1');assert.ok((await p.evaluate(()=>alohaFirstFrames)).every(f=>f.visible===0));report.slowHtml=true;
  for(const width of [320,1440]){
   const noJs=await b.newContext({javaScriptEnabled:false,viewport:{width,height:1000}}),q=await noJs.newPage();await q.goto(base+'/?aloha-shell-preview=1');
   const links=q.locator('nav.aloha-primary-nav li.has-child > ul > li:not(.aloha-mega-editorial) > a');assert.ok(await links.count()>=18);for(const link of await links.all())assert.ok(await link.isVisible());
   assert.equal(await q.evaluate(()=>document.documentElement.scrollWidth),width);await q.screenshot({path:`${out}/no-js-${width}.png`});
   await links.first().click();await q.waitForURL(base+'/vacation-rentals/');report.noJs.push({width,allChildrenReachable:true,normalHref:true});await noJs.close();
  }
  assert.deepEqual(report.errors,[]);fs.writeFileSync(`${out}/initial-frames.json`,JSON.stringify(report,null,2)+'\n');console.log('Initial frames, delayed JS/HTML, reload and no-JS fallback PASS');
 }finally{await b.close()}
})().catch(e=>{console.error(e);process.exit(1)});
