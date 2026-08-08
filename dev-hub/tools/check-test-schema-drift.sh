#!/usr/bin/env bash
# check-test-schema-drift.sh — Mesure la dérive entre les migrations réelles et
# le schéma de test manuel CreatesMvpSchema (issue #1489).
#
# Usage : dev-hub/tools/check-test-schema-drift.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# Mode RAPPORT (non bloquant) : liste les tables créées par les migrations
# (database/migrations/public + tenant) absentes du schéma manuel
# api/tests/Support/CreatesMvpSchema.php. Exit 0 toujours ; les tables
# manquantes sont affichées en warning ::warning::.
# L'objectif à terme : supprimer CreatesMvpSchema au profit des vraies
# migrations (RefreshTenantDatabase) — voir issue #1489.
set -uo pipefail

API_DIR="${1:-api}"

if [[ ! -d "${API_DIR}/database/migrations" ]]; then
  echo "::error::${API_DIR}/database/migrations introuvable"
  exit 1
fi

migrated=$(grep -rhoE "Schema::create\(['\"][a-z_]+" "${API_DIR}/database/migrations" \
           | sed -E "s/Schema::create\(['\"]//" | sort -u)
fixture=$(grep -rhoE "Schema::create\(['\"][a-z_]+" "${API_DIR}/tests/Support/CreatesMvpSchema.php" 2>/dev/null \
          | sed -E "s/Schema::create\(['\"]//" | sort -u)

missing=$(comm -23 <(echo "${migrated}") <(echo "${fixture}"))

if [[ -n "${missing}" ]]; then
  n=$(wc -l <<< "${missing}")
  echo "::warning::Drift schéma de test (issue #1489) : ${n} table(s) créée(s) par les migrations mais absentes de CreatesMvpSchema :"
  printf '::warning::  - %s\n' "${missing}"
  echo "→ ces tables ne sont pas couvertes par les ${N} tests utilisant CreatesMvpSchema."
  echo "→ cible : migrer les tests vers RefreshTenantDatabase (migrations réelles)."
else
  echo "✓ Schéma de test aligné sur les migrations (${#migrated} tables)."
fi

exit 0
