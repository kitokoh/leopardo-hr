#!/usr/bin/env bash
#
# check-performance-budgets.sh — budgets de performance et guards N+1 (MAT-014, issue #5872).
#
# 1. Cohérence du registre `performance-budgets.json` (structure, budgets, index) ;
# 2. SIGNALEMENT (warning, non bloquant) :
#    - `->get()` / `->all()` non paginé dans les contrôleurs API (scans lents) ;
#    - requêtes Eloquent dans des boucles (pattern N+1) ;
#    - index obligatoires du registre absents des migrations (création à prévoir).
#
# Un registre cassé fait échouer la CI ; les scans lents sont signalés pour
# triage (acceptance MAT-014 : « les scans lents sont signalés »).
#
# Usage : bash dev-hub/tools/check-performance-budgets.sh [api_dir] [registry]
# Tests : bash dev-hub/tools/tests/check-performance-budgets.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${2:-${SCRIPT_DIR}/performance-budgets.json}"
API_DIR="${1:-api}"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre budgets introuvable : ${REGISTRY} (issue #5872)." >&2
  exit 1
fi

python3 - "${REGISTRY}" "${API_DIR}" << 'PYEOF'
import json, re, sys
from pathlib import Path

registry = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
api_dir = Path(sys.argv[2])

errors = []
warnings = []

# ── 1. Structure du registre ─────────────────────────────────────────────────
for key in ("rules", "critical_endpoints", "required_indexes"):
    if key not in registry:
        errors.append(f"clé obligatoire '{key}' absente du registre")

rules = registry.get("rules") or {}
for key in ("pagination", "n_plus_one", "p95_target_ms", "p99_target_ms"):
    if key not in rules:
        errors.append(f"règle '{key}' absente de rules")

seen = set()
for ep in registry.get("critical_endpoints") or []:
    key = f"{ep.get('method')} {ep.get('route')}"
    if key in seen:
        errors.append(f"endpoint dupliqué : {key}")
    seen.add(key)
    for k in ("method", "route", "max_queries", "p95_ms", "pagination"):
        if k not in ep:
            errors.append(f"endpoint {key} : clé '{k}' manquante")
    if not ep.get("route", "").startswith("/api/"):
        errors.append(f"endpoint {key} : route invalide")

for idx in registry.get("required_indexes") or []:
    for k in ("table", "index"):
        if k not in idx:
            errors.append(f"index requis : clé '{k}' manquante")

# ── 2. Signalements (warnings) ───────────────────────────────────────────────
if api_dir.exists():
    # a) requêtes non paginées dans les contrôleurs API
    query_re = re.compile(r"->(get|all)\(\)")
    paginate_re = re.compile(r"->paginate\(|->simplePaginate\(|->cursorPaginate\(|->limit\(|->take\(")
    for ctrl in sorted(api_dir.joinpath("app").rglob("*Controller.php")):
        text = ctrl.read_text(encoding="utf-8", errors="replace")
        if query_re.search(text) and not paginate_re.search(text):
            warnings.append(f"{ctrl.relative_to(api_dir)} : requêtes non paginées (get/all) sans paginate/limit visible — scan lent potentiel")

    # b) pattern N+1 : requête dans une boucle
    n1_re = re.compile(r"(foreach|while)\s*\([^)]*\)\s*\{[^}]{0,600}?->(get|first|find|where)\(")
    for ctrl in sorted(api_dir.joinpath("app").rglob("*Controller.php")):
        text = ctrl.read_text(encoding="utf-8", errors="replace")
        text_clean = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)
        for m in n1_re.finditer(text_clean):
            line_no = text[: m.start()].count("\n") + 1
            warnings.append(f"{ctrl.relative_to(api_dir)}:{line_no} : requête Eloquent dans une boucle — pattern N+1 potentiel")

    # c) index obligatoires absents des migrations
    migration_text = ""
    for mf in sorted(api_dir.joinpath("database/migrations").rglob("*.php")):
        migration_text += mf.read_text(encoding="utf-8", errors="replace") + "\n"
    for idx in registry.get("required_indexes") or []:
        name = idx.get("index", "")
        if name and name not in migration_text:
            warnings.append(f"index requis '{name}' ({idx.get('table')}) absent des migrations — création à prévoir")

if errors:
    print("::error::Registre budgets de performance incohérent (issue #5872, MAT-014) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Registre budgets cohérent — {len(seen)} endpoints critiques, {len(registry.get('required_indexes') or [])} index requis.")
for w in warnings:
    print(f"  ⚠️  {w}")
if warnings:
    print(f"({len(warnings)} signalement(s) à trier — non bloquant, acceptance MAT-014 « scans lents signalés »)")
PYEOF
