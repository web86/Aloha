const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, readFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const base = 'http://127.0.0.1:8088';
const key = 'm24-fixture-' + randomBytes(8).toString('hex');
const testPath = '/var/www/m24-tests';
const credentials = Object.fromEntries(readFileSync('.env', 'utf8').split('\n')
    .filter(line => /^WP_ADMIN_(USER|PASSWORD)=/.test(line))
    .map(line => [line.slice(0, line.indexOf('=')), line.slice(line.indexOf('=') + 1).trim().replace(/^(['"])(.*)\1$/, '$2')]));

function wp(file, ...args) {
    const result = spawnSync('./bin/local', ['wp', 'eval-file', testPath + '/' + file, ...args], {
        encoding: 'utf8', timeout: 60000,
    });
    assert.equal(result.status, 0, result.stderr);
    return result.stdout.trim();
}

// Serve a server-rendered fixture fragment with a controlled version of the Maps API.
async function testMap(context, fixture, screenshots) {
    const fragment = wp('rental-template-browser-fixture.php', 'map-html', key);
    const requests = [];
    const mock = `
        let sample;
        class MockMap {
            constructor(surface, options) {
                sample = this;
                this.surface = surface;
                surface.dataset.center = JSON.stringify(options.center);
            }
            addListener() {}
            fitBounds(bounds) { this.surface.dataset.bounds = JSON.stringify(bounds.points); }
            panTo(position) { this.surface.dataset.selection = JSON.stringify(position); }
        }
        class UnusedInfoWindow {
            setContent(node) { this.content = node; }
            open() { sample.surface.dataset.popup = this.content.textContent; }
        }
        class LatLngBounds {
            points = [];
            extend(position) { this.points.push(position); }
        }
        class PinElement {
            constructor({glyphText}) { const pin = document.createElement('span'); pin.textContent = glyphText; return pin; }
        }
        class AdvancedMarkerElement {
            constructor({map, title}) {
                const pin = document.createElement('button');
                pin.setAttribute('aria-label', title);
                map.surface.append(pin);
                return pin;
            }
        }
        window.google = {maps: {importLibrary: async name => ({
            maps: {Map: MockMap}, marker: {AdvancedMarkerElement, PinElement}, core: {LatLngBounds},
        })[name]}};
        window.alohaRentalMapsReady();
    `;
    assert.match(fragment, /data-map-config/);
    for (const disallowed of ['19.765432', '155.234567', '19.555555', 'Private Draft', '-155.345678']) {
        assert.ok(!fragment.includes(disallowed), 'Excluded value in map fragment: ' + disallowed);
    }
    for (const scenario of ['success', 'network error', 'authorization error', 'mobile success', 'browser opt-out']) {
        const page = await context.newPage();
        const success = scenario.endsWith('success');
        const mobile = scenario === 'mobile success';
        if (scenario === 'browser opt-out') await page.addInitScript(() => {
            Object.defineProperty(navigator, 'globalPrivacyControl', {value: true});
        });
        await page.setViewportSize({width: mobile ? 390 : 1440, height: 900});
        await page.route(fixture.url, async route => {
            const response = await route.fetch();
            const html = await response.text();
            let fixtureHtml = html.replace(/<section class="aloha-rental-section" aria-labelledby="aloha-rental-nearby-title"[^>]*>[\s\S]*?<\/section>/, fragment)
                ;
            if(!html.includes('/assets/js/rental-map.js')) fixtureHtml=fixtureHtml.replace('</body>', '<script src="/wp-content/themes/aloha-hello/assets/js/rental-map.js"></script></body>');
            await route.fulfill({response, body: fixtureHtml});
        });
        await page.route('https://maps.googleapis.com/maps/api/js?**', route => {
            requests.push(scenario);
            if (scenario === 'network error') return route.abort();
            return route.fulfill({contentType: 'application/javascript', body: scenario === 'authorization error'
                ? 'window.gm_authFailure();' : mock});
        });
        await page.goto(fixture.url);
        const section = page.locator('[data-rental-map]');
        const list = section.locator('ol');
        assert.ok(!requests.includes(scenario), 'Maps requested far from the viewport.');
        await section.scrollIntoViewIfNeeded();
        if (scenario === 'browser opt-out') {
            assert.ok(await section.getByText('The interactive map is unavailable.').isVisible());
            assert.ok(await list.locator('[data-map-place-name]').filter({hasText: key + ' Beach'}).isVisible());
            await page.close();
            continue;
        }
        if (success) {
            const container = section.locator('.aloha-rental-map');
            if (mobile) assert.ok((await container.boundingBox()).y > (await list.boundingBox()).y);
            else assert.ok((await container.boundingBox()).y > (await list.boundingBox()).y);
            const surface = section.getByRole('region', {name: 'Map of nearby places'});
            await surface.waitFor();
            const markers = surface.getByRole('button');
            assert.deepEqual(await markers.allTextContents(), [key+' Park',key+' Beach',fixture.title]);
            assert.equal(await surface.locator('.aloha-rental-map-label').count(),3);
            assert.equal(await surface.locator('.is-home').count(),1);
            assert.deepEqual(JSON.parse(await surface.getAttribute('data-bounds')), [
                {lat: 19.123456, lng: -155.543217}, {lat: 19.222222, lng: -155.444444}, {lat: 19.111111, lng: -155.222222},
            ]);
            await list.getByRole('button', {name: key + ' Beach'}).click();
            assert.equal(await markers.nth(1).getAttribute('aria-pressed'),'true');
            assert.equal(await list.getByRole('button',{name:key+' Beach'}).getAttribute('aria-pressed'),'true');
            await markers.first().dispatchEvent('gmp-click');
            assert.equal(await markers.first().getAttribute('aria-pressed'),'true');
            assert.equal(await list.getByRole('button', {name: key + ' Park'}).getAttribute('aria-pressed'), 'true');
            assert.equal(await list.getByRole('button', {name: key + ' Legacy Trail'}).count(), 0);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));
            if (screenshots && !mobile) await section.screenshot({path: join(screenshots, 'close-by-map-mock.png')});
        } else {
            await section.getByText(/^The map could not be loaded[.]/).waitFor();
            assert.equal(await list.getByRole('listitem').count(), 3);
            assert.ok(await list.locator('[data-map-place-name]').getByText(key + ' Beach').isVisible());
        }
        await page.close();
    }
    assert.deepEqual(requests, ['success', 'network error', 'authorization error', 'mobile success']);
}

(async () => {
    const browser = await chromium.launch();
    try {
        const fixtures = JSON.parse(wp('rental-template-browser-fixture.php', 'create', key));
        assert.notEqual(fixtures.draft.id, 186);
        console.log(wp('rental-template-smoke.php', String(fixtures.public.id)));
        const publicContext = await browser.newContext();
        const editorContext = await browser.newContext();
        const guestyRequests = [];
        for (const context of [publicContext, editorContext]) {
            await context.route('**/*', route => {
                const target = new URL(route.request().url());
                if (target.origin === base) return route.continue();
                if (target.hostname.endsWith('.guesty.com') || target.hostname === 'guesty.com') {
                    guestyRequests.push(target.hostname);
                }
                return route.abort();
            });
        }

        const page = await publicContext.newPage();
        await page.addInitScript(() => {
            window.rentalShift = 0; window.rentalShiftSources = [];
            new PerformanceObserver(list => {
                for (const entry of list.getEntries()) {
                    if (!entry.hadRecentInput) { window.rentalShift += entry.value; window.rentalShiftSources.push({value:entry.value,nodes:entry.sources?.map(item=>({tag:item.node?.tagName,class:item.node?.className,from:item.previousRect.y,to:item.currentRect.y}))}); }
                }
            }).observe({type: 'layout-shift', buffered: true});
        });
        const unavailableMap = wp('rental-template-browser-fixture.php', 'unavailable-html', key);
        await page.route(fixtures.public.url,async route=>{const response=await route.fetch();const body=(await response.text()).replace(/<section class="aloha-rental-section" aria-labelledby="aloha-rental-nearby-title"[^>]*>[\s\S]*?<\/section>/,unavailableMap);await route.fulfill({response,body});});
        assert.equal((await page.goto(fixtures.public.url)).status(), 200);
        const main = page.locator('.aloha-rental-detail');
        await main.getByRole('heading', {name: fixtures.public.title}).waitFor();
        for (const heading of [
            'What makes this home special', 'About this Keauhou vacation rental', 'Where everyone sleeps',
            /^What['’]s included$/, 'Close by', 'Guests said', 'House rules', /^More vacation rentals/,
        ]) {
            assert.ok(await main.getByRole('heading', {name: heading, exact: true}).isVisible(), String(heading) + " / " + (await main.getByRole("heading", {level: 2}).allTextContents()).join(" | "));
        }
        assert.deepEqual(await main.locator('.aloha-rental-facts dd strong').allTextContents(),
            ['8', '3', '2.5', '2,000', 'Covered', 'Ocean', '5 min', '2 cars']);
        assert.equal(await main.locator('.aloha-rental-review-score').count(), 0);
        assert.deepEqual(await main.locator('.aloha-rental-review figcaption').allTextContents().then(rows => rows.map(row => row.replace(/\s+/g, ' ').trim())),
            ['Anna · Family trip · October 2026', 'October 2026']);
        assert.ok(await main.getByRole('link', {name: /View all reviews for this home on Airbnb/}).isVisible());
        assert.ok(await main.getByText('Fixture road noise.', {exact: true}).isVisible());
        const html = await page.content();
        for (const privateValue of ['PRIVATE_FIXTURE_', 'private-source-', 'private-photo-', 'private-space-',
            '19.765432', '155.234567', '19.555555', 'Private Draft', 'assets.guesty.com', '_awb_source_snapshot']) {
            assert.ok(!html.includes(privateValue), 'Private field reached public HTML: ' + privateValue);
        }

        const tiles = page.locator('.aloha-gallery-tile:not([hidden])');
        assert.equal(await tiles.count(), 5);
        const galleryData = JSON.parse(await page.locator('.aloha-gallery-data').textContent());
        assert.deepEqual(galleryData.map(image => image.id), [2, 1, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13].map(n => fixtures.images[n]));
        assert.ok(galleryData.every(image => new URL(image.url).origin === base));
        const screenshots = process.env.RENTAL_TEMPLATE_SCREENSHOTS;
        if (screenshots) mkdirSync(screenshots, {recursive: true});
        const facts = main.locator('.aloha-rental-facts');
        const booking = main.locator('.aloha-rental-booking');
        const nearby = main.locator('.aloha-rental-nearby');
        const highlights = main.locator('[data-rental-highlights]');
        const viewport = highlights.locator('[data-rental-viewport]');
        const actions = main.locator('.aloha-rental-actions');
        const actionButtons = actions.getByRole('button');
        assert.deepEqual(await highlights.getByRole('heading', {level: 3}).allTextContents(),
            ['Ocean air', 'Sunset patio', 'Morning light', 'Open kitchen', 'Quiet nights']);
        assert.deepEqual(await main.locator('.aloha-rental-sleep-card h3').allTextContents(), ['Suite', 'Garden Room', 'Twin Room']);
        assert.equal(await main.locator('.aloha-rental-bed-icons svg').count(), 3);
        assert.match(await main.locator('.aloha-rental-sleep-card').last().innerText(), /2.*Single/i);
        assert.deepEqual(await nearby.locator('[data-map-place-name]').allTextContents(), [
            key + ' Park', key + ' Beach', key + ' Legacy Trail',
        ]);
        assert.deepEqual(await nearby.locator('.aloha-rental-travel-time').allTextContents(), ['7 min drive']);
        assert.equal(await nearby.getByText('Outdoors').count(), 0);
        assert.equal(await nearby.locator('.aloha-rental-place-number').count(), 0);
        assert.equal(await main.locator('.aloha-rental-crumbs a').first().innerText(), 'Home');
        assert.equal(await main.locator('.aloha-rental-crumbs a').first().getAttribute('href'), base + '/');
        assert.ok(await main.getByText('The interactive map is unavailable.').isVisible());
        assert.equal(await main.locator('[data-map-config]').count(), 0);
        assert.ok([401, 403].includes((await publicContext.request.get(base + '/wp-json/aloha-content/v1/nearby-places')).status()));
        for (const width of [320, 390, 768, 1024, 1440]) {
            await page.setViewportSize({width, height: 900});
            await page.reload();
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), 'Page overflow at ' + width);
            assert.ok(await main.evaluate(el => el.scrollWidth <= el.clientWidth + 2), 'Rental overflow at ' + width);
            const styles = async (selector, properties) => main.locator(selector).first().evaluate((el, keys) => {
                const css = getComputedStyle(el); return keys.map(key => css[key]);
            }, properties);
            assert.deepEqual(await styles('.aloha-rental-fact-custom dd strong',['fontSize']),['17px']);
            assert.notDeepEqual(await styles('.aloha-rental-facts > div:first-child dd strong',['fontSize']),['17px']);
            assert.deepEqual(await styles('.aloha-rental-sleep-card',['padding','gap','flexDirection']),['22px','8px','column']);
            assert.deepEqual(await styles('.aloha-rental-sleep-card h3',['fontSize']),['19px']);
            assert.deepEqual(await styles('.aloha-rental-sleep-card p',['fontSize','color']),['16px','rgb(22, 35, 42)']);
            assert.deepEqual(await styles('.aloha-rental-access.is-ground',['fontSize','padding','borderRadius','backgroundColor','color']),
                ['13px','3px 10px','999px','rgb(7, 80, 86)','rgb(243, 246, 246)']);
            assert.deepEqual(await styles('.aloha-rental-access:not(.is-ground)',['backgroundColor','color']),['rgb(228, 238, 240)','rgb(22, 35, 42)']);
            assert.deepEqual(await styles('.aloha-rental-amenities-section .aloha-rental-more-button',['marginTop','minHeight','padding','borderRadius','fontWeight','fontSize','color','borderTopColor']),
                ['32px','52px','0px 28px','999px','600','16px','rgb(22, 35, 42)','rgb(22, 35, 42)']);
            assert.deepEqual(await styles('.aloha-rental-about [data-rental-more]',['borderRadius']),['0px']);
            for(const li of await nearby.locator('li').all()) assert.deepEqual(await li.evaluate(el=>{
                const s=getComputedStyle(el);return [s.margin,s.borderBottomColor,s.fontSize,s.color];
            }),['0px','rgb(219, 228, 230)','17px','rgb(22, 35, 42)']);
            assert.deepEqual(await styles('.aloha-rental-travel-time',['opacity','fontWeight','whiteSpace']),['0.72','400','nowrap']);
            assert.deepEqual(await styles('.aloha-rental-review',['padding']),['32px']);
            assert.deepEqual(await styles('.aloha-rental-review blockquote',['margin','fontSize','webkitLineClamp']),['0px 0px 24px','18px','3']);
            assert.deepEqual(await styles('.aloha-rental-review figcaption',['fontSize']),['15px']);
            assert.deepEqual(await styles('.aloha-rental-contact',['backgroundColor','color','padding','borderRadius']),
                ['rgb(7, 80, 86)','rgb(243, 246, 246)','32px','10px']);
            assert.deepEqual(await styles('.aloha-rental-contact img',['width','height','objectFit','borderRadius']),['96px','96px','cover','50%']);
            assert.ok((await main.locator('.aloha-rental-contact img').getAttribute('src')).startsWith(base));
            assert.deepEqual(await styles('.aloha-rental-contact p',['fontSize','opacity']),['16px','0.85']);
            assert.deepEqual(await styles('.aloha-rental-rule-summary dt',['fontSize']),['18px']);
            assert.deepEqual(await styles('.aloha-rental-rule-summary dd',['fontSize','color']),['16px','rgb(22, 35, 42)']);
            const columns = async selector => (await styles(selector,['gridTemplateColumns']))[0].split(' ').length;
            assert.equal(await columns('.aloha-rental-rule-summary'),width>=1024?3:width>=768?2:1);
            assert.equal(await columns('.aloha-rental-nearby'),width>=768?2:1);
            assert.equal(await columns('.aloha-rental-reviews'),width>=768?2:1);
            console.log('PASS computed styles at',width);
            await page.evaluate(() => scrollTo(0, 0));
            await page.evaluate(() => document.fonts.ready);
            const before = await actionButtons.evaluateAll(buttons => buttons.map(button => {
                const {x, y, width, height} = button.getBoundingClientRect();
                const icon = button.querySelector('svg').getBoundingClientRect();
                return [x, y, width, height, Math.abs(y + height / 2 - icon.y - icon.height / 2)];
            }));
            for (const button of await actionButtons.all()) {
                await button.focus();
                await page.keyboard.press('Enter');
                assert.match(await actions.getByRole('status').textContent(), /coming soon/i);
                const after = await actionButtons.evaluateAll(buttons => buttons.map(button => {
                    const {x, y, width, height} = button.getBoundingClientRect();
                    const icon = button.querySelector('svg').getBoundingClientRect();
                    return [x, y, width, height, Math.abs(y + height / 2 - icon.y - icon.height / 2)];
                }));
                assert.deepEqual(after, before, 'Action buttons shifted at ' + width);
                assert.ok(after.every(box => box[4] < 1), 'Icon alignment at ' + width);
            }
            assert.ok((await facts.boundingBox()).y > (await main.locator('.aloha-rental-gallery').boundingBox()).y);
            if (width >= 768) {
                assert.ok((await booking.boundingBox()).x > (await facts.boundingBox()).x);
                assert.equal(await booking.evaluate(node => getComputedStyle(node).position), 'sticky');
                await main.locator('.aloha-rental-about').scrollIntoViewIfNeeded();
                assert.ok(Math.abs((await booking.boundingBox()).y - 96) < 4, 'Booking sticky offset at ' + width);
            } else {
                assert.ok((await booking.boundingBox()).y > (await facts.boundingBox()).y);
            }
            if (screenshots && [390, 1440].includes(width)) {
                await page.reload();
                await page.evaluate(() => document.fonts.ready);
                for (const image of await main.locator('img[loading="lazy"]').all()) {
                    if (await image.isVisible()) await image.scrollIntoViewIfNeeded();
                }
                await viewport.evaluate(el => { el.scrollLeft = 0; });
                await page.evaluate(() => scrollTo(0, 0));
                await main.screenshot({path: join(screenshots, 'public-' + width + '.png')});
                for(const [name,selector] of Object.entries({facts:'.aloha-rental-facts',sleeping:'.aloha-rental-sleeping',nearby:'.aloha-rental-nearby',reviews:'.aloha-rental-reviews',contact:'.aloha-rental-contact',rules:'section[aria-labelledby="aloha-rental-rules-title"]'})) {
                    await main.locator(selector).screenshot({path:join(screenshots,name+'-'+width+'.png')});
                }
            }
        }
        const cls = await page.evaluate(() => window.rentalShift);
        if(cls>=.1)console.log('Layout shift sources:',JSON.stringify(await page.evaluate(()=>window.rentalShiftSources)));
        assert.ok(cls < 0.1, 'Unexpected layout shift: ' + cls);
        console.log('Desktop initial layout shift:', cls.toFixed(4));
        await page.emulateMedia({reducedMotion: 'reduce'});
        const previous = highlights.getByRole('button', {name: 'Previous highlight'});
        const next = highlights.getByRole('button', {name: 'Next highlight'});
        assert.ok(await previous.isDisabled());
        await next.click();
        assert.ok(await previous.isEnabled());
        await viewport.focus();
        await page.keyboard.press('End');
        assert.ok(await next.isDisabled());
        await page.keyboard.press('Home');
        assert.ok(await previous.isDisabled());
        const drag = await viewport.boundingBox();
        await page.mouse.move(drag.x + drag.width - 20, drag.y + 90);
        await page.mouse.down();
        await page.mouse.move(drag.x + drag.width / 2, drag.y + 90, {steps: 12});
        await page.mouse.up();
        await page.waitForFunction(() => !document.querySelector('[data-rental-prev]').disabled);

        const touchContext = await browser.newContext({viewport: {width: 390, height: 900}, hasTouch: true, isMobile: true});
        const touchPage = await touchContext.newPage();
        await touchPage.goto(fixtures.public.url);
        const touchViewport = touchPage.locator('[data-rental-viewport]');
        await touchViewport.scrollIntoViewIfNeeded();
        const swiping = await touchViewport.boundingBox();
        const cdp = await touchContext.newCDPSession(touchPage);
        const touch = (type, offset) => cdp.send('Input.dispatchTouchEvent', {type, touchPoints: offset === null ? []
            : [{x: swiping.x + swiping.width - 24 - offset, y: swiping.y + 65}]});
        await touch('touchStart', 0);
        for (const offset of [30, 65, 110, 165, 230]) await touch('touchMove', offset);
        await touch('touchEnd', null);
        await touchPage.waitForFunction(() => !document.querySelector('[data-rental-prev]').disabled);
        await touchContext.close();

        const about = main.locator('.aloha-rental-about');
        assert.equal(await about.getByText('A sunny coastal retreat.').count(), 0);
        assert.ok(await about.getByText('Synthetic public description.').isVisible());
        assert.equal(await about.getByText('Synthetic Guesty description.').count(), 0);
        const aboutMore = about.getByRole('button', {name: 'Read the full description ↓'});
        assert.equal(await aboutMore.getAttribute('aria-expanded'), 'false');
        assert.ok(await about.locator('[data-description-content]').evaluate(el => el.classList.contains('is-collapsed')));
        await aboutMore.focus();
        await page.keyboard.press('Space');
        assert.ok(await about.getByText(/Final description detail/).isVisible());
        await about.getByRole('button', {name: 'Show less ↑'}).click();
        assert.ok(await about.locator('[data-description-content]').evaluate(el => el.classList.contains('is-collapsed')));

        assert.ok(await main.getByText('Ground floor · no steps').isVisible());
        assert.match(await main.locator('.aloha-rental-sleep-card').first().innerText(), /King Bed · en-suite, walk-in shower/);
        assert.ok(await main.getByText('Upstairs · 14 steps').isVisible());
        const house = main.locator('section[aria-labelledby="aloha-rental-rules-title"]');
        assert.ok(await house.getByText('Confirmed fixture policy').first().isVisible());
        assert.ok(!(await house.getByText('Minimum guest age').isVisible()));
        await house.getByRole('button', {name: 'All house rules & guest info →'}).click();
        assert.ok(await house.getByText('Minimum guest age').isVisible());
        await house.getByRole('button', {name: 'Show less ↑'}).click();
        const contactText = await main.locator('.aloha-rental-contact p').innerText();
        assert.equal((contactText.match(/Alex/g) || []).length, 1);
        assert.ok(!contactText.includes('{First Name}'));
        const ask = main.getByRole('link', {name: 'Ask Alex'});
        assert.match(await ask.getAttribute('href'), /^mailto:.*example[.]test[?]subject=/);
        const amenities = main.locator('section[aria-labelledby="aloha-rental-amenities-title"]');
        assert.equal(await amenities.getByText('Hammock').isVisible(), false);
        assert.equal(await amenities.getByText('Fixture Workspace').isVisible(), false);
        await amenities.getByRole('button', {name: 'Show all 11 amenities'}).click();
        assert.ok(await amenities.getByText('Hammock').isVisible());
        assert.ok(await amenities.getByText('Fixture Workspace').isVisible());
        await amenities.getByRole('button', {name: 'Show less ↑'}).click();
        assert.equal(await amenities.getByText('Hammock').isVisible(), false);

        const staticContext = await browser.newContext({javaScriptEnabled: false});
        const staticPage = await staticContext.newPage();
        await staticPage.goto(fixtures.public.url);
        assert.ok(await staticPage.getByText(/Final description detail/).isVisible());
        assert.ok(await staticPage.getByText('Fixture Workspace').isVisible());
        assert.equal(await staticPage.locator('.aloha-rental-highlight').count(), 5);
        await staticContext.close();
        await testMap(publicContext, fixtures.public, screenshots);
        await page.getByRole('button', {name: 'Living room', exact: true}).click();
        assert.equal(await tiles.count(), 2);
        await tiles.first().locator('.aloha-gallery-photo').click();
        const modal = page.locator('dialog.aloha-media-dialog');
        await modal.waitFor({state: 'visible'});
        await modal.getByText('1 / 2', {exact: true}).waitFor();
        await page.keyboard.press('ArrowRight');
        await modal.getByText('2 / 2', {exact: true}).waitFor();
        await page.keyboard.press('Escape');
        await modal.waitFor({state: 'hidden'});
        await page.getByRole('button', {name: 'Ocean view', exact: true}).click();
        assert.equal(await tiles.count(), 1);
        await page.getByRole('button', {name: 'All photos', exact: true}).click();
        assert.equal(await tiles.count(), 5);

        wp('rental-template-browser-fixture.php', 'description', key, 'guesty');
        await page.reload();
        await about.getByRole('heading', {name: 'About this Keauhou vacation rental'}).waitFor();
        assert.equal(await about.getByText('A sunny coastal retreat.').count(), 0);
        assert.ok(await about.getByText('Synthetic Guesty description.').isVisible());
        assert.equal(await about.getByText('Synthetic public description.').count(), 0);
        assert.ok(!(await about.getByRole('button').isVisible()));

        wp('rental-template-browser-fixture.php', 'description', key, 'excerpt');
        await page.reload();
        assert.equal(await about.count(), 0);
        assert.equal(await highlights.locator('.aloha-rental-highlight').count(), 1);
        assert.ok(!(await highlights.locator('.aloha-rental-carousel-controls').isVisible()));
        await page.goto(fixtures.empty.url);
        await page.getByRole('heading', {name: fixtures.empty.title}).waitFor();
        for (const absent of ['.aloha-rental-gallery', '#aloha-rental-reviews-title', '#aloha-rental-sleep-title',
            '#aloha-rental-amenities-title', '#aloha-rental-rules-title', '#aloha-rental-nearby-title',
            '#aloha-rental-highlights-title', '#aloha-rental-about-title']) {
            assert.equal(await page.locator(absent).count(), 0, absent);
        }

        await editorContext.request.get(base + '/wp-login.php');
        assert.equal((await editorContext.request.post(base + '/wp-login.php', {maxRedirects: 0, form: {
            log: credentials.WP_ADMIN_USER, pwd: credentials.WP_ADMIN_PASSWORD, testcookie: '1',
        }})).status(), 302);
        const editor = await editorContext.newPage();
        await editor.goto(base + '/wp-admin/post.php?post=' + fixtures.draft.id + '&action=edit');
        const preview = editor.locator('#post-preview');
        const [tab] = await Promise.all([editorContext.waitForEvent('page'), preview.click()]);
        await tab.getByRole('heading', {name: fixtures.draft.title, exact: true}).waitFor();
        const link = new URL(tab.url());
        assert.equal(link.searchParams.get('preview'), 'true');
        const idParam = link.searchParams.has('preview_id') ? 'preview_id' : 'awb_preview_id';
        const nonceParam = link.searchParams.has('preview_nonce') ? 'preview_nonce' : 'awb_preview_nonce';
        assert.equal(link.searchParams.get(idParam), String(fixtures.draft.id));
        assert.ok(link.searchParams.get(nonceParam));
        if (screenshots) await tab.locator('.aloha-rental-detail').screenshot({path: join(screenshots, 'draft-preview.png')});
        await page.goto(link.toString());
        assert.equal(await page.getByRole('heading', {name: fixtures.draft.title}).count(), 0, 'Anonymous preview leak.');
        link.searchParams.set(idParam, String(fixtures.public.id));
        await tab.goto(link.toString());
        assert.equal(await tab.getByRole('heading', {name: fixtures.draft.title}).count(), 0, 'Mismatched preview ID accepted.');
        link.searchParams.set(idParam, String(fixtures.draft.id));
        link.searchParams.set(nonceParam, 'wrong-nonce');
        await tab.goto(link.toString());
        assert.equal(await tab.getByRole('heading', {name: fixtures.draft.title}).count(), 0, 'Invalid preview nonce accepted.');
        link.searchParams.delete(nonceParam);
        await tab.goto(link.toString());
        assert.equal(await tab.getByRole('heading', {name: fixtures.draft.title}).count(), 0, 'Missing preview nonce accepted.');
        assert.deepEqual(guestyRequests, []);
        console.log('PASS: published Rental, native draft Preview and nonce rejection, local gallery filters, lightbox, selected reviews, Save/Share, sticky booking, highlights touch and keyboard, About modes, disclosures, Nearby map (mocked success, network and authorization errors), empty sections and 320/390/768/1024/1440 px.');
    } finally {
        await browser.close();
        wp('rental-template-browser-fixture.php', 'remove', key);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
