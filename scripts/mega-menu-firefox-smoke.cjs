/* Native Firefox BiDi smoke. Node 22+, FIREFOX_BINARY optional; no browser download. */
const fs=require('node:fs'),assert=require('node:assert/strict'),{firefox}=require('./lib/firefox-bidi.cjs');
const out=process.env.EVIDENCE_DIR||'.local/evidence/m1-2-2',base='http://127.0.0.1:8088',sleep=ms=>new Promise(r=>setTimeout(r,ms));fs.mkdirSync(out,{recursive:true});
(async()=>{const f=await firefox(),report={version:f.session.capabilities.browserVersion,desktop:[],mobile:[]};
 const evalAt=(selector,expr)=>f.eval(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});return ${expr}})()`);
 const rect=selector=>evalAt(selector,'el.getBoundingClientRect().toJSON()');
 const expanded=key=>evalAt(`li.aloha-mega-${key} > a`,"el.getAttribute('aria-expanded')");
 const key=async value=>f.send('input.performActions',{context:f.context,actions:[{type:'key',id:'keyboard',actions:[{type:'keyDown',value},{type:'keyUp',value}]}]});
 const click=async selector=>{const r=await rect(selector);await f.move(r.x+r.width/2,r.y+r.height/2);await f.send('input.performActions',{context:f.context,actions:[{type:'pointer',id:'mouse',parameters:{pointerType:'mouse'},actions:[{type:'pointerDown',button:0},{type:'pointerUp',button:0}]}]})};
 try{
 for(const width of [1280,1440,1920]){
  await f.viewport(width);await f.move(1,700);await f.goto(base+'/?aloha-shell-preview=1');await sleep(300);
  assert.equal(await f.eval('document.documentElement.scrollWidth'),width);
  for(const section of ['rentals','island','homeowners','about']){
   const a=await rect(`li.aloha-mega-${section} > a`);await f.move(a.x+a.width/2,a.y+a.height/2);await sleep(320);assert.equal(await expanded(section),'true');
   const p=await rect(`li.aloha-mega-${section} > ul`),sx=a.x+a.width/2,sy=a.y+a.height/2;
   for(let n=1;n<=14;n++)await f.move(sx+(p.x+p.width/2-sx)*n/14,sy+(p.y+p.height/2-sy)*n/14,25);
   await sleep(750);assert.equal(await expanded(section),'true','center pause '+section);
   const links=await f.eval(`[...document.querySelectorAll('li.aloha-mega-${section} .aloha-mega-links-list > li > a')].map(e=>e.getBoundingClientRect().toJSON())`);
   for(const l of [links[0],links.at(-1)]){await f.move(l.x+l.width/2,l.y+l.height/2,130);await sleep(650);assert.equal(await expanded(section),'true','child hover '+section)}
   if(width===1440)await f.shot(`${out}/firefox-open-${section}.png`);
   await f.move(1,700);await sleep(80);await f.move(p.x+20,p.y+20,20);await sleep(650);assert.equal(await expanded(section),'true','cancel close on return');
   await f.move(1,700);await sleep(350);assert.equal(await expanded(section),'false');
  }
  await f.eval("document.querySelector('li.aloha-mega-rentals > a').focus()");await key('\uE015');assert.equal(await expanded('rentals'),'true');assert.ok(await f.eval("document.querySelector('li.aloha-mega-rentals > ul').contains(document.activeElement)"));if(width===1440)await f.shot(`${out}/firefox-keyboard-focus.png`);await key('\uE004');assert.ok(await f.eval("document.querySelector('li.aloha-mega-rentals > ul').contains(document.activeElement)"));await key('\uE00C');assert.equal(await expanded('rentals'),'false');
  await f.eval('(()=>{document.activeElement.blur();scrollTo(0,900);return true})()');await sleep(150);await click('li.aloha-mega-rentals > a');assert.ok(await f.eval("document.querySelector('dialog').open"));await key('\uE00C');await sleep(100);
  await f.eval("document.querySelector('li.aloha-mega-rentals > a').focus()");await key('\uE015');const header=await rect('.aloha-site-header'),panel=await rect('li.aloha-mega-rentals > ul');assert.equal(header.y,0);assert.equal(panel.y,header.height);if(width===1440)await f.shot(`${out}/firefox-sticky.png`);
  report.desktop.push({width,centerAndChildHover:true,outsideAndReentry:true,keyboard:true,sticky:true});
 }
 for(const width of [320,390,768,1024]){
  await f.viewport(width);await f.goto(base+'/?aloha-shell-preview=1');await sleep(200);await click('.wp-block-navigation__responsive-container-open');await sleep(200);
  assert.ok(await f.eval("document.querySelector('.is-menu-open')!==null"));assert.equal(await f.eval('document.documentElement.scrollWidth'),width);
  await f.eval("document.querySelector('li.aloha-mega-rentals > a').focus()");await key('\uE015');assert.equal(await expanded('rentals'),'true');await f.shot(`${out}/firefox-mobile-${width}.png`);
  await key('\uE00C');assert.equal(await expanded('rentals'),'false');await key('\uE00C');await sleep(200);assert.equal(await f.eval("document.querySelector('.is-menu-open')===null"),true);
  report.mobile.push({width,overlay:true,arrowDownEscape:true,overflow:false});
 }
 await f.viewport(1440);await f.goto(base+'/?aloha-shell-preview=1');await f.eval("(()=>{document.documentElement.style.fontSize='200%';document.querySelector('li.aloha-mega-rentals > a').focus();return true})()");await key('\uE015');assert.equal(await f.eval('document.documentElement.scrollWidth'),1440);await f.shot(`${out}/firefox-text-200.png`);report.text200=true;
 await f.move(1,700);await f.goto(base+'/?aloha-shell-preview=1');assert.equal(await f.eval("document.querySelectorAll('.is-aloha-open').length"),0);report.reloadClosed=true;
 fs.writeFileSync(`${out}/firefox.json`,JSON.stringify(report,null,2)+'\n');console.log('Native Firefox: hover trajectories, child pause, reentry, keyboard, sticky and mobile PASS');
 }finally{await f.close()}
})().catch(e=>{console.error(e);process.exit(1)});
