#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
BUILD=$ROOT/build
PUBLIC=$ROOT/public

rm -rf "$BUILD"
mkdir -p "$BUILD"

# Copy public files
cp -R "$PUBLIC/" "$BUILD/"

# Minify CSS (compatible with macOS sed/awk) - conservative
CSS_IN="$BUILD/assets/css/style.css"
if [ -f "$CSS_IN" ]; then
  echo "Minifying CSS..."
  # remove /* ... */ blocks and collapse whitespace
  awk 'BEGIN{RS="";ORS="\n"} {gsub(/\/\*[\s\S]*?\*\//,""); print}' "$CSS_IN" | tr '\n' ' ' | tr -s ' ' > "$CSS_IN.min"
  mv "$CSS_IN.min" "$CSS_IN"
fi

# Minify JS (strip // and /* */ comments conservatively)
JS_IN="$BUILD/assets/js/main.js"
if [ -f "$JS_IN" ]; then
  echo "Minifying JS..."
  awk 'BEGIN{RS="";ORS="\n"} {gsub(/\/\*[\s\S]*?\*\//,""); print}' "$JS_IN" | sed -E 's/\/\/[^\n]*//g' | tr '\n' ' ' | tr -s ' ' > "$JS_IN.min"
  mv "$JS_IN.min" "$JS_IN"
fi

# Gzip static assets
echo "Gzipping assets..."
find "$BUILD" -type f \( -name "*.html" -o -name "*.css" -o -name "*.js" -o -name "*.svg" \) -print0 | xargs -0 -n1 gzip -9 -c > /dev/null 2>&1 || true

# Summary
echo "Build complete: $BUILD"
ls -la "$BUILD" | sed -n '1,120p'
