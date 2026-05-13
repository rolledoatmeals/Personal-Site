#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
HTML=/tmp/site_audit.html
curl -sSf http://127.0.0.1:8000/ -o "$HTML"

# Check HTTP OK
if [ ! -s "$HTML" ]; then
  echo "ERROR: failed to fetch page"
  exit 1
fi

# Basic checks
MISSING_ALT=$(grep -i "<img" -n "$HTML" | grep -v "alt=" || true)
SVG_COUNT=$(grep -o "<svg" "$HTML" | wc -l || true)
HTTP_STATUS=0
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/ | { read -r HTTP_STATUS; echo "HTTP status: $HTTP_STATUS"; }

echo "inline SVGs: $SVG_COUNT"
if [ -n "$MISSING_ALT" ]; then
  echo "Images missing alt attributes (line numbers):"
  echo "$MISSING_ALT"
else
  echo "All images have alt attributes (quick grep check)."
fi

echo "Audit complete."
