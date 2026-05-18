#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)

if ! command -v heroku >/dev/null 2>&1; then
  echo "Heroku CLI is required but was not found in PATH." >&2
  exit 1
fi

if [ -z "${HEROKU_APP:-}" ]; then
  echo "HEROKU_APP not set. Example: HEROKU_APP=zach-peronal-site ./scripts/deploy.sh" >&2
  exit 1
fi

if ! heroku apps:info --app "$HEROKU_APP" >/dev/null 2>&1; then
  echo "Heroku app '$HEROKU_APP' does not exist. Run 'make heroku-create HEROKU_APP=$HEROKU_APP' first." >&2
  exit 1
fi

TMP=$(mktemp -d)
cleanup() {
  rm -rf "$TMP"
}
trap cleanup EXIT

echo "Creating temporary repo in $TMP"
rsync -a \
  --exclude '.git' \
  --exclude 'build' \
  --exclude 'vendor' \
  --exclude '.DS_Store' \
  "$ROOT/" "$TMP/"

cd "$TMP"
git init -q
git add -A
git commit -m "Deploy build $(date -u +%Y-%m-%dT%H:%M:%SZ)" -q

echo "Pushing to Heroku app: $HEROKU_APP"
HEROKU_GIT_URL=$(heroku info --app "$HEROKU_APP" --shell | awk -F= '/^git_url=/{print $2}')
if [ -z "$HEROKU_GIT_URL" ]; then
  HEROKU_GIT_URL="https://git.heroku.com/${HEROKU_APP}.git"
fi

# Attempt to push; requires that you have permissions (API key or logged in)
git remote add heroku "$HEROKU_GIT_URL" || true
git push --force heroku HEAD:main

echo "Deploy push complete. Check 'heroku releases --app $HEROKU_APP' for status."
