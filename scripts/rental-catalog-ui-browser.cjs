/** Read-only UI geometry and behavior across installed browser engines. No Rental writes. */
const assert = require('node:assert/strict');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const playwright = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088';
const evidence = process.env.CATALOG_UI_EVIDENCE || '.local/evidence/m2-5a1';
function mockMaps() {
    window.catalogMapState = {zoom: 13, instances: 0, zoomCalls: 0};
    class Map {
        constructor(surface) { this.surface = surface; window.catalogMapState.instances++; }
        fitBounds() {}
        setCenter() {}
        getZoom() { return window.catalogMapState.zoom; }
        setZoom(zoom) { window.catalogMapState.zoom = zoom; window.catalogMapState.zoomCalls++; }
    }
    class AdvancedMarkerElement {
        constructor({map}) { this.element = document.createElement('div'); map.surface.append(this.element); }
        append(element) { this.element.append(element); }
    }
    class LatLngBounds { extend() {} }
    window.google = {maps: {importLibrary: async name => ({maps: {Map}, marker: {AdvancedMarkerElement}, core: {LatLngBounds}}[name])}};
}
(async () => {
    mkdirSync(evidence, {recursive: true});
    const results = [];
    for (const engine of (process.env.CATALOG_UI_ENGINES || 'chromium,firefox').split(',')) {
        let browser;
        try {
            browser = await playwright[engine].launch({timeout: 30000});
            const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'reduce'});
            await context.addInitScript(mockMaps);
            const external = [];
            await context.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.origin === base) return route.continue();
                external.push(url.hostname); return route.abort();
            });
            const page = await context.newPage(); const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(base + '/vacation-rentals/');
            await page.evaluate(() => document.fonts.ready);
            const root = page.locator('[data-rental-catalog]');
            const form = root.locator('form');
            const guests = root.locator('[data-guest-toggle]');
            const popover = root.locator('[data-guest-popover]');
            const geometry = [];
            for (const width of [320, 390, 768, 1024, 1280, 1440, 1920]) {
                await page.setViewportSize({width, height: 1000});
                await page.evaluate(() => { window.scrollTo(0, 0); document.activeElement?.blur(); });
                const measured = await page.evaluate(() => {
                    const box = selector => {
                        const node = document.querySelector(selector), b = node.getBoundingClientRect(), s = getComputedStyle(node);
                        return {x: b.x, y: b.y, width: b.width, height: b.height, fontSize: s.fontSize, lineHeight: s.lineHeight, padding: s.padding, color: s.color, background: s.backgroundColor, radius: s.borderRadius, appearance: s.appearance};
                    };
                    return {width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth + 1, form: box('.aloha-catalog-filters'), search: box('.aloha-catalog-search'), bedroom: box('.aloha-catalog-bedrooms span'), select: box('[name="area_id"]'), sort: box('.aloha-catalog-sort'), title: box('.aloha-catalog-grid h3')};
                });
                assert.equal(measured.overflow, false, engine + ' overflow ' + width);
                assert.equal(measured.search.height, 56); assert.equal(measured.search.color, 'rgb(255, 255, 255)'); assert.equal(measured.search.fontSize, '19px');
                assert.equal(measured.bedroom.height, 34); assert.ok(measured.bedroom.width >= 40);
                assert.equal(await root.locator('.aloha-catalog-bedrooms label').count(), 3);
                assert.equal(measured.select.appearance, 'none'); assert.equal(measured.select.fontSize, '17px'); assert.equal(measured.title.fontSize, '26px');
                assert.equal(measured.sort.height, 44);
                const lastBedroom = await root.locator('.aloha-catalog-bedrooms label').last().boundingBox();
                const searchBox = measured.search;
                assert.ok(lastBedroom.x + lastBedroom.width <= searchBox.x || lastBedroom.y + lastBedroom.height <= searchBox.y || searchBox.y + searchBox.height <= lastBedroom.y, 'Bedrooms overlap Search at ' + width);
                if (width >= 1280) { assert.equal(measured.form.radius, '40px'); assert.equal(measured.form.padding, '6px 6px 6px 8px'); assert.equal(measured.search.padding, '0px 32px'); }
                await guests.click();
                const pop = await popover.boundingBox(); assert.ok(pop.x >= 19 && pop.x + pop.width <= width - 19, engine + ' popover bounds ' + width);
                for (const control of await popover.locator('[data-guest-step]').all()) { const b = await control.boundingBox(); assert.ok(Math.abs(b.width-44)<.01); assert.ok(Math.abs(b.height-44)<.01); }
                const number = await popover.locator('input').first().evaluate(e => {const b=e.getBoundingClientRect(),s=getComputedStyle(e);return{width:Math.round(b.width*100)/100,height:Math.round(b.height*100)/100,padding:s.padding,border:s.borderTopWidth,fontSize:s.fontSize};});
                assert.deepEqual(number, {width:42,height:44,padding:'0px',border:'0px',fontSize:'17px'});
                assert.equal(await page.evaluate(() => document.activeElement.id), 'catalog-adults');
                await page.keyboard.press('Escape'); assert.equal(await popover.isVisible(), false); assert.equal(await guests.evaluate(e => e === document.activeElement), true);
                geometry.push(measured);
            }
            await page.setViewportSize({width: 320, height: 568});
            await guests.click();
            const shortPopover = await popover.boundingBox();
            assert.ok(shortPopover.y >= 68 && shortPopover.y + shortPopover.height <= 553, engine + ' short viewport popover');
            await page.keyboard.press('Escape');
            await page.setViewportSize({width: 1440, height: 1000});
            await guests.click();
            await popover.getByRole('button', {name:'More adults',exact:true}).click();
            await popover.getByRole('button', {name:'More adults',exact:true}).click();
            await popover.getByRole('button', {name:'More children',exact:true}).click();
            assert.equal(await guests.innerText(), '2 adults, 1 child');
            await popover.getByRole('button', {name:'Done',exact:true}).click();
            assert.equal(await popover.isVisible(), false);
            await guests.click(); await form.locator('[name="adults"]').fill('-1'); assert.equal(await form.locator('[name="adults"]').inputValue(), '0');
            await root.locator('h1').click(); assert.equal(await popover.isVisible(), false);
            await guests.click(); await form.locator('[name="adults"]').fill('2'); await form.locator('[name="children"]').fill('0'); await page.keyboard.press('Escape');
            const search = form.getByRole('button', {name:'Search',exact:true});
            await search.hover(); assert.equal(await search.evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(232, 79, 0)');
            await search.focus(); await page.keyboard.press('Tab'); await page.keyboard.press('Shift+Tab'); assert.equal(await search.evaluate(e=>getComputedStyle(e).outlineWidth),'2px');
            const arrow = root.locator('.aloha-related-arrow').first();
            assert.equal(await arrow.evaluate(e=>getComputedStyle(e,'::before').width),'34px');
            const bedroom = form.locator('[name="bedrooms"][value="3"]');
            await bedroom.click(); assert.equal(await bedroom.isChecked(), true); await bedroom.click();
            await page.waitForFunction(() => document.querySelector('[name="bedrooms"][value=""]').checked);
            await bedroom.focus(); await page.keyboard.press('Space'); assert.equal(await bedroom.isChecked(), true); await page.keyboard.press('Space'); assert.equal(await bedroom.isChecked(), false);
            await form.getByRole('button', {name:'Search',exact:true}).click();
            assert.equal(new URL(page.url()).searchParams.get('adults'), '2'); assert.equal(new URL(page.url()).searchParams.has('bedrooms'), false);
            assert.ok(!new URL(page.url()).searchParams.has('dates'));
            await root.locator('[data-catalog-clear]').click();
            await page.evaluate(() => window.scrollTo(0,450));
            await page.waitForTimeout(100);
            let sticky = await root.locator('[data-catalog-sticky]').boundingBox();
            let header = await page.locator('.aloha-site-header').boundingBox();
            assert.ok(sticky.y >= header.y + header.height - 1, 'Sticky overlaps header');
            await page.evaluate(() => { document.documentElement.style.setProperty('--wp-admin--admin-bar--height','32px'); window.dispatchEvent(new Event('resize')); });
            await page.waitForTimeout(100); sticky = await root.locator('[data-catalog-sticky]').boundingBox(); header = await page.locator('.aloha-site-header').boundingBox();
            assert.ok(sticky.y >= header.y + header.height - 1, 'Sticky overlaps admin bar/header');
            await page.evaluate(() => { document.documentElement.style.removeProperty('--wp-admin--admin-bar--height'); window.dispatchEvent(new Event('resize')); window.scrollTo(0,0); });
            await root.locator('[data-catalog-zoom="1"]').click(); assert.equal(await page.evaluate(() => window.catalogMapState.zoom), 14);
            await root.locator('[data-catalog-zoom="-1"]').click(); assert.equal(await page.evaluate(() => window.catalogMapState.zoom), 13);
            const marker = root.locator('.aloha-catalog-marker').first();
            await marker.click(); const preview = root.locator('[data-catalog-map-preview]');
            assert.equal(await preview.isVisible(), true); assert.equal(await marker.getAttribute('aria-expanded'), 'true');
            assert.ok((await marker.getAttribute('class')).includes('is-selected'));
            assert.ok(await preview.locator('a').getAttribute('href'));
            await preview.getByRole('button', {name:'Close home preview'}).click(); assert.equal(await preview.isVisible(), false);
            await marker.focus(); await page.keyboard.press('Enter'); assert.equal(await preview.isVisible(), true); await page.keyboard.press('Escape'); assert.equal(await preview.isVisible(), false);
            const scroll = await page.evaluate(() => window.scrollY); await root.locator('[data-catalog-toggle]').click();
            assert.equal(await root.locator('#catalog-map').isVisible(), false); assert.ok(Math.abs(await page.evaluate(() => window.scrollY)-scroll) <= 1);
            await root.locator('[data-catalog-toggle]').click(); assert.equal(await root.locator('#catalog-map').isVisible(), true);
            assert.equal(await page.evaluate(() => window.catalogMapState.instances), 1);
            assert.equal(await root.getByRole('button', {name:'Add dates',exact:true}).isDisabled(), true);
            assert.equal(await root.getByRole('button', {name:'Search as I move the map'}).isDisabled(), true);
            assert.equal(external.some(host => host.includes('guesty')), false); assert.deepEqual(errors, []);
            await context.close();
            const noJS = await browser.newContext({javaScriptEnabled:false}); const plain = await noJS.newPage();
            await plain.goto(base+'/vacation-rentals/');
            assert.equal(await plain.locator('[name="adults"]').isVisible(),true); assert.equal(await plain.locator('[name="children"]').isVisible(),true);
            await plain.locator('[name="adults"]').fill('100');
            await Promise.all([plain.waitForNavigation(),plain.getByRole('button',{name:'Search',exact:true}).click()]);
            assert.equal(new URL(plain.url()).searchParams.get('adults'),'100');assert.equal(await plain.locator('[data-catalog-item]:not([hidden])').count(),0);
            await noJS.close();
            results.push({engine,version:browser.version(),status:'PASS',geometry});
            console.log('PASS',engine,browser.version(),'seven widths, normalized controls, guests keyboard/stepper/outside, bedrooms reset, sticky/admin offset, zoom/preview, map toggle, no-JS');
        } catch (error) {
            results.push({engine,status:'FAIL',error:error.message}); throw error;
        } finally {
            writeFileSync(join(evidence,'cross-browser.json'),JSON.stringify(results,null,2));
            if(browser)await browser.close();
        }
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
