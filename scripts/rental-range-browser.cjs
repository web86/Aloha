const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const {mkdirSync,writeFileSync}=require('node:fs');
const {join,resolve}=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base='http://127.0.0.1:8088',key='m24-fixture-'+randomBytes(8).toString('hex');
const evidence=process.env.RENTAL_RANGE_SCREENSHOTS||'.local/evidence/m2-4c1b-range';
function wp(file,...args){const r=spawnSync('./bin/local',['wp','eval-file','/var/www/m24-tests/'+file,...args],{encoding:'utf8',timeout:60000});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout.trim();}
const add=(d,n)=>{const v=new Date(d+'T12:00:00Z');v.setUTCDate(v.getUTCDate()+n);return v.toISOString().slice(0,10);};
(async()=>{
 const browser=await chromium.launch();let fixture;const report=[];
 try{
  // Confirm why stock range mode cannot use ordinary unavailable-night disabling.
  const native=await browser.newPage();await native.setContent('<input id="native">');
  await native.addScriptTag({path:resolve(__dirname,'../theme/aloha-hello/assets/lib/flatpickr/flatpickr.min.js')});
  const nativeCount=await native.evaluate(()=>{const p=flatpickr('#native',{mode:'range',disable:['2027-03-20']});p.setDate(['2027-03-15','2027-03-20']);return p.selectedDates.length;});assert.equal(nativeCount,1);await native.close();
  fixture=JSON.parse(wp('rental-template-browser-fixture.php','create',key));wp('rental-booking-fixture.php',key,'range');mkdirSync(evidence,{recursive:true});
  for(const width of [320,390,768,1024,1440]){
   const ctx=await browser.newContext({viewport:{width,height:1000}});let availability=0,prices=0;
   await ctx.route('**/*',r=>new URL(r.request().url()).origin===base?r.continue():r.abort());
   await ctx.route('**/aloha-bridge/v1/rentals/*/*',r=>{
    const u=new URL(r.request().url()),q=u.searchParams;assert.equal(r.request().method(),'GET');
    if(u.pathname.endsWith('/availability')){availability++;throw new Error('Unexpected availability fetch');}
    prices++;return r.fulfill({json:{version:1,rentalId:fixture.public.id,status:'partial',currency:'USD',estimatedTotal:'1234.00',items:[],notices:['Synthetic estimate'],dates:{checkIn:q.get('checkIn'),checkOut:q.get('checkOut')},guests:{adults:Number(q.get('adults')),children:Number(q.get('children'))},expiresAt:new Date(Date.now()+60000).toISOString()}});
   });
   const page=await ctx.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(fixture.public.url);
   const widget=page.locator('.aloha-rental-booking'),dialog=page.locator('.aloha-booking-calendar'),arrow=page.locator('.aloha-calendar-arrow');
   await widget.scrollIntoViewIfNeeded();await widget.locator('[data-booking-dates="in"]').click();
   const today=JSON.parse(await widget.getAttribute('data-booking-config')).today;
   const futureDate=new Date(today+'T12:00:00');futureDate.setDate(1);futureDate.setMonth(futureDate.getMonth()+4);const future=futureDate.toISOString().slice(0,7)+'-';
   const visible=()=>page.evaluate(()=>{const p=document.querySelector('[data-calendar-input]')._flatpickr;return p.currentYear*12+p.currentMonth;});
   async function month(d){const target=new Date(d+'T12:00:00'),n=target.getFullYear()*12+target.getMonth();for(let i=0;i<15;i++){const v=await visible();if(v===n)return;await dialog.locator(v<n?'.flatpickr-next-month':'.flatpickr-prev-month').click();}throw new Error('Month navigation failed');}
   const cell=d=>dialog.locator(`.flatpickr-day[data-date="${d}"]:not(.prevMonthDay):not(.nextMonthDay)`);
   await month(future+'01');
   assert.equal(await cell(future+'03').getAttribute('aria-disabled'),'true','Short available gap is not a usable arrival');
   assert.equal(await cell(future+'03').getAttribute('title'),'Min 3 nights');
   assert.equal(await cell(future+'18').getAttribute('aria-disabled'),'true','All in-range CTD endpoints blocked');
   assert.match(await cell(future+'04').getAttribute('title'),/already booked/);
   assert.notEqual(await cell(future+'04').evaluate(el=>getComputedStyle(el,'::before').content),'none','X is CSS-generated');
   assert.match(await cell(future+'08').getAttribute('title'),/Availability not confirmed/);
   assert.equal(await cell(future+'08').evaluate(el=>getComputedStyle(el,'::before').content),'none','Unknown has no X');
   await cell(future+'03').dispatchEvent('click',{bubbles:true});assert.equal(await dialog.locator('[data-calendar-note]').innerText(),'Min 3 nights','Touch invalid-date feedback');
   for(const [day,nights] of [[5,1],[10,2],[15,3],[20,4],[25,7]]){
    if(!await dialog.isVisible())await widget.locator('[data-booking-dates="in"]').click();
    const start=future+String(day).padStart(2,'0'),end=add(start,nights);await month(start);const left=await visible();
    await cell(start).click();assert.equal(await visible(),left,'Arrival preserves month four months ahead');
    assert.equal(await cell(start).evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(7, 80, 86)');
    assert.equal(await dialog.locator('[data-calendar-note]').innerText(),`Min ${nights} nights`);
    if(end.slice(0,7)!==start.slice(0,7)&&width<768)await month(end);
    if(day===15){await cell(future+'19').hover();assert.equal(await dialog.locator('[data-calendar-note]').innerText(),'Checkout not allowed');assert.equal(await dialog.locator('.aloha-in-range').count(),0);}
    await cell(end).hover();const count=await dialog.locator('.aloha-in-range').count();assert.ok(count>0);
    if(day===25)assert.match(await cell(end).getAttribute('title'),/Checkout only/);
    await dialog.screenshot({path:join(evidence,`range-${nights}-${width}.png`)});
    await cell(end).click();assert.equal(await dialog.isVisible(),false);await widget.locator('[data-total-amount]').getByText(/1,234/).waitFor();
    await widget.locator('[data-booking-dates="out"]').click();assert.ok(await dialog.locator('.aloha-in-range').count()>0,'Committed range survives reopen');await page.keyboard.press('Escape');
   }
   // A longer stay spanning multiple months; manual navigation after arrival remains stable.
   await widget.locator('[data-booking-dates="in"]').click();const longIn=add(today,210),longOut=add(longIn,65);await month(longIn);await cell(longIn).click();await month(longOut);const endMonth=await visible();await cell(longOut).focus();assert.ok(await dialog.locator('.aloha-in-range').count()>0,'Keyboard preview across months');await page.keyboard.press('Enter');assert.equal(await dialog.isVisible(),false);await widget.locator('[data-total-amount]').getByText(/1,234/).waitFor();
   await widget.locator('[data-booking-dates="out"]').click();assert.equal(await visible(),endMonth,'Reopens near checkout');await page.keyboard.press('Escape');
   // New Year range uses the same rules and does not jump to today.
   const year=Number(today.slice(0,4)),newIn=`${year}-12-28`,newOut=`${year+1}-01-04`;
   await widget.locator('[data-booking-dates="in"]').click();await month(newIn);await cell(newIn).click();await month(newOut);await cell(newOut).hover();assert.ok(await dialog.locator('.aloha-in-range').count()>0);await cell(newOut).click();await widget.locator('[data-total-amount]').getByText(/1,234/).waitFor();
   if(width>=768){
    await widget.locator('[data-booking-dates="in"]').click();await month(future+'01');const pair=await visible();
    const second=new Date(future+'15T12:00:00');second.setMonth(second.getMonth()+1);const selected=second.toISOString().slice(0,10);
    await cell(selected).click();assert.equal(await visible(),pair,'Selecting arrival in second displayed month keeps the month pair');
    await cell(add(selected,3)).click();await widget.locator('[data-total-amount]').getByText(/1,234/).waitFor();
   }
   // Arrow points at the active field after placement, and flips when room is above.
   await widget.locator('[data-booking-dates="in"]').click();
   if(width===1440){await page.setViewportSize({width,height:700});await page.waitForTimeout(80);assert.equal(await arrow.getAttribute('data-side'),'bottom');await page.screenshot({path:join(evidence,'arrow-above-1440.png')});}
   const a=await arrow.boundingBox(),field=await widget.locator('[data-booking-dates="in"]').boundingBox();if(a)assert.ok(Math.abs(a.x+a.width/2-field.x-field.width/2)<8,'Arrow points at active field center');
   await page.keyboard.press('Escape');assert.equal(await arrow.isVisible(),false);
   assert.equal(availability,0);assert.deepEqual(errors,[]);report.push({width,prices,availability,nativeDisabledCheckoutCount:nativeCount});await ctx.close();
  }
  writeFileSync(join(evidence,'range-results.json'),JSON.stringify(report,null,2));console.log('PASS: range hover/keyboard/touch, 1/2/3/4/7 nights, impossible arrivals, four-month navigation, occupied checkout, New Year, multi-month stay and arrows at five widths.');console.log(JSON.stringify(report));
 }finally{if(fixture)wp('rental-template-browser-fixture.php','remove',key);await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
