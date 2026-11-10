#!/usr/bin/env bash
# #9336 — Garde d'invariant : le budget du gate de déploiement doit rester
# COMPATIBLE avec la durée réelle de la suite de tests qu'il attend, et avec le
# timeout du job qui le porte.
#
# Incident du 2026-11-10 : sur `main`, TOUS les runs « Deploy - Leopardo »
# étaient verts et AUCUN ne déployait. Le gate attendait `Tests - Leopardo` avec
# un budget de 30 min alors que la suite backend dure 45-82 min : le budget
# s'épuisait pendant que les tests progressaient, le gate rendait `pending`,
# `should_deploy=false`, et le job « Deploy API + Web to Render » était `skipped`.
# Le tier dev est resté figé sur `6020118` (7 nov) pendant que `main` avançait.
#
# L'invariant vérifié ici est volontairement simple et sans dépendance :
#   1. budget_par_defaut  >  durée nominale haute des tests   (sinon on attend
#      un verdict qui ne peut pas arriver dans le budget)
#   2. budget_par_defaut  <  timeout du job `prepare`         (sinon le budget
#      est inapplicable : le job est tué avant de l'atteindre)
#
# Usage : dev-hub/tools/check-deploy-gate-budget.sh
# Exit 0 = invariant respecté, 1 = régression.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ACTION="$REPO_ROOT/.github/actions/verify-deploy-workflows/action.yml"
DEPLOY="$REPO_ROOT/.github/workflows/deploy-main.yml"

# Durée NOMINALE observée du job backend de `tests.yml` (mesures 2026-11-10 :
# 45-82 min pour un run nominal ; 192-205 min pour les runs pathologiques qui
# ont précédé #9311). On se cale sur la borne nominale haute : au-delà, le run
# est déjà anormal et c'est `deploy-main-catchup` qui doit reprendre.
NOMINAL_MAX_MINUTES=82

fail() {
    echo "::error::$*" >&2
    exit 1
}

[ -f "$ACTION" ] || fail "Gate introuvable : $ACTION"
[ -f "$DEPLOY" ] || fail "Workflow de déploiement introuvable : $DEPLOY"

# 1) Budget par défaut du gate.
BUDGET="$(sed -n "s/.*DEPLOY_GATE_BUDGET_MINUTES ?? '\([0-9]\+\)'.*/\1/p" "$ACTION" | head -n 1)"
[ -n "${BUDGET:-}" ] || fail "Budget par défaut introuvable dans $ACTION (DEPLOY_GATE_BUDGET_MINUTES ?? '<n>')."

# 2) Timeout du job qui porte le gate.
PREPARE_TIMEOUT="$(awk '
    /^  prepare:/ { in_prepare = 1; next }
    in_prepare && /timeout-minutes:/ { gsub(/[^0-9]/, "", $0); print; exit }
' "$DEPLOY")"
[ -n "${PREPARE_TIMEOUT:-}" ] || fail "timeout-minutes du job 'prepare' introuvable dans $DEPLOY."

echo "Gate : budget=${BUDGET} min — job prepare timeout=${PREPARE_TIMEOUT} min — durée nominale des tests=${NOMINAL_MAX_MINUTES} min"

if [ "$BUDGET" -le "$NOMINAL_MAX_MINUTES" ]; then
    fail "Budget du gate (${BUDGET} min) <= durée nominale des tests (${NOMINAL_MAX_MINUTES} min) : le gate expirera pendant que la suite progresse encore et le déploiement sera skippé en silence (#9336)."
fi

if [ "$BUDGET" -ge "$PREPARE_TIMEOUT" ]; then
    fail "Budget du gate (${BUDGET} min) >= timeout du job 'prepare' (${PREPARE_TIMEOUT} min) : le budget serait inapplicable, le job serait tué avant (#9336)."
fi

echo "OK — le gate peut attendre la suite de tests dans son budget, et son budget tient dans le timeout du job."
