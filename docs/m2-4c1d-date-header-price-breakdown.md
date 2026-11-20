# M2.4C.1d — Date header and Price details

Current price-component behavior: [Independent rows and explicit estimates](pricing-breakdown-estimates.md) supersedes the tax gate and Partial caption described below.

Aloha baseline: **`325fb50`**, existing remote branch **`feature/m2-4b-rental-template`**. Paired Bridge: **0.3.18 / `2000ffc`**, published to **`codex/m2-3-rental-data-editor`**. Bridge's financial analysis and sanitized source projection are in its [financial report](https://github.com/web86/AlohaWPBridge/blob/2000ffc/docs/m2-4c1d-price-breakdown-fix.md). The separate root checkout's original WIP is preserved; main and production are unchanged.

## Date heading

Dates now use the **page/site locale**, starting with the HTML language attribute and falling back to the PHP site locale, then `en-US`. The browser's language is not used as an implicit date locale. Fields use that same explicit locale. `Intl.DateTimeFormat.formatRange()` follows the locale's month/day order, collapses a shared month and includes both years when they differ:

- English same month: **Jan 18 – 22** · 4 nights.
- English different months: **Jan 29 – Feb 2** · 4 nights.
- Different years: **Dec 30, 2026 – Jan 3, 2027** · 4 nights.
- Singular: **Mar 5 – 6** · 1 night.
- French page with a French WordPress catalog: **29 janv. – 2 févr.** · 4 nuits, even with a Russian browser locale.

The existing paragraph gains `aloha-booking-period` only after both dates are selected. A **22px / weight 600 strong** holds the range; a separate **16px / regular muted span** holds nights. Native WordPress `wp-i18n` supplies translated plural forms; the existing theme text domain is registered for script translations. No date/translation library is added. One line is retained when space allows, with normal wrapping at narrow widths and for long year ranges. The initial minimum nightly-rate markup and styling are retained, and cancellation restores that original heading.

## Price details

Price details renders only `items` from PriceBreakdownDTO and the authoritative BE estimated Total. Rows use a consistent 16px two-column grid, right-aligned currency amounts and a separated Total row. Expanding View details makes no request. The backend accommodation label says “rates vary by date”, with correct singular/plural nights.

The renderer no longer joins `payload.notices`. This also handles an older cached DTO safely:

- Reconciled: no additional notice paragraph.
- Partial: one compact, translatable **Some charges are not itemized yet** line inside expanded details.
- Failed total retrieval: the real error stays visible; stale Total and savings are cleared.

The result remains an estimated GET price with `quotedForGuestCount=false`; it is not a final Quote. Marketing savings remain a separate DTO amount. No extra success explanation appears above or below Book now. Existing development-only Book now feedback remains truthful and creates no reservation or charge. Comparative Airbnb copy retains the M2.4C.1c production-policy approval requirement; `comparisonApproved` remains false.

## Financial result and current live limit

Bridge now resolves nullable tax inclusivity only for a uniquely reconciled, supported **added-tax** interpretation with a confirmed common base and fee source. It preserves the normalized null values, compares bounded candidates in minor units and never labels a residual as Taxes. Unknown/ambiguous tax bases, fee sources, account/listing inheritance, overrides, included/mixed interpretations and mismatches remain Partial. Unsupported inheritance also suppresses uncertain fee rows. Internal reason codes stay server-side and are removed from public REST.

All published historical fixtures pass, including nullable `isInclusive`:

| Scenario | Accommodation after discount | Cleaning | Taxes | BE total |
| --- | ---: | ---: | ---: | ---: |
| Pilot, 5 nights | 3,238.00 | 595.00 | 709.11 | **4,542.11 USD** |
| Second, 5 nights | 3,321.00 | 500.00 | 706.89 | **4,527.89 USD** |
| Second, 6 nights | 4,060.00 | 500.00 | 843.60 | **5,403.60 USD** |
| Second, 7 nights, weekly factor 0.9 | 4,355.10 | 500.00 | 898.19 | **5,753.29 USD** |

Fresh live GET diagnostics for Rental 186/365 and the historical/problem date windows were attempted but stopped locally at **`credentials_unreadable`**, before a Guesty HTTP response. Current normalized Guesty settings and dated totals therefore remain **unverified**; the historical fixtures above are not represented as current provider observations. No credentials or connection configuration were changed. Damage Protection $60 was not added automatically.

## Verification

Chromium **320 / 390 / 768 / 1024 / 1440px**, all passed:

| Check | Result |
| --- | --- |
| New `scripts/rental-price-details-browser.cjs` | EN site with RU browser; shared month, cross-month, cross-year, singular night; FR page/catalog; 22px/16px typography; Reconciled taxes, compact Partial, real errors, unchanged Total, separate savings and original nightly-heading restoration. Seven mocked price GETs and **zero availability requests** per width. |
| Existing Booking suite | PASS: automatic totals/details, Partial/errors/retry, guests, stale responses, retained estimates/calendar, anchored popup, keyboard focus, sticky/header and short-viewport behavior. |
| Existing Range suite | PASS: min stays/CTA/CTD, checkout-only, unknown/occupied days, range preview, manual/second-month/New-Year/long-stay navigation and arrows. |
| Existing M2.4C.1c polish suite | PASS: repeated-click/Enter/Space/real-touch cancellation, no day rebuild/month movement, available-date colors, compact controls, DTO savings/currencies, errors/guests, 200ms navigation and reduced motion. |
| Layout | No horizontal overflow or browser page errors. Booking/polish scenario CLS **0**. New locale-reload scenario widget CLS **0**; initial full-page shifts **0.0008–0.0201** came from the existing Highlights/About sections after a French page reload, outside the widget. |
| Bridge | **90** financial assertions, **367** isolated WordPress/REST/cache/provider assertions, **1,214** isolated webhook/snapshot assertions, and six-worker cache concurrency with one regeneration: PASS. |
| Static checks | JavaScript syntax, PHP lint and `git diff --check`: PASS. |
| Rental 186/365 | **14/14** before/after fingerprint groups matched: post, mapping, source, editorial, featured, service and revisions for both Rentals. |

Browser price responses are mocked and fixtures are synthetic; external browser requests are blocked and test records are removed afterward. Before screenshots replay the pinned **`325fb50` JS/CSS** with the same rendered widget structure and controlled old-contract DTOs, including the browser-locale bug. After screenshots use the new assets and identical financial fixture amounts; these images are not live offers or verified Airbnb comparisons. Test date spans are deliberately varied to exercise typography; screenshot nightly totals are synthetic.

The new suite can be repeated with the existing local Compose/mount setup and `PLAYWRIGHT_MODULE`, from the host checkout. `PRICE_DETAILS_SCREENSHOTS` sets the evidence directory; `PRICE_DETAILS_BASELINE=1` uses the pinned baseline JS/CSS at 390/1440px. No source checkout/reset is needed for baseline evidence.

## Before / after screenshots

| State | 390px before | 390px after | 1440px before | 1440px after |
| --- | --- | --- | --- | --- |
| Date heading | [Before](screenshots/m2-4c1d/before-period-390.png) | [After](screenshots/m2-4c1d/after-period-390.png) | [Before](screenshots/m2-4c1d/before-period-1440.png) | [After](screenshots/m2-4c1d/after-period-1440.png) |
| Reconciled details | [Before](screenshots/m2-4c1d/before-reconciled-390.png) | [After](screenshots/m2-4c1d/after-reconciled-390.png) | [Before](screenshots/m2-4c1d/before-reconciled-1440.png) | [After](screenshots/m2-4c1d/after-reconciled-1440.png) |
| Partial details | [Before](screenshots/m2-4c1d/before-partial-390.png) | [After](screenshots/m2-4c1d/after-partial-390.png) | [Before](screenshots/m2-4c1d/before-partial-1440.png) | [After](screenshots/m2-4c1d/after-partial-1440.png) |

Additional cases: year boundary [320px](screenshots/m2-4c1d/after-year-boundary-320.png), [390px](screenshots/m2-4c1d/after-year-boundary-390.png), [1440px](screenshots/m2-4c1d/after-year-boundary-1440.png); French locale [390px](screenshots/m2-4c1d/after-fr-period-390.png), [1440px](screenshots/m2-4c1d/after-fr-period-1440.png).

Calendar behavior, local-first snapshots, webhook handling, cache keys/TTL and pricing/calendar GET routes remain unchanged. No Quote, Reservation or Payment POST, real Rental change, main merge or production deployment occurred.
