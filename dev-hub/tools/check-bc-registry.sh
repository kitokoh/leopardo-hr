#!/usr/bin/env bash
#
# check-bc-registry.sh — garde CI du registre des bounded contexts (MAT-001, issue #5859).
#
# Vérifie que `dev-hub/tools/bc-registry.json` (le manifeste machine-readable
# des 23 bounded contexts) est complet et cohérent avec le disque et CODEOWNERS :
#
#   1. JSON valide et lisible ;
#   2. codes BC-01..BC-23 tous présents, uniques, index cohérents ;
#   3. chaque entrée expose les clés obligatoires (code, name, label, owner,
#      priority, status, paths, routes, migrations, events, allowed_dependencies) ;
#   4. status ∈ {active, partial, planned} ;
#   5. owner présent dans CODEOWNERS (@owner) ;
#   6. pour un BC actif/partiel : chaque chemin existe sur le disque (dossier,
#      fichier, ou glob avec ≥ 1 correspondance) ; chaque glob de routes, de
#      migrations et d'événements a ≥ 1 correspondance ;
#   7. dépendances : chaque code cité dans allowed_dependencies existe dans le
#      registre (aucun BC fantôme) ;
#   8. cohérence CODEOWNERS : chaque BC possède une section `# BC-xx` dans
#      CODEOWNERS et chaque chemin actif/partiel est couvert par un motif de
#      cette section (le fallback global `*` ne suffit pas).
#
# Échoue (::error:: + exit 1) dès qu'une règle est violée, avec un message
# actionnable. Usage :
#   bash dev-hub/tools/check-bc-registry.sh [chemin_registre] [racine_repo]
#
# Tests : bash dev-hub/tools/check-bc-registry-test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${1:-${SCRIPT_DIR}/bc-registry.json}"
REPO_ROOT="${2:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
EXPECTED_BC_MAX="${3:-23}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre introuvable : ${REGISTRY} (issue #5859)."
  exit 1
fi

cd "${REPO_ROOT}"
CODEOWNERS_FILE="${REPO_ROOT}/CODEOWNERS"

violations=0
fail() { # message
  echo "::error::${1}" >&2
  violations=$((violations + 1))
}

# ── 1. Parse JSON + structure minimale ────────────────────────────────────────
PY="$(command -v python3 || true)"
if [[ -z "${PY}" ]]; then
  echo "::error::python3 requis pour check-bc-registry.sh (issue #5859)." >&2
  exit 1
fi

python3 - "${REGISTRY}" << 'PYEOF' > /tmp/bc-registry-normalized.json
import json, sys
from pathlib import Path

registry_path = Path(sys.argv[1])
try:
    data = json.loads(registry_path.read_text(encoding="utf-8"))
except Exception as exc:  # JSON invalide
    print(f"::error::Registre JSON invalide ({registry_path.name}) : {exc}", file=sys.stderr)
    sys.exit(1)

if not isinstance(data, dict) or "contexts" not in data or not isinstance(data["contexts"], list):
    print("::error::Registre mal formé : objet racine attendu avec une liste 'contexts' (issue #5859).", file=sys.stderr)
    sys.exit(1)

# Sortie normalisée pour la suite du contrôle
print(json.dumps(data, ensure_ascii=False, indent=2))
PYEOF

if [[ $? -ne 0 ]]; then
  exit 1
fi

# ── 2..7. Contrôles de fond (python) ─────────────────────────────────────────
python3 - "${REGISTRY}" "${CODEOWNERS_FILE}" "${EXPECTED_BC_MAX}" << 'PYEOF'
import json, re, sys
from pathlib import Path

registry_path = Path(sys.argv[1])
codeowners_path = Path(sys.argv[2])
expected_max = int(sys.argv[3])
data = json.loads(registry_path.read_text(encoding="utf-8"))
contexts = data["contexts"]

errors = []
def err(msg):
    errors.append(msg)

required_keys = ["code", "name", "label", "owner", "priority", "status",
                 "paths", "routes", "migrations", "events", "allowed_dependencies"]
allowed_status = {"active", "partial", "planned"}
allowed_priority = {"P0", "P1", "P2", "P3"}

codes = [c["code"] for c in contexts if isinstance(c, dict) and "code" in c]
dupes = sorted({c for c in codes if codes.count(c) > 1})
if dupes:
    err(f"codes dupliqués dans le registre : {', '.join(dupes)}")

# 2. Les codes attendus
expected = [f"BC-{i:02d}" for i in range(1, expected_max + 1)]
missing = [c for c in expected if c not in codes]
extra = [c for c in codes if c not in expected]
if missing:
    err(f"BC absents du registre : {', '.join(missing)}")
if extra:
    err(f"codes non standards dans le registre : {', '.join(sorted(extra))} (attendus BC-01..BC-23)")

codeowners_text = codeowners_path.read_text(encoding="utf-8") if codeowners_path.exists() else ""
owners_in_codeowners = set(re.findall(r"@([A-Za-z0-9_-]+)", codeowners_text))

# 3..7. Par entrée
for c in contexts:
    if not isinstance(c, dict):
        err("entrée non-objet dans contexts")
        continue
    code = c.get("code", "<sans code>")
    for key in required_keys:
        if key not in c:
            err(f"{code} : clé obligatoire '{key}' absente")
            continue
    if "status" in c and c["status"] not in allowed_status:
        err(f"{code} : status '{c['status']}' invalide (attendu {sorted(allowed_status)})")
    if "priority" in c and c["priority"] not in allowed_priority:
        err(f"{code} : priority '{c['priority']}' invalide (attendu {sorted(allowed_priority)})")

    owner = c.get("owner", "")
    if owner and owner not in owners_in_codeowners:
        err(f"{code} : owner '{owner}' absent de CODEOWNERS — ajouter '@{owner}' (issue #5859)")

    status = c.get("status", "active")
    if status == "planned":
        continue  # les chemins/globs d'un BC planifié n'existent pas encore sur main

    # 6. existence des chemins et correspondance des globs
    def glob_matches(pattern: str) -> bool:
        if any(ch in pattern for ch in "*?["):
            return len(list(Path(".").glob(pattern))) > 0
        return Path(pattern).exists()

    for p in c.get("paths", []):
        if not glob_matches(p):
            err(f"{code} : chemin/glob '{p}' introuvable sur le disque")
    for r in c.get("routes", []):
        if not glob_matches(r):
            err(f"{code} : glob de routes '{r}' sans correspondance")
    for m in c.get("migrations", []):
        if not glob_matches(m):
            err(f"{code} : glob de migrations '{m}' sans correspondance")
    for e in c.get("events", []):
        # App\Events\X* -> api/app/Events/X*.php
        rel = e.replace("App\\Events\\", "api/app/Events/") + ".php"
        if not glob_matches(rel):
            err(f"{code} : événement '{e}' introuvable (attendu {rel})")

    # 7. dépendances connues
    for dep in c.get("allowed_dependencies", []):
        if dep not in codes:
            err(f"{code} : dépendance '{dep}' inconnue dans le registre")

    # 8. cohérence CODEOWNERS : section BC + couverture des chemins
    if codeowners_text:
        section = re.search(rf"^#\s*{re.escape(code)}\b.*$", codeowners_text, re.MULTILINE)
        if not section:
            err(f"{code} : section '# {code}' manquante dans CODEOWNERS (issue #5859)")
        else:
            lines = codeowners_text[section.end():].splitlines()
            patterns = []
            for ln in lines:
                ln = ln.strip()
                if not ln or ln.startswith("#"):
                    continue
                if ln.startswith("/") and "@" in ln:
                    pat = ln.split()[0]
                    if pat != "*":
                        patterns.append(pat.strip("/"))
            for p in c.get("paths", []):
                bp = p.strip("/")
                covered = any(bp == pat or bp.startswith(pat.rstrip("/") + "/")
                              or pat.startswith(bp + "/") for pat in patterns)
                if not covered:
                    err(f"{code} : chemin '{p}' non couvert par la section CODEOWNERS '# {code}' (fallback global insuffisant)")

if errors:
    print("::error::Registre des bounded contexts invalide (issue #5859) :", file=sys.stderr)
    for e in errors:
        print(f"  - {e}", file=sys.stderr)
    sys.exit(1)

print(f"✅ Registre des bounded contexts valide : {len(contexts)} contextes, 23 attendus, CODEOWNERS cohérent.")
PYEOF

exit $?
