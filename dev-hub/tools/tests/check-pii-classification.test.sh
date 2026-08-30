#!/usr/bin/env bash
#
# check-pii-classification.test.sh — tests du garde PII (MAT-011, issue #5869).
#
# Scénarios :
#   1. catalogue réel → vert ;
#   2. fixture valide → vert ;
#   3. champ sans politique → rouge ;
#   4. classification inconnue → rouge ;
#   5. clé dupliquée → rouge ;
#   6. champ déclaré dans un contexte mais absent → rouge.
#
# Usage : bash dev-hub/tools/tests/check-pii-classification.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )" && pwd)"
GUARD="${SCRIPT_DIR}/../check-pii-classification.sh"
REG_REAL="${SCRIPT_DIR}/../pii-classification.json"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# ── 1. catalogue réel → vert ────────────────────────────────────────────────
out="$(bash "${GUARD}" "${REG_REAL}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ catalogue réel → vert"
  pass=$((pass + 1))
else
  echo "❌ catalogue réel → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# ── Fixture valide ──────────────────────────────────────────────────────────
FIX="${TMP}/pii-good.json"
python3 - "${FIX}" << 'JEOF'
import json, sys
data = {
    "schema_version": "test",
    "purpose": "test",
    "categories": {"identite": "id"},
    "classifications": ["confidential"],
    "retentions": ["10y"],
    "anonymizations": ["redact"],
    "encryptions": ["at_rest"],
    "contexts": {"BC-04 HR": ["employee_email"]},
    "fields": [
        {"key": "employee_email", "context": "BC-04 HR", "category": "identite",
         "classification": "confidential", "retention": "10y",
         "anonymization": "redact", "encryption": "at_rest", "access": "rh"}
    ]
}
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${FIX}" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ fixture valide → vert"
  pass=$((pass + 1))
else
  echo "❌ fixture valide → vert attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# ── 3. champ sans politique → rouge ─────────────────────────────────────────
python3 - "${TMP}/pii-missing.json" "${FIX}" << 'JEOF'
import json, sys
data = json.load(open(sys.argv[2], encoding="utf-8"))
data["fields"][0]["classification"] = ""
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${TMP}/pii-missing.json" 2>&1 || true)"
if [[ "${out}" == *"❌"* ]]; then
  echo "✅ champ sans classification → rouge"
  pass=$((pass + 1))
else
  echo "❌ champ sans classification → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# ── 4. classification inconnue → rouge ─────────────────────────────────────
python3 - "${TMP}/pii-badclass.json" "${FIX}" << 'JEOF'
import json, sys
data = json.load(open(sys.argv[2], encoding="utf-8"))
data["fields"][0]["classification"] = "top_secret_weird"
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${TMP}/pii-badclass.json" 2>&1 || true)"
if [[ "${out}" == *"❌"* ]]; then
  echo "✅ classification inconnue → rouge"
  pass=$((pass + 1))
else
  echo "❌ classification inconnue → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# ── 5. clé dupliquée → rouge ────────────────────────────────────────────────
python3 - "${TMP}/pii-dup.json" "${FIX}" << 'JEOF'
import json, sys
data = json.load(open(sys.argv[2], encoding="utf-8"))
data["fields"].append(dict(data["fields"][0]))
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${TMP}/pii-dup.json" 2>&1 || true)"
if [[ "${out}" == *"❌"* ]]; then
  echo "✅ clé dupliquée → rouge"
  pass=$((pass + 1))
else
  echo "❌ clé dupliquée → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

# ── 6. champ déclaré dans un contexte mais absent des fields → rouge ────────
python3 - "${TMP}/pii-dead.json" "${FIX}" << 'JEOF'
import json, sys
data = json.load(open(sys.argv[2], encoding="utf-8"))
data["contexts"]["BC-04 HR"].append("employee_national_id")
json.dump(data, open(sys.argv[1], "w", encoding="utf-8"), indent=2)
JEOF
out="$(bash "${GUARD}" "${TMP}/pii-dead.json" 2>&1 || true)"
if [[ "${out}" == *"❌"* ]]; then
  echo "✅ champ déclaré absent → rouge"
  pass=$((pass + 1))
else
  echo "❌ champ déclaré absent → rouge attendu"
  echo "${out}" | tail -3
  fail=$((fail + 1))
fi

echo "──────────────────────────────"
echo "PASS=${pass} FAIL=${fail}"
[[ "${fail}" -eq 0 ]]
