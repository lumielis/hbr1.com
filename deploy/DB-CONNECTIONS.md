# Database connections — current state and the one-connection target

## What the legacy code does

`cgi-bin/main.php` opens two handles:

```php
$Db       = new Mysql_DB($DB_Host,       $DB_User,       ...);   // site data
$Db_Music = new Mysql_DB($DB_Music_Host, $DB_Music_User, ...);   // playout data
```

Both point at **the same server** — `mariadb.lan.etwnet.com:3306`. The split is
not about location.

## Why the split exists

It is a **privilege** split, not a topology one. Verified against the live
server:

```
hbr1@192.168.%
  GRANT ALL PRIVILEGES ON `hbr1`.*      TO 'hbr1'@'192.168.%'
  GRANT SELECT         ON `radiodj_%`.* TO 'hbr1'@'192.168.%'
  GRANT SELECT         ON `samdb_%`.*   TO 'hbr1'@'192.168.%'

hbr1-web@192.168.%
  GRANT SELECT                   ON `samdb\_%`.*  TO 'hbr1-web'@'192.168.%'
  GRANT SELECT, INSERT, UPDATE,
        DELETE                   ON `radiodj_%`.* TO 'hbr1-web'@'192.168.%'
  GRANT SELECT ON `hbr1_tronic`.*, `hbr1_trance`.*, `hbr1_ambient`.*
```

Consequences:

| Operation | Needs | User |
|---|---|---|
| Read site content (gallery, guestbook, members, ticker) | `hbr1.*` | `hbr1` |
| Read current track / queue | `SELECT` on `radiodj_%`, `samdb_%` | either |
| **Submit a song request** (INSERT into `radiodj_<station>.requests`) | `INSERT` on `radiodj_%` | **`hbr1-web` only** |

That last row is why a single `hbr1` credential cannot do everything today:
it has `SELECT` only on the music schemas. Verified — an INSERT as `hbr1`
fails, the same INSERT as `hbr1-web` succeeds.

## What the application does now

Two **named roles**, one host:

- `default` → `hbr1` — all site reads/writes, and all music **reads**.
- `music` → `hbr1-web` — only the song-request write path.

`App\Services\TrackService` reads through the `default` role (the ORM mapping
declares `music`, but `hbr1` already has SELECT, so either works); the future
`RequestService` writes through `music`.

Keeping them separate is deliberate least-privilege: the site's main credential
does not need INSERT on the playout databases.

## Collapsing to one connection

Your instinct is right — this *can* be one connection. It requires a grant
change, not a code change:

```sql
GRANT INSERT ON `radiodj_%`.* TO 'hbr1'@'192.168.%';
```

Then:

1. Set `DB_MUSIC_USER = DB_USER` and `DB_MUSIC_PASSWORD = DB_PASSWORD` in
   `/etc/hbr1/env.php`.
2. Delete the `music` role from `config/config.php` and from `Bootstrap`.
3. Change the `connection` argument in `App\Services\MusicDatabaseMapping`
   from `'music'` to `null` (falls back to `default`).

**Recommendation:** do this at W4, when `RequestService` is implemented — one
credential to rotate instead of two, and one pool instead of two. Do *not*
grant `ALL` on the music schemas: `INSERT` on `radiodj_%` is the minimum the
request feature needs, and the playout applications own the rest of those
tables.

## Caveat found while probing

The `hbr1-web` grants also cover `hbr1_tronic`, `hbr1_trance` and
`hbr1_ambient` — databases that **do not exist** on the server. They are
leftovers from an earlier layout. Nothing references them; ignore them, and
drop them when the credentials are next rotated (Phase 0).
