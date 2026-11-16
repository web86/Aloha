# M2.3A — Shared Nearby Places registry contract

**Status: approved product direction / implementation pending (2026-11-16).** This document is part of the M2.3 design handoff with `web86/AlohaWPBridge` ADR-0005 and `docs/m2-3-rental-data-contract.md`. It does not implement schema, change routing, publish locations or modify theme markup.

## Ownership

- `plugins/aloha-content` owns one shared private editor-managed place catalog (`nearby_place`) reused by any Rental, and optionally Area/Community editorial sections later. Do not place this catalog in the Aloha Hello theme or duplicate it inside AlohaWPBridge.
- `web86/AlohaWPBridge` owns per-Rental links: the stable catalog place ID, editor-entered integer minutes, mode `min` or `min drive`, and presentation order. Source Guesty coordinates stay in private source DTO; no public exact house marker by default.
- `theme/aloha-hello` renders selected places in the Unit `Close by` section; it does not query Guesty or calculate route times.

## Catalog record (proposed `nearby_place` CPT, no public permalink)

- WordPress post ID is canonical; title is human-readable place name. Use `public=false`, `show_ui=true`, `show_in_rest=true` with explicit capabilities and `publicly_queryable=false`, `exclude_from_search=true`, `has_archive=false`, `rewrite=false`. The catalog must not create a public URL or a second Rental CPT.
- Required validated coordinates: decimal latitude [-90,90], longitude [-180,180], both nullable until editor completes record; prevent using invalid coordinates. Coordinates of a public landmark may be suitable to display, unlike exact home coordinates.
- Optional category label and `area` post ID; area must reference an existing published Area when present. No duplicated Community–Area models.
- Reusable independent of any one Rental; editing a place name updates all rendered references through WP ID.
- Delete/trash behavior: preserve Rental selections as unresolved IDs without fabricating new places; hide missing/unpublished catalog entries in public rendering, show actionable editor warning. Do not cascade delete Rental content.

## Rental selection and semantics

- Selection is many Rentals → many Places, ordered individually. Bridge stores `[{placeId,minutes,mode,order}]`, validates places exist and minutes are integer >=0 (or null while draft). Only populated valid time estimates render publicly. Keep `min` as designer-supplied display value, but **do not claim walking time** unless designer explicitly defines it.
- No automatic geocoding or travel-time calculations. Exact home latitude/longitude must not be published or passed to third-party maps without privacy review.
- Editor UX: compact searchable selector with Area prioritization but no hard restriction; selected rows allow minutes/mode, reorder and remove; provide **Add new Nearby place** link requiring catalog capability.
- Future catalog add/change via REST needs nonces, permission checks, data validation, XSS-safe display, and revisions where supported.

## Non-goals of M2.3A.0

No actual CPT registration or migration, no public `Close by` rendering, no Guesty writes, no external geocoding, no frontend distance claim, no changes to WP installation data or existing Area and Community records.

## Delivery checkpoints

1. M2.3A.0: approve contract (this document).
2. M2.3A.2: implement `nearby_place` with protected CRUD, validation and tests in this repository.
3. M2.3B: Bridge selects by ID, validating catalog on write and maintaining user order.
4. Theme stage: consume already selected safe DTO, render verified human labels and editor-entered durations.
