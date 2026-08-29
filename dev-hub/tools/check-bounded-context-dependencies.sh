#!/usr/bin/env bash
# check-bounded-context-dependencies.sh — Garde CI « dépendances inter-contextes »
# (MAT-002, issue #5860)
#
# Interdit les imports directs entre bounded contexts hors des dépendances
# autorisées et versionnées (dev-hub/governance/bounded-context-dependencies.json).
#
# Principe : deny-by-default. Pour chaque fichier PHP sous api/app/Modules,
# api/app/Core, api/app/AI (et api/app/Solutions s'il existe), on résout :
#   - le BC du fichier (via le registre des bounded contexts, MAT-001/#5859) ;
#   - le BC de chaque import `use App\...` (préfixe de namespace mappé au registre).
# Toute arête (from → to) avec from ≠ to absente de la matrice → échec avec un
# message actionnable (fichier, import, arête, remède).
#
# Les imports vers l'infrastructure partagée (App\Shared, App\Models,
# App\Http, App\Core\Http, ...) ne sont pas des arêtes inter-contextes et sont
# ignorés. La matrice peut aussi déclarer des arêtes `baseline: true`
# (état actuel gelé) ou `baseline: false` (nouveau contrat explicite).
#
# Usage : dev-hub/tools/check-bounded-context-dependencies.sh [repo_root]
# Prérequis : bash, jq, grep, find, python3 (pour la résolution de namespace).
# Exit codes : 0 = OK, 1 = violation.

set -uo pipefail

ROOT="${1:-.}"
REGISTRY="${ROOT}/dev-hub/governance/bounded-context-registry.json"
MATRIX="${ROOT}/dev-hub/governance/bounded-context-dependencies.json"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre des bounded contexts introuvable : ${REGISTRY} (MAT-001/#5859 requis avant MAT-002/#5860)." >&2
  exit 1
fi
if [[ ! -f "${MATRIX}" ]]; then
  echo "::error::Matrice de dépendances introuvable : ${MATRIX} (MAT-002/#5860)." >&2
  exit 1
fi

ERRORS=0
fail() {
  ERRORS=$((ERRORS + 1))
  echo "::error::${1}" >&2
}

# ── 0. Validité de la matrice ────────────────────────────────────────────────
if ! jq -e . "${MATRIX}" >/dev/null 2>&1; then
  echo "::error::Matrice JSON invalide : ${MATRIX}" >&2
  exit 1
fi
CODES=$(jq -r '.bounded_contexts[].code' "${REGISTRY}")
while IFS=$'\t' read -r f t; do
  grep -qx "${f}" <<<"${CODES}" || fail "Matrice : BC source inconnu '${f}' (arête ${f} → ${t})."
  grep -qx "${t}" <<<"${CODES}" || fail "Matrice : BC cible inconnu '${t}' (arête ${f} → ${t})."
done < <(jq -r '.edges[] | [.from, .to] | @tsv' "${MATRIX}")

# ── 1. Résolution BC fichier / BC import via python3 ─────────────────────────
VIOLATIONS=$(REGISTRY="${REGISTRY}" MATRIX="${MATRIX}" ROOT="${ROOT}" python3 - <<'PYEOF'
import json, os, re, sys

root = os.environ["ROOT"]
reg = json.load(open(os.environ["REGISTRY"]))
matrix = json.load(open(os.environ["MATRIX"]))

bc_by_path = {}
for bc in reg["bounded_contexts"]:
    for p in bc["paths"]:
        bc_by_path[p["path"]] = bc["code"]

prefix_map = []
for path, code in bc_by_path.items():
    if path.startswith("api/app/Modules/"):
        prefix_map.append(("App\\Modules\\" + path[len("api/app/Modules/"):] + "\\", code))
    elif path.startswith("api/app/Core/"):
        prefix_map.append(("App\\Core\\" + path[len("api/app/Core/"):] + "\\", code))
    elif path == "api/app/AI":
        prefix_map.append(("App\\AI\\", code))
    elif path.startswith("api/app/Contracts/"):
        prefix_map.append(("App\\Contracts\\" + path[len("api/app/Contracts/"):] + "\\", code))
    elif path == "api/app/Policies":
        prefix_map.append(("App\\Policies\\", code))
    elif path == "api/app/Jobs":
        prefix_map.append(("App\\Jobs\\", code))

allowed = set()
for e in matrix["edges"]:
    allowed.add((e["from"], e["to"]))

def path_to_bc(rel):
    for path, code in bc_by_path.items():
        if rel.startswith(path + "/"):
            return code
    return None

def import_to_bc(ns):
    for prefix, code in prefix_map:
        if ns == prefix.rstrip("\\") or ns.startswith(prefix):
            return code
    return None

use_re = re.compile(r"\buse\s+(App\\[A-Za-z0-9_\\]+)\b")
out = []
roots = ["api/app/Modules", "api/app/Core", "api/app/AI"]
if os.path.isdir(os.path.join(root, "api/app/Solutions")):
    roots.append("api/app/Solutions")
for r in roots:
    for dirpath, dirnames, filenames in os.walk(os.path.join(root, r)):
        for fn in filenames:
            if not fn.endswith(".php"):
                continue
            rel = os.path.relpath(os.path.join(dirpath, fn), root).replace(os.sep, "/")
            from_bc = path_to_bc(rel)
            if not from_bc:
                continue
            text = open(os.path.join(root, rel), encoding="utf-8", errors="replace").read()
            for m in use_re.finditer(text):
                ns = m.group(1).strip().rstrip("\\")
                to_bc = import_to_bc(ns)
                if to_bc and to_bc != from_bc and (from_bc, to_bc) not in allowed:
                    out.append(f"{rel} : import {ns} — arête {from_bc} → {to_bc} non déclarée dans bounded-context-dependencies.json")
print("\n".join(out))
PYEOF
)

# ── 2. Rapport ───────────────────────────────────────────────────────────────
if [[ -n "${VIOLATIONS}" ]]; then
  while IFS= read -r line; do
    [[ -n "${line}" ]] && fail "${line} → ajoute l'arête dans la matrice (avec justification) ou utilise un contrat partagé (MAT-002/#5860)."
  done <<<"${VIOLATIONS}"
fi

if [[ "${ERRORS}" -gt 0 ]]; then
  echo "::error::Dépendances inter-contextes (MAT-002/#5860) : ${ERRORS} violation(s)." >&2
  exit 1
fi

echo "✓ Dépendances inter-contextes conformes à la matrice versionnée (MAT-002/#5860) : $(jq '.edges | length' "${MATRIX}") arêtes autorisées, 0 import hors contrat."
exit 0
