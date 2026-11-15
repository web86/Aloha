# Aloha Hello — Rental gallery design plan (aligned with AlohaWPBridge M2.2.2)

**Status: documentation only, 2026-11-15.** Feature branch `codex/m1-6-rental-gallery` based on `codex/m2-0-local-integration`. Do not implement or merge until plugin source/editor model and GET-only Guesty capability audit are reviewed.

## Wireframe

User-supplied screenshot `Снимок экрана 2026-11-15 в 23.13.12.png` references a listing gallery with **Interior**, **Outside**, **Neighborhood**, optional **3D Tour** tabs and five-photo mosaic: 1 large left, 4 small right, last tile **Show all N photos**. No overlay video on the first photo in Rental. Count N applies to the active category's **published/selected local attachment photos**. Only show 3D Tour if a verified permitted tour exists; never fabricate one.

## Existing reusable implementation

Existing code in `theme/aloha-hello`:
- `inc/media-gallery.php` — registration of `aloha/media-gallery`, attachment-ID render, 5-tile mosaic, optional Area-only film and Show all N.
- `blocks/media-gallery/block.json`, `editor.js`, `render.php` — native Gutenberg gallery, editing, photo order, native Media Library ALT/captions.
- `assets/css/media-gallery.css` — desktop + responsive mosaics.
- `assets/js/media-dialog.js` and `assets/css/media-dialog.css` — fullscreen accessible gallery dialog, keyboard/swipe.
- `inc/area.php` — Area template plumbing.

Do not fork all these into a second custom gallery. Reuse or extend with a thin Rental-specific wrapper/mode, ideally driven by validated WordPress attachment-ID groups saved by AlohaWPBridge, not by Guesty raw URLs.

## Presentation ownership

AlohaWPBridge owns durable editor-chosen groups/order/visibility and guarded photo sideloads. Theme owns strictly SSR markup, styles, responsive/accessible tabs, fullscreen viewer and editorial Gutenberg rendering; never call Guesty APIs directly from the theme.

- Tabs: `Interior`, `Outside`, `Neighborhood`, conditional `3D Tour`; hide empty groups; active tab switches gallery and viewer source. Each category can have 1–N local images. Five tiles only when at least five, with existing honest 1–4 layouts otherwise. No automatic bulk imports of source metadata records (Kaapuni has 237).
- Preserve `aloha/media-gallery` original Area and Community behavior; film/video overlay stays Area-only where previously configured. Rental must never show Area film overlay.
- Title/nickname header near the gallery like the mock; do not automatically insert blocks or rewrite already existing Rental post content/editorial fields. An opt-in Rental block or dedicated `single-rental.html` needs an explicit Gutenberg/Theme ownership decision before writing.
- Use native responsive WordPress image sizes/srcset, accessible keyboard tabs, controls/focus, prevent irrelevant JS/REST in other CPTs; respect privacy of third-party 3D embedding.
- Do not implement Save or Share buttons until functionality is agreed; the wireframe is visual context only.

## Readiness, dependencies & tests

Wait for Bridge branch `codex/m2-2-2-rental-editor-gallery` to define validated versioned, revision-aware curated gallery attachment-ID model and confirm optional 3D availability via read-only Guesty Open API. Audit endpoints and GET-only requirement documented in:
https://github.com/web86/AlohaWPBridge/blob/codex/m2-2-2-rental-editor-gallery/docs/m2-2-2-rental-editor-gallery-plan.md

Visual QA against owner screenshot at 320, 390, 768, 1440px; verify old Area video/gallery and Community behavior unaffected, no empty tabs/fake tour, proper handling of 1–4 images, and 237 unimported photos are not shipped to public page. No auto publish or Guesty write endpoints. Commit/push separate feature branch only after implementation and tests, do not merge `main`.
