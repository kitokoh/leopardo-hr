#!/usr/bin/env bash
#
# #7961 — Cliquet anti-croissance BRANCHE vs main + budget de réduction des
# baselines PHPStan.
#
# Les gardes existantes sont toutes diff-scopées (base_sha → head_sha d'une
# PR) : #5448 « 0 nouvelle entrée » et cliquet total #7655
# (check-phpstan-baseline-debt.sh guard), PA2-ARCH-005 par module touché
# (check-phpstan-baseline-delta.sh). Cette garde-ci compare l'état COMPLET de
# la branche courante (working tree) au dernier origin/main :
#
#   1. Somme des `count:` de chaque fichier baseline sur la branche courante
#      ET sur origin/main (parsing texte pur — aucun PHP requis).
#   2. ÉCHOUE si le total global croît vs main (ratchet anti-croissance,
#      indépendant de la fenêtre de commits d'une PR — couvre aussi les
#      pushes directs, les merges de branches anciennes, etc.).
#   3. Affiche les totaux, le delta, le top 10 des modules/fichiers les plus
#      chargés (priorisation), et l'état vs le budget de réduction daté
#      (dev-hub/governance/phpstan-baseline-budget.json).
#
# Usage:
#   dev-hub/tools/check-phpstan-baseline-budget.sh [base_ref] [api_dir]
#     base_ref : défaut origin/main
#     api_dir  : défaut api
#
# Codes de sortie : 0 = total stable ou en baisse ; 1 = total en hausse vs
# base_ref ; 2 = usage / environnement invalide.

set -euo pipefail

BASE_REF="${1:-origin/main}"
API_DIR="${2:-api}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUDGET_FILE="$SCRIPT_DIR/../governance/phpstan-baseline-budget.json"

# Même famille de fichiers que #5448 / PA2-ARCH-005.
BASELINE_FILES=(
  "phpstan-strict-baseline.neon"
  "phpstan-baseline.neon"
  "phpstan-modules-baseline.neon"
)

if ! git rev-parse --verify --quiet "${BASE_REF}^{commit}" >/dev/null; then
  # Checkout CI shallow / branche locale sans main : tenter le fetch.
  git fetch --no-tags origin main 2>/dev/null || true
fi
if ! git rev-parse --verify --quiet "${BASE_REF}^{commit}" >/dev/null; then
  echo "❌ #7961 : ref de base introuvable (${BASE_REF}) — impossible de comparer. Faites 'git fetch origin main'." >&2
  exit 2
fi

BASE_SHA="$(git rev-parse "${BASE_REF}^{commit}")"

# Tout le calcul en python3 (texte pur) : lit les baselines du working tree
# pour la branche courante, et via `git show` pour la base.
python3 - "$API_DIR" "$BASE_SHA" "$BUDGET_FILE" "${BASELINE_FILES[@]}" <<'PYEOF'
import datetime
import json
import os
import re
import subprocess
import sys

api_dir, base_sha, budget_file = sys.argv[1], sys.argv[2], sys.argv[3]
baseline_files = sys.argv[4:]

ENTRY_RE = re.compile(r"count:\s*(\d+)\s*\n\s*path:\s*(\S+)")


def parse(content: str):
    """[(count, path)] depuis un fichier baseline neon (texte pur)."""
    return [(int(c), p) for c, p in ENTRY_RE.findall(content or "")]


def module_of(path: str) -> str:
    m = re.match(r"^app/(Core|Modules)/([^/]+)/", path)
    if m:
        return f"app/{m.group(1)}/{m.group(2)}"
    if path.startswith("app/Shared/") or path == "app/Shared":
        return "app/Shared"
    if path.startswith("tests/"):
        parts = path.split("/")
        return f"tests/{parts[1]}" if len(parts) > 1 else "tests"
    if path.startswith("app/"):
        parts = path.split("/")
        return f"app/{parts[1]}" if len(parts) > 1 else "app"
    return path.split("/")[0] if "/" in path else "(hors app)"


def read_head(bf: str) -> str:
    fp = os.path.join(api_dir, bf)
    if not os.path.isfile(fp):
        return ""
    with open(fp, encoding="utf-8") as fh:
        return fh.read()


def read_base(bf: str) -> str:
    r = subprocess.run(
        ["git", "show", f"{base_sha}:{api_dir}/{bf}"],
        capture_output=True, text=True,
    )
    return r.stdout if r.returncode == 0 else ""


head_totals, base_totals = {}, {}
head_mod, head_file = {}, {}
rows = []

for bf in baseline_files:
    h_entries = parse(read_head(bf))
    b_entries = parse(read_base(bf))
    h_total = sum(c for c, _ in h_entries)
    b_total = sum(c for c, _ in b_entries)
    head_totals[bf], base_totals[bf] = h_total, b_total
    rows.append((bf, b_total, h_total, h_total - b_total))
    for c, p in h_entries:
        head_mod[module_of(p)] = head_mod.get(module_of(p), 0) + c
        head_file[f"{p} [{bf}]"] = head_file.get(f"{p} [{bf}]", 0) + c

grand_head = sum(head_totals.values())
grand_base = sum(base_totals.values())
delta = grand_head - grand_base

print("== Cliquet baselines PHPStan branche vs main (#7961) ==")
print(f"Base : {base_sha[:12]} (origin/main) — Branche : working tree courant")
print()
print(f"{'Baseline':<36} {'main':>8} {'branche':>8} {'delta':>7}")
for bf, b, h, d in rows:
    print(f"{bf:<36} {b:>8} {h:>8} {d:>+7}")
print(f"{'TOTAL':<36} {grand_base:>8} {grand_head:>8} {delta:>+7}")

print()
print("-- Top 10 modules les plus chargés (occurrences, branche courante) --")
for mod, occ in sorted(head_mod.items(), key=lambda kv: -kv[1])[:10]:
    print(f"  {mod:<44} {occ:>6} occ")

print()
print("-- Top 10 fichiers les plus chargés (occurrences, branche courante) --")
for f, occ in sorted(head_file.items(), key=lambda kv: -kv[1])[:10]:
    print(f"  {f:<76} {occ:>6} occ")

# --- Budget de réduction (informative, ne bloque pas) --------------------
budget_lines = []
try:
    with open(budget_file, encoding="utf-8") as fh:
        budget = json.load(fh)
except Exception:
    budget = None

if budget:
    snap = budget.get("snapshot", {})
    snap_date = snap.get("date", "?")
    snap_total = snap.get("total_occurrences")
    weekly = budget.get("weekly_reduction_pct", 5)
    print()
    print(f"-- Budget de réduction (#7961, snapshot {snap_date}, objectif -{weekly} %/semaine) --")
    if isinstance(snap_total, int):
        try:
            weeks = max(0, (datetime.date.today() - datetime.date.fromisoformat(snap_date)).days) / 7.0
        except ValueError:
            weeks = 0.0
        expected = int(round(snap_total * (1 - weekly / 100.0) ** weeks))
        on_track = "✅ dans le budget" if grand_head <= expected else "⚠️ en retard sur le budget (informative)"
        print(f"  Snapshot : {snap_total} occ — attendu aujourd'hui ≤ {expected} occ — mesuré {grand_head} occ → {on_track}")
        budget_lines.append(f"Budget : snapshot {snap_total} occ ({snap_date}), attendu ≤ {expected}, mesuré {grand_head} — {on_track}")
    for mod in budget.get("priority_modules", []):
        name = mod.get("module", "?")
        occ = head_mod.get(name, 0)
        print(f"  Priorité strict : {name:<28} {occ:>5} occ (cible {mod.get('target', 0)})")

# --- Résumé CI ------------------------------------------------------------
summary_path = os.environ.get("GITHUB_STEP_SUMMARY")
if summary_path:
    with open(summary_path, "a", encoding="utf-8") as fh:
        fh.write("## Cliquet baselines PHPStan branche vs main (#7961)\n\n")
        fh.write("| Baseline | main | branche | delta |\n|---|---:|---:|---:|\n")
        for bf, b, h, d in rows:
            fh.write(f"| `{bf}` | {b} | {h} | {d:+d} |\n")
        fh.write(f"| **Total** | **{grand_base}** | **{grand_head}** | **{delta:+d}** |\n\n")
        for line in budget_lines:
            fh.write(line + "\n")
        fh.write("\nBudget : `dev-hub/governance/phpstan-baseline-budget.json` — doc : `docs/qa/BUDGET_PHPSTAN_7961.md`.\n")

print()
if delta > 0:
    print(f"❌ Cliquet #7961 : total occurrences baselines {grand_base} → {grand_head} (+{delta}) vs origin/main — toute CROISSANCE du total est interdite.")
    print("   Corrigez le code au lieu de re-baseliner, ou réduisez ailleurs dans la même PR (le total ne doit pas monter).")
    sys.exit(1)

print(f"✅ Cliquet #7961 : total occurrences baselines {grand_base} → {grand_head} ({delta:+d}) — stable ou en baisse vs origin/main.")
PYEOF
