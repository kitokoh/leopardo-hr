#!/usr/bin/env bash
#
# check-golden-journeys.test.sh — tests du garde (MAT-013, issue #5871).
#
# Scénarios :
#   1. registre réel → vert ;
#   2. fixture cohérente → vert ;
#   3. route d'étape supprimée (solution active) → rouge ;
#   4. solution active sans journey → rouge ;
#   5. solution inconnue → rouge.
#
# Usage : bash dev-hub/tools/tests/check-golden-journeys.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-golden-journeys.sh"
REG_REAL="${SCRIPT_DIR}/../golden-journeys.json"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# ── 1. Registre réel → vert ──────────────────────────────────────────────────
out="$(bash "${GUARD}" "api" "${REG_REAL}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ registre réel → vert"
  pass=$((pass + 1))
else
  echo "❌ registre réel → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── Fixture ──────────────────────────────────────────────────────────────────
FIX="${TMP}/fix"
mkdir -p "${FIX}/routes"
cat > "${FIX}/routes/api.php" << 'PHEOF'
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => 'ok');
    Route::post('/employees', fn () => 'ok');
});
PHEOF
python3 - "${TMP}/registry.json" << 'JEOF'
import json, sys
data = {
    "solutions": {"hr_core": {"label": "HR", "status": "active"}, "future": {"label": "F", "status": "planned"}},
    "journeys": [
        {"id": "GJ-01", "name": "Onboarding", "solution": "hr_core", "acceptance": "x",
         "steps": [
             {"order": 1, "action": "Créer employé", "method": "POST", "route": "/api/v1/employees", "role": "manager"},
             {"order": 2, "action": "Santé", "method": "GET", "route": "/api/v1/health", "role": "public"}
         ]}
    ],
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
# 2. fixture cohérente → vert
out="$(bash "${GUARD}" "${FIX}" "${TMP}/registry.json" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture cohérente → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture cohérente → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 3. route d'étape supprimée → rouge
python3 - "${TMP}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["journeys"][0]["steps"][1] = {"order": 2, "action": "Supprimé", "method": "GET",
                                   "route": "/api/v1/health/absent", "role": "public"}
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}" "${TMP}/registry.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"health/absent"* ]]; then
  echo "✅ route d'étape supprimée → rouge"
  pass=$((pass + 1))
else
  echo "❌ route d'étape supprimée → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 4. solution active sans journey → rouge
python3 - "${TMP}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["solutions"]["crm_client"] = {"label": "CRM", "status": "active"}
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}" "${TMP}/registry.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"crm_client"* ]]; then
  echo "✅ solution active sans journey → rouge"
  pass=$((pass + 1))
else
  echo "❌ solution active sans journey → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi
python3 - "${TMP}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
del data["solutions"]["crm_client"]
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF

# 5. solution inconnue → rouge
python3 - "${TMP}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["journeys"][0]["solution"] = "inconnue"
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}" "${TMP}/registry.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"inconnue"* ]]; then
  echo "✅ solution inconnue → rouge"
  pass=$((pass + 1))
else
  echo "❌ solution inconnue → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo ""
echo "── check-golden-journeys.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
