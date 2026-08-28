#!/usr/bin/env bash
#
# check-bc-dependencies.sh — garde d'architecture inter-contextes (MAT-002, issue #5860).
#
# Interdit les imports PHP directs entre bounded contexts hors des contrats
# autorisés. Les dépendances autorisées sont VERSIONNÉES à deux endroits :
#
#   1. `dev-hub/tools/bc-registry.json` → champ `allowed_dependencies` de chaque
#      BC (contrats autorisés, matrice source de vérité) ;
#   2. `dev-hub/tools/bc-dependencies-allowlist.txt` → dette EXISTANTE gelée
#      (aucune nouvelle ligne autorisée sans discussion architecturale).
#
# Règles :
#   - chaque fichier PHP sous `api/app/**` est affecté à son BC par le plus long
#     préfixe de chemin du registre ;
#   - les imports de noms de famille transverses (App\Events, App\Shared,
#     App\Contracts, App\Enums, App\Exceptions, App\Support, App\Attributes,
#     App\Traits, App\Notifications, App\Mail, helpers) sont des CONTRATS
#     partagés — toujours autorisés (mécanisme d'interopérabilité sanctionné) ;
#   - tout import vers un autre BC non listé dans `allowed_dependencies` et
#     absent de l'allowlist → échec avec un message actionnable (fichier, BC
#     source, BC cible, dépendances autorisées).
#
# Usage : bash dev-hub/tools/check-bc-dependencies.sh [api_dir]
# Tests : bash dev-hub/tools/check-bc-dependencies-test.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REGISTRY="${2:-${SCRIPT_DIR}/bc-registry.json}"
ALLOWLIST="${SCRIPT_DIR}/bc-dependencies-allowlist.txt"
API_DIR="${1:-api}"

if [[ ! -f "${REGISTRY}" ]]; then
  echo "::error::Registre introuvable : ${REGISTRY} — MAT-001 (#5859) doit être mergé avant MAT-002 (#5860)." >&2
  exit 1
fi
if [[ ! -f "${ALLOWLIST}" ]]; then
  echo "::error::Allowlist introuvable : ${ALLOWLIST} (issue #5860)." >&2
  exit 1
fi

python3 - "${API_DIR}" "${REGISTRY}" << 'PYEOF'
import json, re, sys
from pathlib import Path

api_dir = Path(sys.argv[1])
registry = json.loads(Path(sys.argv[2]).read_text(encoding="utf-8"))

# ── Carte des BC : préfixes de chemins (les plus longs d'abord) ──────────────
bc_by_prefix = []  # (prefix, code)
for ctx in registry["contexts"]:
    code = ctx["code"]
    for p in ctx.get("paths", []):
        if not p.startswith("api/app/"):
            continue
        if any(ch in p for ch in "*?["):
            continue  # glob : non résolu ici (chemins actifs déclarés en dossiers)
        bc_by_prefix.append((p.rstrip("/") + "/", code))
bc_by_prefix.sort(key=lambda t: len(t[0]), reverse=True)

def bc_for_path(rel: str) -> str | None:
    for prefix, code in bc_by_prefix:
        if rel.startswith(prefix):
            return code
    return None  # hors périmètre BC (partagé/infra)

# ── Noms de famille transverses = contrats partagés ──────────────────────────
CONTRACT_PREFIXES = (
    "App\\Events\\", "App\\Shared\\", "App\\Contracts\\", "App\\Enums\\",
    "App\\Exceptions\\", "App\\Support\\", "App\\Attributes\\", "App\\Traits\\",
    "App\\Notifications\\", "App\\Mail\\",
)
CONTRACT_LEAF = ("\\helpers",)

allowed_deps = {c["code"]: set(c.get("allowed_dependencies", [])) for c in registry["contexts"]}

violations = []  # (source_bc, target_bc, file, import_stmt)

for php_file in sorted(api_dir.joinpath("app").rglob("*.php")):
    rel = "api/" + str(php_file.relative_to(api_dir))
    src_bc = bc_for_path(rel)
    if src_bc is None:
        continue  # fichiers partagés non affectés à un BC : non contrôlés en sortie
    try:
        content = php_file.read_text(encoding="utf-8")
    except Exception:
        continue
    for line in content.splitlines():
        line = line.strip()
        if not line.startswith("use App\\"):
            continue
        m = re.match(r"use (App\\[A-Za-z0-9_\\]+);", line)
        if not m:
            continue
        ns = m.group(1)
        # Noms de famille transverses = contrats partagés, toujours autorisés
        if any(ns.startswith(cp) for cp in CONTRACT_PREFIXES):
            continue
        # chemin approximé : App\X\Y\Z -> api/app/X/Y/Z
        rel_target = "api/app/" + ns[4:].replace("\\", "/")
        tgt_bc = bc_for_path(rel_target)
        if tgt_bc is None or tgt_bc == src_bc:
            continue
        if tgt_bc in allowed_deps.get(src_bc, set()):
            continue
        violations.append((src_bc, tgt_bc, rel, line))

# ── Comparaison à l'allowlist (dette gelée, par paire BC) ────────────────────
allowlist_path = Path(sys.argv[2]).parent / "bc-dependencies-allowlist.txt"
allowed = set()
if allowlist_path.exists():
    for ln in allowlist_path.read_text(encoding="utf-8").splitlines():
        ln = ln.strip()
        if ln and not ln.startswith("#"):
            allowed.add(ln)

current_pairs = {f"{s} -> {t}" for s, t, f, i in violations}
new_pairs = sorted(current_pairs - allowed)

if not new_pairs:
    total = len(current_pairs)
    print(f"✅ Aucun nouvel import inter-contextes (MAT-002) — {total} paires BC actuelles toutes couvertes par les contrats/allowlist.")
    sys.exit(0)

print("::error::Imports inter-contextes non autorisés détectés (issue #5860, MAT-002) :", file=sys.stderr)
for pair in new_pairs:
    src, tgt = pair.split(" -> ")
    print(f"  ❌ {src} -> {tgt} (autorisé : {sorted(allowed_deps.get(src, set())) or 'aucun — contrats partagés uniquement'})", file=sys.stderr)
    for s, t, rel, imp in violations:
        if f"{s} -> {t}" == pair:
            print(f"       {rel} :: {imp}", file=sys.stderr)
print("", file=sys.stderr)
print("Chaque BC ne peut importer que les BC listés dans son 'allowed_dependencies'", file=sys.stderr)
print("(dev-hub/tools/bc-registry.json) ou les contrats partagés (App\\Events, App\\Shared,", file=sys.stderr)
print("App\\Contracts, ...). Corriger le code : événements partagés, contrats (interfaces),", file=sys.stderr)
print("injection de dépendance. NE PAS agrandir bc-dependencies-allowlist.txt sans", file=sys.stderr)
print("discussion architecturale documentée.", file=sys.stderr)
sys.exit(1)
PYEOF
