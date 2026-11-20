# M2.4C.1c — Range calendar and booking widget polish

Extends Aloha `1d1e2fc` on the existing remote branch `feature/m2-4b-rental-template`. Implementation and browser checks: **`e754bcb`**. Bridge remains **0.3.17**, commit **`6e3c861`**, with no source or contract changes. Both integration worktrees were clean at the start; the separate root checkout's existing WIP was preserved.

## Calendar selection

The existing flatpickr 4.6.13 and arrival/departure state machine remain in use. Clicking the selected Check-in is now handled **before** the general disabled-date check. It cancels both dates, returns to Check-in selection, clears preview/departure-reason cache and invalidates the old Total and savings. The popup stays open in the same visible month. The same action works with mouse, touch, Enter and Space, including a committed range reopened later. Cancellation performs no price or availability request.

Day clicks are captured before flatpickr's native single-date handler can move the selected month. Selection updates classes and accessible attributes on the existing day elements; it calls none of `clear()`, `jumpToDate()`, `changeMonth()` or `redraw()`. Tests spy on those methods and compare day-node identity, including selecting and cancelling in the second displayed month. March/April therefore stays March/April when choosing in April. Open-time navigation to an existing selection and manual arrow navigation retain their previous purpose.

Manual month navigation uses a 200ms fade/8px translate on flatpickr's existing visible day container, with no second carousel. It stops on closing or `prefers-reduced-motion: reduce`; hidden calendars are not animated. Duplicate year/month hooks at December–January do not cancel the same transition. A six-row minimum day-container height keeps the popup height stable between months.

Availability and selection eligibility now have separate styling. Confirmed available dates stay **#E5F5E9 / #16232A**, including dates blocked by minimum stay, CTA or CTD. Disabled candidates retain the `not-allowed` cursor and existing reason tooltip. Confirmed unavailable dates remain grey with an X; unknown dates remain neutral without an X. Valid range preview includes both endpoints in **#075056 / white**. Existing stay restrictions and the occupied-day checkout-only exception are unchanged.

## Compact widget

All three controls use **12px 14px padding** and **72px minimum height**. Price and Instant booking share a baseline and one desktop row; mobile can wrap without overlapping. Empty status takes no space, the initial action gap is 14px, and Total/details use smaller gaps. Errors and loading messages remain visible when needed; a successful Reconciled or Partial calculation leaves no separate status paragraph.

The permanent technical disclaimer, extra Total labels and guest-specific paragraph are removed. Existing pricing notices, including Partial limitations, remain inside expanded **Price details**. Book now still only displays the truthful development response that no reservation or charge was made. Checkout, Guesty POST and payment operations have not been added.

There is exactly one marketing block:

- Initial or invalidated calculation: **Book direct and save 15%** compared to Airbnb, directly under Check availability. The charge message is hidden.
- Valid Total: Book now, then “You won’t be charged yet” (centered, 14px, 10px top margin), then “You save **$618** vs the same dates on Airbnb” for the test DTO.

Changing dates or guests, cancelling arrival or encountering an error immediately removes the old savings amount and restores the initial state. PHP escapes the initial copy; JavaScript uses text nodes for dynamic copy.

Savings use only the existing **`marketing.amount`** and currency formatter. There is no frontend percentage arithmetic or subtraction from Total. The unchanged Bridge's integer-money rounding returns whole-major-unit `618` for Total `4121.44` in both USD and EUR; the UI displays **$618 / €618**. A controlled retry with DTO savings `987` displays **€987** while Total remains **€4,121.44**, proving the widget does not recompute it. The separate Guesty weekly discount stays in Price details and is not applied twice. Missing or malformed marketing data does not produce a fabricated comparison amount.

## Production comparative advertising policy

The exact requested Airbnb comparison copy is prepared in the interface and tests. It is **not evidence that an actual Airbnb price for the same dates is 15% higher**. Comparative advertising policy and support for that claim still require confirmation before production use. The existing DTO flags **`displayOnly: true`** and **`comparisonApproved: false`** are preserved; no comparison was marked technically approved and the Guesty pricing contract was not changed. This stage does not deploy production.

## Verification

Chromium passed at **320 / 390 / 768 / 1024 / 1440px**:

| Suite | Result |
| --- | --- |
| Existing `scripts/rental-booking-browser.cjs` | PASS at all five widths: anchored popup above/below, short viewport, sticky/header, guests, automatic estimates, details, Partial/errors/retry, stale-response guards, focus/dismissal, retained 60/90-second states. Seven controlled price GETs and zero availability requests per width. |
| Existing `scripts/rental-range-browser.cjs` | PASS at all five widths: min stays 1/2/3/4/7, CTA/CTD/max stay, unusable gaps, unknown/occupied dates, checkout-only, inclusive hover/keyboard preview, four-month navigation, second month, New Year, 65-night stay and popup arrows. Seven/eight controlled price GETs and zero availability requests per width. |
| New `scripts/rental-booking-polish-browser.cjs` | PASS at all five widths: repeated-click/Enter/Space cancellation, no navigation/rebuild or replaced day nodes, second-month stability, availability color/cursor, 72px controls, desktop baseline, compact spacing, exact initial/Total copy, DTO savings in USD/EUR, unchanged Total/weekly discount, Partial/errors/guest invalidation, 200ms motion including year boundary and reduced motion. Five controlled price GETs and zero availability requests per width. |
| Real touch context, 390px | PASS: tap Check-in, tap again to cancel; popup stays open, fields reset, zero price/availability requests. |
| Layout | No horizontal overflow or browser page errors in the three suites. Booking/polish scenario CLS **0.0000** at all five widths. |
| Static checks | JavaScript syntax checks for the widget and all three browser suites; PHP lint; `git diff --check`: PASS. |
| Unchanged Bridge `tests/pricing-check.php` | **62 assertions passed**; no financial test modifications. Existing USD/EUR marketing rounding also checked directly. |
| Real Rental 186 and 365 | Before/after fingerprints match in **14/14 groups**: post, mapping, source, editorial, featured media, service data and revisions for both Rentals. |

Browser suites run against the local WordPress stack with synthetic fixture Rentals and mocked price GET responses; external browser requests are blocked. Fixtures are removed afterward. The existing persistent local snapshot fixture is reused. Baseline evidence was captured from `1d1e2fc` before product edits. These images show synthetic data, not a live offer or a verified Airbnb comparison.

To repeat, use the existing local Compose setup with this worktree's `scripts` mounted at `/var/www/m24-tests`, then run each browser script from the host checkout with `PLAYWRIGHT_MODULE` set if Playwright is outside the default module path. `BOOKING_POLISH_SCREENSHOTS` selects the evidence directory. `BOOKING_POLISH_BASELINE=1` captures the five comparison states at 390/1440px and should be used with the baseline source mounted.

Guesty webhooks, local snapshot persistence, calendar REST, selected-period pricing GET, PriceBreakdownDTO, cache behavior and Bridge source remain unchanged. No new component, library, endpoint, backend service, quote, reservation or payment flow was introduced. Neither repository's main branch nor production was changed.

## Before / after evidence

Mobile widget height fell from **493px to 391px** initially, **687px to 498px** after Total, and **996px to 808px** with Price details open, using the same fixture and viewport.

### Mobile — 390px

| State | Before | After |
| --- | --- | --- |
| Calendar Check-in | [Before](screenshots/m2-4c1c/before-check-in-390.png) | [After](screenshots/m2-4c1c/after-check-in-390.png) |
| Calendar range preview | [Before](screenshots/m2-4c1c/before-range-390.png) | [After](screenshots/m2-4c1c/after-range-390.png) |
| Initial widget | [Before](screenshots/m2-4c1c/before-initial-390.png) | [After](screenshots/m2-4c1c/after-initial-390.png) |
| After Total | [Before](screenshots/m2-4c1c/before-total-390.png) | [After](screenshots/m2-4c1c/after-total-390.png) |
| Price details | [Before](screenshots/m2-4c1c/before-details-390.png) | [After](screenshots/m2-4c1c/after-details-390.png) |

### Desktop — 1440px

| State | Before | After |
| --- | --- | --- |
| Calendar Check-in | [Before](screenshots/m2-4c1c/before-check-in-1440.png) | [After](screenshots/m2-4c1c/after-check-in-1440.png) |
| Calendar range preview | [Before](screenshots/m2-4c1c/before-range-1440.png) | [After](screenshots/m2-4c1c/after-range-1440.png) |
| Initial widget | [Before](screenshots/m2-4c1c/before-initial-1440.png) | [After](screenshots/m2-4c1c/after-initial-1440.png) |
| After Total | [Before](screenshots/m2-4c1c/before-total-1440.png) | [After](screenshots/m2-4c1c/after-total-1440.png) |
| Price details | [Before](screenshots/m2-4c1c/before-details-1440.png) | [After](screenshots/m2-4c1c/after-details-1440.png) |

Publication consists of the frontend/test commit above and a separate report/evidence commit on the same Aloha feature branch. Bridge has no new commit or push for this stage.
