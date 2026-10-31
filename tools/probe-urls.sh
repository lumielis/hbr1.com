#!/bin/bash
# Probe a list of URLs given on stdin, one per line.
set -u
BASE=${BASE:-http://hbr1.evo}
while IFS= read -r u; do
  [ -z "$u" ] && continue
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u")
  size=$(curl -s -o /dev/null -w '%{size_download}' "$BASE$u")
  printf '%-34s %s %7s\n' "$u" "$code" "$size"
done
