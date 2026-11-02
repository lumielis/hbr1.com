# `legacy/` — the retired trees

Nothing here is served, executed or read by the application. The vhost denies
`/legacy/`, and no code path points into it. It exists so the pre-migration
implementation can still be read while the PHP port is finished.

If you are looking for a URL that used to work, this is not where it lives any
more — see "Where things went" below.

## What is here

```
archive/cgi-bin/          the Perl/PHP CGI tree
  *.cgi, *.xp, main.php   the old entry points (mod_cgi / mod_fcgid)
  include/                the modules and templates they required
    admin/                the admin modules (ticker, news, station, userman, …)
    client/               the public pages
    lang/                 the two translation files
  lib/, module/           vendored support code

archive/template/         the HTML and .phtml templates the entry points used
  admin/                  admin screens
  client/                 public screens
  *.phtml                 the PHP-era page templates

archive/htmldocs/         the dead parts of the old document root
  admin/                  the old admin frameset
  chat_old/               superseded by the chat that is now a route
  cgi-img/, java/, …      artwork and applets nothing referenced
  muses/                  the old ffmp3 Flash skins
  premium/, stations/,    pages reachable only by typing the URL
  broadcast/, gallery/
  cgi-htm/                client + admin screen templates, kept as the
                          reference the Clarity views were ported against
  webtv-applet/           the Java-applet WebTV pages and the dead
                          `chat/index.html` frameset, which would have
                          shadowed the `/chat/` route
  test/ (minus the .rar)  the archived test directory

archive/htdocs-root/      root-level pages nothing referenced
                          (choose.htm, sitemap.htm, timeline.html, …)
```

## Why it is not simply deleted

The PHP port was written by diffing against this code. `docs/PARITY.md` and the
per-page comments in `views/web/*.clarity.html` cite specific files here
(`cgi-bin/include/client/party.pab.pl`, `template/client/topframe.phtml`, …), and
the WebTV ticker pipeline is documented in `deploy/WEBTV-TICKER.md` in terms of
`cgi-bin/include/admin/ticker.pl`. Deleting it would make that documentation
unverifiable.

It is also the record of what ran on the old host, which matters for the
credential rotation in `deploy/CREDENTIAL-ROTATION.md`.

## Where things went

| You are looking for | It is now |
|---|---|
| `/images/…`, `/style/…`, `/gp/…`, `/playlist/…`, `/chat/…`, `/webtv/…` | `public/…` — same URL, no alias |
| `/ltop.htm`, `/djs.htm`, `/artists.html`, `/link_base.htm`, `/contact.htm`, `/events.html`, `/top.htm`, `/lbot.htm`, `/navigation.htm`, `/iphone.html`, `/empty.htm` | `public/…` — same URL |
| `/shop/`, `/upload/`, `/live/…` | `public/…` — still linked from the legacy nav and the `lbot` camera strip |
| `/cgi-htm/jscript/…` | `public/cgi-htm/jscript/…` — same URL |
| `/` | the `/` route (`views/web/index.clarity.html`); `public/index.html` is the frozen pre-migration frameset |
| `/admin` | the `/admin` route (`AdminController`) — W8 |
| anything under `/cgi-bin/`, `/run/`, `/perl/` | answered by `App\Http\LegacyUrlMapper` with a 301 |

## Rules for this directory

- **Do not add to it.** It is not a staging area for code that might come back.
- **Do not serve from it.** If a file here needs to be web-reachable, it belongs
  in `public/`.
- **Do not put credentials in it that are not already there.** The tree contains
  `include/config.pab.pl` and `include/settings.inc`, which read their secrets
  from the environment and carry no literals; `tests/SecretsTest.php` enforces
  that and must keep passing.
