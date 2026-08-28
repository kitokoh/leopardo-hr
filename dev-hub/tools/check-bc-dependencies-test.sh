#!/usr/bin/env bash
#
# check-bc-dependencies-test.sh — tests du garde check-bc-dependencies.sh (MAT-002, issue #5860).
#
# Scénarios :
#   1. dépôt réel → garde vert (58 paires gelées dans l'allowlist) ;
#   2. fixture : import inter-BC non autorisé sans allowlist → rouge + message actionnable ;
#   3. fixture : import inter-BC couvert par allowed_dependencies → vert ;
#   4. fixture : import inter-BC présent dans l'allowlist → vert (dette gelée).
#
# Usage : bash dev-hub/tools/check-bc-dependencies-test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/check-bc-dependencies.sh"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

expect_ok() { # description api_dir registry
  local desc="$1" api_dir="$2" registry="$3"
  local out
  out="$(cd "${REPO_ROOT}" && bash "${GUARD}" "${api_dir}" "${registry}" 2>&1 || true)"
  if [[ "${out}" == *"✅"* ]]; then
    echo "✅ ${desc}"
    pass=$((pass + 1))
  else
    echo "❌ ${desc} — le garde a échoué alors qu'il devait passer"
    echo "${out}" | tail -5
    fail=$((fail + 1))
  fi
}

expect_fail() { # description needle api_dir registry
  local desc="$1" needle="$2" api_dir="$3" registry="$4"
  local out
  out="$(cd "${REPO_ROOT}" && bash "${GUARD}" "${api_dir}" "${registry}" 2>&1 || true)"
  if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"${needle}"* ]]; then
    echo "✅ ${desc}"
    pass=$((pass + 1))
  elif [[ "${out}" != *"::error::"* ]]; then
    echo "❌ ${desc} — le garde a réussi alors qu'il devait échouer"
    fail=$((fail + 1))
  else
    echo "❌ ${desc} — échec sans le message attendu (cherché: ${needle})"
    echo "${out}" | tail -5
    fail=$((fail + 1))
  fi
}

# ── 1. Dépôt réel ─────────────────────────────────────────────────────────────
expect_ok "dépôt réel (58 paires couvertes)" "api" "${SCRIPT_DIR}/bc-registry.json"

# ── Fixtures ──────────────────────────────────────────────────────────────────
make_fixture() { # dest
  local dest="$1"
  mkdir -p "${dest}/api/app/Modules/Alpha" "${dest}/api/app/Modules/Beta/Models"
  cat > "${dest}/api/app/Modules/Alpha/Service.php" << 'PHEOF'
<?php

namespace App\Modules\Alpha;

use App\Modules\Beta\Models\Beta;

class Service {}
PHEOF
  cat > "${dest}/api/app/Modules/Beta/Models/Beta.php" << 'PHEOF'
<?php

namespace App\Modules\Beta\Models;

class Beta {}
PHEOF
  cat > "${dest}/bc-registry.json" << 'JEOF'
{
  "contexts": [
    {"code": "BC-01", "name": "ALPHA", "label": "Alpha", "owner": "tester", "priority": "P0", "status": "active",
     "paths": ["api/app/Modules/Alpha"], "routes": [], "migrations": [], "events": [],
     "allowed_dependencies": []},
    {"code": "BC-02", "name": "BETA", "label": "Beta", "owner": "tester", "priority": "P1", "status": "active",
     "paths": ["api/app/Modules/Beta"], "routes": [], "migrations": [], "events": [],
     "allowed_dependencies": []}
  ]
}
JEOF
}

# ── 2. Import inter-BC non autorisé (pas d'allowlist) ─────────────────────────
make_fixture "${TMP}/noadd"
expect_fail "import inter-BC non autorisé → rouge" "BC-01 -> BC-02" "${TMP}/noadd/api" "${TMP}/noadd/bc-registry.json"

# ── 3. Import couvert par allowed_dependencies → vert ─────────────────────────
make_fixture "${TMP}/allowed"
python3 - "${TMP}/allowed/bc-registry.json" << 'PYEOF'
import json, sys
p = sys.argv[1]
data = json.load(open(p, encoding="utf-8"))
data["contexts"][0]["allowed_dependencies"] = ["BC-02"]
json.dump(data, open(p, "w", encoding="utf-8"), indent=2)
PYEOF
expect_ok "import couvert par allowed_dependencies → vert" "${TMP}/allowed/api" "${TMP}/allowed/bc-registry.json"

# ── 4. Import présent dans l'allowlist → vert ─────────────────────────────────
make_fixture "${TMP}/allowlist"
printf '# dette gelée\nBC-01 -> BC-02\n' > "${TMP}/allowlist/bc-dependencies-allowlist.txt"
expect_ok "import présent dans l'allowlist → vert" "${TMP}/allowlist/api" "${TMP}/allowlist/bc-registry.json"

echo ""
echo "── check-bc-dependencies-test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
