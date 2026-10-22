/* Local acceptance check. Run from the project root with PLAYWRIGHT_MODULE set.
 * Evidence and credentials stay in .local/ and .env; no npm/build pipeline.
 */
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:8088';
const evidence = process.env.EVIDENCE_DIR || '.local/evidence/m1-1';
fs.mkdirSync(evidence, { recursive: true });

async function geometry(page) {
	return page.evaluate(() => {
		const box = document.querySelector('#container-ruler').getBoundingClientRect();
		const main = document.querySelector('main').getBoundingClientRect();
		const style = el => {
			const s = getComputedStyle(el);
			return { family: s.fontFamily, size: s.fontSize, lineHeight: s.lineHeight, weight: s.fontWeight, color: s.color, background: s.backgroundColor };
		};
		return {
			viewport: innerWidth, scrollWidth: document.documentElement.scrollWidth,
			x: box.x, usable: box.width, outer: main.width,
			body: style(document.body), h1: style(document.querySelector('h1')), h2: style(document.querySelector('h2')),
			fonts: [...document.fonts].map(f => ({ family: f.family, style: f.style, weight: f.weight, display: f.display, status: f.status })),
			buttonHeights: [...document.querySelectorAll('.wp-block-button__link')].map(el => el.getBoundingClientRect().height),
			fields: [...document.querySelectorAll('input:not([type=checkbox]):not([type=radio]),select')].map(el => el.getBoundingClientRect().height)
		};
	});
}

(async () => {
	const browser = await chromium.launch({ headless: true });
	const report = { widths: [], javascriptErrors: [], consoleErrors: [], failedRequests: [], externalRequests: [], fontResponses: [], interaction: {} };
	try {
		const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
		const page = await context.newPage();
		page.on('pageerror', e => report.javascriptErrors.push(e.message));
		page.on('console', m => { if (m.type() === 'error') report.consoleErrors.push(m.text()); });
		page.on('requestfailed', r => report.failedRequests.push({ url: r.url(), error: r.failure()?.errorText }));
		page.on('request', r => { if (r.url().startsWith('http') && !r.url().startsWith(base + '/')) report.externalRequests.push(r.url()); });
		page.on('response', r => { if (r.url().includes('.woff2')) report.fontResponses.push({ url: r.url(), status: r.status() }); });
		for (const [width, x, usable] of [[320,20,280],[390,20,350],[768,40,688],[1024,40,944],[1440,120,1200]]) {
			await page.setViewportSize({ width, height: 1000 });
			const response = await page.goto(base + '/?aloha-style-showcase=1');
			assert.equal(response.status(), 200);
			assert.match(response.headers()['x-robots-tag'], /noindex/);
			await page.evaluate(() => document.fonts.ready);
			const metrics = await geometry(page);
			assert.equal(metrics.scrollWidth, width, `page overflow at ${width}`);
			assert.ok(Math.abs(metrics.x - x) < 0.2, `content x at ${width}`);
			assert.ok(Math.abs(metrics.usable - usable) < 0.2, `usable width at ${width}`);
			assert.equal(metrics.fonts.length, 3);
			assert.ok(metrics.fonts.every(f => f.status === 'loaded' && f.display === 'swap'));
			assert.ok(metrics.buttonHeights.every(h => h >= 52));
			assert.ok(metrics.fields.every(h => h >= 52));
			report.widths.push(metrics);
			await page.screenshot({ path: `${evidence}/showcase-${width}.png`, fullPage: true });
		}
		assert.equal(parseFloat(report.widths[1].h1.size), 52);
		assert.equal(parseFloat(report.widths[4].h1.size), 100);
		assert.ok(Math.abs(parseFloat(report.widths[1].h2.size) - 38) < .01);
		assert.equal(parseFloat(report.widths[4].h2.size), 60);
		const primary = page.locator('.wp-block-button__link').first();
		await primary.hover();
		report.interaction.hover = await primary.evaluate(el => ({ background: getComputedStyle(el).backgroundColor, color: getComputedStyle(el).color }));
		assert.equal(report.interaction.hover.background, 'rgb(245, 86, 0)');
		await page.mouse.down();
		report.interaction.active = await primary.evaluate(el => ({ background: getComputedStyle(el).backgroundColor, color: getComputedStyle(el).color }));
		assert.equal(report.interaction.active.background, 'rgb(22, 35, 42)');
		await page.mouse.up();
		const disabled = page.locator('button:disabled').first();
		assert.equal(await disabled.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(228, 238, 240)');
		await page.reload();
		await page.keyboard.press('Tab');
		assert.equal(await page.locator(':focus').textContent(), 'Skip to design system');
		await page.screenshot({ path: `${evidence}/keyboard-skip.png` });
		await page.keyboard.press('Enter');
		assert.equal(await page.locator(':focus').getAttribute('id'), 'main');
		report.interaction.tabOrder = [];
		for (let i = 0; i < 25; i++) {
			await page.keyboard.press('Tab');
			const focus = await page.evaluate(() => {
				const el = document.activeElement, s = getComputedStyle(el);
				return { tag: el.tagName, id: el.id, text: (el.textContent || '').trim().slice(0,70), visible: el.matches(':focus-visible'), outline: s.outlineStyle, outlineWidth: s.outlineWidth, disabled: el.disabled || el.getAttribute('aria-disabled') === 'true' };
			});
			if (focus.tag === 'BODY') break;
			assert.ok(focus.visible && focus.outline !== 'none' && parseFloat(focus.outlineWidth) >= 2);
			assert.ok(!focus.disabled);
			report.interaction.tabOrder.push(focus);
			if (focus.id === 'demo-name') await page.screenshot({ path: `${evidence}/keyboard-field.png` });
			if (focus.text === 'An underlined light link') await page.screenshot({ path: `${evidence}/keyboard-dark-surface.png` });
		}
		assert.ok(report.interaction.tabOrder.some(f => f.id === 'demo-name'));
		await page.locator('#demo-name').fill('Local style check');
		await page.locator('#demo-select').focus();
		// Native select type-ahead avoids dependence on the OS popup in headless mode.
		await page.keyboard.press('f');
		assert.equal(await page.locator('#demo-select').inputValue(), 'First sample');
		await page.locator('input[type=checkbox]').focus();
		await page.keyboard.press('Space');
		assert.ok(await page.locator('input[type=checkbox]').isChecked());
		assert.equal(await page.locator('form').count(), 0);
		await page.setViewportSize({ width: 320, height: 1000 });
		await page.evaluate(() => document.documentElement.style.fontSize = '200%');
		assert.equal((await geometry(page)).scrollWidth, 320, 'overflow at 200% text zoom');
		await page.screenshot({ path: `${evidence}/text-zoom-200.png`, fullPage: true });
		const fallback = await browser.newPage({ viewport: { width: 390, height: 1000 } });
		await fallback.route('**/*.woff2', route => route.abort());
		await fallback.goto(base + '/?aloha-style-showcase=1');
		await fallback.evaluate(() => document.fonts.ready);
		report.fallback = await geometry(fallback);
		assert.equal(report.fallback.scrollWidth, 390);
		assert.ok(report.fallback.fonts.every(f => f.status === 'error'));
		await fallback.screenshot({ path: `${evidence}/font-fallback-390.png`, fullPage: true });
		await fallback.close();
		assert.deepEqual(report.javascriptErrors, []);
		assert.deepEqual(report.consoleErrors, []);
		assert.deepEqual(report.failedRequests, []);
		assert.deepEqual(report.externalRequests, []);
		assert.ok(report.fontResponses.every(r => r.status === 200));
		report.scripts = await page.locator('script[src]').evaluateAll(nodes => nodes.map(n => n.src));
		fs.writeFileSync(`${evidence}/frontend.json`, JSON.stringify(report, null, 2) + '\n');
		console.log('Frontend: five widths, fonts, geometry, interaction, keyboard, 200% text zoom, font fallback PASS');
	} finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
