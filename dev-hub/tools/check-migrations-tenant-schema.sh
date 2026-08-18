#!/usr/bin/env bash
# check-migrations-tenant-schema.sh — convention #1613 / CONVENTIONS §2.6.
#
# Les migrations tenant ne doivent pas utiliser `Schema::hasTable()` /
# `Schema::table()` avec un nom NU : ces appels ne voient que
# `current_schema()` alors que les tables sont résolues via le search_path
# (piège #1595/#1933 — backfill silencieusement sauté, tables au mauvais
# schéma). Les migrations doivent résoudre le schéma via
# `resolveTableSchema()` / `schemaTableExists()` (helpers globaux) et
# qualifier les noms (`{$schema}.table`).
#
# Périmètre : uniquement les migrations créées à partir de 2026-08-18
# (date d'introduction de la garde). Les 47 migrations historiques qui
# violent la convention sont une dette assumée et documentée (audit PM
# 2026-08-17) — cette garde empêche la dette de re-croître.
#
# Usage : dev-hub/tools/check-migrations-tenant-schema.sh [repo_root]
# Exit 1 si une migration nouvelle viole la convention.

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

GUARD_DATE="2026_08_18"
VIOLATIONS=0

if [[ ! -d "api/database/migrations/tenant" ]]; then
  echo "OK: pas de dossier de migrations tenant (api/database/migrations/tenant) — rien à contrôler."
  exit 0
fi

for f in api/database/migrations/tenant/*.php; do
  base="$(basename "${f}")"
  datepart="${base%%_*}"

  # Comparaison lexicographique : les dates de migrations sont zéro-paddées.
  if [[ "${datepart}" < "${GUARD_DATE}" ]]; then
    continue
  fi

  if grep -qE "Schema::hasTable\(|Schema::hasColumn\(|Schema::table\(" "${f}"; then
    echo "VIOLATION #1613: ${f}"
    echo "  → Schéma::hasTable/hasColumn/table au nom nu interdit dans les migrations tenant."
    echo "  → Utiliser resolveTableSchema()/schemaTableExists() + noms qualifiés (pattern F-17)."
    VIOLATIONS=$((VIOLATIONS + 1))
  fi
done

if [[ "${VIOLATIONS}" -gt 0 ]]; then
  echo "FAIL: ${VIOLATIONS} migration(s) nouvelle(s) violent(ent) la convention #1613 (CONVENTIONS §2.6)."
  exit 1
fi

echo "OK: aucune migration postérieure à ${GUARD_DATE} ne viole la convention #1613."
exit 0
