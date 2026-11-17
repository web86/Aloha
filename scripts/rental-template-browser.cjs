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
        assert.equal((await page.goto(fixtures.public.url)).status(), 200);
        const main = page.locator('.aloha-rental-detail');
        await main.getByRole('heading', {name: fixtures.public.title}).waitFor();
        for (const heading of [
            'What makes this home special', 'About this vacation rental', 'Where everyone sleeps',
            "What's included", 'Close by', 'Guests said', 'House rules', 'More vacation rentals',
        ]) {
            assert.ok(await main.getByRole('heading', {name: heading, exact: true}).isVisible(), heading);
        }
        assert.match(await main.locator('.aloha-rental-facts').innerText(), /8 guests.*2,000 ft²/);
        const scores = await main.locator('.aloha-rental-review-score').allTextContents();
        assert.deepEqual(scores.map(score => score.replace(/\s+/g, ' ').trim()), ['4.5 / 5 Airbnb', '8.5 / 10 Booking.com']);
        assert.ok(await main.getByText('Fixture road noise.', {exact: true}).isVisible());
        const html = await page.content();
        for (const privateValue of ['PRIVATE_FIXTURE_', 'private-source-', 'private-photo-', 'private-space-',
            '19.123456', '155.543217', 'assets.guesty.com', '_awb_source_snapshot']) {
            assert.ok(!html.includes(privateValue), 'Private field reached public HTML: ' + privateValue);
        }

        const tiles = page.locator('.aloha-gallery-tile');
        assert.equal(await tiles.count(), 5);
        const galleryData = JSON.parse(await page.locator('.aloha-gallery-data').textContent());
        assert.deepEqual(galleryData.map(image => image.id), [2, 1, 3, 4, 5].map(n => fixtures.images[n]));
        assert.ok(galleryData.every(image => new URL(image.url).origin === base));
        const screenshots = process.env.RENTAL_TEMPLATE_SCREENSHOTS;
        if (screenshots) mkdirSync(screenshots, {recursive: true});
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({width, height: 900});
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), 'Page overflow at ' + width);
            assert.ok(await main.evaluate(el => el.scrollWidth <= el.clientWidth + 2), 'Rental overflow at ' + width);
            if (screenshots) await main.screenshot({path: join(screenshots, 'public-' + width + '.png')});
        }
        await page.getByRole('button', {name: 'Living room 2', exact: true}).click();
        assert.equal(await tiles.count(), 2);
        await tiles.first().locator('.aloha-gallery-photo').click();
        const modal = page.locator('dialog.aloha-media-dialog');
        await modal.waitFor({state: 'visible'});
        await modal.getByText('1 / 2', {exact: true}).waitFor();
        await page.keyboard.press('ArrowRight');
        await modal.getByText('2 / 2', {exact: true}).waitFor();
        await page.keyboard.press('Escape');
        await modal.waitFor({state: 'hidden'});
        await page.getByRole('button', {name: 'Ocean view 1', exact: true}).click();
        assert.equal(await tiles.count(), 1);
        await page.getByRole('button', {name: 'All photos 5', exact: true}).click();
        assert.equal(await tiles.count(), 5);

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
        console.log('PASS: published Rental, native draft Preview and nonce rejection, local gallery filters, lightbox, selected reviews, empty sections and 320/390/768/1440 px.');
    } finally {
        await browser.close();
        wp('rental-template-browser-fixture.php', 'remove', key);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
