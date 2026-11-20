# M2.4C.1a — Compact local-first booking calendar

> Full range interaction, month preservation, arrows and block feedback are extended by [M2.4C.1b](m2-4c1b-range-calendar-ux.md). This report preserves the M2.4C.1a baseline.

Extends M2.4C.1 at `4e0f7ae` on the existing `feature/m2-4b-rental-template` remote branch, with AlohaWPBridge **0.3.16**. Changes were made in the clean `codex/m2-4-theme-integration` worktree. The root Aloha checkout's uncommitted Rental work was preserved. Main and production were not changed.

## Calendar presentation and interaction

The existing pinned **flatpickr 4.6.13** remains the date surface: two months at 768px and above, one below. The central native dialog, backdrop, visible title, date prices, permanent explanatory text, calendar footer and third-click availability button are removed. Only month/year headings, weekday/date grids and navigation remain visible in the ordinary state. A close control is available to assistive technology and becomes visible when focused; Escape, outside pointer interaction and focus moving outside dismiss the popover.

The nonmodal dialog is mounted in `document.body` to escape the sticky card's internal overflow. Lightweight fixed positioning uses the date trigger and field rectangles, clamps horizontally, measures the actual sticky header/admin bar, chooses space above/below, and updates on document/container scrolling and resize. It closes when the trigger leaves the visible viewport. It never locks page scrolling. Short viewports bound the popup height with internal overflow.

Available days use `#E5F5E9 / #16232A`; disabled known dates use `#ECEFF0 / #929DA2` with a strike-through. Unknown dates are neutral and disabled, without an availability claim. Selected arrival/departure use `#075056 / #FFFFFF`; intervening nights use `#D7EAE4`. An occupied night becomes selectable only as a valid checkout boundary: a diagonal treatment, bottom edge and accessible label explicitly identify **checkout only**.

The first date selects arrival and keeps the calendar open. Arrival minimum/maximum nights, CTA/CTD, booking window and occupied intervening nights constrain checkout. Selecting departure updates both fields, closes the calendar and immediately runs existing `calculate()` through Bridge `price-details`. Errors preserve the chosen dates. Guest changes retain the existing composition validation and **Done** recalculation. AbortController/generation guards prevent an older result from replacing a later choice.

## Local-first and estimates

The theme embeds only Bridge's public local calendar projection; internal daily rates, settings and provider identity are excluded. Opening, month navigation, resize and reopening require no network request when this snapshot exists. The loaded observation is stable for the page lifetime: no timer clears days, reloads the calendar or resets dates. A legacy Rental with no snapshot has an explicit, non-overlay loading/error/retry bootstrap state; unknown dates fail closed.

Checkout starts **Calculating total…**, then displays Total and inline Price details. The authoritative estimate remains Bridge's BE total with existing reconciliation/partial behavior and financial/weekly-discount rules. A response already expired on arrival is rejected, but a displayed estimate no longer disappears after 60 seconds. It remains preliminary until a separately approved Quote/Checkout stage. Book now retains the existing checkout-not-enabled behavior.

Bridge storage, sync boundaries, fallback coverage, provider coalescing and future webhook/reconciliation routing are documented in its companion report `docs/m2-4c1a-local-first-calendar.md`.

## Acceptance

Chromium passed **320, 390, 768, 1024 and 1440px**, including a 650px-high viewport and scroll/resize transitions:

- Popup is mounted outside the overflow card, starts **6px below** the date trigger when space permits, stays inside viewport edges and below the measured sticky header.
- Correct one/two months, exact colors, minimum/maximum stay, CTA/CTD, unknown status and occupied checkout boundary.
- **Zero calendar REST requests** for snapshot opening/navigation/reopening/resize at every width. Provider-boundary tests in Bridge separately verify zero Guesty GETs on the stored path and fresh calendar/total reads on calculation.
- Two-click date selection with automatic close/calculation, preserved dates on unavailable response, Reconciled/Partial/Error/Retry, guest limits/composition and delayed-response rejection. Seven controlled price requests per width exercise those transitions.
- Advancing browser time **91 seconds** preserves both calendar eligibility and the displayed total, with no calendar request.
- Keyboard navigation, Escape/focus return, outside dismissal, free page scrolling and sticky-header geometry. Horizontal overflow **false** and widget scenario CLS **0.0000** at all widths.
- Legacy missing-snapshot Loading/Error/Retry and unknown dates are tested separately. Reopening after its successful bootstrap does not request data again.
- Existing complete Rental template regression passed: native draft preview/nonces, gallery/filter/lightbox, highlights, reviews, Save/Share, About modes, disclosures and mocked Nearby maps. Initial desktop CLS **0.0012**.
- Bridge's native Update/Preview/Revisions regression preserves the new calendar fingerprint. Real Rental 186/365 integrity comparison passed **14/14 groups** (post, mapping, source, editorial, featured image, service metadata and revisions), after all tests.

The browser harness creates/removes synthetic Rentals and media, aborts external browser requests, and intercepts pricing responses. The displayed USD 4542.11 and 579 nightly source fixture are test values, not a live tariff or offer. Screenshots show only synthetic content.

| State | Mobile 390px | Desktop 1440px |
| --- | --- | --- |
| Anchored calendar | [Screenshot](screenshots/m2-4c1a/calendar-anchored-390.png) | [Screenshot](screenshots/m2-4c1a/calendar-anchored-1440.png) |
| Checkout restrictions | [Screenshot](screenshots/m2-4c1a/calendar-checkout-390.png) | [Screenshot](screenshots/m2-4c1a/calendar-checkout-1440.png) |
| Total / Price details | [Screenshot](screenshots/m2-4c1a/booking-details-390.png) | [Screenshot](screenshots/m2-4c1a/booking-details-1440.png) |
| Missing snapshot | [Loading](screenshots/m2-4c1a/calendar-loading-390.png) / [Error](screenshots/m2-4c1a/calendar-error-390.png) | Same bounded fallback |

## Changed components

- `inc/rental-booking.php`: safe embedded snapshot and compact nonmodal markup.
- `assets/js/rental-booking.js`: anchored positioning/focus, stable local calendar and automatic estimate lifecycle.
- `assets/css/rental-booking.css`: compact geometry, requested colors and checkout-only distinction.
- `scripts/rental-booking-{fixture.php,browser.cjs}`: synthetic persistent calendar, viewport/state/network/timer assertions.
- This report, original-report cross-reference and synthetic screenshots.

No Quote, reservation, payment, real Rental sync or real photo import was made. No subsequent stage was started.
