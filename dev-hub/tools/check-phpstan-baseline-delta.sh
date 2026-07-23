#!/usr/bin/env bash
#
# PA2-ARCH-005 — CI guard: fail a diff that INCREASES the number of ignored
# PHPStan/Larastan errors attributed to a module the PR already touches.
#
# Context: docs/PLAN_ACTION2/PHPSTAN_BASELINE_REDUCTION_PLAN.md (created for
# this ticket) documents a per-module reduction plan for
# api/phpstan-baseline.neon and api/phpstan-modules-baseline.neon (1168 and
# 53 ignored error occurrences respectively at the time of writing). The
# acceptance criterion for PA2-ARCH-005 is a reduction plan *and* a tracked
# delta on every PR touching an already-baselined ("old") module — this
# script is that delta check.
#
# Method (diff-scoped, consistent with the sibling guards in this
# directory: check-strict-types-new-files.sh / PA2-ARCH-009,
# check-hardcoded-accented-messages.sh / PA2-I18N-007):
#   1. Parse `count:`/`path:` pairs out of both baseline files at base_sha
#      and head_sha (via `git show`), bucket them per module using the
#      same app/Core/<X>, app/Modules/<X>, app/Shared boundary the
#      reduction plan doc uses.
#   2. Find which modules under app/Core, app/Modules, or app/Shared have
#      an added/modified/deleted PHP file in this diff.
#   3. For each touched module, compare its ignored-error count at
#      head_sha vs base_sha in EACH baseline file. Fail if it increased in
#      either file. A module not touched by the diff is never checked (so
#      pre-existing debt elsewhere never blocks an unrelated PR); a
#      decrease or an equal count always passes.
#
# This is a one-way ratchet: it only prevents new debt in a module you are
# already modifying, exactly as the ticket's acceptance criterion asks for.
# It intentionally does not require the count to reach zero, and does not
# touch app/Http or app/Services (legacy, pre-module-migration code, out of
# scope for the module-keyed baseline files — see the reduction plan doc,
# Phase 3).
#
# Usage: dev-hub/tools/check-phpstan-baseline-delta.sh <base_sha> <head_sha> [api_dir]

set -euo pipefail

BASE_SHA="${1:-}"
HEAD_SHA="${2:-}"
API_DIR="${3:-api}"

if [ -z "$BASE_SHA" ] || [ -z "$HEAD_SHA" ]; then
  echo "Usage: $0 <base_sha> <head_sha> [api_dir]" >&2
  exit 1
fi

if ! git cat-file -e "${BASE_SHA}^{commit}" 2>/dev/null; then
  git fetch --no-tags --depth=1 origin "${BASE_SHA}" 2>/dev/null || true
fi
if ! git cat-file -e "${HEAD_SHA}^{commit}" 2>/dev/null; then
  git fetch --no-tags --depth=1 origin "${HEAD_SHA}" 2>/dev/null || true
fi

BASELINE_FILES=("phpstan-baseline.neon" "phpstan-modules-baseline.neon")

get_module_counts() {
  local sha="$1"
  local file="$2"
  local tmp
  tmp="$(mktemp)"
  if ! git show "${sha}:${API_DIR}/${file}" > "$tmp" 2>/dev/null; then
    rm -f "$tmp"
    return 0
  fi

  python3 - "$tmp" <<'PYEOF'
import re
import sys

content = open(sys.argv[1]).read()
entries = re.findall(r"count:\s*(\d+)\s*\n\s*path:\s*(\S+)", content)

def module_of(path):
    m = re.match(r"^app/(Core|Modules)/([^/]+)/", path)
    if m:
        return f"app/{m.group(1)}/{m.group(2)}"
    if path.startswith("app/Shared/") or path == "app/Shared":
        return "app/Shared"
    return None

counts = {}
for c, p in entries:
    mod = module_of(p)
    if mod is None:
        continue
    counts[mod] = counts.get(mod, 0) + int(c)

for mod, c in sorted(counts.items()):
    print(f"{mod}\t{c}")
PYEOF
  rm -f "$tmp"
}

# Modules touched by this diff (any PHP file added/modified/deleted under
# api/app/Core/<X>/** or api/app/Modules/<X>/** or api/app/Shared/**).
mapfile -t touched_php_files < <(
  git diff --name-only --diff-filter=ACMRD "${BASE_SHA}" "${HEAD_SHA}" -- "${API_DIR}/app" \
    | grep -E '\.php$' \
    | sort -u || true
)

if [ ${#touched_php_files[@]} -eq 0 ]; then
  echo "No PHP files touched under ${API_DIR}/app in this diff — nothing to check."
  exit 0
fi

declare -A touched_modules

for f in "${touched_php_files[@]}"; do
  rel="${f#"${API_DIR}"/}"
  if [[ "$rel" =~ ^app/(Core|Modules)/([^/]+)/ ]]; then
    touched_modules["app/${BASH_REMATCH[1]}/${BASH_REMATCH[2]}"]=1
  elif [[ "$rel" =~ ^app/Shared(/|$) ]]; then
    touched_modules["app/Shared"]=1
  fi
done

if [ ${#touched_modules[@]} -eq 0 ]; then
  echo "This diff only touches legacy app/Http, app/Services or non-module code — no per-module baseline to check (see PHPSTAN_BASELINE_REDUCTION_PLAN.md Phase 3)."
  exit 0
fi

VIOLATIONS=0

for baseline_file in "${BASELINE_FILES[@]}"; do
  base_counts_raw="$(get_module_counts "$BASE_SHA" "$baseline_file")"
  head_counts_raw="$(get_module_counts "$HEAD_SHA" "$baseline_file")"

  for module in "${!touched_modules[@]}"; do
    base_count="$(echo "$base_counts_raw" | awk -F'\t' -v m="$module" '$1==m{print $2}')"
    head_count="$(echo "$head_counts_raw" | awk -F'\t' -v m="$module" '$1==m{print $2}')"
    base_count="${base_count:-0}"
    head_count="${head_count:-0}"

    if [ "$head_count" -gt "$base_count" ]; then
      echo "❌ ${baseline_file}: ${module} ignored-error count increased (${base_count} -> ${head_count})."
      VIOLATIONS=$((VIOLATIONS + 1))
    else
      echo "✅ ${baseline_file}: ${module} ignored-error count ${base_count} -> ${head_count} (no regression)."
    fi
  done
done

echo ""
echo "Checked ${#touched_modules[@]} touched module(s) against ${#BASELINE_FILES[@]} baseline file(s)."

if [ "$VIOLATIONS" -gt 0 ]; then
  echo "Found $VIOLATIONS module(s) with an increased PHPStan baseline count (PA2-ARCH-005)."
  echo "Fix the new finding instead of adding it to the baseline, or see docs/PLAN_ACTION2/PHPSTAN_BASELINE_REDUCTION_PLAN.md."
  exit 1
fi

echo "✅ No touched module regressed its PHPStan baseline count."
