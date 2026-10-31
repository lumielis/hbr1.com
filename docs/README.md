# HBR1 — Music on Futurenet

Migration of the legacy HBR1 site from Perl 5.10 + in-house frameworks
(`PAB2`/`PAB3`/`ssms`) to PHP 8.3 on **Azera** + **Clarity**.

The full plan, including the root-cause analysis of the production failures,
lives in the session artifact `plan.md`. The operational checklist is
[`deploy/PHASE-0-RUNBOOK.md`](deploy/PHASE-0-RUNBOOK.md).

## Status

| Workstream | State |
|---|---|
| Phase 0 — security cleanup | runbook ready, **needs execution on the server** |
| W1 — foundation | **done** (bootstrap, routing, views, redirects, tests, deploy configs) |
| W2 — data layer | not started |
| W3 — streams & station core | routing + playlist endpoints done; live data pending |
| W4–W8 | not started |
| W9 — cutover | not started |

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
