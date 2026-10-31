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
| W3 — streams & station core | **mostly done** — listen, playlist, topframe, stream files; `vimg`/`earthcam`/`jsm*` open |
| W4 — tracklist | **done** — pagination, request endpoint, request rules + tests |
| W5 — gallery | **done** — public pages on the live `GAL_` schema; admin upload is W8 |
| W6 — community | **mostly done** — guestbook, party calendar, mixes, chat, navigation frame; party submission is W8 |
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
/muses/{station}        Muses HTML player popup (linked from "Tune In:")
/tracklist/{station}    paginated catalogue (?offset=)
/request/{station}/{id} song request form (CSRF, per-IP limits)
/topframe               clock/header frame
/gallery                public categories
/gallery/{category}     galleries in a category
/gallery/{cat}/{gal}    pictures in a gallery
/gallery/pic/{id}       single picture, prev/next
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
/mixes                  mix styles
/mixes/{category}       mixes in a style
/mixes/playlist/{id}    one mix and its track listing
/chat                   chat room (database-backed, not the jsm* file transport)
```

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

An unknown station (`/listen/nope`, `/playlist/nope`, `/tracklist/nope`) and an
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
- `RequestControllerTest` — the reject paths (unknown station, station without
  request support) and that CSRF is actually registered.
- The accept path is verified against `evaluate()` returning `STATUS_OK`, and
  the real submission is left untested by design.

If a staging RadioDJ instance ever exists, point `radio_dj_request_url` at it
and add a real end-to-end case.

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
