#!/usr/bin/env bash
#
# check-bc-registry-test.sh — tests du garde check-bc-registry.sh (MAT-001, issue #5859).
#
# Scénarios :
#   1. registre réel (dev-hub/tools/bc-registry.json) → garde vert ;
#   2. registre minimal valide dans un fixture → garde vert ;
#   3. owner absent de CODEOWNERS → garde rouge (message actionnable) ;
#   4. chemin actif inexistant → garde rouge ;
#   5. dépendance vers un BC inconnu → garde rouge ;
#   6. code BC manquant → garde rouge ;
#   7. section CODEOWNERS manquante → garde rouge ;
#   8. JSON invalide → garde rouge.
#
# Usage : bash dev-hub/tools/check-bc-registry-test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/check-bc-registry.sh"
REAL_REGISTRY="${SCRIPT_DIR}/bc-registry.json"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

expect_ok() { # description registry root [expected_max]
  local desc="$1"
  if bash "${GUARD}" "$2" "$3" "${4:-}" >/dev/null 2>&1; then
    echo "✅ ${desc}"
    pass=$((pass + 1))
  else
    echo "❌ ${desc} — le garde a échoué alors qu'il devait passer"
    fail=$((fail + 1))
  fi
}

expect_fail() { # description motif [registry] [root] [expected_max]
  local desc="$1" needle="$2"
  local out
  out="$(bash "${GUARD}" "$3" "$4" "${5:-}" 2>&1 || true)"
  if [[ -n "${needle}" ]] && [[ "${out}" != *"${needle}"* ]]; then
    echo "❌ ${desc} — le garde a échoué mais sans le message attendu (cherché: ${needle})"
    echo "--- sortie ---"
    echo "${out}"
    fail=$((fail + 1))
    return
  fi
  if [[ -z "${out}" ]]; then
    echo "❌ ${desc} — le garde a réussi alors qu'il devait échouer"
    fail=$((fail + 1))
    return
  fi
  echo "✅ ${desc}"
  pass=$((pass + 1))
}

# ── 1. Registre réel ──────────────────────────────────────────────────────────
expect_ok "registre réel valide" "${REAL_REGISTRY}" "${REPO_ROOT}"

# ── 2. Fixture minimal valide ────────────────────────────────────────────────
FIX="${TMP}/ok"
mkdir -p "${FIX}/api/app/Modules/Alpha" "${FIX}/api/app/Modules/Beta" \
         "${FIX}/api/routes/modules" "${FIX}/api/database/migrations/tenant" \
         "${FIX}/api/app/Events"
touch "${FIX}/api/routes/modules/alpha.php" "${FIX}/api/database/migrations/tenant/2026_01_01_000001_create_alpha_tables.php"
cat > "${FIX}/api/app/Events/AlphaCreated.php" << 'PHEOF'
<?php

namespace App\Events;

class AlphaCreated {}
PHEOF
cat > "${FIX}/bc-registry.json" << 'JEOF'
{
  "contexts": [
    {
      "code": "BC-01",
      "name": "ALPHA",
      "label": "Alpha Context",
      "owner": "tester",
      "priority": "P0",
      "status": "active",
      "paths": ["api/app/Modules/Alpha"],
      "routes": ["api/routes/modules/alpha.php"],
      "migrations": ["api/database/migrations/tenant/*alpha*"],
      "events": ["App\\Events\\AlphaCreated"],
      "allowed_dependencies": ["BC-02"]
    },
    {
      "code": "BC-02",
      "name": "BETA",
      "label": "Beta Context",
      "owner": "tester",
      "priority": "P1",
      "status": "planned",
      "paths": ["api/app/Modules/Beta"],
      "routes": [],
      "migrations": [],
      "events": [],
      "allowed_dependencies": []
    }
  ]
}
JEOF
cat > "${FIX}/CODEOWNERS" << 'CEOF'
* @nobody
# BC-01 ALPHA
/api/app/Modules/Alpha/ @tester
/api/routes/modules/alpha.php @tester
# BC-02 BETA
/api/app/Modules/Beta/ @tester
CEOF
expect_ok "fixture minimal valide" "${FIX}/bc-registry.json" "${FIX}" 2

# ── 3. Owner absent de CODEOWNERS ────────────────────────────────────────────
FIX="${TMP}/owner"
mkdir -p "${FIX}/api/app/Modules/Alpha"
cp "${TMP}/ok/bc-registry.json" "${FIX}/bc-registry.json"
cat > "${FIX}/CODEOWNERS" << 'CEOF'
* @nobody
# BC-01 ALPHA
/api/app/Modules/Alpha/ @qui
# BC-02 BETA
/api/app/Modules/Beta/ @qui
CEOF
expect_fail "owner absent de CODEOWNERS → rouge" "absent de CODEOWNERS" "${FIX}/bc-registry.json" "${FIX}" 2

# ── 4. Chemin actif inexistant ───────────────────────────────────────────────
FIX="${TMP}/path"
mkdir -p "${FIX}/api/app/Modules"
cp "${TMP}/ok/bc-registry.json" "${FIX}/bc-registry.json"
sed -i 's|api/app/Modules/Alpha|api/app/Modules/Gamma|' "${FIX}/bc-registry.json"
cp "${TMP}/ok/CODEOWNERS" "${FIX}/CODEOWNERS"
expect_fail "chemin actif inexistant → rouge" "introuvable sur le disque" "${FIX}/bc-registry.json" "${FIX}" 2

# ── 5. Dépendance inconnue ───────────────────────────────────────────────────
FIX="${TMP}/dep"
mkdir -p "${FIX}/api/app/Modules/Alpha"
cp "${TMP}/ok/bc-registry.json" "${FIX}/bc-registry.json"
sed -i 's/"allowed_dependencies": \["BC-02"\]/"allowed_dependencies": ["BC-99"]/' "${FIX}/bc-registry.json"
cp "${TMP}/ok/CODEOWNERS" "${FIX}/CODEOWNERS"
expect_fail "dépendance vers BC inconnu → rouge" "dépendance 'BC-99' inconnue" "${FIX}/bc-registry.json" "${FIX}" 2

# ── 6. Code BC manquant ──────────────────────────────────────────────────────
FIX="${TMP}/missing"
mkdir -p "${FIX}/api/app/Modules/Alpha"
python3 - "${TMP}/ok/bc-registry.json" "${FIX}/bc-registry.json" << 'PYEOF'
import json, sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
data["contexts"] = [c for c in data["contexts"] if c["code"] != "BC-02"]
json.dump(data, open(sys.argv[2], "w", encoding="utf-8"), indent=2)
PYEOF
cp "${TMP}/ok/CODEOWNERS" "${FIX}/CODEOWNERS"
expect_fail "code BC manquant → rouge" "BC-02" "${FIX}/bc-registry.json" "${FIX}" 2

# ── 7. Section CODEOWNERS manquante ──────────────────────────────────────────
FIX="${TMP}/no-section"
mkdir -p "${FIX}/api/app/Modules/Alpha"
cp "${TMP}/ok/bc-registry.json" "${FIX}/bc-registry.json"
cat > "${FIX}/CODEOWNERS" << 'CEOF'
* @tester
CEOF
expect_fail "section CODEOWNERS manquante → rouge" "section '# BC-01' manquante" "${FIX}/bc-registry.json" "${FIX}" 2

# ── 8. JSON invalide ─────────────────────────────────────────────────────────
FIX="${TMP}/badjson"
mkdir -p "${FIX}"
printf '{ pas du json ' > "${FIX}/bc-registry.json"
touch "${FIX}/CODEOWNERS"
expect_fail "JSON invalide → rouge" "JSON invalide" "${FIX}/bc-registry.json" "${FIX}"

echo ""
echo "── check-bc-registry-test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
