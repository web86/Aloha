const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088';
const key = 'm24-fixture-' + randomBytes(8).toString('hex');
function wp(path, ...args) {
    const result = spawnSync('./bin/local', ['wp', 'eval-file', path, ...args.map(String)], {encoding: 'utf8', timeout: 120000});
    assert.equal(result.status, 0, result.stdout + result.stderr);
    return result.stdout.trim();
}
const fixture = (...args) => wp('/var/www/m24-tests/rental-template-browser-fixture.php', ...args.slice(0, 1), key, ...args.slice(1));
function mockMaps() {
    window.catalogMapCreations = 0;
    class Map {
        constructor(surface) { this.surface = surface; window.catalogMapCreations++; }
        fitBounds() {}
        setCenter() {}
    }
    class AdvancedMarkerElement {
        constructor({map}) { this.element = document.createElement('div'); map.surface.append(this.element); }
        append(element) { this.element.append(element); }
    }
    class LatLngBounds { extend() {} }
    window.google = {maps: {importLibrary: async name => ({maps: {Map}, marker: {AdvancedMarkerElement}, core: {LatLngBounds}}[name])}};
}
(async () => {
    const browser = await chromium.launch();
    try {
        const data = JSON.parse(fixture('create'));
        fixture('related');
        const prepared = JSON.parse(wp('/var/www/m24-tests/rental-catalog-fixture.php', 'prepare', key));
        console.log(wp('/var/www/bridge-tests/rental-catalog-check.php', data.public.id, key));
        console.log(wp('/var/www/m24-tests/rental-catalog-fixture.php', 'check', key));
        const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'reduce', extraHTTPHeaders: {'X-Aloha-Catalog-Test': key}});
        const external = [];
        await context.route('**/*', route => {
            const url = new URL(route.request().url());
            if (url.origin === base) return route.continue();
            external.push(url.hostname); return route.abort();
        });
        await context.addInitScript(mockMaps);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const catalog = page.locator('[data-rental-catalog]');
        const form = catalog.locator('form');
        const allCards = catalog.locator('[data-catalog-item]');
        const visible = () => catalog.locator('[data-catalog-item]:not([hidden])');
        const ids = async () => visible().evaluateAll(elements => elements.map(element => Number(element.dataset.catalogItem)));
        await page.goto(base + '/vacation-rentals/?adults=100', {waitUntil: 'networkidle'});
        assert.equal(await allCards.count() >= 5, true);
        assert.equal(await visible().count(), 0);
        assert.equal(await allCards.locator('img[src]').count(), 0, 'Filtered cards fetched images');
        const markup = await page.content();
        for (const privateText of ['private-source-', 'PRIVATE_FIXTURE', 'assets.guesty.com', '_awb_source', 'private-photo-']) assert.ok(!markup.includes(privateText), privateText);
        assert.ok(!markup.includes('rental-booking.js') && !markup.includes('flatpickr'));
        await catalog.locator('[data-catalog-clear]').click();
        assert.equal(await visible().count(), await allCards.count());
        for (const mode of ['draft', 'related-private', 'related-unmapped', 'related-other']) assert.equal(await catalog.locator(`[data-catalog-item="${prepared[mode].id}"]`).count(), 0, mode);
        const target = catalog.locator(`[data-catalog-item="${data.public.id}"]`);
        const area = String(JSON.parse(await target.getAttribute('data-catalog-fields')).area);
        assert.ok(await target.locator('img').count() === 6);
        assert.equal(await target.locator('img[src]').count(), 1);
        await target.locator('[data-related-next]').click();
        assert.equal(await target.locator('img[src]').count(), 2);
        assert.equal(await target.locator('[data-related-carousel]').getAttribute('data-related-index'), '1');
        await target.locator('[data-related-viewport]').focus(); await page.keyboard.press('End');
        assert.equal(await target.locator('[data-related-carousel]').getAttribute('data-related-index'), '5');
        await page.keyboard.press('Home');
        assert.match(await target.locator('img').first().getAttribute('alt'), /Local photo 2/);
        assert.equal(await target.locator('img[alt="Local photo 6"]').count(), 0);
        assert.match(await target.locator('.aloha-related-price').innerText(), /345/);
        assert.equal(await catalog.locator(`[data-catalog-item="${prepared['related-single'].id}"] .aloha-related-price`).innerText(), 'Check dates for pricing');
        assert.match(await catalog.locator(`[data-catalog-item="${prepared.empty.id}"] .aloha-related-placeholder`).innerText(), /Photos coming soon/);
        await page.waitForFunction(() => window.catalogMapCreations === 1);
        assert.equal(await catalog.locator('.aloha-catalog-marker.is-match').count() >= 3, true);
        const countMarkers = await catalog.locator('.aloha-catalog-marker').count();
        const requests = [];
        page.on('request', request => requests.push({type: request.resourceType(), url: request.url()}));
        await form.locator('[name="area_id"]').selectOption(area);
        await form.locator('[name="bedrooms"][value="3"]').check();
        await form.locator('[name="adults"]').fill('6'); await form.locator('[name="children"]').fill('2');
        await form.getByRole('button', {name: 'Search', exact: true}).click();
        assert.deepEqual(await ids(), [data.public.id]);
        assert.match(page.url(), /area_id=/); assert.match(page.url(), /adults=6/); assert.match(page.url(), /children=2/);
        assert.equal(await catalog.locator('.aloha-catalog-marker.is-match').count(), 1);
        assert.equal(await catalog.locator('.aloha-catalog-marker.is-neutral').count(), countMarkers - 1);
        assert.equal(await page.evaluate(() => window.catalogMapCreations), 1, 'Filtering recreated the map');
        assert.ok(!requests.some(request => ['xhr', 'fetch', 'document'].includes(request.type)), 'Local filter made a data request');
        assert.match(await catalog.locator('[data-catalog-count]').innerText(), /^1 home/);
        const filteredURL = page.url();
        await page.reload(); assert.deepEqual(await ids(), [data.public.id]);
        await catalog.locator('[data-catalog-remove="children"]').click();
        assert.ok(!new URL(page.url()).searchParams.has('children'));
        await page.goBack(); assert.equal(page.url(), filteredURL); assert.equal(await form.locator('[name="children"]').inputValue(), '2');
        await page.goForward(); assert.equal(await form.locator('[name="children"]').inputValue(), '0');
        await catalog.locator('[data-catalog-clear]').click();
        for (const bedrooms of ['2', '3', '4']) {
            await form.locator(`[name="bedrooms"][value="${bedrooms}"]`).check();
            await form.getByRole('button', {name: 'Search', exact: true}).click();
            assert.equal((await ids()).includes(prepared[{'2': 'related-single', '3': 'public', '4': 'related-gallery'}[bedrooms]].id), true);
            assert.equal((await ids()).includes(prepared.empty.id), false, 'Unknown bedrooms matched');
            await catalog.locator('[data-catalog-clear]').click();
        }
        await form.locator('[name="children"]').fill('1');
        await form.getByRole('button', {name: 'Search', exact: true}).click();
        assert.equal((await ids()).includes(prepared['related-gallery'].id), false, 'Confirmed child restriction ignored');
        assert.equal((await ids()).includes(prepared.empty.id), false, 'Unknown capacity matched');
        await catalog.locator('[data-catalog-clear]').click();
        const noJS = await browser.newContext({javaScriptEnabled: false, extraHTTPHeaders: {'X-Aloha-Catalog-Test': key}});
        await noJS.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const plain = await noJS.newPage();
        for (const query of ['', `area_id=${area}`, 'bedrooms=2', 'bedrooms=3', 'bedrooms=4', 'adults=5&children=1', `area_id=${area}&bedrooms=3&adults=6&children=2`,
            'adults=100', 'area_id[]=1&bedrooms[]=3&adults=-1&children=999999999999', 'bedrooms=2&bedrooms=3', 'bedrooms=3&bedrooms[]=2', 'area_id=999999999']) {
            const url = base + '/vacation-rentals/' + (query ? '?' + query : '');
            const response = await plain.goto(url);
            assert.equal(response.status(), 200, query);
            await page.goto(url);
            const ssr = await plain.locator('[data-catalog-item]:not([hidden])').evaluateAll(elements => elements.map(element => Number(element.dataset.catalogItem)));
            assert.deepEqual(await ids(), ssr, 'PHP/JS mismatch: ' + query);
            if (query === 'adults=100') assert.equal(ssr.length, 0);
        }
        await plain.goto(base + '/vacation-rentals/');
        await plain.locator('[name="area_id"]').selectOption(area);
        await plain.locator('[name="bedrooms"][value="2"]').check();
        await Promise.all([plain.waitForNavigation(), plain.getByRole('button', {name: 'Search', exact: true}).click()]);
        assert.equal(new URL(plain.url()).searchParams.get('bedrooms'), '2');
        assert.equal(await plain.locator('[data-catalog-item]:not([hidden])').count(), 1);
        await noJS.close();
        await page.goto(base + '/vacation-rentals/?area_id=' + area);
        for (const width of [1440, 768, 390]) {
            await page.setViewportSize({width, height: 1000});
            await page.evaluate(() => document.fonts.ready);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Overflow at ' + width);
            const columns = await catalog.locator('.aloha-catalog-grid').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length);
            assert.equal(columns, width === 390 ? 1 : 2);
            if (width === 1440) assert.equal(await catalog.locator('.aloha-catalog-map-sticky').evaluate(element => getComputedStyle(element).position), 'sticky');
            const next = target.locator('[data-related-next]');
            const touch = await next.boundingBox(); assert.ok(touch.width >= 44 && touch.height >= 44);
            const toggle = catalog.locator('[data-catalog-toggle]'); await toggle.click();
            assert.equal(await catalog.locator('#catalog-map').isVisible(), false);
            await toggle.click(); assert.equal(await catalog.locator('#catalog-map').isVisible(), true);
            if (process.env.CATALOG_SCREENSHOTS) {
                mkdirSync(process.env.CATALOG_SCREENSHOTS, {recursive: true});
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.waitForTimeout(200);
                await page.screenshot({path: join(process.env.CATALOG_SCREENSHOTS, 'catalog-' + width + '.png'), fullPage: true});
            }
            console.log('PASS catalog layout', width);
        }
        for (const mode of ['missing', 'error', 'gpc']) {
            const other = await browser.newContext({viewport: {width: 1440, height: 1000}, extraHTTPHeaders: {'X-Aloha-Catalog-Test': key, 'X-Aloha-Catalog-Map': mode}});
            if (mode === 'gpc') await other.addInitScript(() => Object.defineProperty(navigator, 'globalPrivacyControl', {value: true}));
            let mapsRequests = 0;
            await other.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.origin === base) return route.continue();
                if (url.hostname.includes('googleapis')) mapsRequests++;
                return route.abort();
            });
            const fallback = await other.newPage();
            await fallback.goto(base + '/vacation-rentals/');
            const message = fallback.locator('[data-catalog-map-message]');
            await message.waitFor({state: 'visible'});
            if (mode === 'gpc') assert.match(await message.innerText(), /privacy/);
            if (mode === 'error') { await fallback.waitForTimeout(250); assert.equal(mapsRequests, 1); assert.match(await message.innerText(), /unavailable/); }
            else assert.equal(mapsRequests, 0);
            assert.ok(await fallback.locator('[data-catalog-item]:not([hidden])').count() >= 5);
            await other.close();
        }
        assert.equal(external.some(host => host.includes('guesty')), false);
        assert.deepEqual(errors, []);
        console.log('PASS catalog SSR/JS parity, GET/no-JS, Area/bedrooms/capacity/child restrictions, chips/clear/history/reload, local photos/Embla, public markers/filter updates, provider failure/missing key/GPC and asset isolation.');
        console.log('Server HTTP audit marker:', key);
        await context.close();
    } finally { fixture('remove'); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
