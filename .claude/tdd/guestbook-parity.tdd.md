# TDD evidence — guestbook parity

**Date:** 2026-11-01
**Branch:** `guestbook-parity` (stacked on `gallery-parity`)
**Source:** the user's *"please continue with /guestbook"* under the
front-page parity plan and its HTML5/working-links decisions.

## User journeys

1. As a visitor, I want `/guestbook` to look like the original guestbook:
   welcome heading, intro, splitter-fenced entries, obfuscated e-mail links,
   the same dates and the legacy pager.
2. As a visitor, I want `/guestbook/submit` to be its own page with the
   legacy form, and validation errors to bring me back there.
3. As a maintainer, I want the stored guestbook HTML rendered safely, with
   links the legacy `formatHyperlinks()` would have produced.

## RED

Run before implementation:

```text
php vendor/bin/phpunit --filter "LegacyMarkupTest|GalleryPageTest|GuestbookPageTest"
ERRORS! Tests: 39, Assertions: 202, Errors: 6, Failures: 11.
```

- 6 errors: `LegacyMarkup::formatGuestbookContent()` / `xorDecryptHex()` did
  not exist.
- 11 failures: attribute-bearing tags survived (C1), `//evil.example` was
  accepted (M1), gallery info printed raw BBC markup (H1), and the listing /
  submit page / pager / sanitizer did not match production.

The RED run was not committed separately; this branch records it here and in
the session transcript. The gallery branch carries the separate RED/GREEN
checkpoint commits.

## GREEN

```text
php vendor/bin/phpunit            # OK (325 tests, 1552 assertions)
php tools/lint.php                # 137 files parse
php tools/smoke.php               # 43 checks, 0 failed
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text
                                  # overall 73.61 % lines (1858/2524)
```

Browser measurements (620 px viewport, 583 px frame):

| Element | Local | Production |
|---|---|---|
| h2 top | 54 | 54 |
| first splitter top | 139 | 139 |
| first entry top | 151 | 151 |
| document height | 2704 | 2704 |
| pager top | 2631 | 2625 |
| signature top | 2684 | 2678 |
| submit input left / width | 152 / 283 | 152 / 283 |

The pager sits 6 px lower and the submit button 3 px lower; the rest is
font-metric rounding.

## Test specification

| # | Guarantee | Test | Type | Result |
|---|---|---|---|---|
| 1 | Listing uses the legacy heading, intro, submit link and signature | `GuestbookPageTest::testListingUsesTheLegacyHeadingAndIntro` | integration | PASS |
| 2 | Entries use splitters and Europe/Berlin dates labelled `(utc)` | `…::testEntriesUseTheLegacyLayout` | integration | PASS |
| 3 | E-mail links use the XOR blob and `crypt.js` | `…::testEntriesWithEmailUseTheObfuscatedMailtoLink` | integration | PASS |
| 4 | Pager uses `Nav_*` markup and canonical offsets | `…::testPagerUsesTheLegacyNavMarkup` | integration | PASS |
| 5 | Submit page reproduces the legacy form | `…::testSubmitPageUsesTheLegacyForm` | integration | PASS |
| 6 | Validation errors render the submit form | `…::testValidationErrorsRenderTheSubmitForm` | integration | PASS |
| 7 | Both pages are well-formed HTML | `…::testGuestbookPagesAreWellFormedHtml` | integration | PASS |
| 8 | Guestbook views emit no obsolete markup | `…::testGuestbookViewsUseNoObsoletePresentationalMarkup` | static | PASS |
| 9 | Links are canonical | `…::testLinksAreCanonical` | integration | PASS |
| 10 | Field validation branches | `GuestbookServiceTest` (7 cases) | unit | PASS |
| 11 | Content keeps `<br>`/entities and autolinks URLs, e-mails, domains | `LegacyMarkupTest::testGuestbookContent*` | unit | PASS |
| 12 | Scripts, attributes and unsafe anchors are dropped | `LegacyMarkupTest::testGuestbookContentDropsScriptsAndUnsafeAnchors` | unit | PASS |
| 13 | XOR matches the production key | `LegacyMarkupTest::testXorHandshakeMatchesTheLegacyKey` | unit | PASS |

## Review findings resolved in this branch

- **C1 (critical, stored XSS):** allowed tags are rebuilt without attributes;
  covered by `testAllowedTagsLoseTheirAttributes`.
- **H1 (high, gallery info):** `infoHtml` is rendered; covered by
  `GalleryPageTest::testGalleryInfoRendersTheLegacyCreditMarkup`.
- **M1 (medium, protocol-relative URLs):** rejected; covered by
  `testProtocolRelativeUrlsAreNotTreatedAsLocal`.
- **M2 (medium, dead per-gallery COUNT):** still open.

## Coverage and known gaps

- Overall 73.61 % lines, still below the 80 % target; the gap is mostly the
  pre-existing controllers/services outside these slices.
- `GuestbookService` 61.11 %: `submit()` (writes to the live table) and
  `matchesFilter()` (data-driven filter rows) are intentionally untested.
- A successful post still shows the thanks page; production returned to the
  listing with a message-log block. Recorded in `docs/PARITY.md`.
