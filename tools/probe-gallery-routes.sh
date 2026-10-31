#!/bin/bash
# Verify the gallery routes against the built-in server.
set -u
cd /host/Projekte/Privat/hbr1.com || exit 1

PORT=${PORT:-8080}
php -S 127.0.0.1:${PORT} -t public public/index.php >/tmp/hbr1-gallery.log 2>&1 &
SERVER_PID=$!
trap 'kill ${SERVER_PID} 2>/dev/null' EXIT

sleep 2
BASE="http://127.0.0.1:${PORT}"

for path in /gallery /gallery/16 /gallery/16/31 /gallery/pic/536 /gallery/999 /gallery/16/26; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "${BASE}${path}")
  printf '%-22s %s\n' "${path}" "${code}"
done

echo
echo "--- category 16 content ---"
curl -s "${BASE}/gallery/16" | grep -oE '/gallery/16/[0-9]+|page [0-9]+ of [0-9]+' | head -6

echo
echo "--- gallery 31 pictures ---"
curl -s "${BASE}/gallery/16/31" | grep -oE '/gallery/pic/[0-9]+' | head -4
