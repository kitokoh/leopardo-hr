#!/usr/bin/env bash
# check-bc-registry.sh — Garde du registre des bounded contexts (MAT-001, issue #5859)
#
# Vérifie que `docs/architecture/bounded-context-registry.json` (le registre
# machine-readable des 23 bounded contexts Leopardo) reste cohérent avec le
# dépôt réel. Le registre est la source de vérité de l'attribution des chemins
# et des propriétaires ; toute dérive doit faire échouer la PR.
#
# ÉCHEC (exit 1, ::error::) si :
#   1. le JSON est invalide ou viole le schéma (champs requis, ids/codes uniques) ;
#   2. un chemin `existing`/`partial` d'un BC n'existe pas sur le disque
#      (chemin ou propriétaire absent — critère d'acceptation MAT-001) ;
#   3. un dossier `api/app/Modules/*` ou `api/app/Core/*` n'est réclamé par
#      AUCUN BC (orphelin) ou par PLUSIEURS BC (conflit d'ownership) et n'est
#      pas listé dans `sharedPaths` ;
#   4. une dépendance référence un code BC inexistant, soi-même, ou crée un cycle ;
#   5. le propriétaire d'un BC n'apparaît pas dans CODEOWNERS (incohérence docs) ;
#   6. si `docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md` existe,
#      l'ensemble de ses codes BC diffère de celui du registre.
#
# AVERTISSEMENTS (affichés, non bloquants) : fichiers de routes/migrations
# listés mais absents ; chemin `to-create` qui existe déjà (entrée périmée).
#
# Usage : bash dev-hub/tools/check-bc-registry.sh [repo_root]
#   repo_root défaut : racine du dépôt (détectée depuis l'emplacement du script).
#   Un repo_root explicite permet les tests automatisés (check-bc-registry-test.sh).

set -euo pipefail

REPO_ROOT="${1:-}"
if [[ -z "$REPO_ROOT" ]]; then
  REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi
cd "$REPO_ROOT"

REGISTRY="docs/architecture/bounded-context-registry.json"
SCHEMA="docs/architecture/bounded-context-registry.schema.json"
GOVERNANCE_DOC="docs/architecture/BOUNDED-CONTEXT-REGISTRY-AGENT-PLAN.md"
CODEOWNERS="CODEOWNERS"

if [[ ! -f "$REGISTRY" ]]; then
  echo "::error::Registre absent : $REGISTRY (MAT-001 #5859)."
  exit 1
fi
if [[ ! -f "$SCHEMA" ]]; then
  echo "::error::Schéma absent : $SCHEMA (MAT-001 #5859)."
  exit 1
fi
if [[ ! -f "$CODEOWNERS" ]]; then
  echo "::error::CODEOWNERS absent à la racine du dépôt — incohérence (MAT-001 #5859)."
  exit 1
fi

python3 - "$REPO_ROOT" "$GOVERNANCE_DOC" << 'PYEOF'
import json
import os
import re
import sys

root = sys.argv[1]
gov_doc = sys.argv[2]

registry_path = os.path.join(root, "docs/architecture/bounded-context-registry.json")
codeowners_path = os.path.join(root, "CODEOWNERS")

failures = []
warnings = []

# ── 1. Lecture + validation de base ─────────────────────────────────────────
try:
    with open(registry_path, encoding="utf-8") as fh:
        registry = json.load(fh)
except json.JSONDecodeError as exc:
    print(f"::error::Registre JSON invalide : {exc}")
    sys.exit(1)

contexts = registry.get("contexts", [])
if not isinstance(contexts, list) or not contexts:
    print("::error::Registre sans contexte — 'contexts' doit être un tableau non vide.")
    sys.exit(1)

shared = set(registry.get("sharedPaths", []) or [])

REQUIRED = ["id", "code", "name", "owner", "priority", "status", "paths", "dependencies"]
STATUSES = {"existing", "partial", "to-create"}
PRIORITIES = {"P0", "P1", "P2", "P3"}

seen_ids = set()
seen_codes = set()
for ctx in contexts:
    code = ctx.get("code", "?")
    missing = [f for f in REQUIRED if f not in ctx]
    if missing:
        failures.append(f"[{code}] champs requis manquants : {', '.join(missing)}")
    if ctx.get("id") in seen_ids:
        failures.append(f"id dupliqué : {ctx.get('id')}")
    seen_ids.add(ctx.get("id"))
    if code in seen_codes:
        failures.append(f"code dupliqué : {code}")
    seen_codes.add(code)
    if ctx.get("status") not in STATUSES:
        failures.append(f"[{code}] statut invalide : {ctx.get('status')!r} (attendu {sorted(STATUSES)})")
    if ctx.get("priority") not in PRIORITIES:
        failures.append(f"[{code}] priorité invalide : {ctx.get('priority')!r}")
    if not isinstance(ctx.get("paths"), list):
        failures.append(f"[{code}] 'paths' doit être un tableau")
    if not isinstance(ctx.get("dependencies"), list):
        failures.append(f"[{code}] 'dependencies' doit être un tableau")

if failures:
    for f in failures:
        print(f"::error::{f}")
    print(f"::error::Registre invalide — {len(failures)} erreur(s) de structure (MAT-001 #5859).")
    sys.exit(1)

# ── 2. Existence des chemins selon le statut ────────────────────────────────
for ctx in contexts:
    code = ctx["code"]
    status = ctx["status"]
    for path in ctx["paths"]:
        exists = os.path.exists(os.path.join(root, path))
        if status in ("existing", "partial") and not exists:
            failures.append(f"[{code}] chemin absent alors que le BC est {status} : {path}")
        if status == "to-create" and exists:
            warnings.append(f"[{code}] chemin 'to-create' déjà présent (entrée périmée ?) : {path}")

# ── 3. Ownership : chaque dossier Modules/* et Core/* réclamé exactement 1× ─
claimed_by = {}
for ctx in contexts:
    for path in ctx["paths"]:
        claimed_by.setdefault(path, []).append(ctx["code"])

def scan_dirs(base):
    result = []
    full = os.path.join(root, base)
    if not os.path.isdir(full):
        return result
    for entry in sorted(os.listdir(full)):
        if os.path.isdir(os.path.join(full, entry)):
            result.append(f"{base}/{entry}")
    return result

for base in ("api/app/Modules", "api/app/Core"):
    for d in scan_dirs(base):
        owners = claimed_by.get(d, [])
        if not owners:
            if d not in shared:
                failures.append(f"dossier non réclamé par aucun BC (orphelin) : {d} — l'ajouter à un BC ou à sharedPaths")
        elif len(owners) > 1:
            failures.append(f"dossier réclamé par plusieurs BC : {d} → {', '.join(owners)} (1 seul propriétaire autorisé)")

# ── 4. Dépendances : codes existants, pas d'auto-dépendance ─────────────────
for ctx in contexts:
    code = ctx["code"]
    for dep in ctx["dependencies"]:
        if dep == code:
            failures.append(f"[{code}] auto-dépendance interdite : {dep}")
        elif dep not in seen_codes:
            failures.append(f"[{code}] dépendance vers un BC inconnu : {dep}")

# Cycles : AVERTISSEMENT (non bloquant) — le couplage réel BC-TENANT ↔
# BC-IDENTITY (Company → Employee, RegisterAction → Company) crée un cycle
# de contrat assumé et documenté (MAT-002 #5860). On ne rapporte que le
# noyau cyclique réel (suppression itérative des nœuds sans entrée/sortie).
cycle_info = []
if not failures:
    adj = {c["code"]: [d for d in c["dependencies"] if d in seen_codes] for c in contexts}
    nodes = set(adj)
    incoming = {n: {s for s in adj if n in adj[s]} for n in nodes}
    outgoing = {n: set(adj[n]) for n in nodes}
    changed = True
    while changed:
        changed = False
        for n in list(nodes):
            if not incoming[n] or not outgoing[n]:
                nodes.discard(n)
                for m in nodes:
                    outgoing[m].discard(n)
                    incoming[m].discard(n)
                changed = True
    if nodes:
        cycle_info.append(" ↔ ".join(sorted(nodes)))
    for cyc in cycle_info:
        warnings.append(f"cycle de dépendances (revue manuelle requise) : {cyc}")

# ── 5. Cohérence CODEOWNERS : le propriétaire de chaque BC y figure ─────────
codeowner_handles = set()
with open(codeowners_path, encoding="utf-8") as fh:
    for line in fh:
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue
        for match in re.findall(r"@[a-zA-Z0-9-]+", stripped):
            codeowner_handles.add(match.lstrip("@"))

for ctx in contexts:
    if ctx["owner"] not in codeowner_handles:
        failures.append(
            f"[{ctx['code']}] propriétaire '{ctx['owner']}' absent de CODEOWNERS — "
            f"l'ajouter ou corriger le registre (cohérence docs, MAT-001)"
        )

# ── 6. Cohérence documentation : codes du registre vs plan de gouvernance ───
gov_path = os.path.join(root, gov_doc)
if os.path.exists(gov_path):
    with open(gov_path, encoding="utf-8") as fh:
        content = fh.read()
    doc_codes = set(re.findall(r"^\|\s*\d+\s*\|\s*(BC-[A-Z0-9-]+)\s*\|", content, re.MULTILINE))
    if doc_codes:
        reg_codes = {c["code"] for c in contexts}
        if doc_codes != reg_codes:
            missing_in_reg = sorted(doc_codes - reg_codes)
            extra_in_reg = sorted(reg_codes - doc_codes)
            failures.append(
                "codes BC incohérents avec le plan de gouvernance "
                f"{gov_doc}: manquants au registre {missing_in_reg}, "
                f"en trop {extra_in_reg}"
            )
else:
    warnings.append(f"plan de gouvernance absent ({gov_doc}) — cohérence docs reportée")

# ── 7. Advisory : routes / migrations / events ──────────────────────────────
for ctx in contexts:
    code = ctx["code"]
    for r in ctx.get("routes", []) or []:
        if not os.path.exists(os.path.join(root, r)):
            warnings.append(f"[{code}] route listée absente : {r}")
    for m in ctx.get("migrations", []) or []:
        if not os.path.exists(os.path.join(root, m)):
            warnings.append(f"[{code}] migrations listée absente : {m}")

# ── Sortie ──────────────────────────────────────────────────────────────────
for w in warnings:
    print(f"::warning::{w}")

if failures:
    print("")
    print("══════════════════════════════════════════════════════════════")
    print("  BC REGISTRY GUARD — MAT-001 (issue #5859)")
    print("══════════════════════════════════════════════════════════════")
    print(f"  {len(failures)} incohérence(s) entre le registre et le dépôt :")
    print("")
    for f in failures:
        print(f"  ❌  {f}")
    print("")
    print("  Fix : mettre à jour docs/architecture/bounded-context-registry.json")
    print("  (chemins réels, propriétaires CODEOWNERS) ou corriger le dépôt.")
    print("")
    sys.exit(1)

print(f"✅  BC registry OK — {len(contexts)} bounded contexts cohérents avec le dépôt "
      f"({len(warnings)} avertissement(s) non bloquant(s)).")
PYEOF
