#!/usr/bin/env bash
#
# check-runbooks.sh — garde de couverture runbook par bounded context (MAT-015, issue #5873).
#
# Vérifie que `dev-hub/tools/runbook-registry.json` est cohérent :
#   1. JSON valide + champs obligatoires (drill_log, platform_runbooks, contexts) ;
#   2. les runbooks plateforme référencés existent sur le disque ;
#   3. chaque BC actif possède une entrée (notes ou runbooks) ; chaque BC
#      planifié est marqué `status: planned` ;
#   4. chaque runbook référencé par un BC existe sur le disque ;
#   5. le drill log existe et contient au moins une entrée datée (preuve
#      d'exercice backup/restore/rollback — MAT-015).
#
# Usage : bash dev-hub/tools/check-runbooks.sh [registry] [repo_root]
# Tests : bash dev-hub/tools/tests/check-runbooks.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${1:-${SCRIPT_DIR}/runbook-registry.json}"
REPO_ROOT="${2:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
cd "${REPO_ROOT}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre runbook introuvable : ${REGISTRY} (issue #5873)." >&2
  exit 1
fi

python3 - "${REGISTRY}" << 'PYEOF'
import json, re, sys
from pathlib import Path

registry = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
errors = []
def err(msg):
    errors.append(msg)

# 1. champs obligatoires
for key in ("drill_log", "platform_runbooks", "contexts"):
    if key not in registry:
        err(f"clé obligatoire '{key}' absente du registre")
platform = registry.get("platform_runbooks") or {}

# 2. runbooks plateforme existent
for role, path in platform.items():
    if not Path(path).exists():
        err(f"runbook plateforme '{role}' introuvable : {path}")

# 3. contexte actif avec entrée, planifié marqué
active = set()
for ctx in registry.get("contexts", []):
    code = ctx.get("code", "?")
    if ctx.get("status") == "planned":
        continue
    active.add(code)
    if not ctx.get("notes") and not ctx.get("runbooks"):
        err(f"{code} : aucune couverture (notes ou runbooks) — un BC actif doit être couvert")

# 4. runbooks référencés existent
for ctx in registry.get("contexts", []):
    for rb in ctx.get("runbooks", []):
        if not Path(rb).exists():
            err(f"{ctx.get('code')} : runbook référencé introuvable : {rb}")

# 5. drill log avec preuve datée
drill = registry.get("drill_log")
if drill:
    if not Path(drill).exists():
        err(f"drill log introuvable : {drill}")
    else:
        text = Path(drill).read_text(encoding="utf-8")
        dated = re.findall(r"\b\d{4}-\d{2}-\d{2}\b", text)
        if not dated:
            err(f"drill log {drill} : aucune entrée datée — preuve d'exercice backup/restore/rollback requise")

if errors:
    print("::error::Registre runbook incohérent (issue #5873, MAT-015) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Registre runbook cohérent — {len(active)} BC actifs couverts, runbooks vérifiés, drill log daté.")
PYEOF
