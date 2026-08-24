#!/usr/bin/env bash
# Garde CI ADR-0016 Phase 5 (issue #5356) — ZÉRO référence résiduelle au
# module SmartAttendance (supprimé) et à l'ancienne surface /smart-attendance/*.
#
# Règle opérationnelle ADR-0016 : « 1 module = 1 surface API » — toute route de
# pointage vit sous /api/v1/attendance/* ; le module SmartAttendance n'existe plus.
#
# Règles scannées :
#   1. Import de classe `App\Modules\SmartAttendance\*` — interdit partout dans
#      le code Laravel vivant (api/app, bootstrap, config, lang, routes, tests).
#   2. Chemin API `/smart-attendance` — interdit dans les définitions de routes,
#      le contrat OpenAPI, le SDK, les scripts de charge, le front web, postman.
#      Les lignes de COMMENTAIRE (//, *, /*) sont ignorées : documenter la
#      purge historique est autorisé et même souhaité.
#
# Exclusions documentées (non résiduelles) :
#   - api/database/migrations/** : enregistrements immuables (déjà appliqués)
#   - CHANGELOG.md / api/CHANGELOG.md / docs/** : historique des phases 1-4
#   - front/mobile_apps/** : routes de NAVIGATION interne Flutter nommées
#     /smart-attendance (aucun appel API — le contrat mobile est 100 %
#     /attendance/*, vérifié par validate-mobile-workflow-contracts.ps1)
#
# Allow-list (références VOLONTAIRES, justifiées) :
#   - api/tests/Feature/Attendance/GeoRoutesMigrationTest.php : tests qui
#     ASSERTENT la disparition des alias (404) — le nom du chemin est cité à
#     dessein pour verrouiller la purge.
#   - dev-hub/tools/check-no-smartattendance.sh : ce script lui-même.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
violations=0

ALLOWLIST=(
  "api/tests/Feature/Attendance/GeoRoutesMigrationTest.php"
  "dev-hub/tools/check-no-smartattendance.sh"
)

is_allowed() {
  local rel="$1"
  for a in "${ALLOWLIST[@]}"; do
    [[ "$rel" == "$a" ]] && return 0
  done
  return 1
}

fail() {
  local file="$1" msg="$2"
  echo "::error file=$file::$msg"
  violations=$((violations + 1))
}

scan() {
  local base="$1" pattern="$2" msg="$3"
  [[ -d "$base" || -f "$base" ]] || return 0
  while IFS= read -r file; do
    rel="${file#$REPO_ROOT/}"
    is_allowed "$rel" && continue
    # Ne garder que les lignes de CODE (hors commentaires /* */, // et *)
    if grep -E "$pattern" "$file" | grep -vE '^[[:space:]]*(//|\*|/\*)' | grep -q .; then
      fail "$rel" "$msg"
    fi
  done < <(find "$base" -type f 2>/dev/null || true)
}

# 1) Imports de classe SmartAttendance dans le code Laravel vivant.
scan "$REPO_ROOT/api/app"      'Modules\\SmartAttendance' "Import de classe App\\Modules\\SmartAttendance\\* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/bootstrap" 'Modules\\SmartAttendance' "Import de classe App\\Modules\\SmartAttendance\\* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/config"   'Modules\\SmartAttendance' "Import de classe App\\Modules\\SmartAttendance\\* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/lang"     'Modules\\SmartAttendance' "Import de classe App\\Modules\\SmartAttendance\\* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/routes"   'Modules\\SmartAttendance' "Import de classe App\\Modules\\SmartAttendance\\* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/tests"    'Modules\\SmartAttendance' "Import de classe App\\Modules\\SmartAttendance\\* résiduel (ADR-0016 Phase 5, #5356)."

# 2) Chemin API /smart-attendance dans les routes, contrats et clients.
scan "$REPO_ROOT/api/routes"       '/smart-attendance' "Chemin API /smart-attendance/* résiduel dans les routes (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/app"          '/smart-attendance' "Chemin API /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/tests"        '/smart-attendance' "Chemin API /smart-attendance/* résiduel dans les tests (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/api/openapi.yaml" '/smart-attendance' "Contrat OpenAPI : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/dev-hub/openapi"  '/smart-attendance' "Miroir OpenAPI : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/dev-hub/sdk"      '/smart-attendance' "SDK généré : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/dev-hub/load"     '/smart-attendance' "Script de charge : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/dev-hub/tools"    '/smart-attendance' "Outil dev-hub : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/front/web/src"    '/smart-attendance' "Front web : chemin /smart-attendance/* résiduel — migrer sur /attendance/* (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/front/web/public" '/smart-attendance' "Front web public : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/postman"          '/smart-attendance' "Collection Postman : chemin /smart-attendance/* résiduel (ADR-0016 Phase 5, #5356)."
scan "$REPO_ROOT/scripts"          'Modules\\SmartAttendance|/smart-attendance' "Script racine : référence SmartAttendance résiduelle (ADR-0016 Phase 5, #5356)."

if [[ "$violations" -gt 0 ]]; then
  echo "::error::ADR-0016 Phase 5 : $violations fichier(s) avec référence SmartAttendance résiduelle."
  exit 1
fi

echo "::notice::ADR-0016 Phase 5 : zéro référence SmartAttendance dans le code vivant — OK."
