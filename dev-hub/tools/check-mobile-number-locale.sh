#!/usr/bin/env bash
# #4197 — garde i18n mobile : interdit les formats de nombres/dates FR codés
# en dur dans les apps (les utilisateurs AR/TR/EN doivent voir leur locale).
#
# Patterns interdits :
#   - NumberFormat.decimalPattern('fr') / ("fr")          → locale forcée FR
#   - NumberFormat.decimalPattern(deviceIntlDateLocale)   → getter de DATE
#     utilisé pour des nombres (utiliser deviceIntlNumberLocale)
#
# Usage : bash dev-hub/tools/check-mobile-number-locale.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT/front/mobile_apps"

violations=0

while IFS= read -r line; do
  echo "❌ $line"
  violations=$((violations + 1))
done < <(grep -rn "decimalPattern('fr')\|decimalPattern(\"fr\")\|decimalPattern(deviceIntlDateLocale)" \
  leopardo_employee/lib leopardo_manager/lib leopardo_hr/lib leopardo_core/lib 2>/dev/null || true)

if [ "$violations" -gt 0 ]; then
  echo "⛔ $violations violation(s) i18n nombre (locale FR forcée / mauvais getter)."
  exit 1
fi

echo "✅ Aucun format de nombre FR codé en dur (deviceIntlNumberLocale partout)."
