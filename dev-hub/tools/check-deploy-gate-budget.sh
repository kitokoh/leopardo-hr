#!/usr/bin/env bash
# #9464 — Garde d'invariant : le budget du gate de déploiement doit rester
# COMPATIBLE avec la durée RÉELLE de la suite de tests qu'il attend, et avec le
# timeout du job qui le porte.
#
# Historique de l'incident (à lire avant de « simplifier » cette garde) :
#
#   1. #7559 : budget 30 min < suite backend 45-82 min (estimation) → gate
#      `pending` → `should_deploy=false` → job de déploiement `skipped`, run
#      VERT. Le tier dev est resté figé sur `6020118` (7 nov).
#   2. #9336 : budget porté à 100 min, toujours sur l'ESTIMATION de 82 min.
#      Insuffisant : la durée MÉDIANE mesurée est de 240 min (maximum 533).
#      Le gate a consommé ses 100,6 min puis rendu `pending` à nouveau.
#   3. #9464 (ce fichier) : la durée n'est plus une estimation recopiée à
#      plusieurs endroits, mais une constante MESURÉE, lue depuis
#      `dev-hub/tools/deploy-gate-budget.env` — source unique partagée avec
#      l'action du gate et le workflow de rattrapage.
#
# Invariants vérifiés :
#   1. budget_par_defaut  >  durée médiane mesurée des tests
#   2. budget_par_defaut  <  timeout du job `prepare`
#   3. la constante de durée n'est pas régressée sous la médiane observée
#   4. la fenêtre de polling du rattrapage couvre au moins 30 min
#
# Usage : dev-hub/tools/check-deploy-gate-budget.sh
# Exit 0 = invariant respecté, 1 = régression.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CONSTANTS="$REPO_ROOT/dev-hub/tools/deploy-gate-budget.env"
ACTION="$REPO_ROOT/.github/actions/verify-deploy-workflows/action.yml"
DEPLOY="$REPO_ROOT/.github/workflows/deploy-main.yml"
CATCHUP="$REPO_ROOT/.github/workflows/deploy-main-catchup.yml"

# Plancher absolu : la médiane mesurée le 2026-11-14 sur 15 runs de main.
# Une valeur inférieure signifierait qu'on a « recalibré » sur une estimation
# plutôt que sur une mesure — exactement le défaut de #9336.
MEASURED_MEDIAN_FLOOR_MINUTES=240

fail() {
    echo "::error::$*" >&2
    exit 1
}

[ -f "$CONSTANTS" ] || fail "Source unique introuvable : $CONSTANTS"
[ -f "$ACTION" ] || fail "Gate introuvable : $ACTION"
[ -f "$DEPLOY" ] || fail "Workflow de déploiement introuvable : $DEPLOY"
[ -f "$CATCHUP" ] || fail "Workflow de rattrapage introuvable : $CATCHUP"

# shellcheck disable=SC1090
set -a
. "$CONSTANTS"
set +a

for var in DEPLOY_GATE_NOMINAL_MAX_MINUTES DEPLOY_GATE_BUDGET_MINUTES \
           DEPLOY_GATE_PREPARE_TIMEOUT_MINUTES DEPLOY_CATCHUP_POLL_MINUTES; do
    [ -n "${!var:-}" ] || fail "Constante $var absente de $CONSTANTS"
done

# 1) Le budget RÉELLEMENT utilisé par l'action doit être celui de la source
#    unique. Sans ce contrôle, la constante pourrait être corrigée ici pendant
#    que l'action garde son ancienne valeur en dur — c'est précisément le
#    mécanisme qui a produit #9336.
ACTION_BUDGET="$(sed -n "s/.*DEPLOY_GATE_BUDGET_MINUTES ?? '\([0-9]\+\)'.*/\1/p" "$ACTION" | head -n 1)"
[ -n "${ACTION_BUDGET:-}" ] || fail "Budget par défaut introuvable dans $ACTION (DEPLOY_GATE_BUDGET_MINUTES ?? '<n>')."
if [ "$ACTION_BUDGET" != "$DEPLOY_GATE_BUDGET_MINUTES" ]; then
    fail "Divergence : l'action du gate utilise ${ACTION_BUDGET} min alors que la source unique déclare ${DEPLOY_GATE_BUDGET_MINUTES} min (#9464)."
fi

# 2) Le timeout du job `prepare` doit être celui de la source unique.
PREPARE_TIMEOUT="$(awk '
    /^  prepare:/ { in_prepare = 1; next }
    in_prepare && /timeout-minutes:/ { gsub(/[^0-9]/, "", $0); print; exit }
' "$DEPLOY")"
[ -n "${PREPARE_TIMEOUT:-}" ] || fail "timeout-minutes du job 'prepare' introuvable dans $DEPLOY."
if [ "$PREPARE_TIMEOUT" != "$DEPLOY_GATE_PREPARE_TIMEOUT_MINUTES" ]; then
    fail "Divergence : le job 'prepare' est plafonné à ${PREPARE_TIMEOUT} min alors que la source unique déclare ${DEPLOY_GATE_PREPARE_TIMEOUT_MINUTES} min (#9464)."
fi

echo "Gate : budget=${DEPLOY_GATE_BUDGET_MINUTES} min — job prepare timeout=${PREPARE_TIMEOUT} min — durée médiane mesurée des tests=${DEPLOY_GATE_NOMINAL_MAX_MINUTES} min — polling rattrapage=${DEPLOY_CATCHUP_POLL_MINUTES} min"

# 3) La durée déclarée ne doit pas être régressée sous la médiane mesurée.
if [ "$DEPLOY_GATE_NOMINAL_MAX_MINUTES" -lt "$MEASURED_MEDIAN_FLOOR_MINUTES" ]; then
    fail "Durée nominale déclarée (${DEPLOY_GATE_NOMINAL_MAX_MINUTES} min) < médiane mesurée (${MEASURED_MEDIAN_FLOOR_MINUTES} min) : on recalibre sur une estimation, pas sur une mesure (#9464)."
fi

# 4) Le budget doit couvrir la durée mesurée…
if [ "$DEPLOY_GATE_BUDGET_MINUTES" -le "$DEPLOY_GATE_NOMINAL_MAX_MINUTES" ]; then
    fail "Budget du gate (${DEPLOY_GATE_BUDGET_MINUTES} min) <= durée médiane des tests (${DEPLOY_GATE_NOMINAL_MAX_MINUTES} min) : le gate expirera pendant que la suite progresse encore et le déploiement sera skippé en silence (#7559, #9336, #9464)."
fi

# …et tenir dans le timeout du job qui le porte.
if [ "$DEPLOY_GATE_BUDGET_MINUTES" -ge "$PREPARE_TIMEOUT" ]; then
    fail "Budget du gate (${DEPLOY_GATE_BUDGET_MINUTES} min) >= timeout du job 'prepare' (${PREPARE_TIMEOUT} min) : le budget serait inapplicable, le job serait tué avant (#9336)."
fi

# 5) Le rattrapage doit disposer d'une fenêtre utile, et son échec doit être
#    VISIBLE. Avant #9464 il bouclait 12 × 30 s = 6 min puis rendait `success`
#    avec un simple `::warning::` : un rattrapage mort ne se voyait nulle part.
if [ "$DEPLOY_CATCHUP_POLL_MINUTES" -lt 30 ]; then
    fail "Fenêtre de polling du rattrapage (${DEPLOY_CATCHUP_POLL_MINUTES} min) < 30 min : le rattrapage renoncera avant qu'un run de tests puisse conclure (#9464)."
fi

CATCHUP_ATTEMPTS="$(sed -n 's/.*for attempt in {1\.\.\([0-9]\+\)}; do.*/\1/p' "$CATCHUP" | head -n 1)"
[ -n "${CATCHUP_ATTEMPTS:-}" ] || fail "Boucle de polling introuvable dans $CATCHUP."
CATCHUP_WINDOW=$(( CATCHUP_ATTEMPTS * 30 / 60 ))
if [ "$CATCHUP_WINDOW" -lt "$DEPLOY_CATCHUP_POLL_MINUTES" ]; then
    fail "Fenêtre réelle du rattrapage (${CATCHUP_ATTEMPTS} × 30 s = ${CATCHUP_WINDOW} min) < ${DEPLOY_CATCHUP_POLL_MINUTES} min déclarées (#9464)."
fi

if ! grep -q '::error::Rattrapage annulé' "$CATCHUP"; then
    fail "Le rattrapage conclut encore en simple ::warning:: : un rattrapage mort resterait invisible (#9464)."
fi

echo "OK — le gate peut attendre la suite de tests dans son budget, son budget tient dans le timeout du job, et le rattrapage dispose d'une fenêtre utile avec un échec visible."
