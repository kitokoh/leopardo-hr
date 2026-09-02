#!/usr/bin/env bash
# =============================================================================
# pre-push-checks.sh — Validation locale AVANT push (règle « PR verte à la
# création »). Réduit drastiquement le nombre de checks CI rouges et le temps
# passé au merge (issue #6749).
#
# Usage :  ./scripts/pre-push-checks.sh [--front] [--api] [--fast]
#   --front  inclut les checks front (eslint, tsc, jest ciblé) — nécessite npm i
#   --api    inclut les checks backend (pint, php -l) — nécessite composer install
#   --fast   ne lance que les gardes rapides (défaut)
#
# Exit 0 = tout est vert ; exit 1 = violations à corriger avant push.
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ERRORS=0
run() {
  echo "── $*"
  if ! bash -c "$*"; then
    echo "❌ ÉCHEC : $*"
    ERRORS=$((ERRORS + 1))
  fi
}

DO_FRONT=false
DO_API=false
for arg in "$@"; do
  case "$arg" in
    --front) DO_FRONT=true ;;
    --api) DO_API=true ;;
    --fast) ;;
  esac
done

# ── Gardes rapides (toujours) ────────────────────────────────────────────────
run "bash dev-hub/tools/check-migration-basename-collisions.sh"
run "dev-hub/tools/check-module-isolation.sh"
run "bash dev-hub/tools/check-architecture-docs-parity.sh"
run "bash dev-hub/tools/check-bounded-context-registry.sh"
run "bash dev-hub/tools/check-golden-journeys.sh"

# ── Backend (si composer install fait) ───────────────────────────────────────
if $DO_API; then
  if [[ -x api/vendor/bin/pint ]]; then
    run "php api/vendor/bin/pint --test api/app api/bootstrap/providers.php api/routes"
  elif command -v pint >/dev/null 2>&1; then
    run "pint --test api/app api/bootstrap/providers.php api/routes"
  else
    echo "⚠️  pint non disponible — lancez composer install dans api/ ou installez pint."
  fi
  # php -l sur les fichiers PHP modifiés (vs main)
  for f in $(git diff --name-only origin/main...HEAD 2>/dev/null | grep '\.php$' || true); do
    run "php -l $f"
  done
  if [[ -x api/vendor/bin/phpstan ]]; then
    run "php api/vendor/bin/phpstan analyse -c api/phpstan-strict.neon api/app/Core api/app/Modules --no-progress"
  fi
fi

# ── Front (si npm install fait) ──────────────────────────────────────────────
if $DO_FRONT; then
  if [[ -d front/web/node_modules ]]; then
    run "cd front/web && npx tsc --noEmit"
    run "cd front/web && npx eslint src --max-warnings 0"
  else
    echo "⚠️  front/web/node_modules absent — lancez npm install dans front/web/."
  fi
fi

if [[ $ERRORS -gt 0 ]]; then
  echo ""
  echo "❌ $ERRORS check(s) en échec — corrigez avant de pousser."
  exit 1
fi
echo ""
echo "✅ Tous les checks locaux sont verts — vous pouvez pousser."
