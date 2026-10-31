#!/bin/bash
# Start the PHP built-in server for the app and probe a set of URLs.
set -u
cd /host/Projekte/Privat/hbr1.com || exit 1

PORT=${PORT:-8099}
php -S 127.0.0.1:${PORT} -t public public/index.php >/tmp/hbr1-serve.log 2>&1 &
SERVER_PID=$!
trap 'kill ${SERVER_PID} 2>/dev/null' EXIT

sleep 2

urls=(
  "/"
  "/listen/trance"
  "/mixes"
  "/topframe"
  "/stream/trance.m3u"
  "/cgi-bin/main.php?module=tracklist&station=Trance"
  "/cgi-bin/main.php?module=../../htdocs/index"
  "/cgi-bin/asx.cgi?trance"
  "/nope"
)

for u in "${urls[@]}"; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}${u}")
  loc=$(curl -s -o /dev/null -w '%{redirect_url}' "http://127.0.0.1:${PORT}${u}")
  printf '%-52s %s %s\n' "$u" "$code" "$loc"
done
