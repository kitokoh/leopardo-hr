#!/usr/bin/env bash
# ============================================================
# check-merge-health.sh — Garde anti-régression de merge (issue #5519)
# ------------------------------------------------------------
# Constats session 2026-08-25 : les merges du swarm ont maintes fois
# corrompu main SANS que les checks requis ne le détectent :
#   1. marqueurs de conflit non résolus committés (<<<<<<< / ======= / >>>>>>>)
#      — parse error PHP sur main (routes/modules/accounting.php, 9 marqueurs) ;
#   2. imports dupliqués dans les fichiers de routes (use ... doublons) ;
#   3. fichiers de routes en erreur de syntaxe PHP (Fatal au boot) ;
#   4. providers corrompus (classe manquante — AccountingServiceProvider,
#      PHP Fatal sur TOUS les jobs PHP) ;
#   5. routes publiques perdues (documents/shared/{token} — portail client 404) ;
#   6. contrôleurs orphelins (jamais câblés dans routes/).
#
# Usage : dev-hub/tools/check-merge-health.sh [--fix]
#   --fix : tente les réparations automatiques sûres (marqueurs, doublons use).
# Retour : 0 = sain, 1 = au moins un problème (CI FAIL).
# ============================================================
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT}"
FAIL=0
FIX="${1:-}"

say_ok()   { echo "  ✅ $1"; }
say_bad()  { echo "::error::$1"; FAIL=1; }

echo "=== Garde anti-régression de merge (issue #5519) ==="

# ---- 1. Marqueurs de conflit résiduels dans les fichiers suivis ----
echo "--- 1. Marqueurs de conflit (git tracked) ---"
CONFLICT_FILES="$(git grep -l -E '^(<<<<<<<|=======|>>>>>>>)' -- ':!*.lock' ':!**/package-lock.json' 2>/dev/null | grep -vE '\.(lock|md)$' | head -20 || true)"
if [[ -n "${CONFLICT_FILES}" ]]; then
  say_bad "Marqueurs de conflit non résolus dans : $(echo ${CONFLICT_FILES} | tr '\n' ' ')"
  if [[ "${FIX}" == "--fix" ]]; then
    echo "  → git checkout --theirs sur les fichiers en conflit : NON appliqué (décision humaine requise)"
  fi
else
  say_ok "aucun marqueur de conflit"
fi

# ---- 2. Syntaxe PHP de tous les fichiers de routes ----
echo "--- 2. php -l sur tous les fichiers de routes ---"
ROUTE_FILES="$(find api/routes -name '*.php' -o -path '*/routes/*.php' -name '*.php' 2>/dev/null | grep -E 'routes/.*\.php$')"
ROUTE_FILES="${ROUTE_FILES} $(find api/app/Modules -path '*/routes/*.php' -name '*.php' 2>/dev/null)"
for f in ${ROUTE_FILES}; do
  if ! php -l "${f}" >/dev/null 2>&1; then
    say_bad "Erreur de syntaxe PHP : ${f}"
  fi
done
[[ ${FAIL} -eq 0 ]] && say_ok "tous les fichiers de routes parsent"

# ---- 3. Imports `use` dupliqués dans les fichiers de routes ----
echo "--- 3. Imports dupliqués ---"
for f in ${ROUTE_FILES}; do
  DUP="$(grep -E '^use ' "${f}" 2>/dev/null | sort | uniq -d | head -3)"
  if [[ -n "${DUP}" ]]; then
    say_bad "Imports dupliqués dans ${f} : $(echo ${DUP} | tr '\n' ' ')"
  fi
done
[[ ${FAIL} -eq 0 ]] && say_ok "aucun import dupliqué"

# ---- 4. Providers : classe déclarée + étend ServiceProvider ----
echo "--- 4. Providers de modules sains ---"
for f in $(find api/app/Modules -name '*ServiceProvider.php' 2>/dev/null); do
  if ! grep -qE 'class [A-Za-z]+ extends ServiceProvider' "${f}"; then
    say_bad "Provider corrompu (classe manquante) : ${f}"
  fi
  if ! php -l "${f}" >/dev/null 2>&1; then
    say_bad "Erreur de syntaxe PHP : ${f}"
  fi
done
[[ ${FAIL} -eq 0 ]] && say_ok "tous les providers sont sains"

# ---- 5. Routes publiques critiques présentes ----
echo "--- 5. Routes publiques critiques ---"
if ! grep -rq "documents/shared/{token}" api/routes/ 2>/dev/null; then
  say_bad "Route publique manquante : /accounting/documents/shared/{token} (portail client #5225/#5433)"
fi
if ! grep -rq "payment-webhooks/{gateway}" api/routes/ 2>/dev/null; then
  say_bad "Route publique manquante : /accounting/payment-webhooks/{gateway} (webhook paiements #5272)"
fi
[[ ${FAIL} -eq 0 ]] && say_ok "routes publiques critiques présentes"

# ---- 6. Contrôleurs orphelins (réutilise la garde existante) ----
echo "--- 6. Contrôleurs orphelins ---"
ORPHANS="$(bash dev-hub/tools/check-unrouted-controllers.sh api 2>&1 | grep -c '❌ Orphan' || true)"
if [[ "${ORPHANS}" != "0" ]]; then
  say_bad "${ORPHANS} contrôleur(s) orphelin(s) (jamais câblés dans routes/)"
else
  say_ok "aucun contrôleur orphelin"
fi

# ---- 7. Marqueurs de résolution git (dans le working tree) ----
echo "--- 7. Résolution git en cours ---"
if [[ -n "$(git diff --name-only --diff-filter=U 2>/dev/null)" ]]; then
  say_bad "Conflits git non résolus dans le working tree"
else
  say_ok "working tree propre"
fi

echo ""
if [[ ${FAIL} -eq 0 ]]; then
  echo "✅ MAIN_HEALTH_OK — aucune régression de merge détectée (issue #5519)"
  exit 0
else
  echo "❌ MAIN_HEALTH_FAIL — corriger avant merge (issue #5519)"
  exit 1
fi
