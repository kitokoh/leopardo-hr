#!/usr/bin/env bash
# Garde #4411 : les chemins --path de migrate utilisés par les entrypoints
# Edge (SQLite) doivent pointer vers une migration EXISTANTE et SQLite-safe.
# Avant : --path=database/migrations/edge → répertoire inexistant → glob vide
# → schéma jamais provisionné, sync offline morte en silence.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MIGRATION="database/migrations/tenant/2026_06_29_000001_create_edge_sync_tables.php"

for entrypoint in "$REPO_ROOT/edge/docker-entrypoint.edge.sh" "$REPO_ROOT/edge/docker-entrypoint.publish.sh"; do
  if ! grep -q -- "--path=$MIGRATION" "$entrypoint"; then
    echo "FAIL: $entrypoint ne référence pas la migration Edge SQLite ($MIGRATION)" >&2
    exit 1
  fi
  if grep -q -- "--path=database/migrations/edge" "$entrypoint"; then
    echo "FAIL: $entrypoint utilise encore le chemin fantôme database/migrations/edge (issue #6604)" >&2
    exit 1
  fi
  echo "OK: $entrypoint utilise la migration tenant canonique ($MIGRATION)"
done

# #6604 : supervisord + compose edge-sync doivent aussi pointer la source unique
for f in "$REPO_ROOT/edge/supervisord.edge.conf" "$REPO_ROOT/edge/docker-compose.yml"; do
  if grep -q -- "--path=database/migrations/edge" "$f"; then
    echo "FAIL: $f utilise encore le chemin fantôme database/migrations/edge (issue #6604)" >&2
    exit 1
  fi
  if ! grep -q -- "--path=$MIGRATION" "$f"; then
    echo "FAIL: $f ne référence pas la migration tenant canonique ($MIGRATION, issue #6604)" >&2
    exit 1
  fi
  echo "OK: $f utilise la migration tenant canonique"
done

if [ ! -f "$REPO_ROOT/api/$MIGRATION" ]; then
  echo "FAIL: la migration $MIGRATION n'existe pas" >&2
  exit 1
fi

echo "PASS: chemins de migration Edge cohérents (#4411)"
