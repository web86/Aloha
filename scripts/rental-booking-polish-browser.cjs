/* Local acceptance and before/after evidence. Run from the Aloha host checkout. */
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088', key = 'm24-fixture-' + randomBytes(8).toString('hex');
const baseline = process.env.BOOKING_POLISH_BASELINE === '1';
const evidence = process.env.BOOKING_POLISH_SCREENSHOTS || '.local/evidence/m2-4c1c';
function wp(file, ...args) {
    const result = spawnSync('./bin/local', ['wp', 'eval-file', '/var/www/m24-tests/' + file, ...args], {encoding: 'utf8', timeout: 60000});
    assert.equal(result.status, 0, result.stdout + result.stderr); return result.stdout.trim();
}
const add = (value, days) => { const d = new Date(value + 'T12:00:00Z'); d.setUTCDate(d.getUTCDate() + days); return d.toISOString().slice(0, 10); };
(async () => {
    const browser = await chromium.launch(); let fixture; const results = [];
    try {
        fixture = JSON.parse(wp('rental-template-browser-fixture.php', 'create', key));
        wp('rental-booking-fixture.php', key, 'range'); mkdirSync(evidence, {recursive: true});
        for (const width of baseline ? [390, 1440] : [320, 390, 768, 1024, 1440]) {
            const context = await browser.newContext({viewport: {width, height: 1000}, reducedMotion: 'no-preference'});
            let calls = 0, availability = 0, currency = 'USD', mode = 'reconciled', amount = '618';
            await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            await context.route('**/aloha-bridge/v1/rentals/*/*', async route => {
                const url = new URL(route.request().url()), q = url.searchParams;
                assert.equal(route.request().method(), 'GET');
                if (url.pathname.endsWith('/availability')) { availability++; throw new Error('Unexpected calendar request'); }
                calls++; const requested = {currency, mode, amount};
                await new Promise(resolve => setTimeout(resolve, 180));
                if (requested.mode === 'error') return route.fulfill({status: 422, json: {status: 'unavailable', message: 'These dates are unavailable. Choose another stay.'}});
                return route.fulfill({json: {version: 1, rentalId: fixture.public.id, status: requested.mode, currency: requested.currency,
                    estimatedTotal: '4121.44', dates: {checkIn: q.get('checkIn'), checkOut: q.get('checkOut')},
                    guests: {adults: Number(q.get('adults')), children: Number(q.get('children'))},
                    items: [{kind: 'accommodation', label: 'Accommodation', amount: '3600.00'}, {kind: 'discount', label: 'Weekly stay discount', amount: '-50.00'}, {kind: 'cleaning', label: 'Cleaning fee', amount: '350.00'}, {kind: 'tax', label: 'Taxes', amount: '221.44'}],
                    marketing: {percent: 15, amount: requested.amount, displayOnly: true, comparisonApproved: false},
                    notices: ['Estimated total. Final amount is confirmed during booking.', ...(requested.mode === 'partial' ? ['Detailed price breakdown is temporarily unavailable.'] : [])],
                    expiresAt: new Date(Date.now() + 60000).toISOString(), bookingEnabled: false}});
            });
            const page = await context.newPage(), errors = []; page.on('pageerror', error => errors.push(error.message));
            await page.addInitScript(() => { window.polishCLS = 0; new PerformanceObserver(list => { for (const entry of list.getEntries()) if (!entry.hadRecentInput) window.polishCLS += entry.value; }).observe({type: 'layout-shift', buffered: true}); });
            await page.goto(fixture.public.url); await page.evaluate(() => document.fonts.ready);
            const widget = page.locator('.aloha-rental-booking'), dialog = page.locator('.aloha-booking-calendar');
            await widget.scrollIntoViewIfNeeded();
            const today = JSON.parse(await widget.getAttribute('data-booking-config')).today;
            const futureDate = new Date(today + 'T12:00:00Z'); futureDate.setUTCDate(1); futureDate.setUTCMonth(futureDate.getUTCMonth() + 4);
            const future = futureDate.toISOString().slice(0, 7) + '-';
            const cell = value => dialog.locator(`.flatpickr-day[data-date="${value}"]:not(.prevMonthDay):not(.nextMonthDay)`);
            const visible = () => page.evaluate(() => { const p = document.querySelector('[data-calendar-input]')._flatpickr; return p.currentYear * 12 + p.currentMonth; });
            async function month(value) {
                const d = new Date(value + 'T12:00:00Z'), target = d.getUTCFullYear() * 12 + d.getUTCMonth();
                for (let i = 0; i < 15; i++) { const current = await visible(); if (current === target) return; await dialog.locator(current < target ? '.flatpickr-next-month' : '.flatpickr-prev-month').click(); }
                throw new Error('Month not reached');
            }
            const shot = name => widget.screenshot({path: join(evidence, `${baseline ? 'before' : 'after'}-${name}-${width}.png`)});
            await shot('initial');
            if (!baseline) {
                assert.equal(await widget.locator('[data-booking-status]').isVisible(), false);
                assert.equal(await widget.getByText('You won’t be charged yet', {exact: true}).isVisible(), false);
                assert.equal(await widget.locator('.aloha-booking-save').count(), 1);
                assert.equal(await widget.locator('.aloha-booking-save').innerText(), 'Book direct and save 15% compared to Airbnb');
                assert.equal(await widget.getByText('Online reservations and payments are not enabled yet.', {exact: true}).count(), 0);
                const geometry = await widget.evaluate(root => {
                    const heading = root.querySelector('[data-booking-heading]'), instant = root.querySelector('.aloha-booking-instant');
                    const markers = [heading, instant].map(el => { const marker = document.createElement('i'); marker.style.cssText = 'display:inline-block;width:0;height:0;padding:0;margin:0;border:0'; el.append(marker); return marker; });
                    const baselines = markers.map(el => el.getBoundingClientRect().bottom); markers.forEach(el => el.remove());
                    const controls = [...root.querySelectorAll('[data-booking-dates],.aloha-booking-guests summary')].map(el => ({height: el.getBoundingClientRect().height, padding: getComputedStyle(el).paddingTop}));
                    return {baselines, controls, status: root.querySelector('[data-booking-status]').getBoundingClientRect().height, gap: root.querySelector('[data-booking-action]').getBoundingClientRect().top - root.querySelector('.aloha-booking-fields').getBoundingClientRect().bottom};
                });
                assert.ok(geometry.controls.every(control => Math.abs(control.height - 72) < 1 && control.padding === '12px'), JSON.stringify(geometry));
                assert.equal(geometry.status, 0); assert.ok(geometry.gap >= 10 && geometry.gap <= 16);
                if (width >= 768) assert.ok(Math.abs(geometry.baselines[0] - geometry.baselines[1]) < 1, JSON.stringify(geometry));
            }
            await widget.locator('[data-booking-dates="in"]').click(); await month(future + '01');
            if (!baseline) {
                assert.equal(await cell(future + '03').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(229, 245, 233)');
                assert.equal(await cell(future + '03').evaluate(el => getComputedStyle(el).cursor), 'not-allowed');
                assert.equal(await cell(future + '03').getAttribute('title'), 'Min 3 nights');
                // Selection must not call any flatpickr navigation/rebuild method or replace day nodes.
                await page.evaluate(() => { const p = document.querySelector('[data-calendar-input]')._flatpickr; window.selectionCalls = []; for (const key of ['clear', 'jumpToDate', 'changeMonth', 'redraw']) { const original = p[key]; p[key] = function(...args) { window.selectionCalls.push(key); return original.apply(p, args); }; } });
            }
            const start = future + '15', end = add(start, 3), previousMonth = await visible();
            await cell(start).click();
            await dialog.screenshot({path: join(evidence, `${baseline ? 'before' : 'after'}-check-in-${width}.png`)});
            if (!baseline) {
                const node = await cell(start).elementHandle();
                await cell(start).click(); assert.equal(await cell(start).evaluate((el, old) => el === old, node), true);
                assert.equal(await widget.locator('[data-check-in]').innerText(), 'Add date'); assert.equal(await dialog.isVisible(), true);
                assert.equal(await visible(), previousMonth); assert.deepEqual(await page.evaluate(() => window.selectionCalls), []);
                await cell(start).click(); await cell(start).focus(); await page.keyboard.press('Enter');
                assert.equal(await widget.locator('[data-check-in]').innerText(), 'Add date');
                await cell(start).click(); await cell(start).focus(); await page.keyboard.press('Space');
                assert.equal(await widget.locator('[data-check-in]').innerText(), 'Add date');
                await cell(start).dispatchEvent('click', {bubbles: true});
                await cell(start).dispatchEvent('click', {bubbles: true});
                assert.equal(await widget.locator('[data-check-in]').innerText(), 'Add date');
                await cell(start).click(); assert.equal(await visible(), previousMonth); assert.equal(calls, 0);
                assert.deepEqual(await page.evaluate(() => window.selectionCalls), []);
                if (width >= 768) {
                    await cell(start).click();
                    const secondDate = new Date(start + 'T12:00:00Z'); secondDate.setUTCMonth(secondDate.getUTCMonth() + 1);
                    const second = secondDate.toISOString().slice(0, 10), originalNode = await cell(second).elementHandle();
                    await cell(second).click();
                    assert.equal(await visible(), previousMonth, 'Second-month arrival does not move either month');
                    assert.equal(await cell(second).evaluate((element, original) => element === original, originalNode), true);
                    assert.deepEqual(await page.evaluate(() => window.selectionCalls), []);
                    await cell(second).click(); await cell(start).click();
                }
            }
            await cell(end).hover();
            await dialog.screenshot({path: join(evidence, `${baseline ? 'before' : 'after'}-range-${width}.png`)});
            await cell(end).click(); await widget.locator('[data-total-amount]').getByText(/4,121.44/).waitFor();
            await shot('total');
            await widget.locator('.aloha-booking-breakdown summary').click(); await shot('details');
            if (!baseline) {
                assert.equal(await widget.locator('[data-booking-status]').isVisible(), false);
                assert.equal(await widget.locator('[data-marketing-amount]').innerText(), '$618');
                assert.equal(await widget.locator('.aloha-booking-save').innerText(), 'You save $618 vs the same dates on Airbnb');
                assert.equal(await widget.getByText('Guest-specific pricing is confirmed during booking.', {exact: true}).count(), 0);
                assert.equal(await widget.getByText('You won’t be charged yet', {exact: true}).isVisible(), true);
                assert.match(await widget.locator('[data-price-lines]').innerText(), /Weekly stay discount/);
                assert.equal(await widget.locator('[data-total-amount]').innerText(), '$4,121.44');
                await widget.locator('[data-booking-action]').click(); assert.match(await widget.locator('[data-booking-status]').innerText(), /No reservation has been created/);
                // Cancelling a committed Check-in clears total and savings immediately.
                await widget.locator('[data-booking-dates="in"]').click(); await cell(start).click();
                assert.equal(await widget.locator('[data-booking-price]').isVisible(), false);
                assert.equal(await widget.locator('.aloha-booking-save').innerText(), 'Book direct and save 15% compared to Airbnb');
                assert.equal(await widget.getByText('You won’t be charged yet', {exact: true}).isVisible(), false);
                assert.equal(calls, 1);
                await cell(start).click(); await cell(end).click(); await widget.locator('[data-total-amount]').getByText(/4,121.44/).waitFor();
                // Guests and errors invalidate, never preserve an old marketing amount.
                await widget.locator('.aloha-booking-guests summary').click(); currency = 'EUR'; mode = 'partial';
                await widget.locator('[data-guests="adults"]').fill('3');
                assert.equal(await widget.locator('[data-marketing-amount]').count(), 0);
                await widget.locator('[data-guests-done]').click(); await widget.locator('[data-total-amount]').getByText(/4,121.44/).waitFor();
                assert.equal(await widget.locator('[data-marketing-amount]').innerText(), '€618');
                assert.equal(await widget.locator('[data-booking-status]').isVisible(), false);
                await widget.locator('.aloha-booking-breakdown summary').click(); assert.match(await widget.locator('[data-price-detail-note]').innerText(), /Some charges are not itemized yet/);
                mode = 'error'; await widget.locator('.aloha-booking-guests summary').click(); await widget.locator('[data-guests="adults"]').fill('4'); await widget.locator('[data-guests-done]').click();
                await widget.getByText('These dates are unavailable. Choose another stay.', {exact: true}).waitFor(); assert.equal(await widget.locator('[data-marketing-amount]').count(), 0);
                mode = 'reconciled'; amount = '987'; await widget.locator('[data-booking-action]').click(); await widget.locator('[data-marketing-amount]').getByText('€987', {exact: true}).waitFor();
                assert.equal(await widget.locator('[data-total-amount]').innerText(), '€4,121.44', 'Savings are taken from DTO, never subtracted/recomputed');
                // Only manual navigation animates, and reduced motion cancels that animation.
                await widget.locator('[data-booking-dates="in"]').click(); await dialog.locator('.flatpickr-next-month').click();
                const motion = await dialog.locator('.flatpickr-rContainer').evaluate(el => el.getAnimations().map(animation => animation.effect.getTiming().duration));
                assert.deepEqual(motion, [200]);
                await page.emulateMedia({reducedMotion: 'reduce'}); await dialog.locator('.flatpickr-prev-month').click();
                assert.equal(await dialog.locator('.flatpickr-rContainer').evaluate(el => el.getAnimations().length), 0);
                await page.emulateMedia({reducedMotion: 'no-preference'});
                await month(`${today.slice(0, 4)}-12-01`); await dialog.locator('.flatpickr-next-month').click();
                assert.deepEqual(await dialog.locator('.flatpickr-rContainer').evaluate(el => el.getAnimations().map(animation => animation.effect.getTiming().duration)), [200], 'Year and month hooks do not cancel the same navigation animation');
                await page.keyboard.press('Escape');
            }
            const layout = await page.evaluate(() => ({overflow: document.documentElement.scrollWidth > innerWidth, cls: window.polishCLS}));
            assert.equal(layout.overflow, false); assert.deepEqual(errors, []); assert.equal(availability, 0);
            results.push({width, calls, availability, ...layout}); await context.close();
        }
        if (!baseline) {
            const touch = await browser.newContext({viewport: {width: 390, height: 900}, hasTouch: true, isMobile: true});
            await touch.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            const page = await touch.newPage(); let network = 0; page.on('request', request => { if (request.url().includes('/aloha-bridge/v1/rentals/')) network++; });
            await page.goto(fixture.public.url); await page.locator('[data-booking-dates="in"]').tap();
            const day = page.locator('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)').first();
            const selected = await day.getAttribute('data-date'); await day.tap();
            await page.locator(`.flatpickr-day[data-date="${selected}"]:not(.prevMonthDay):not(.nextMonthDay)`).tap();
            assert.equal(await page.locator('[data-check-in]').innerText(), 'Add date');
            assert.equal(await page.locator('.aloha-booking-calendar').isVisible(), true); assert.equal(network, 0);
            await touch.close();
        }
        writeFileSync(join(evidence, baseline ? 'baseline-results.json' : 'polish-results.json'), JSON.stringify(results, null, 2));
        console.log('PASS: ' + (baseline ? 'before-state evidence' : 'booking polish, cancellation without navigation, availability colors, layout, DTO savings, Partial/errors/guests and reduced motion') + '.');
        console.log(JSON.stringify(results));
    } finally { if (fixture) wp('rental-template-browser-fixture.php', 'remove', key); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
