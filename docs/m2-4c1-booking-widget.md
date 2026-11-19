# M2.4C.1 — Booking widget and availability calendar

Implemented 2026-11-19 on `feature/m2-4b-rental-template`. Bridge dependency: M2.4C.1 / 0.3.15 on `codex/m2-3-rental-data-editor`. No main merge or production deployment.

## Presentation contract

The Rental block now renders the booking card instead of the integration placeholder. Initial `from … / night` and Related Rentals read the same allowlisted `minimumNightlyPrice` in `RentalPublicData`. Only `available` projections render a starting price; absent, unconfirmed or stale values render **Check dates for pricing**. Currency formatting is presentation only; the theme does no accommodation, discount, tax, fee or savings arithmetic.

The card supports date selection, Adults / Children with editable counts and explicit + / − controls, capacity limits, known child suitability, calculated totals, inline Price details, loading, retry, unavailable and expired estimates. Defaults are two adults / zero children, with a capacity-one fallback. An estimated total is explicitly not a guest-specific Quote. **Book now** displays a truthful checkout-not-enabled state; it creates no Quote, reservation or payment. `Instant booking` remains the informational design label.

Neutral marketing is **Book direct with Aloha**. The Bridge's display-only 15% calculation is not shown as a discount or an Airbnb comparison; that requires an approved advertising policy.

## Calendar and request lifecycle

[flatpickr 4.6.13](https://github.com/flatpickr/flatpickr/releases/tag/v4.6.13), MIT, is pinned under `assets/lib/flatpickr/`. Files were taken from the official npm package and its published SHA-512 integrity was verified. No runtime CDN, Bootstrap or React dependency was added.

The inline picker lives in a native dialog: one month below 768px and two months above, with day prices, disabled dates, range highlighting and explicit loading/error feedback. The visible month interval plus bounded neighboring days is requested; month navigation fetches more data. No multi-year preload. Cached client days have individual expiry and never remain eligible after expiry.

We deliberately use flatpickr's single-date surface with a small arrival/departure state machine. Its ordinary range-disable behavior would reject a booked checkout date. The state machine applies arrival min/max nights and CTA, occupied-night availability, departure CTD and booking window; a booked date can be selected as checkout without including that night. Known impossible arrivals are disabled. Unknown/expired calendar days fail closed. The server repeats these checks and remains authoritative.

Only the Rental's WordPress ID is used in:

- `GET aloha-bridge/v1/rentals/{id}/availability?from=…&to=…`
- `GET aloha-bridge/v1/rentals/{id}/price-details?checkIn=…&checkOut=…&adults=…&children=…`

Preview carries only the existing authorized preview parameters and WordPress REST nonce. No Guesty credentials, IDs, raw calendar, guest or reservation references enter the page. Fetches use AbortController and generation guards; any change clears the previous amount immediately. The returned Rental/dates/guest composition/expiry are checked before rendering. Details reuse the response and make no new request.

## Sticky and compact layout

Sticky top is measured sticky-header height + fixed admin bar + **20px**, using ResizeObserver and resize/scroll updates. In the tested site this is 88px with the 68px header and 112px with the 92px header. The card has a viewport-bounded internal scroll area for tall details and short windows. Below 768px it stays in document flow. Global pill-button and input styles are reset only inside the widget/calendar. Button symbols and spinners use flex alignment with text.

## Verification

`node scripts/rental-booking-browser.cjs` is run from the local Aloha checkout with the verified compose mounts and `PLAYWRIGHT_MODULE`. It creates synthetic Rentals/media through the existing controlled fixture, intercepts pricing DTOs, aborts external requests and removes its records. The Bridge has independent real-WordPress adapter tests, so browser fixtures contain no fixed production tariff.

Chromium passed **320, 390, 768, 1024 and 1440px**: initial minimum, one/two months, nightly rates, past/CTA/CTD/minimum constraints, booked checkout boundary, unavailable occupied night, range selection, guest +/−/limits, totals/details, Partial/Unavailable/Retry, stale response rejection, request races, keyboard day focus, Escape/focus return, reduced motion, actual sticky geometry and overflow. Seven controlled price requests per viewport cover the state transitions.

- Dedicated widget scenario: CLS **0.0000**, horizontal overflow **false** at every tested width.
- Existing complete public Rental scenario: initial desktop CLS **0.0012**.
- Existing Gallery, Related Rentals and Google Maps tests remain part of regression; public rendering itself makes no Guesty calls.

The screenshots use synthetic data; the historical USD 4542.11 is a fixture, not a current quote.

| State | Mobile 390px | Desktop 1440px |
| --- | --- | --- |
| No dates | [Screenshot](screenshots/m2-4c1/booking-default-390.png) | [Screenshot](screenshots/m2-4c1/booking-default-1440.png) |
| Calendar | [Screenshot](screenshots/m2-4c1/calendar-390.png) | [Screenshot](screenshots/m2-4c1/calendar-1440.png) |
| Calendar loading | [Screenshot](screenshots/m2-4c1/calendar-loading-390.png) | [Screenshot](screenshots/m2-4c1/calendar-loading-1440.png) |
| Calculating | [Screenshot](screenshots/m2-4c1/booking-loading-390.png) | [Screenshot](screenshots/m2-4c1/booking-loading-1440.png) |
| Guests | [Screenshot](screenshots/m2-4c1/booking-guests-390.png) | [Screenshot](screenshots/m2-4c1/booking-guests-1440.png) |
| Price details | [Screenshot](screenshots/m2-4c1/booking-details-390.png) | [Screenshot](screenshots/m2-4c1/booking-details-1440.png) |
| Partial | [Screenshot](screenshots/m2-4c1/booking-partial-390.png) | [Screenshot](screenshots/m2-4c1/booking-partial-1440.png) |
| Unavailable | [Screenshot](screenshots/m2-4c1/booking-unavailable-390.png) | [Screenshot](screenshots/m2-4c1/booking-unavailable-1440.png) |
| Calendar error | [Screenshot](screenshots/m2-4c1/calendar-error-390.png) | [Screenshot](screenshots/m2-4c1/calendar-error-1440.png) |

[Sticky viewport](screenshots/m2-4c1/booking-sticky-1440.png). Full local evidence additionally covers 320/768/1024px.

## Remaining dependencies

The live GET adapter returned an estimated **USD 4121.44** for the previously researched five-night period, with **Partial** detail because tax inclusivity/fee applicability were not fully established. Live current values deliberately differ from historical fixtures. Unsupported provider rules remain explicit unavailable/partial states in the Bridge; the theme supplies no fabricated values. Production latency/load and Guesty CDN media/cron still need staging acceptance; this stage did not import real photos or modify the media queue.

Quote, granular paid guest composition, rate-plan confirmation, checkout, reservations, payment schedule, Stripe and payment authorization remain for the next separately approved stage.

## Changed files

- `theme/aloha-hello/inc/rental.php`: local asset registration, widget renderer and shared Related minimum.
- `theme/aloha-hello/inc/rental-booking.php`: markup, safe frontend configuration and currency presentation.
- `theme/aloha-hello/assets/js/rental-booking.js`: picker/state/request lifecycle, guest controls, details and measured sticky offset.
- `theme/aloha-hello/assets/css/rental-booking.css`: responsive widget/calendar/guest presentation.
- `theme/aloha-hello/assets/lib/flatpickr/{flatpickr.min.js,flatpickr.min.css,LICENSE.md}`: pinned local dependency.
- `scripts/rental-booking-browser.cjs`, `scripts/rental-booking-fixture.php`: controlled widget browser checks.
- `scripts/rental-template-browser.cjs`: regression now checks measured header offset.
- `scripts/rental-template-browser-fixture.php`, `scripts/rental-related-browser.cjs`: confirmed/stale/absent shared-price coverage in Related cards.
- This report and `docs/screenshots/m2-4c1/`: the state screenshots linked above.
