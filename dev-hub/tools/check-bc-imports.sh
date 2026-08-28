#!/usr/bin/env bash
# check-bc-imports.sh — Garde des imports inter-bounded-contexts (MAT-002, issue #5860)
#
# Interdit les imports directs entre bounded contexts en dehors des contrats
# autorisés, où « contrat » = la liste `dependencies` versionnée dans
# `docs/architecture/bounded-context-registry.json` (MAT-001, #5859).
#
# Surfaces contrôlées (complémentaires de la garde d'isolation modules #5584,
# qui couvre déjà Modules→Modules et Core→Modules) :
#   - Modules/X → Core/Y           (import d'un module vers le noyau)
#   - Core/X → Core/Y  (X≠Y)       (import entre contextes du noyau)
#   - AI/*, Jobs/*, Contracts/* → tout BC
#
# Règle : pour chaque fichier PHP, on extrait les `use App\...\...;` qui
# ciblent un bounded context ; si le BC source n'a pas déclaré le BC cible
# dans ses `dependencies`, c'est une violation avec un message actionnable.
# Les imports vers `App\Shared\*`, `App\Events\*`, `App\Policies\*` et les
# namespaces hors registre (Http, Exceptions, Support, Rules, Providers,
# Console…) sont hors périmètre (partagés ou non-BC).
#
# La matrice actuelle des imports a été « versionnée » dans le registre (v1.1,
# #5860) : elle gèle le couplage existant et bloque tout NOUVEL import hors
# contrat. Pour autoriser un import : déclarer le BC cible dans les
# `dependencies` du BC source (registre) — jamais contourner la garde.
#
# Usage : bash dev-hub/tools/check-bc-imports.sh [repo_root]
#   repo_root défaut : racine du dépôt. Un repo_root explicite permet les
#   tests automatisés (check-bc-imports-test.sh).

set -euo pipefail

REPO_ROOT="${1:-}"
if [[ -z "$REPO_ROOT" ]]; then
  REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "$REPO_ROOT"

if [[ ! -f "docs/architecture/bounded-context-registry.json" ]]; then
  echo "::error::Registre des bounded contexts absent (docs/architecture/bounded-context-registry.json)."
  echo "::error::MAT-001 (#5859) doit être mergé avant cette garde."
  exit 1
fi

python3 - "$REPO_ROOT" << 'PYEOF'
import json
import os
import re
import sys
from pathlib import Path

root = sys.argv[1]
registry = json.load(open(os.path.join(root, "docs/architecture/bounded-context-registry.json"), encoding="utf-8"))

contexts = registry["contexts"]
codes = {c["code"] for c in contexts}
deps_of = {c["code"]: set(c["dependencies"]) for c in contexts}
shared = set(registry.get("sharedPaths", []) or [])

# Chemins → BC (préfixe le plus long gagne)
path_rules = []
for ctx in contexts:
    for p in ctx["paths"]:
        if p.endswith(".php"):
            continue
        path_rules.append((p.rstrip("/") + "/", ctx["code"]))
path_rules.sort(key=lambda r: -len(r[0]))

def bc_for_path(rel):
    for prefix, code in path_rules:
        if rel.startswith(prefix):
            return code
    return None

# Namespaces → BC (préfixe le plus long gagne)
ns_rules = []
for ctx in contexts:
    for p in ctx["paths"]:
        if p.startswith("api/app/Modules/"):
            ns_rules.append(("App\\Modules\\" + p.split("/")[3] + "\\", ctx["code"]))
        elif p.startswith("api/app/Core/"):
            ns_rules.append(("App\\Core\\" + p.split("/")[3] + "\\", ctx["code"]))
ns_rules.append(("App\\AI\\", "BC-AI"))
ns_rules.append(("App\\Jobs\\", "BC-INTEGRATION"))
ns_rules.append(("App\\Contracts\\Queue\\", "BC-INTEGRATION"))
ns_rules.append(("App\\Contracts\\Communication\\", "BC-COMMS"))
ns_rules.sort(key=lambda r: -len(r[0]))

def bc_for_ns(imported):
    for prefix, code in ns_rules:
        if imported.startswith(prefix):
            return code
    return None

# Surfaces laissées à la garde #5584 (check-module-isolation) : on ne les
# re-traite pas ici pour éviter les doublons de signal.
def covered_by_5584(rel, imported):
    if imported.startswith("App\\Modules\\"):
        return True  # toute cible Modules est couverte (#5584)
    return False

use_re = re.compile(r"^use\s+(App\\[A-Za-z0-9_\\]+);", re.MULTILINE)

violations = []
scanned = 0
for f in sorted(Path(root, "api/app").rglob("*.php")):
    rel = str(f.relative_to(root))
    src = bc_for_path(rel)
    if src is None or src == "SHARED":
        continue
    if rel.startswith("api/app/Policies/"):
        continue  # conteneur partagé de policies métier (voir registre sharedPaths)
    try:
        content = f.read_text(encoding="utf-8", errors="replace")
    except OSError:
        continue
    scanned += 1
    for m in use_re.findall(content):
        imported = m
        tgt = bc_for_ns(imported)
        if tgt is None or tgt == src:
            continue
        if tgt == "SHARED" or tgt not in codes:
            continue
        if covered_by_5584(rel, imported):
            continue
        if tgt not in deps_of.get(src, set()):
            violations.append((rel, src, tgt, imported))

if violations:
    print("")
    print("══════════════════════════════════════════════════════════════")
    print("  BC IMPORT GUARD — MAT-002 (issue #5860)")
    print("══════════════════════════════════════════════════════════════")
    print(f"  {len(violations)} import(s) direct(s) hors contrat autorisé :")
    print("")
    for rel, src, tgt, imp in sorted(violations):
        print(f"  ❌  [{src} → {tgt}]")
        print(f"      {rel}")
        print(f"      {imp}")
    print("")
    print("  Contrat : le BC cible doit figurer dans les `dependencies` du BC")
    print("  source dans docs/architecture/bounded-context-registry.json (MAT-001).")
    print("  Actions :")
    print("    1. si l'import est légitime → déclarer la dépendance dans le")
    print("       registre (la PR documente alors le contrat) ;")
    print("    2. sinon → remplacer l'import direct par un contrat/événement")
    print("       partagé (pattern #5584) et retirer l'import.")
    print("  Ne jamais élargir le périmètre de la garde pour masquer un import.")
    print("")
    sys.exit(1)

print(f"✅  BC imports OK — {scanned} fichiers analysés, aucun import hors contrat "
      f"(matrice de {len(contexts)} bounded contexts).")
PYEOF
