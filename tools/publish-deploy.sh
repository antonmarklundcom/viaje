#!/usr/bin/env bash
# tools/publish-deploy.sh — build viaje.com.py and push the built document root as the
# root of the `deploy` branch, for hPanel → Advanced → GIT (branch: deploy).
# site/content, site/media, site/data and site/cache are NOT published: they belong to
# the server (admin edits, leads) and a Git deploy must never overwrite or delete them.
# Seed content once with out/viaje.com.py-content-seed.zip (see docs/cutover-runbook.md).
set -euo pipefail
repo="$(cd "$(dirname "$0")/.." && pwd)"
domain=viaje.com.py
cd "$repo"
php tools/build.php "$domain" --fresh
tmp="$(mktemp -d)"
cp -a "dist/$domain/." "$tmp/"
rm -rf "$tmp/site/content" "$tmp/site/media" "$tmp/site/data" "$tmp/site/cache"
printf 'site/config.local.php\nsite/content/\nsite/media/\nsite/data/\nsite/cache/\n' > "$tmp/.gitignore"
remote="$(git remote get-url origin)"
sha="$(git rev-parse --short HEAD)"
cd "$tmp"
git init -q -b deploy
git add -A
git -c user.name="deploy" -c user.email="deploy@viaje.com.py" commit -qm "Deploy build of $sha"
git push -f "$remote" deploy:deploy
rm -rf "$tmp"
