/* Local synthetic evidence; site language intentionally differs from browser language. */
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {mkdirSync, writeFileSync} = require('node:fs');
const {join} = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088', key = 'm24-fixture-' + randomBytes(8).toString('hex');
const baseline = process.env.PRICE_DETAILS_BASELINE === '1';
const evidence = process.env.PRICE_DETAILS_SCREENSHOTS || '.local/evidence/m2-4c1d';
function wp(file, ...args) {
    const result = spawnSync('./bin/local', ['wp', 'eval-file', '/var/www/m24-tests/' + file, ...args], {encoding: 'utf8', timeout: 60000});
    assert.equal(result.status, 0, result.stdout + result.stderr); return result.stdout.trim();
}
(async () => {
    const browser = await chromium.launch(); let fixture; const results = [];
    try {
        fixture = JSON.parse(wp('rental-template-browser-fixture.php', 'create', key));
        wp('rental-booking-fixture.php', key, 'range'); mkdirSync(evidence, {recursive: true});
        for (const width of baseline ? [390, 1440] : [320, 390, 768, 1024, 1440]) {
            const context = await browser.newContext({viewport: {width, height: 1000}, locale: 'ru-RU', reducedMotion: 'reduce'});
            let mode = 'reconciled', calls = 0, availability = 0;
            await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            if (baseline) for (const extension of ['js', 'css']) {
                const path = `theme/aloha-hello/assets/${extension}/rental-booking.${extension}`;
                const old = spawnSync('git', ['show', `325fb50:${path}`], {cwd: join(__dirname, '..'), encoding: 'utf8'});
                assert.equal(old.status, 0, old.stderr);
                await context.route(`**/rental-booking.${extension}*`, route => route.fulfill({contentType: extension === 'js' ? 'application/javascript' : 'text/css', body: old.stdout}));
            }
            await context.route('**/aloha-bridge/v1/rentals/*/*', route => {
                const url = new URL(route.request().url()), q = url.searchParams; assert.equal(route.request().method(), 'GET');
                if (url.pathname.endsWith('/availability')) { availability++; throw new Error('Unexpected calendar request'); }
                calls++; const count = (Date.parse(q.get('checkOut')) - Date.parse(q.get('checkIn'))) / 86400000;
                if (mode === 'error') return route.fulfill({status: 503, json: {status: 'unavailable', message: 'No confirmed price. Please try again.'}});
                return route.fulfill({json: {version: 1, rentalId: fixture.public.id, status: mode, currency: 'USD', estimatedTotal: '4542.11',
                    dates: {checkIn: q.get('checkIn'), checkOut: q.get('checkOut')}, guests: {adults: +q.get('adults'), children: +q.get('children')},
                    items: [{kind: 'accommodation', label: `${count} ${count === 1 ? 'night' : 'nights'} (rates vary by date)`, amount: '3238.00'}, {kind: 'cleaning', label: 'Cleaning fee', amount: '595.00'},
                        ...(mode === 'reconciled' ? [{kind: 'tax', label: 'TAT', amount: '421.63'}, {kind: 'tax', label: 'GET', amount: '172.49'}, {kind: 'tax', label: 'HI TAT', amount: '114.99'}] : [])],
                    // Deliberately old notices: new UI must not dump these even from a cached old DTO.
                    notices: [...(mode === 'partial' ? ['Detailed price breakdown is temporarily unavailable.'] : []), 'Estimated total. Final amount is confirmed during booking.', 'This estimate is not a guest-specific quote.'],
                    marketing: {percent: 15, amount: '681', displayOnly: true, comparisonApproved: false}, quotedForGuestCount: false, bookingEnabled: false, expiresAt: new Date(Date.now() + 60000).toISOString()}});
            });
            const page = await context.newPage(), errors = []; page.on('pageerror', error => errors.push(error.message));
            await page.addInitScript(() => { window.detailsCLS = 0; window.widgetCLS = 0; window.detailsShifts = []; new PerformanceObserver(list => { for (const entry of list.getEntries()) if (!entry.hadRecentInput) { window.detailsCLS += entry.value; if (entry.sources.some(source => source.node?.closest?.('.aloha-rental-booking'))) window.widgetCLS += entry.value; window.detailsShifts.push({value: entry.value, at: entry.startTime, sources: entry.sources.map(source => source.node?.className || source.node?.nodeName)}); } }).observe({type: 'layout-shift', buffered: true}); });
            await page.goto(fixture.public.url); await page.evaluate(() => document.fonts.ready);
            const widget = page.locator('.aloha-rental-booking'), dialog = page.locator('.aloha-booking-calendar'); await widget.scrollIntoViewIfNeeded();
            const heading = widget.locator('[data-booking-heading]'); const initial = await heading.innerHTML();
            const year = Number(JSON.parse(await widget.getAttribute('data-booking-config')).today.slice(0, 4)) + 1;
            const shot = async name => {
                await widget.evaluate(el => window.scrollTo(0, Math.max(0, scrollY + el.getBoundingClientRect().top - parseFloat(el.style.getPropertyValue('--aloha-booking-offset')))));
                await widget.screenshot({path: join(evidence, `${baseline ? 'before' : 'after'}-${name}-${width}.png`)});
            };
            async function select(start, end) {
                await widget.locator('[data-booking-dates="in"]').click();
                const targetDate = new Date(start + 'T12:00:00Z'), target = targetDate.getUTCFullYear() * 12 + targetDate.getUTCMonth();
                for (let i = 0; i < 15; i++) {
                    const current = await page.evaluate(() => { const p = document.querySelector('[data-calendar-input]')._flatpickr; return p.currentYear * 12 + p.currentMonth; });
                    if (current === target) break;
                    await dialog.locator(current < target ? '.flatpickr-next-month' : '.flatpickr-prev-month').click();
                }
                const cell = value => dialog.locator(`.flatpickr-day[data-date="${value}"]:not(.prevMonthDay):not(.nextMonthDay)`);
                await cell(start).click();
                if ((await cell(end).count()) === 0) await dialog.locator('.flatpickr-next-month').click();
                await cell(end).click(); await widget.locator('[data-total-amount]').getByText(/4.*542/).waitFor();
            }
            await select(`${year}-01-29`, `${year}-02-02`); await shot('period');
            await widget.locator('.aloha-booking-breakdown summary').click(); const beforeDetails = calls; await shot('reconciled'); assert.equal(calls, beforeDetails);
            if (!baseline) {
                assert.equal(await heading.locator('strong').innerText(), 'Jan 29 – Feb 2');
                assert.equal(await heading.locator('span').innerText(), '· 4 nights');
                assert.equal(await heading.getAttribute('class'), 'aloha-booking-period');
                assert.equal(await heading.locator('strong').evaluate(el => getComputedStyle(el).fontSize), '22px');
                assert.equal(await heading.locator('span').evaluate(el => getComputedStyle(el).fontSize), '16px');
                assert.equal(await widget.locator('[data-price-detail-note]').isVisible(), false);
                assert.equal(await widget.getByText(/not a guest-specific quote|Final amount is confirmed|Detailed price breakdown/).count(), 0);
                assert.match(await widget.locator('[data-price-lines]').innerText(), /TAT/);
                assert.equal(await widget.locator('[data-marketing-amount]').innerText(), '$681');
                // Locale belongs to page, even when the browser uses Russian.
                assert.equal(await page.evaluate(() => navigator.language), 'ru-RU');
                assert.equal(await page.locator('html').getAttribute('lang'), 'en-US');
            }
            mode = 'partial'; await widget.locator('.aloha-booking-guests summary').click(); await widget.locator('[data-guests="adults"]').fill('3'); await widget.locator('[data-guests-done]').click();
            await widget.locator('[data-total-amount]').getByText(/4.*542/).waitFor(); await widget.locator('.aloha-booking-breakdown summary').click(); await shot('partial');
            if (!baseline) {
                assert.equal(await widget.locator('[data-price-detail-note]').innerText(), 'Some charges are not itemized yet');
                assert.equal(await widget.locator('[data-booking-status]').isVisible(), false);
                assert.equal(await widget.locator('[data-price-lines] dt').filter({hasText: /TAT|GET/}).count(), 0);
                assert.equal(await widget.locator('[data-marketing-amount]').innerText(), '$681');
                mode = 'reconciled';
                await select(`${year}-01-18`, `${year}-01-22`); assert.equal(await heading.locator('strong').innerText(), 'Jan 18 – 22');
                await select(`${year}-03-05`, `${year}-03-06`); assert.equal(await heading.locator('span').innerText(), '· 1 night');
                await select(`${year - 1}-12-30`, `${year}-01-03`);
                assert.equal(await heading.locator('strong').innerText(), `Dec 30, ${year - 1} – Jan 3, ${year}`); await shot('year-boundary');
                // Simulate the page locale selected by a language plugin; keep browser locale Russian.
                await context.route(fixture.public.url, async route => {
                    const response = await route.fetch(); let html = await response.text();
                    html = html.replace('lang="en-US"', 'lang="fr-FR"');
                    html = html.replace(/(<script[^>]+id="aloha-rental-booking-js")/, '<script>wp.i18n.setLocaleData({"":{"domain":"aloha-hello","lang":"fr_FR","plural-forms":"nplurals=2; plural=(n > 1);"},"%s night":["%s nuit","%s nuits"]}, "aloha-hello");</script>$1');
                    await route.fulfill({response, body: html});
                });
                await page.reload(); await select(`${year}-01-29`, `${year}-02-02`);
                assert.equal(await heading.locator('strong').innerText(), '29 janv. – 2 févr.'); assert.equal(await heading.locator('span').innerText(), '· 4 nuits'); await shot('fr-period');
                mode = 'error'; await widget.locator('.aloha-booking-guests summary').click(); await widget.locator('[data-guests="adults"]').fill('4'); await widget.locator('[data-guests-done]').click();
                await widget.getByText('No confirmed price. Please try again.', {exact: true}).waitFor();
                assert.equal(await widget.locator('[data-booking-price]').isVisible(), false); assert.equal(await widget.locator('[data-marketing-amount]').count(), 0);
                await widget.locator('[data-booking-dates="in"]').click();
                await dialog.locator(`.flatpickr-day[data-date="${year}-01-29"]:not(.prevMonthDay):not(.nextMonthDay)`).click();
                assert.equal(await heading.innerHTML(), initial); assert.equal(await heading.getAttribute('class'), '');
            }
            assert.equal(availability, 0); assert.deepEqual(errors, []);
            const layout = await page.evaluate(() => ({overflow: document.documentElement.scrollWidth > innerWidth, cls: window.detailsCLS, widgetCLS: window.widgetCLS, shifts: window.detailsShifts})); assert.equal(layout.overflow, false); assert.equal(layout.widgetCLS, 0);
            results.push({width, calls, availability, ...layout}); await context.close();
        }
        writeFileSync(join(evidence, baseline ? 'baseline-results.json' : 'results.json'), JSON.stringify(results, null, 2));
        console.log('PASS: ' + (baseline ? 'date header/details baseline' : 'site date locale/typography, month/year/singular, reconciled taxes, compact Partial and error/savings regression') + '.'); console.log(JSON.stringify(results));
    } finally { if (fixture) wp('rental-template-browser-fixture.php', 'remove', key); await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
