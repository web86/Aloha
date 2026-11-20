/* Synthetic PHP Breakdown DTOs rendered by the real local widget; no live Guesty data. */
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088', key = 'm24-fixture-' + randomBytes(8).toString('hex');
const evidence = process.env.PRICE_COMPONENT_SCREENSHOTS || '.local/evidence/kiss-price-components';
function wp(file, ...args) {
    const r = spawnSync('./bin/local', ['wp', 'eval-file', file, ...args.map(String)], {encoding: 'utf8', timeout: 60000});
    assert.equal(r.status, 0, r.stdout + r.stderr); return r.stdout.trim();
}
(async () => {
    const browser = await chromium.launch(); let fixture; const results = [];
    const modes = ['unknown-fee', 'nullable', 'confirmed', 'mixed', 'mismatch'];
    try {
        fixture = JSON.parse(wp('/var/www/m24-tests/rental-template-browser-fixture.php', 'create', key));
        wp('/var/www/m24-tests/rental-booking-fixture.php', key, 'range'); mkdirSync(evidence, {recursive: true});
        const payloads = Object.fromEntries(modes.map(mode => [mode, JSON.parse(wp('/var/www/html/wp-content/plugins/aloha-wp-bridge/tests/pricing-visible-fixture.php', fixture.public.id, mode))]));
        for (const width of [320, 390, 768, 1024, 1440]) {
            const context = await browser.newContext({viewport: {width, height: 1100}, reducedMotion: 'reduce'});
            let mode = modes[0], prices = 0, availability = 0;
            await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            await context.route('**/aloha-bridge/v1/rentals/*/*', route => {
                const url = new URL(route.request().url()), q = url.searchParams; assert.equal(route.request().method(), 'GET');
                if (url.pathname.endsWith('/availability')) { availability++; throw new Error('Unexpected availability request'); }
                prices++;
                const {diagnostics, ...dto} = payloads[mode]; // Match the public REST allowlist.
                return route.fulfill({json: {...dto, dates: {...dto.dates, checkIn: q.get('checkIn'), checkOut: q.get('checkOut')}, guests: {adults: +q.get('adults'), children: +q.get('children')}, expiresAt: new Date(Date.now() + 60000).toISOString()}});
            });
            const page = await context.newPage(), errors = []; page.on('pageerror', error => errors.push(error.message));
            await page.goto(fixture.public.url); await page.evaluate(() => document.fonts.ready);
            const widget = page.locator('.aloha-rental-booking'), dialog = page.locator('.aloha-booking-calendar'); await widget.scrollIntoViewIfNeeded();
            await widget.locator('[data-booking-dates="in"]').click();
            const target = 2027 * 12;
            for (let i = 0; i < 15; i++) {
                const current = await page.evaluate(() => { const p = document.querySelector('[data-calendar-input]')._flatpickr; return p.currentYear * 12 + p.currentMonth; });
                if (current === target) break;
                await dialog.locator(current < target ? '.flatpickr-next-month' : '.flatpickr-prev-month').click();
            }
            const day = value => dialog.locator(`.flatpickr-day[data-date="2027-01-${value}"]:not(.prevMonthDay):not(.nextMonthDay)`);
            await day('07').click(); await day('12').click();
            for (let i = 0; i < modes.length; i++) {
                mode = modes[i];
                if (i) {
                    await widget.locator('.aloha-booking-guests summary').click();
                    await widget.locator('[data-guests="adults"]').fill(String(i + 2)); await widget.locator('[data-guests-done]').click();
                }
                const total = mode === 'mismatch' ? '$5,896.38' : '$5,895.38';
                await widget.locator('[data-total-amount]').getByText(total, {exact: true}).waitFor();
                const beforeDetails = prices; await widget.locator('.aloha-booking-breakdown summary').click(); assert.equal(prices, beforeDetails);
                const estimated = widget.locator('[data-price-lines] dt.aloha-price-estimated');
                const expected = mode === 'confirmed' || mode === 'mismatch' ? 0 : mode === 'mixed' ? 2 : 3;
                assert.equal(await estimated.count(), expected);
                for (const text of await estimated.allTextContents()) assert.match(text, /\(estimated\)$/);
                assert.equal(await widget.locator('[data-price-lines] .aloha-price-estimated-start').count(), expected ? 2 : 0);
                assert.equal(await widget.locator('[data-marketing-amount]').innerText(), '$884');
                assert.equal(await widget.locator('[data-booking-status]').isVisible(), false);
                assert.equal(await widget.getByText('Unconfirmed fee', {exact: true}).count(), 0);
                if (mode === 'confirmed') assert.equal(await widget.locator('[data-price-detail-note]').isVisible(), false);
                else assert.equal(await widget.locator('[data-price-detail-note]').innerText(), 'Some charges are estimated or not itemized');
                if (mode === 'unknown-fee') {
                    const rows = await widget.locator('[data-price-lines]').innerText();
                    for (const value of ['$4,550.00', '$425.00', '$547.25', '$223.88', '$149.25']) assert.ok(rows.includes(value), rows);
                    assert.equal(payloads[mode].status, 'partial');
                }
                if (mode === 'mixed') {
                    const classes = await widget.locator('[data-price-lines] dt').evaluateAll(nodes => nodes.map(node => [node.textContent, node.className]));
                    assert.equal(classes.findIndex(([text]) => text === 'TAT'), 2);
                    assert.ok(classes.slice(3, 5).every(([, klass]) => klass.includes('aloha-price-estimated')), JSON.stringify(classes));
                }
                if ([390, 1440].includes(width)) {
                    await widget.evaluate(el => window.scrollTo(0, Math.max(0, scrollY + el.getBoundingClientRect().top - parseFloat(el.style.getPropertyValue('--aloha-booking-offset')))));
                    await widget.screenshot({path: join(evidence, `${mode}-${width}.png`)});
                }
            }
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
            assert.equal(overflow, false); assert.equal(availability, 0); assert.deepEqual(errors, []);
            results.push({width, prices, availability, overflow}); await context.close();
        }
        writeFileSync(join(evidence, 'results.json'), JSON.stringify(results, null, 2));
        console.log('PASS: real PHP DTOs, visible independent estimated taxes, separate confirmed rows, null inclusivity, unchanged/mismatched BE totals and separate savings at five widths.'); console.log(JSON.stringify(results));
    } finally { if (fixture) wp('/var/www/m24-tests/rental-template-browser-fixture.php', 'remove', key); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
