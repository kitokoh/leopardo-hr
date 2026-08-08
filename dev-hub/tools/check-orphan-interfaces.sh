#!/usr/bin/env bash
# check-orphan-interfaces.sh — Détecte les interfaces de contrat DDD sans implémentation.
#
# Usage : dev-hub/tools/check-orphan-interfaces.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# Règle (issue #1492) : chaque interface de `Modules/*/Domain/Contracts/`
# doit avoir au moins une classe `implements` (dans api/app, y compris hors
# Modules : ex. app/Services/Payroll/CountryRules implémente
# Modules/Payroll CountryRulesInterface). Les orphelins CONNUS (liste ci-
# dessous, au 2026-08-07) sont tolérés ; tout NOUVEL orphelin fait échouer le
# check : soit l'implémenter, soit assumer via ADR et ajouter à l'allowlist.
#
# Exemple : ALLOW_ORPHAN_INTERFACES="MonInterface" \
#           dev-hub/tools/check-orphan-interfaces.sh
set -euo pipefail

API_DIR="${1:-api}"

DEFAULT_ALLOW="AttendanceRepositoryInterface InvoiceRepositoryInterface \
SubscriptionRepositoryInterface DocumentRepositoryInterface FolderRepositoryInterface \
AccessTokenServiceInterface CameraRepositoryInterface TripRepositoryInterface \
VehicleRepositoryInterface PartnerRepositoryInterface ContractRepositoryInterface \
DepartmentRepositoryInterface EmployeeRepositoryInterface NotificationRepositoryInterface \
OnboardingRepositoryInterface PlanningRepositoryInterface CompanyProvisioningInterface \
JobPostingRepositoryInterface GeofenceValidatorInterface TrainingRepositoryInterface"
ALLOW="${ALLOW_ORPHAN_INTERFACES:-${DEFAULT_ALLOW}}"

if [[ ! -d "${API_DIR}/app/Modules" ]]; then
  echo "::error::${API_DIR}/app/Modules introuvable — API_DIR invalide ?"
  exit 1
fi

orphans=()
for iface in "${API_DIR}"/app/Modules/*/Domain/Contracts/*Interface.php; do
  [[ -e "${iface}" ]] || continue
  name="$(basename "${iface}" .php)"
  # Fichiers contenant à la fois "implements" et le nom de l'interface, hors déclaration elle-même.
  if rg -l "implements" "${API_DIR}/app" --glob '*.php' 2>/dev/null \
      | xargs -r rg -l "\b${name}\b" 2>/dev/null \
      | grep -v "Domain/Contracts" | grep -q .; then
    : # implémentée
  else
    orphans+=("${name} (${iface#${API_DIR}/})")
  fi
done

new_orphans=()
for o in "${orphans[@]:-}"; do
  short="${o%% *}"
  if grep -qw "${short}" <<< "${ALLOW}"; then
    echo "::warning::Orphelin connu (allowlist) : ${o}"
  else
    new_orphans+=("${o}")
  fi
done

if [[ ${#new_orphans[@]} -gt 0 ]]; then
  echo "::error::Interfaces de contrat SANS implémentation (NOUVEAUX orphelins) :"
  printf '::error::  - %s\n' "${new_orphans[@]}"
  echo "Ajoutez-les à ALLOW_ORPHAN_INTERFACES uniquement si c'est un choix assumé (ADR),"
  echo "sinon implémentez l'interface (issue #1492)."
  exit 1
fi

echo "✓ ${#orphans[@]} orphelin(s) au total (tous dans l'allowlist) — 0 nouveau, OK"
exit 0
