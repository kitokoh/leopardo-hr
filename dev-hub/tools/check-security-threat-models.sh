#!/usr/bin/env bash
#
# check-security-threat-models.sh — revue sécurité : registre des threat models (MAT-017, issue #5875).
#
# Vérifie que `dev-hub/tools/security-threat-models.json` est cohérent :
#   1. JSON valide + champs obligatoires (surfaces, control_catalog) ;
#   2. chaque surface a id unique, label, doc existante, contrôles non vides ;
#   3. chaque contrôle cité existe dans le catalogue (aucun contrôle fantôme) ;
#   4. les contrôles critiques (secrets, permissions, audit) sont couverts par
#      chaque surface active.
#
# Le document de référence (docs/security/THREAT_MODELS_MAT017.md) détaille
# menaces et contrôles par surface ; le registre garantit la couverture.
#
# Usage : bash dev-hub/tools/check-security-threat-models.sh [registry] [repo_root]
# Tests : bash dev-hub/tools/tests/check-security-threat-models.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${1:-${SCRIPT_DIR}/security-threat-models.json}"
REPO_ROOT="${2:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
cd "${REPO_ROOT}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre threat models introuvable : ${REGISTRY} (issue #5875)." >&2
  exit 1
fi

python3 - "${REGISTRY}" << 'PYEOF'
import json, sys
from pathlib import Path

registry = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
errors = []
def err(msg):
    errors.append(msg)

for key in ("surfaces", "control_catalog"):
    if key not in registry:
        err(f"clé obligatoire '{key}' absente du registre")

catalog = registry.get("control_catalog") or {}
surfaces = registry.get("surfaces") or []

ids = []
for s in surfaces:
    sid = s.get("id", "<sans id>")
    ids.append(sid)
    for key in ("id", "label", "doc", "controls"):
        if key not in s:
            err(f"{sid} : clé obligatoire '{key}' absente")
            continue
    doc = s.get("doc", "")
    if doc and not Path(doc).exists():
        err(f"{sid} : document de threat model introuvable : {doc}")
    controls = s.get("controls") or []
    if not controls:
        err(f"{sid} : aucun contrôle listé")
    for c in controls:
        if c not in catalog:
            err(f"{sid} : contrôle '{c}' inconnu du catalogue")
    # contrôles critiques minimaux pour toute surface
    for critical in ("secrets", "permissions", "audit"):
        if critical not in controls:
            err(f"{sid} : contrôle critique '{critical}' manquant")

if len(ids) != len(set(ids)):
    err("ids de surfaces dupliqués")

if errors:
    print("::error::Registre threat models incohérent (issue #5875, MAT-017) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Threat models couverts — {len(surfaces)} surfaces, contrôles critiques vérifiés (secrets/permissions/audit).")
PYEOF
