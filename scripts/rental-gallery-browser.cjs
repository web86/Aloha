const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const base = 'http://127.0.0.1:8088';
const key = 'm24-fixture-' + randomBytes(8).toString('hex');
const evidence = process.env.RENTAL_GALLERY_EVIDENCE;
const gallerySelector = '[data-rental-gallery]';
const imageSelector = '.aloha-gallery-tile:not([hidden]) .aloha-gallery-photo img';
const photoNumber = url => Number(new URL(url).pathname.match(new RegExp(key + '-(\\d+)(?:-|\\.)'))?.[1]) || null;
const file = url => new URL(url).pathname.split('/').pop();

function fixture(action) {
    const result = spawnSync('./bin/local', ['wp', 'eval-file', '/var/www/m24-tests/rental-template-browser-fixture.php', action, key],
        {encoding: 'utf8', timeout: 90000});
    assert.equal(result.status, 0, result.stderr);
    return result.stdout.trim();
}

async function ready(gallery) {
    await gallery.locator(imageSelector).evaluateAll(images => Promise.all(images.map(image => image.decode())));
    await gallery.locator('.aloha-gallery-tile:not([hidden]) .is-loading').first().waitFor({state: 'detached'});
}

async function runViewport(browser, url, width) {
    const context = await browser.newContext({viewport: {width, height: 900}, deviceScaleFactor: 1});
    try {
        const page = await context.newPage();
        await page.addInitScript(() => {
            window.galleryVitals = {lcp: null, cls: 0, shifts: []};
            new PerformanceObserver(entries => {
                for (const entry of entries.getEntries()) {
                    window.galleryVitals.lcp = {ms: Math.round(entry.startTime),
                        tag: entry.element?.tagName || null, inMosaic: Boolean(entry.element?.closest('.aloha-gallery-mosaic')),
                        url: entry.url || null};
                }
            }).observe({type: 'largest-contentful-paint', buffered: true});
            let start = -Infinity;
            let last = -Infinity;
            let session = 0;
            new PerformanceObserver(entries => {
                for (const entry of entries.getEntries()) {
                    if (entry.hadRecentInput) continue;
                    if (entry.startTime - last >= 1000 || entry.startTime - start >= 5000) {
                        start = entry.startTime;
                        session = 0;
                    }
                    last = entry.startTime;
                    session += entry.value;
                    window.galleryVitals.shifts.push({value: entry.value, sources: entry.sources.map(({node}) =>
                        node instanceof Element ? node.tagName + '.' + node.className : null)});
                    window.galleryVitals.cls = Math.max(window.galleryVitals.cls, session);
                }
            }).observe({type: 'layout-shift', buffered: true});
        });
        const requests = [];
        page.on('request', request => {
            if (request.resourceType() === 'image') requests.push(request.url());
        });
        const gallery = page.locator(gallerySelector);
        const mosaic = gallery.locator('.aloha-gallery-mosaic');
        const lead = mosaic.locator(imageSelector).first();
        const filters = gallery.getByRole('group', {name: 'Filter photos'});
        const filter = label => filters.getByRole('button', {name: label, exact: true});
        const height = () => mosaic.evaluate(el => el.getBoundingClientRect().height);
        const rows = () => mosaic.evaluate(el => getComputedStyle(el).gridTemplateRows.split(' ').length);
        await page.goto(url);
        await ready(gallery);
        await page.evaluate(() => document.fonts.ready.then(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))));
        await page.waitForFunction(() => window.galleryVitals.lcp);
        await page.waitForTimeout(220);
        assert.equal(await lead.getAttribute('loading'), 'eager');
        assert.equal(await lead.getAttribute('fetchpriority'), 'high');
        assert.equal(await mosaic.locator('img[fetchpriority="high"]').count(), 1);
        assert.equal(await mosaic.locator('img[loading="lazy"]').count(), 4);
        assert.ok(await filters.evaluate(el => el.nextElementSibling.matches('.aloha-gallery-mosaic')));
        assert.equal(await filter('All photos').getAttribute('data-count'), '12');
        assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.aloha-rental-usp')).color), 'rgb(22, 35, 42)');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2));
        const initialHeight = await height();
        const vitals = await page.evaluate(() => window.galleryVitals);
        if (vitals.lcp.url) vitals.lcp.url = file(vitals.lcp.url);
        const transfer = await page.evaluate(() => performance.getEntriesByType('resource').filter(entry => entry.initiatorType === 'img').map(entry => ({
            url: entry.name, bodyBytes: entry.encodedBodySize, transferBytes: entry.transferSize,
        })));
        const images = await mosaic.locator(imageSelector).evaluateAll(nodes => nodes.map(node => ({
            currentSrc: node.currentSrc, width: node.clientWidth, alt: node.alt, sizes: node.sizes,
        })));
        const initialFixtureRequests = requests.map(photoNumber).filter(Boolean);
        // Photo 6 is the fixture's related-Rental thumbnail, outside the mosaic.
        assert.ok(initialFixtureRequests.every(id => id <= 6), 'Off-mosaic gallery photos requested: ' + initialFixtureRequests);
        assert.ok(transfer.every(entry => new URL(entry.url).origin === base), 'A non-local image was pulled into the Rental');
        assert.ok(images.every(image => !/^.+-\d+\.jpg$/.test(image.currentSrc.split('/').pop()) || /-\d+x\d+\.jpg$/.test(image.currentSrc)),
            'A full-size original was selected for the mosaic');
        if (evidence && [390, 1440].includes(width)) await page.screenshot({path: join(evidence, 'first-screen-' + width + '.png')});

        await mosaic.evaluate(el => { window.initialRentalImages = [...el.querySelectorAll('img')]; });
        const patio = filter('Patio');
        const beforePatio = requests.length;
        await patio.press('Enter');
        await ready(gallery);
        assert.equal(await mosaic.getAttribute('data-count'), '1');
        assert.equal(await rows(), 1, 'Single image has extra grid tracks at ' + width);
        assert.equal(await lead.evaluate(img => getComputedStyle(img).objectFit), 'contain');
        assert.equal(await lead.getAttribute('alt'), 'Local photo 5');
        assert.equal(await patio.getAttribute('aria-pressed'), 'true');
        assert.equal(await lead.getAttribute('loading'), 'eager');
        assert.equal(await mosaic.locator('.aloha-gallery-tile:not([hidden]) [fetchpriority="high"]').count(), 0);
        assert.equal(await height(), initialHeight);
        assert.ok(await lead.evaluate(img => img === window.initialRentalImages[4]));
        const patioSrc = file(await lead.evaluate(img => img.currentSrc));
        const patioRequests = requests.slice(beforePatio).map(file);
        assert.ok(requests.slice(beforePatio).map(photoNumber).filter(Boolean).every(number => number === 5),
            'Patio switch requested hidden photos: ' + patioRequests + ' · ' + images.map(image => image.sizes).join(' | '));
        if (evidence && [390, 1440].includes(width)) await gallery.screenshot({path: join(evidence, 'single-photo-' + width + '.png')});
        const beforeRestore = requests.length;
        await filter('All photos').click();
        await ready(gallery);
        assert.equal(await mosaic.getAttribute('data-count'), '5');
        assert.equal(await height(), initialHeight);
        assert.ok(await mosaic.evaluate(el => [...el.querySelectorAll('img')].every((img, index) => img === window.initialRentalImages[index])));
        const restoredRequests = requests.slice(beforeRestore).map(file);
        assert.deepEqual(restoredRequests, [], 'Restoring All photos repeated image downloads');
        for (const [label, expected] of [['Living room', 2], ['Dining area', 3], ['Pool', 4]]) {
            await filter(label).click();
            await ready(gallery);
            assert.equal(await mosaic.getAttribute('data-count'), String(expected));
            assert.equal(await mosaic.locator(imageSelector).count(), expected);
            assert.equal(await height(), initialHeight);
            assert.equal(await rows(), width < 768 && expected === 4 ? 3 : 2);
        }

        await filter('All photos').click();
        const overlay = mosaic.getByRole('link', {name: 'Show all 12 photos'});
        await overlay.scrollIntoViewIfNeeded();
        const beforeLightbox = requests.length;
        await overlay.click();
        const modal = page.locator('.aloha-media-dialog');
        await modal.getByText('1 / 12', {exact: true}).waitFor();
        await modal.locator('img').evaluate(img => img.decode());
        const lightboxRequests = requests.slice(beforeLightbox).map(photoNumber).filter(Boolean);
        assert.ok(lightboxRequests.every(number => number === 2), 'Lightbox loaded photos other than the active one');
        await page.keyboard.press('ArrowLeft');
        await modal.getByText('12 / 12', {exact: true}).waitFor();
        await modal.locator('img').evaluate(img => img.decode());
        assert.equal(await modal.locator('img').getAttribute('alt'), 'Local photo 13');
        await page.keyboard.press('Escape');
        await modal.waitFor({state: 'hidden'});
        await page.waitForFunction(() => document.activeElement?.matches('.aloha-gallery-more'));
        await filter('Living room').click();
        await mosaic.locator('.aloha-gallery-tile:not([hidden]) .aloha-gallery-photo').first().click();
        await modal.getByText('1 / 2', {exact: true}).waitFor();
        await page.keyboard.press('ArrowRight');
        await modal.getByText('2 / 2', {exact: true}).waitFor();
        assert.equal(await modal.locator('img').getAttribute('alt'), 'Local photo 4');
        await page.keyboard.press('Escape');
        await modal.waitFor({state: 'hidden'});
        await page.waitForFunction(() => !document.body.classList.contains('aloha-media-open')
            && document.activeElement?.matches('.aloha-gallery-photo'));

        const actions = page.locator('[data-rental-actions]');
        const status = actions.getByRole('status');
        for (const [label, key] of [['Save', 'Enter'], ['Share', 'Space']]) {
            const action = actions.getByRole('button', {name: label, exact: true});
            await action.focus();
            const active = await action.evaluate(button => document.activeElement === button
                ? button.textContent.trim() : document.activeElement?.className);
            assert.equal(active, label);
            await page.keyboard.press(key);
            assert.equal(await status.textContent(), label + ' is coming soon.');
        }
        const gallerySources = new Set(images.map(image => image.currentSrc));
        const galleryTransfers = transfer.filter(entry => gallerySources.has(entry.url));
        return {width, vitals, images: images.map(image => ({...image, currentSrc: file(image.currentSrc)})),
            imageLoads: transfer.length, bodyBytes: transfer.reduce((sum, entry) => sum + entry.bodyBytes, 0),
            transferBytes: transfer.reduce((sum, entry) => sum + entry.transferBytes, 0),
            galleryImageLoads: galleryTransfers.length,
            galleryBodyBytes: galleryTransfers.reduce((sum, entry) => sum + entry.bodyBytes, 0),
            patioSrc, patioRequests, restoredRequests, lightboxRequests, height: initialHeight};
    } finally {
        await context.close();
    }
}

async function slowAndFailed(browser, url) {
    const context = await browser.newContext({viewport: {width: 390, height: 900}, reducedMotion: 'reduce'});
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    await cdp.send('Network.emulateNetworkConditions', {
        offline: false, latency: 120, downloadThroughput: 75000, uploadThroughput: 40000,
    });
    let release;
    const blocked = new Promise(resolve => { release = resolve; });
    await page.route(url => url.origin === base && photoNumber(url.href) !== null, async route => {
        const number = photoNumber(route.request().url());
        if (number === 5) return route.abort();
        if (number === 2) await blocked;
        return route.continue();
    });
    try {
        await page.goto(url, {waitUntil: 'domcontentloaded'});
        const gallery = page.locator(gallerySelector);
        const lead = gallery.locator(imageSelector).first();
        await gallery.locator('.aloha-gallery-photo.is-loading').first().waitFor({state: 'attached'});
        const measure = () => gallery.evaluate(el => ({
            height: el.querySelector('.aloha-gallery-mosaic').getBoundingClientRect().height,
            below: document.querySelector('.aloha-rental-body').getBoundingClientRect().top + scrollY,
        }));
        const initial = await measure();
        assert.equal(await lead.evaluate(img => getComputedStyle(img).opacity), '0');
        assert.equal(await lead.evaluate(img => getComputedStyle(img).transitionDuration), '0s');
        release();
        await lead.evaluate(img => img.decode());
        await gallery.locator('.aloha-gallery-photo.is-unavailable').waitFor();
        assert.match(await gallery.locator('.aloha-gallery-photo.is-unavailable').evaluate(el => getComputedStyle(el, '::after').content), /Photo unavailable/);
        assert.deepEqual(await measure(), initial, 'Photos changed nearby geometry while loading or failing');
        return {latencyMilliseconds: 120, downloadBytesPerSecond: 75000, initial, after: await measure(), reducedMotion: true};
    } finally {
        release();
        await context.close();
    }
}

(async () => {
    const browser = await chromium.launch();
    try {
        const data = JSON.parse(fixture('create'));
        const report = {chromium: browser.version(), viewportResults: []};
        if (evidence) mkdirSync(evidence, {recursive: true});
        for (const width of [320, 390, 768, 1440]) report.viewportResults.push(await runViewport(browser, data.public.url, width));
        report.slowAndFailed = await slowAndFailed(browser, data.public.url);
        console.log(JSON.stringify(report, null, 2));
        if (evidence) writeFileSync(join(evidence, 'gallery-performance.json'), JSON.stringify(report, null, 2) + '\n');
        console.log('PASS: measured initial load, layouts 1–5, cached nodes, filter and shared lightbox, error and reduced motion.');
    } finally {
        await browser.close();
        fixture('remove');
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
