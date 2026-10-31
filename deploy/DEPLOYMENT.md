# Deployment

Production is **Apache 2.4.58 + mod_fcgid** on Ubuntu 24.04
(`apache.lan.etwnet.com`, `www.hbr1.com`). There is **no nginx** on the
production host and **no composer** either — both were verified on the box.
The git repository is the deploy mechanism.

## What the server actually has

| | |
|---|---|
| OS | Ubuntu 24.04.5, kernel 6.8 |
| Web server | Apache 2.4.58 (no nginx installed) |
| PHP for the vhost | `php8.3-cgi` via `FcgidWrapper "/usr/bin/php-cgi -c /etc/"` |
| PHP CLI | 8.5.12 (default `php`), 8.3 available as `php8.3` |
| git | `/usr/bin/git` |
| rsync | `/usr/bin/rsync` |
| composer | **missing** |

`php8.3` extensions confirmed present: `mbstring`, `pdo`, `pdo_mysql`, `gd`,
`json`, `fileinfo`, `session`, `tokenizer`, `exif`, `opcache`, `apcu`.

Missing and required by `composer.json`:

| Extension | Needed by | Status |
|---|---|---|
| `ext-curl` | Icecast client (W3), later HTTP clients | **install** |
| `ext-dom` | XML handling for RadioDJ responses | **install** |

```
sudo apt install php8.3-curl php8.3-xml
```

`ext-intl`, `ext-zip` and `ext-imagick` are optional today (Clarity suggests
`intl`; gallery upload in W8 may want `imagick`).

## Deploying without composer on the server

`vendor/` is **not** committed, and composer is not installed on the box, so
build the artifact where composer exists (WSL) and ship the result:

```
git push          -> bare repo on the host
post-receive hook -> git archive + rsync into a release dir, flip the symlink
```

Shape:

```
/home/christian/WWWRoot/www.hbr1.com/
├── releases/2026-10-31-a/      # checked-out tree + vendor/
├── releases/2026-10-31-b/
├── current -> releases/2026-10-31-b
└── ...
```

The Apache `DocumentRoot` then points at
`/home/christian/WWWRoot/www.hbr1.com/current/public`, and a rollback is a
symlink flip.

Why not `git pull` into the docroot directly: it publishes `.git/`, it makes
rollback a reset that can lose local state, and a half-finished pull is a
broken site.

### The hook (draft, W9)

```bash
#!/bin/sh
# /home/christian/git/hbr1.com.git/hooks/post-receive
set -e
GIT_DIR=/home/christian/git/hbr1.com.git
ROOT=/home/christian/WWWRoot/www.hbr1.com
REL="$ROOT/releases/$(date +%Y-%m-%d-%H%M%S)"
while read -r _old _new ref; do
  [ "$ref" = "refs/heads/main" ] || continue
  mkdir -p "$REL"
  git --work-tree="$REL" --git-dir="$GIT_DIR" checkout -f main
done
# vendor/ is built on the developer machine and shipped with the release,
# because composer is not installed on the host.
rsync -a --delete "$GIT_DIR/../vendor/" "$REL/vendor/"
ln -sfn "$REL" "$ROOT/current"
```

`vendor/` transfer is the one open detail — either rsync it from the pushing
machine, commit it, or install composer on the host. **Recommendation: install
composer on the host** (`apt install composer`) and run `composer install
--no-dev --optimize-autoloader` inside the release dir. It removes a manual
step from every deploy and keeps the artifact honest.

## Local must equal the server

The dev box here is **nginx + php-fpm**; production is **Apache + mod_fcgid**.
That difference already caused a real bug: the nginx `alias` block for
`/playlist/` shadowed the `/playlist/{station}` route and returned 404, while
the Apache config (Alias + FallbackResource) would have been fine. Both configs
now use the same "serve the file if it exists, otherwise fall through to the
front controller" rule so the behaviour matches.

Keep [deploy/apache/www.hbr1.com.conf](www.hbr1.com.conf) as production truth
and [deploy/nginx/hbr1.evo.conf](../nginx/hbr1.evo.conf) as the dev
approximation, and change both together.
