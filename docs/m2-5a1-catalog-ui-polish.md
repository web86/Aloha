# M2.5A.1 — Catalog UI polish

## Pre-change audit (2026-11-21)

Bases verified: Aloha `35438dab0c89b97191abd6617c15f46d02325727`, Bridge `4bc70bb9fed63735a8b778c718d8b31382fdf6a3`. Design repo main `3af4cdc181a7e0d599d7fcefb2544f332a0d8e05`; reviewed README, exact HTML styles/assets and both PDF pages. A fresh 1440/768/390 capture and computed DOM measurements were taken before source changes. Existing dirty root checkouts are preserved. Implementation uses isolated `codex/m2-5a1-catalog-ui-polish`; Bridge remains unchanged.

| Priority | Element | Fresh M2.5A measurement / discrepancy | Design target / action |
| --- | --- | --- | --- |
| 1 | Filter panel | 1200 × 105.59, padding 14/16/14/24, radius 38; exposed bordered numeric fields | Unified white panel, 6/6/6/8 padding, radius 40, exact shadow and short separators |
| 1 | Search | 52px high, 16px black label, global button border/padding win | 56px, white 19px/700, 0 32px; explicit scoped states |
| 1 | Guests | Two permanent inputs, no summary or disclosure | One summary control, accessible local stepper popover; native GET inputs without JS |
| 1 | Bedrooms | Four visible 44px chips including ANY | Three visual 34px chips, 40px minimum width, 44px touch area and reset |
| 1 | Sticky | Filter not sticky | Local sticky wrapper below actual header/admin bar; responsive popover bounds |
| 2 | Heading | 66.24px/68.89px, y184.8; extra 24px WP main flow margin | 72px/1 at 1440; remove catalog-only flow offset, preserve shared header |
| 2 | Results/chips | Recommended plain 12px text; chips 12px | Sort pill with native accessible select, 15px; chips 14px and close icon |
| 2 | Cards | Title 30px, USP16/facts13; arrows visibly44; 24px horizontal gap | Title26, USP/facts15; visual34 with 44 hit target, 22px gap, 40px row gap |
| 2 | Map | 527.28px column (~44%), gap40 already correct; radius10; no preview/zoom UI | Retain measured columns, radius12, compact house/price pins, selection/preview, working zoom, disabled movement search |
| 3 | Help | Padding32, radius8, h2=32; no portrait data | Padding28/32, radius10, heading30, neutral team icon (no invented person), outline disabled CTA |
| 3 | Benefits | 90px top spacing, 20px headings | 110px top, 40px columns, 22px headings and exact lines/text spacing |

Reference artboard numbers, faces, tags, dates, promotions and coordinates are not rental content. Only real local public data is rendered. Date and movement searches remain explicitly disabled. Current Oʻahu public point is preserved.

## Delivered changes

Only the theme changed. The existing dynamic catalog block, shared Rental card renderer, Embla module, GET/History filtering and native archive remain in place. No new endpoint, dependency, build step or Bridge DTO was introduced.

- Filter: exact white/radius40/shadow/padding tokens, short separators, normalized native Area select with SVG arrow, disabled Add dates/calendar control and coming-soon text. A scoped catalog main rule removes the extra WordPress flow margin.
- Guests: progressive enhancement of the same two GET inputs. The summary opens an accessible non-modal popover with 44px stepper buttons, 42px value fields, input clamping, Done, Escape, outside/focus-out closing and focus restoration. Position is bounded to the viewport, including short/visual viewport changes. Without JS the labelled numeric inputs remain visible and submit through GET.
- Bedrooms: three visible chips; a hidden reset radio preserves the existing empty value. Pointer repeat-selection and Space can reset the chosen chip; Clear all and History update explicit checked properties across engines. Visual height is 34px, radio hit height 44px. Desktop widths follow the measured reference; narrow layouts retain the requested 40px minimum and avoid overlapping Search.
- Results: native Recommended-only sort select in a pill; future price choices are disabled. Map toggle preserves scroll and the existing responsive image sizing. Active chips have close affordances and scoped hover/focus/active states.
- Cards: existing renderer unchanged. Catalog-only type, spacing, hover scale and arrow styling; arrows have a 34px visual circle inside a 44px button. Card row spacing is implemented through the existing subgrid contract rather than introducing gaps inside each card. No feature tags are invented; an unused styling hook is ready for confirmed future chips.
- Map: same deferred Google loader, key/Map ID, privacy/GPC and fallback policy. House/price pins, dark selection, bounded local preview and native Map-instance zoom controls. Price uses the existing available minimum; preview uses the first already-normalized local card image and confirmed facts. Optional marker collision priority favors the selected pin, then matches, then neutral points; coordinates are never shifted. Movement search is visibly disabled. Selection does not fetch provider data, pan-search or synchronize the cards.
- Lower sections: editable Gutenberg copy retained. Help uses a neutral team icon instead of inventing a portrait/person, and the previously approved disabled outline CTA. Typography, spacing, radii and three-column benefits follow the reference. Shared header/footer and all Single Rental styles were preserved.

### Files

Modified:

- `theme/aloha-hello/inc/rental-catalog.php`
- `theme/aloha-hello/templates/archive-rental.html`
- `theme/aloha-hello/assets/css/rental-catalog.css`
- `theme/aloha-hello/assets/js/rental-catalog.js`
- `theme/aloha-hello/assets/js/rental-catalog-map.js`
- `scripts/rental-catalog-browser.cjs` (only opens/closes Guests around its existing fill steps)

Added:

- `scripts/rental-catalog-ui-browser.cjs` — read-only geometry/interaction suite, defaults to Chromium/Firefox; `CATALOG_UI_ENGINES` can select usable installed engines.
- `scripts/rental-catalog-visual.cjs` — screenshots of real current public data and real Google Maps, without seeding or source writes.
- This audit/report.

Bridge stays clean at `4bc70bb9fed63735a8b778c718d8b31382fdf6a3`.

## Final before/after geometry (1440px Chromium)

| Element | Before | Updated | Reference / intentional difference |
| --- | --- | --- | --- |
| Panel width × height | 1200 × 105.59 | 1200 × 89.19 | HTML reference 1200 × 89.19 |
| Panel radius/padding | 38; 14/16/14/24 | 40; 6/6/6/8 | Matches reference |
| Panel shadow | 0 7 24, alpha .047 | 0 6 24, alpha .08 | Matches reference |
| H1 size / line height | 66.24 / 68.89 | 72 / 72 | Matches reference at 1440 |
| H1 top | 184.80 | 160.39 | Reference 160.39 |
| Search height / size / weight | 52 / 16 / 600 | 56 / 19 / 700 | Reference target |
| Search width / horizontal padding | 108.39 / 28 | 126.86 / 32 | Reference 126.86 / 32 |
| Search label | Dark | White | Reference target |
| Bedrooms | Four visible, 44px | Three, 34px visual / 44px hit | No visible ANY; requested accessibility preserved |
| Sort | Plain text, 12px | Native select pill, 15px / 44px height | Same stable Recommended order |
| Card title / USP / facts | 30 / 16 / 13px | 26 / 15 / 15px | Reference type sizes |
| Card horizontal / row spacing | 24 / 36px | 22 / 40px | Existing subgrid component retained |
| Carousel arrow | Visible 44px | Visible 34px / hit 44px | Reference look with usable touch area |
| Map width / layout gap | 527.28 / 40px | 527.28 / 40px | Existing ~44% map column preserved |
| Map radius | 10px | 12px | Reference |
| Help radius / heading | 8 / 32px | 10 / 30px | Neutral 80px icon substitutes unconfirmed portrait |
| Benefits top / columns gap | 90 / default WP gap | 110 / 40px | Reference spacing |

Box-sizing differences in the exported reference were measured, not copied blindly: its declared 110/130px content bases include 32px padding and a 1px divider. Catalog border-box bases account for these explicitly. The Search margin also needed to beat the catalog normalization selector. The resulting panel screenshot is 1200 × 90 raster pixels in both versions, including fractional CSS-pixel rounding.

The filter is 32px higher on the page than the reference artboard because current truthful intro text occupies one fewer line. Heading position and component geometry match; extra fictional location/live-price text was not inserted to force a second line.

## Executed verification

Runtime: bundled Playwright 1.62.1, installed Chromium 151.0.7922.34 and Firefox 153.0. No packages or browsers downloaded.

```sh
PLAYWRIGHT_MODULE=<bundled-playwright> node <theme>/scripts/rental-catalog-ui-browser.cjs
PLAYWRIGHT_MODULE=<bundled-playwright> node <theme>/scripts/rental-catalog-visual.cjs
PLAYWRIGHT_MODULE=<bundled-playwright> node <theme>/scripts/rental-catalog-browser.cjs
PLAYWRIGHT_MODULE=<bundled-playwright> node <theme>/scripts/rental-related-browser.cjs
PLAYWRIGHT_MODULE=<bundled-playwright> node <theme>/scripts/rental-gallery-browser.cjs
./bin/local wp eval-file /var/www/bridge-tests/rental-integrity.php
```

Commands run from the host Aloha checkout with the isolated theme worktree mounted by ignored `.local/compose.m25a1.json`; Bridge remains mounted from the unchanged M2.5A worktree. Existing fixtures use `/var/www/m24-tests` and Bridge tests `/var/www/bridge-tests`. The existing local HTTP MU probe was enabled for the M2.5A suite, then removed. Browser logs, counters, screenshots and environment configuration remain ignored.

Results:

- Chromium and Firefox: PASS at 320, 390, 768, 1024, 1280, 1440, 1920px. Computed form/button/font/appearance sizes, absence of overflow and Bedrooms/Search overlap, popover bounds and steppers, keyboard/focus/outside closing, radio reset, URL, sticky, simulated 32px admin-bar offset, 320×568 short viewport, zoom, preview, selection, map hide/show and no-JS GET all checked. Focus-visible is tested using actual keyboard modality. Geometry assertions allow 0.01px transform rounding.
- WebKit 26.5: launcher and context creation succeed; page creation hangs. A separate 20s watchdog ended the attempt and its processes were stopped. No Safari/WebKit rendering pass is claimed. Native Safari/iOS and Edge were not separately run; mobile input sizes stay >=16px and controls use explicit scoped appearance.
- Real Google Maps / screenshots: PASS, current public images/prices/points, selected preview and working zoom, Guesty browser requests=0, JavaScript errors=0.
- M2.5A catalog suite: PASS, 38 projection checks, 17 PHP renderer/filter checks, GET/no-JS vs JS parity, invalid values/arrays, chips/clear/history/reload, lazy Embla images, map matching/neutral updates, missing key/error/GPC, and archive asset isolation.
- Server audit for the completed catalog suite: 32 cold requests, WordPress HTTP=0, Guesty=0. Local filtering made no document/XHR/fetch request.
- Existing Related Rentals: PASS at 320/390/768/1024/1440; independent arrow/dot/swipe/keyboard carousels, local media and price/badge fallbacks, CLS=0.
- Existing Gallery/media dialog: PASS; measured loading, 1–5 layouts, cached nodes, filters/lightbox, error and reduced motion.
- Real Rentals 186/365: identical before/after fingerprints (post, mapping, source, editorial, featured, service and revisions). All disposable test Rentals removed. No source/sync, booking, pricing or shared shell files changed.
- PHP/JS syntax and Git whitespace checks: PASS.

## Visual evidence and review

All paths below are under the main checkout's ignored `.local/evidence/m2-5a1/`:

- `before/catalog-{1440,768,390}.png` and `before/measurements.json`: fresh unchanged M2.5A baseline.
- `reference/html-1440.png`, `reference/filter-panel.png`, `reference/catalog.png`, `reference/catalog-page2.png`: script-free reference rendering and both PDF pages, inspected visually. The export has missing vendor runtime files; for measurement its own unchanged artboard/styles/assets were rendered as static reference HTML using the same local font faces. No reference runtime/markup was transferred to production.
- `comparison/reference-vs-current.png`, `comparison/reference-vs-updated.png`: side-by-side at identical 1440px width.
- `comparison/filter-overlay-before.png`, `filter-overlay-updated.png`, `filter-diff-before.png`, `filter-diff-updated.png`: 1:1 component overlays/diffs, without stretching either panel.
- `updated/catalog-{1440,768,390}.png`, `mobile-viewport.png`: real-data responsive screenshots.
- `updated/filters-selected.png`, `guests-{1440,768,390}.png`, `map-selected.png`, `map-preview.png`, `map-hidden.png`, `empty-results.png`, `disabled-controls.png`: requested interaction states.
- `visual-measurements.json`, `cross-browser.json`: measured geometry and actual engine results.

Final screenshots and the panel overlay were inspected after the last stylesheet change. Panel edges, dividers, field labels and Search geometry now align closely with the HTML artboard. Diffs contain genuine value/state differences (Add dates vs fictional selected dates, Add guests vs example guests, no default Bedrooms selection) and do not act as a misleading whole-page pixel-error score.

Remaining intentional visual differences: current public data yields two homes rather than six example cards; no invented feature chips/promotions/reviews; current public points include Oʻahu and are preserved; real Google tiles/attribution differ from the painted reference map; sticky offsets include the real header; CTA is disabled and has a neutral icon instead of an unconfirmed person. Existing header/footer content and styling remain unchanged. Stage B dates and Stage C movement search remain visibly unavailable.

No merge to main or production deployment. This stage ends after its verified feature-branch commit/push.
