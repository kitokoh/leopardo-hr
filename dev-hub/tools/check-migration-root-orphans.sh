#!/usr/bin/env bash
# ============================================================
# check-migration-root-orphans.sh — aucune migration à la racine de
#                                   database/migrations/ (issue #7975)
# ------------------------------------------------------------
# Constat #7975 : `2026_07_12_115602_create_onboarding_progresses_table.php`
# vivait à la RACINE de api/database/migrations/. Le runner canonique
# (`leopardo:migrate`, api/docker-entrypoint.sh) ne migre QUE
# `--path=database/migrations/public` puis `--path=database/migrations/tenant`
# → toute migration à la racine est ORPHELINE : jamais exécutée par le
# pipeline de déploiement (table absente en prod alors que le code la requête).
#
# Cette garde refuse tout fichier *.php directement sous
# api/database/migrations/ (les sous-dossiers public/ et tenant/ sont les
# seuls emplacements canoniques).
#
# Usage : dev-hub/tools/check-migration-root-orphans.sh [repo_root]
# Exit 1 si une migration orpheline est trouvée.
# ============================================================
set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi

shopt -s nullglob
orphans=("${ROOT}"/api/database/migrations/*.php)

if (( ${#orphans[@]} > 0 )); then
  echo "FAIL #7975 : migration(s) orpheline(s) à la racine de database/migrations/ :" >&2
  printf '  - %s\n' "${orphans[@]}" >&2
  echo "Le runner (leopardo:migrate) ne migre que public/ et tenant/ — déplacer le fichier dans le sous-dossier canonique." >&2
  exit 1
fi

echo "OK : aucune migration orpheline à la racine de database/migrations/."
exit 0
