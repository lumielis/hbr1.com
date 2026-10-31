#!/bin/bash
# Read-only survey of the production box for the deploy design.
set -u
HOST=${HOST:-christian@apache.lan.etwnet.com}
run() { timeout 90 ssh -o BatchMode=yes "$HOST" "$1" 2>&1; }

echo "=== os ==="
run "lsb_release -ds 2>/dev/null; uname -r"
echo
echo "=== web servers installed / listening ==="
run "dpkg -l | grep -E '^ii +(apache2|nginx)' | awk '{print \$2, \$3}'"
run "ss -ltnp 2>/dev/null | grep -E ':80 |:443 ' | head -5"
echo
echo "=== php ==="
run "php -v 2>/dev/null | head -2; which php php8.3 php-cgi 2>/dev/null"
echo
echo "=== git / rsync / composer ==="
run "for b in git rsync composer; do printf '%-10s ' \$b; command -v \$b || echo MISSING; done"
echo
echo "=== php extensions that matter ==="
run "php -m 2>/dev/null | grep -iE 'pdo_mysql|mysqli|mbstring|intl|gd|imagick|zip|curl|xml' | tr '\n' ' '"
echo
echo "=== nginx vhosts present ==="
run "ls /etc/nginx/sites-enabled/ 2>/dev/null; ls /etc/nginx/conf.d/ 2>/dev/null"
echo
echo "=== apache vhosts present ==="
run "ls /etc/apache2/sites-enabled/ 2>/dev/null"
