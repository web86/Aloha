# M2.3A.2 — Shared Nearby Places catalog

**Status: implemented in Aloha Content 0.8.0 (2026-11-16).** The catalog follows AlohaWPBridge ADR-0005 and its Rental data contract.

## Ownership

- `plugins/aloha-content` owns one shared private editor-managed place catalog (`nearby_place`) reused by any Rental, and optionally Area/Community editorial sections later. Do not place this catalog in the Aloha Hello theme or duplicate it inside AlohaWPBridge.
- `web86/AlohaWPBridge` owns per-Rental links: the stable catalog place ID, editor-entered integer minutes, mode `min` or `min drive`, and presentation order. Source Guesty coordinates stay in private source DTO; no public exact house marker by default.
- `theme/aloha-hello` renders selected places in the Unit `Close by` section; it does not query Guesty or calculate route times.

## Catalog record

- The `nearby_place` CPT uses the WordPress post ID and title. It has a native admin list, a compact admin form and a protected REST controller. Public queries, permalinks, archive and search are disabled.
- Latitude is between −90 and 90 and longitude between −180 and 180. Drafts may omit one or both. Publish requires a name and both valid coordinates. Enter decimal degrees with a decimal point.
- Category is an optional text label. An optional Area must be a published `area` post. The private `_aloha_nearby_details` meta stores the coordinate pair and category, and `_aloha_nearby_area_id` holds the optional Area ID so the selector can filter it. The editor validates new associations before saving. If a linked Area becomes unavailable, the selector returns `areaId: null` and the admin form shows a warning.
- Each place is independent of Rentals. Future consumers can resolve the current name using the stable ID.
- Native trash and delete affect only the selected place. Future Rental selections can retain an unresolved ID. Public rendering should omit unavailable places and the future editor should flag them for review.

## Admin and selector

Users with `manage_options` can create, edit, publish and delete records under **Nearby Places**. Saves use a nonce scoped to the place and verify the corresponding WordPress post capability. A valid published place can be saved back to draft. Trash and permanent deletion use the native WordPress controls.

`GET /wp-json/aloha-content/v1/nearby-places` is the read-only selector for AlohaWPBridge. Use authenticated WordPress REST requests. With cookie authentication, include a standard `wp_rest` nonce. The selector currently requires `manage_options` and includes published, valid records only.

| Parameter | Behavior |
| --- | --- |
| `search` | Searches the title only. Omit it for all available names. |
| `areaId` | Optional published Area post ID. When supplied, the response contains only matches from that Area. |
| `page` | One-based page number, default `1`. |
| `per_page` | Page size from 1 to 100, default `20`. |

The response is an array in name order, then ID order. `X-WP-Total` and `X-WP-TotalPages` describe pagination. The response is private and non-cacheable.

```json
[
  {"id": 123, "name": "Example beach", "category": "Beach", "areaId": 42, "status": "publish"}
]
```

`id` is an integer. `name` and `status` are strings. `category` and `areaId` are nullable. Anonymous requests get HTTP 401 and authenticated users without catalog access get 403. No record or coordinate fields are available on generic Core REST endpoints. Authorized PHP consumers can call `Aloha\Content\get_nearby_place_coordinates(int $id)` for `{latitude: float|null, longitude: float|null}` or `WP_Error`. This accessor checks the catalog capability and permission to edit the place.

## Rental selection and semantics

- Selection is many Rentals → many Places, ordered individually. Bridge stores `[{placeId,minutes,mode,order}]`, validates places exist and minutes are integer >=0 (or null while draft). Only populated valid time estimates render publicly. Keep `min` as designer-supplied display value, but **do not claim walking time** unless designer explicitly defines it.
- No automatic geocoding or travel-time calculations. Exact home latitude/longitude must not be published or passed to third-party maps without privacy review.
- Editor UX: compact searchable selector with Area prioritization but no hard restriction; selected rows allow minutes/mode, reorder and remove; provide **Add new Nearby place** link requiring catalog capability.
- Future catalog add/change via REST needs nonces, permission checks, data validation, XSS-safe display, and revisions where supported.

## Verification

Run `bash scripts/test-aloha-content.sh` from the Aloha checkout. It uses a disposable WordPress site and tests the Nearby Places lifecycle, validation, capabilities, title search, Area filter and pagination. It also runs the existing Aloha Content and public routing regressions. With Playwright available, set `CONTENT_BROWSER_TEST=1` to exercise the Nearby Places form and verify public URLs cannot expose a place. Set `CONTENT_COMMUNITY_EDITOR_TEST=1` to include the Community sidebar browser suite.

On WordPress 7.1.3 and PHP 8.3, 55 Nearby Places integration checks, 88 general Aloha Content checks, 75 Area server checks, 22 Community server checks and 78 Area/Community HTTP checks passed. Chromium also exercised the Nearby Places form at 390px, verified the authenticated REST response, confirmed anonymous URLs could not expose the place and moved the record to Trash. Existing Gutenberg and Community sidebar browser checks passed. The suite used a separate database and discarded the test site at exit.

## Delivery checkpoints

1. M2.3A.0: approve contract (this document).
2. M2.3A.2: implement `nearby_place` with protected CRUD, validation and tests in this repository.
3. M2.3B: Bridge selects by ID, validating catalog on write and maintaining user order.
4. Theme stage: consume already selected safe DTO, render verified human labels and editor-entered durations.
