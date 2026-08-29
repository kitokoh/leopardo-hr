#!/usr/bin/env bash
#
# check-pilot-gates.sh — garde des critères go/no-go pilotes (MAT-018, issue #5876).
#
# Vérifie `dev-hub/tools/pilot-gates.json` :
#   1. JSON valide + champs obligatoires (pilots, decision_rules) ;
#   2. chaque pilote a id unique, label, go_decision ∈ {pending, go, no_go}, gates ≥ 1 ;
#   3. chaque gate a id unique par pilote, label, status ∈ {pending, validated} ;
#   4. CONSISTANCE : go_decision=go interdit tant qu'un gate n'est pas validé ;
#      go_decision=no_go requiert un gate bloqué documenté (status pending) ;
#   5. les IDs de gates obligatoires (manifest, recette, golden_journey) existent.
#
# Usage : bash dev-hub/tools/check-pilot-gates.sh [registry] [repo_root]
# Tests : bash dev-hub/tools/tests/check-pilot-gates.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${1:-${SCRIPT_DIR}/pilot-gates.json}"
REPO_ROOT="${2:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
cd "${REPO_ROOT}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre pilotes introuvable : ${REGISTRY} (issue #5876)." >&2
  exit 1
fi

python3 - "${REGISTRY}" << 'PYEOF'
import json, sys
from pathlib import Path

registry = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
errors = []
def err(msg):
    errors.append(msg)

for key in ("pilots", "decision_rules"):
    if key not in registry:
        err(f"clé obligatoire '{key}' absente du registre")

pilots = registry.get("pilots") or []
pilot_ids = []
MANDATORY_GATES = ("manifest", "recette", "golden_journey")

for p in pilots:
    pid = p.get("id", "<sans id>")
    pilot_ids.append(pid)
    for key in ("id", "label", "go_decision", "gates"):
        if key not in p:
            err(f"pilote {pid} : clé obligatoire '{key}' absente")
    decision = p.get("go_decision")
    if decision not in ("pending", "go", "no_go"):
        err(f"pilote {pid} : go_decision '{decision}' invalide (pending/go/no_go)")
    gates = p.get("gates") or []
    if not gates:
        err(f"pilote {pid} : aucun gate")
    gate_ids = []
    statuses = []
    for g in gates:
        gid = g.get("id", "?")
        gate_ids.append(gid)
        for key in ("id", "label", "status"):
            if key not in g:
                err(f"pilote {pid}/{gid} : clé obligatoire '{key}' absente")
        st = g.get("status")
        statuses.append(st)
        if st not in ("pending", "validated"):
            err(f"pilote {pid}/{gid} : status '{st}' invalide (pending/validated)")
    if len(gate_ids) != len(set(gate_ids)):
        err(f"pilote {pid} : ids de gates dupliqués")
    for mg in MANDATORY_GATES:
        if mg not in gate_ids:
            err(f"pilote {pid} : gate obligatoire '{mg}' absent")
    # 4. consistance de la décision
    all_validated = all(s == "validated" for s in statuses)
    if decision == "go" and not all_validated:
        err(f"pilote {pid} : go_decision=go alors que des gates sont pending — GO interdit tant que tous les gates ne sont pas validés")
    if decision == "no_go" and all_validated:
        err(f"pilote {pid} : go_decision=no_go alors que tous les gates sont validés — décision incohérente")

if len(pilot_ids) != len(set(pilot_ids)):
    err("ids de pilotes dupliqués")

if errors:
    print("::error::Registre pilotes incohérent (issue #5876, MAT-018) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Critères go/no-go cohérents — {len(pilots)} pilotes, gates obligatoires vérifiés, aucune décision GO prématurée.")
PYEOF
