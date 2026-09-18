#!/usr/bin/env bash
#
# check-manager-role-enum.sh — Garde CI « hasManagerRole() n'accepte que des
# valeurs réellement assignables de manager_role » (issue #7600, R3 de
# l'épique #7597).
#
# Le motif mort mesuré par l'épique : des policies conditionnées à
# hasManagerRole(..., 'manager') ou (..., 'server') alors que ces valeurs
# n'ont JAMAIS été assignables via l'API (StoreEmployeeRequest /
# UpdateEmployeeRequest : in:principal,rh,dept,comptable,superviseur,
# marketing) — la condition est donc toujours fausse, et seuls principal/rh
# passaient réellement. Constaté sur ~31 policies Restaurant et ~25 policies
# TravelAgency ('manager', 'server', 'kitchen', 'rider', 'agent', 'checkin').
#
# Règle : tout littéral passé à hasManagerRole() doit appartenir à l'enum
# assignable. Un nouveau rôle métier ne s'ajoute PAS ici « pour passer » :
# soit il entre dans l'enum (validation API + CHECK en base + doc RBAC),
# soit le besoin s'exprime par une assignation de ressource
# (employee_resource_assignments, épique #7597).
#
# Usage : dev-hub/tools/check-manager-role-enum.sh [api_dir]
# Exit 1 si une violation est détectée.

set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

# Enum assignable — source : StoreEmployeeRequest/UpdateEmployeeRequest.
ALLOWED="principal|rh|dept|comptable|superviseur|marketing"

violations=0

# Extrait chaque appel hasManagerRole(...) et vérifie chaque littéral.
while IFS=: read -r file line call; do
  # Récupère les littéraux '...' de l'appel.
  literals=$(printf '%s' "$call" | grep -oE "'[a-z_]+'" | tr -d "'" || true)
  for value in $literals; do
    if ! printf '%s' "$value" | grep -qE "^(${ALLOWED})$"; then
      echo "::error file=${file},line=${line}::check-manager-role-enum: hasManagerRole('${value}') — '${value}' n'est pas une valeur assignable de manager_role (${ALLOWED//|/, }). Le motif mort ne doit pas revenir : utiliser une assignation de ressource (épique #7597) ou faire entrer le rôle dans l'enum (validation API + CHECK + doc)."
      violations=$((violations + 1))
    fi
  done
done < <(grep -rnoE "hasManagerRole\([^)]*\)" "$API_DIR/app" --include='*.php' || true)

if [[ "$violations" -gt 0 ]]; then
  echo "check-manager-role-enum: $violations violation(s)."
  exit 1
fi

echo "check-manager-role-enum: OK"
