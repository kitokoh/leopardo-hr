#!/usr/bin/env bash
# check-absence-facade.sh — Garde CI « façade Absence » (ARCH-003, issue #7959)
#
# Décision architecturale : api/app/Modules/Absence est une FAÇADE de
# présentation (BC-06 LEAVE) dont le domaine vit dans Modules/Planning (BC-05).
# Voir docs/adr/ARCH-003-module-absence-facade.md.
#
# Ce garde empêche la façade de se remplir d'un domaine parallèle, ce qui
# recréerait la duplication dénoncée par l'audit du 2026-09-20.
#
# Interdit sous api/app/Modules/Absence/ :
#   - Application/        (cas d'usage → ils vivent dans Planning)
#   - Domain/Models/      (modèles persistés → Planning)
#   - Domain/Events/      (événements → Planning)
#   - Infrastructure/     (accès aux données → Planning)
#
# Autorisé : Interfaces/ (HTTP), Domain/Support/ (catalogue de décision,
# purement présentationnel), Providers/.
#
# Usage : dev-hub/tools/check-absence-facade.sh [repo_root]
# Exit codes : 0 = OK, 1 = violation.

set -uo pipefail

ROOT="${1:-.}"
MODULE="${ROOT}/api/app/Modules/Absence"
ADR="docs/adr/ARCH-003-module-absence-facade.md"

if [[ ! -d "${MODULE}" ]]; then
  # Module supprimé : plus de façade à protéger (et l'ADR devra être révisé).
  echo "check-absence-facade : ${MODULE} absent — rien à vérifier."
  exit 0
fi

ERRORS=0
FORBIDDEN=("Application" "Domain/Models" "Domain/Events" "Infrastructure")

for sub in "${FORBIDDEN[@]}"; do
  if [[ -d "${MODULE}/${sub}" ]]; then
    echo "::error::Modules/Absence/${sub}/ existe : le module Absence est une façade de presentation (ARCH-003). Le domaine vit dans Modules/Planning. Voir ${ADR} (issue #7959)." >&2
    ERRORS=$((ERRORS + 1))
  fi
done

if [[ "${ERRORS}" -eq 0 ]]; then
  echo "✓ Absence reste une facade conforme a ARCH-003 : aucun repertoire de domaine sous Modules/Absence (issue #7959)."
  exit 0
fi

exit 1
