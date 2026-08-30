#!/usr/bin/env bash
# payroll-golden-report.sh — Compte et liste les cas golden de paie (F-03)
# et de comptabilité (MAT-007 #5865).
#
# Usage : dev-hub/tools/payroll-golden-report.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# Un « cas golden » = une méthode de test dans api/tests/Feature/Payroll/Golden/
# ou api/tests/Feature/Accounting/Golden*. Sortie au format ::notice:: pour
# GitHub Actions + texte lisible.
set -uo pipefail

API_DIR="${1:-api}"
GOLDEN_DIR="${API_DIR}/tests/Feature/Payroll/Golden"
ACCOUNTING_GOLDEN_DIR="${API_DIR}/tests/Feature/Accounting"

count_cases() {
  local dir="$1" pattern="$2" count=0 f n p
  if [[ ! -d "${dir}" ]]; then
    echo "::error::${dir} introuvable"
    return 1
  fi
  files=$(find "${dir}" -maxdepth 1 -name "${pattern}" | sort)
  for f in ${files}; do
    # cas = méthodes test_ + entrées de data providers (chaque ligne 'nom' => [..])
    n=$(grep -cE "public function test_" "${f}" || true)
    p=$(grep -cE "^[[:space:]]*'[^']+'" "${f}" || true)
    count=$((count + n + p))
  done
  echo "${count}"
}

count_payroll=$(count_cases "${GOLDEN_DIR}" "*Test.php") || exit 1
count_accounting=$(count_cases "${ACCOUNTING_GOLDEN_DIR}" "Golden*Test.php") || exit 1

echo "::notice::GOLDEN_PAYROLL_CASES=${count_payroll} (cible FOCUS : ≥ 40, M+3)"
echo "::notice::GOLDEN_ACCOUNTING_CASES=${count_accounting} (MAT-007 #5865)"
echo "Cas golden de paie : ${count_payroll}"
for f in $(find "${GOLDEN_DIR}" -maxdepth 1 -name "*Test.php" | sort); do
  echo "  - ${f#${API_DIR}/}"
done
echo "Cas golden de comptabilité : ${count_accounting}"
for f in $(find "${ACCOUNTING_GOLDEN_DIR}" -maxdepth 1 -name "Golden*Test.php" | sort); do
  echo "  - ${f#${API_DIR}/}"
done

if [[ "${count_payroll}" -lt 40 ]]; then
  echo "→ Objectif FOCUS : ≥ 40 cas (docs/focus/PLAN.md métrique)."
fi
exit 0
