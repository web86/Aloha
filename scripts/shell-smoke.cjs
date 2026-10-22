/* Actual WordPress shell acceptance; credentials/admin checks are separate. */
const fs = require('node:fs'), assert = require('node:assert/strict');
const {chromium, firefox, webkit} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browserType = {chromium, firefox, webkit}[process.env.SHELL_BROWSER || 'chromium'];
const out = '.local/evidence/m1-2'; fs.mkdirSync(out, {recursive: true});
const base = 'http://127.0.0.1:8088';
const metrics = page => page.evaluate(() => {
 const rect = s => document.querySelector(s).getBoundingClientRect().toJSON();
 return {width: innerWidth, scrollWidth: document.documentElement.scrollWidth, header: rect('.aloha-site-header'), logo: rect('.aloha-site-header img'), footer: rect('.aloha-footer-inner'), bar: rect('.aloha-bottom-bar'), bodyPadding: getComputedStyle(document.body).paddingBottom, headers: document.querySelectorAll('header').length, footers: document.querySelectorAll('footer').length, menus: document.querySelectorAll('nav.aloha-primary-nav').length};
});
(async () => {
 const browser = await browserType.launch({headless: true});
 const report = {browser: browserType.name(), widths: [], errors: [], consoleErrors: [], external: [], failed: [], checks: []};
 try {
  const p = await browser.newPage({viewport: {width: 1440, height: 1000}});
  p.on('pageerror', e => report.errors.push(e.message));
  p.on('console', m => {if (m.type() === 'error') report.consoleErrors.push(m.text())});
  p.on('request', r => {if (r.url().startsWith('http') && !r.url().startsWith(base + '/')) report.external.push(r.url())});
  p.on('requestfailed', r => report.failed.push({url: r.url(), error: r.failure()?.errorText}));
  for (const width of [320,390,768,1024,1440]) {
   await p.setViewportSize({width,height:1000});
   const response=await p.goto(base+'/?aloha-shell-preview=1');
   assert.equal(response.status(),200); assert.match(response.headers()['x-robots-tag'], /noindex/);
   await p.evaluate(()=>document.fonts.ready);
   const m = await metrics(p); report.widths.push(m);
   assert.equal(m.scrollWidth,width); assert.equal(m.headers,1); assert.equal(m.footers,1); assert.equal(m.menus,1);
   assert.equal(m.header.height,width>=1280?92:68); assert.equal(m.logo.height,width>=1280?56:40);
   assert.equal(m.logo.x,width===1440?120:width>=768?40:20);
   await p.screenshot({path:`${out}/shell-${width}-top.png`});
   await p.evaluate(()=>scrollTo(0,1000));
   await p.waitForFunction(()=>document.querySelector('.aloha-site-header').classList.contains('is-scrolled'));
   assert.ok(Math.abs((await metrics(p)).header.y)<1,'sticky header');
   const blur=await p.locator('.aloha-site-header').evaluate(el=>getComputedStyle(el,'::before').backdropFilter);
   assert.match(blur,/blur/);
   await p.screenshot({path:`${out}/shell-${width}-scrolled.png`});
   await p.evaluate(()=>scrollTo(0,document.body.scrollHeight));
   await p.screenshot({path:`${out}/shell-${width}-footer.png`});
   if (width<768) {
    const group=p.locator('.aloha-footer-group').nth(2), summary=group.locator('summary');
    assert.equal(await group.getAttribute('open'),null);
    await summary.focus(); await p.keyboard.press('Enter');
    assert.notEqual(await group.getAttribute('open'),null);
    assert.ok(await group.getByRole('link',{name:'Results & Reports',exact:true}).isVisible());
    await p.screenshot({path:`${out}/shell-${width}-footer-open.png`});
    const last=p.locator('.aloha-legal a').last(); await last.focus();
    const b=await last.boundingBox(),bar=await p.locator('.aloha-bottom-bar').boundingBox();
    assert.ok(b.y+b.height<=bar.y,'footer link above bottom bar');
    await p.locator('#shell-test-input').focus();
    assert.equal(await p.locator('.aloha-bottom-bar').evaluate(el=>getComputedStyle(el).visibility),'hidden');
    await p.locator('#shell-test-input').press('Tab');
   }
   if(width<1280) {
    await p.evaluate(()=>scrollTo(0,0));
    const open=p.locator('.wp-block-navigation__responsive-container-open');
    const overlay=p.locator('.aloha-primary-nav .wp-block-navigation__responsive-container');
    await open.click(); await p.waitForFunction(()=>document.querySelector('.is-menu-open'));
    await overlay.evaluate(async el => { await Promise.all(el.getAnimations().map(animation => animation.finished)); });
    assert.equal(await overlay.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(243, 246, 246)');
    assert.equal(await overlay.evaluate(el=>getComputedStyle(el).color),'rgb(22, 35, 42)');
    assert.equal(await p.locator('html').evaluate(el=>getComputedStyle(el).overflow),'hidden');
    if(width<768) assert.equal(await p.locator('.aloha-bottom-bar').evaluate(el=>getComputedStyle(el).visibility),'hidden');
    assert.equal(await p.locator('[role=dialog][aria-modal=true]').count(),1);
    assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width);
    await p.screenshot({path:`${out}/shell-${width}-menu.png`});
    await overlay.locator('a').first().click();
    await p.waitForFunction(()=>document.querySelector('dialog').open);
    await p.keyboard.press('Escape');
    await p.waitForFunction(()=>!document.querySelector('dialog').open);
    assert.ok(await overlay.evaluate(el=>el.classList.contains('is-menu-open')),'demo returns to same menu');
    const y=await p.evaluate(()=>scrollY); await p.mouse.wheel(0,600); await p.waitForTimeout(100); assert.equal(await p.evaluate(()=>scrollY),y);
    const close=overlay.locator('.wp-block-navigation__responsive-container-close');
    const links=overlay.locator('a'); await links.last().focus(); await p.keyboard.press('Tab');
    assert.equal(await close.evaluate(el=>el===document.activeElement),true,'focus wraps');
    await p.keyboard.press('Escape'); await p.waitForFunction(()=>!document.querySelector('.is-menu-open'));
    assert.equal(await open.evaluate(el=>el===document.activeElement),true,'Escape focus return');
    await p.waitForTimeout(100); await open.click(); await p.waitForFunction(()=>document.querySelector('.is-menu-open'));
    await p.evaluate(()=>history.back()); await p.waitForFunction(()=>!document.querySelector('.is-menu-open'));
    assert.equal(await open.evaluate(el=>el===document.activeElement),true,'Back focus return');
    await p.waitForTimeout(100);
   }
  }
  // Desktop separate parent links, explicit disclosure, keyboard Escape under pointer.
  const top=p.locator('.aloha-primary-nav .wp-block-navigation__container > li').first();
  const toggle=top.locator('button').first(), parent=top.locator('a').first();
  assert.equal(await parent.getAttribute('href'),'/vacation-rentals/');
  await toggle.click(); assert.equal(await toggle.getAttribute('aria-expanded'),'true');
  await p.screenshot({path:`${out}/desktop-dropdown.png`});
  await p.keyboard.press('Tab'); assert.ok((await p.locator(':focus').getAttribute('href')).startsWith('/'));
  await p.keyboard.press('Escape'); assert.equal(await toggle.getAttribute('aria-expanded'),'false');
  assert.equal(await top.locator('ul').evaluate(el=>getComputedStyle(el).visibility),'hidden');
  await parent.click(); await p.waitForFunction(()=>document.querySelector('dialog').open);
  assert.match(await p.locator('#aloha-demo-message').textContent(),/vacation-rentals/);
  await p.keyboard.press('Escape'); await p.waitForFunction(()=>!document.querySelector('dialog').open);
  assert.equal(await parent.evaluate(el=>el===document.activeElement),true);
  const book=p.locator('.aloha-header-book button'); await book.click();
  assert.match(await p.locator('#aloha-demo-message').textContent(),/No booking or payment/);
  await p.screenshot({path:`${out}/book-demo.png`}); await p.getByRole('button',{name:'Close',exact:true}).click();
  assert.equal(await p.locator('a[href^="tel:"], a[href^="mailto:"]').count(),0);
  // Mobile button demo, text zoom, reduced motion.
  await p.setViewportSize({width:390,height:844});
  await p.locator('.aloha-bottom-bar [data-aloha-action=call]').click();
  assert.match(await p.locator('#aloha-demo-message').textContent(),/No call/);
  await p.keyboard.press('Escape');
  for(const width of [320,1440]) {
   await p.setViewportSize({width,height:1000}); await p.evaluate(()=>{document.documentElement.style.fontSize='200%';scrollTo(0,0)});
   assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width,`text zoom overflow ${width}`);
   await p.screenshot({path:`${out}/text-zoom-200-${width}.png`});
   if(width===320) {
    await p.locator('.wp-block-navigation__responsive-container-open').click();
    await p.waitForFunction(()=>document.querySelector('.is-menu-open'));
    const menu=p.locator('.aloha-primary-nav .wp-block-navigation__responsive-container');
    await menu.evaluate(async el=>{await Promise.all(el.getAnimations().map(a=>a.finished))});
    assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),width,'200% mobile menu overflow');
    assert.ok(await menu.evaluate(el=>el.scrollWidth<=el.clientWidth),'200% overlay content fits');
    await p.screenshot({path:`${out}/text-zoom-200-menu-${width}.png`});
    await p.keyboard.press('Escape');await p.waitForFunction(()=>!document.querySelector('.is-menu-open'));
    await p.waitForTimeout(100);
    await p.locator('.aloha-legal a').last().focus();
    const link=await p.locator('.aloha-legal a').last().boundingBox(),bar=await p.locator('.aloha-bottom-bar').boundingBox();
    assert.ok(link.y+link.height<=bar.y,'200% footer link clears bar');
   }
   await p.evaluate(()=>document.documentElement.style.fontSize='');
  }
  await p.emulateMedia({reducedMotion:'reduce'});
  report.reducedMotion=await p.locator('.aloha-site-header').evaluate(el=>getComputedStyle(el,'::before').transitionDuration);
  assert.equal(report.reducedMotion,'0s');
  assert.deepEqual(report.errors,[]);assert.deepEqual(report.consoleErrors,[]);assert.deepEqual(report.external,[]);assert.deepEqual(report.failed,[]);
  report.checks=['five widths','sticky blur','single header/footer/navigation','footer disclosure + Results','bottom bar + field focus','native overlay trap/Escape/Back','desktop disclosure + canonical parent link','safe demo dialog','200% text zoom','reduced motion'];
  fs.writeFileSync(`${out}/frontend-${browserType.name()}.json`,JSON.stringify(report,null,2)+'\n');
  console.log(`${browserType.name()}: shell acceptance PASS`);
 } finally {await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
