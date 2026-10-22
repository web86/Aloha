/* Real local WordPress acceptance. Set PLAYWRIGHT_MODULE to an installed Playwright. */
const fs = require('node:fs'), assert = require('node:assert/strict');
const {chromium, firefox, webkit} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const type = {chromium, firefox, webkit}[process.env.SHELL_BROWSER || 'chromium'];
const base = 'http://127.0.0.1:8088', out = '.local/evidence/m1-2-1';
fs.mkdirSync(out, {recursive:true});
(async () => {
 const browser = await type.launch({headless:true});
 const report = {browser:type.name(), widths:[], checks:[], errors:[], consoleErrors:[], external:[], failed:[]};
 try {
  const context = await browser.newContext({viewport:{width:1440,height:1000},hasTouch:true});
  const p = await context.newPage();
  p.on('console', m => {if(m.type()==='error')report.consoleErrors.push(m.text())});
  p.on('pageerror', e => report.errors.push(e.message));
  p.on('request', r => {if(r.url().startsWith('http')&&!r.url().startsWith(base+'/')) report.external.push(r.url())});
  p.on('requestfailed', r => report.failed.push(r.url()));
  const parent = key => p.locator(`li.aloha-mega-${key} > a`).first();
  const panel = key => p.locator(`li.aloha-mega-${key} > ul`).first();
  const expanded = async (key, value) => assert.equal(await parent(key).getAttribute('aria-expanded'),String(value),key);
  const settle = () => p.waitForTimeout(340);
  const reset = async width => {
   if(await p.locator('.is-menu-open').count()){await p.locator('.wp-block-navigation__responsive-container-close').click();await p.waitForFunction(()=>!document.querySelector('.is-menu-open'));await p.waitForTimeout(150);}
   await p.setViewportSize({width,height:1000});
   const response=await p.goto(base+'/?aloha-shell-preview=1');assert.equal(response.status(),200);assert.match(response.headers()['x-robots-tag'],/noindex/); await p.locator('.aloha-nav-ready').waitFor(); await p.evaluate(()=>document.fonts.ready); await p.waitForTimeout(220);
  };
  const tap = async loc => {await loc.scrollIntoViewIfNeeded();await p.waitForTimeout(190);const r=await loc.boundingBox();assert.ok(r);await p.touchscreen.tap(r.x+r.width/2,r.y+r.height/2)};
  const dismiss = async () => {await p.waitForFunction(()=>document.querySelector('dialog').open);await p.keyboard.press('Escape');await p.waitForFunction(()=>!document.querySelector('dialog').open);await p.waitForTimeout(70)};
  for (const width of [1280,1440,1920]) {
   await reset(width);
   assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
   assert.equal(await p.locator('header').count(),1);assert.equal(await p.locator('footer').count(),1);assert.equal(await p.locator('nav.aloha-primary-nav').count(),1);
   const before = await p.locator('main').boundingBox();
   await p.screenshot({path:`${out}/closed-${width}.png`});
   for (const key of ['rentals','island','homeowners','about']) {
    await parent(key).hover(); await settle(); await expanded(key,true);
    assert.equal(await p.locator('.aloha-mega-item.is-aloha-open').count(),1);
    const a=await panel(key).boundingBox(),h=await p.locator('.aloha-site-header').boundingBox();
    assert.ok(Math.abs(a.y-h.y-h.height)<1,'panel meets header');assert.ok(a.width>=1100);assert.ok(a.x>=0&&a.x+a.width<=width);
    assert.equal(await p.locator('.aloha-mega-item > button').first().evaluate(el=>getComputedStyle(el).clipPath),'inset(50%)');
    assert.deepEqual(await p.locator('main').boundingBox(),before,'no layout shift');
    await p.mouse.move(a.x+a.width/2,a.y+12,{steps:12});await settle();await expanded(key,true);
    if(width===1440)await p.screenshot({path:`${out}/open-${key}.png`});
    await p.mouse.move(width-3,700);await settle();await expanded(key,false);
   }
   // Fast sweep resolves to one final panel, with no stale previous panel.
   for(const key of ['rentals','island','homeowners','about'])await parent(key).hover();
   await settle();await expanded('about',true);assert.equal(await p.locator('.aloha-mega-item.is-aloha-open').count(),1);
   await parent('rentals').hover();await settle();
   await p.locator('.wp-block-navigation__container > li > a').filter({hasText:/^Invest$/}).hover();await settle();assert.equal(await p.locator('.is-aloha-open').count(),0);
   await parent('rentals').hover();await settle();await parent('rentals').click();await dismiss();
   await parent('rentals').press('ArrowDown');await expanded('rentals',true);
   assert.ok(await panel('rentals').evaluate(el=>el.contains(document.activeElement)));
   await p.keyboard.press('Tab');assert.ok(await panel('rentals').evaluate(el=>el.contains(document.activeElement)));
   await p.keyboard.press('Shift+Tab');assert.ok(await panel('rentals').evaluate(el=>el.contains(document.activeElement)));
   if(width===1440)await p.screenshot({path:`${out}/keyboard-focus.png`});
   await p.keyboard.press('Escape');await expanded('rentals',false);assert.ok(await parent('rentals').evaluate(el=>el===document.activeElement));
   await settle();await expanded('rentals',false); // Escape under pointer must stay closed.
   await parent('island').press('ArrowDown');await panel('island').locator('li:not(.aloha-mega-editorial) > a').first().click();await dismiss();
   await p.evaluate(()=>{document.activeElement.blur();scrollTo(0,950)});await settle();
   await parent('rentals').hover();await settle();
   const sticky=await p.locator('.aloha-site-header').boundingBox(), pr=await panel('rentals').boundingBox();
   assert.ok(Math.abs(sticky.y)<1);assert.ok(Math.abs(pr.y-sticky.height)<1);
   if(width===1440)await p.screenshot({path:`${out}/sticky-scroll.png`});
   report.widths.push({width,overflow:false,header:sticky.height,panelWidth:pr.width});
  }
  report.checks.push('desktop hover/bridge/delayed close/switch/Invest','parent and child mouse links','ArrowDown/Tab/ShiftTab/Escape/focus return','sticky and no layout shift');
  // Real touch events at desktop width; the hover state never arms a touch navigation.
  await reset(1440);await parent('rentals').hover();await settle();
  await tap(parent('rentals'));await expanded('rentals',true);assert.equal(await p.locator('dialog').getAttribute('open'),null);
  await p.waitForTimeout(500);await expanded('rentals',true);await p.screenshot({path:`${out}/touch-state.png`});
  await tap(parent('rentals'));await dismiss();
  await tap(parent('island'));await expanded('island',true);await tap(parent('homeowners'));await expanded('homeowners',true);await expanded('island',false);
  await tap(panel('homeowners').locator('li:not(.aloha-mega-editorial) > a').first());await dismiss();
  await p.touchscreen.tap(20,700);assert.equal(await p.locator('.is-aloha-open').count(),0);
  await tap(p.locator('.wp-block-navigation__container > li > a').filter({hasText:/^Invest$/}));await dismiss();
  await parent('about').hover();await settle();await tap(parent('about'));assert.equal(await p.locator('dialog').getAttribute('open'),null);await expanded('about',true);
  await p.mouse.move(3,700);await settle();await expanded('about',true);
  await parent('island').hover();await settle(); // Touch stays until a deliberate mouse pointerdown or other focus.
  await parent('island').click();await dismiss();
  await p.setViewportSize({width:1400,height:900});await settle();await expanded('island',false);
  report.checks.push('real touch first/second tap, switch, outside, child and Invest','hybrid hover-to-touch and touch-to-mouse','resize clears state');
  for(const width of [320,390,768,1024]) {
   await reset(width);
   const trigger=p.locator('.wp-block-navigation__responsive-container-open'), overlay=p.locator('.aloha-primary-nav .wp-block-navigation__responsive-container');
   await trigger.click();await p.waitForFunction(()=>document.querySelector('.is-menu-open'));await settle();
   assert.equal(await p.locator('[role=dialog][aria-modal=true]').count(),1);
   assert.equal(await p.locator('html').evaluate(el=>getComputedStyle(el).overflow),'hidden');
   assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
   await tap(parent('rentals'));await expanded('rentals',true);assert.ok(await panel('rentals').isVisible());
   assert.equal(await panel('rentals').locator('.aloha-mega-editorial').evaluate(el=>getComputedStyle(el).display),'none');
   await p.screenshot({path:`${out}/mobile-overlay-${width}.png`});
   await tap(parent('rentals'));await dismiss();assert.ok(await overlay.evaluate(el=>el.classList.contains('is-menu-open')));
   await tap(parent('island'));await expanded('island',true);await expanded('rentals',false);
   await tap(panel('island').locator('li:not(.aloha-mega-editorial) > a').first());await dismiss();
   await p.keyboard.press('Escape');await expanded('island',false);assert.ok(await parent('island').evaluate(el=>el===document.activeElement));
   const y=await p.evaluate(()=>scrollY);await p.mouse.wheel(0,400);await p.waitForTimeout(70);assert.equal(await p.evaluate(()=>scrollY),y);
   await overlay.locator('.aloha-mega-item > button').last().focus();await p.keyboard.press('Tab');assert.ok(await overlay.locator('.wp-block-navigation__responsive-container-close').evaluate(el=>el===document.activeElement));
   await parent('about').press('ArrowDown');await expanded('about',true);
   await panel('about').locator('li:not(.aloha-mega-editorial) > a').last().focus();await p.keyboard.press('Tab');
   assert.ok(await overlay.locator('.wp-block-navigation__responsive-container-close').evaluate(el=>el===document.activeElement));
   await p.keyboard.press('Shift+Tab');assert.ok(await panel('about').evaluate(el=>el.contains(document.activeElement)));
   await p.keyboard.press('Escape');await expanded('about',false);
   if(width<768)assert.equal(await p.locator('.aloha-bottom-bar').evaluate(el=>getComputedStyle(el).visibility),'hidden');
   await p.keyboard.press('Escape');await p.waitForFunction(()=>!document.querySelector('.is-menu-open'));assert.ok(await trigger.evaluate(el=>el===document.activeElement));
   await p.waitForTimeout(150);await trigger.click();await p.waitForFunction(()=>document.querySelector('.is-menu-open'));
   await p.evaluate(()=>history.back());await p.waitForFunction(()=>!document.querySelector('.is-menu-open'));assert.ok(await trigger.evaluate(el=>el===document.activeElement));
   if(width<768){const summary=p.locator('.aloha-footer-group summary').nth(2);await summary.focus();await p.keyboard.press('Enter');assert.ok(await p.locator('.aloha-footer-group').nth(2).getByRole('link',{name:'Results & Reports'}).isVisible());}
   if(width<768){
    await p.locator('#shell-test-input').focus();await p.waitForTimeout(50);assert.equal(await p.locator('.aloha-bottom-bar').evaluate(el=>getComputedStyle(el).visibility),'hidden');
    await p.locator('.aloha-legal a').last().focus();const lr=await p.locator('.aloha-legal a').last().boundingBox(),br=await p.locator('.aloha-bottom-bar').boundingBox();assert.ok(lr.y+lr.height<=br.y);
   }
   report.widths.push({width,overflow:false,overlay:true,twoTap:true});
  }
  report.checks.push('mobile overlay 320/390/768/1024, two-tap and child link','Core focus trap/Escape/Back/scroll lock/bottom bar','footer disclosure regression');
  assert.equal(await p.locator('a[href^="tel:"],a[href^="mailto:"]').count(),0);
  await reset(390);await p.locator('.aloha-bottom-bar [data-aloha-action=call]').click();assert.match(await p.locator('#aloha-demo-message').textContent(),/No call/);await dismiss();
  await reset(1440);await p.locator('.aloha-header-book button').click();assert.match(await p.locator('#aloha-demo-message').textContent(),/No booking or payment/);await p.screenshot({path:`${out}/book-demo.png`});await dismiss();
  report.checks.push('verified-contact protection, Call/Book demo, bottom field focus and footer clearance');
  // Ordinary navigation outside the local preview retains real href behavior (future URLs may 404).
  const actual=await context.newPage();const actualParent=()=>actual.locator('li.aloha-mega-rentals > a');
  await actual.goto(base+'/sample-page/');await actualParent().click();await actual.waitForURL(base+'/vacation-rentals/');
  await actual.goto(base+'/sample-page/');await actualParent().press('Enter');await actual.waitForURL(base+'/vacation-rentals/');
  await actual.goto(base+'/sample-page/');let r=await actualParent().boundingBox();await actual.touchscreen.tap(r.x+r.width/2,r.y+r.height/2);assert.match(actual.url(),/sample-page/);assert.equal(await actualParent().getAttribute('aria-expanded'),'true');
  r=await actualParent().boundingBox();await actual.touchscreen.tap(r.x+r.width/2,r.y+r.height/2);await actual.waitForURL(base+'/vacation-rentals/');
  await actual.goto(base+'/sample-page/');await actualParent().hover();await actual.waitForTimeout(350);await actual.locator('li.aloha-mega-rentals .aloha-mega-links-list > li > a').nth(1).click();await actual.waitForURL(base+'/big-island/mauna-lani/');
  await actual.goto(base+'/sample-page/');const popupPromise=context.waitForEvent('page');await actualParent().click({modifiers:[process.platform==='darwin'?'Meta':'Control']});const popup=await popupPromise;await popup.waitForURL(base+'/vacation-rentals/');assert.equal(popup.url(),base+'/vacation-rentals/');await popup.close();
  const middlePromise=context.waitForEvent('page');await actualParent().click({button:'middle'});const middle=await middlePromise;await middle.waitForURL(base+'/vacation-rentals/');assert.equal(middle.url(),base+'/vacation-rentals/');await middle.close();await actual.close();
  report.checks.push('actual mouse/Enter/two-tap/child href navigation outside preview','Cmd/Ctrl-click and middle-click open normal new tabs');
  for(const width of [320,1440]) {
   await reset(width);await p.evaluate(()=>document.documentElement.style.fontSize='200%');
   if(width<1280){await p.locator('.wp-block-navigation__responsive-container-open').click();await p.waitForFunction(()=>document.querySelector('.is-menu-open'))}
   await parent('rentals').press('ArrowDown');await settle();
   assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
   assert.ok(await panel('rentals').evaluate(el=>el.scrollWidth<=el.clientWidth+1));
   await p.screenshot({path:`${out}/text-200-${width}.png`});
  }
  await reset(1440);await p.emulateMedia({reducedMotion:'reduce'});await parent('rentals').press('ArrowDown');
  assert.equal(await panel('rentals').evaluate(el=>getComputedStyle(el).transitionDuration),'0s');
  await p.screenshot({path:`${out}/reduced-motion.png`});
  report.checks.push('200% text at 320/1440','reduced motion','no external requests or JavaScript errors');
  assert.deepEqual(report.errors,[]);assert.deepEqual(report.consoleErrors,[]);assert.deepEqual(report.external,[]);assert.deepEqual(report.failed,[]);
  fs.writeFileSync(`${out}/frontend-${type.name()}.json`,JSON.stringify(report,null,2)+'\n');
  console.log(type.name()+': M1.2.1 frontend PASS');
 } finally {await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
