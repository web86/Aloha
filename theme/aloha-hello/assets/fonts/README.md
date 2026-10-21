# Local font distribution

Retrieved on 2026-10-22 from the official Google Fonts CSS API / Google-hosted WOFF2 delivery. The binaries are unchanged Latin subsets; the original OFL notices are included alongside them. No browser cache extraction or runtime CDN is used.

| File | Registered face | Bytes | SHA-256 |
| --- | --- | --- | --- |
| `instrument-serif-latin-400-normal.woff2` | Instrument Serif 400 normal | 15040 | `60c06664b5a95c7de6cc3e00d1f9034d78bd1e40b564016b241674449a067d4d` |
| `instrument-serif-latin-400-italic.woff2` | Instrument Serif 400 italic | 15684 | `6ee678c33f388dd7ba59700ebea635deb98821baafd817b09891f7927177f702` |
| `outfit-latin-variable.woff2` | Outfit 400–700 normal | 32228 | `92684e4acde79ef07758cd09380b7e01e9824d8b061eddeda046f78c166d7b12` |

Outfit's original variable font contains a `wght` axis of 100–900; the theme exposes only the required 400–700 range. Instrument Serif faces are static 400. All faces use `font-display: swap` through `theme.json`.

Sources:

- [Google Fonts delivery request](https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@400;500;600;700&display=swap), Latin WOFF2 response for a modern browser.
- [Instrument Serif normal WOFF2](https://fonts.gstatic.com/s/instrumentserif/v5/jizBRFtNs2ka5fXjeivQ4LroWlx-6zUTjnTLgNs.woff2), [italic WOFF2](https://fonts.gstatic.com/s/instrumentserif/v5/jizHRFtNs2ka5fXjeivQ4LroWlx-6zAjjH7Motmp5g.woff2).
- [Outfit variable WOFF2](https://fonts.gstatic.com/s/outfit/v15/QGYvz_MVcBeNP4NJtEtqUYLknw.woff2).
- [Instrument Serif OFL](https://github.com/google/fonts/blob/main/ofl/instrumentserif/OFL.txt), [upstream project](https://github.com/Instrument/instrument-serif).
- [Outfit OFL](https://github.com/google/fonts/blob/main/ofl/outfit/OFL.txt), [upstream project](https://github.com/Outfitio/Outfit-Fonts).

Both families are distributed under SIL Open Font License 1.1, which permits redistribution and embedding with the notices preserved. See `Instrument-Serif-OFL.txt` and `Outfit-OFL.txt` for the full terms and original copyright notices. These font licenses are separate from the theme code license.

The current site is English. Characters outside these font subsets, including Cyrillic, extended Latin and U+02BB, use the declared system fallbacks. Check coverage and obtain additional licensed subsets before expanding languages or Hawaiian diacritics; do not fabricate missing glyphs.
