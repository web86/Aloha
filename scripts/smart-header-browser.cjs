const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const {mkdirSync,writeFileSync}=require('node:fs');
const {join}=require('node:path');
const pw=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const evidence=process.env.CATALOG_UX_EVIDENCE||'.local/evidence/m2-5a2';
const query=spawnSync('./bin/local',['wp','eval',`$r=['home'=>home_url('/'),'archive'=>get_post_type_archive_link('rental')];foreach(['area','community','rental'] as $type){$q=['post_type'=>$type,'post_status'=>'publish','has_password'=>false,'numberposts'=>1];if($type==='area')$q['name']='mauna-lani';$p=get_posts($q);$r[$type]=$p?get_permalink($p[0]):null;}echo wp_json_encode($r);`],{encoding:'utf8',timeout:60000});
assert.equal(query.status,0,query.stdout+query.stderr);const routes=JSON.parse(query.stdout);mkdirSync(evidence,{recursive:true});
(async()=>{const results=[];
for(const engine of(process.env.CATALOG_UI_ENGINES||'chromium,firefox').split(',')){
 const browser=await pw[engine].launch();
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  await context.route('**/*',r=>new URL(r.request().url()).origin==='http://127.0.0.1:8088'?r.continue():r.abort());
  const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  for(const [kind,url] of Object.entries(routes)){
   assert.ok(url,'Missing public '+kind);
   for(const width of[1440,390]){
    await page.setViewportSize({width,height:900});await page.goto(url);await page.evaluate(()=>document.fonts.ready);
    await page.evaluate(()=>{if(document.documentElement.scrollHeight<2200){const s=document.createElement('div');s.dataset.headerTestSpacer='';s.style.height='2400px';document.querySelector('main').append(s);}});
    await page.waitForTimeout(400);
    const header=page.locator('.aloha-site-header');
    const height=await header.evaluate(e=>e.offsetHeight);const wrapper=await header.evaluate(e=>e.parentElement.getBoundingClientRect().height);
    assert.ok((await header.boundingBox()).y>=-1);
    if(engine==='chromium'&&kind==='archive'&&width===1440)await page.screenshot({path:join(evidence,'header-visible.png')});
    await page.evaluate(()=>scrollTo(0,550));await page.waitForTimeout(100);await page.evaluate(()=>scrollBy(0,140));await page.waitForTimeout(300);
    assert.ok((await header.boundingBox()).y+height<=1,kind+' '+width+' did not hide');
    assert.equal(await header.evaluate(e=>e.parentElement.getBoundingClientRect().height),wrapper,'Header displaced layout');
    if(engine==='chromium'&&kind==='archive'&&width===1440)await page.screenshot({path:join(evidence,'header-hidden.png')});
    for(let i=0;i<3;i++){await page.evaluate(()=>scrollBy(0,-3));await page.waitForTimeout(20);await page.evaluate(()=>scrollBy(0,3));await page.waitForTimeout(20);}
    assert.ok((await header.boundingBox()).y+height<=1,'Jitter revealed header');
    await page.evaluate(()=>scrollBy(0,-70));await page.waitForTimeout(300);assert.ok((await header.boundingBox()).y>=-1,'Up scroll did not reveal');
    await page.keyboard.press('Tab');await header.locator('a[href]').first().focus();
    await page.evaluate(()=>scrollBy(0,200));await page.waitForTimeout(100);assert.equal(await header.evaluate(e=>getComputedStyle(e).transform),'none','Keyboard focus moved header');
    await page.evaluate(()=>document.activeElement.blur());await page.mouse.click(1,400);
    await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(200);assert.ok((await header.boundingBox()).y>=-1,'Top header not visible');
    if(width===390){
     const open=page.locator('.wp-block-navigation__responsive-container-open');await open.click();await page.waitForSelector('.is-menu-open');
     await page.evaluate(()=>scrollTo(0,500));await page.waitForTimeout(100);
     assert.equal(await header.evaluate(e=>getComputedStyle(e).transform),'none','Menu acquired transformed containing block');
     const box=await page.locator('.is-menu-open').boundingBox();assert.ok(box.width>=width-1&&box.height>=850,'Mobile overlay not viewport-sized');
     await page.keyboard.press('Escape');await page.waitForSelector('.is-menu-open',{state:'detached'});await page.waitForTimeout(80);
    }else{
     const trigger=page.locator('.aloha-mega-item > a').first();
     if(await trigger.count()){
      await trigger.hover();await page.waitForTimeout(160);assert.ok(await page.locator('.is-aloha-open').count());
      await page.evaluate(()=>scrollTo(0,500));await page.waitForTimeout(100);assert.equal(await header.evaluate(e=>getComputedStyle(e).transform),'none','Mega-menu moved with hidden header');
      await trigger.focus();await page.keyboard.press('Escape');await page.evaluate(()=>document.activeElement.blur());await page.mouse.move(1,500);
     }
    }
    results.push({engine,page:kind,width,status:'PASS'});
   }
  }
  await page.setViewportSize({width:1440,height:900});await page.goto(routes.archive);await page.waitForTimeout(400);
  await page.evaluate(()=>{const target=document.createElement('h2');target.id='header-anchor-fixture';target.tabIndex=-1;target.textContent='Anchor fixture';target.style.marginTop='1200px';document.querySelector('main').append(target);location.hash=target.id;});await page.waitForTimeout(400);
  const anchor=await page.locator('#header-anchor-fixture').boundingBox(),head=await page.locator('.aloha-site-header').boundingBox();assert.ok(anchor.y>=head.y+head.height-1,'Anchor hidden by header');
  assert.deepEqual(errors,[]);await context.close();
  for(const width of[1440,390]){
   const admin=await browser.newContext({viewport:{width,height:900},reducedMotion:'reduce'});
   await admin.addInitScript(()=>{const o=new MutationObserver(()=>{if(!document.body)return;const bar=document.createElement('div');bar.id='wpadminbar';Object.assign(bar.style,{position:innerWidth<768?'absolute':'fixed',height:(innerWidth<768?46:32)+'px',inset:'0 0 auto',zIndex:99999});document.body.prepend(bar);o.disconnect();});o.observe(document,{childList:true,subtree:true});});
   await admin.route('**/*',r=>new URL(r.request().url()).origin==='http://127.0.0.1:8088'?r.continue():r.abort());
   const p=await admin.newPage();await p.goto(routes.archive);await p.waitForTimeout(400);await p.evaluate(()=>scrollTo(0,600));await p.waitForTimeout(300);
   const bottom=await p.locator('#wpadminbar').evaluate(e=>Math.max(0,e.getBoundingClientRect().bottom));const h=await p.locator('.aloha-site-header').boundingBox();
   assert.ok(h.y+h.height<=bottom+1,'Hidden header/admin offset');
   assert.ok(Math.abs((await p.locator('[data-catalog-sticky]').boundingBox()).y-bottom)<2,'Filter/admin gap');
   await p.evaluate(()=>scrollBy(0,-70));await p.waitForTimeout(300);const shown=await p.locator('.aloha-site-header').boundingBox();assert.ok(Math.abs(shown.y-bottom)<2,'Visible header/admin gap');
   await admin.close();
  }
  const animated=await browser.newContext({viewport:{width:1440,height:900},reducedMotion:'no-preference'});await animated.route('**/*',r=>new URL(r.request().url()).origin==='http://127.0.0.1:8088'?r.continue():r.abort());const p=await animated.newPage();await p.goto(routes.archive);await p.evaluate(()=>document.fonts.ready);await p.waitForTimeout(450);await p.evaluate(()=>scrollTo(0,650));await p.waitForTimeout(350);assert.ok((await p.locator('[data-catalog-sticky]').boundingBox()).y<2,'Animation did not settle sticky offset');await p.evaluate(()=>scrollBy(0,-70));await p.waitForTimeout(350);assert.ok((await p.locator('[data-catalog-sticky]').boundingBox()).y>=90,'Revealed offset not published');await animated.close();
  console.log('PASS smart header',engine,'Home/Area/Community/Single/Archive desktop+mobile, jitter/top, menus/focus, anchor, admin offsets, reduced and animated motion');
 }finally{await browser.close();writeFileSync(join(evidence,'header-results.json'),JSON.stringify(results,null,2));}
}
})().catch(e=>{console.error(e);process.exitCode=1;});
