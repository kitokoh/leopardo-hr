#!/usr/bin/env bash
#
# check-golden-journeys.sh — garde du registre des golden journeys (MAT-013, issue #5871).
#
# Vérifie que `dev-hub/tools/golden-journeys.json` est cohérent avec le dépôt :
#   1. JSON valide + champs obligatoires (solutions, journeys) ;
#   2. chaque journey a id unique, name, solution connue, acceptance, steps ≥ 1 ;
#   3. chaque étape d'une solution ACTIVE référence une route API qui EXISTE
#      dans api/routes/** (résolution des préfixes de groupes, paramètres
#      {x} ignorés) — les solutions planifiées documentent leur cible ;
#   4. chaque solution active possède au moins un journey.
#
# Usage : bash dev-hub/tools/check-golden-journeys.sh [api_dir] [registry]
# Tests : bash dev-hub/tools/tests/check-golden-journeys.test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${2:-${SCRIPT_DIR}/golden-journeys.json}"
API_DIR="${1:-api}"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre golden journeys introuvable : ${REGISTRY} (issue #5871)." >&2
  exit 1
fi

python3 - "${REGISTRY}" "${API_DIR}" << 'PYEOF'
import json, re, sys
from pathlib import Path

registry = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
api_dir = Path(sys.argv[2])

errors = []
def err(msg):
    errors.append(msg)

for key in ("solutions", "journeys"):
    if key not in registry:
        err(f"clé obligatoire '{key}' absente du registre")

solutions = registry.get("solutions") or {}
journeys = registry.get("journeys") or []

# ── Routes réelles : résolution des préfixes de groupes (comme le garde
#    check-tenant-platform-separation.sh) ─────────────────────────────────────
RE_MIDDLEWARE = re.compile(r"(?:->|::)middleware\(([^)]*)\)")
RE_PREFIX = re.compile(r"(?:->|::)prefix\(\s*'([^']+)'\s*\)")
RE_ROUTE = re.compile(r"Route::(get|post|put|patch|delete|options|any)\(\s*'([^']+)'")

def logical_lines(lines):
    merged = []
    i = 0
    while i < len(lines):
        cur = lines[i]
        j = i + 1
        while j < len(lines):
            nxt = lines[j].strip()
            if not nxt or nxt.startswith("//") or nxt.startswith("#"):
                j += 1
                continue
            s = cur.strip()
            if s.startswith("//") or s.startswith("#"):
                break
            if nxt.startswith("->") or s.endswith("->") or s.endswith(","):
                cur = cur + " " + lines[j]
                j += 1
            else:
                break
        merged.append((i + 1, cur))
        i = j
    return merged

def collect_routes(api_dir: Path) -> set:
    """(method, uri_normalisée) — uri sans /api/v1, params remplacés par {}."""
    routes = set()
    for route_file in sorted(api_dir.joinpath("routes").rglob("*.php")):
        stack = [{"prefix_parts": []}]
        depth = 0
        for lineno, raw in logical_lines(route_file.read_text(encoding="utf-8").splitlines()):
            stmt = raw.strip()
            if not stmt or stmt.startswith("//") or stmt.startswith("#"):
                continue
            prefixes = [m.group(1) for m in RE_PREFIX.finditer(stmt)]
            rrs = RE_ROUTE.findall(stmt)
            opens = stmt.count("{") - stmt.count("}")
            ctx = stack[depth]
            if rrs:
                eff_parts = ctx["prefix_parts"] + prefixes
                eff = "/".join(eff_parts) if eff_parts else ""
                for method, uri in rrs:
                    full = (eff + "/" + uri.lstrip("/")) if eff else uri.lstrip("/")
                    norm = re.sub(r"\{[^}]*\}", "{}", full)
                    norm = re.sub(r"^(api/)?v1/", "", norm)
                    norm = norm.rstrip("/")
                    routes.add((method.upper(), norm))
            if opens > 0:
                new_ctx = {"prefix_parts": ctx["prefix_parts"] + prefixes}
                depth += 1
                if len(stack) <= depth:
                    stack.append(new_ctx)
                else:
                    stack[depth] = new_ctx
            if opens < 0:
                depth += opens
                if depth < 0:
                    depth = 0
                stack = stack[: depth + 1]
    return routes

real_routes = collect_routes(api_dir)

def norm_route(route: str) -> str:
    n = re.sub(r"^/", "", route)
    n = re.sub(r"\{[^}]*\}", "{}", n)
    n = re.sub(r"^(api/)?v1/", "", n)
    return n.rstrip("/")

# ── Structure des journeys ───────────────────────────────────────────────────
ids = []
for j in journeys:
    jid = j.get("id", "<sans id>")
    ids.append(jid)
    for key in ("name", "solution", "acceptance", "steps"):
        if key not in j:
            err(f"{jid} : clé obligatoire '{key}' absente")
    sol = j.get("solution")
    if sol not in solutions:
        err(f"{jid} : solution '{sol}' inconnue")
    steps = j.get("steps") or []
    if not steps:
        err(f"{jid} : aucun step")
    is_planned = (solutions.get(sol) or {}).get("status") == "planned"
    for s in steps:
        method = (s.get("method") or "GET").upper()
        route = s.get("route") or ""
        if not route.startswith("/api/"):
            err(f"{jid} : route API invalide '{route}' (doit commencer par /api/)")
            continue
        if is_planned:
            continue  # solution planifiée : la cible est documentée, pas encore routée
        key = (method, norm_route(route))
        if key not in real_routes:
            # tolérance : méthode différente sur la même URI ?
            if norm_route(route) not in {u for m, u in real_routes if m == method}:
                err(f"{jid} : route '{method} {route}' introuvable dans api/routes/** (endpoint supprimé ou déplacé ?)")

# Chaque solution active a ≥ 1 journey
for sol, meta in solutions.items():
    if meta.get("status") == "active" and not any(j.get("solution") == sol for j in journeys):
        err(f"solution active '{sol}' sans golden journey")

if len(ids) != len(set(ids)):
    err("ids de journeys dupliqués")

if errors:
    print("::error::Registre golden journeys incohérent (issue #5871, MAT-013) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Golden journeys cohérents — {len(journeys)} parcours, {len(real_routes)} routes résolues, cibles vérifiées.")
PYEOF
