# WebTV / TV Ticker — how it worked, and how it works now

The user asked: *"I thought you could create pages in the admin panel that WebTV
would then display."* That was exactly right — and this document records why it
did not work, and what replaced it.

## What the legacy pipeline did

The ticker was a **file generator**, not a page renderer.

1. The admin module `cgi-bin/include/admin/ticker.pl` exposed
   `?action=ticker` with a `ticker` table behind it — one row per page. Columns:
   `title`, `content`, `image`, `bg_color`, `delay`, `title_color`,
   `content_color`, `anchor_color`, font families and sizes, `border_horizontal`,
   `border_vertical`, `show_title`, `show_image`, `status`, `position`,
   `object_type` (`n` text / `f` Flash), `flash_movie`.
2. On every save, re-order or kill, it called `write_ticker()`. That deleted the
   whole `public/webtv/ticker/content*.htm` sequence and re-rendered it from the
   table, using the admin template `legacy/archive/htmldocs/cgi-htm/admin/ticker_template.htm`.
3. Each generated file carried a meta-refresh pointing at its successor:

   ```html
   <meta http-equiv="refresh" content="15; URL=content3.htm">
   ```

   The last page pointed back at `content.htm`, forming a ring.
4. `public/webtv/ticker/index.html` is a three-frame frameset
   (10 / 160 / 24 px). The middle frame loads `content.htm`, so the browser
   follows the ring on its own. The left and right frames are the static camera
   chrome (`index_left.html`, `index_right.html`).
5. `public/index.html` puts that frameset in the `lcam` frame at
   `rows="189,120,1*"` on the left of the front page.

### Why the pages you created never appeared

The generated `content*.htm` files were **committed to git**. The live table
holds 12 running pages last edited in 2020, and the committed files matched
them — until someone edited the table. Then one of two things happened:

- **Nothing**, because the web server user could not write into the document
  root, so `write_ticker()` failed silently and the stale files kept serving.
- **Or the change was live but lost on the next deploy**, because the deploy
  overwrote the generated files with the committed ones.

Either way, the file system — not the database — was the source of truth for
what a visitor saw. That is the bug.

There was a second, quieter failure: `content.htm` (page 0) and `content11.htm`
(page 11) both have **empty** `content` in the database. They only worked as
"title-only" pages because the template guarded the title with
`<IF_TICKER_SHOW_TITLE>`, and both those rows have `show_title = 0`. A page with
neither title nor content renders as an empty pane — which is what you see if
you land on the first page of the ring.

## What it does now

`App\Services\TickerService` reads the `ticker` table and
`App\Controllers\Web\TickerController` renders the pages under the **same file
names**, so the frameset, the meta-refresh URLs and any bookmark keep working.

```
GET /webtv/ticker                  -> frameset            (views/web/ticker.clarity.html)
GET /webtv/ticker/content.htm      -> page 0              (content.htm is index 0)
GET /webtv/ticker/content{N}.htm   -> page N
```

- Nothing is written to disk, so there is no publish step and no write
  permission needed on the document root.
- `delay`, colours, fonts and borders come from the row, per request.
- The ring is computed from the running rows (`status = 1`, ordered by
  `position`, then `id`), so re-ordering in the admin takes effect immediately.
- The last page points back at `content.htm`, exactly as before.
- An index past the end is a **404**, not a replay of page 0.

The markup filter `ssms::system::to_html()` is ported verbatim into
`TickerService::markup()`: `[a=url]text[/a]`, `[a]url[/a]`, `[b]`, `[i]`, `[u]`.
Anything else — including `[s]` — is left as literal text, as before.

### Removed artefacts

`git rm` on these; they are outputs, not sources:

```
public/webtv/ticker/content.htm … content11.htm
public/webtv/ticker/index.html
```

`index_left.html`, `index_right.html` and everything under
`public/webtv/ticker/images/` and `flash/` stay: they are the artwork, and the
`images/` directory is where the admin uploads ticker pictures.

### Flash is not ported

`object_type = 'f'` rendered a Flash movie via `<object>`/`<embed>`. The plugin is
gone from every current browser, so those pages answer **404** rather than an
empty black box. No running row uses it.

## Still open

- **Admin UI** (`/admin/ticker`). Today the table can only be edited in SQL. The
  admin module is W8, behind `AdminAuthMiddleware` + CSRF; the upload targets are
  `public/webtv/ticker/images` and `.../flash`.
- **`broadcast` and `streamstats`** pages — still Perl
  (`cgi-bin/include/admin/streamstats.pab.pl`).
- **`jsm*` / CamTV upload** (`jsmp.cgi` upload, `jsmg.cgi` serve, `jsmt.cgi` room
  registry). The chat transport that used them is already dead —
  `$path{'data'}` is commented out in `cgi-bin/include/settings.inc`, so the
  uploads resolved to `/chat/<room>.txt` at the filesystem root and silently
  failed. See `deploy/CHAT-STATE.md`. Porting it means inventing a state
  directory that never existed.
- **`earthcam`** (`/webtv/earthcam`).
