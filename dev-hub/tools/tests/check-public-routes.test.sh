#!/usr/bin/env bash
#
# check-public-routes.test.sh — tests du garde check-public-routes.sh (issue #5519).
#
# Scénarios :
#   1. route:list complet (fixture) → garde vert ;
#   2. route publique canonique manquante dans route:list → rouge + nom de la route.
#
# Usage : bash dev-hub/tools/tests/check-public-routes.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-public-routes.sh"
CANONICAL="${SCRIPT_DIR}/../public-routes-canonical.txt"

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0

# Route:list synthétique couvrant toutes les routes canoniques (méthode + uri).
python3 - "${CANONICAL}" "${TMP}/routes-full.json" << 'PYEOF'
import json, sys
routes = []
for ln in open(sys.argv[1], encoding="utf-8").read().splitlines():
    ln = ln.strip()
    if not ln or ln.startswith("#"):
        continue
    parts = ln.split(None, 1)
    if len(parts) != 2:
        continue
    method, uri = parts[0].split("|")[0].upper(), parts[1].lstrip("/")
    routes.append({"method": method, "uri": uri})
json.dump(routes, open(sys.argv[2], "w", encoding="utf-8"), indent=2)
print(f"fixture: {len(routes)} routes")
PYEOF

# ── 1. Liste complète → vert ─────────────────────────────────────────────────
out="$(bash "${GUARD}" api --route-list "${TMP}/routes-full.json" 2>&1 || true)"
if [[ "${out}" == *"✅"* ]]; then
  echo "✅ route:list complet → vert"
  pass=$((pass + 1))
else
  echo "❌ route:list complet → vert attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

# ── 2. Route publique manquante → rouge ──────────────────────────────────────
python3 - "${TMP}/routes-full.json" "${TMP}/routes-missing.json" << 'PYEOF'
import json, sys
routes = json.load(open(sys.argv[1], encoding="utf-8"))
# Retire la route portail (le scénario de la régression #5495/#5377)
routes = [r for r in routes if not r["uri"].startswith("api/v1/accounting/documents/shared/")]
json.dump(routes, open(sys.argv[2], "w", encoding="utf-8"), indent=2)
PYEOF
out="$(bash "${GUARD}" api --route-list "${TMP}/routes-missing.json" 2>&1 || true)"
if [[ "${out}" == *"::error::"* ]] && [[ "${out}" == *"documents/shared"* ]]; then
  echo "✅ route publique manquante → rouge avec le nom de la route"
  pass=$((pass + 1))
else
  echo "❌ route publique manquante → rouge attendu"
  echo "${out}" | tail -4
  fail=$((fail + 1))
fi

echo ""
echo "── check-public-routes.test.sh : ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
