# Independent price components and visible estimates

Aloha baseline: **`8fc7036`**, remote feature branch **`feature/m2-4b-rental-template`**. Paired Bridge: **0.3.19 / `bb0c8a7`**, published to **`codex/m2-3-rental-data-editor`**. See the [Bridge calculation/verification report](https://github.com/web86/AlohaWPBridge/blob/bb0c8a7/docs/pricing-breakdown-kiss.md).

The backend now computes each supported component independently. An unknown additional fee no longer suppresses all taxes. Null inclusivity is preserved and produces explicitly estimated tax rows when their needed inputs are available; a matching total alone does not verify that they were added for this channel. Missing or conflicting inputs qualify the overall breakdown as Partial while other supported lines stay visible. The old combination search and global calculation gate are removed.

The theme keeps the existing single Price details list. Rows marked with optional `estimated: true` follow supported confirmed rows, separated by a line and shown with muted labels/amounts. Backend labels include **(estimated)**, including for older consumers. The Total row remains below all components and uses the original BE amount. The frontend does no financial arithmetic or reclassification from sums.

Partial shows only **Some charges are estimated or not itemized**. Reconciled has no extra notice. Existing errors, retry, dates, guest changes, automatic calculation and Book now development feedback are retained. Marketing savings remain a separate DTO amount and are never deducted from Total.

## Screenshot scenario — synthetic PHP DTO

Dates: **Jan 7–12, 2027**, five nights. The first scenario uses **2 guests**, matching the user-provided screenshot:

| Line | USD |
| --- | ---: |
| Accommodation | 4,550.00 |
| Cleaning fee | 425.00 |
| TAT (estimated) | 547.25 |
| GET (estimated) | 223.88 |
| HI TAT (estimated) | 149.25 |
| **BE fixture Total, unchanged** | **5,895.38** |

The three preliminary taxes total **920.38**, based on known charges of 4,975.00. The unknown fee is omitted. Despite the matching arithmetic, the result remains **Partial** until the full application is confirmed. The 11% / 4.5% / 3% rules live only in test fixtures here; production has no historical-rate fallback.

The existing `test222` URL was found by slug and maps to **Rental 365**; exact title lookup had initially missed it. The local database was not replaced and the Rental was not recreated. WP-CLI reads returned `credentials_unreadable`. A separate check in the WordPress server container showed that a fresh financial read first needs OAuth renewal. Every POST, including OAuth, was blocked before sending, as requested. Therefore these images/amounts are **synthetic/user-supplied test data, not a fresh Guesty response**. No Guesty POST or source/financial-data change was made.

## Verification

All five widths **320 / 390 / 768 / 1024 / 1440px** passed:

- New `scripts/rental-price-components-browser.cjs` gets actual DTOs from the PHP `Breakdown` through a local read-only test adapter. It renders unknown-fee, nullable-inclusivity, confirmed, mixed confirmed/estimated, and mismatch scenarios. Checks include exact screenshot amounts, estimated labels and grouping, Partial/Reconciled captions, unchanged BE Total, separate **$884** marketing amount and no guessed fee. View details makes no request. Each width has **5 mocked price GETs**, **0 availability requests**, no overflow and no browser errors.
- Existing Booking, Range, M2.4C.1c polish and date/details suites all passed. Their prior behavior checks are retained; only the compact Partial caption expectation changed. Calendar selection, same-day cancellation, eligibility/availability colors, navigation, reduced motion, stale-response guards, errors/retry, currencies, locale/date typography and guest handling remain covered.
- Bridge has **110** financial assertions and **367** isolated WordPress/REST/cache/provider-boundary assertions passing. Added component-level checks cover nullable taxes, unknown fees without hiding other fees/taxes/discounts, fixed cleaning/fixed tax without accommodation data, the exact screenshot arithmetic and authoritative-total mismatches.
- Real Rentals **186 and 365** (including test222): **14/14** before/after fingerprint groups matched. Root checkout WIP was preserved.
- PHP/JavaScript syntax and `git diff --check` passed. Browser fixture posts were removed; provider responses are mocked and external browser requests blocked. Internal diagnostics remain absent from public REST.

No new endpoint, financial engine, component or library was added. Cache policy, Guesty routes, snapshots and webhooks are unchanged. Neither main branch nor production was changed.

## Synthetic screenshots

| Scenario | Mobile 390px | Desktop 1440px |
| --- | --- | --- |
| Unknown additional fee; visible tax estimates | [View](screenshots/price-components/unknown-fee-390.png) | [View](screenshots/price-components/unknown-fee-1440.png) |
| Null inclusivity; estimates remain Partial | [View](screenshots/price-components/nullable-390.png) | [View](screenshots/price-components/nullable-1440.png) |
| Confirmed supported rules; Reconciled | [View](screenshots/price-components/confirmed-390.png) | [View](screenshots/price-components/confirmed-1440.png) |
| Separate confirmed and estimated tax rows | [View](screenshots/price-components/mixed-390.png) | [View](screenshots/price-components/mixed-1440.png) |
| Mismatch; BE Total retained as 5,896.38 | [View](screenshots/price-components/mismatch-390.png) | [View](screenshots/price-components/mismatch-1440.png) |

Repeat the new suite using the existing Compose mounts and Playwright setup, from the host checkout. `PRICE_COMPONENT_SCREENSHOTS` optionally sets the evidence directory. `tests/pricing-visible-fixture.php` accepts only existing synthetic `m24-fixture-…-public` Rentals, rejects IDs 186/365 and performs no source write or provider call.
