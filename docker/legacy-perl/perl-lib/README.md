# Perl libraries for the 5.10 oracle

Populate this directory before building the image, from
`C:\Users\christian\Nextcloud\Perl-Lib`:

```bash
cp -r /mnt/c/Users/christian/Nextcloud/Perl-Lib/{PAB2,pab3,ssms} \
      docker/legacy-perl/perl-lib/
```

Expected layout after copying:

```
perl-lib/
├── PAB2/     # Makefile.PL at the root; XS under xs/
├── pab3/     # Makefile.PL at the root; XS under xs/; MySQL driver in its own dir
└── ssms/     # no Makefile.PL — a tree of .pl files pulled in with `require`
```

Notes:

- These libraries are **not** part of this repository and must not be added to
  it: they are abandoned third-party code with an unclear licence. `.gitignore`
  excludes all three directories.
- Build artefacts (`blib/`, `*.so`, `Makefile`, `pm_to_blib`) are excluded too.
  The image compiles the XS from source; a copied `.so` would shadow it and
  reintroduce exactly the glibc/libmariadb mismatch the build exists to avoid.
- `ssms` is not a CPAN distribution. It is `require`d by path, so it is copied
  to `/opt/perl-lib/ssms` and symlinked to where `settings.inc` expects it.

## What the build had to work around

All of these are in the image's `Dockerfile` with explanations. Summarised:

| Problem | Fix |
|---|---|
| `defined %hash` is a **compile error** since 5.24 | build Perl 5.10.1 from source |
| glibc 2.34 dropped implicit `libm` | `-Dlibs='-lm -ldl -lcrypt -lpthread'` |
| gcc 10+ defaults to `-fno-common` | `-fcommon` |
| `libmysqlclient` no longer packaged | `libmariadb-dev` + `--libs`/`--include` overrides |
| MariaDB's `mysql.h` also declares `my_set_error()` | rename the driver's helper to `pab3_my_set_error` |
| MariaDB dropped `MYSQL->reconnect` | patch out one assignment in `DBD::mysql` 4.052 |
| current `DBI`/`DBD::mysql` need a newer perl | pin `DBI` 1.616, `DBD::mysql` 4.052 |
| three different shebangs across the entry points | all three paths symlinked to 5.10.1 |

## Status

The image **builds and runs**: Perl 5.10.1, `PAB` 2.4.0, `PAB3` 3.201, `DBI` 1.616,
`DBD::mysql` 4.052, `PAB3::DB::Driver::Mysql` loading and completing a live MySQL
round-trip, and Apache serving `/cgi-bin/` over mod_cgi.

Two things are still needed before it works as a parity oracle:

1. **`$dbi{'dsn'}` in `settings.inc` hardcodes `host=sql.alien-heads.net`.** The
   `HBR1_DB_HOST` environment value that file also sets is only read by
   `config.pab.pl`, never by the ssms DSN. Run the container with
   `--add-host sql.alien-heads.net:<current-db-ip>` until that line is fixed.
2. The scripts read credentials from `env.local.php` next to the document root,
   so mount it: `-v "$PWD/env.local.php:/var/www/env.local.php:ro"`.

The image is DISPOSABLE — it is deleted at cutover (plan §9, W9).
