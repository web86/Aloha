const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088', key = 'm24-fixture-' + randomBytes(8).toString('hex');
const evidence = process.env.RENTAL_BOOKING_SCREENSHOTS || '.local/evidence/m2-4c1';
function wp(file, ...args) {
    const r = spawnSync('./bin/local', ['wp', 'eval-file', '/var/www/m24-tests/' + file, ...args], {encoding: 'utf8', timeout: 60000});
    assert.equal(r.status, 0, r.stdout + r.stderr); return r.stdout.trim();
}
const add = (d, n) => { const v = new Date(d + 'T12:00:00Z'); v.setUTCDate(v.getUTCDate() + n); return v.toISOString().slice(0, 10); };
(async () => {
    const browser = await chromium.launch(); let fixture;
    const report = [];
    try {
        fixture = JSON.parse(wp('rental-template-browser-fixture.php', 'create', key));
        wp('rental-booking-fixture.php', key); mkdirSync(evidence, {recursive: true});
        for (const width of [320, 390, 768, 1024, 1440]) {
            const context = await browser.newContext({viewport: {width, height: 1000}, reducedMotion: 'reduce'});
            let mode = 'reconciled', calendarError = false, priceDelay = 350, priceCalls = 0;
            await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            await context.route('**/aloha-bridge/v1/rentals/*/*', async route => {
                const url = new URL(route.request().url()), q = url.searchParams;
                const json = {version: 1, rentalId: fixture.public.id, fetchedAt: new Date().toISOString(), expiresAt: new Date(Date.now() + 60000).toISOString()};
                assert.equal(route.request().method(), 'GET');
                if (url.pathname.endsWith('/availability')) {
                    await new Promise(resolve => setTimeout(resolve, 350));
                    if (calendarError) return route.fulfill({status: 503, json: {status: 'unavailable', message: 'Calendar temporarily unavailable. Please retry.'}});
                    const from = q.get('from'), to = q.get('to'), days = [];
                    const today = new Intl.DateTimeFormat('en-CA', {timeZone: 'Pacific/Honolulu'}).format(new Date());
                    for (let d = from; d <= to; d = add(d, 1)) days.push({date: d, status: d === add(today, 7) ? 'unavailable' : 'available', nightlyRate: {amount: '579.00', currency: 'USD'}, minNights: 3, maxNights: 28, cta: d === add(today, 1), ctd: d === add(today, 6), reason: null});
                    return route.fulfill({json: {...json, status: 'available', from, to, days,
                        rules: {today, earliestCheckIn: today, windowEnd: add(from, 365), maxNights: 28, capacity: 11, multi: false, suitableForChildren: true, timezone: 'Pacific/Honolulu'}}});
                }
                priceCalls++; const requestedMode = mode;
                await new Promise(resolve => setTimeout(resolve, priceDelay));
                if (requestedMode === 'unavailable') return route.fulfill({status: 422, json: {status: 'unavailable', message: 'These dates are unavailable. Choose another stay.'}});
                const checkIn = q.get('checkIn'), checkOut = q.get('checkOut');
                return route.fulfill({json: {...json, ...(requestedMode === 'stale' ? {expiresAt: new Date(Date.now() - 1000).toISOString()} : {}), status: requestedMode === 'stale' ? 'reconciled' : requestedMode, currency: 'USD', estimatedTotal: requestedMode === 'partial' ? '4600.00' : '4542.11',
                    dates: {checkIn, checkOut, nights: 5}, guests: {adults: Number(q.get('adults')), children: Number(q.get('children'))},
                    items: [{kind: 'accommodation', label: '5 nights (calendar rates)', amount: '3238.00'}, {kind: 'cleaning', label: 'Cleaning fee', amount: '595.00'}, {kind: 'tax', label: 'Taxes (TAT + GET + HITAT)', amount: '709.11'}],
                    notices: [requestedMode === 'partial' ? 'Detailed price breakdown is temporarily unavailable.' : 'Estimated total. Final amount is confirmed during booking.'], bookingEnabled: false}});
            });
            const page = await context.newPage(), errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.addInitScript(() => { window.fixtureCLS = 0; new PerformanceObserver(list => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.fixtureCLS += e.value; }).observe({type: 'layout-shift', buffered: true}); });
            await page.goto(fixture.public.url); await page.evaluate(() => document.fonts.ready);
            const widget = page.locator('.aloha-rental-booking'), dialog = page.locator('.aloha-booking-calendar');
            await widget.scrollIntoViewIfNeeded();
            assert.match(await widget.locator('[data-booking-heading]').innerText(), /345/);
            assert.equal(await widget.getByText('Book direct with Aloha', {exact: true}).count(), 1);
            assert.equal(await widget.getByText(/15%|vs Airbnb/).count(), 0);
            await widget.screenshot({path: join(evidence, `booking-default-${width}.png`)});
            await widget.locator('[data-booking-dates="in"]').click();
            await page.getByText('Loading availability…', {exact: true}).waitFor();
            if ([390,1440].includes(width)) await dialog.screenshot({path: join(evidence, `calendar-loading-${width}.png`)});
            await page.locator('[data-calendar-loading]').waitFor({state: 'hidden'});
            assert.equal(await dialog.locator('.dayContainer').count(), width >= 768 ? 2 : 1);
            const keyboardDay = dialog.locator('.flatpickr-day[tabindex="0"]').first();
            await keyboardDay.focus(); await page.keyboard.press('ArrowRight');
            assert.equal(await dialog.locator('.flatpickr-day').evaluateAll(nodes => nodes.some(el => el === document.activeElement)), true);
            const today = JSON.parse(await widget.getAttribute('data-booking-config')).today;
            const start = add(today, 2);
            const cell = d => dialog.locator(`.flatpickr-day[data-date="${d}"]:not(.prevMonthDay):not(.nextMonthDay)`);
            assert.equal(await cell(add(today, 1)).getAttribute('aria-disabled'), 'true');
            await cell(start).click();
            assert.equal(await cell(add(start, 1)).getAttribute('aria-disabled'), 'true');
            await page.getByText('Choose checkout. Minimum stay: 3 nights.', {exact: true}).waitFor();
            assert.equal(await cell(add(start, 4)).getAttribute('aria-disabled'), 'true');
            assert.notEqual(await cell(add(start, 5)).getAttribute('aria-disabled'), 'true');
            assert.equal(await cell(add(start, 6)).getAttribute('aria-disabled'), 'true');
            await cell(add(start, 5)).click();
            assert.equal(await dialog.locator('.aloha-range-start').count(), 1);
            assert.equal(await dialog.locator('.aloha-range-end').count(), 1);
            await dialog.screenshot({path: join(evidence, `calendar-${width}.png`)});
            await dialog.locator('[data-calendar-apply]').click();
            await widget.getByText('Calculating total…', {exact: true}).first().waitFor();
            if ([390,1440].includes(width)) await widget.screenshot({path: join(evidence, `booking-loading-${width}.png`)});
            await widget.locator('[data-total-amount]').getByText(/4,542/).waitFor();
            await widget.locator('.aloha-booking-breakdown summary').click();
            await widget.getByText('Cleaning fee', {exact: true}).waitFor();
            await widget.screenshot({path: join(evidence, `booking-details-${width}.png`)});
            await widget.locator('[data-booking-action]').click();
            assert.match(await widget.locator('[data-booking-status]').innerText(), /No reservation has been created/);
            // Composition invalidates the old total immediately; Done requests the new composition.
            await widget.locator('.aloha-booking-guests summary').click();
            await widget.getByRole('button', {name: 'Fewer adults', exact: true}).click();
            assert.equal(await widget.getByRole('button', {name: 'Fewer adults', exact: true}).isDisabled(), true);
            await widget.getByRole('button', {name: 'More adults', exact: true}).click();
            await widget.getByRole('button', {name: 'More children', exact: true}).click();
            assert.equal(await widget.locator('[data-guests="children"]').inputValue(), '1');
            if ([390,1440].includes(width)) await widget.screenshot({path: join(evidence, `booking-guests-${width}.png`)});
            assert.equal(await widget.locator('[data-booking-price]').isVisible(), false);
            mode = 'partial'; await widget.locator('[data-guests-done]').click();
            await widget.locator('[data-booking-status]').getByText('Detailed price breakdown is temporarily unavailable.', {exact: true}).waitFor();
            await widget.screenshot({path: join(evidence, `booking-partial-${width}.png`)});
            // Error never retains the previous confirmed amount.
            mode = 'unavailable'; await widget.locator('.aloha-booking-guests summary').click();
            await widget.locator('[data-guests="adults"]').fill('3'); await widget.locator('[data-guests-done]').click();
            await widget.getByText('These dates are unavailable. Choose another stay.', {exact: true}).waitFor();
            assert.equal(await widget.locator('[data-booking-price]').isVisible(), false);
            await widget.screenshot({path: join(evidence, `booking-unavailable-${width}.png`)});
            mode = 'stale'; await widget.getByRole('button', {name: 'Retry', exact: true}).click();
            await widget.getByText('Stale estimate. Please retry.', {exact: true}).waitFor();
            assert.equal(await widget.locator('[data-booking-price]').isVisible(), false);
            mode = 'reconciled'; await widget.getByRole('button', {name: 'Retry', exact: true}).click();
            await widget.locator('[data-total-amount]').getByText(/4,542/).waitFor();
            // Old delayed response must not reappear after another guest edit.
            priceDelay = 450; await widget.locator('.aloha-booking-guests summary').click();
            await widget.locator('[data-guests="children"]').fill('2'); await widget.locator('[data-guests-done]').click();
            await widget.locator('.aloha-booking-guests summary').click(); await widget.locator('[data-guests="children"]').fill('3');
            await page.waitForTimeout(550); assert.equal(await widget.locator('[data-booking-price]').isVisible(), false);
            await widget.locator('[data-guests-done]').click(); await widget.locator('[data-total-amount]').getByText(/4,542/).waitFor();
            // Calendar failure and retry have explicit feedback.
            calendarError = true; await widget.locator('[data-booking-dates="in"]').click();
            await dialog.getByText('Calendar temporarily unavailable. Please retry.', {exact: true}).waitFor();
            assert.equal(await dialog.locator('.flatpickr-day:not(.flatpickr-disabled)').count(), 0);
            await dialog.screenshot({path: join(evidence, `calendar-error-${width}.png`)});
            calendarError = false; await dialog.getByRole('button', {name: 'Retry', exact: true}).click();
            await dialog.locator('[data-calendar-loading]').waitFor({state: 'hidden'});
            await page.keyboard.press('Escape'); assert.equal(await dialog.isVisible(), false);
            assert.equal(await widget.locator('[data-booking-dates="in"]').evaluate(el => el === document.activeElement), true);
            const layout = await page.evaluate(() => {
                const w = document.querySelector('.aloha-rental-booking'), h = document.querySelector('.aloha-site-header');
                return {overflow: document.documentElement.scrollWidth > innerWidth, position: getComputedStyle(w).position,
                    offset: parseFloat(getComputedStyle(w).top), header: h.getBoundingClientRect().height, cls: window.fixtureCLS};
            });
            assert.equal(layout.overflow, false); assert.equal(layout.position, width < 768 ? 'static' : 'sticky');
            if (width >= 768) assert.ok(Math.abs(layout.offset - layout.header - 20) < 1);
            if (width >= 1024) {
                await page.evaluate(() => window.scrollTo(0, document.querySelector('.aloha-rental-body').offsetTop + 600));
                const geometry = await widget.evaluate(el => ({top: el.getBoundingClientRect().top, expected: parseFloat(getComputedStyle(el).top)}));
                assert.ok(Math.abs(geometry.top - geometry.expected) < 2, 'Actual sticky position');
                await page.screenshot({path: join(evidence, `booking-sticky-${width}.png`)});
            }
            assert.deepEqual(errors, []); report.push({width, priceCalls, ...layout}); await context.close();
        }
        writeFileSync(join(evidence, 'browser-results.json'), JSON.stringify(report, null, 2));
        console.log('PASS: booking widget 320/390/768/1024/1440, calendar, estimates/details/partial/error/retry, races, guests, focus, sticky and overflow.');
        console.log(JSON.stringify(report));
    } finally { if (fixture) wp('rental-template-browser-fixture.php', 'remove', key); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
