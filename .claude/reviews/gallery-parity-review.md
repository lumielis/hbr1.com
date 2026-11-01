# Code Review: `gallery-parity`

**Reviewed:** 2026-11-01
**Scope:** the working tree is clean, so this reviews the branch range
`main..HEAD` (commits `f9e5102`, `b3c3d4d`, `295ef0d`).
**Decision:** **BLOCK** — one critical stored-XSS vector and one content
regression must be fixed before merge.

## Summary

The gallery reproduction is accurate and the tests are meaningful, but the
credit renderer trusts attributes on allowed inline tags and is printed with
`|raw`, which turns legacy gallery credits into a stored-XSS surface. The
gallery info line also bypasses the same renderer, so twelve galleries show
raw `[a=…]` markup.

## Findings

### CRITICAL

**C1 — Stored XSS through allowed inline tags.**
`app/Support/LegacyMarkup.php:35`, rendered raw at
`views/web/gallery.clarity.html:42`, `gallery_category.clarity.html:45`,
`gallery_gallery.clarity.html:39`, `gallery_picture.clarity.html:44`.

`strip_tags($input, '<b><i><u><br>')` keeps attributes on the allowed tags.
Verified payloads:

```text
[a=http://ok.example]<b onclick="alert(1)">x</b>[/a]
  -> <a href="http://ok.example" …><b onclick="alert(1)">x</b></a>

[a=http://ok.example]<br onmouseover="alert(1)">x[/a]
  -> <a href="http://ok.example" …><br onmouseover="alert(1)">x</a>
```

The credit HTML is emitted with `|raw`, so the handler executes. Gallery
credits are legacy user-submitted content. **Fix:** strip attributes from the
allowlisted tags before returning, e.g.
`preg_replace('!<(b|i|u|br)\b[^>]*>!i', '<$1>', $input)`, and add RED/GREEN
tests for both payloads.

### HIGH

**H1 — Gallery info is escaped instead of rendered.**
`app/Services/GalleryService.php:87` and
`views/web/gallery_gallery.clarity.html:23`.

Twelve galleries store BBC markup in `GAL_Info` (e.g. gallery 37:
`Fotos and design by [a=http://www.jordanoptix.de]Jordan Optix[/a]`). The view
uses `{{ gallery:info }}`, so the page prints the brackets literally, while
production printed a link (broken, but visible as text). **Fix:** add
`infoHtml`/`infoPlain` through `LegacyMarkup` and render `infoHtml|raw` after
C1 is fixed; add a test that finds a BBC-bearing gallery and asserts an
anchor.

### MEDIUM

**M1 — Protocol-relative links are accepted.**
`app/Support/LegacyMarkup.php:111`. Any string starting with `/` is trusted,
including `//evil.example`, producing an off-site link. Restrict to
`preg_match('#^/(?!/)#', $url)`.

**M2 — Dead per-gallery COUNT query.**
`app/Services/GalleryService.php:89` still counts pictures for every gallery
tile, and `:90`/`:115` still expose `locked`; neither is rendered any more.
This adds one query per tile for nothing. Remove them and update
`tests/GalleryServiceTest.php` to call `countPicturesFor()` directly, or keep
`count` only if the W8 editor will consume it.

### LOW

**L1 — Picture title bypasses the legacy renderer.**
`views/web/gallery_picture.clarity.html:44` uses `{{ picture:title }}`;
production used `bbc_to_html()` for `GAP_Title`. No current row carries
markup (checked 500), so this is latent only.

**L2 — Character-based truncation.**
`LegacyMarkup::cut()` counts characters (`mb_*`); `cut_string()` counted
bytes. Current gallery titles are ASCII, so output is identical today.

**L3 — Alt text differs from production.**
Category tiles use the category name in `alt`; production used the file name
and raw copyright. Not visible, and the raw-BBC alt is better left behind.

## Validation

| Check | Result |
|---|---|
| Type check | Skipped (PHP; no separate type-check step) |
| Lint (`php tools/lint.php`) | Pass — 136 files |
| Tests (`php vendor/bin/phpunit`) | Pass — 306 tests, 1435 assertions |
| Smoke (`php tools/smoke.php`) | Pass — 43/43 |
| Coverage (`XDEBUG_MODE=coverage … --coverage-text`) | **Fail overall** — 67.95 % lines (target 80 %). Changed code is 95–97 % lines (`LegacyMarkup` 95.74 %, `GalleryService` 97.35 %, `GalleryController` 95.16 %). |

Passing tests do not cover C1 or H1, which is why they escaped: the suite
never feeds markup through the credits and never checks a BBC-bearing info
line.

## Files Reviewed

- `app/Support/LegacyMarkup.php` (added)
- `app/Services/GalleryService.php` (modified)
- `app/Controllers/Web/GalleryController.php` (modified)
- `views/layouts/page.clarity.html` (modified)
- `views/web/gallery.clarity.html` (modified)
- `views/web/gallery_category.clarity.html` (modified)
- `views/web/gallery_gallery.clarity.html` (modified)
- `views/web/gallery_picture.clarity.html` (modified)
- `htdocs/style/client.css` (modified)
- `tests/LegacyMarkupTest.php` (added)
- `tests/GalleryPageTest.php` (added)
- `docs/PARITY.md`, `docs/README.md`, `.claude/tdd/gallery-parity.tdd.md` (docs)

## Addendum — resolved on `guestbook-parity` (2026-11-01)

- **C1 fixed.** `LegacyMarkup` now rebuilds the allowed inline tags and drops
  every attribute, so `<b onclick>` and `<br onmouseover>` cannot survive.
  `LegacyMarkupTest::testAllowedTagsLoseTheirAttributes`.
- **H1 fixed.** `GalleryService` exposes `infoHtml` and the gallery view
  renders it after sanitizing. `GalleryPageTest::testGalleryInfoRendersTheLegacyCreditMarkup`.
- **M1 fixed.** Protocol-relative URLs are rejected.
  `LegacyMarkupTest::testProtocolRelativeUrlsAreNotTreatedAsLocal`.
- **M2 still open.** The per-gallery `COUNT`/`locked` fields remain unused by
  the views.
- **New issue fixed while porting the guestbook:** entry content was printed
  raw. It is now allowlisted and autolinked `formatGuestbookContent()`, with
  tests for scripts, attributes and unsafe anchors.

Re-validation: 325 tests / 1552 assertions pass, lint parses 137 files, smoke
is 43/43, coverage is 73.61 % overall. No critical or high findings remain;
the decision becomes **APPROVE with comments** once M2 is triaged.
