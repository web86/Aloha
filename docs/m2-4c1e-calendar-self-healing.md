# M2.4C.1e — Immediate calendar updates without reload

| Repository | Baseline | Implementation | Feature branch |
| --- | --- | --- | --- |
| Aloha | `fef82e1` | **`f8d79af`** — Apply healed calendars without reloading the booking widget | `feature/m2-4b-rental-template` |
| AlohaWPBridge | 0.3.19 / `bb0c8a7` | **0.3.20 / `45af189`** — Add Guesty sync settings and atomic calendar self-healing | `codex/m2-3-rental-data-editor` |

Documentation/evidence follows in separate commits on those branches. The [Bridge report](https://github.com/web86/AlohaWPBridge/blob/codex/m2-3-rental-data-editor/docs/m2-4c1e-sync-settings-self-healing.md) details admin settings, UTC/site-time planning, monthly GET reuse, locks, budgets and server tests.

## Visitor behavior

The existing `price-details` GET may now return a confirmed availability-change error containing `calendarUpdated=true` and the new public calendar projection. The widget retains that error body, replaces the in-memory calendar observation and updates the existing flatpickr surface.

It then clears Check-in, Checkout, range preview and departure-reason cache; hides the previous Total and savings; restores the original nightly heading, Check availability and initial marketing state; and shows exactly:

> These dates are no longer available. Please choose new dates.

The displayed month is remembered before the update and restored. Reopening the picker keeps that month/pair instead of moving back to today. The calendar retains the existing open/closed state; choosing new dates uses the received data immediately. **No document reload or extra availability REST request occurs.** Existing gray/X, unknown, green/restricted and checkout-only styling continues to apply to the new observations.

A failed/incomplete/budget-limited server refresh has no updated-calendar flag. The widget retains selected dates, shows the retry error, and removes the old price/savings through ordinary invalidation. A malformed/wrong-Rental calendar is rejected without claiming an update. Existing abort/generation guards ensure a delayed earlier healing response cannot clear a more recent calculation. A previous calendar fetch is cancelled when an authoritative replacement is accepted.

Only `theme/aloha-hello/assets/js/rental-booking.js` changes production behavior in Aloha. No new calendar, component, framework, endpoint, UI timer or financial calculation is added. Pricing lines, estimated-label grouping, locale/date typography, Book now development feedback and separate DTO marketing savings keep their prior behavior.

## Bridge behavior paired with this UI

The backend compares the local saved stay with fresh normalized Open/BE calendars. Only a confirmed availability/stay-rule contradiction heals. Ordinary success, Total changes, lack of a suitable rate plan, unknown dates or provider/auth/429/5xx failures do not trigger a false refresh.

Full normalized monthly observations are kept request-locally before selection trimming. The repair reuses them, fetches only missing months, and publishes through existing source/publication locks and a short atomic transaction. It covers at least two consecutive months from Check-in and every month of a longer stay. Partial target responses do not overwrite the snapshot; unrelated older unknown dates remain unknown. Successful partial-range work does not advance the full-year-success clock.

A successful repair returns **422 / availability_changed** with the public projection; an unsuccessful repair returns **503 / calendar_refresh_failed** without an update claim. Neither route returns internal nightly prices, provider settings or guest/reservation records. The normal 45-second pricing budget is retained; repair uses at most 12 seconds within it, with bounded HTTP timeouts and locks.

Connections now has Webhook Settings (receiver URL/Copy URL, secret status, event/worker information) and Calendar Synchronization (default Off, preferred site-time start). Effective server On/Off constants take priority. The single existing minute worker distributes daily nearest-30-day/tail and weekly-full work one Rental at a time. The UI makes server-cron and heartbeat limitations explicit. No real subscription, cron installation or backup enablement is performed by this stage.

## Verification

`node scripts/rental-calendar-healing-browser.cjs`, using synthetic Rentals and intercepted GETs, passes Chromium **320 / 390 / 768 / 1024 / 1440px**. Each viewport verifies:

- A successful estimate followed by a confirmed Jan 28–Feb 5 change, with Jan 30 newly unavailable.
- Both date fields reset, old Total/savings hidden and exact message/initial action restored.
- The prior month/pair retained on reopening; newly blocked dates visible from the received projection.
- New valid date selection and calculation afterward.
- Failed refresh retry with dates retained; wrong-Rental projection rejected.
- A delayed stale healing error cannot replace a newer successful price.
- **7** mocked price GETs, **0** availability GETs, **1 initial document navigation**, no subsequent reload, no horizontal overflow or browser errors.

All five existing Aloha Booking, Range, polish, date/details and price-component suites pass at the same five widths. They retain coverage for automatic estimates, Partial/error/retry, guest controls, generation races, focus/sticky/short viewports, no calendar-viewing network traffic, min/CTA/CTD/checkout-only rules, month stability, reduced motion, locale/typography, confirmed/estimated tax rows and unchanged Total/savings. Booking/polish scenario CLS remains 0; date-suite initial Highlights/About shifts remain outside the widget as previously documented.

Bridge checks pass: **534** healing assertions; **42** settings/planning assertions plus **17 + 17** forced server variants; **367** pricing/REST/cache assertions; **110** financial assertions; **1,214** webhook/snapshot assertions; two real concurrent PHP processes with **one** source publication; existing isolated import/Guesty suites; existing Bridge Picker browser; and new admin clipboard/form/diagnostic browser checks at 390/768/1440px. PHP/JavaScript syntax and whitespace checks pass.

Real **Rental 186/365** integrity is unchanged in **14/14** groups: post, mapping, source, editorial, featured, service and revisions. Automatic tests never use either real Rental as a healing target. Synthetic fixture records are removed and provider responses are mocked. The real WordPress server was checked after tests: backup synchronization remains **Off**, with no stored setting or server override. Main, production and root WIP are unchanged.

## Synthetic frontend screenshots

| State | Mobile 390px | Desktop 1440px |
| --- | --- | --- |
| Dates / old estimate cleared with exact message | [View](screenshots/m2-4c1e/cleared-390.png) | [View](screenshots/m2-4c1e/cleared-1440.png) |
| Updated calendar, Jan 30 unavailable | [View](screenshots/m2-4c1e/calendar-390.png) | [View](screenshots/m2-4c1e/calendar-1440.png) |

The browser asserts the unchanged month immediately after reopening, then manually navigates where needed to inspect Jan 30 for the calendar screenshot. Images are synthetic, not live availability or a booking offer. Run the new suite from the host checkout with the existing Compose script mounts and `PLAYWRIGHT_MODULE`; `HEALING_SCREENSHOTS` optionally selects the evidence directory.
