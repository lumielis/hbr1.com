# Front-page parity

The port is judged against **production**, not against the repository's legacy
templates. The two have drifted: production's gallery entrance says
`GALLERY ENTRANCE`, while `htdocs/cgi-htm/client/galleries.htm` says
`PHOTO GALLERIES`. Captures of the live pages are the reference.

## Policy

- **Look like the legacy page.** Same headings, bands, colours, frame art,
  thumbnails, spacing and pagination.
- **Valid HTML5.** No obsolete presentational markup in `views/web/*`: no
  `<font>`, `bgcolor`, `align`, `background`, `cellspacing`, `cellpadding` or
  `border` attributes. Geometry that must not move is expressed in
  `htdocs/style/client.css`.
- **Working links.** Legacy credit markup is rendered as real anchors. The
  old `href\="..."` output was a defect and is not reproduced. Link schemes
  are limited to `http`, `https`, `mailto` and root-relative paths.
- **Canonical hrefs.** Internal links target the ported routes
  (`/gallery/{cat}/{gal}`), not `main.php?…`; the legacy entry points remain
  301s.
- **Frame pieces are out of scope.** `/`, `/topframe` and `/navigation` were
  confirmed correct and are not reworked.

## Gallery — verified 2026-11-01

Reference captures: `temp/prod-gallery.html`, `temp/prod-gallery-cat.html`,
`temp/prod-gallery-main.html`, `temp/prod-gallery-pic.html`.

| Route | Production reference | Result |
|---|---|---|
| `/gallery` | `main.php?module=gallery` | Match. `GALLERY ENTRANCE`, welcome lines, 3-column 142×110 `category_select_border.gif` grid, 110×82 thumbs, 13 px row gaps, credits, `body_bg2.gif` well. |
| `/gallery/{cat}` | `…&show=cat&catid=N` | Match. `GALLERIES ON HBR1`, ENTRANCE bands, 20-character titles in `#88CCAA`, `gallery_select_border.gif`, 350 px icon pager. |
| `/gallery/{cat}/{gal}` | `…&show=gal&galid=N` | Match. `GALLERY MAIN`, LEVEL_UP bands, 6 `photo_select_border.gif` tiles, 10 px card spacers, 330 px icon pager. |
| `/gallery/pic/{id}` | `…&show=pic&picid=N` | Match. `GALLERY PICTURE`, LEVEL_UP bands, 400×300 preview inside the four border gifs, `#88EE00` title, medium arrows right-aligned at the legacy x-position. |

Measured with the real browser at the 583 px frame width: grid 455×143,
cards 142×133, frames 142×110, credit rows 13 px, pager 330/350 wide and 28/37
tall, with a 1 px global vertical offset inherited from the shared frame.

### Intentional deviations

| Deviation | Reason |
|---|---|
| Edit icons point at `/admin` | The gallery editor is W8; production pointed at the legacy editor. |
| Random thumbnail differs per load | Production also picks one picture per category/gallery at random (`ORDER BY RAND()` / random offset). |
| Credit links work; legacy links were broken | Agreed policy: same look, working links. |
| 1 px vertical offset | The shared frame renders in standards mode with an explicit `img:only-child` compensation; pre-existing and accepted. |

## Guestbook — verified 2026-11-01

References: `temp/prod-guestbook.html` and `temp/prod-guestbook-submit.html`,
captured with a freshly minted legacy `sid` (the page is unreachable without
it; the algorithm and key are in `gbms.pab.pl` / `PAB::Crypt::Xor`).

| Route | Production reference | Result |
|---|---|---|
| `/guestbook` | `apax.cgi?run=gbms` | Match. GUESTBOOK band, centred welcome heading and intro, Submit link, 1 px `#333BAA` splitters around every entry, obfuscated e-mail links, `Europe/Berlin` dates labelled `(utc)`, `Nav_*` pager, `-THE END-`, `(c) HBR1 2001-2005` signature. |
| `/guestbook/submit` | `apax.cgi?run=gbms&cmd=submit` | Match. Centred well, centred welcome/spam-filter note and `Back to the Entries`, centred 3 px-padded form, 11 px Verdana fields, blue field chrome. |

Measured: h2 top 54, first splitter top 139, first entry top 151, document
height 2704 — identical to production. The pager sits 6 px lower and the
submit button 3 px lower; the remaining delta is font-metric rounding.

On `/guestbook/submit` the horizontal geometry now matches exactly: the h2 text
box lands at x 136.6 / width 181.8, `Back to the Entries` at x 169.0 / width
117.0, and the textarea centre at 260.8 — all identical to production. The
`*-left` modifier classes that used to sit on those three elements were wrong:
production's `guestbook.submit.htm` puts them in the layout's `align="center"`
well with no align of their own, so they inherit the centring. Left-aligning
them moved the back link from x 169 to x 0 and made the whole block look
detached from the form below it.

### Deliberate deviations

| Deviation | Reason |
|---|---|
| `?cmd=submit` is `/guestbook/submit` | Canonical route; the legacy entry point still redirects. |
| A successful post shows the thanks page | Production returned to the listing with a message-log block; reproducing that is a W6 follow-up. |
| Entry content is sanitized before rendering | Production printed stored HTML verbatim. An allowlist keeps `<br>`, inline tags and anchors, drops scripts/attributes, then `formatHyperlinks()` links bare URLs, e-mail addresses and domains. |
| E-mail links are reproduced | The XOR handshake and `/jscript/crypt.js` are kept, so scrapers still cannot read addresses from the markup. |

## Remaining content pages

Not yet verified against production: `/listen`, `/playlist`, `/mixes`,
`/muses`, `/request/{station}/{song}`, `/party*`, `/chat`,
`/webtv/ticker*`, `/iphone`. They are the next slices; add a row here before
calling any of them done.
