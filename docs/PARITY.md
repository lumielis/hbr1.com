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

## Remaining content pages

Not yet verified against production: `/listen`, `/playlist`, `/mixes`,
`/muses`, `/request/{station}/{song}`, `/guestbook`, `/party*`, `/chat`,
`/webtv/ticker*`, `/iphone`. They are the next slices; add a row here before
calling any of them done.
