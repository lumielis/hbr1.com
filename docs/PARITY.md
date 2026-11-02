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

## Chat — verified 2026-11-01, user list 2026-11-02

Reference: `https://hbr1.com/chat/` and its six frame documents. The terminal
is a frameset, not a content page, so it keeps the legacy frameset doctype
(HTML5 has no `frameset`), while its three dynamic frames use valid markup.

| Route | Production reference | Result |
|---|---|---|
| `/chat`, `/chat/` | `/chat/` | Match. Same geometry as production's `rows="26,*,26,29"` / `cols="64,*,100,64"`, expressed as one CSS grid instead of a frameset so the whole terminal scales together at the site's 1.333x default zoom. The four chrome panes are still the static files under /chat/; the message/user/submit panes are canonical routes. |
| `/chat/content` | `/html/chat_content.html` | Match. 9 s meta refresh, `#100040` well, `MM/DD HH:MM` in `#009988`, nickname in its stored colour, message text escaped. Rendered live from `chat` instead of the generated file. |
| `/chat/users` | `apax.cgi?Part=Chat&Action=listUsers` | Match. Live from `chat`: every nickname that posted within `ChatOnlineTime` (600 s), once each, ordered by its earliest in-window message, painted in its stored colour, with the legacy click-through popup (nickname and last activity). Same title, 9 s refresh and background. The popup's "Remote address is …" line is dropped — see deviations. |
| `/chat/submit` | `apax.cgi?Part=Chat&Action=submit` | Match. 29 px bar pinned to the bottom of the frame, Nickname/Message/OK in the dark-blue input chrome with the 9 px tabs hugging the top, auto-layout spacer giving the production 798/198 column split; inputs at x=355/446/728 at a 996 px viewport, identical to production. |

The four chrome panes (`top`, `left`, `right`, `bottom`) were already
byte-identical to production and are still served as static files, loaded
through iframes so they stay the single source of truth.

The terminal was a `<frameset>` until the site started scaling itself to
1.333x. A frame's 26/29 px row heights are frameset units that page CSS cannot
reach, so the panes grew inside rows that did not, clipping the submit bar and
cutting the "MESSAGE TERMINAL" heading in half. It is now a CSS grid with the
same numbers - `grid-template-rows: auto 1fr auto auto`,
`grid-template-columns: 64px 1fr 100px 64px` - in a single document, so
everything scales together. Each pane is a nested scrolling `<iframe>`, which
is what a `<frame>` was.

`/chat/submit` also needed two geometry corrections, both invisible in a wide
viewport but obvious inside its pane:

* **The bar sat at the top instead of the bottom.** It is the last cell of a
  `height="100%"` table with `valign="bottom"`. Production got the full-height
  table for free: quirks mode propagates `height: 100%` from `<body>` through
  the `<form>` that wraps the rows. Standards mode does not propagate a
  percentage height through a form at all, so the table collapsed to its 29 px
  content height. `html`, `body` and the now-`class="chat-form"` form all
  declare `height: 100%` explicitly; the table then measures 963.2 px at a
  963 px viewport and the bar lands at y=934.2, exactly as production does.
* **The two 9 px chrome tabs were pushed to the bottom.** Production sets
  `valign="top"` on just the `inptl`/`inptr` cells and leaves `inpl`/`inpr` (the
  29 px end pieces) unset. The port applied a blanket
  `vertical-align: bottom` to every cell in the row, which dropped both tabs to
  y=20 and flattened the tab silhouette. `.chat-tab` is now `top` and
  `.chat-corner` resets to `baseline` — `vertical-align` does not inherit, so
  that reset has to be written out rather than omitted.

### Deliberate deviations

| Deviation | Reason |
|---|---|
| The message pane is live from the database | Production polls a generated file that is only rewritten on a post; between posts it goes stale. The frame keeps the same 9 s refresh and line format. |
| The user pane is live from the database | Same reason. It reproduces the `Action=listUsers` query (last 600 s, one row per nickname) rather than the stale `/html/chat_userlist.html` that `writeUserlist()` wrote, and that nothing called. |
| The user popup omits the remote address | Production printed `chat.remote_addr` for every listed nickname to the whole room, which discloses other users' IPs. The nickname and last-activity lines are kept. |
| `Part=Chat&Action=…` maps to `/chat/…` | Canonical pane URLs; the legacy entry points still redirect. |
| The old single-page `/chat` view is gone | The terminal is the grid, as in production. |
| The submit form posts to `/chat/submit`, not `/chat` | The bar is its own pane. Posting to `/chat` left the pane's URL on the terminal, so a refresh loaded the whole terminal inside the 39 px bar. Keeping the URL on `/chat/submit` reproduces what production's frame did. `POST /chat` still works for old clients. |
| The submit bar reloads `chat_main` and `chat_users` after a post | Production's `chat_submit.htm` did the same (`parent.frames['chat_main'].location.reload()`), so a fresh message and the poster's own nickname appear immediately instead of at the next 9 s tick. |
| `/html/chat_content.html` and `/chat/index.html` remain as stale static copies | Only reached by direct bookmarks; the terminal no longer loads them. |

## Remaining content pages

Not yet verified against production: `/listen`, `/playlist`, `/mixes`,
`/muses`, `/request/{station}/{song}`, `/party*`,
`/webtv/ticker*`, `/iphone`. They are the next slices; add a row here before
calling any of them done.
