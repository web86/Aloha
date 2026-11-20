# M2.5A — Local-first Vacation Rentals catalog

Date: 2026-11-20. Stage A only. No main merge or production deployment.

## Scope and bases

Both remote bases were fetched and verified before implementation:

- Aloha `origin/feature/m2-4b-rental-template`: `6f3a128dede9abd7333101a5c37fffe3227c308d`.
- AlohaWPBridge `origin/codex/m2-3-rental-data-editor`: `1e846a26b06ef36c2d7cbaa1f2c57baa01130fa0`.
- Both changes use `codex/m2-5a-rental-catalog` in separate local worktrees. Existing main checkouts and their unfinished Single Rental work were preserved.

## Implementation

The native `/vacation-rentals/` archive uses `templates/archive-rental.html`, existing header/footer template parts, and the server-rendered `aloha/rental-catalog` block. The lower help/benefit sections use editable core Gutenberg blocks. `aloha/catalog-contact` resolves a verified shell contact or published Contact page; otherwise it renders a disabled **Help me choose** placeholder. The user explicitly requested this placeholder until the contact destination is decided.

Bridge's new `RentalCatalogData::get()` checks published/password-free/mapped Rentals, versioned local snapshots and strictly boolean `active=true`, `isListed=true`, `beVisible=true`. Missing/unknown visibility is excluded. It returns allowlisted card data, status-bearing numeric facts, published Area/Community references, badge, `Pricing\Minimum::public()`, a bounded local gallery and normalized public coordinates. It does not call `RentalPublicData::get()`.

The existing gallery selector was minimally extended with optional limit/image size arguments. It ranks inexpensive gallery metadata with the existing source/editor order and visibility rules, then resolves at most six usable local attachments at `medium_large`. It skips missing files and removed/hidden photos. The single-page default remains the complete `large` gallery. The catalog image allowlist omits full originals, source photo IDs and captions. No local image means the shared card placeholder; a hidden source cover is not reintroduced as a thumbnail fallback.

`aloha_rental_related_card()`, `aloha_rental_related_price()` and `aloha_rental_fact()` remain the renderers. The card renderer accepts optional catalog matching fields, hidden state and responsive sizes. Existing Related Rentals use unchanged defaults. Card CSS and Embla initialization were extracted into shared `rental-cards.css` / `rental-cards.js`. Single gallery, booking, calendar, financial code and Nearby map were not rewritten.

### Filters and ordering

- URL/GET contract: `area_id`, `bedrooms` (`2`, `3`, `4` meaning 4+), `adults`, `children`. Counts are separate integers 0–100. Invalid values/arrays normalize to defaults.
- `area_id` avoids WordPress's existing CPT query variable `area`. References come exclusively from `_awb_area_id`; only published, password-free Area options are offered.
- All public cards are server-rendered. PHP selects visible results on initial GET. Hidden cards have no image `src`; secondary carousel images use `data-src` until selected.
- JavaScript applies the same confirmed-capacity/bedroom rules locally, updates count/chips/map and uses `pushState` plus `popstate`. Standard GET submits and chip links work without JavaScript.
- Required capacity is adults + children. Unknown/stale capacity or bedrooms cannot satisfy a corresponding filter. Confirmed `suitableForChildren=false` excludes a home when children > 0. Unknown child policy remains a capacity-only result, with explicit text that suitability and availability need confirmation; no family-suitability badge is inferred.
- Recommended uses stable `menu_order ASC, ID ASC`. No trustworthy aggregate rating contract was found. Selected Featured Reviews are not averaged. A future normalized `ratingScore` can be added to the projection and ordering without changing card rendering.

### Map

The catalog uses the existing `aloha_rental_maps_config` filter, browser key and Map ID conventions. Google Maps is deferred until intersection, respects Global Privacy Control and has loading/error/missing-key/no-coordinate fallbacks. The list remains independent. Show/Hide map changes the layout without recreating the provider map.

Only `publicMapLocation` enters marker configuration. Confirmed published source coordinates precede the manually administered public fallback. Private location is never used. Matching markers show the shared available minimum nightly price, or the public home title when unconfirmed; other public homes remain neutral unpriced dots. Filter changes update marker presentation in place. There is no availability request, bounds search, date search or map movement search.

Catalog styles/scripts enqueue only on the Rental archive. Booking, Single gallery/dialog scripts and flatpickr do not enqueue there. No endpoint, schema, CPT, taxonomy, table or cache subsystem was added.

## Changed files

Aloha:

- Added `theme/aloha-hello/templates/archive-rental.html` and `inc/rental-catalog.php`.
- Added `assets/css/rental-catalog.css`, `assets/js/rental-catalog.js`, `assets/js/rental-catalog-map.js` under the theme.
- Extracted shared `assets/css/rental-cards.css` and `assets/js/rental-cards.js` from `rental.css` / `rental-content.js`.
- Updated `inc/rental.php` and `functions.php` for shared assets, optional card arguments and catalog module loading.
- Added `scripts/rental-catalog-fixture.php`, `rental-catalog-browser.cjs`, optional local-only `rental-catalog-http-audit.php`, and this report.

AlohaWPBridge:

- Added `src/RentalCatalogData.php` and `tests/rental-catalog-check.php`.
- Updated `src/RentalPublicData.php` with shared sanitization/reference/fact helpers and bounded gallery support; default full DTO output remains intact.
- Updated `aloha-wp-bridge.php` to load the new class. No version/schema migration required.

## Verification

Tests use disposable synthetic mapped Rentals/media and remove their records. Real Rentals 186/365 are not test fixtures. Evidence and runtime configuration stay in ignored `.local/`.

Actual commands (PHP/WP/browser commands run with feature worktree mounts):

```sh
php <bridge>/tests/rental-public-data-fixture.php
php <bridge>/tests/pricing-check.php
php <bridge>/tests/photo-label-suggestions.php
./bin/local wp eval-file /var/www/bridge-tests/rental-catalog-check.php <synthetic-id> <marker>
./bin/local wp eval-file /var/www/m24-tests/rental-catalog-fixture.php check <marker>
./bin/local wp eval-file /var/www/m24-tests/rental-template-smoke.php <synthetic-id>
./bin/local wp eval-file /var/www/bridge-tests/pricing-smoke.php
node <theme>/scripts/rental-catalog-browser.cjs
node <theme>/scripts/rental-related-browser.cjs
node <theme>/scripts/rental-gallery-browser.cjs
node <theme>/scripts/rental-booking-browser.cjs
node <theme>/scripts/rental-price-details-browser.cjs
./bin/local wp eval-file /var/www/bridge-tests/rental-integrity.php
```

Set `COMPOSE_FILE` to the local Compose stack with theme/Bridge worktree mounts. Mount this branch's `scripts` at `/var/www/m24-tests` and Bridge tests at `/var/www/bridge-tests` in CLI. Set `PLAYWRIGHT_MODULE` for the installed runtime and `CATALOG_SCREENSHOTS` for catalog evidence. The installed Playwright runtime expected an older browser build, so a private wrapper supplied the existing Chromium 1234 executable; no dependencies or browsers were downloaded.

For a full-request server audit, temporarily install `scripts/rental-catalog-http-audit.php` as a local MU plugin before running the catalog suite. It only activates for local synthetic test headers, flushes the request object cache, rejects/counts attempted WordPress HTTP requests, supplies a synthetic Maps key for mocked provider tests, and writes numeric counters to `/tmp/aloha-catalog-http-audit.jsonl`. Remove the MU plugin after testing. No provider credentials, raw URLs or payloads are logged.

Results:

- Public DTO fixture, 110 pure pricing assertions and PhotoLabelSuggestions fixture: PASS.
- Catalog projection: 38 checks, bounded six-image hydration, publication/visibility exclusions, missing facts, removed images, empty gallery, coordinate priority/privacy and no HTTP or SQL writes: PASS.
- Catalog PHP rendering/filter assertions: 17, PASS; HTTP=0.
- Catalog browser: PASS at 1440/768/390. GET/no-JS and JS parity includes combined filters, invalid arrays/duplicates, empty results, chips, clear, reload, Back/Forward, lazy carousels, child restriction and map states. No overflow or JavaScript errors. No booking/flatpickr assets on archive.
- Server full-request audit: 32 cold catalog requests in the completed suite; total WordPress HTTP=0 and Guesty=0. Local filtering made no document/XHR/fetch requests. Image requests occur only as needed for revealed cards/slides.
- Live Google Maps check with the already configured browser key: PASS, one public marker, no JavaScript errors, Guesty requests=0. Synthetic tests independently cover multiple priced/unpriced markers, neutral filtered markers, missing key, provider error and GPC.
- Existing Single Rental PHP render smoke: PASS with no provider calls.
- Existing Related Rentals suite: PASS at 320/390/768/1024/1440; independent carousels, arrow/dot/swipe/keyboard, local visible ordered media, badge/minimum fallbacks and CLS=0.
- Existing Gallery suite: PASS, initial-load measurements, 1–5 layouts, cached image nodes, filters, shared lightbox, image error and reduced motion.
- Existing Booking suite: PASS at 320/390/768/1024/1440, calendar, estimates/details, partial/error/retry, races, guests, focus, sticky and overflow.
- Existing price-details suite: PASS at 320/390/768/1024/1440, date locale/header, reconciled taxes, Partial and error/savings behavior; widget CLS=0.
- Existing WordPress pricing/REST smoke: 367 assertions, PASS (all provider responses are isolated fixtures).
- Rentals 186/365: identical before/after fingerprints for posts, mapping, source, editorial, featured, service metadata and revisions.
- PHP lint, JS syntax and Git whitespace checks: PASS.

Screenshots (not committed): `.local/evidence/m2-5a/catalog-{1440,768,390}.png` are synthetic fixture/provider-state evidence. `.local/evidence/m2-5a/live/catalog-{1440,768,390}.png` show the current public catalog with actual Google Maps. Related regression screenshots are in the `related/` subdirectory.

## Current data and deferred scope

The current local data permits one catalog Rental. Its normalized public map point displays on Oʻahu although the page/Area copy says Big Island / Mauna Lani. The catalog faithfully uses the existing public projection; no real Rental coordinates, source snapshots, settings or editorial metadata were corrected by this stage. This is an editorial/source-data follow-up.

The help CTA intentionally stays a disabled placeholder under the user's follow-up instruction. Header/footer contact placeholders are existing behavior and were preserved.

M2.5B remains: date picker, Guesty Booking Engine search, confirmed availability, estimated selected-stay total and stay-price ordering.

M2.5C remains: search as the map moves, richer card/map synchronization, editorial feature chips, promotions and additional interactions. Neither stage has been started.
