#!/usr/bin/env bash
#
# check-security-threat-models.test.sh — tests du garde (MAT-017, issue #5875).
#
# Scénarios :
#   1. registre réel → vert ;
#   2. fixture valide → vert ;
#   3. contrôle inconnu du catalogue → rouge ;
#   4. contrôle critique manquant → rouge ;
#   5. document introuvable → rouge.
#
# Usage : bash dev-hub/tools/tests/check-security-threat-models.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-security-threat-models.sh"
REG_REAL="${SCRIPT_DIR}/../security-threat-models.json"
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
mkdir -p "${FIX}/docs/security"
touch "${FIX}/docs/security/THREAT.md"
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
data = {
    "surfaces": [
        {"id": "uploads", "label": "Uploads", "doc": "docs/security/THREAT.md",
         "controls": ["type_taille_mime", "secrets", "permissions", "audit"]}
    ],
    "control_catalog": {
        "type_taille_mime": "validation",
        "secrets": "secrets",
        "permissions": "perms",
        "audit": "audit"
    }
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
# 2. fixture valide → vert
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture valide → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture valide → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 3. contrôle inconnu → rouge
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["surfaces"][0]["controls"].append("controle_fantome")
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"controle_fantome"* ]]; then
  echo "✅ contrôle inconnu → rouge"
  pass=$((pass + 1))
else
  echo "❌ contrôle inconnu → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 4. contrôle critique manquant → rouge
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["surfaces"][0]["controls"] = ["secrets", "permissions"]
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"audit"* ]]; then
  echo "✅ contrôle critique manquant → rouge"
  pass=$((pass + 1))
else
  echo "❌ contrôle critique manquant → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 5. document introuvable → rouge
python3 - "${FIX}/registry.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["surfaces"][0]["doc"] = "docs/security/ABSENT.md"
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/registry.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"ABSENT"* ]]; then
  echo "✅ document introuvable → rouge"
  pass=$((pass + 1))
else
  echo "❌ document introuvable → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo ""
echo "── check-security-threat-models.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
