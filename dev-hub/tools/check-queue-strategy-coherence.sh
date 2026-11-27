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
#   - la supervision GH Actions (`queue-supervision.yml`) est une sonde HTTP
#     SANS credentials (issue #7694) : plus jamais de secrets DB/APP_KEY dans
#     `.github/workflows/`, plus jamais de drain CI de la queue de prod.
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

# 5. Sécurité #7694 : plus JAMAIS de drain CI de la queue de prod, ni de
#    credentials de prod (secrets DB_* / APP_KEY) injectés dans un runner.
if [[ -e "$REPO_ROOT/.github/workflows/queue-worker-fallback.yml" ]]; then
  echo "::error::queue-worker-fallback.yml est revenu : un CI ne doit pas être le worker de secours de la prod (issues #7694/#7649)."
  violations=$((violations + 1))
fi
if grep -rEn "secrets\.(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD|APP_KEY)" "$REPO_ROOT/.github/workflows"; then
  echo "::error::Un workflow injecte des credentials de prod (secrets.DB_* / secrets.APP_KEY) dans un runner (issue #7694) — utiliser la sonde HTTP /api/v1/health."
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

# 7. #9976 — topologie : le drain dans le conteneur web est une dégradation
#    DÉCLARÉE, pas un état acceptable. Un web service au plan `free` s'endort
#    après ~15 min d'inactivité : la queue ET le scheduler s'arrêtent avec lui.
#    Le produit vend de la paie et de la facturation ; une paie non calculée
#    n'est pas une dégradation cosmétique.
#
#    Cette garde interdit qu'on rende la dégradation SILENCIEUSE :
#      - si le web tourne en plan free, le bloc « topologie » du blueprint doit
#        garder la trace explicite de la dégradation (marqueur QUEUE_DEGRADED) ;
#      - si un worker est décommenté (donc provisionné intentionnellement), les
#        gates WEB_QUEUE_DRAIN / WEB_SCHEDULER_LOOP du web DOIVENT être à false,
#        sinon deux consommateurs se disputent la même file.
for plan_file in "$REPO_ROOT/render.yaml" "$REPO_ROOT/render.prod.yaml"; do
  [[ -f "$plan_file" ]] || continue
  name=$(basename "$plan_file")

  # Un worker décommenté = provisionné.
  worker_active=0
  if grep -qE "^  - type: worker" "$plan_file"; then
    worker_active=1
  fi

  if [[ "$worker_active" -eq 1 ]]; then
    # Le web ne doit plus drainer ni planifier.
    #
    # ⚠️ Absence ≠ désactivation : le défaut du gate dans
    # docker-entrypoint.sh est « on ». Un blueprint qui déclare un worker SANS
    # poser `WEB_QUEUE_DRAIN=false` laisse donc le web drainer AUSSI : deux
    # consommateurs sur une file, jobs volés et ordre non garanti. La première
    # version de cette garde exigeait seulement « si la variable est déclarée,
    # elle doit être à false » et laissait passer ce cas — vérifié en
    # injectant un worker factice : garde verte, défaut présent.
    for gate in WEB_QUEUE_DRAIN WEB_SCHEDULER_LOOP; do
      if ! grep -A1 -E "key: $gate" "$plan_file" | grep -q 'value: "false"'; then
        echo "::error::$name déclare un worker actif mais ne pose pas $gate=\"false\" sur le web : le web continue de consommer (défaut du gate = on) — deux consommateurs pour une file (issue #9976)."
        violations=$((violations + 1))
      fi
    done
  fi

  # Le web en plan free + drain actif = scheduler perdu au spin-down.
  # Acceptable UNIQUEMENT si la dégradation est déclarée par le marqueur.
  if grep -qE "plan: free" "$plan_file"; then
    if ! grep -q "QUEUE_DEGRADED:" "$plan_file"; then
      echo "::error::$name a un service web en plan free sans marqueur QUEUE_DEGRADED : la perte de la queue au spin-down redeviendrait silencieuse (issue #9976)."
      violations=$((violations + 1))
    fi
  fi
done

# 8. #9976 — l'entrypoint doit porter les gates (sinon WEB_QUEUE_DRAIN=false
#    n'a aucun effet et la bascule vers un worker dédié est impossible).
for gate in WEB_QUEUE_DRAIN WEB_SCHEDULER_LOOP; do
  if ! grep -q "$gate" "$REPO_ROOT/api/docker-entrypoint.sh"; then
    echo "::error::api/docker-entrypoint.sh ne lit plus $gate : la bascule vers un worker dédié serait impossible (issue #9976)."
    violations=$((violations + 1))
  fi
done

if [[ "$violations" -gt 0 ]]; then
  echo "::error::Stratégie queue incohérente : $violations divergence(s) (issue #5578)."
  exit 1
fi

echo "Queue strategy coherence OK (database partout, topologie #9976 declarée)."
