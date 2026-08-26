#!/usr/bin/env bash
# check-cross-module-imports.sh
# Issue #5584 : garde CI contre les nouveaux imports croisés inter-modules.
#
# Stratégie :
#   - Les violations existantes (audit 2026-08-26) sont GELÉES : elles ne
#     bloquent pas ce check.
#   - Ce script échoue si le DIFF du PR introduit un NOUVEL import croisé
#     entre modules distincts (Modules/X → use App\Modules\Y, X ≠ Y)
#     ou une nouvelle dépendance Core → Modules.
#
# Usage :
#   check-cross-module-imports.sh <base_sha> <head_sha> [api_dir]
#
# Exit codes : 0 = OK, 1 = new violations found

set -euo pipefail

BASE_SHA="${1:-}"
HEAD_SHA="${2:-}"
API_DIR="${3:-api}"

if [[ -z "$BASE_SHA" || -z "$HEAD_SHA" ]]; then
  echo "Usage: $0 <base_sha> <head_sha> [api_dir]"
  exit 1
fi

BASE_SHA="$(git rev-parse "$BASE_SHA")"
HEAD_SHA="$(git rev-parse "$HEAD_SHA")"

if [[ "$BASE_SHA" == "$HEAD_SHA" ]]; then
  echo "ℹ️  base == head — nothing to check."
  exit 0
fi

# Write diff to a temp file to avoid process substitution portability issues.
DIFF_TMP="$(mktemp)"
trap 'rm -f "$DIFF_TMP"' EXIT

git diff "${BASE_SHA}...${HEAD_SHA}" \
  -- "${API_DIR}/app/Modules/" "${API_DIR}/app/Core/" \
  > "$DIFF_TMP"

ERRORS=0
REPORT=""
current_file=""

while IFS= read -r diff_line; do
  # Track current file from diff header ("+++ b/path/to/file.php")
  if [[ "$diff_line" =~ ^\+\+\+\ b/(.+\.php)$ ]]; then
    current_file="${BASH_REMATCH[1]}"
    continue
  fi

  # Only process added lines (not +++, not context)
  if [[ ! "$diff_line" =~ ^\+[^+] ]]; then
    continue
  fi

  content="${diff_line:1}"

  # Must be a `use App\Modules\` statement
  if [[ ! "$content" =~ ^use\ App\\Modules\\ ]]; then
    continue
  fi

  # Only files under api/app/Modules or api/app/Core
  if [[ -z "$current_file" ]]; then
    continue
  fi
  if [[ ! "$current_file" =~ ^${API_DIR}/app/(Modules|Core)/ ]]; then
    continue
  fi

  # Extract target module: use App\Modules\<TargetModule>\...
  if [[ "$content" =~ ^use\ App\\Modules\\([A-Za-z]+)\\ ]]; then
    target_module="${BASH_REMATCH[1]}"
  else
    continue
  fi

  # Determine source module
  source_module=""
  if [[ "$current_file" =~ ^${API_DIR}/app/Modules/([A-Za-z]+)/ ]]; then
    source_module="${BASH_REMATCH[1]}"
  elif [[ "$current_file" =~ ^${API_DIR}/app/Core/ ]]; then
    source_module="Core"
  fi

  [[ -z "$source_module" ]] && continue

  # Same-module import is fine
  [[ "$source_module" == "$target_module" ]] && continue

  # New cross-module import detected
  ERRORS=$((ERRORS + 1))
  REPORT="${REPORT}  $(printf '%-80s' "❌  ${current_file}")\n"
  REPORT="${REPORT}      └── ${source_module} → App\\Modules\\${target_module}\n"
  REPORT="${REPORT}          ${content}\n"

done < "$DIFF_TMP"

if [[ "$ERRORS" -eq 0 ]]; then
  echo "✅  No new cross-module imports detected."
  exit 0
fi

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  ARCHITECTURE GUARD — Cross-Module Imports (issue #5584)"
echo "══════════════════════════════════════════════════════════════"
echo ""
echo "  Found ${ERRORS} new cross-module import(s) in this PR."
echo "  Rule (ARCHITECTURE.md §52-54): a module must not import"
echo "  classes of another module directly."
echo ""
printf "%b" "${REPORT}"
echo ""
echo "  Fix: use an interface/contract or an injected service instead"
echo "  of importing the model/class directly across module boundaries."
echo ""
exit 1
