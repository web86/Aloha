# M2.4B — Rental template, first working version

**Status:** verified together with M2.4A in local WordPress. Both checkouts were mounted from isolated Git worktrees.

Repo: web86/Aloha; branch: feature/m2-4b-rental-template. Depends on web86/AlohaWPBridge, branch feature/m2-4a-rental-public-data. Both branches must be active in the test WordPress site. The Bridge integration includes the published M2.3C.1 gallery.

## Implementation

- theme/aloha-hello/templates/single-rental.html — native fixed-layout block template, preserving site Header/Footer.
- theme/aloha-hello/inc/rental.php — local-only presentation, reading AlohaWPBridge RentalPublicData. No calls to Guesty, no DB writes.
- theme/aloha-hello/assets/css/rental.css — responsive typography and sections using existing Aloha theme tokens.
- theme/aloha-hello/assets/js/rental-gallery.js — automatic tab filters. It reuses the existing Aloha media dialog and mosaic markup.
- scripts/rental-template-smoke.php — WP-CLI read-only smoke.

Page sections: heading, local facts and USP; five-photo mosaic with auto labels; editable Highlights; About; verified sleeping spaces with bed-type icons; amenities grouped by Guesty names (no icons); selected Nearby Places and manual minutes; selected Airbnb / Booking.com Guests said reviews with original ratings and attribution; house rules including quiet hours and local check-in/out; and up to three related published Rentals sharing Area.

Missing or unimported photos never become public photo URLs. With fewer than five imported images, the mosaic adapts. The gallery is omitted when it has no visible mapped images. It does not reuse Featured Image as a gallery fallback because that could display a deliberately hidden photo. Optional sections disappear when there are no safe values. Raw source addresses, home coordinates, provider IDs and Guesty credentials do not appear in HTML.

The booking card is explicitly a placeholder; no live prices, fake values, API calls, booking or payments. Dynamic booking is M2.4C.

## Public and preview rules

RentalPublicData::get() is strictly published, mapped and not password protected. RentalPublicData::preview() requires native WordPress Preview context, edit_post capability, matching Rental ID and a valid WordPress nonce. Core autosave links keep their native keys. Bridge adds a separate signed pair to the native owner-draft link when core omits autosave keys. This allows authorized preview of the existing draft without publishing it. Never loosen the preview permissions to is_preview() alone.

## Local verification

The integration mounts the theme and Bridge worktrees read-only on the local WordPress 7.1.3 site. Aloha Hello reports version 0.7.3 and Bridge reports 0.3.5. WordPress resolved `aloha-hello//single-rental` from the theme file.

Run `php tests/rental-public-data-fixture.php` from the Bridge checkout. Run `wp eval-file scripts/rental-template-smoke.php [published-fixture-id]` from the Aloha checkout with both components loaded. To run the browser scenario from the local Aloha root, bind this branch's `scripts` directory at `/var/www/m24-tests` in the WP-CLI service and run `node <theme-worktree>/scripts/rental-template-browser.cjs`. The script creates a uniquely named mapped synthetic Rental pair and an empty Rental, verifies published and draft rendering, and removes the fixtures. Set `RENTAL_TEMPLATE_SCREENSHOTS` to capture screenshots.

The read-only WordPress smoke found zero Guesty requests. Chromium verified the five-image local mosaic and ordering, label filters, the shared lightbox, original review scores and attribution, hidden/empty sections, privacy markers, and 320, 390, 768 and 1440 px. Native editor Preview rendered the synthetic draft, and anonymous, wrong-ID, invalid-nonce and missing-nonce requests withheld its content. There was no database template override. The native Preview action from the Rental list opened local draft 186 as a read-only GET.

Deployment and booking/pricing remain separate from this local verification.
