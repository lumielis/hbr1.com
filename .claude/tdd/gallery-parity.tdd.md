# TDD evidence — gallery parity

**Date:** 2026-11-01
**Branch:** `gallery-parity`
**Source plan:** the inline `/ecc:plan` output for front-page parity (no
`*.plan.md` file was supplied). Scope was narrowed by the user to main content
pages; gallery was the reported failure and the first slice.

## User journeys

1. As a visitor, I want `/gallery` to look like the original gallery entrance,
   so the ported site feels unchanged.
2. As a visitor, I want `/gallery/{cat}` to show the galleries in the legacy
   three-column layout, with the legacy title truncation and icon pager.
3. As a visitor, I want `/gallery/{cat}/{gal}` to show the gallery's pictures
   inside the legacy frames and bands.
4. As a visitor, I want `/gallery/pic/{id}` to show the 400×300 framed picture
   with the medium previous/next arrows.
5. As a maintainer, I want the pages to be valid HTML5 with working,
   canonical links.

## Checkpoints

| Stage | Commit | Evidence |
|---|---|---|
| RED | `f9e5102` | `test: reproduce the legacy gallery pages and markup helpers` — `LegacyMarkupTest` 10 errors (class missing); `GalleryPageTest` 9 failures, 1 pass. |
| GREEN | `b3c3d4d` | `fix: reproduce the legacy gallery pages with valid HTML5` — 306 tests, 1435 assertions; lint clean; smoke 43/43. |
| Refactor | this commit | CSS duplication removed; 20 targeted tests and lint still pass. |

## Task report

### Task 1 — Legacy markup helpers

- **Summary:** ported `bbc_to_html()` and `cut_string()` into
  `App\Support\LegacyMarkup`, emitting working anchors and rejecting unsafe
  URL schemes.
- **RED:** `php vendor/bin/phpunit --filter LegacyMarkupTest` → 10 errors,
  `Class "App\Support\LegacyMarkup" not found`.
- **GREEN:** `OK (10 tests, 15 assertions)`.
- **Guarantees:** named and bare BBC links become real anchors; unsafe
  schemes never produce `href`; mailto is kept; b/i/u convert; plain text
  decodes entities for `alt`; titles cut at 20 characters, backing up to the
  last space (legacy behaviour).

### Task 2 — Gallery page contract

- **Summary:** rewrote the four gallery views and extended
  `GalleryService`/`GalleryController` with the data the legacy pages printed
  (credits, short titles, the picture's category).
- **RED:** `php vendor/bin/phpunit --filter GalleryPageTest` → 9 failures,
  1 pass (canonical links already held).
- **GREEN:** `OK (10 tests, 141 assertions)`.
- **Guarantees:** legacy headings, bands, frame assets, thumbnail sizes,
  credit lines, truncated titles, icon pagers, the 400×300 picture frame,
  well-formed HTML and no obsolete presentational markup in the gallery
  views.

### Task 3 — Geometry verification

- **Summary:** measured both pages in the browser at the 583 px frame width.
- **Result:** grid 455×143, cards 142×133, frames 142×110, credit rows 13 px,
  pagers 330/350 × 28/37, edit icon at x=281, right arrow at x=437, picture
  arrow at x=457 — matching production to the pixel except a pre-existing
  1 px frame offset.
- **Fixes found by measurement:** UA cell padding (1 px) inflated pager rows
  by 2 px; the global `img:only-child` rule made anchored arrows ignore
  right alignment; `.picture-caption td` out-specified the prev/next cells.

## Test specification

| # | Guarantee | Test | Type | Result |
|---|---|---|---|---|
| 1 | `/gallery` uses GALLERY ENTRANCE and both intro lines | `GalleryPageTest::testEntranceUsesTheLegacyHeadingAndIntro` | integration | PASS |
| 2 | Entrance renders framed 110×82 tiles, credits, canonical links | `…::testEntranceRendersTheLegacyFramedCategoryTiles` | integration | PASS |
| 3 | Category uses GALLERIES ON HBR1, ENTRANCE bands, truncated titles | `…::testCategoryPageUsesTheLegacyHeadingBandsAndTruncatedTitles` | integration | PASS |
| 4 | Category pager is the icon pager, not text | `…::testCategoryPagerUsesTheLegacyIconPager` | integration | PASS |
| 5 | Gallery uses GALLERY MAIN, LEVEL_UP bands, photo frames | `…::testGalleryPageUsesTheLegacyHeadingBandsAndTiles` | integration | PASS |
| 6 | Picture uses the four border gifs, 400×300, medium arrows | `…::testPicturePageUsesTheLegacyFramingAndMediumArrows` | integration | PASS |
| 7 | All four pages are well-formed once script/style are removed | `…::testGalleryPagesAreWellFormedHtml` | integration | PASS |
| 8 | Gallery views emit no obsolete presentational markup | `…::testGalleryViewsUseNoObsoletePresentationalMarkup` | static | PASS |
| 9 | Internal gallery links are canonical | `…::testGalleryLinksAreCanonicalAndInternal` | integration | PASS |
| 10 | Legacy colours and frame assets are present | `…::testGalleryStylesKeepTheLegacyColoursAndFrameAssets` | static | PASS |
| 11 | BBC links are working anchors; unsafe schemes rejected | `LegacyMarkupTest` (10 cases) | unit | PASS |

Commands actually run:

```bash
php vendor/bin/phpunit --filter LegacyMarkupTest
php vendor/bin/phpunit --filter GalleryPageTest
php vendor/bin/phpunit            # 306 tests, 1435 assertions
php tools/lint.php                # 136 files parse
php tools/smoke.php               # 43 checks, 0 failed
```

## Coverage and known gaps

- **Coverage tool:** no Xdebug or PCOV driver is installed, so a coverage
  percentage could not be produced. The tests are behavioural (rendered
  output and measured geometry), not line-coverage driven.
- **Parity CLI:** the planned `tools/parity.php` / screenshot-diff harness is
  not built yet; this slice used direct browser measurements and the captured
  production pages. It is the next TDD cycle.
- **Remaining pages:** listen, playlist, mixes, muses, request, guestbook,
  party, chat, ticker and iphone are still unverified; see `docs/PARITY.md`.
- **Deliberate deviation:** gallery edit icons target `/admin` until W8.

## Merge evidence

The two checkpoint commits carry the RED and GREEN messages above. If they are
squashed, copy this report into the squash commit body.
