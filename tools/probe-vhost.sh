#!/bin/bash
# Probe the app as served by the real dev vhost (nginx), not `php -S`.
#
# This is the check that matters: it goes through the same layers as
# production (web server -> front controller -> dispatcher -> view), so it
# catches things the in-process smoke test cannot, such as a static alias
# shadowing an application route.
set -u
BASE=${BASE:-http://hbr1.evo}

# Real ids, read from the live database with tools/probe-gallery.php.
CAT=${CAT:-16}
GAL=${GAL:-31}
PIC=${PIC:-536}

urls=(
  "/"
  "/listen"
  "/listen/trance"
  "/listen/Trance"
  "/listen/nope"
  "/playlist"
  "/playlist/tronic"
  "/playlist/trance"
  "/tracklist"
  "/tracklist/ambient"
  "/tracklist/nope"
  "/request/trance/1"
  "/topframe"
  "/gallery"
  "/gallery/${CAT}"
  "/gallery/${CAT}/${GAL}"
  "/gallery/pic/${PIC}"
  "/gallery/9999"
  "/stream/trance.m3u"
  "/stream/trance.asx"
  "/stream/trance.pls"
  "/cgi-bin/main.php?module=tracklist&station=Trance"
  "/cgi-bin/main.php?module=gallery"
  "/perl/apax.cgi?run=navigation"
  "/cgi-bin/asx.cgi?trance"
  "/cgi-bin/main.php?module=../../htdocs/index"
  # Static assets must keep working from the legacy tree.
  "/style/client.css"
  "/playlist/trance.ogg.m3u"
  "/chat/index.html"
  "/webtv/webtv.jpg"
  # Nothing outside public/ may be reachable.
  "/app/Bootstrap.php"
  "/env.php"
  "/config/config.php"
  "/deploy/PHASE-0-RUNBOOK.md"
  "/nope"
)

fail=0
for u in "${urls[@]}"; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u")
  size=$(curl -s -o /dev/null -w '%{size_download}' "$BASE$u")
  loc=$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE$u")
  printf '%-52s %s %7s %s\n' "$u" "$code" "$size" "$loc"
done
