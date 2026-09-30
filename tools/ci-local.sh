#!/usr/bin/env bash
# tools/ci-local.sh — the whole merge gate, exactly as CI runs it (.github/workflows/ci.yml only
# calls this script, so local runs and CI cannot drift). Needs PHP 8.1+ with curl, gd, mbstring,
# fileinfo and zip. Every step runs; the exit code is non-zero if any of them failed.
set -uo pipefail
cd "$(dirname "$0")/.."

failed=()
step() {
    local name="$1"; shift
    echo
    echo "== $name"
    if "$@"; then echo "   ok: $name"; else echo "   FAILED: $name"; failed+=("$name"); fi
}

lint() {
    find . \( -path ./dist -o -path ./.git -o -path ./node_modules \) -prune -o -name '*.php' -print0 \
        | xargs -0 -n1 -P4 php -l > /dev/null
}

step "php -l on every PHP file" lint
step "verify viaje.com.py --strict" php tools/verify.php viaje.com.py --strict
step "verify thingstodoinparaguay.com" php tools/verify.php thingstodoinparaguay.com
for t in tools/tests/*.php; do
    [ "$(basename "$t")" = "lib.php" ] && continue
    step "$t" php "$t"
done

echo
if [ ${#failed[@]} -eq 0 ]; then
    echo "ci-local: all steps passed"
    exit 0
fi
printf 'ci-local: %d step(s) failed:\n' "${#failed[@]}"
printf '  - %s\n' "${failed[@]}"
exit 1
