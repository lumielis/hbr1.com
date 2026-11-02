# Phase 0 — Security runbook (execute ON THE SERVER)

These steps cannot be a git commit: the production host has no git checkout,
and several of them change state outside the repository. Work through them in
order and tick each one off.

Reference: `plan.md` §2 (diagnosis) and §3 (this runbook).

---

## 0. Before you touch anything

- [ ] Take a filesystem snapshot / `tar` of `/home/christian/WWWRoot/www.hbr1.com`
      and a `mysqldump` of the `hbr1` database.
- [ ] Note which Perl interpreter each surviving endpoint uses, so a mistake is
      recoverable: the site currently runs on `/usr/local/bin/perl` while
      `/usr/bin/perl` (5.38) and `/opt/perl/bin/perl` (stale 5.10, segfaults)
      are broken.
- [ ] Keep the Apache config backup: `cp -a /etc/apache2/sites-enabled/www.hbr1.com.conf{,.bak}`.

---

## 1. Remove the webshells

Two remote shells were on the server in what was then the ticker upload directory.
That directory is now `public/webtv/ticker/images/`, so the paths below follow it:

| File | What it is |
|---|---|
| `public/webtv/ticker/images/.x.php` | b374k 2.8 remote shell (99 KB) |
| `public/webtv/ticker/images/xm.php` | `eval(base64_decode(...))` + `eval(urldecode($_POST['ev']))` uploader |

Neither is tracked in git (`tests/SecretsTest.php` asserts that no `.php` other
than `public/index.php` is tracked under the document root), so this is a
server-side deletion only.

- [ ] Delete both from the server:
      ```bash
      rm -f /home/christian/WWWRoot/www.hbr1.com/public/webtv/ticker/images/.x.php
      rm -f /home/christian/WWWRoot/www.hbr1.com/public/webtv/ticker/images/xm.php
      ```
- [ ] Search for other copies dropped through them (these shells typically
      leave siblings):
      ```bash
      find /home/christian/WWWRoot -type f \( -name '*.php' -o -name '*.phtml' \) \
           -newermt '2020-01-01' -print0 | xargs -0 grep -lE 'eval\s*\(\s*(base64_decode|urldecode|gzinflate|str_rot13)' 2>/dev/null
      find /home/christian/WWWRoot -type f -name '.*.php' -print
      find /home/christian/WWWRoot -type f -perm -u+x -name '*.php' -print
      ```
- [ ] Check Apache access logs for hits on the shells before deleting them:
      ```bash
      grep -aE '(/\.x\.php|xm\.php)' /var/log/apache2/*access* \
        /home/christian/WWWRoot/www.hbr1.com/log/access.log 2>/dev/null | tail -50
      ```
      **If there are hits, treat the host as compromised**: audit `crontab -l`,
      `~/.ssh/authorized_keys`, `/etc/passwd` for new users, and outbound traffic.

## 2. Rotate every credential reachable from that docroot

Assume all of them are public — `config.pab.pl` and `settings.inc` were served
as plain text.

**This is now its own document: [`CREDENTIAL-ROTATION.md`](CREDENTIAL-ROTATION.md).**
Work through it before anything else here, in the order it gives — MySQL last,
because rotating it first locks the site out of its own configuration. It covers:

- MySQL `hbr1-web` (and retiring `hbr1`), including the dead `samdb_%` and
  `hbr1_trance|tronic|ambient` grants;
- the Icecast admin password, which also feeds the now-playing panel;
- the **Shoutcast** admin password, a separate secret on `217.64.169.133`;
- the four `htdocs/admin/.htpasswd` passwords, rehashed with bcrypt;
- mail, FTP and SSH, plus an audit for anything scheduled that you did not
  schedule.

The values must now live in `/etc/hbr1/env.php`; see §4 below.

## 3. Stop serving internals as text

- [ ] Add the deny block to `/etc/apache2/sites-enabled/www.hbr1.com.conf`
      (see `deploy/apache/www.hbr1.com.conf`, section "Phase 0"):

      ```apache
      <DirectoryMatch "^/home/christian/WWWRoot/www\.hbr1\.com/(cgi-bin/(include|lib|module)|config|app|views|db|deploy|tools|tests|legacy|var)">
          Require all denied
      </DirectoryMatch>
      <FilesMatch "^\.">
          Require all denied
      </FilesMatch>
      <FilesMatch "\.(bak|old|orig|swp|save|inc|pl|pm|pab|cgi|xp)$">
          Require all denied
      </FilesMatch>
      ```

- [ ] `Options -Indexes` on the `cgi-bin` Directory (it is currently
      `Options +ExecCGI -Includes` with listing enabled globally).
- [ ] `apachectl configtest && systemctl reload apache2`
- [ ] Verify from outside:
      ```bash
      for u in /cgi-bin/include/config.pab.pl /cgi-bin/include/settings.inc \
               /cgi-bin/include/ /cgi-bin/lib/class_db_mysqli.php \
               /htdocs/admin/.htpasswd /admin/.htpasswd; do
        printf '%-44s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "https://www.hbr1.com$u")"
      done
      ```
      Everything above must be `403` or `404`.

## 4. Move secrets out of the document root

- [ ] Create `/etc/hbr1/env.php` (mode `0640`, group `www-data`) holding the
      credentials, using the same constant names as `env.php` in the repo.
- [ ] The application already reads it via `HBR1_ENV` (default
      `/etc/hbr1/env.php`), so no code change is needed at cutover.
- [ ] Keep it out of the repo. Do **not** add a `.dist` copy containing real
      values.

## 5. Path traversal in the legacy PHP

`main.php?module=../../htdocs/index` loads arbitrary PHP outside `include/`.
Until W4 replaces the module, add a whitelist to `cgi-bin/main.php`:

- [ ] Replace the free-form `require` with an allowlist of the eight known
      modules (`tracklist`, `listen`, `playlist`, `topframe`, `gallery`,
      `muses`, and the admin ones actually in use).
- [ ] Verify: `curl -s -o /dev/null -w '%{http_code}' 'https://www.hbr1.com/cgi-bin/main.php?module=../../htdocs/index'` must not be `200`.

## 6. Admin auth

- [ ] `htdocs/admin/htaccess.txt` is not an active `.htaccess` (wrong filename).
      Production returns `401` on `/admin/`, so find out what enforces it:
      ```bash
      grep -rn 'admin' /etc/apache2/sites-enabled/ /etc/apache2/conf-enabled/ | head
      ls -la /home/christian/WWWRoot/www.hbr1.com/htdocs/admin/.htaccess
      ```
- [ ] Record the answer in `deploy/apache/www.hbr1.com.conf` — the PHP
      replacement (`AdminAuthMiddleware`, W8) must preserve the same gate.

## 7. Xdebug and error display

- [ ] Make sure Xdebug is off for any pool reachable from the internet:
      ```bash
      php -i | grep -i 'xdebug.mode'
      ```
      The dev vhost currently dumps full stack traces (including file paths and
      SQL) into responses.
- [ ] `display_errors = Off`, `log_errors = On` for production.

## 8. Delete dead weight

The repository cleanup already moved this content out of the served tree: it now
lives under `legacy/archive/`, which the vhost denies and git keeps only for
reference. These steps remove it from the **server checkout**, which is a
different operation and still worth doing.

- [ ] `htdocs/test/` — the `.rar` archive and `config.xml` were deleted from the
      repository; the directory is now `legacy/archive/htmldocs/test/`. Remove it
      from the server.
- [ ] `htdocs/chat_old/` → `legacy/archive/htmldocs/chat_old/`
- [ ] `htdocs/extra/` → `legacy/archive/htmldocs/extra/`
- [ ] `htdocs/` itself should no longer exist after the cutover: the document root
      is `public/`, and every static asset that is still used was moved into it.
      Confirm nothing is left behind:
      ```bash
      ls -la /home/christian/WWWRoot/www.hbr1.com/
      ```
- [ ] Confirm with the access log first:
      ```bash
      for d in test chat_old extra; do
        echo "$d: $(grep -ac "/$d/" /var/log/apache2/*access* 2>/dev/null | paste -sd+ | bc)"
      done
      ```

## 9. Sign-off

- [ ] `curl -sI https://www.hbr1.com/` → 200
- [ ] `curl -s -o /dev/null -w '%{http_code}\n' 'https://www.hbr1.com/cgi-bin/main.php?module=tracklist'` → 200 (site still up)
- [ ] No file under `cgi-bin/` other than an entry point is web-reachable
- [ ] Webshells gone, credentials rotated, `/etc/hbr1/env.php` in place
