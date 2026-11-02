# Chat: where the state actually lives

Short answer: **`data/chat/` does not exist on production and never did.**
The legacy code refers to `$path{'data'} . '/chat/'`, and that path variable is
**commented out** in the live configuration, so the concatenation silently
degrades to a filesystem root path.

## The evidence

`cgi-bin/include/settings.inc` on the server (line 5):

```perl
#$path{'data'} = qq(/home/christian/WWWRoot/www.hbr1.com/cgi-bin/include/data);
```

`$path{'data'}` is therefore `undef`. The users of it:

| File | Expression | Resolves to |
|---|---|---|
| `jsma.cgi:26` | `$path{'data'} . '/chat/' . $room` | `/chat/<room>` |
| `jsma.cgi:51` | `$path{'data'} . '/chat/' . $id` | `/chat/<id>` |
| `jsmt.cgi:12` | `$path{'data'} . '/rooms.db'` | `/rooms.db` |
| `jsmt.cgi:29,42` | `$path{'data'} . '/chat/' . $room` | `/chat/<room>` |

Checked on the host: `/chat` and `/rooms.db` **do not exist**. The scripts do
not check for that, so `open FILE, '> ...'` fails silently and the frame
protocol returns nothing useful.

There is also a second, different variable in use:

```perl
$path{'chat'} = qq(/home/christian/WWWRoot/www.hbr1.com/public/chat);
```

`jsmp.cgi` writes the CamTV upload to `$path{'chat'} . '/images/camtv/'`, i.e.
`public/chat/images/camtv/`. That **does** exist and holds the shipped images
(`empty.jpg`, `LiveChat.jpg`, `Danceroom.jpg`, `loading.jpg`).

## Consequence: the JSM chat transport is already dead

`cgi-bin/chat.cgi` (the database-backed chat UI) is a different thing and it
works — it uses the `chat` table, which holds **25,029 rows**, newest 2026-10-29.

The `jsm*` scripts are the old JSM **live transport** (the `\xFF`-delimited
frame protocol, plus the CamTV camera upload). They have no usable state
directory, and in production:

```
/cgi-bin/jsmg.cgi -> 500
/perl/chat.cgi    -> 500
/chat/            -> 200   (static HTML only)
```

So the plan's "keep the JSM protocol byte-compatible" requirement is aimed at
code that is no longer backed by a working store.

## What this means for W6

- **`/chat` is a database feature**, not a filesystem one. Port `chat.cgi`
  against the `chat` table (25k rows) and drop the `data/chat/` assumption
  entirely — there is nothing to reproduce.
- **`jsmp.cgi`/`jsmg.cgi`/`jsmt.cgi`/`jsma.cgi` are unrecoverable as designed.**
  They need a writable state dir that was never configured. Port them only if
  the CamTV/WebTV feature is still wanted; if it is, define the state directory
  as a deploy setting (env var, outside the docroot) instead of resurrecting a
  broken constant.
- No migration of chat state is required, because no chat state exists.
