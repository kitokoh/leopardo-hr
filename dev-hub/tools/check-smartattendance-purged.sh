#!/usr/bin/env bash
#
# Garde CI ADR-0016 Phase 5 (issue #5356) — aucune résurgence du module
# SmartAttendance après sa suppression (fusion complète dans Attendance).
#
# Échoue si :
#   1. un fichier PHP importe/étend `App\Modules\SmartAttendance\*` ;
#   2. un chemin `/smart-attendance/*` est (ré)introduit dans les specs OpenAPI ;
#   3. le dossier `api/app/Modules/SmartAttendance` (ré)apparaît ;
#   4. `SmartAttendanceServiceProvider` est encore référencé.
#
# Usage : dev-hub/tools/check-smartattendance-purged.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
violations=0

# 1. Imports / usages PHP du namespace SmartAttendance (app, tests, bootstrap, routes)
if grep -rIl --include="*.php" -E "App\\\\Modules\\\\SmartAttendance" \
    "$REPO_ROOT/api/app" "$REPO_ROOT/api/tests" "$REPO_ROOT/api/bootstrap" "$REPO_ROOT/api/routes" 2>/dev/null; then
  echo "::error::Imports App\\\\Modules\\\\SmartAttendance résiduels (ADR-0016 Phase 5 #5356)."
  violations=$((violations + 1))
fi

# 2. Chemins /smart-attendance/* dans les specs OpenAPI (api + miroir)
if grep -qE "^  /smart-attendance/" "$REPO_ROOT/api/openapi.yaml" "$REPO_ROOT/dev-hub/openapi/v1.yaml" 2>/dev/null; then
  echo "::error::Chemins /smart-attendance/* résiduels dans les specs OpenAPI (ADR-0016 Phase 5 #5356)."
  violations=$((violations + 1))
fi

# 3. Dossier du module supprimé
if [[ -d "$REPO_ROOT/api/app/Modules/SmartAttendance" ]]; then
  echo "::error::Module api/app/Modules/SmartAttendance résiduel (ADR-0016 Phase 5 #5356)."
  violations=$((violations + 1))
fi

# 4. Provider supprimé encore référencé
if grep -rq "SmartAttendanceServiceProvider" "$REPO_ROOT/api" --include="*.php" 2>/dev/null; then
  echo "::error::SmartAttendanceServiceProvider encore référencé (ADR-0016 Phase 5 #5356)."
  violations=$((violations + 1))
fi

if [[ "$violations" -gt 0 ]]; then
  echo "::error::SmartAttendance : $violations violation(s) — fusion ADR-0016 non finalisée (Phase 5 #5356)."
  exit 1
fi

echo "::notice::SmartAttendance : module supprimé, 0 référence résiduelle (ADR-0016 Phase 5 #5356)."
