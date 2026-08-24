#!/usr/bin/env bash
# Garde ADR-0016 Phase 2 (issue #5353) — UN SEUL chemin d'usage de la géofence.
#
# AttendanceGeofenceService est l'unique implémentation du calcul de distance ;
# GeofenceZoneService est l'unique CONSOMMATEUR direct autorisé. Tout autre
# fichier qui référence AttendanceGeofenceService directement introduit une
# seconde logique de distance/zone → CI rouge (règle opérationnelle ADR-0016).
#
# Fichiers autorisés (allow-list) :
#   - l'implémentation elle-même (AttendanceGeofenceService.php)
#   - le chemin d'usage unique (GeofenceZoneService.php)
#   - le binding du contrat (AttendanceServiceProvider, ADR-0016 Phase 5 #5356)
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODULES="$REPO_ROOT/api/app/Modules"

ALLOWED=(
  "api/app/Modules/Attendance/Infrastructure/Services/AttendanceGeofenceService.php"
  "api/app/Modules/Attendance/Infrastructure/Services/GeofenceZoneService.php"
  "api/app/Modules/Attendance/Providers/AttendanceServiceProvider.php"
  "api/app/Modules/Attendance/Domain/Contracts/GeofenceValidatorInterface.php"
)

violations=0

while IFS= read -r file; do
  rel="${file#$REPO_ROOT/}"
  allowed=0
  for a in "${ALLOWED[@]}"; do
    if [[ "$rel" == "$a" ]]; then
      allowed=1
      break
    fi
  done
  # Ne garder que les lignes de CODE (hors commentaires /* */ et //)
  if [[ "$allowed" -eq 0 ]] \
    && grep -E "AttendanceGeofenceService" "$file" \
      | grep -vE '^\s*(//|\*|/\*)' | grep -q .; then
    echo "::error file=$rel::Usage direct d'AttendanceGeofenceService hors chemin unifié (GeofenceZoneService) — ADR-0016 Phase 2 (#5353). Passez par GeofenceZoneService."
    violations=$((violations + 1))
  fi
done < <(grep -rl "AttendanceGeofenceService" "$MODULES" --include="*.php" || true)

if [[ "$violations" -gt 0 ]]; then
  echo "::error::Geofence : $violations fichier(s) contourne(nt) le chemin d'usage unique."
  exit 1
fi

echo "::notice::Geofence : un seul chemin d'usage (GeofenceZoneService) — OK (ADR-0016 Phase 2)."
