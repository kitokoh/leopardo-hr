#!/usr/bin/env bash
#
# check-freeze-gate.test.sh — tests du garde des gates de gel (issue #8585).
#
# Le garde depend de la date du jour : les tests la passent en 3e argument, ce
# qui rend l'expiration deterministe (aucun test ne depend de l'horloge reelle).
#
# Scenarios :
#   1. registre reel (gate tenu) -> vert ;
#   2. gel expire SANS decision de gate -> rouge (le cas #8585) ;
#   3. gel encore dans sa fenetre + gate pending -> vert ;
#   4. gel encore dans sa fenetre + gate deja tenu -> rouge (gate premature) ;
#   5. document de reference introuvable -> rouge ;
#   6. dates non ISO -> rouge ;
#   7. starts_on > ends_on -> rouge ;
#   8. status de gate invalide -> rouge.
#
# Usage : bash dev-hub/tools/tests/check-freeze-gate.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-freeze-gate.sh"
REG_REAL="${SCRIPT_DIR}/../governance-freezes.json"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0
ok() { echo "✅ $1"; pass=$((pass + 1)); }
ko() { echo "❌ $1"; fail=$((fail + 1)); }

# Faux depot avec le document de reference
FAKE="${TMP}/repo"
mkdir -p "${FAKE}/docs/GOUVERNANCE"
echo "# Freeze" > "${FAKE}/docs/GOUVERNANCE/FREEZE_SCOPE_60J.md"

write_registry() {
  # $1 = chemin, $2 = ends_on, $3 = status du gate, $4 = doc, $5 = starts_on
  python3 - "$1" "$2" "$3" "$4" "$5" << 'JEOF'
import json, sys
path, ends, status, doc, starts = sys.argv[1:6]
data = {
    "freezes": [
        {"id": "freeze-60j", "label": "Freeze 60j", "doc": doc,
         "starts_on": starts, "ends_on": ends,
         "gate": {"id": "j60", "label": "Gate J60", "status": status,
                  "owner": "founder", "updated_at": "2026-10-26"}}
    ]
}
json.dump(data, open(path, "w", encoding="utf-8"), indent=2)
JEOF
}

# ── 1. Registre reel -> vert ─────────────────────────────────────────────────
out="$(bash "${GUARD}" "${REG_REAL}" "${REPO_ROOT}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then ok "registre réel (gate tenu) → vert"; else
  ko "registre réel → vert attendu"; echo "${out}" | tail -3; fi

# ── 2. Gel expiré SANS décision -> rouge (le cas #8585) ──────────────────────
write_registry "${TMP}/r2.json" "2026-10-17" "pending" "docs/GOUVERNANCE/FREEZE_SCOPE_60J.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r2.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"EXPIRÉ"* ]]; then
  ok "gel expiré sans décision de gate → rouge (cas #8585)"; else
  ko "gel expiré sans décision → rouge attendu"; echo "${out}" | tail -3; fi

# ── 3. Gel en cours + gate pending -> vert ───────────────────────────────────
write_registry "${TMP}/r3.json" "2026-12-31" "pending" "docs/GOUVERNANCE/FREEZE_SCOPE_60J.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r3.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then ok "gel en cours + gate pending → vert"; else
  ko "gel en cours + gate pending → vert attendu"; echo "${out}" | tail -3; fi

# ── 4. Gel en cours + gate déjà tenu -> rouge ────────────────────────────────
write_registry "${TMP}/r4.json" "2026-12-31" "held" "docs/GOUVERNANCE/FREEZE_SCOPE_60J.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r4.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"avant l'heure"* ]]; then
  ok "gel en cours + gate déjà tenu → rouge (gate prématuré)"; else
  ko "gate prématuré → rouge attendu"; echo "${out}" | tail -3; fi

# ── 5. Document de référence introuvable -> rouge ────────────────────────────
write_registry "${TMP}/r5.json" "2026-12-31" "pending" "docs/GOUVERNANCE/ABSENT.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r5.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"introuvable"* ]]; then
  ok "document de référence introuvable → rouge"; else
  ko "document introuvable → rouge attendu"; echo "${out}" | tail -3; fi

# ── 6. Dates non ISO -> rouge ────────────────────────────────────────────────
write_registry "${TMP}/r6.json" "31/12/2026" "pending" "docs/GOUVERNANCE/FREEZE_SCOPE_60J.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r6.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"non ISO"* ]]; then
  ok "date non ISO → rouge"; else
  ko "date non ISO → rouge attendu"; echo "${out}" | tail -3; fi

# ── 7. starts_on > ends_on -> rouge ──────────────────────────────────────────
write_registry "${TMP}/r7.json" "2026-08-01" "pending" "docs/GOUVERNANCE/FREEZE_SCOPE_60J.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r7.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"postérieur"* ]]; then
  ok "starts_on > ends_on → rouge"; else
  ko "starts_on > ends_on → rouge attendu"; echo "${out}" | tail -3; fi

# ── 8. Status de gate invalide -> rouge ──────────────────────────────────────
write_registry "${TMP}/r8.json" "2026-12-31" "peut-etre" "docs/GOUVERNANCE/FREEZE_SCOPE_60J.md" "2026-08-19"
out="$(bash "${GUARD}" "${TMP}/r8.json" "${FAKE}" 2026-10-26 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"invalide"* ]]; then
  ok "status de gate invalide → rouge"; else
  ko "status invalide → rouge attendu"; echo "${out}" | tail -3; fi

echo ""
echo "── check-freeze-gate.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
