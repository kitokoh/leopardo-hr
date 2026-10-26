#!/usr/bin/env bash
#
# check-pilot-gates.test.sh — tests du garde (MAT-018, issue #5876).
#
# Scénarios :
#   1. registre réel → vert ;
#   2. fixture valide → vert ;
#   3. GO avec un gate pending → rouge ;
#   4. status invalide → rouge ;
#   5. gate obligatoire absent → rouge.
#
# Usage : bash dev-hub/tools/tests/check-pilot-gates.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-pilot-gates.sh"
REG_REAL="${SCRIPT_DIR}/../pilot-gates.json"
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
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# ── Fixture ──────────────────────────────────────────────────────────────────
FIX="${TMP}/fix"
mkdir -p "${FIX}"
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
data = {
    "pilots": [
        {"id": "pilot-a", "label": "Pilote A", "go_decision": "pending",
         "gates": [
             {"id": "manifest", "label": "m", "status": "pending",
              "owner": "platform", "updated_at": "2026-10-26", "evidence": "cmd -> vert"},
             {"id": "recette", "label": "r", "status": "pending",
              "owner": "product", "updated_at": "2026-10-26"},
             {"id": "golden_journey", "label": "gj", "status": "pending",
              "owner": "qa", "updated_at": "2026-10-26", "evidence": "cmd -> vert"}
         ]}
    ],
    "decision_rules": {"go": "tous validés"}
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
# 2. fixture valide → vert
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture valide → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture valide → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 3. GO avec gate pending → rouge
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["go_decision"] = "go"
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"GO"* ]]; then
  echo "✅ GO prématuré → rouge"
  pass=$((pass + 1))
else
  echo "❌ GO prématuré → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 4. status invalide → rouge
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["go_decision"] = "pending"
data["pilots"][0]["gates"][0]["status"] = "peut-etre"
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"peut-etre"* ]]; then
  echo "✅ status invalide → rouge"
  pass=$((pass + 1))
else
  echo "❌ status invalide → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 5. gate obligatoire absent → rouge
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["gates"] = [g for g in data["pilots"][0]["gates"] if g["id"] != "recette"]
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"recette"* ]]; then
  echo "✅ gate obligatoire absent → rouge"
  pass=$((pass + 1))
else
  echo "❌ gate obligatoire absent → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 6. gate sans propriétaire → rouge (#8586)
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["gates"][0].pop("owner", None)
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"owner"* ]]; then
  echo "✅ gate sans propriétaire → rouge"
  pass=$((pass + 1))
else
  echo "❌ gate sans propriétaire → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 7. gate sans date → rouge (#8586)
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["gates"][0]["owner"] = "platform"
data["pilots"][0]["gates"][0].pop("updated_at", None)
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"updated_at"* ]]; then
  echo "✅ gate sans date → rouge"
  pass=$((pass + 1))
else
  echo "❌ gate sans date → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 8. date non ISO → rouge (#8586)
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["gates"][0]["updated_at"] = "26/10/2026"
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"non ISO"* ]]; then
  echo "✅ date non ISO → rouge"
  pass=$((pass + 1))
else
  echo "❌ date non ISO → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 9. gate technique sans preuve → rouge (#8586)
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["pilots"][0]["gates"][0]["updated_at"] = "2026-10-26"
data["pilots"][0]["gates"][0].pop("evidence", None)
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"evidence"* ]]; then
  echo "✅ gate technique sans preuve → rouge"
  pass=$((pass + 1))
else
  echo "❌ gate technique sans preuve → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# 10. gate non technique (recette) sans preuve → VERT (la preuve n'est exigée
#     que sur les gates techniques : une recette métier se signe, elle ne
#     s'exécute pas en CI).
#     Fixture RÉGÉNÉRÉE : les scénarios 3 à 9 l'ont mutée (status invalide,
#     gate supprimé) — réutiliser l'état muté testerait autre chose que ce
#     qu'on croit.
python3 - "${FIX}/gates.json" << 'JEOF'
import json, sys
data = {
    "pilots": [
        {"id": "pilot-a", "label": "Pilote A", "go_decision": "pending",
         "gates": [
             {"id": "manifest", "label": "m", "status": "pending",
              "owner": "platform", "updated_at": "2026-10-26", "evidence": "cmd -> vert"},
             {"id": "recette", "label": "r", "status": "pending",
              "owner": "product", "updated_at": "2026-10-26"},
             {"id": "golden_journey", "label": "gj", "status": "pending",
              "owner": "qa", "updated_at": "2026-10-26", "evidence": "cmd -> vert"}
         ]}
    ],
    "decision_rules": {"go": "tous validés"}
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}/gates.json" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ gate non technique sans preuve → vert (preuve exigée seulement sur les gates techniques)"
  pass=$((pass + 1))
else
  echo "❌ gate non technique sans preuve → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo ""
echo "── check-pilot-gates.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]