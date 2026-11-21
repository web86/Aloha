/** Real public data / real Google Maps screenshots. No seeding or source changes. */
const assert = require('node:assert/strict');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const evidence = process.env.CATALOG_UI_EVIDENCE || '.local/evidence/m2-5a1';
(async()=>{
    const browser = await chromium.launch();
    try {
        const context = await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
        let guesty = 0; const errors = [];
        await context.route('**/*',route=>{
            if(new URL(route.request().url()).hostname.includes('guesty')){guesty++;return route.abort();}
            return route.continue();
        });
        const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
        const dir=join(evidence,'updated');mkdirSync(dir,{recursive:true});
        await page.goto('http://127.0.0.1:8088/vacation-rentals/');await page.evaluate(()=>document.fonts.ready);
        await page.locator('.aloha-catalog-marker').first().waitFor({timeout:25000});
        await page.waitForTimeout(1200);
        const root=page.locator('[data-rental-catalog]'),form=root.locator('form');
        const capture=async(name,fullPage=true)=>{
            await page.mouse.move(1,1);await page.evaluate(()=>{document.activeElement?.blur();window.scrollTo(0,0)});
            await page.waitForTimeout(150);await page.screenshot({path:join(dir,name+'.png'),fullPage});
        };
        const sizes={};
        for(const width of[1440,768,390]){
            await page.setViewportSize({width,height:1000});
            await capture('catalog-'+width);
            if(width===390)await page.screenshot({path:join(dir,'mobile-viewport.png')});
            sizes[width]=await page.evaluate(()=>{
                const read=s=>{const e=document.querySelector(s),b=e.getBoundingClientRect(),c=getComputedStyle(e);return{x:b.x,y:b.y,width:b.width,height:b.height,fontSize:c.fontSize,lineHeight:c.lineHeight,padding:c.padding,radius:c.borderRadius,color:c.color,background:c.backgroundColor};};
                return{form:read('.aloha-catalog-filters'),search:read('.aloha-catalog-search'),heading:read('.aloha-catalog h1'),card:read('.aloha-rental-related-card'),map:read('.aloha-catalog-map-sticky'),sort:read('.aloha-catalog-sort')};
            });
            await root.locator('[data-guest-toggle]').click();
            await form.locator('[name="adults"]').fill('2');await form.locator('[name="children"]').fill('1');
            await page.mouse.move(1,1);await page.screenshot({path:join(dir,'guests-'+width+'.png')});
            await page.keyboard.press('Escape');
            await root.locator('[data-guest-toggle]').click();
            await form.locator('[name="adults"]').fill('0');await form.locator('[name="children"]').fill('0');
            await root.locator('[data-guest-done]').click();
        }
        await page.setViewportSize({width:1440,height:1000});await page.evaluate(()=>scrollTo(0,0));
        await root.locator('[data-guest-toggle]').click();await form.locator('[name="children"]').fill('0');await root.locator('[data-guest-done]').click();
        const fields=JSON.parse(await root.locator('[data-catalog-fields]').first().getAttribute('data-catalog-fields'));
        if(fields.area)await form.locator('[name="area_id"]').selectOption(String(fields.area));
        if(fields.bedrooms!==null){const value=fields.bedrooms>=4?'4':String(fields.bedrooms);if(['2','3','4'].includes(value))await form.locator(`[name="bedrooms"][value="${value}"]`).check();}
        await form.getByRole('button',{name:'Search',exact:true}).click();await capture('filters-selected');
        await root.locator('[data-catalog-clear]').click();
        await root.locator('.aloha-catalog-marker').first().click();
        assert.equal(await root.locator('[data-catalog-map-preview]').isVisible(),true);
        await page.waitForTimeout(1500);
        await capture('map-selected');await root.locator('.aloha-catalog-map-sticky').screenshot({path:join(dir,'map-preview.png')});
        await root.getByRole('button',{name:'Close home preview'}).click();
        await root.locator('[data-catalog-zoom="1"]').click();await root.locator('[data-catalog-zoom="-1"]').click();
        await root.locator('[data-catalog-toggle]').click();await capture('map-hidden');await root.locator('[data-catalog-toggle]').click();
        await root.locator('[data-guest-toggle]').click();await form.locator('[name="adults"]').fill('100');await root.locator('[data-guest-done]').click();
        await form.getByRole('button',{name:'Search',exact:true}).click();assert.equal(await root.locator('[data-catalog-item]:not([hidden])').count(),0);await capture('empty-results');
        await root.locator('[data-catalog-clear]').click();await capture('disabled-controls');
        await root.locator('.aloha-catalog-filters').screenshot({path:join(dir,'filter-panel.png')});
        assert.equal(guesty,0);assert.deepEqual(errors,[]);
        writeFileSync(join(evidence,'visual-measurements.json'),JSON.stringify({liveMap:true,guestyRequests:guesty,errors,sizes},null,2));
        console.log('PASS actual Google Maps, current public data, desktop/tablet/mobile, guests, selected filters, map preview/zoom, hidden map, empty and disabled states; Guesty=0.');
    }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
