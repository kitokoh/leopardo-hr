#!/usr/bin/env bash
# Issue #5578 — garde CI : UNE seule source de vérité pour la file queue.
#
# Contexte (audit 2026-08-26) : trois sources contradictoires sur la
# connexion queue en prod (probe=database, render.yaml=redis, drain GH
# =database) → selon le dispatch réel, le worker Redis payant était inactif
# OU personne ne drainait redis. Décision : file UNIQUE = `database`
# (cohérent avec ProbeAvailabilityCommand — database volontairement FIXE —
# et le drain GH queue-worker-fallback.yml ; pas de quota, drainable même
# quand Render dort ou a épuisé ses 750 h mensuelles, cf. #5204/#5205).
#
# Vérifie que TOUTES les sources déclarent `database` :
#   1. render.yaml — QUEUE_CONNECTION sur web / worker / scheduler
#   2. render.yaml — dockerCommand du worker = `queue:work database`
#   3. queue-worker-fallback.yml (drain GH) — QUEUE_CONNECTION + queue:work
#   4. queue-supervision.yml — QUEUE_CONNECTION
#   5. config/queue.php — défaut prod = database (jamais redis)
#   6. ProbeAvailabilityCommand — QUEUE_CONNECTION figé à database
#
# Usage : dev-hub/tools/check-queue-strategy.sh [repo_root]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RENDER_YAML="${ROOT}/render.yaml"
FALLBACK="${ROOT}/.github/workflows/queue-worker-fallback.yml"
SUPERVISION="${ROOT}/.github/workflows/queue-supervision.yml"
QUEUE_CONFIG="${ROOT}/api/config/queue.php"
PROBE="${ROOT}/api/app/Console/Commands/ProbeAvailabilityCommand.php"

FAIL=0

fail() {
  echo "::error::queue-strategy: $*"
  FAIL=1
}

[[ -f "${RENDER_YAML}" ]] || fail "render.yaml introuvable"

# 1. render.yaml : chaque QUEUE_CONNECTION doit valoir database
mapfile -t CONN_VALUES < <(grep -A1 "key: QUEUE_CONNECTION" "${RENDER_YAML}" \
  | grep -oE "value: [a-z]+" | sed -E "s/value: //" || true)
if [[ ${#CONN_VALUES[@]} -lt 3 ]]; then
  fail "render.yaml doit déclarer QUEUE_CONNECTION sur web, worker ET scheduler (trouvé: ${#CONN_VALUES[@]})"
else
  for V in "${CONN_VALUES[@]}"; do
    [[ "${V}" == "database" ]] || fail "render.yaml QUEUE_CONNECTION doit être 'database' (trouvé: '${V}')"
  done
fi

# 2. render.yaml : le worker dédié doit draîner database (pas redis)
if grep -q "queue:work redis" "${RENDER_YAML}"; then
  fail "render.yaml : le worker lance 'queue:work redis' — doit être 'queue:work database' (split-brain avec le drain GH)"
fi
if ! grep -q "php artisan queue:work database" "${RENDER_YAML}"; then
  fail "render.yaml : dockerCommand du worker doit être 'php artisan queue:work database'"
fi

# 3. Drain GH (queue-worker-fallback.yml) : database
if ! grep -q "QUEUE_CONNECTION: database" "${FALLBACK}"; then
  fail "queue-worker-fallback.yml doit déclarer QUEUE_CONNECTION: database"
fi
if ! grep -q "php artisan queue:work database" "${FALLBACK}"; then
  fail "queue-worker-fallback.yml doit draîner avec 'queue:work database'"
fi

# 4. Supervision (queue-supervision.yml) : database
if ! grep -q "QUEUE_CONNECTION: database" "${SUPERVISION}"; then
  fail "queue-supervision.yml doit déclarer QUEUE_CONNECTION: database"
fi

# 5. config/queue.php : le défaut prod ne doit JAMAIS retomber sur redis
if grep -qE "production' \? 'redis' : 'sync'" "${QUEUE_CONFIG}"; then
  fail "config/queue.php : le défaut prod retombe sur redis — doit être 'database' (split-brain)"
fi
if ! grep -qE "production' \? 'database' : 'sync'" "${QUEUE_CONFIG}"; then
  fail "config/queue.php : défaut prod manquant — attendu 'database'"
fi

# 6. Probe : QUEUE_CONNECTION figé à database (source de vérité runtime)
if ! grep -q "'QUEUE_CONNECTION' => 'database'" "${PROBE}"; then
  fail "ProbeAvailabilityCommand doit figer 'QUEUE_CONNECTION' => 'database'"
fi

if [[ "${FAIL}" -ne 0 ]]; then
  echo "::error::Stratégie queue incohérente (issue #5578) — une seule file : database."
  exit 1
fi

echo "✓ Stratégie queue cohérente : database partout (probe, render.yaml, drain GH, supervision, config)."
exit 0
