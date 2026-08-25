#!/usr/bin/env bash
# check-phpstan-baseline-debt.sh — Rapport de dette PHPStan baseline (issue #5448)
#
# Métrique demandée par #5448 :
#   - nombre d'entrées baseline par fichier et par module
#   - ratio baseline / erreurs strictes
#   - total par fichier baseline (phpstan-baseline, phpstan-modules-baseline,
#     phpstan-strict-baseline)
#
# Usage:
#   dev-hub/tools/check-phpstan-baseline-debt.sh            # rapport sur l'arbre courant
#   dev-hub/tools/check-phpstan-baseline-debt.sh <sha>      # rapport sur un commit donné
#   dev-hub/tools/check-phpstan-baseline-debt.sh --ci       # + garde globale « 0 nouvelle entrée » vs main
#
# La garde par module existe déjà : check-phpstan-baseline-delta.sh (PA2-ARCH-005,
# branchée dans architecture-check.yml). Ce script est la MÉTRIQUE de pilotage.

set -euo pipefail
cd "$(dirname "$0")/../.."
ROOT="$(pwd)"
API_DIR="api"

SHA="${1:-HEAD}"
CI_MODE=0
if [ "$SHA" = "--ci" ]; then SHA="HEAD"; CI_MODE=1; fi

BASELINE_FILES=("phpstan-baseline.neon" "phpstan-modules-baseline.neon" "phpstan-strict-baseline.neon")

count_entries() {
  local sha="$1" file="$2"
  if [ "$sha" = "HEAD" ]; then
    [ -f "$API_DIR/$file" ] || { echo 0; return; }
    python3 - "$API_DIR/$file" <<'PYEOF'
import re, sys
c = open(sys.argv[1]).read()
print(sum(int(m) for m in re.findall(r"count:\s*(\d+)", c)))
PYEOF
  else
    git show "$sha:$API_DIR/$file" 2>/dev/null | python3 -c "
import re, sys
c = sys.stdin.read()
print(sum(int(m) for m in re.findall(r'count:\s*(\d+)', c)))
" || echo 0
  fi
}

module_breakdown() {
  local sha="$1" file="$2"
  if [ "$sha" = "HEAD" ]; then
    [ -f "$API_DIR/$file" ] || return 0
    python3 - "$API_DIR/$file" <<'PYEOF'
import re, sys, collections
c = open(sys.argv[1]).read()
entries = re.findall(r"count:\s*(\d+)\s*\n\s*path:\s*(\S+)", c)
def module_of(p):
    m = re.match(r"^app/(Core|Modules)/([^/]+)/", p)
    if m: return f"app/{m.group(1)}/{m.group(2)}"
    if p.startswith("app/Shared/"): return "app/Shared"
    if p.startswith(("app/Http", "app/Services")): return "app/" + p.split("/")[1]
    if p.startswith("tests/"): return "tests/" + p.split("/")[1]
    if p.startswith("app/"): return "app/" + p.split("/")[1]
    return "(autres)"
counts = collections.Counter()
for cnt, p in entries:
    counts[module_of(p)] += int(cnt)
for mod, c in counts.most_common():
    print(f"{mod}\t{c}")
PYEOF
  else
    git show "$sha:$API_DIR/$file" 2>/dev/null | python3 -c "
import re, sys, collections
c = sys.stdin.read()
entries = re.findall(r'count:\s*(\d+)\s*\n\s*path:\s*(\S+)', c)
def module_of(p):
    m = re.match(r'^app/(Core|Modules)/([^/]+)/', p)
    if m: return f'app/{m.group(1)}/{m.group(2)}'
    if p.startswith('app/Shared/'): return 'app/Shared'
    if p.startswith(('app/Http', 'app/Services')): return 'app/' + p.split('/')[1]
    return '(autres)'
counts = collections.Counter()
for cnt, p in entries:
    counts[module_of(p)] += int(cnt)
for mod, c in counts.most_common():
    print(f'{mod}\t{c}')
" || true
  fi
}

echo "═══════════════════════════════════════════════════════════"
echo "  PHPStan baseline — rapport de dette (issue #5448)"
echo "  Commit analysé : $SHA"
echo "═══════════════════════════════════════════════════════════"
echo ""

TOTAL=0
declare -A TOTALS
for f in "${BASELINE_FILES[@]}"; do
  n=$(count_entries "$SHA" "$f")
  TOTALS[$f]=$n
  TOTAL=$((TOTAL + n))
  printf "  %-38s %8d entrées\n" "$f" "$n"
done
echo ""
echo "  TOTAL (3 baselines) : $TOTAL entrées ignorées"
echo ""

echo "── Répartition par module (phpstan-strict-baseline.neon, niveau 8) ──"
echo ""
module_breakdown "$SHA" "phpstan-strict-baseline.neon" | while IFS=$'\t' read -r mod cnt; do
  printf "  %-45s %6d\n" "$mod" "$cnt"
done
echo ""

# Garde globale « 0 nouvelle entrée » vs main (ratchet unidirectionnel global)
if [ "$CI_MODE" = "1" ]; then
  echo "── Garde globale (vs origin/main) ──"
  BASE_SHA="$(git rev-parse origin/main 2>/dev/null || echo HEAD)"
  VIOLATIONS=0
  for f in "${BASELINE_FILES[@]}"; do
    base=$(count_entries "$BASE_SHA" "$f")
    head=$(count_entries "HEAD" "$f")
    if [ "$head" -gt "$base" ]; then
      echo "  ❌ $f : $base → $head (augmentation)"
      VIOLATIONS=$((VIOLATIONS + 1))
    else
      echo "  ✅ $f : $base → $head (pas de régression)"
    fi
  done
  echo ""
  if [ "$VIOLATIONS" -gt 0 ]; then
    echo "❌ $VIOLATIONS baseline(s) en augmentation — corrigez le code, n'ajoutez pas à la baseline (#5448)."
    exit 1
  fi
  echo "✅ Aucune nouvelle entrée baseline vs main."
fi
