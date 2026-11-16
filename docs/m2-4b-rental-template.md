# M2.4B — Rental template, first working version

**Status: implemented on isolated GitHub branch, not integrated into the user's local WP or merged.**

Repo: web86/Aloha; branch: feature/m2-4b-rental-template. Depends on web86/AlohaWPBridge, branch feature/m2-4a-rental-public-data. Both branches must be active in the test WordPress site. The current unfinished Codex M2.3C.1 gallery editor work is untouched.

## Implementation

- theme/aloha-hello/templates/single-rental.html — native fixed-layout block template, preserving site Header/Footer.
- theme/aloha-hello/inc/rental.php — local-only presentation, reading AlohaWPBridge RentalPublicData. No calls to Guesty, no DB writes.
- theme/aloha-hello/assets/css/rental.css — responsive typography and sections using existing Aloha theme tokens.
- theme/aloha-hello/assets/js/rental-gallery.js — automatic tab filters. It reuses the existing Aloha media dialog and mosaic markup.
- scripts/rental-template-smoke.php — WP-CLI read-only smoke.

Page sections: heading, local facts and USP; five-photo mosaic with auto labels; editable Highlights; About; verified sleeping spaces with bed-type icons; amenities grouped by Guesty names (no icons); selected Nearby Places and manual minutes; selected Airbnb / Booking.com Guests said reviews with original ratings and attribution; house rules including quiet hours and local check-in/out; and up to three related published Rentals sharing Area.

Missing or unimported photos never become public photo URLs. With fewer than five imported images, the mosaic adapts. If no gallery images are available, a valid local Featured Image is a fallback. Optional sections disappear when there are no safe values. Raw source addresses, home coordinates, provider IDs and Guesty credentials do not appear in HTML.

The booking card is explicitly a placeholder; no live prices, fake values, API calls, booking or payments. Dynamic booking is M2.4C.

## Public and preview rules

RentalPublicData::get() is strictly published, mapped and not password protected. RentalPublicData::preview() requires native WordPress Preview context, edit_post capability, matching preview_id and the WordPress preview_nonce. This allows authorized preview of the existing draft without publishing it. Never loosen the preview permissions to is_preview() alone.

## Local verification

1. Preserve Codex's currently uncommitted gallery work. Do not hard reset or switch over it. Fetch the two feature branches in separate Git worktrees or a disposable local clone.
2. Validate PHP 8.3/8.4 syntax for theme/aloha-hello/inc/rental.php and AlohaWPBridge src/RentalPublicData.php and the smoke PHP file.
3. Run Bridge fixture: php tests/rental-public-data-fixture.php; then the existing Bridge/Aloha Content smoke suites.
4. On a disposable/backed-up WordPress install using both branches, activate Aloha Hello and AlohaWPBridge and run: wp eval-file scripts/rental-template-smoke.php (from the Aloha checkout). Read-only.
5. Open the real mapped Rental Draft using WordPress's native Preview. Verify the rendered sections and gallery filters, especially 1/2/5+ imported photos, and a selected Guest review. Never force Publish or start Guesty Sync/downloads.
6. Browser QA widths: 320, 390, 768, 1440 pixels. Confirm dialog focus/keyboard, mobile layout, stale/absent facts, no private source leaks, and no provider API calls on page load.
7. If a saved wp_template customization overrides single-rental.html, inspect it rather than rewriting its DB record automatically.

## Limitations

No full WP/Chromium integration or theme-specific PHP lint has run in this assistant's remote environment. The added WP-CLI test has not been executed against the user's actual WP site. Evaluate before merge. Related homes and public review reuse terms need operational review for production.

For M2.4B acceptance, the native draft preview should show locally sourced data without a single Guesty request and without altering any WordPress data. The template should not be publicly visible until the Rental is deliberately published.
