#!/bin/bash
# Check that tracklist rows link to a working request form.
set -u
cd /host/Projekte/Privat/hbr1.com || exit 1

PORT=${PORT:-8082}
php -S 127.0.0.1:${PORT} -t public public/index.php >/tmp/hbr1-request-form.log 2>&1 &
SERVER_PID=$!
trap 'kill ${SERVER_PID} 2>/dev/null' EXIT

sleep 2

BASE="http://127.0.0.1:${PORT}"

ids=$(curl -s "${BASE}/mixes/trance" | grep -oE '/request/trance/[0-9]+' | head -3)

if [ -z "${ids}" ]; then
  echo "no request links found on the tracklist"
  exit 1
fi

for link in ${ids}; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "${BASE}${link}")
  printf '%-28s %s\n' "${link}" "${code}"
done

# A station slug that does not exist must be a 404, not a redirect loop.
printf '%-28s %s\n' "/request/rock/1" \
  "$(curl -s -o /dev/null -w '%{http_code}' "${BASE}/request/rock/1")"
