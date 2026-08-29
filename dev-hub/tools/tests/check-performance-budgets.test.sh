#!/usr/bin/env bash
#
# check-performance-budgets.test.sh — tests du garde (MAT-014, issue #5872).
#
# Scénarios :
#   1. registre réel → vert (warnings autorisés) ;
#   2. fixture valide → vert ;
#   3. registre cassé (clé manquante) → rouge ;
#   4. N+1 dans un contrôleur fixture → warning signalé ;
#   5. endpoint dupliqué → rouge.
#
# Usage : bash dev-hub/tools/tests/check-performance-budgets.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-performance-budgets.sh"
REG_REAL="${SCRIPT_DIR}/../performance-budgets.json"
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
mkdir -p "${FIX}/app/Modules/HR/Interfaces/Api/V1"
cat > "${FIX}/app/Modules/HR/Interfaces/Api/V1/EmployeeController.php" << 'PHEOF'
<?php

namespace App\Modules\HR\Interfaces\Api\V1;

class EmployeeController
{
    public function index()
    {
        $employees = \App\Models\Employee::all(); // scan lent potentiel
        foreach ($employees as $e) {
            $dept = \App\Models\Department::where('id', $e->department_id)->first(); // N+1 réel
        }
        return $employees;
    }
}
PHEOF
mkdir -p "${FIX}/database/migrations/tenant"
python3 - "${TMP}/budgets.json" << 'JEOF'
import json, sys
data = {
    "rules": {"pagination": "x", "n_plus_one": "y", "p95_target_ms": 300, "p99_target_ms": 800},
    "critical_endpoints": [
        {"method": "GET", "route": "/api/v1/employees", "max_queries": 10, "p95_ms": 300, "pagination": True}
    ],
    "required_indexes": [
        {"table": "employees", "index": "employees_company_id_index"}
    ],
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
# 2. fixture valide → vert
out="$(bash "${GUARD}" "${FIX}" "${TMP}/budgets.json" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture valide → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture valide → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 3. registre cassé → rouge
python3 - "${TMP}/budgets.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
del data["critical_endpoints"][0]["route"]
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}" "${TMP}/budgets.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]]; then
  echo "✅ registre cassé → rouge"
  pass=$((pass + 1))
else
  echo "❌ registre cassé → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi
python3 - "${TMP}/budgets.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["critical_endpoints"][0]["route"] = "/api/v1/employees"
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF

# 4. N+1 signalé (warning)
out="$(bash "${GUARD}" "${FIX}" "${TMP}/budgets.json" 2>&1 || true)"
if [[ "${out}" == *"N+1"* ]] && [[ "${out}" == *"EmployeeController"* ]]; then
  echo "✅ N+1 dans le contrôleur → warning signalé"
  pass=$((pass + 1))
else
  echo "❌ N+1 → warning attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 5. endpoint dupliqué → rouge
python3 - "${TMP}/budgets.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["critical_endpoints"].append({"method": "GET", "route": "/api/v1/employees", "max_queries": 5, "p95_ms": 200, "pagination": True})
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}" "${TMP}/budgets.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"dupliqué"* ]]; then
  echo "✅ endpoint dupliqué → rouge"
  pass=$((pass + 1))
else
  echo "❌ endpoint dupliqué → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo ""
echo "── check-performance-budgets.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
