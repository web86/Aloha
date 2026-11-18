# M2.4B.2 — Rental Page Layout & Content Refinement

## Visual reference and components

The desktop reference is `макеты/Design v2 — Home page (unit)@1x.png` (1440 × 8000). The companion archive on disk is `Design v2 — Home page (unit)-html.zip`. Its `UnitDesign.dc.html` includes plan-view King, Queen and Single bed shapes. The theme uses those simple SVG geometries with theme colors and existing SVG symbols for other bed types. The archive also contains sample photography, logos and its own React assets. The production template uses WordPress attachments, existing Aloha typography and colors, and the theme's Embla 8.6.0, already used by Beaches near Mauna Lani.

Save and Share keep fixed button geometry and announce their status through a positioned live region. The existing M2.4B.1 local photo mosaic remains above the page grid. The eight facts sit beside the sticky Booking Widget on desktop and above it on phones. At 1440 px the theme's 1200 px content grid gives 768 px to the main column, with a 56 px gap and 376 px for Booking. Beds appear alongside Bedrooms, following the reference. Lanai, View, To the beach and Parking show an em dash until the DTO can supply values.

## Rental content

- **Highlights** retain the editor's order and use locally imported images. Embla adds mouse drag, touch, arrow controls and keyboard navigation. Controls disappear when all cards fit. Reduced-motion navigation uses instant transitions. The server renders every card, and the track can be scrolled without JavaScript.
- **About** uses published Community, then published Area, then the generic title. The WordPress Excerpt appears first and only once. The remaining text comes from the existing Guesty or Custom mode. Long descriptions keep an introduction visible, and a button with `aria-expanded` and `aria-controls` reveals the rest. Short descriptions appear in full.
- **Sleeping** shows the room names and bed types and quantities in the DTO. **Amenities** keep the original names and groups. The enhanced view initially shows six groups and up to four items in each. All groups and the entire description are present in the HTML and remain readable with JavaScript disabled.

## Close by and Google Maps

Bridge 0.3.10 adds category and a normalized coordinate pair to the existing Nearby projection. It preserves editorial order, and places with no travel time appear without a time label. The projection accepts only selected, published, unprotected Nearby Place records. Aloha Content validates each coordinate pair. A record with legacy invalid coordinates stays in the list and has no marker. Private Guesty location data, raw metadata and unselected catalog records never reach the map. The catalog and its privileged coordinate reader retain their access checks.

Supply `ALOHA_GOOGLE_MAPS_BROWSER_KEY` and `ALOHA_GOOGLE_MAPS_MAP_ID` through environment-specific WordPress configuration. Use a JavaScript map ID and restrict the browser key in Google Cloud to the Maps JavaScript API and the permitted site origins. The script sends an origin-only referrer; configure the restrictions to match that origin. The browser key is visible to the visitor by design. [Google loading guide](https://developers.google.com/maps/documentation/javascript/load-maps-js-api), [advanced marker setup](https://developers.google.com/maps/documentation/javascript/advanced-markers/migration).

The local setup had neither a live Maps configuration nor a consent manager integration. Google Maps loads automatically as Close by approaches the viewport. The browser's Global Privacy Control signal suppresses the load. The list remains available with a missing configuration, no eligible points, a blocked request or an authorization error. Multiple markers determine the visible bounds. List buttons and markers share selection, numbering and a title popup.

## Visual differences

The desktop reference uses a supplied photograph set, example rental copy and a booking mock. The implementation uses local media, actual Rental data and the existing Booking Widget content. The specified desktop layout places the map to the right of the list. A Rental-specific mobile reference was unavailable, so the mobile layout adapts the desktop components to a stacked flow. The local screenshots use fixture images and a map fallback.

## Reproducible checks

- `scripts/rental-template-browser.cjs` exercises layout at 320, 390, 768, 1024 and 1440 px, button geometry, sticky Booking, carousel input, description modes, disclosures, nearby ordering, private data boundaries and WordPress Preview. It uses local fixtures and mocks the Maps JavaScript API for success, network failure and authorization failure. It also checks that Google is never requested before the section approaches the viewport or when the browser opts out.
- `scripts/rental-gallery-browser.cjs` exercises the M2.4B.1 gallery and records network and layout metrics.
- Bridge includes the standalone `tests/rental-public-data-fixture.php` and its Docker smoke suite.

## Local results

Chromium 151 passed the five target widths. Save and Share retained their geometry with keyboard activation and showed no horizontal overflow. The gallery regression kept its five initial mosaic requests and passed variants 1–5, local lightbox, reduced motion and slow/failed image loading. Cold-context gallery CLS stayed below 0.05 across the four gallery test widths. PHP and JavaScript syntax, the Bridge smoke suite, the standalone DTO fixture and native WordPress Preview passed.

All six read-only SHA-256 checks matched for each of Rental 186 and 365. The synthetic listing, places, attachments and mappings were removed by the test harness. The browser evidence is in `.local/evidence/m2-4b2/` (`public-1440.png`, `public-390.png`, `close-by-map-mock.png` and `gallery/gallery-performance.json`). The screenshots were compared to the original desktop PNG for alignment, section order, card proportions and the planned map placement. Live Google Maps was unavailable in the local environment. No live Booking or Guesty operation was used to validate the page.
