# Credential rotation checklist

Executed **on the server and at each provider**, not by a commit. Tick each box
as it is done; nothing here can be verified from the repository.

## Why this is required

The repository is **public** and its history contains live credentials in at
least these tracked files:

| File | What leaked |
|---|---|
| `cgi-bin/include/config.php` | MySQL password for `hbr1-web` (and the retired `hbr1` user) |
| `cgi-bin/include/config.pab.pl` | the same pair, plus an Icecast admin URL with basic credentials inside it, twelve times |
| `cgi-bin/include/config.pab.pl.old` | a tracked backup with the credentials and the unmasked Icecast URL |
| `cgi-bin/include/settings.inc` | a Shoutcast admin password, `pass=`, on five URLs |
| `legacy/archive/htmldocs/admin/.htpasswd` | four crypt-des hashes (was `htdocs/admin/`) |
| `deploy/PHASE-0-RUNBOOK.md` | the Icecast password, in prose |

Those values have been removed from the working tree, and `tests/SecretsTest.php`
now fails if one comes back. **That does not remove them from the commits that
already contain them**, and rewriting history on a public remote is not
sufficient on its own — forks, caches and scrapes persist. Treat every value
below as already compromised.

### Order matters

Rotate **MySQL last**. Rotating it first locks the site out of its own
configuration; rotating it last means the new password is already deployed when
the old one stops working. Everything else can go in any order.

---

## 1. Prepare the secrets file first

The new values have to exist somewhere before the services start using them.

- [ ] Create `env.local.php` for development, or `/etc/hbr1/env.php` for
      production, from the committed template:

      ```bash
      sudo install -d -m 0750 -o root -g www-data /etc/hbr1
      sudo install -m 0640 -o root -g www-data \
           deploy/env.example.php /etc/hbr1/env.php
      sudo -e /etc/hbr1/env.php          # fill in the new values
      ```

      Keep the mode at `0640`. Anything world-readable is a regression regardless
      of where the file lives.

- [ ] Confirm nothing in the document root can read it. `/etc/hbr1/` is outside
      the vhost root by construction; verify rather than assume:

      ```bash
      curl -s -o /dev/null -w '%{http_code}\n' https://www.hbr1.com/../etc/hbr1/env.php
      curl -s -o /dev/null -w '%{http_code}\n' 'https://www.hbr1.com/cgi-bin/include/env.php'
      ```

      Both must be `403` or `404`.

---

## 2. MySQL

| Identity | Where it was exposed | Notes |
|---|---|---|
| `hbr1-web` | `config.php`, `config.pab.pl` | the application's only credential |
| `hbr1` | `config.php`, `config.pab.pl` | legacy `ALL` grant; retire it, do not rotate it |

- [ ] Check which accounts actually exist and from where they may connect.
      The docs assume a `192.168.%` and a `localhost` host clause; confirm
      before writing:

      ```sql
      SELECT user, host, account_locked FROM mysql.user WHERE user IN ('hbr1','hbr1-web');
      SHOW GRANTS FOR 'hbr1-web'@'localhost';
      SHOW GRANTS FOR 'hbr1-web'@'192.168.%';
      ```

- [ ] Generate and set the new password (`openssl rand -base64 32` is enough;
      avoid anything that needs URL-encoding, because it ends up in a DSN):

      ```sql
      ALTER USER 'hbr1-web'@'192.168.%' IDENTIFIED BY 'NEW_SECRET';
      ```

      Repeat for every host clause that came back above — `ALTER USER` on one
      host does **not** change the others, and a stale clause is how "but I
      rotated it" ends up connecting with the old password.

- [ ] Drop the grants that have pointed at nothing since 2019 (from
      [`DB-CONNECTIONS.md`](DB-CONNECTIONS.md)):

      ```sql
      REVOKE SELECT ON `samdb\_%`.*      FROM 'hbr1-web'@'192.168.%';
      REVOKE SELECT ON `samdb_ambient`.* FROM 'hbr1-web'@'192.168.%';
      REVOKE SELECT ON `hbr1\_trance`.*  FROM 'hbr1-web'@'192.168.%';
      REVOKE SELECT ON `hbr1\_tronic`.*  FROM 'hbr1-web'@'192.168.%';
      REVOKE SELECT ON `hbr1\_ambient`.* FROM 'hbr1-web'@'192.168.%';
      ```

- [ ] Confirm `hbr1-web` can still do its job before retiring `hbr1` —
      reads on the site data, and insert/update/delete on `radiodj_%`:

      ```bash
      mysql -u hbr1-web -p -e 'SELECT 1 FROM hbr1.guestbook LIMIT 1;
                               SELECT 1 FROM radiodj_trance.songs LIMIT 1;'
      ```

- [ ] Retire `hbr1` (nothing in the PHP application uses it; only the legacy
      Perl tree did, and it now reads the same env file):

      ```sql
      DROP USER 'hbr1'@'192.168.%';
      DROP USER 'hbr1'@'localhost';
      ```

      Keep the account locked rather than dropped if the Perl tree still has to
      run until W9: `ALTER USER 'hbr1'@'192.168.%' ACCOUNT LOCK;`

- [ ] Put the new password in `/etc/hbr1/env.php` as `DB_PASSWORD`.

- [ ] Verify both trees pick it up — PHP and Perl read the same file:

      ```bash
      sudo -u www-data php -r 'require "/etc/hbr1/env.php"; echo DB_USER, " ", strlen(DB_PASSWORD), "\n";'
      sudo -u www-data perl -e 'do "/home/christian/WWWRoot/www.hbr1.com/cgi-bin/include/config.pab.pl"; print $ENV{HBR1_DB_USER}, " ", length($ENV{HBR1_DB_PASSWORD}), "\n";'
      ```

      Confirm the site still serves before moving on (§7).

---

## 3. Icecast admin

The admin interface on `radio.hbr1.com:19800/admin/` authenticates with HTTP
basic. That credential appeared inside the URL twelve times, so it is the most
widely copied secret in this list.

- [ ] Change the admin password in the Icecast config (`<admin-password>`),
      then restart Icecast:

      ```bash
      sudo systemctl restart icecast2
      ```

- [ ] Note that **changing it also breaks any listener that polls the stats
      XML**. Those are:
      - the PHP side, through `ICECAST_STATS_URL` in `env.php`/`env.local.php`;
      - the Perl tree, through `$ENV{HBR1_ICECAST_URL}` in `config.pab.pl`.

- [ ] Update `ICECAST_STATS_URL` in `/etc/hbr1/env.php` to the new value, in the
      `scheme://user:password@host:port/admin/stats.xml` shape.

- [ ] Update `ICECAST_STATS_USER` and `ICECAST_STATS_PASSWORD` alongside it, so
      a future non-URL consumer has the parts.

- [ ] Verify the stats endpoint answers with the new credential and not the old:

      ```bash
      curl -s -o /dev/null -w '%{http_code}\n' "$ICECAST_STATS_URL"
      ```

- [ ] Verify the site's now-playing line still populates (it degrades to no
      caption if the stats fetch fails, so an empty header is the symptom):

      ```bash
      curl -s https://www.hbr1.com/cgi-bin/main.php?module=listen | grep -c 'Last Played'
      ```

---

## 4. Shoutcast admin — separate secret, separate hosts

`settings.inc` polls Shoutcast `admin.cgi` on **`217.64.169.133:19700`, `:19710`
and `:19720`**, and a local fallback on `127.0.0.1:19730`. That is a different
password from the Icecast one, on different infrastructure. It is easy to
rotate one and forget the other.

- [ ] Confirm with the provider whether Shoutcast is still in use at all. If it
      is not, stop polling it instead of rotating:

      ```bash
      for p in 19700 19710 19720; do
        curl -s -o /dev/null -w "$p %{http_code}\n" --max-time 5 \
             "http://217.64.169.133:$p/admin.cgi?mode=viewxml"
      done
      ```

- [ ] If it is still live, change the source/admin password at the provider and
      put it in `/etc/hbr1/env.php` as `SHOUTCAST_STATS_PASSWORD`.

- [ ] `settings.inc` falls back to the password embedded in
      `ICECAST_STATS_URL` when `SHOUTCAST_STATS_PASSWORD` is absent, so check
      which one it actually used before assuming the right value is in place:

      ```bash
      sudo -u www-data perl -e 'require "/home/christian/WWWRoot/www.hbr1.com/cgi-bin/include/settings.inc"; for (sort keys %station_def) { my $u = $station_def{$_}{shout_stat}; print "$_ ", (length $ENV{HBR1_SHOUTCAST_PASS}), "\n"; }'
      ```

- [ ] Remember `%station_unused` — the `rock` and `hiphop` entries — also read
      the variable. They are dead definitions, but they were two of the leaked
      copies.

---

## 5. The admin gate

`htdocs/admin/.htpasswd` - now archived at `legacy/archive/htmldocs/admin/.htpasswd` - carried four crypt-des hashes. Des is reversible at
the speeds it was chosen for, so treat the passwords as known.

- [ ] Change all four passwords (`christian`, `relda`, `willi`, `4711007`).

- [ ] Regenerate the file with a modern hash, not des:

      ```bash
      sudo htpasswd -B -C 12 /home/christian/WWWRoot/www.hbr1.com/legacy/archive/htmldocs/admin/.htpasswd christian
      ```

      `-B` is bcrypt; `-2y` also works if Apache is recent. Do **not** omit the
      flag — the default for an existing des line stays des.

- [ ] Keep it out of git. It is now in `.gitignore`; confirm:

      ```bash
      cd /path/to/checkout && git check-ignore -v legacy/archive/htmldocs/admin/.htpasswd
      ```

- [ ] Find out what enforces the 401. `htaccess.txt` is not an active
      `.htaccess`, so something else does it — record the answer before the W8
      port replaces it:

      ```bash
      grep -rn 'admin' /etc/apache2/sites-enabled/ /etc/apache2/conf-enabled/ | head
      ls -la /home/christian/WWWRoot/www.hbr1.com/legacy/archive/htmldocs/admin/
      ```

---

## 6. Mail, FTP, SSH

Lower confidence: these have not been seen in the repository, but `ssms` was
served as plaintext and the shells had file access, so check rather than assume.

- [ ] Mail: any SMTP account the guestbook or notification code uses. Look for
      accounts whose password was ever in a file reachable from the docroot.

- [ ] FTP: any `Net::FTP` upload target (the WebTV and gallery upload paths).

- [ ] SSH for `christian`, including **host keys**, if a shell had file access:

      ```bash
      ls -la ~/.ssh/
      # Compare authorized_keys against what you expect; remove anything else.
      # If you cannot account for every key, regenerate the key pair.
      ```

- [ ] Review `crontab -l`, `/etc/cron.*`, and `systemctl list-timers` for
      anything scheduled that you did not schedule.

---

## 7. Confirm nothing broke

- [ ] The site still serves:

      ```bash
      for u in / /listen /playlist /mixes /gallery /chat /webtv/ticker; do
        printf '%-14s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "https://www.hbr1.com$u")"
      done
      ```

- [ ] The legacy entry point still works — it reads the config directly and does
      not go through `App\Bootstrap`:

      ```bash
      curl -s -o /dev/null -w '%{http_code}\n' \
        'https://www.hbr1.com/cgi-bin/main.php?module=tracklist'
      ```

- [ ] Now-playing still populates, which is the Icecast credential working.

- [ ] Song requests still land, which is the `radiodj_%` write grant working.
      Use a station you can clear afterwards.

- [ ] `git status` is clean and no secret file is tracked:

      ```bash
      git ls-files | grep -E 'env\.local\.php|\.htpasswd$|\.old$'   # must be empty
      ```

---

## 8. Only then: the repository

- [ ] Push. `origin/main` was 22+ commits behind; until it is pushed, none of
      the removal in the working tree has reached GitHub at all.

- [ ] Decide on history. Scrubbing is `git filter-repo --path ... --invert-paths`
      followed by a force-push, which rewrites every commit hash. On a public
      remote this does **not** un-publish anything already fetched — it only
      stops the next clone from getting it. It is still worth doing, but **only
      after §2–§5**, so a stale clone cannot authenticate with anything.

- [ ] Consider making the repository private. There is no public interest in it,
      and it removes the whole class of problem.

---

## Sign-off

- [ ] MySQL rotated, dead grants revoked, `hbr1` retired or locked
- [ ] Icecast admin rotated, `ICECAST_STATS_URL` updated, now-playing confirmed
- [ ] Shoutcast rotated or polling stopped
- [ ] All four admin passwords changed and rehashed with bcrypt
- [ ] Mail / FTP / SSH checked, unexplained keys removed
- [ ] `/etc/hbr1/env.php` is `0640 root:www-data` and outside the docroot
- [ ] Site, legacy entry point, now-playing and requests all verified
- [ ] Pushed; history decision recorded

When the last box is ticked, replace this file with a line in
[`DB-CONNECTIONS.md`](DB-CONNECTIONS.md) saying when the rotation happened — the
*date* is useful, the values are not.
