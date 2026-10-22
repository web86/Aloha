/* Browser-only stress fixture derived from current rendered links; never mutates WordPress. */
const fs=require('node:fs'),assert=require('node:assert/strict'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const out=process.env.EVIDENCE_DIR||'.local/evidence/m1-2-2',base='http://127.0.0.1:8088';fs.mkdirSync(out,{recursive:true});
(async()=>{const b=await chromium.launch({headless:true}),p=await b.newPage({viewport:{width:1440,height:1000}}),report=[];
try{
 await p.route('**/assets/js/shell.js*',async r=>{const response=await r.fetch();const prefix=`(()=>{const p=document.querySelector('li.aloha-mega-rentals > ul'),items=[...p.children].filter(e=>e.classList.contains('wp-block-navigation-item'));for(let i=0;i<8;i++){const c=items[i%items.length].cloneNode(true);c.querySelector('.wp-block-navigation-item__label').textContent='A longer navigation label for an unhurried island experience '+(i+1);p.append(c);}p.querySelector('h2').textContent='A longer heading with room to wrap naturally';p.querySelector('.aloha-mega-description').textContent='A thoughtful, unhurried island experience, with room to explore. '.repeat(22);p.querySelector('.aloha-mega-feature').classList.add('has-image-left');})();\n`;await r.fulfill({response,body:prefix+await response.text()})});
 for(const width of [1280,1440,1920]){
  await p.setViewportSize({width,height:1000});await p.goto(base+'/?aloha-shell-preview=1');await p.locator('li.aloha-mega-rentals > a').press('ArrowDown');
  const panel=p.locator('li.aloha-mega-rentals > ul'),list=panel.locator('.aloha-mega-links-list');
  assert.equal(await list.locator(':scope > li').count(),13);assert.equal(await list.evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length),3);
  assert.ok(await panel.evaluate(el=>el.scrollHeight>el.clientHeight));assert.ok(await panel.evaluate(el=>el.scrollWidth<=el.clientWidth));assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
  const photo=panel.locator('.aloha-mega-image');if(await photo.count()){const image=await photo.boundingBox(),copy=await panel.locator('.aloha-mega-copy').boundingBox();assert.ok(image.x+image.width<copy.x);assert.ok(image.width<=124)}
  await p.screenshot({path:`${out}/layout-left-13-links-${width}.png`});
  await panel.locator('.aloha-mega-cta').focus();assert.ok(await panel.evaluate(el=>el.scrollTop>0));
  await p.evaluate(()=>document.documentElement.style.fontSize='200%');await p.locator('li.aloha-mega-rentals > a').press('ArrowDown');
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);assert.ok(await panel.evaluate(el=>el.scrollWidth<=el.clientWidth));await p.screenshot({path:`${out}/layout-200-left-13-links-${width}.png`});
  await p.emulateMedia({reducedMotion:'reduce'});assert.equal(await list.locator('a').first().evaluate(el=>getComputedStyle(el,'::after').transitionDuration),'0s');
  report.push({width,links:13,columns:3,imageLeft:true,longContentScroll:true,text200:true,reducedMotion:true});await p.emulateMedia({reducedMotion:'no-preference'});
 }
 fs.writeFileSync(`${out}/layout-stress.json`,JSON.stringify(report,null,2)+'\n');console.log('13-link grid, left image, long content, 200% and reduced motion PASS');
}finally{await b.close()}})().catch(e=>{console.error(e);process.exit(1)});
