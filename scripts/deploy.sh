#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
BUILD=$ROOT/build

if [ ! -d "$BUILD" ]; then
  echo "Build directory not found. Run ./scripts/build.sh first." >&2
  exit 1
fi

echo "Preparing deploy from $BUILD"

if [ -z "${HEROKU_APP:-}" ]; then
  echo "HEROKU_APP not set. To push to Heroku, set HEROKU_APP environment variable to your app name."
  echo "Example: HEROKU_APP=my-app ./scripts/deploy.sh"
  echo "Alternatively, upload the contents of $BUILD to your static host."
  exit 0
fi

TMP=$(mktemp -d)
echo "Creating temporary repo in $TMP"
cd "$TMP"
git init -q
cp -R "$BUILD"/* .
git add -A
git commit -m "Deploy build $(date -u +%Y-%m-%dT%H:%M:%SZ)" -q || true

echo "Pushing to Heroku app: $HEROKU_APP"
HEROKU_GIT_URL="https://git.heroku.com/${HEROKU_APP}.git"

# Attempt to push; requires that you have permissions (API key or logged in)
git remote add heroku "$HEROKU_GIT_URL" || true
git push --force heroku master

echo "Deploy push attempted. Check Heroku dashboard for release status."
