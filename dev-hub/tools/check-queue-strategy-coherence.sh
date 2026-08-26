#!/usr/bin/env bash
#
# Garde CI de cohérence de la stratégie queue (issue #5578, réf. #4340/#4349).
#
# La file en production est `database` — UNE seule source de vérité :
#   - `ProbeAvailabilityCommand` fige `QUEUE_CONNECTION=database` au boot
#     (api/docker-entrypoint.sh, avant config:cache) ;
#   - `render.yaml` expose `QUEUE_CONNECTION=database` sur web / worker /
#     scheduler et le worker dédié consomme la connexion par défaut ;
#   - `config/queue.php` ne retombe PAS sur redis en production ;
#   - le drain de secours GH Actions (`queue-worker-fallback.yml`) draine
#     `database` (même connexion que le probe).
#
# Échoue (::error:: + exit 1) si l'un des artefacts diverge — le but est de
# rendre indétectable toute dérive silencieuse entre probe, worker, fallback
# et doc de déploiement.
#
# Usage : dev-hub/tools/check-queue-strategy-coherence.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
violations=0

# 1. Le probe fige la queue sur database (source de vérité runtime).
if ! grep -q "'QUEUE_CONNECTION' => 'database'" "$REPO_ROOT/api/app/Console/Commands/ProbeAvailabilityCommand.php"; then
  echo "::error::ProbeAvailabilityCommand ne fige plus QUEUE_CONNECTION=database (issue #5578)."
  violations=$((violations + 1))
fi

# 2. config/queue.php : en production, le défaut ne doit JAMAIS être redis.
if grep -q "'production' ? 'redis'" "$REPO_ROOT/api/config/queue.php"; then
  echo "::error::config/queue.php retombe encore sur redis en production (issue #5578)."
  violations=$((violations + 1))
fi

# 3. render.yaml : aucun QUEUE_CONNECTION=redis sur web / worker / scheduler.
if grep -n "QUEUE_CONNECTION" -A1 "$REPO_ROOT/render.yaml" | grep -q "value: redis"; then
  echo "::error::render.yaml expose encore QUEUE_CONNECTION=redis (issue #5578) — attendu database."
  violations=$((violations + 1))
fi

# 4. render.yaml : le worker dédié ne doit plus cibler la connexion redis.
if grep -q "queue:work redis" "$REPO_ROOT/render.yaml"; then
  echo "::error::render.yaml lance encore 'php artisan queue:work redis' (issue #5578) — attendu connexion par défaut (database)."
  violations=$((violations + 1))
fi

# 5. Le drain GH Actions draine la même connexion (database).
if ! grep -q "queue:work database" "$REPO_ROOT/.github/workflows/queue-worker-fallback.yml"; then
  echo "::error::queue-worker-fallback.yml ne draine plus la connexion database (issue #5578)."
  violations=$((violations + 1))
fi

# 6. La doc de déploiement canonique des workers est alignée.
if grep -q "QUEUE_CONNECTION=redis" "$REPO_ROOT/docs/deployment/DEPLOYMENT_GUIDE.md"; then
  echo "::error::DEPLOYMENT_GUIDE.md documente encore QUEUE_CONNECTION=redis (issue #5578)."
  violations=$((violations + 1))
fi
if grep -q "queue:work redis" "$REPO_ROOT/docs/deployment/DEPLOYMENT_GUIDE.md"; then
  echo "::error::DEPLOYMENT_GUIDE.md documente encore 'queue:work redis' (issue #5578)."
  violations=$((violations + 1))
fi

if [[ "$violations" -gt 0 ]]; then
  echo "::error::Stratégie queue incohérente : $violations divergence(s) (issue #5578)."
  exit 1
fi

echo "Queue strategy coherence OK (database partout)."
