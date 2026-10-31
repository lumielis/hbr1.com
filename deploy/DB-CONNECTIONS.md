# Database connection

## One server, one credential, one connection

The legacy code opened two handles in `cgi-bin/main.php`:

```php
$Db       = new Mysql_DB($DB_Host,       $DB_User,       ...);   // site data
$Db_Music = new Mysql_DB($DB_Music_Host, $DB_Music_User, ...);   // playout data
```

Both always pointed at **the same server** (`mariadb.lan.etwnet.com:3306`). The
split existed only because the two MySQL users had different grants:

| | before |
|---|---|
| `hbr1` | `ALL` on `hbr1`.*, `SELECT` on `radiodj_%`.* and `samdb_%`.* |
| `hbr1-web` | `SELECT/INSERT/UPDATE/DELETE` on `radiodj_%`.*, `SELECT` on `samdb_%`.* |

`hbr1` could read the playout tables but not write a song request; `hbr1-web`
could. That was the entire reason for a second handle.

## Now

`hbr1-web` was given all privileges of `hbr1`, so it covers everything:

| | after |
|---|---|
| `hbr1-web` | `ALL` on `hbr1`.*, `SELECT/INSERT/UPDATE/DELETE` on `radiodj_%`.* |

Verified end to end — with `hbr1-web` alone the application can

- read site content, playout history and the playout queue;
- insert into `hbr1.guestbook` (site writes);
- insert into `radiodj_trance.requests` (the song-request write).

So the application now uses a **single connection** (`db.default`) and a single
credential. The services no longer name a connection role, and
`MusicDatabaseMapping` maps only schema-qualified RadioDJ tables.

## Configuration

The DSN is composed from parts rather than hard-coded, so host and credentials
rotate independently:

```php
// placeholders in env.php; real values live outside the document root
'DB_HOST'     => 'localhost',
'DB_PORT'     => 3306,
'DB_DATABASE' => 'hbr1',
'DB_CHARSET'  => 'utf8mb4',
'DB_USER'     => 'hbr1-web',
'DB_PASSWORD' => '',
```

`DB_DSN` is derived from those, and can still be set outright if a socket path
or an exotic DSN is ever needed.

## Least privilege

Collapsing to one credential widens the site user's reach: it can now write to
`hbr1`.* as well as to the playout request tables. Two things keep that
tolerable:

- the playout grants are `SELECT`-only except for `radiodj_%`, where
  `INSERT/UPDATE/DELETE` is needed solely by the song-request feature;
- the application never connects as `root`.

If the tighter split is wanted back later, nothing in the code assumes a single
role: add a second entry under `config['db']` and give the mapping a
`connection` argument. That is a `config/` plus `MusicDatabaseMapping` change
and nothing else.

## Cleanup still outstanding

`hbr1-web` holds grants that are now dead weight. Drop them at the next
credential rotation (Phase 0):

```sql
REVOKE SELECT ON `samdb\_%`.*       FROM 'hbr1-web'@'192.168.%';   -- no samdb_* exists
REVOKE SELECT ON `samdb_ambient`.*  FROM 'hbr1-web'@'192.168.%';
REVOKE SELECT ON `hbr1\_trance`.*   FROM 'hbr1-web'@'192.168.%';   -- these DBs do not exist
REVOKE SELECT ON `hbr1\_tronic`.*   FROM 'hbr1-web'@'192.168.%';
REVOKE SELECT ON `hbr1\_ambient`.*  FROM 'hbr1-web'@'192.168.%';
```

Then retire the `hbr1` user entirely once the legacy Perl tree is gone (W9) —
nothing in the PHP application uses it any more.

## SAM Broadcaster

There is no `samdb_*` schema on the server and all three stations run RadioDJ.
The `samdb_%` grants are vestigial and the SAM code paths are gone from the
mapping; nothing reads SAM tables.
