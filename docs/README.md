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
| W6–W8 | not started |
| W9 — cutover | not started |

What works against the live server right now:

```
/                       frameset front page
/listen/{station}       current track + queue (live RadioDJ data)
/playlist/{station}     current track + queue, legacy layout
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

Every legacy URL form still resolves — `/cgi-bin/main.php?module=…`,
`/perl/apax.cgi?run=…`, `/run/main.xp?action=…`, `/cgi-bin/asx.cgi?trance` — as
a 301 onto the canonical route.

## Two findings that shaped the work

**No SAM Broadcaster databases exist any more.** The server only has
`radiodj_trance|tronic|ambient`. All three stations are on RadioDJ, so the SAM
code paths are dormant fallbacks, not live code.

**The two database connections are one server.** `$Db` and `$Db_Music` both
point at `mariadb.lan.etwnet.com`; the split is a *grant* split (`hbr1` is
SELECT-only on `radiodj_%`, `hbr1-web` can INSERT for song requests). It can be
collapsed after a single `GRANT INSERT` — see `deploy/DB-CONNECTIONS.md`.

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
php tools/lint.php          # parse-check every PHP file
php tools/smoke.php         # route + dispatch smoke test
php tools/check-redirects.php
bash tools/serve-probe.sh   # HTTP probe against the built-in server
composer test
```

Serve locally:

```bash
php -S 127.0.0.1:8099 -t public public/index.php
```

## Secrets

`env.php` holds development defaults only. On the server the real values live
in `/etc/hbr1/env.php` (outside the document root), loaded through the
`HBR1_ENV` environment variable. Never commit credentials — several were
already exposed by files served as plain text under `/cgi-bin/`.
