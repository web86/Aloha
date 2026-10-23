/* Real editor/media/video/dialog acceptance; only the disposable :8091 site. */
const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const auth=JSON.parse(fs.readFileSync(process.env.ALOHA_CONTENT_TEST_AUTH,'utf8'));
const fixture=JSON.parse(fs.readFileSync(process.env.ALOHA_AREA_HERO_FIXTURES,'utf8'));
const presentation=JSON.parse(fs.readFileSync(process.env.ALOHA_AREA_PRESENTATION_FIXTURES,'utf8'));
assert.equal(auth.url,'http://127.0.0.1:8091','Never seed primary WordPress with Hero fixtures');
const out=process.env.EVIDENCE_DIR,report={checks:[],pageErrors:[],viewports:[]},media=[];
let browser,page;
const check=label=>{report.checks.push(label);console.log('PASS:',label);};
async function whiteBackdropContrast(front){
 await front.evaluate(()=>new Promise(requestAnimationFrame));
 const ratio=await front.locator('.aloha-area-hero').evaluate(hero=>{
  const crumb=hero.querySelector('.aloha-area-breadcrumb'),rect=hero.getBoundingClientRect();
  const start=parseFloat(hero.style.getPropertyValue('--aloha-area-copy-start'));
  const y=crumb.getBoundingClientRect().top-rect.top;
  const alpha=179/255+(230-179)/255*Math.max(0,y-start)/Math.max(1,rect.height-start);
  const background=[22,35,42].map(c=>c*alpha+255*(1-alpha));
  const text=getComputedStyle(crumb).color.match(/[\d.]+/g).slice(0,3).map(Number);
  const luminance=rgb=>rgb.map(c=>{c/=255;return c<=.04045?c/12.92:((c+.055)/1.055)**2.4;}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
  return (luminance(text)+.05)/(luminance(background)+.05);
 });
 assert.ok(ratio>=4.5,'Small Hero copy contrast over a white backing: '+ratio);
 return Number(ratio.toFixed(2));
}
const api=(method,path,data)=>page.evaluate(async({method,path,data})=>wp.apiFetch({path:'/wp/v2/'+path,method,data}),{method,path,data});
async function editor(id){
 await page.goto(auth.url+`/wp-admin/post.php?post=${id}&action=edit`);
 await page.waitForFunction(()=>window.wp?.data?.select('core/editor')?.getCurrentPostType()==='area'&&wp.data.select('core/block-editor').getBlocks().length>5);
 await page.evaluate(()=>wp.data.dispatch('core/preferences').set('core/edit-post','welcomeGuide',false));
 const welcome=page.getByRole('button',{name:'Get started',exact:true});if(await welcome.isVisible())await welcome.click();
 const panel=page.getByRole('button',{name:'Area Media',exact:true});await panel.waitFor();if(await panel.getAttribute('aria-expanded')!=='true')await panel.click();
 await page.getByLabel('YouTube film URL',{exact:true}).waitFor();
}
async function save(){await page.evaluate(async()=>{await wp.data.dispatch('core/editor').savePost();});await page.waitForFunction(()=>!wp.data.select('core/editor').isSavingPost());}
async function chooseVideo(id){
 await page.getByRole('button',{name:/^(Choose|Replace) background video$/}).click();
 const modal=page.locator('.media-modal');await modal.waitFor();
 const library=modal.getByRole('tab',{name:'Media Library',exact:true});if(await library.isVisible())await library.click();
 const tile=modal.locator(`.attachment[data-id="${id}"]`);await tile.waitFor();await tile.click();
 await modal.locator('.media-button-select').click();await modal.waitFor({state:'hidden'});
 await page.waitForFunction(id=>wp.data.select('core/editor').getEditedPostAttribute('meta').aloha_area_loop_video_id===id,id);
}
async function uploadVideo(name,colour){
 // A labelled tiny colour-only WebM fixture, never an editorial or main-site video.
 const result=await page.evaluate(async({name,colour})=>{
  const canvas=document.createElement('canvas');canvas.width=160;canvas.height=90;
  const ctx=canvas.getContext('2d'),stream=canvas.captureStream(10);
  const recorder=new MediaRecorder(stream,{mimeType:'video/webm;codecs=vp8'}),chunks=[];
  const stopped=new Promise(resolve=>recorder.onstop=resolve);recorder.ondataavailable=e=>chunks.push(e.data);recorder.start();
  const draw=setInterval(()=>{ctx.fillStyle=colour;ctx.fillRect(0,0,160,90);ctx.fillStyle='#ffffff';ctx.font='12px sans-serif';ctx.fillText('TEST FIXTURE',28,48);},80);
  await new Promise(r=>setTimeout(r,600));recorder.stop();await stopped;clearInterval(draw);stream.getTracks().forEach(t=>t.stop());
  const form=new FormData();form.append('file',new File(chunks,name+'.webm',{type:'video/webm'}));form.append('title',name);
  return wp.apiFetch({path:'/wp/v2/media',method:'POST',body:form});
 },{name,colour});media.push(result.id);return result;
}
(async()=>{
 browser=await chromium.launch({headless:true});
 const editorContext=await browser.newContext({viewport:{width:1440,height:1000}});page=await editorContext.newPage();page.on('pageerror',e=>report.pageErrors.push(e.message));
 try{
  await page.goto(auth.url+'/wp-login.php?redirect_to='+encodeURIComponent(auth.url+`/wp-admin/post.php?post=${fixture.area}&action=edit`));await page.waitForTimeout(400);
  await page.locator('#user_login').fill(auth.user);await page.locator('#user_pass').fill(auth.password);assert.ok(await page.locator('#user_pass').inputValue()===auth.password);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  await editor(fixture.area);
  const original=(await api('GET',`area/${fixture.area}?context=edit`)).content.raw;
  const blue=await uploadVideo('Hero blue TEST fixture','#075056'),green=await uploadVideo('Hero green TEST fixture','#23572c');
  await chooseVideo(blue.id);await page.getByLabel('YouTube film URL',{exact:true}).fill('https://youtu.be/AlohaTest01');await save();
  await editor(fixture.area);assert.equal(await page.getByLabel('YouTube film URL',{exact:true}).inputValue(),'https://www.youtube.com/watch?v=AlohaTest01');
  assert.equal(await page.locator('.interface-complementary-area video').getAttribute('src'),blue.source_url);
  check('Area Media selects a native Library video, saves and reopens both revisioned fields');
  await chooseVideo(green.id);await save();await editor(fixture.area);assert.equal((await api('GET',`area/${fixture.area}?context=edit`)).meta.aloha_area_loop_video_id,green.id);
  await page.getByRole('button',{name:'Remove background video',exact:true}).click();await save();await editor(fixture.area);
  assert.equal(await page.getByRole('button',{name:'Choose background video',exact:true}).count(),1);
  await chooseVideo(blue.id);await save();
  await api('POST',`area/${fixture.area}`,{featured_media:presentation.media.hero});
  const revisions=await api('GET',`area/${fixture.area}/revisions?per_page=100`);assert.ok(revisions.length>=3);
  assert.equal((await api('GET',`area/${fixture.area}?context=edit`)).content.raw,original);
  check('Video replacement/removal survives reopening; native revisions exist and Gutenberg body is unchanged');
  const invalid=await page.evaluate(async id=>{try{await wp.apiFetch({path:'/wp/v2/area/'+id,method:'POST',data:{meta:{aloha_area_film_url:'https://example.test/not-youtube'}}});return null;}catch(e){return e.code;}},fixture.area);
  assert.equal(invalid,'aloha_invalid_area_film');
  const valid=await page.evaluate(()=>{const all=bs=>bs.flatMap(b=>[b,...all(b.innerBlocks||[])]);return all(wp.data.select('core/block-editor').getBlocks()).every(b=>b.isValid!==false&&b.name!=='core/missing');});assert.ok(valid);
  await editor(fixture.area);await page.getByLabel('YouTube film URL',{exact:true}).scrollIntoViewIfNeeded();await page.screenshot({path:out+'/hero-editor.png',fullPage:true});
  check('Invalid film URL produces a REST error; existing starter blocks stay valid');
  await api('POST',`area/${fixture.other}`,{meta:{aloha_area_loop_video_id:green.id,aloha_area_film_url:'https://youtube.com/watch?v=AlohaTest02'}});
  assert.equal((await api('GET',`area/${fixture.area}?context=edit`)).meta.aloha_area_loop_video_id,blue.id);
  check('Two Areas keep independent video and film settings');
  const frontContext=await browser.newContext({viewport:{width:1440,height:1000}}),front=await frontContext.newPage();front.on('pageerror',e=>report.pageErrors.push(e.message));
  const youtube=[];await frontContext.route('https://www.youtube-nocookie.com/**',route=>{youtube.push(route.request().url());return route.fulfill({contentType:'text/html',body:'<!doctype html><title>YouTube player test double</title><button>Player test control</button>'});});
  await front.goto(fixture.url);await front.evaluate(()=>document.fonts.ready);
  const video=front.locator('.aloha-area-hero-video');await front.waitForFunction(()=>document.querySelector('.aloha-area-hero-video')?.classList.contains('is-playing'));
  assert.equal(youtube.length,0);assert.equal(await front.locator('iframe').count(),0);
  assert.equal(await video.evaluate(v=>v.muted&&v.loop&&v.autoplay&&v.playsInline),true);assert.ok(await video.getAttribute('poster'));
  assert.equal(await front.locator('.aloha-area-hero-image').getAttribute('fetchpriority'),'high');
  check('Native muted loop plays over its prioritized poster; no YouTube request or iframe before activation');
  await front.getByRole('button',{name:'Pause background video',exact:true}).click();assert.equal(await video.evaluate(v=>v.paused),true);
  await front.getByRole('button',{name:'Play background video',exact:true}).click();await front.waitForFunction(()=>!document.querySelector('video').paused);
  await front.evaluate(()=>scrollTo(0,document.body.scrollHeight));await front.waitForFunction(()=>document.querySelector('.aloha-area-hero-video').paused);
  await front.evaluate(()=>scrollTo(0,0));await front.waitForFunction(()=>!document.querySelector('.aloha-area-hero-video').paused);
  check('Background loop has a keyboard-accessible pause control and pauses outside the viewport');
  const film=front.getByRole('button',{name:'Watch the 2-min area film',exact:true});
  for(let i=0;i<40&&!await film.evaluate(el=>el===document.activeElement);i++)await front.keyboard.press('Tab');
  assert.equal(await film.evaluate(el=>el===document.activeElement),true);
  assert.notEqual(await film.evaluate(el=>getComputedStyle(el).outlineStyle),'none');await front.keyboard.press('Enter');
  const dialog=front.locator('.aloha-media-dialog[open]');await dialog.waitFor();assert.equal(await dialog.locator('iframe').count(),1);
  await front.waitForFunction(()=>document.querySelector('.aloha-area-hero-video').paused);assert.equal(youtube.length,1);
  await front.getByRole('button',{name:'Close',exact:true}).waitFor();
  // Native Chromium may Tab into browser chrome (activeElement === body).
  // Its :modal top layer keeps background page controls inert without a custom trap.
  for(let i=0;i<4;i++){await front.keyboard.press('Tab');assert.ok(await front.evaluate(()=>{const d=document.querySelector('.aloha-media-dialog');return d.matches(':modal')&&(d.contains(document.activeElement)||document.activeElement===document.body);}));}
  await front.screenshot({path:out+'/hero-modal-desktop.png'});
  // Cross-origin players own keys while focused. Return to the native Close
  // control using Tab, then Escape closes the dialog from its document.
  for(let i=0;i<6&&!await front.getByRole('button',{name:'Close',exact:true}).evaluate(el=>el===document.activeElement);i++)await front.keyboard.press('Tab');
  assert.equal(await front.getByRole('button',{name:'Close',exact:true}).evaluate(el=>el===document.activeElement),true);
  await front.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});await front.locator('.aloha-media-dialog iframe').waitFor({state:'detached'});assert.equal(await front.locator('.aloha-media-dialog iframe').count(),0);assert.equal(await film.evaluate(el=>el===document.activeElement),true);
  await film.click();await dialog.waitFor();await front.getByRole('button',{name:'Close',exact:true}).click();await dialog.waitFor({state:'hidden'});
  await film.click();await dialog.waitFor();await front.mouse.click(2,2);await dialog.waitFor({state:'hidden'});await front.locator('.aloha-media-dialog iframe').waitFor({state:'detached'});
  check('Film dialog supports Enter/Tab/Escape/Close, contains focus, removes its iframe and restores focus');
  for(const width of [1440,1024,768,390,320]){
   await front.setViewportSize({width,height:1000});await front.evaluate(()=>scrollTo(0,0));
   assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Hero overflow at '+width);
   const geometry=await video.evaluate(v=>{const a=v.getBoundingClientRect(),b=v.parentElement.getBoundingClientRect();return{w:a.width,h:a.height,pw:b.width,ph:b.height,fit:getComputedStyle(v).objectFit};});assert.equal(geometry.w,geometry.pw);assert.equal(geometry.h,geometry.ph);assert.equal(geometry.fit,'cover');
   if(width<768)assert.ok(await front.locator('.aloha-area-video-toggle').evaluate(el=>el.getBoundingClientRect().top>el.parentElement.querySelector('.aloha-area-hero-actions').getBoundingClientRect().bottom+8),'Mobile pause control must not overlap the CTA');
   report.contrast=report.contrast||{};report.contrast[width]=await whiteBackdropContrast(front);
   await front.screenshot({path:out+'/hero-'+width+'.png'});report.viewports.push(width);
  }
  await film.click();await dialog.waitFor();await front.screenshot({path:out+'/hero-modal-mobile.png'});await front.keyboard.press('Escape');
  await front.evaluate(()=>document.documentElement.style.fontSize='200%');assert.ok(await front.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.ok(await front.locator('.aloha-area-video-toggle').evaluate(el=>el.getBoundingClientRect().top>el.parentElement.querySelector('.aloha-area-hero-actions').getBoundingClientRect().bottom+8),'200% text pause control must not overlap the CTA');report.contrast.text200=await whiteBackdropContrast(front);await front.screenshot({path:out+'/hero-text-200.png'});
  check('Five responsive Hero sizes and 200% text retain cover framing and no horizontal overflow');
  await front.goto(fixture.other_url);await front.waitForFunction(()=>document.querySelector('.aloha-area-hero-video')?.classList.contains('is-playing'));assert.equal(await front.locator('.aloha-area-hero-image').count(),0);
  check('Video also works without a Featured Image over the brand-color fallback');
  const reduced=await browser.newContext({reducedMotion:'reduce'}),rp=await reduced.newPage();const reducedRequests=[];rp.on('request',r=>{if(r.url()===blue.source_url)reducedRequests.push(r.url());});
  await rp.goto(fixture.url);await rp.waitForTimeout(500);assert.equal(await rp.locator('video').getAttribute('src'),null);assert.equal(reducedRequests.length,0);assert.equal(await rp.locator('.aloha-area-hero-image').evaluate(i=>i.naturalWidth>0),true);
  await rp.emulateMedia({reducedMotion:'no-preference'});await rp.waitForFunction(()=>document.querySelector('.aloha-area-hero-video').classList.contains('is-playing'));
  await rp.emulateMedia({reducedMotion:'reduce'});await rp.waitForFunction(()=>!document.querySelector('.aloha-area-hero-video').hasAttribute('src'));await rp.screenshot({path:out+'/hero-reduced-motion.png'});await reduced.close();
  check('Reduced motion makes no video request; live preference changes stop and unload the loop');
  for(const signal of [{saveData:true,type:'wifi',effectiveType:'4g'},{saveData:false,type:'cellular',effectiveType:'4g'},{saveData:false,type:'wifi',effectiveType:'2g'}]){
   const cx=await browser.newContext();await cx.addInitScript(signal=>{const c=new EventTarget();Object.assign(c,signal);Object.defineProperty(navigator,'connection',{value:c});},signal);const p=await cx.newPage();let requests=0;p.on('request',r=>{if(r.url()===blue.source_url)requests++;});await p.goto(fixture.url);await p.waitForTimeout(350);assert.equal(await p.locator('video').getAttribute('src'),null);assert.equal(requests,0);await cx.close();
  }
  check('Save-Data, known cellular and slow connections suppress video loading/autoplay');
  const broken=await browser.newContext();await broken.route(blue.source_url,route=>route.abort());const bp=await broken.newPage();await bp.goto(fixture.url);await bp.waitForFunction(()=>!!document.querySelector('.aloha-area-hero-video').error);
  assert.equal(await bp.locator('video').evaluate(v=>v.classList.contains('is-playing')),false);assert.equal(await bp.locator('.aloha-area-hero-image').evaluate(i=>i.naturalWidth>0),true);await bp.screenshot({path:out+'/hero-video-error.png'});await broken.close();
  check('Failed video loading leaves the intact fallback poster');
  const denied=await browser.newContext();await denied.addInitScript(()=>{
   // Model a policy declining both declarative autoplay and explicit play().
   const src=Object.getOwnPropertyDescriptor(HTMLMediaElement.prototype,'src');
   Object.defineProperty(HTMLMediaElement.prototype,'src',{get:src.get,set(value){this.autoplay=false;src.set.call(this,value);}});
   HTMLMediaElement.prototype.play=()=>Promise.reject(new DOMException('Fixture autoplay declined','NotAllowedError'));
  });const dp=await denied.newPage();await dp.goto(fixture.url);await dp.waitForTimeout(500);assert.equal(await dp.locator('video').evaluate(v=>v.classList.contains('is-playing')),false);await denied.close();
  check('Browser autoplay rejection leaves the poster visible without a blank frame');
  await api('POST',`area/${fixture.other}`,{meta:{aloha_area_loop_video_id:0,aloha_area_film_url:''}});await front.goto(fixture.other_url);assert.equal(await front.locator('.aloha-area-hero video,.aloha-area-hero img,.aloha-area-film').count(),0);assert.ok(await front.locator('.aloha-area-hero').evaluate(el=>getComputedStyle(el).backgroundColor!=='rgba(0, 0, 0, 0)'));
  assert.equal(await front.getByRole('link',{name:'Explore the area',exact:true}).getAttribute('href'),'#area-content');assert.equal(await front.getByText(/See \d+ homes/).count(),0);await front.screenshot({path:out+'/hero-no-media.png'});
  assert.equal((await api('GET',`area/${fixture.area}?context=edit`)).content.raw,original);assert.deepEqual(report.pageErrors,[]);
  check('Empty media gives the text Hero, neutral CTA and no fabricated home count; saved body remains intact');
  await frontContext.close();
 }catch(e){report.error=e.message;if(page&&!page.isClosed())await page.screenshot({path:out+'/hero-failure.png',mask:[page.locator('#user_login'),page.locator('#user_pass')]});throw e;}
 finally{if(page&&!page.isClosed())for(const id of media)await api('DELETE','media/'+id+'?force=true').catch(()=>{});fs.writeFileSync(out+'/area-hero-browser-report.json',JSON.stringify(report,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
