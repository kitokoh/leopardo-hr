#!/usr/bin/env bash
#
# check-freeze-gate.sh — garde des gates de sortie de gel (issue #8585).
#
# Contexte : `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` declare un gel de perimetre
# valable du 2026-08-19 au 2026-10-17, avec revision prevue « au gate J60 ».
# Le gate n'a JAMAIS ete tenu : le gel a expire en silence. Consequence : des
# interdictions (« nouvelle app mobile », « nouveau pays de paie ») restent
# affichees comme en vigueur alors que leur fenetre est close, et des chantiers
# geles (dont l'app mobile pompiste, #8585) restent bloques par une regle que
# personne n'a reconduite.
#
# Ce garde ne leve ni ne reconduit un gel — c'est une decision de fondateur.
# Il rend l'expiration VISIBLE : un gel expire sans decision de gate enregistree
# fait echouer le job.
#
# Verifie :
#   1. JSON valide + champs obligatoires (freezes) ;
#   2. chaque gel a id unique, label, doc, starts_on, ends_on (dates ISO),
#      starts_on <= ends_on, et un gate avec id/label/status/owner/updated_at ;
#   3. le document reference par `doc` existe reellement ;
#   4. CONSISTANCE : un gel dont `ends_on` est PASSE doit porter une decision de
#      gate (`status` != pending). Un gel encore dans sa fenetre doit rester
#      `pending` (on ne clot pas un gate avant l'heure).
#
# Usage : bash dev-hub/tools/check-freeze-gate.sh [registry] [repo_root] [today]
# Tests : bash dev-hub/tools/tests/check-freeze-gate.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${1:-${SCRIPT_DIR}/governance-freezes.json}"
REPO_ROOT="${2:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
TODAY="${3:-$(date -u +%Y-%m-%d)}"
cd "${REPO_ROOT}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre des gels introuvable : ${REGISTRY} (issue #8585)." >&2
  exit 1
fi

python3 - "${REGISTRY}" "${REPO_ROOT}" "${TODAY}" << 'PYEOF'
import json, re, sys
from pathlib import Path

registry_path, repo_root, today = sys.argv[1], Path(sys.argv[2]), sys.argv[3]
registry = json.loads(Path(registry_path).read_text(encoding="utf-8"))
errors = []
def err(msg):
    errors.append(msg)

ISO = re.compile(r"^\d{4}-\d{2}-\d{2}$")

if "freezes" not in registry:
    err("clé obligatoire 'freezes' absente du registre")

freezes = registry.get("freezes") or []
if not freezes:
    err("registre vide : aucun gel déclaré")

ids = []
for f in freezes:
    fid = f.get("id", "<sans id>")
    ids.append(fid)
    for key in ("id", "label", "doc", "starts_on", "ends_on", "gate"):
        if key not in f:
            err(f"gel {fid} : clé obligatoire '{key}' absente")
    for key in ("starts_on", "ends_on"):
        v = f.get(key)
        if not v or not ISO.match(str(v)):
            err(f"gel {fid} : '{key}' absent ou non ISO (AAAA-MM-JJ)")
    s, e = f.get("starts_on"), f.get("ends_on")
    if s and e and ISO.match(str(s)) and ISO.match(str(e)) and s > e:
        err(f"gel {fid} : starts_on ({s}) postérieur à ends_on ({e})")

    # 3. le document de référence doit exister
    doc = f.get("doc")
    if doc and not (repo_root / doc).is_file():
        err(f"gel {fid} : document référencé introuvable — {doc}")

    # 2. le gate
    g = f.get("gate") or {}
    gid = g.get("id", "?")
    for key in ("id", "label", "status", "owner", "updated_at"):
        if key not in g:
            err(f"gel {fid}/gate {gid} : clé obligatoire '{key}' absente")
    st = g.get("status")
    if st not in ("pending", "held", "extended", "lifted"):
        err(f"gel {fid}/gate {gid} : status '{st}' invalide (pending/held/extended/lifted)")
    if g.get("updated_at") and not ISO.match(str(g["updated_at"])):
        err(f"gel {fid}/gate {gid} : 'updated_at' non ISO (AAAA-MM-JJ)")

    # 4. consistance temporelle
    if e and ISO.match(str(e)):
        expired = e < today
        if expired and st == "pending":
            err(
                f"gel {fid} : EXPIRÉ le {e} (aujourd'hui {today}) sans décision de gate — "
                f"le gate '{gid}' est toujours 'pending'. Un gel qui expire en silence laisse "
                f"des interdictions en vigueur que personne n'a validées (#8585)."
            )
        if not expired and st != "pending":
            err(
                f"gel {fid} : gate '{gid}' = '{st}' alors que le gel court encore "
                f"(fin {e}, aujourd'hui {today}) — on ne clôt pas un gate avant l'heure."
            )

if len(ids) != len(set(ids)):
    err("ids de gels dupliqués")

if errors:
    print("::error::Registre des gels incohérent (issue #8585) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Gels cohérents — {len(freezes)} gel(s), gates de sortie tracés, aucune expiration silencieuse.")
PYEOF
