# M2.5A.2 — Global Smart Header and catalog UX

Date: 2026-11-21. Base Aloha `97fc3a1d770a7722d790faeed185380680e4ac15`; feature branch `codex/m2-5a2-catalog-ux`. Both were fetched/verified before work. Bridge remains clean and unchanged on its M2.5A branch at `4bc70bb9fed63735a8b778c718d8b31382fdf6a3`. Design reference main was fetched and is still `3af4cdc181a7e0d599d7fcefb2544f332a0d8e05` (HTML/PDF/assets reviewed in M2.5A.1).

Existing dirty root checkouts and all earlier worktrees were preserved. No merge to main, production deployment, Guesty/Pricing/Booking change, new endpoint, framework, public DTO change or real Rental metadata write.

## Nine changes

1. **Global Smart Header.** Implemented in the existing `shell.js` and `shell.css`, with no second controller. The sticky wrapper stays in flow while only the closed header transforms. At the top (48px) it is visible; meaningful downward travel past the header hides it, upward travel of 12px reveals it, and direction hysteresis ignores small alternating deltas. Passive scroll events feed requestAnimationFrame. Actual admin-bar geometry determines the inset, including the mobile absolute admin bar scrolling out of view. Header height and animated visible bottom are published for consumers. Open mobile/mega menus, keyboard focus and open native dialogs pin the header. Menu/focus states force `transform:none` and no transition so fixed menu overlays never inherit a transformed containing block. Reduced motion skips animation. Public-page scroll padding protects anchor/focus targets.
2. **Filter backdrop.** A non-interactive near-opaque-to-transparent gradient replaces the solid sticky background. The white desktop panel stays opaque and retains its ~1200 × 89px geometry. The catalog consumes the header's changing visible bottom throughout hide/reveal transitions; the filter and map settle without a top gap or admin-bar overlap.
3. **Hide map grid.** Desktop (>=1024px) uses two card columns with the map and three without it. Tablet uses two; mobile (<=767px) uses one. Existing card nodes, Embla and subgrid remain. Responsive `sizes` follow measured gallery widths via the grid ResizeObserver and visibility changes, including newly revealed cards; hidden/secondary images keep their lazy sources.
4. **Editable CTA in Results.** The original Gutenberg help content moved verbatim to `parts/catalog-help.html`. `block_template_part('catalog-help')` renders it once after cards/empty state inside `.aloha-catalog-results`. The map aside now spans the CTA as well. The help block expands with Results when the map is hidden. Native Site Editor database overrides were actually tested with a disposable template part, then removed. Existing overrides would be preserved by the test. Why book direct remains editable in the archive.
5. **Badge diagnosis.** Read-only metadata → effective badge → catalog DTO → HTML tracing was added. No rule or metadata was changed; concrete findings are below. Synthetic active/expired/none and gallery overlay checks pass.
6. **Movement-search icon.** Replaced the text glyph with a 16px stroke SVG checkbox. It remains unchecked, disabled and labelled coming soon. No movement search was implemented.
7. **Anchored InfoWindow.** The existing safe local preview is now content of one Google Maps `InfoWindow`, opened with `{map, anchor: selectedAdvancedMarker, shouldFocus:true}`. Default auto-pan keeps it within the map. Width/scrollable height are bounded; long text, missing image/facts and unknown price are handled. Custom close and captured Escape restore marker focus; filter changes and map hiding close it. Selected pin style and neutral semantics remain. Provider reopen during resize is not confused with an explicit user close. The SDK is explicitly notified of size changes; no map search or Guesty request follows. API behavior verified against [Google's InfoWindow reference](https://developers.google.com/maps/documentation/javascript/reference/info-window).
8. **Mobile fullscreen map.** On <=767px JavaScript initially hides the map. Show map moves the original aside into a native top-layer dialog, with Close map SVG/text, safe-area spacing, viewport sizing, inert background, focus management and scroll lock/restore. Escape/Back close it; Forward can restore the dialog entry. InfoWindow works inside it. The existing SDK/map instance is reused, with resize after opening. Crossing 767/768 restores the appropriate desktop/map preference and closes modal state. GPC/error fallback works inside the dialog. Without JS the in-flow map/list remain available.
9. **Mobile compact filters.** The original Dates and Search controls form a compact `Add dates | Filters (n) | Search` bar. The original Guests/Area/Bedrooms node tree moves into a native bottom sheet, never cloned. Inputs retain `form="catalog-filter-form"`, so one GET/FormData/URL contract still governs everything. The sheet has Clear all and Show homes, native modal isolation plus explicit Tab endpoint cycling, Escape/Back/close and focus/scroll restoration. Applying replaces the temporary overlay history entry with the resulting filter URL; ordinary Search/chips still push filter history. Disabled dates never generate URL dates. No-JS keeps the original visible GET form.

## Badge findings (real Rentals, read-only)

| Rental | `_awb_listing_badge` | `_awb_first_published_at` | `effective()` | DTO / HTML | Reason |
| --- | --- | --- | --- | --- | --- |
| 186 | `new` | Missing | `null` | `badge=null`, no span | First publication is unconfirmed |
| 365 | `new` | Missing | `null` | `badge=null`, no span | First publication is unconfirmed |

This is **not** a confirmed NEW expiry and not a renderer/CSS defect. The existing Bridge deliberately does not invent first-publication dates for legacy Rentals. Seven-day behavior is unchanged: only a confirmed first publication less than seven days old permits NEW. LUXE remains independent of that timer. No attempt was made to restart NEW or change the real metadata.

The catalog regression now asserts active NEW/LUXE text, visible non-clipped overlay bounds/opacity, none, and an expired NEW disappearing after a fixture-only date change. The existing Related Rentals expiry/LUXE checks also pass. Synthetic records are distinct from 186/365 and were removed.

## Files

Production theme changes:

- `assets/js/shell.js`, `assets/css/shell.css`: one global header controller, public scroll offsets and safe animation states.
- `assets/js/rental-catalog.js`: dialog/history controller, moved form/map nodes, one filter state, width-based image sizes and header offset consumption.
- `assets/css/rental-catalog.css`: gradient, three-column state, mobile compact bar, native sheet/fullscreen styling and InfoWindow content.
- `assets/js/rental-catalog-map.js`: anchored InfoWindow and SDK resize handling.
- `inc/rental-catalog.php`: explicit form associations, field wrapper, SVG checkbox and native template-part render in Results.
- `templates/archive-rental.html`: removes the old duplicate CTA location.
- Added `parts/catalog-help.html`: editable core blocks, existing icon/copy/disabled contact CTA.

Test/evidence tooling:

- Added `scripts/smart-header-browser.cjs`, `rental-catalog-ux-browser.cjs`, `rental-catalog-badges.php`, `rental-catalog-template-part-check.php`, `lib/catalog-map-mock.cjs`.
- Updated existing `rental-catalog-browser.cjs`, `rental-catalog-ui-browser.cjs`, `rental-catalog-visual.cjs` for the new mobile modes and actual InfoWindow API.
- Updated `mega-menu-smoke.cjs`: its planned-link test now selects a marked list child, since Mauna Lani is a real route and the editorial CTA is hidden on mobile. Its real href/new-tab tests remain. GPC isolates this shell suite from optional Maps on destination pages.
- This report. No screenshots, logs, credentials or local Compose overrides committed.

## Executed verification

All browser commands used installed bundled Playwright 1.62.1 with Chromium 151.0.7922.34 and Firefox 153.0. No packages or browsers installed. Commands ran from the host Aloha checkout with feature sources mounted through ignored `.local/compose.m25a2.json`; theme scripts at `/var/www/m24-tests`, unchanged Bridge tests at `/var/www/bridge-tests`.

```sh
node <theme>/scripts/smart-header-browser.cjs
node <theme>/scripts/rental-catalog-ux-browser.cjs
node <theme>/scripts/rental-catalog-browser.cjs
node <theme>/scripts/rental-catalog-ui-browser.cjs
node <theme>/scripts/rental-catalog-visual.cjs
node <theme>/scripts/mega-menu-smoke.cjs
node <theme>/scripts/rental-related-browser.cjs
node <theme>/scripts/rental-gallery-browser.cjs
node <theme>/scripts/rental-template-browser.cjs
node <theme>/scripts/rental-booking-browser.cjs
./bin/local wp eval-file /var/www/m24-tests/rental-catalog-badges.php
./bin/local wp eval-file /var/www/m24-tests/rental-catalog-template-part-check.php
./bin/local wp eval-file /var/www/bridge-tests/rental-integrity.php
```

Set `PLAYWRIGHT_MODULE` to the bundled runtime and `CATALOG_UX_EVIDENCE` to the desired evidence path. The unchanged local HTTP MU probe from M2.5A was temporarily installed for the catalog suite and removed afterward. It counts/rejects attempted server HTTP for synthetic test headers, never logs provider URLs/payloads, and supplies controlled missing-key/error cases.

| Check | Actual result |
| --- | --- |
| Smart Header, Chromium + Firefox | PASS: Home, Area, Community, Single Rental, Archive at desktop/mobile; down/up/top/jitter, keyboard focus, mobile/mega menus, anchor, simulated fixed/absolute admin bars, reduced and animated motion. Wrapper height preserved. Short-page tests append a DOM-only spacer when needed to exercise scrolling. |
| Catalog UX, both engines | PASS at 320/390/768/1024/1280/1440/1920: 2/3/1 columns, unchanged card nodes, measured sizes, CTA/map scope, compact sheet, one input tree, form association, Apply/Clear, Back/Forward, focus trap/return, scroll lock, fullscreen and orientation/breakpoint restoration. |
| InfoWindow cases | PASS: anchor is selected AdvancedMarker, only one window, safe long text, missing photo/facts, unknown-price fallback, neutral marker, filter/hide close, zoom and one map instance. Stress content is injected only in the browser test, not into WordPress. |
| Real Google Maps | PASS: both actual public points selected; InfoWindow bounds verified at both edges; mobile fullscreen and preview; one SDK load across repeated opens/width changes; JavaScript errors=0, browser Guesty=0. |
| M2.5A catalog regression | PASS: 38 Bridge projection checks + 17 PHP renderer/filter checks; SSR/JS/no-JS parity, GET/history/chips/clear, invalid inputs, Embla, privacy/GPC/missing key/provider error, asset isolation and badge variants. |
| Server HTTP audit | **33 cold catalog requests; total WordPress HTTP=0, Guesty=0.** Local filtering makes no document/XHR/fetch request. |
| Existing Design v2 UI suite | PASS in Chromium/Firefox for desktop/tablet geometry, scoped controls, focus/hover, popover, reset, map/zoom/preview; short mobile sheet checked. Full mobile coverage is in the new UX suite. |
| Existing mega-menu smoke | PASS in Chromium, including desktop/touch/mobile, keyboard, Escape/Back, 200% text, reduced motion, real href and new-tab navigation; no external requests or JS errors under GPC. |
| Related Rentals | PASS at 320/390/768/1024/1440; independent carousels, swipe/keyboard/cache, local ordered images, badges, price fallbacks; CLS=0. |
| Gallery / media dialog | PASS: initial-load measurements, 1–5 layouts, cached nodes, filters/lightbox, image error and reduced motion. |
| Single Rental full browser suite | PASS: published/draft Preview/nonce behavior, gallery, reviews, Save/Share, highlights, descriptions, disclosures, Nearby success/errors, empty data and all five widths. Measured initial desktop CLS=0.0011. |
| Booking/calendar regression | PASS at all five widths, including guests, anchored calendar, local-first state, estimates/details/errors/races, focus/sticky/overflow; widget CLS=0. Pricing code unchanged. |
| Site Editor override | PASS: one CTA inside Results, disposable database template-part override replaces the file content, then deletion restores `source=theme`. |
| Real Rentals integrity | PASS: final fingerprints exactly match before for posts, mapping, source, editorial, featured, service metadata and revisions of 186/365. Synthetic Rentals remaining=0. |
| PHP/JS syntax and Git whitespace | PASS. |

WebKit was retried for this stage: launcher 26.5 and context succeeded, but page creation again did not complete within a 20-second watchdog. The attempt was terminated; **no Safari/WebKit PASS is claimed**. Native iOS/Edge were not separately tested.

## Screenshots

Ignored evidence directory: `.local/evidence/m2-5a2/` in the main host checkout.

- `header-visible.png`, `header-hidden.png`: both global header states. Header test captures intentionally block optional provider requests, so their map area can show fallback.
- `desktop-map.png`, `desktop-viewport.png`, `desktop-three-columns.png`: real public catalog with/without map. Current data has two homes; the third column is intentionally empty rather than filled with invented listings.
- `cta-and-map.png`: editable CTA in Results alongside the sticky map.
- `catalog-320.png`, `catalog-390.png`, `compact-selected-320.png`, `compact-selected-390.png`: compact mobile filters and active counts.
- `filter-sheet-320.png`, `filter-sheet-390.png`: actual bottom sheet.
- `fullscreen-map-320.png`, `fullscreen-map-390.png`, `fullscreen-infowindow-320.png`, `fullscreen-infowindow-390.png`: native map dialog and anchored local preview.
- `infowindow-0.png`, `infowindow-1.png`: real provider window at both map edges.
- `empty-with-cta.png`: empty results retain the editable help CTA.
- `header-results.json`, `catalog-ux-results.json`, `cross-browser.json`, `live-map-results.json`: engine/geometry/provider evidence. `mega-menu/` holds the existing shell regression screenshots.

Final screenshots were inspected visually, including the narrow sheet, fullscreen InfoWindow, header hidden state and widened grid/CTA. Google tile loading/content varies with provider/network; the catalog stays usable through the existing fallback.

## Scope boundaries and limitations

Dates and movement search remain visibly disabled. No M2.5B/C search, quote, reservation or payment work began. Help remains the previously approved disabled placeholder. Public Oʻahu coordinates remain exactly as provided by Bridge; no relocation or data correction was performed. Missing first-publication metadata on 186/365 is reported, not repaired. Browser engines tested are explicitly listed above; no unsupported cross-browser claim is made.

All requested changes are confined to this feature branch. Original work in progress remains untouched. No main merge or production deployment.
