#!/usr/bin/env bash
# tools/imagery-preflight.sh [--cdn-url <url>]
#
# Run this BEFORE calling any Higgsfield generation tool. It answers, from the
# repo and the network, the four questions that cost hours when skipped:
#   1. Is there already an imagery manifest / plan in this repo?
#   2. Has imagery already been generated or localized (git history)?
#   3. Are the manifest's images already on disk?
#   4. Can THIS session download from the Higgsfield CDN?
# Exit 0 = safe to proceed to the printed next step. Exit 3 = generation is
# already done; do not generate. Exit 4 = CDN unreachable from here; fix the
# environment (see the printed instructions) before generating anything.
set -u
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)"

CDN_URL=""
while [ $# -gt 0 ]; do
  case "$1" in
    --cdn-url) CDN_URL="$2"; shift 2 ;;
    *) echo "unknown arg $1" >&2; exit 2 ;;
  esac
done

hr() { printf '\n== %s\n' "$1"; }

hr "1. Existing manifests / plans"
MANIFESTS=$(git ls-files | grep -E -i '(imagery|image|images)[-_.]?(manifest|plan|brief)\.(json|md|csv)$|assets/img/manifest\.json$|jobs\.csv$' || true)
if [ -n "$MANIFESTS" ]; then echo "$MANIFESTS"; else echo "(none tracked)"; fi

hr "2. Prior imagery commits (git log)"
git log --oneline -i -E --grep='imag(e|es|ery)|higgsfield|localize|webimg|photos?' | head -20 || true

hr "3. Manifest images already on disk?"
DONE=0
for M in $(echo "$MANIFESTS" | grep -E 'imagery-manifest\.json$' || true); do
  python3 - "$M" <<'PY'
import json, sys, glob, os
m = json.load(open(sys.argv[1]))
imgs = m.get("images", [])
present = missing = 0
for i in imgs:
    f = i.get("file") or ""
    base = os.path.splitext(f)[0]
    hits = glob.glob(f"**/assets/img/{base}*", recursive=True)
    if hits: present += 1
    else: missing += 1; print(f"  missing: {f}")
print(f"{sys.argv[1]}: {len(imgs)} images planned, {present} localized, {missing} missing")
sys.exit(0 if missing else 3)
PY
  [ $? -eq 3 ] && DONE=1
done

hr "4. Can this session reach the Higgsfield CDN?"
if [ -z "$CDN_URL" ]; then
  CDN_URL=$(grep -rhoE 'https://[a-z0-9]+\.cloudfront\.net/[^" ]+\.(png|jpg|webp)' docs 2>/dev/null | head -1 || true)
fi
[ -z "$CDN_URL" ] && CDN_URL="https://d8j0ntlcm91z4.cloudfront.net/"
HOST=$(echo "$CDN_URL" | sed -E 's#https?://([^/]+).*#\1#')
CODE=$(curl -sS -o /dev/null -w '%{http_code}' -m 15 -I "$CDN_URL" 2>/dev/null); CODE=${CODE:-000}
echo "HEAD $HOST -> HTTP $CODE"

if [ "$DONE" = 1 ]; then
  echo
  echo "RESULT: imagery already generated AND localized. Do NOT generate. Edit content instead."
  exit 3
fi
if [ "$CODE" = 000 ] || [ "$CODE" = 403 ]; then
  cat <<TXT

RESULT: this session CANNOT download from $HOST. Do not generate yet.
Fix (one-time, per cloud environment, ~1 minute):
  claude.ai/code -> environment selector -> edit environment ->
  Network access: Custom -> Allowed domains, add:
      *.cloudfront.net
  tick "Also include default list of common package managers", save,
  then start a NEW session in that environment and re-run this script.
Docs: https://code.claude.com/docs/en/cloud-environments#access-levels
TXT
  exit 4
fi
echo
echo "RESULT: CDN reachable. Safe to generate (if step 3 shows missing images) and localize in-session."
exit 0
