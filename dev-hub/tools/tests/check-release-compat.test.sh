#!/usr/bin/env bash
#
# check-release-compat.test.sh — tests du garde (MAT-016, issue #5874).
#
# Scénarios :
#   1. matrice réelle → vert ;
#   2. fixture cohérente → vert ;
#   3. version API ≠ config → rouge ;
#   4. version app mobile ≠ pubspec → rouge ;
#   5. plancher min_api manquant → rouge ;
#   6. api_min_supported > api → rouge.
#
# Usage : bash dev-hub/tools/tests/check-release-compat.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-release-compat.sh"
MATRIX_REAL="${SCRIPT_DIR}/../release-compat-matrix.json"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# ── 1. Matrice réelle → vert ─────────────────────────────────────────────────
out="$(bash "${GUARD}" api "${MATRIX_REAL}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ matrice réelle → vert"
  pass=$((pass + 1))
else
  echo "❌ matrice réelle → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── Fixture ──────────────────────────────────────────────────────────────────
FIX="${TMP}/fix"
mkdir -p "${FIX}/config" "${FIX}/../front/mobile_apps/leopardo_employee" "${FIX}/../front/zkteco-kiosk"
cat > "${FIX}/config/app.php" << 'PHEOF'
<?php

return [
    'version' => env('APP_VERSION', '4.24.0'),
];
PHEOF
printf 'version: 1.0.0+1\n' > "${FIX}/../front/mobile_apps/leopardo_employee/pubspec.yaml"
printf '{"version": "1.0.0"}\n' > "${FIX}/../front/zkteco-kiosk/package.json"
cat > "${TMP}/fix-matrix.json" << 'JEOF'
{
  "api": "4.24.0",
  "api_min_supported": "4.20.0",
  "components": {
    "mobile_apps": {
      "leopardo_employee": { "current": "1.0.0+1", "min_api": "4.20.0" }
    },
    "kiosk": { "current": "1.0.0", "min_api": "4.20.0" }
  }
}
JEOF
# 2. fixture cohérente → vert
out="$(bash "${GUARD}" "${FIX}" "${TMP}/fix-matrix.json" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture cohérente → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture cohérente → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# 3. version API ≠ config → rouge
sed -i 's/"api": "4.24.0"/"api": "4.25.0"/' "${TMP}/fix-matrix.json"
out="$(bash "${GUARD}" "${FIX}" "${TMP}/fix-matrix.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"APP_VERSION"* ]]; then
  echo "✅ version API ≠ config → rouge"
  pass=$((pass + 1))
else
  echo "❌ version API ≠ config → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi
sed -i 's/"api": "4.25.0"/"api": "4.24.0"/' "${TMP}/fix-matrix.json"

# 4. version app ≠ pubspec → rouge
sed -i 's/"current": "1.0.0+1"/"current": "2.0.0+1"/' "${TMP}/fix-matrix.json"
out="$(bash "${GUARD}" "${FIX}" "${TMP}/fix-matrix.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"leopardo_employee"* ]]; then
  echo "✅ version app ≠ pubspec → rouge"
  pass=$((pass + 1))
else
  echo "❌ version app ≠ pubspec → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi
sed -i 's/"current": "2.0.0+1"/"current": "1.0.0+1"/' "${TMP}/fix-matrix.json"

# 5. plancher min_api manquant → rouge
sed -i 's/"current": "1.0.0", "min_api": "4.20.0"/"current": "1.0.0"/' "${TMP}/fix-matrix.json"
out="$(bash "${GUARD}" "${FIX}" "${TMP}/fix-matrix.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"min_api"* ]]; then
  echo "✅ plancher min_api manquant → rouge"
  pass=$((pass + 1))
else
  echo "❌ plancher min_api manquant → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi
sed -i 's/"current": "1.0.0"/"current": "1.0.0", "min_api": "4.20.0"/' "${TMP}/fix-matrix.json"

# 6. api_min_supported > api → rouge
sed -i 's/"api_min_supported": "4.20.0"/"api_min_supported": "5.0.0"/' "${TMP}/fix-matrix.json"
out="$(bash "${GUARD}" "${FIX}" "${TMP}/fix-matrix.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"plancher"* ]]; then
  echo "✅ plancher > api → rouge"
  pass=$((pass + 1))
else
  echo "❌ plancher > api → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo ""
echo "── check-release-compat.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
