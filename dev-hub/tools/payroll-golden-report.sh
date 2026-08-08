#!/usr/bin/env bash
# payroll-golden-report.sh — Compte et liste les cas golden de paie (F-03).
#
# Usage : dev-hub/tools/payroll-golden-report.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# Un « cas golden » = une méthode de test dans api/tests/Feature/Payroll/Golden/.
# Sortie au format ::notice:: pour GitHub Actions + texte lisible.
set -uo pipefail

API_DIR="${1:-api}"
GOLDEN_DIR="${API_DIR}/tests/Feature/Payroll/Golden"

if [[ ! -d "${GOLDEN_DIR}" ]]; then
  echo "::error::${GOLDEN_DIR} introuvable"
  exit 1
fi

files=$(find "${GOLDEN_DIR}" -name "*Test.php" | sort)
count=0
for f in ${files}; do
  n=$(grep -cE "public function test_" "${f}")
  count=$((count + n))
done

echo "::notice::GOLDEN_PAYROLL_CASES=${count} (cible FOCUS : ≥ 40, M+3)"
echo "Cas golden de paie : ${count}"
for f in ${files}; do
  echo "  - ${f#${API_DIR}/}"
done

if [[ "${count}" -lt 40 ]]; then
  echo "→ Objectif FOCUS : ≥ 40 cas (docs/focus/PLAN.md métrique)."
fi
exit 0
