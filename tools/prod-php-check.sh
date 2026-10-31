#!/bin/bash
# Which extensions the app requires vs what php8.3 on the server actually has.
set -u
HOST=${HOST:-christian@apache.lan.etwnet.com}
run() { timeout 90 ssh -o BatchMode=yes "$HOST" "$1" 2>&1; }

echo "=== required extensions on php8.3 ==="
run 'for e in mbstring pdo pdo_mysql curl dom gd json fileinfo session tokenizer opcache intl zip imagick exif; do printf "%-12s " "$e"; php8.3 -m 2>/dev/null | grep -qix "$e" && echo yes || echo NO; done'

echo
echo "=== same on php 8.5 (CLI default) ==="
run 'for e in mbstring pdo_mysql curl dom gd; do printf "%-12s " "$e"; php -m 2>/dev/null | grep -qix "$e" && echo yes || echo NO; done'

echo
echo "=== which php does the vhost actually use ==="
run "grep -nE 'php|Fcgid|Handler' /etc/apache2/sites-enabled/www.hbr1.com.conf | head -20"

echo
echo "=== installed php packages ==="
run "dpkg -l | grep -E '^ii +php' | awk '{print \$2}' | tr '\n' ' '"
