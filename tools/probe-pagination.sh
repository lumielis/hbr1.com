#!/bin/bash
# Verify tracklist pagination against the built-in server.
set -u
cd /host/Projekte/Privat/hbr1.com || exit 1

PORT=${PORT:-8089}
php -S 127.0.0.1:${PORT} -t public public/index.php >/tmp/hbr1-tracklist.log 2>&1 &
SERVER_PID=$!
trap 'kill ${SERVER_PID} 2>/dev/null' EXIT

sleep 2

for offset in 0 40 200 100000; do
  page=$(curl -s "http://127.0.0.1:${PORT}/mixes/trance?offset=${offset}" \
    | grep -oE 'page [0-9]+ of [0-9]+' | head -1)
  printf 'offset=%-7s %s\n' "${offset}" "${page:-<none>}"
done

printf 'offset=-99     %s\n' \
  "$(curl -s "http://127.0.0.1:${PORT}/mixes/trance?offset=-99" \
     | grep -oE 'page [0-9]+ of [0-9]+' | head -1)"
