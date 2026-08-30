#!/usr/bin/env bash
#
# check-runbooks.test.sh — tests du garde (MAT-015, issue #5873).
#
# Scénarios :
#   1. registre réel → vert ;
#   2. fixture cohérente → vert ;
#   3. runbook référencé introuvable → rouge ;
#   4. BC actif sans couverture → rouge ;
#   5. drill log sans entrée datée → rouge.
#
# Usage : bash dev-hub/tools/tests/check-runbooks.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-runbooks.sh"
REG_REAL="${SCRIPT_DIR}/../runbook-registry.json"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# ── 1. Registre réel → vert ──────────────────────────────────────────────────
out="$(bash "${GUARD}" "${REG_REAL}" "${REPO_ROOT}" 2>&1 || true)"
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
mkdir -p "${FIX}/docs/GESTION_PROJET"
cat > "${FIX}/docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md" << 'DEOF'
# Backup
Procedure minimale.
DEOF
cat > "${FIX}/docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md" << 'DEOF'
# Drill Log
| Date | Type | Result |
|---|---|---|
| 2026-08-22 | restore | pass |
DEOF
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
data = {
    "drill_log": "docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md",
    "platform_runbooks": {"backup_restore": "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md"},
    "contexts": [{"code": "BC-01", "name": "ALPHA", "notes": "couvert par plateforme"}],
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
# 2. fixture cohérente → vert
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture cohérente → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture cohérente → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 3. runbook référencé introuvable → rouge
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["contexts"][0] = {"code": "BC-01", "name": "ALPHA",
                       "runbooks": ["docs/GESTION_PROJET/RUNBOOK_ABSENT.md"], "notes": "x"}
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"RUNBOOK_ABSENT"* ]]; then
  echo "✅ runbook référencé introuvable → rouge"
  pass=$((pass + 1))
else
  echo "❌ runbook référencé introuvable → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 4. BC actif sans couverture → rouge
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["contexts"][0] = {"code": "BC-01", "name": "ALPHA"}
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"BC-01"* ]]; then
  echo "✅ BC actif sans couverture → rouge"
  pass=$((pass + 1))
else
  echo "❌ BC actif sans couverture → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 5. drill log sans entrée datée → rouge
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["contexts"][0] = {"code": "BC-01", "name": "ALPHA", "notes": "couvert"}
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
printf '# Drill Log\nrien de daté ici\n' > "${FIX}/docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md"
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"datée"* ]]; then
  echo "✅ drill log sans entrée datée → rouge"
  pass=$((pass + 1))
else
  echo "❌ drill log sans entrée datée → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo ""
echo "── check-runbooks.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
