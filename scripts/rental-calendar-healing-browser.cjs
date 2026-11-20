/* Synthetic browser regression for the existing price-details error/calendar response. */
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088', key = 'm24-fixture-' + randomBytes(8).toString('hex');
const evidence = process.env.HEALING_SCREENSHOTS || '.local/evidence/m2-4c1e-healing';
function wp(file, ...args) { const r = spawnSync('./bin/local', ['wp', 'eval-file', '/var/www/m24-tests/' + file, ...args], {encoding: 'utf8', timeout: 60000}); assert.equal(r.status, 0, r.stdout + r.stderr); return r.stdout.trim(); }
(async () => {
    const browser = await chromium.launch(); let fixture; const results = [];
    try {
        fixture = JSON.parse(wp('rental-template-browser-fixture.php', 'create', key)); wp('rental-booking-fixture.php', key, 'range'); mkdirSync(evidence, {recursive: true});
        for (const width of [320, 390, 768, 1024, 1440]) {
            const context = await browser.newContext({viewport: {width, height: 1000}, reducedMotion: 'reduce'});
            let mode = 'price', prices = 0, availability = 0, calendar, navigations = 0;
            await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            await context.route('**/aloha-bridge/v1/rentals/*/*', async route => {
                const url = new URL(route.request().url()), q = url.searchParams; assert.equal(route.request().method(), 'GET');
                if (url.pathname.endsWith('/availability')) { availability++; throw new Error('Unexpected calendar REST'); }
                prices++; const requested = mode;
                if (requested === 'slow-heal') await new Promise(resolve => setTimeout(resolve, 300));
                if (['heal', 'slow-heal', 'invalid'].includes(requested)) {
                    const next = structuredClone(calendar); next.days.find(day => day.date === '2027-01-30').status = 'unavailable'; next.days.find(day => day.date === '2027-01-30').reason = 'already_booked';
                    if (requested === 'invalid') next.rentalId = 999999;
                    return route.fulfill({status: 422, json: {version: 1, status: 'unavailable', code: 'availability_changed', message: 'These dates are no longer available. Please choose new dates.', bookingEnabled: false, calendarUpdated: true, calendar: next}});
                }
                if (requested === 'failed') return route.fulfill({status: 503, json: {status: 'unavailable', code: 'calendar_refresh_failed', message: 'Availability changed, but the calendar could not be updated. Please retry the check.'}});
                return route.fulfill({json: {version: 1, rentalId: fixture.public.id, status: 'partial', currency: 'USD', estimatedTotal: '4121.44', items: [], notices: [],
                    marketing: {percent: 15, amount: '618', displayOnly: true, comparisonApproved: false}, dates: {checkIn: q.get('checkIn'), checkOut: q.get('checkOut')},
                    guests: {adults: +q.get('adults'), children: +q.get('children')}, expiresAt: new Date(Date.now() + 60000).toISOString()}});
            });
            const page = await context.newPage(), errors = []; page.on('pageerror', e => errors.push(e.message)); page.on('request', request => { if (request.isNavigationRequest() && request.frame() === page.mainFrame()) navigations++; });
            await page.goto(fixture.public.url); const widget = page.locator('.aloha-rental-booking'), dialog = page.locator('.aloha-booking-calendar'); await widget.scrollIntoViewIfNeeded();
            calendar = JSON.parse(await widget.getAttribute('data-booking-config')).calendar;
            const initialNavigations = navigations;
            const visible = () => page.evaluate(() => { const p = document.querySelector('[data-calendar-input]')._flatpickr; return p.currentYear * 12 + p.currentMonth; });
            async function month(target) { for (let i = 0; i < 15; i++) { const current = await visible(); if (current === target) return; await dialog.locator(current < target ? '.flatpickr-next-month' : '.flatpickr-prev-month').click(); } throw new Error('Month not found'); }
            const cell = value => dialog.locator(`.flatpickr-day[data-date="${value}"]:not(.prevMonthDay):not(.nextMonthDay)`);
            async function guests(n) { await widget.locator('.aloha-booking-guests summary').click(); await widget.locator('[data-guests="adults"]').fill(String(n)); await widget.locator('[data-guests-done]').click(); }
            async function shot(name) { if ([390, 1440].includes(width)) { await widget.evaluate(el => window.scrollTo(0, Math.max(0, scrollY + el.getBoundingClientRect().top - parseFloat(el.style.getPropertyValue('--aloha-booking-offset'))))); await widget.screenshot({path: join(evidence, `${name}-${width}.png`)}); } }
            await widget.locator('[data-booking-dates="in"]').click(); await month(2027 * 12); await cell('2027-01-28').click(); if (width < 768) await dialog.locator('.flatpickr-next-month').click(); await cell('2027-02-05').click();
            await widget.locator('[data-total-amount]').getByText('$4,121.44', {exact: true}).waitFor(); const selectedMonth = await visible();
            mode = 'heal'; await guests(3); await widget.getByText('These dates are no longer available. Please choose new dates.', {exact: true}).waitFor();
            assert.equal(await widget.locator('[data-check-in]').innerText(), 'Add date'); assert.equal(await widget.locator('[data-check-out]').innerText(), 'Add date');
            assert.equal(await widget.locator('[data-booking-price]').isVisible(), false); assert.equal(await widget.locator('[data-marketing-amount]').count(), 0); assert.equal(await widget.locator('[data-action-text]').innerText(), 'Check availability');
            assert.equal(prices, 2); assert.equal(availability, 0); await shot('cleared');
            await widget.locator('[data-booking-dates="in"]').click(); assert.equal(await visible(), selectedMonth, 'Visible month preserved through healing and reopening');
            await month(2027 * 12); assert.equal(await cell('2027-01-30').evaluate(el => el.classList.contains('aloha-unavailable')), true); assert.match(await cell('2027-01-30').getAttribute('title'), /Unavailable/);
            if ([390, 1440].includes(width)) await dialog.screenshot({path: join(evidence, `calendar-${width}.png`)});
            assert.equal(prices, 2); assert.equal(availability, 0);
            mode = 'price'; await cell('2027-01-07').click(); await cell('2027-01-12').click(); await widget.locator('[data-total-amount]').getByText('$4,121.44', {exact: true}).waitFor();
            const dates = await widget.locator('[data-booking-heading]').innerText();
            mode = 'failed'; await guests(4); await widget.getByText('Availability changed, but the calendar could not be updated. Please retry the check.', {exact: true}).waitFor();
            assert.equal(await widget.locator('[data-booking-heading]').innerText(), dates); assert.equal(await widget.locator('[data-booking-price]').isVisible(), false); assert.equal(await widget.locator('[data-action-text]').innerText(), 'Retry');
            mode = 'invalid'; await widget.locator('[data-booking-action]').click(); await widget.getByText('Calendar could not be updated. Please retry the check.', {exact: true}).waitFor(); assert.equal(await widget.locator('[data-booking-heading]').innerText(), dates);
            mode = 'slow-heal'; await widget.locator('[data-booking-action]').click(); mode = 'price'; await guests(5); await widget.locator('[data-total-amount]').getByText('$4,121.44', {exact: true}).waitFor(); await page.waitForTimeout(350);
            assert.equal(await widget.locator('[data-booking-heading]').innerText(), dates, 'Stale healing response cannot erase a newer estimate'); assert.equal(await widget.locator('[data-marketing-amount]').innerText(), '$618');
            assert.equal(navigations, initialNavigations); assert.equal(availability, 0); assert.deepEqual(errors, []); const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth); assert.equal(overflow, false);
            results.push({width, prices, availability, navigations, overflow}); await context.close();
        }
        writeFileSync(join(evidence, 'results.json'), JSON.stringify(results, null, 2)); console.log('PASS: healed calendar without reload/REST, cleared dates/total/savings, preserved month, atomic errors and generation race at five widths.'); console.log(JSON.stringify(results));
    } finally { if (fixture) wp('rental-template-browser-fixture.php', 'remove', key); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
