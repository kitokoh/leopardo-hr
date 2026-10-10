#!/usr/bin/env bash
# Garde GEO-07 (issue #8356, BC-33 GEO) — calculs de distance UNIQUEMENT dans
# le module `geo` (core géospatial transverse).
#
# Même risque que ADR-0016 (geofence single-usage, #5353) : avant le core
# `geo`, chaque module calculait ses distances (Haversine PHP dans
# Attendance, HAVERSINE_SQL brut dans l'annuaire public Restaurant). Le core
# BC-33 est désormais l'unique source de calculs de positionnement — toute
# NOUVELLE implémentation locale (Haversine, ST_Distance, ST_DWithin) hors
# `api/app/Modules/Geo/` est une duplication à faire migrer vers
# App\Shared\Contracts\Geo\GeoServiceContract.
#
# Mode WARNING par défaut (non bloquant — le temps que les consommateurs
# legacy migrent, spec §9 tâche 9) : les violations produisent des
# annotations ::warning sans faire échouer la CI. Durcissement prévu (issue
# de suivi) : GEO_SINGLE_USAGE_STRICT=1 → échec CI sur toute violation.
#
# Exemptions documentées (consommateurs legacy, migration planifiée) :
#   - Attendance geofence (ADR-0016) : GeoSessionManager, GeofenceZoneService
#     — migration vers `geo` avec la refonte geofence (hors scope v1, spec §11) ;
#   - RestaurantPublicDirectoryController : HAVERSINE_SQL conservé UNIQUEMENT
#     comme repli quand le flag `geo` est off (GEO-06 #8355) — supprimé à la
#     généralisation du flag ;
#   - Shared\Contracts\Geo\GeoServiceContract : contrat PARTAGÉ du core
#     (mentions de documentation, pas une implémentation).
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCOPE="$REPO_ROOT/api/app"
STRICT="${GEO_SINGLE_USAGE_STRICT:-0}"

ALLOWED=(
  "api/app/Modules/Attendance/Infrastructure/Services/GeoSessionManager.php"
  "api/app/Modules/Attendance/Infrastructure/Services/GeofenceZoneService.php"
  "api/app/Modules/RestaurantManager/Interfaces/Api/V1/Controllers/RestaurantPublicDirectoryController.php"
  "api/app/Shared/Contracts/Geo/GeoServiceContract.php"
)

violations=0

while IFS= read -r file; do
  rel="${file#$REPO_ROOT/}"

  # Le module geo lui-même est l'implémentation de référence — hors scope.
  case "$rel" in
  api/app/Modules/Geo/*) continue ;;
  esac

  allowed=0
  for a in "${ALLOWED[@]}"; do
    if [[ "$rel" == "$a" ]]; then
      allowed=1
      break
    fi
  done
  [[ "$allowed" -eq 1 ]] && continue

  # Ne garder que les lignes de CODE (hors commentaires /* */ et //).
  if grep -iE "haversine|st_distance|st_dwithin" "$file" \
    | grep -vE '^\s*(//|\*|/\*)' | grep -q .; then
    if [[ "$STRICT" == "1" ]]; then
      echo "::error file=$rel::Calcul de distance hors module geo (BC-33). Passez par App\\Shared\\Contracts\\Geo\\GeoServiceContract (GEO-07 #8356)."
    else
      echo "::warning file=$rel::Calcul de distance hors module geo détecté (BC-33) — à migrer vers GeoServiceContract (GEO-07 #8356, mode warning)."
    fi
    violations=$((violations + 1))
  fi
done < <(grep -rliE "haversine|st_distance|st_dwithin" "$SCOPE" --include="*.php" || true)

if [[ "$violations" -gt 0 ]]; then
  if [[ "$STRICT" == "1" ]]; then
    echo "::error::Geo : $violations fichier(s) implémentent un calcul de distance hors module geo (BC-33, GEO-07 #8356)."
    exit 1
  fi
  echo "::warning::Geo : $violations fichier(s) avec calcul de distance hors module geo (mode warning — durcissement GEO_SINGLE_USAGE_STRICT ultérieur, GEO-07 #8356)."
else
  echo "::notice::Geo : aucun calcul de distance hors module geo — OK (GEO-07 #8356)."
fi
