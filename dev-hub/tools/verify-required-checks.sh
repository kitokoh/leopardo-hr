#!/usr/bin/env bash
#
# verify-required-checks.sh — rejoue EN LOCAL les 4 checks requis au merge
# (cf. docs/GOUVERNANCE/MERGE_RAPIDE_CHECKS_REQUIS.md).
#
# Usage :
#   bash dev-hub/tools/verify-required-checks.sh                 # tout (scope complet)
#   bash dev-hub/tools/verify-required-checks.sh <fichier.php>…  # PHPStan strict + ci-config scopés
#
# Sortie : 0 = les vérifications LOCALES passent (le verdict CI reste à la CI),
#          1 = au moins une vérification a échoué → NE PAS merger.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"

STATUS=0
ok()   { printf '  ✅ %s\n' "$1"; }
ko()   { printf '  ❌ %s\n' "$1"; STATUS=1; }
skip() { printf '  ⏭️  %s\n' "$1"; }

echo "== 1/4 PHPStan — Strict (Core/Modules/Shared, level 8) [check requis] =="
if [[ -x api/vendor/bin/phpstan ]]; then
  if [[ $# -gt 0 ]]; then
    # Les cibles sont données relatives à la racine du dépôt (« api/app/... »)
    # alors que PHPStan est lancé depuis api/ : on retire le préfixe.
    targets=()
    for t in "$@"; do targets+=("${t#api/}"); done
    ( cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon \
        --memory-limit=3G --no-progress --error-format=raw "${targets[@]}" ) \
      && ok "phpstan-strict (cibles fournies)" || ko "phpstan-strict (code $? — 137 = OOM : relancer avec un périmètre plus étroit)"
  else
    ( cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon \
        --memory-limit=3G --no-progress ) >/tmp/phpstan-strict.out 2>&1 && ok "phpstan-strict" \
      || { ko "phpstan-strict (voir /tmp/phpstan-strict.out)"; tail -20 /tmp/phpstan-strict.out; }
  fi
else
  skip "api/vendor absent — 'composer install' requis"
fi

echo "== 2/4 Module Structure Validator [check requis] =="
for cmd in \
  "bash dev-hub/tools/check-bounded-context-registry.sh" \
  "dev-hub/tools/check-module-isolation.sh" \
  "dev-hub/tools/check-bounded-context-dependencies.sh ." \
  "dev-hub/tools/check-crm-boundary-imports.sh" \
  "python3 dev-hub/tools/check-event-catalogue-test.py" \
  "python3 dev-hub/tools/check-event-catalogue.py ." \
  "dev-hub/tools/check-unrouted-controllers.sh api" ; do
  if eval "${cmd}" >/dev/null 2>&1; then ok "${cmd}"; else ko "${cmd}"; eval "${cmd}" 2>&1 | tail -5; fi
done

echo "== 2bis Gardes d'hygiène fréquemment rouges =="
for cmd in \
  "bash dev-hub/tools/check-layer-purity.sh api" \
  "bash dev-hub/tools/check-env-example-parity.sh" \
  "bash dev-hub/tools/check-migration-basename-collisions.sh" ; do
  if eval "${cmd}" >/dev/null 2>&1; then ok "${cmd}"; else ko "${cmd}"; eval "${cmd}" 2>&1 | tail -5; fi
done

echo "== 3/4 Frontend — ESLint + TypeScript [check requis] =="
if [[ -d front/web/node_modules ]]; then
  ( cd front/web && npx tsc --noEmit ) && ok "front/web tsc" || ko "front/web tsc"
  ( cd front/web && npx eslint src --ext .ts,.tsx --max-warnings 0 ) && ok "front/web eslint" || ko "front/web eslint"
else
  skip "front/web/node_modules absent — 'npm ci' requis si la PR touche front/**"
fi

echo "== 4/4 actionlint (+ shellcheck) [check requis] =="
if command -v actionlint >/dev/null 2>&1; then
  actionlint && ok "actionlint" || ko "actionlint"
elif command -v docker >/dev/null 2>&1; then
  docker run --rm -v "${ROOT}:/repo" -w /repo rhysd/actionlint:latest -color \
    && ok "actionlint (docker)" || ko "actionlint (docker)"
else
  skip "actionlint absent (binaire ou docker requis)"
fi

echo
if [[ "${STATUS}" -eq 0 ]]; then
  echo "✅ Vérifications LOCALES vertes — le verdict CI reste requis sur la PR."
else
  echo "❌ Vérifications locales en échec — NE PAS merger en fenêtre."
fi
exit "${STATUS}"
