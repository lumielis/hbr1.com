# HBR1 — Music on Futurenet

Migration of the legacy HBR1 site from Perl 5.10 + in-house frameworks
(`PAB2`/`PAB3`/`ssms`) to PHP 8.3 on **Azera** + **Clarity**.

The full plan, including the root-cause analysis of the production failures,
lives in the session artifact `plan.md`. The operational checklist is
[`deploy/PHASE-0-RUNBOOK.md`](deploy/PHASE-0-RUNBOOK.md).

## Status

| Workstream | State |
|---|---|
| Phase 0 — security cleanup | runbook ready (`deploy/PHASE-0-RUNBOOK.md`), **needs execution on the server** |
| W1 — foundation | **done** — bootstrap, routing, views, legacy redirects, tests, deploy configs |
| W2 — data layer | **done** — 46-table schema baseline, 46 models, `ModelMapping` for the music schemas |
| W3 — streams & station core | **mostly done** — listen, playlist, topframe, stream files, `/muses`, `/viewer`; `earthcam`/`jsm*` open |
| W4 — catalogue | **done** — pagination, request endpoint, request rules + tests |
| W5 — gallery | **done** — public pages on the live `GAL_` schema; visual parity with production verified for all four levels, see [`PARITY.md`](PARITY.md); admin upload is W8 |
| W6 — community | **mostly done** — guestbook, its submit page and the chat frameset verified against production ([`PARITY.md`](PARITY.md)); party calendar, party submission + edit, navigation frame |
| W7 — webtv / ticker / broadcast | **mostly done** — ticker renders live from the `ticker` table (no more generated `content*.htm`); `broadcast`, `streamstats`, `jsm*`/CamTV upload open |
| W8 — admin | not started |
| W9 — cutover | not started — see `deploy/DEPLOYMENT.md` |

Deployment design (git-based, no composer on the host) is in
[`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md). What the legacy chat actually
stores — and why `data/chat/` does not exist — is in
[`deploy/CHAT-STATE.md`](deploy/CHAT-STATE.md).

What works against the live server right now:

```
/                       frameset front page
/listen                 all three station panels (the legacy page)
/listen/{station}       the same page, that station first
/playlist               all three panels, "now and soon" list
/playlist/{station}     the same page, that station first
/muses                  Muses HTML player popup, default station (Ambient)
/muses/{station}        the same player for one station ("Tune In:")
/mixes                  the station catalogue: pick a station ("Inventory")
/mixes/{station}        paginated catalogue for one station (?offset=)
/tracklist, /tracklist/{station}   301 -> /mixes (the legacy name)
/request/{station}/{id} song request form (CSRF, captcha, per-IP limits)
/request/code.png       the form's security image; also the target for the legacy
                        `?module=tracklist&show=secimg`
/topframe               clock/header frame
/iphone                 oversized station chooser for the iPhone
/admin                  placeholder until W8 (the real admin area)
/gallery                public categories
/gallery/{category}     galleries in a category
/gallery/{cat}/{gal}    pictures in a gallery
/gallery/pic/{id}       single picture, prev/next
/viewer/{path}          the `vimg.cgi` image viewer behind every thumbnail
/stream/{station}.m3u   playlist files (these 500 in production)
/stream/{station}.asx
```

Content pages all inherit [`views/layouts/page.clarity.html`](../views/layouts/page.clarity.html),
which carries the 583 px header/title/body/footer chrome
(`header_*.gif`, `title_*.gif`, `body_bg.jpg`, `footer_*.gif`). The first pass
had dropped it, so every page rendered as a bare table floating on black.


W6 community pages:

```
/navigation             bottom navigation frame
/guestbook              guestbook (33/page, spam filter, CSRF posting)
/party                  party calendar (year/month/day grid)
/party/{id}             single party
/party/new              submit a party (was `?part=edit&party_id=new`)
/party/{id}/edit        edit a party you submitted
/chat                   chat room (database-backed, not the jsm* file transport)
```

### The party calendar

`/party` reproduces `htdocs/cgi-htm/client/party_main.htm`, and it is the page
that looks *least* like the rest of the port, so it is worth spelling out what
is deliberate.

The first version of this page was a redesign: a bold year/month strip, a
seven-column grid with hover titles, and a flat "coming up" list. None of that
is on production. What production has is three strips of the same link in
three colours (`#999999` / `#ccee00` / `#0DBD43`, selected by the legacy `id`s
`calendar` / `calendar_found` / `calendar_current`), a `01. Sun` day grid, and
a `cellspacing="1"` list of events grouped by date under a `#200080` header.
The reason it matters: the colours are the *only* signal the page carries that
a year, month or day contains a party, so flattening them removes information.

Three details are easy to get wrong and are now pinned by
`tests/PartyServiceTest.php`:

- The year strip is `current-3 .. current+2` — six entries, not eleven. The
  legacy tag was `<CALENDAR_YEARS start="-3" end="3">` with an exclusive end.
- The month and day strips break the row *before* every seventh cell
  (`<IF_TIMES=6>` sat in front of the `<td>`), so index 0 is a break too. It is
  what closes the `colspan=6` spacer row. Getting this wrong does not look
  broken — it widens the whole 583 px frame to 661 px, because the spacer then
  shares a row with six real cells.
- `parties_in_time()`, the query behind the cell colour, is the one legacy
  query with **no `status` filter**. A draft therefore lights up its day. The
  page used to delete abandoned drafts on every request, which hid that; the
  port does not delete on GET (`PartyService::pruneDrafts()` is a console task),
  so the behaviour is reachable and is reproduced rather than "fixed".

The frame differs from the newer pages too: two SUBMENU bands of one button
each (`ENTER_A_PARTY`, `RESET_CALENDAR`, both `#AAAA22`), a 64 px content gutter
instead of 55, `text-align: left` on the well, and the `(c) HBR1 2001-2003`
signature instead of `- Music on Futurenet`. The layout exposes `bands`,
`content_gutter` and `signature` for that; the catalogue and the request form
keep their defaults.

### The party submission form

`/party/new` and `/party/{id}/edit` reproduce
`htdocs/cgi-htm/client/party_edit.htm`. Two legacy behaviours are deliberately
**not** reproduced, and both were defects rather than features:

- **The row was created on page view.** `?part=edit&party_id=new` ran
  `INSERT INTO party …` before the visitor had typed anything, and only a 24 h
  sweep cleaned up what was left. Three consecutive GETs to production during
  this port produced drafts 7469, 7470 and 7471 — one orphaned row per view.
  The ported form is a plain GET; the row is created on POST. The 19 blank
  drafts still in the table are that bug's residue and are left alone.
- **The password was stored in clear.** `party.password` is `varchar(32)`, so it
  could hold `secret123` but never a hash. New parties store Argon2id; the 800
  clear-text rows keep working, and `PartyService::verifyPassword()` rewrites
  each one on its first successful edit. The column was widened to
  `varchar(255)` for that.

Smaller things that are reproduced faithfully, because the page has to look the
same:

- `n/j/yy` dates (`11/1/26`) and `H:i` times, from
  `ssms::time::set_format('H:m', 'm/d/y')` — the `m` in the time half means
  minutes. A two-digit year is read as 20xx.
- Nothing is preselected on a new form except by the browser, so the country
  select shows **Afghanistan**: it is ordered by name. That is what production
  does and it is left alone.
- `tagout` columns go through `ssms::system::to_sql()`, which escaped `<`, `>`
  and `"`, **left `&` alone** (hence the `M&uuml;nchen` in the table), and wrote
  `<br />` for a newline. `PartyService::tagout()` does the same, which is also
  what makes storing `<script>` safe on a page that prints the value with
  `|raw`.
- A flyer is stored as `<id>.<extension>`, the extension taken from the image
  rather than from the client's file name. The legacy check trusted the suffix,
  so `evil.php` renamed to `evil.jpg` landed in a directory the web server
  serves. The upload is staged, inspected, and only renamed into place once a
  row exists to name it after — and it is checked *before* the party is
  inserted, which is the order `party.pl` used.

One consequence worth knowing: the edit form shows `München` where the column
holds `M&uuml;nchen`, so saving an old row normalises its entities once. The
rendered output is identical either way — the detail page decodes on read — and
the columns are cp1252, so the German repertoire stores losslessly.


W7 WebTV ticker:

```
/webtv/ticker           frameset (camera chrome + rotating page)
/webtv/ticker/content{N}.htm   one ticker page; meta-refresh walks the ring
```

The ticker is rendered from the `ticker` table on every request. The legacy
pipeline instead had `write_ticker()` rewrite `htdocs/webtv/ticker/content*.htm`
from disk on every admin save, and those generated files were committed to the
repository — so the site showed whatever had last been published, not what the
admin contained. See [`deploy/WEBTV-TICKER.md`](deploy/WEBTV-TICKER.md).

The header clock (`/topframe`) is computed in PHP from the IANA timezone
database. The legacy page used MySQL `CONVERT_TZ()`, which returns **NULL** on
this server because the `mysql.time_zone*` tables are empty — so the old header
had no offsets at all.

The frameset also loads plain files from the old document root
(`/ltop.htm`, `/djs.htm`, `/html/lbot.html`, ...). Those are served by
`App\Http\LegacyStatic`, which only ever returns files with a static extension
inside `htdocs` - it never serves `.php`, `.pl` or `.inc`, so the Perl
configuration stays unreachable.

Every legacy URL form still resolves — `/cgi-bin/main.php?module=…`,
`/perl/apax.cgi?run=…`, `/run/main.xp?action=…`, `/cgi-bin/asx.cgi?trance` — as
a 301 onto the canonical route.

An unknown station (`/listen/nope`, `/playlist/nope`, `/mixes/nope`) and an
unknown gallery id are **404**, not an empty 200.

## Verifying

Two levels, both worth running after any change:

```
php tools/smoke.php     # in-process: dispatcher + view, asserts status codes
bash tools/probe-vhost.sh   # over HTTP through nginx: catches config-level bugs
```

`probe-vhost.sh` is the one that catches web-server mistakes. It already paid
for itself: a nginx `alias` for `/playlist/` shadowed `/playlist/{station}` and
returned 404, which the in-process test could never see.

## Findings that shaped the work
**No SAM Broadcaster databases exist any more.** The server only has
`radiodj_trance|tronic|ambient`. All three stations are on RadioDJ, so the SAM
code paths have been removed from the mapping — not just left dormant.

**The two database connections became one.** `$Db` and `$Db_Music` always
pointed at the same host; the split existed only because `hbr1` could not
INSERT song requests. `hbr1-web` now holds all of `hbr1`'s privileges, so the
application uses a single connection and a single credential. See
`deploy/DB-CONNECTIONS.md`, including the dead grants still to revoke.

**The chat is a database, not a directory.** W6 was blocked on "where does
`data/chat/` live". It does not exist, on any server: `$path{'data'}` is
commented out in `cgi-bin/include/settings.inc`, so the `jsm*` transport wrote
to `/chat/<room>.txt` at the filesystem root, where it had no permission. The
real chat is the `chat` table (25,029 rows). Ported against the table; the
`jsm*` scripts are unrecoverable as designed. See `deploy/CHAT-STATE.md`.

**`/listen` and `/playlist` were never single-station pages.** Both legacy
scripts loop over *all* of `$Stations` and ignore `?station=` entirely - only
`tracklist` ever read it. The first port invented a one-station view, so the
pages could never match production. Both routes now render the full set of
panels and a scoped request just promotes that station to the top (an unknown
one is still a 404, so the route does not become an indexable 200).

**Inside `<script>`, Clarity auto-escapes to a *quoted* JS string literal.**
`{{ station:title }}` inside a `<script>` block already emits
`"I.D.M. Tranceponder"`, with quotes and `\u`-hex escaping for `"`, `<`, `/`.
Writing `"{{ station:title }}"` therefore produces `""I.D.M. …""` and the
browser fails with "missing ) after argument list" - which silently blanked the
station names in the header. Emit the bare expression; add no quotes and no
`|json` filter.

**The legacy pages had no doctype, so every frame rendered in quirks mode.**That is load-bearing geometry, not a style preference. In quirks mode an image
that is the only thing in a table cell is laid out as a block, so a
`<td><img height=8></td>` band is 8 px tall. In standards mode the same cell
inherits `client.css`'s `line-height: 13px`, the image sits on the baseline, and
every band grows by 5 px - the page frame came out 462 px tall instead of 404,
with 71 px station panels instead of 61. `client.css` now carries
`img:only-child { display: block }`, which reproduces the quirks behaviour
explicitly, so the port keeps its doctype and standards mode while matching
production to the pixel.

That rule has one deliberate exception. A gallery thumbnail is also the only
child of its cell - it is wrapped in `<a>` - so it becomes a block too, and a
block image ignores the cell's `text-align: center`. The 110x82 picture then
pinned itself to the left edge of its 142x110 `*_select_border.gif` art, leaving
a visible 32 px band on the right. `.gallery-frame img` now carries
`margin-left/right: auto` to recentre it; the cell's `vertical-align: middle`
already handled the 14 px vertical inset. The single-picture view was unaffected
- its 400x300 image sits between two 25x300 border pieces in a collapsed table,
so there is no slack to distribute.

Quirks mode did not only change image layout; it also changed how percentage
heights resolve, and that has bitten once more. The chat submit bar
(`/chat/submit`) is the last cell of a `height="100%"` table with
`valign="bottom"`, and production got the full-height table for free because
quirks mode propagates `height: 100%` from `<body>` through the `<form>` that
wraps the rows. In standards mode a percentage height does **not** propagate
through a form at all, so the table collapsed to its 29 px content height and
the bar rendered at the top of the frame. The fix is an explicit
`html, body, .chat-form { height: 100% }` chain; the rule of thumb for this port
is that any `height="100%"` legacy layout needs every ancestor in the chain to
carry a height, including the `<form>` in between.

## Page scaling

The site was designed at 583 px for a VGA-era window and reads small on modern
hardware, so it scales itself to **1.333x** (4/3) by default. That is CSS
`zoom`, declared on `body` in both `layouts/page.clarity.html` and `client.css`.

CSS `zoom` is the only mechanism a page has here. `meta viewport` and its
`initial-scale` are **mobile-only** - desktop browsers ignore them entirely -
and no API can set the browser's own zoom level. `zoom` is a genuine layout
reflow rather than a paint-time transform, so the browser's own scaler
magnifies the chrome gifs and they stay crisp; `transform: scale` would blur
them. There is no `@media`, `vh`/`vw` or `position: fixed` anywhere in the
stylesheet or the views, so nothing else is sensitive to the mismatch between
CSS `zoom` and real browser zoom (which also shrinks the viewport; `zoom` does
not, so content does not reflow).

Two traps, both hit while building this:

* **A frameset cannot be zoomed.** `<frame>` row heights are frameset units
  that page CSS cannot reach. Zooming the frames made their content grow inside
  rows stuck at 26/29 raw pixels, which clipped the submit bar and sliced the
  "MESSAGE TERMINAL" heading in half. That is why `/chat` is a CSS grid now: a
  nested grid in one document scales as a whole. Keep it that way - do not
  reintroduce a frameset.
* **`getBoundingClientRect()` reports unzoomed values** under CSS `zoom`. Every
  numeric probe during this work read `583` for a box that visibly rendered at
  700 px. Verify zoom changes with screenshots or by comparing rendered output,
  not with computed rects.

The terminal shell deliberately carries **no** `zoom` and does **not** link
`client.css`; instead its grid tracks are pre-multiplied (85/133 px rather than
64/100) and the two bare chrome panes that have no stylesheet get the zoom
declared on the `<iframe>` itself.

### Framesets need their tracks scaled too

`body { zoom }` alone is not enough for a page that lives inside a `<frameset>`.
Frameset track sizes are frameset *units*, not CSS pixels, so a
`cols="*,794,*"` shell keeps its 794 px middle column while the pages inside it
grow by the zoom. The content then overflows its frame by exactly that factor —
measured on `/`: the `main` frame was 600 px wide holding 777 px of content, so
every page grew a horizontal scrollbar.

`htdocs/cgi-htm/jscript/zoom.js` multiplies every fixed track by the zoom,
leaving `*` and `%` tracks alone, and must be loaded **before** the `<frameset>`
tag because the tracks are applied as they parse. `ZoomFactorTest` enforces
both, and is also what keeps the factor in step across the four places it is
declared (see below). It found two framesets (`/chat/`, and the WebTV ticker)
that had been missed by hand.

The factor is declared in four places and they must agree:

| Where | Why |
|---|---|
| `htdocs/style/client.css` `body { zoom }` | every page that links it, including the chat panes |
| `views/layouts/page.clarity.html` `body { zoom }` | the PHP-rendered pages |
| `htdocs/cgi-htm/jscript/zoom.js` `PAGE_ZOOM` | the frameset track scaler |
| `views/web/chat_terminal.clarity.html` | the grid tracks and the two stylesheet-less panes |

`ZoomFactorTest` asserts all four, plus `ltop.htm` and `lbot.html` (which do not
link `client.css` and so carry their own copy).

**`/mixes` and `/tracklist` were the same data, and are now the same page.**
HBR1 plays mixes only - there are no standalone songs - so the RadioDJ `songs`
rows the "Inventory" page listed *are* the DJ sets. The legacy tree still had
two pages for them: `main.php?module=tracklist` (button 6, "Inventory") and
`mixes.cgi` (button 15, "Tracklist"). The second one's distinguishing feature
was a per-mix track listing that nobody ever filled in: `releases` holds **0
rows**, and `labels` holds 1091 rows pointing at nothing, so its detail page
was structurally empty. Both buttons now point at `/mixes`, which renders the
catalogue; `/tracklist` and `/tracklist/{station}` are 301s. The retired
`?action=view&cat_id=N` and `?action=playlist&mix_id=N` levels collapse onto
`/mixes` as well. A page can now light more than one nav button by declaring
`PageIDs` (an array) instead of the legacy single `PageID`.

**`format_duration()` prints hh:mm:ss and `-` for nothing.** The ported
catalogue had drifted to mm:ss and printed `0:00` for the three RadioDJ rows
that carry `duration = 0`; production prints `03:19:57` and `-`. Restored,
including the `-` placeholder the legacy template used.

**`/mixes` lights the "Tracklist" button, but not with `PageID = 'playlist'`.**
`ImageDef` in `navigation.htm` pairs button 15 with `tracklist` and button 11
with `playlist`. The first port had the mixes pages at `'playlist'` and lit the
wrong button; the catalogue now declares both `['inventory', 'tracklist']`, so
both buttons light.

**The two catalogue states are two different legacy templates.** The chooser
is `template/tracklist.phtml` and the station page is
`template/tracklist.mix.phtml`, and they do not share a frame: the chooser has
no SUBMENU band, no `th_*.gif` title cap, and it starts its content well with
`<!-- CONTENT -->` where the station page starts it with a `<table>` of
`th_left/bg/right.gif`. The port rendered one view for both, so every visit to
`/mixes/{station}` was missing the `CHANGE_STATION` bands, the
`- TRANCE MIXES -` cap, the `#100040` column header, the `tr_bg.gif` column
gutters and the `Nav_Control`/`Nav_Page`/`Nav_Current` pager - the whole visual
difference from a bare table. They are now `views/web/mixes_chooser` and
`views/web/mixes`, and the shared layout grows an opt-in `{% set submenu = true %}`
for the SUBMENU bands (the request form needs them too; nothing else does).

**`page_navigation()` is asymmetric, and the port had squared it off.** The
legacy window holds the current page at its *left* edge with seven pages after
it, and only slides back once it would overrun the last page. So page 1 of 18
shows 1..15 and page 9 shows 2..16. The port had `±5` around the current page,
which put page 1 in the middle of a 1..11 window. The seven-page window is
restored, with the `1 ..` / `.. 18` shortcuts the legacy template emitted.

**Without `?offset=`, production picks a random page.** `tracklist.php` did
`rand(0, COUNT(*) - 20)` when no offset was supplied, so the same URL shows a
different slice on every visit - three fetches landed on pages 14, 8 and 4.
That is reproduced, because it is what the page does, but only for a station
that resolved and only when the parameter is absent: `?offset=` is honoured as
given and a malformed value is clamped.

**A blank RadioDJ row still offered "Play it".** The legacy template gated the
action on `$hasreq = $station['SAMHOST'] && $station['SAMPORT']` - a property of
the *station*, not of the row - so the three `song_type = 0` rows with no artist
and no title still rendered a working request link. Production does exactly
this. The port had started hiding the link on empty rows, which is tidier and
wrong; it is now gated on the station again.

**The pager's `Nav_*` classes are unstyled in production.** They are defined in
`style/default.css`, which the legacy page does not link, and the browser
resolves unknown classes to nothing. Adding the rules would have made the pager
13 px and bold where production renders it 11 px and plain, so the port leaves
them undefined too.

**Static assets need a cache buster, and the legacy pages pinned one.**
`/style/client.css` is served as a plain file, so a browser that has it will not
re-fetch after an edit - which is exactly what happened while fixing the
geometry above: the rule was served, but the page still used the cached copy.
The legacy templates hard-coded `?1`. `BaseController::page()` now appends the
file's mtime, so the token looks after itself.

**The Muses player's "default station" is the last one in the list.** The route
had no station-less case, so `/muses` was a 404 while production served a
player. The legacy `include/muses.php` iterates `$Stations` looking for a match
and never clears the loop variable, so a request without `?station=` keeps
whatever the loop ended on - Ambient, hence "HBR1 - Dream Factory" in
production. `StationService::fallback()` makes that explicit and
`?module=muses&station=X` now carries the station through to `/muses/x`.

**Every thumbnail on the legacy pages is a `vimg.cgi` link.** There are about
1,300 of them (`/djs.htm` alone has 56 on a page, `/artists.html` 176 links
across the page, the front page 43), and the port had no route for the viewer,
so all of them were 404s. The legacy script interpolates `$ENV{'QUERY_STRING'}`
straight into an `<img src>`, which means it would also render any URL it was
handed; the new `/viewer/{path}` resolves the target through `LegacyStatic`
instead, so only files that exist in the document root are ever shown, and the
legacy query string is rejected unless it is a plain relative path with an
extension. Verified by clicking a thumbnail on `/djs.htm` through to the
lightbox.

**`/admin` was a frameset that loaded itself.** `htdocs/admin/index.html` is
the legacy admin frameset, and its content frame is `/cgi-bin/admin.cgi` -
which the URL mapper sends back to `/admin`. So the page nested itself as deep
as the browser allowed, and served the old admin chrome with no authentication
in front of it (production answers 401 from an `.htpasswd` gate that this build
does not reproduce yet). `/admin` is now a real route serving a placeholder
until W8; the legacy admin entry points (`run=ticker`, `run=news`,
`run=userman`, `run=crypt`, `run=setup`, `Part=Broadcast`,
`run=tracklist.creator`) redirect there instead of to URLs that never
existed.

**`?run=iphone` redirected to a 404.** The mapper knew the target but the
route was never registered. `/iphone` now renders `template/client/iphone.htm`:
899 px wide, 30 pt body text, 32 pt station names, one button per station, each
captioned with the live track. Verified against the running site.

## ⚠️ Do not submission-test the request endpoint

`POST /request/{station}` calls RadioDJ's `LoadTrackToTop` command. That command
**queues a real track on a live radio station**. There is no dry-run mode.

This was confirmed the hard way during development: an end-to-end probe queued
track 1 on the Trance stream. It was removed again immediately (one row in
`radiodj_trance.requests`, one in `queuelist`), and the probe scripts were
deleted rather than kept, because a test that can affect a live broadcast is
not a test.

The endpoint is therefore covered like this:

- `RequestServiceTest` — all limit and repeat rules against synthetic history
  (11 cases), no I/O at all.
- `CaptchaServiceTest` — the security image: five digits, single use, the
  15-minute expiry, and that the render is a real 100x30 PNG rather than a
  blank one (13 cases).
- `RequestControllerTest` — the reject paths (unknown station, station without
  request support, a wrong captcha code), that the form renders the image, that
  the image route binds the code to the session, and that GO_BACK carries the
  catalogue offset (9 cases). Every case rejects before the playout call, so
  none of them can queue a track.
- `PartyServiceTest` — the calendar's shape: six years from `current-3`, the
  twelve month names, the `01. Sun` day grid, the six-cell row breaks including
  the leading one, exactly one `calendar_current` per strip, `found` following
  the year rather than the visible month, and the leap-day clamp (11 cases).
  These are read-only queries against `party`.
- `PartyFormTest` — the submission form (29 cases). The date format, the field
  rules, that a past start is only allowed when it is the row's own, that the
  password confirmation is only required on create, that `tagout` columns are
  stored escaped with `<br />` and that a pre-existing `&uuml;` is not
  double-escaped, that a new party publishes with an Argon2id hash, that a
  clear-text legacy password still verifies and is upgraded, that an update
  keeps the stored submitter and password, and that a rejected flyer leaves no
  row behind. Rows it creates are deleted in a `finally`.
- The accept path is verified against `evaluate()` returning `STATUS_OK`, and
  the real submission is left untested by design.

If a staging RadioDJ instance ever exists, point `radio_dj_request_url` at it
and add a real end-to-end case.

## The request form's security image

The legacy captcha was `securimage` 3.2RC2 from 2012, which does not parse on
PHP 8.3 at all: it uses curly-brace string offsets (`$str{0}`), removed in PHP
8.0. It was therefore replaced, not restored.

`App\Services\CaptchaService` reproduces the picture and the protocol, not a
new design: **100x30 PNG**, five digits from `1234567890`, background `#100060`,
text `#33ccff`, lines `#0077ff`, two wandering lines, noise level 1,
perturbation 0.15 — the exact settings from the `show=secimg` branch of
`include/tracklist.php`. The drawing pipeline is securimage's: a 5x supersampled
image for the digits and noise, two lines on the final image, then a three-pole
lens distortion sampling the big image back down. The same `data/security.ttf`
is used. Colour histograms of a production image and a port image match (13
colours in production, ~290 text pixels from `#33ccff`), so the two are
indistinguishable at a glance.

The code lives in the session with a 15-minute expiry (securimage's default) and
is **single-use**: a correct submission removes it, so a captured form cannot be
replayed. It is issued by `/request/code.png`, not by the form page, because the
form emits only an `<img>` tag — the code must be written when the browser
fetches the picture it is about to show.

`/request/code.png` is also the redirect target for the legacy
`main.php?module=tracklist&show=secimg`, so a page cached before the port shows
a working image instead of 301-ing to the catalogue.

Captcha failures answer **400** with a fresh image, not 429: a wrong code is a
typo, while the per-IP rules in `RequestService` remain the control that bounds
abuse. The captcha stops scripted bulk submission; the limits bound it.

One deliberate wording change: the legacy template asked the listener to enter
"the code **schown** above". The port says "shown". Everything else on the page
is reproduced verbatim, but a misspelling is a defect rather than a feature, and
the archived `template/tracklist.req.phtml` keeps the original for reference.

## Character encoding

The site is old enough to have lived through three encodings, and the data
still shows it. The short version: **`hbr1` is `latin1` and should become
`utf8mb4` — but 161 values must be repaired first, or the conversion bakes the
damage in permanently.** 16 more can only be repaired after it.

### What is declared where

| Layer | Encoding | Note |
|---|---|---|
| PHP connection (`DB_CHARSET`) | `utf8mb4` | since W1 |
| `hbr1` schema default | `utf8mb4` / `utf8mb4_general_ci` | but 200 of its 202 text columns are still `latin1` |
| `radiodj_*` schema default | `latin1` | 62 of 66 text columns per schema are already `utf8mb4_unicode_ci` |
| PHP output | `charset=utf-8` | nginx sends it; each view also has `<meta charset>` |
| Legacy Perl/PHP output | **nothing** | no `Content-Type` charset and no `<meta>`, so browsers guess |
| Legacy templates/`.htm` | mixed `iso-8859-1` and raw cp1252 | 38 committed files are not valid UTF-8 |

Production sends `Content-Type: text/html` with no charset and no `<meta
charset>`. Every browser therefore guesses — Chrome picks `windows-1252` — and
because the damaged values *are* well-formed UTF-8 byte sequences, decoding them
as cp1252 shows them as mojibake. Verified: production renders `WORLD LEAGUE
with SVEN VÃ„TH`, and so does the port. **Production is not the correct reference
here** — on this defect the two agree, and both are wrong.

### The four states in `hbr1`

Measured over the 1,560 non-ASCII values in the 33 affected `latin1` columns of
13 tables:

| State | Count | Meaning |
|---|---|---|
| clean cp1252 | 1,374 | stored bytes are already the right single-byte text; `ü`, `ö`, `ä`, `ß`, `é`, `€`, `»`, `–` all round-trip losslessly. Nothing to do. |
| repairable | 161 | a UTF-8 byte string that the legacy `latin1` connection stored byte-for-byte, plus the mixed values. Reading the bytes as UTF-8 recovers the characters, and the result fits cp1252, so it can be written back **today**. |
| deferred | 16 | recoverable too, but the correct text needs a character cp1252 cannot hold — Cyrillic, Japanese, fullwidth punctuation. Repairing now would turn those into `?`, which is worse than the mojibake. Needs `utf8mb4` first. |
| already lost | 9 | contain U+FFFD. Nothing to recover. |

So `WORLD LEAGUE with SVEN VÄTH` is stored as `SVEN VÃ„TH` in 5 party rows,
`München` as `MÃ¼nchen` in 59 `party.location` rows, and so on. Both production
and the port render those as mojibake, for different reasons: production never
declares a charset and the sequence is valid UTF-8 decoded as cp1252, while the
port declares UTF-8 and the sequence *is* the wrong text. Verified side by side
on party 2530.

The important part: **the damage is historical, not ongoing.** It clusters in
2004–2009 (`party` 2008: 21 rows, 2009: 20; `guestbook` spread the same way) and
no row written since is affected. The current `utf8mb4` connection writing into
a `latin1` column produces clean cp1252, verified on a scratch table. The 2026
parties are clean.

25 of the 161 are *mixed*: part double-encoded, part plain cp1252, in one value.
They need a run-wise scan, not a regex over the whole string — which is what the
legacy code did, and why it rewrote bytes in exactly those cases.

### What the port must not do

- **Do not add a charset conversion in the read path.** The obvious "fix" —
  detecting mojibake and repairing it when loading a row — would double-apply
  once the schema is converted, and would corrupt the 1,374 clean values it
  guesses wrong about.
- `fit()` in `StationViewService` used to truncate by *bytes*, which was
  harmless while everything was cp1252 and splits multi-byte characters now
  that pages are UTF-8. It counts characters.

### What the result is: three distinct states

This is the part that is easy to get wrong. Repairing **does not** make the
database `utf8mb4`. They are two separate steps, and each stops somewhere
specific.

| | schema | `MÃ¼nchen` renders as | can store `张浩 😀`? |
|---|---|---|---|
| **now** | `latin1` | `MÃ¼nchen` | no — silently becomes `??` |
| **after `--repair`** | still `latin1` | **`München`** | **no** — still silently `??` |
| **after `--convert`** | `utf8mb4` | `München` | **yes** |

Measured on clones of `party`, `guestbook` and `party_comment_1`:

- after `--repair`, the stored bytes for `München` are `4D FC 6E …` — the single
  cp1252 byte `FC`. Correct text, narrow column.
- after `--convert`, the same value is stored as `4D C3 BC 6E …`. Correct text,
  wide column.

So if the goal is *"the site displays the right characters"*, `--repair` alone
achieves it. If the goal is *"the database stops losing characters it cannot
represent"*, only `--convert` achieves it — and the party form will keep turning
an emoji into `?` until it runs.

### Repairing now, and what it does to the live site

The repair needs **no schema change**, which is why it is safe to run while the
legacy site is still serving:

- the **port** renders correctly — it reads through `utf8mb4` and `MÃ¼nchen`
  becomes `München`;
- **legacy Perl** is unaffected. It connects without a charset, gets the server
  default `latin1`, and receives the same cp1252 bytes it received before the
  repair;
- **legacy PHP** (`main.php`, `$DB_Charset = 'utf8'`) improves. It used to
  receive one mojibake layer more for these rows.

Verified by writing the repair to a clone and reading it back through each of
the three connection charsets.

### The conversion, in order

`php tools/charset-repair.php` reports; `--repair` writes; `--convert` migrates
the schema. `php tools/charset-rehearse.php` does the whole thing against `zz_`
copies of the affected tables and drops them, so the outcome can be inspected
before anything is touched.

1. **Back up** `hbr1`. The repair is one-way.
2. **Rehearse:** `php tools/charset-rehearse.php`. Expect `161` repaired,
   `16` deferred, `9` lost, and `verified: 161/161 exact`.
3. **Repair:** `php tools/charset-repair.php --repair`. Safe to run while the
   legacy site is live, for the reason above.
4. **`referer.url`** is now `varchar(200)`, which is 800 bytes in `utf8mb4` and
   fits `MyISAM`'s 1,000-byte key limit. Before the change it was `varchar(255)`
   and the `ALTER` failed with `1071 Specified key was too long` — verified. The
   7 other "over 767 byte" keys are all `FULLTEXT`, which has no such limit.
5. **Convert:** `php tools/charset-repair.php --convert`.
6. **Regenerate `db/schema.sql`** from the converted schema.

Both steps are idempotent, and that matters specifically after step 5: the scan
only reads `latin1` columns, and after conversion there are none, so re-running
`--repair` writes nothing. The same bytes look "double-encoded" again at the new
layer, and a scan that chased them would double-apply — which is why it is
scoped by column charset rather than by pattern.

`utf8mb4_general_ci` matches what `hbr1`'s default already is and what the
`GAL_` tables use. It is a deliberate choice over `utf8mb4_unicode_ci`: the
RadioDJ side treats `ß = ss` under `unicode_ci` but not under `general_ci`, and
changing that would change what the request rules consider a repeat.

### Why the order cannot be reversed

`ALTER TABLE … CONVERT TO CHARACTER SET utf8mb4` reads the stored bytes as
cp1252 and re-encodes them as UTF-8. That is right for the 1,374 clean values
and **wrong** for the 161: `SVEN VÃ„TH` would become `SVEN VÃƒâ€žTH`. Verified on
clones of all 46 tables.

It is not that the damage becomes invisible — the mojibake is still there and
still wrong. It is that it moves to a second layer which is *valid text*, so a
scan for malformed bytes no longer finds it.

There is a way to convert first if that ever becomes necessary — snapshot the
raw values, convert, then write the repaired text back from the snapshot — but
it needs a scratch store and is strictly more work. Repairing first is cheaper
and safer.

### The 16 deferred values are not fixed by `--convert`

`--convert` lets them be *stored*; it does not repair them. Once converted they
sit at that second layer, and the inverse rule that would undo it is not safe:

- applied to the whole database it wrongly matched **9 of 1,535** already-correct
  values — and those 9 are exactly the 9 rows that already hold a literal U+FFFD
  (verified: 9 rows contain U+FFFD, and all 9 are the false positives). The byte
  string `C3 BD` really does decode to `ý`, so the rule cannot tell them apart;
- it fixed only **2 of the 16** deferred values. The other 14 are Cyrillic spam
  in `party_comment_1` whose encoding was scrambled too badly to invert.

So the honest position is: those 16 stay mojibake. 14 of them are `party_comment`
spam that nothing renders (the comment thread is W8 work), one is a `chat.name`
full of fullwidth punctuation, one is a language list in a `party.memo`. The
cost of leaving them is zero visible characters; the cost of a clever
auto-fixer is damaging nine correct rows. Leave them, and edit by hand if that
never sits right.

### Backing up before the repair

The repair changes stored bytes irreversibly, so take a backup first, and choose
the export encoding deliberately. Two choices are exact and two silently lose
data.

MariaDB's `latin1` is really cp1252, and PHP's `latin1` is ISO-8859-1. They
disagree on the five bytes cp1252 leaves undefined: `0x81 0x8D 0x8F 0x90 0x9D`.
phpMyAdmin converts the string in PHP before writing the file, so the "file
encoding" it asks for decides what happens to those five bytes:

| phpMyAdmin choice | restores exactly? |
|---|---|
| **UTF-8** | **yes** — verified both directions |
| **latin1 / ISO-8859-1** | **yes** |
| cp1252 / Windows-1252 | no — 17 values, 43 bytes become `?` |
| latin9 / ISO-8859-15 | no — `€` and seven other bytes become `?` |

**Choose UTF-8.** It is the only option that is total: every byte maps to a
character, so nothing can be dropped on the way out, and `SET NAMES utf8mb4` in
the file makes the same mapping on the way back. cp1252 looks like the obvious
pick and is the wrong one, because it *adds* holes to bytes that ISO-8859-1
defines fine.

Verified on the `hbr1-utf8.sql` export of this database:

- 1,560 of 1,560 non-ASCII values present exactly, as UTF-8;
- all three used hole bytes (`0x81`, `0x90`, `0x9D`) preserved as `C2 81`, `C2 90`,
  `C2 9D` — the same characters that were `?` under cp1252;
- 76,715 of 76,715 rows across 46 tables; 46 `PRIMARY KEY`, 27 `FULLTEXT`;
- engines and `latin1` column charsets unchanged, so a restore returns to the
  damaged state rather than to repaired text — which is what a backup must do;
- every one of those bytes round-trips through the real restore path (a
  `utf8mb4` client inserting into a `latin1` column), including `€` as `0x80`.

The earlier `hbr1-cp1252.sql` export is *not* exact: 17 values lose 43 hole bytes
to `?`, and one of them (`party.location #2611`) is in the `--repair` set.

Verify rather than assume: compare each value's `CAST(col AS BINARY)` against the
escaped text in the file. Grepping for the visible characters is not enough —
that is what made an early check of the cp1252 dump look clean while 17 values
were already altered.

One harmless side effect to expect: `party`'s `AUTO_INCREMENT` reads 7717 in the
live table against 7691 in either dump, because 26 blank drafts were created by
test submissions since. A restore of either file is consistent; it just drops
those 26 rows, which are drafts with an empty title.

### The playout schemas are not ours

`radiodj_*` belong to RadioDJ; it writes them and it has its own idea about
encoding. `history.artist` holds `hÃ¤uptling schlumpf` and `FreakzOnTour Â» RIT`
in exactly the same double-encoded state, and 62 of 66 columns are already
`utf8mb4`, so the mojibake there came from RadioDJ reading files whose tags it
mis-identified — not from us. Do not repair those. Reads of non-ASCII metadata
should tolerate a mangled string rather than assume it is clean.

### Committed files that are not UTF-8

38 files under `htdocs/`, `cgi-bin/`, `template/` and `docker/legacy-perl/` are
cp1252/`iso-8859-1`, and 4 under `htdocs/test/` carry a UTF-8 BOM. They are all
legacy or scheduled for deletion in Phase 0. Anything new must be UTF-8 without
a BOM — [`.gitattributes`](../.gitattributes) pins the line endings but cannot
police encoding, so this stays a review item.

## Layout

```
app/            application code (controllers, services, middleware, HTTP)
config/         configuration (no credentials — see env.php)
views/          Clarity templates
public/         the only web-reachable directory
db/             schema baseline + migrations
deploy/         Apache (production truth) and nginx (dev approximation)
docker/         throwaway Perl 5.10 oracle
legacy/         frozen Perl tree (created at cutover)
tools/          lint, smoke, redirect and parity helpers
tests/          PHPUnit
```

## Why a framework

Not a taste decision: the legacy site is built on two abandoned in-house
frameworks whose XS/C layer is pinned to Perl 5.10 and segfaults on anything
newer. Rebuilding that layer is a dead end. Azera + Clarity replace the router,
the template engine, the DB layer and the auth layer with maintained code, and
are already the house stack (see `sailantis-homepage`).

The ORM rule (plan §5): Active Record for owned tables, `ModelMapping` for the
six music schemas, raw queries for reporting — and **controllers never touch the
database**, services own all data access.

## Development

```bash
composer install
php tools/lint.php          # parse-check every PHP file (-v lists directories)
php tools/smoke.php         # route + dispatch smoke test
php tools/check-redirects.php
bash tools/serve-probe.sh   # HTTP probe against the built-in server
composer test
```

Two data tools, both safe to run at any time:

```bash
php tools/charset-repair.php     # report the latin1 damage; --repair writes
php tools/charset-rehearse.php   # same, against zz_ copies, then drops them
```

See [Character encoding](#character-encoding) before using either.

`tools/lint.php` skips `vendor/`, `var/`, `temp/`, `legacy/`, `.git/` and
`.vscode/`. It compares normalised paths, because on Windows
`getPathname()` returns backslashes and a forward-slash skip list silently
matched nothing - which is why the walk used to descend into
`.git/objects/` and `vendor/nikic/php-parser/lib/...`.

Serve locally:

```bash
php -S 127.0.0.1:8099 -t public public/index.php
```

## Secrets

`env.php` holds development defaults only. On the server the real values live
in `/etc/hbr1/env.php` (outside the document root), loaded through the
`HBR1_ENV` environment variable. Never commit credentials — several were
already exposed by files served as plain text under `/cgi-bin/`.
