# M2.4C.1b — Range calendar UX

Extends `e1f9c12` on the existing `feature/m2-4b-rental-template` remote branch, paired with Bridge **0.3.17**. The verified integration worktree was clean before implementation. The root checkout's WIP, main and production remain unchanged.

## Selection model and month stability

flatpickr **4.6.13** remains pinned. A direct test of its native `mode: 'range'` shows that an ordinary disabled occupied endpoint is removed from the selection: `setDate([arrival, occupiedCheckout])` retains only one date. Native range disabling also mixes occupied nights and departure-only restrictions. The existing single-surface arrival/departure state machine is therefore retained, with full range behavior over it.

One `stayReason()` implements the accepted interval checks: known arrival/departure, advance notice/window, CTA, arrival min/max nights, departure CTD and every occupied night before checkout. `disabled()`, hover, keyboard preview, touch feedback and final selection all use that same result. Cached arrival/departure reasons avoid repeated scans on every pointer movement. An arrival is enabled only when a search finds **at least one valid checkout**; a booked checkout boundary with allowed CTD is checked before treating that night as an obstacle to later endpoints. Server `Calendar::validate()` uses the same eligibility semantics and rejects an unknown departure.

First click selects arrival immediately and keeps the calendar open. Hover or keyboard focus over a valid checkout paints the entire visible interval, including both boundaries, **#075056 / white**. An invalid candidate clears its preview and explains the restriction. Second click updates fields, closes and calls the existing calculation. Existing committed ranges remain highlighted on reopening; guest changes, price errors, AbortController/generation guards and retained estimates keep the accepted M2.4C.1a behavior.

The month reset came from flatpickr `clear(false)`: it restores the library's initial calendar view. The handler now saves `currentYear/currentMonth`, clears the library selection, then restores that exact visible pair with `jumpToDate(..., false)` before redraw. Selecting in the second visible month keeps both months in place. Manual navigation after arrival is not overridden, and reopening locates the selected arrival/checkout. No recursive value/month-change handler or network reload was introduced.

## Popover, blocks and feedback

One decorative `aria-hidden` span forms the white bordered arrow. After popup measurement and placement it points at the active date-field center, clamped inside the popup corners. It appears above a popup below the fields and below a popup above them, with `pointer-events: none`. It is mounted outside the scroll panel, so sidebar/popup overflow cannot clip it. When a short viewport requires overlap rather than a meaningful above/below placement, the decoration is hidden. Sticky header, viewport edges, scroll/resize, mobile width and free page scrolling are retained.

Confirmed unavailable nights use the requested grey colors and a large **CSS pseudo-element X** with a readable date number. Arrival/checkout restrictions on otherwise available nights do not falsely claim a booking. Only normalized provider `reserved`/`booked` status produces the booked explanation; general blocking/allotment shows **Unavailable**. Unknown dates have no X and say **Availability not confirmed**. Valid checkout-only occupied days keep their distinct treatment and accessible **Checkout only** label, without an X during checkout selection.

Native `title` and the day accessible label carry concise explanations. Disabled cells retain pointer events; captured invalid taps display the same reason without selecting. Arrow keys can focus disabled candidates to read their reason; Enter/Space use the same selection check. Exactly one compact bottom line shows **Minimum stay varies by date**, then the selected arrival's minimum in nights, or the current attempted restriction. Its style is 12px, #64737A, centered.

Local-first remains intact: the same public Bridge snapshot is embedded in the page, internal daily prices stay private, and browsing/navigation/preview never fetches Guesty or calendar REST. No framework, positioning library, second calendar or UI timer was added.

## Verification

`node scripts/rental-range-browser.cjs` creates one synthetic mapped Rental, seeds a persistent normalized calendar, intercepts price responses and aborts external browser requests. It checks Chromium at **320 / 390 / 768 / 1024 / 1440px**:

- Native disabled-endpoint behavior and preserved state-machine rationale.
- Month four months ahead, no reset after arrival, arrival in the second visible month, manual month navigation, New Year and a 65-night stay across multiple months.
- Minimum stays **1/2/3/4/7 nights**, short unusable gaps, all-valid-endpoint CTD blocking, CTA, max stay and unknown status.
- Immediate arrival color, valid hover/keyboard range including bounds, invalid preview feedback, committed range on reopen and checkout on an occupied boundary.
- Large X via pseudo-elements, booked/unknown/minimum/CTD tooltips, touch invalid-date feedback and keyboard selection.
- Arrow attachment, active-field center and above-field flip in a short desktop viewport.
- Automatic close/calculation, seven or eight controlled price requests per width, and **zero availability requests** at every width.

The updated `scripts/rental-booking-browser.cjs` also passed all five widths: guest controls/limits, Partial/Error/Retry, preserved dates on errors, stale-response races, Escape/focus/outside dismissal, scroll/resize, 650px-high placement, sticky header, no horizontal overflow and the former 60/90-second boundaries. Widget scenario CLS remains **0.0000**. Bridge's provider-boundary tests independently verify fresh selected-period GETs and no provider GET for saved-calendar viewing.

Full Bridge smoke and Aloha Content isolated regression accompany this stage. Final real Rental 186/365 comparison covers seven groups each; **14/14 groups matched** after all tests. Test screenshots contain synthetic data only, not a live rate or booking offer.

| Evidence | Screenshot |
| --- | --- |
| Mobile range | [390px](screenshots/m2-4c1b/range-3-390.png) |
| Desktop range | [1440px](screenshots/m2-4c1b/range-3-1440.png) |
| Seven-night range / occupied checkout | [390px](screenshots/m2-4c1b/range-7-390.png) |
| Above-field arrow | [1440px](screenshots/m2-4c1b/arrow-above-1440.png) |
| Below-field arrow | [390px](screenshots/m2-4c1b/calendar-anchored-390.png) / [1440px](screenshots/m2-4c1b/calendar-anchored-1440.png) |

Changed files: `theme/aloha-hello/inc/rental-booking.php`, `assets/js/rental-booking.js`, `assets/css/rental-booking.css`, `scripts/rental-booking-fixture.php`, `scripts/rental-booking-browser.cjs`, new `scripts/rental-range-browser.cjs`, this report, the prior report's extension link and synthetic screenshots.

Aloha code commit: `48404f1`. Paired Bridge code commit: `80eed14`. Report/evidence follow in a documentation commit on the same feature branches. Backend event formats, queue/retry/persistence, account audit and explicitly deferred operations are documented in Bridge's `docs/m2-4c1b-calendar-webhooks.md`.

No actual webhook subscription, bulk real-calendar reconciliation, server cron, Quote, reservation or payment was activated. Those production operations require the separate permission specified in the task. No next stage was started.
