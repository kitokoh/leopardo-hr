#!/usr/bin/env bash
# check-golden-tests-required.sh — MAT-007 / garde « pas de modification
# sensible sans test golden ».
#
# Toute PR qui modifie le CODE métier de la paie (api/app/Modules/Payroll)
# ou de la comptabilité (api/app/Modules/Accounting) doit impérativement
# ajouter ou modifier un test golden correspondant :
#   - api/tests/Feature/Payroll/Golden/
#   - api/tests/Feature/Accounting/Golden/
#
# Les golden tests sont des invariants CALCULÉS À LA MAIN (montants, arrondis,
# périodes, écritures) : sans eux, une « petite » modification des règles
# (taux, arrondi, période, écriture) passe inaperçue (régression silencieuse).
#
# Usage :
#   DIFF_BASE_SHA=<sha> DIFF_HEAD_SHA=<sha> \
#     bash dev-hub/tools/check-golden-tests-required.sh [repo_root]
# Exit 1 si le code Payroll/Accounting est modifié sans test golden.

set -euo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "${ROOT}"

BASE_SHA="${DIFF_BASE_SHA:-}"
HEAD_SHA="${DIFF_HEAD_SHA:-}"

if [[ -z "${BASE_SHA}" || "${BASE_SHA}" == "0000000000000000000000000000000000000000" ]]; then
  BASE_SHA="$(git rev-parse HEAD~1 2>/dev/null || git rev-parse HEAD)"
fi
if [[ -z "${HEAD_SHA}" ]]; then
  HEAD_SHA="$(git rev-parse HEAD)"
fi

# Modifications du code métier (hors tests).
SENSITIVE_CHANGES="$(git diff --name-only "${BASE_SHA}" "${HEAD_SHA}" 2>/dev/null \
  | grep -E '^api/app/Modules/(Payroll|Accounting)/.*\.php$' \
  | grep -vE '/tests?/|/Interfaces/Api/V1/(Requests|Resources)/' || true)"

# Modifications des tests golden.
GOLDEN_CHANGES="$(git diff --name-only "${BASE_SHA}" "${HEAD_SHA}" 2>/dev/null \
  | grep -E '^api/tests/Feature/(Payroll|Accounting)/Golden/.*\.php$' || true)"

if [[ -z "${SENSITIVE_CHANGES}" ]]; then
  echo "OK: aucun changement de code métier Payroll/Accounting — garde golden non applicable."
  exit 0
fi

if [[ -z "${GOLDEN_CHANGES}" ]]; then
  echo "FAIL: ${SENSITIVE_CHANGES}"
  echo ""
  echo "La PR modifie du code métier Payroll/Accounting SANS test golden (MAT-007)."
  echo "Ajoutez/modifiez un invariant calculé à la main dans :"
  echo "  - api/tests/Feature/Payroll/Golden/  (paie : montants, arrondis, périodes)"
  echo "  - api/tests/Feature/Accounting/Golden/ (écritures, snapshots, clôtures)"
  echo "Référentiel : docs/testing/GOLDEN_TESTS.md"
  exit 1
fi

echo "OK: code métier modifié accompagné de test golden :"
echo "${GOLDEN_CHANGES}"
exit 0
