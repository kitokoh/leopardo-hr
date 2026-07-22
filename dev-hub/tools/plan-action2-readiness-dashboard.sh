#!/usr/bin/env bash
#
# PA2-AUTO-007: dashboard readiness tickets.
#
# Pourquoi: le backlog PLAN_ACTION2 a deja un CSV canonique (source de
# verite des tickets), un fichier de roadmap qui groupe les tickets par
# release marche (04_ROADMAP_RELEASES.md), et un fichier de backlog detaille
# qui marque certains tickets comme livres ("Fait"/"FAIT" dans la colonne
# Definition of Done de 02_BACKLOG_ATOMIQUE.md) — mais rien n'assemblait ces
# trois sources pour repondre a la question concrete "combien de tickets
# de la Release B sont vraiment livres, et lesquels restent a faire avant
# de pouvoir l'annoncer a un client pilote ?" sans parcourir manuellement
# le CSV + la roadmap + le backlog + git log a chaque fois.
#
# Ce script produit un tableau markdown en lecture seule (git log local +
# fichiers du depot, pas d'appel API GitHub necessaire par defaut ; passer
# --repo active un croisement optionnel avec les PR ouvertes via `gh` pour
# distinguer "non demarre" de "en cours de revue") :
#
#   - une ligne par release de 04_ROADMAP_RELEASES.md ;
#   - le nombre de tickets, le nombre de tickets livres (marque Fait dans
#     le backlog OU deja fusionne sur la branche par defaut via
#     `git log <default-branch>`), le nombre en cours (PR ouverte
#     referencant l'ID, si --repo fourni), et le pourcentage de readiness ;
#   - en detail, chaque ticket avec son statut (FAIT / EN COURS / A FAIRE).
#
# Important: le croisement git n'utilise QUE l'historique de la branche par
# defaut (`origin/main`, ou `main` local, ou --ref explicite) et jamais
# `git log --all`. `--all` inclurait aussi les commits de branches de
# feature non fusionnees (ex: `feature/issue-XXXX` ouvertes par ce meme
# agent), ce qui marquerait a tort un ticket encore en PR ouverte comme
# "FAIT" alors qu'il n'est pas encore merge sur main.
#
# Un ticket du CSV absent de la roadmap est liste a part ("Hors roadmap")
# plutot que silencieusement ignore, pour ne jamais perdre un ticket de vue.
#
# Usage:
#   dev-hub/tools/plan-action2-readiness-dashboard.sh
#     [--csv path] [--roadmap path] [--backlog path] [--ref gitref]
#     [--repo owner/repo] [--output path]
#
# --ref est optionnel: sans lui, le script essaie `origin/main` puis `main`
# puis `HEAD` (premier qui existe). --repo est optionnel: sans lui, un
# ticket ni marque Fait ni trouve dans l'historique de la branche par
# defaut est simplement classe "A FAIRE" (pas de distinction "en cours de
# revue" sans interroger GitHub).

set -euo pipefail

CSV_PATH="docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv"
ROADMAP_PATH="docs/PLAN_ACTION2/04_ROADMAP_RELEASES.md"
BACKLOG_PATH="docs/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md"
REPO=""
OUTPUT_PATH=""
GIT_REF=""

usage() {
  cat <<'EOF'
Usage: dev-hub/tools/plan-action2-readiness-dashboard.sh [options]

Options:
  --csv PATH        Chemin du CSV canonique (defaut: docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv)
  --roadmap PATH    Chemin de la roadmap par release (defaut: docs/PLAN_ACTION2/04_ROADMAP_RELEASES.md)
  --backlog PATH    Chemin du backlog detaille (defaut: docs/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md)
  --ref gitref      Ref git a utiliser comme historique "deja livre" (defaut: origin/main, puis main, puis HEAD)
  --repo owner/repo Active le croisement PR ouvertes via `gh` pour detecter "en cours"
  --output PATH     Ecrit aussi le rapport dans ce fichier (en plus de stdout)
  -h, --help        Affiche cette aide
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --csv) CSV_PATH="$2"; shift 2 ;;
    --roadmap) ROADMAP_PATH="$2"; shift 2 ;;
    --backlog) BACKLOG_PATH="$2"; shift 2 ;;
    --ref) GIT_REF="$2"; shift 2 ;;
    --repo) REPO="$2"; shift 2 ;;
    --output) OUTPUT_PATH="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Option inconnue: $1" >&2; usage >&2; exit 1 ;;
  esac
done

for f in "$CSV_PATH" "$ROADMAP_PATH" "$BACKLOG_PATH"; do
  if [[ ! -f "$f" ]]; then
    echo "Fichier introuvable: $f" >&2
    exit 1
  fi
done

# Determiner la ref a utiliser comme historique "deja livre": jamais
# `--all` (voir note en tete de fichier), seulement la branche par defaut.
if [[ -z "$GIT_REF" ]]; then
  if git rev-parse --verify --quiet origin/main >/dev/null 2>&1; then
    GIT_REF="origin/main"
  elif git rev-parse --verify --quiet main >/dev/null 2>&1; then
    GIT_REF="main"
  else
    GIT_REF="HEAD"
  fi
fi
if ! git rev-parse --verify --quiet "$GIT_REF" >/dev/null 2>&1; then
  echo "Ref git introuvable: $GIT_REF" >&2
  exit 1
fi

# on recupere une seule fois tous les sujets/corps de commit de l'historique
# de $GIT_REF et on cherche les IDs dedans en Python (une invocation `git
# log --grep` par ticket serait trop lente pour 175 tickets). Ecrit dans un
# fichier temporaire plutot qu'une variable d'environnement: l'historique
# complet peut depasser la limite ARG_MAX, ce qui ferait echouer l'appel
# Python avec "Argument list too long".
COMMIT_TEXT_FILE=$(mktemp)
OPEN_PRS_FILE=$(mktemp)
trap 'rm -f "$COMMIT_TEXT_FILE" "$OPEN_PRS_FILE"' EXIT
git log "$GIT_REF" --format='%s%n%b' > "$COMMIT_TEXT_FILE" 2>/dev/null || true

if [[ -n "$REPO" ]]; then
  if ! command -v gh >/dev/null 2>&1; then
    echo "gh CLI requis pour --repo." >&2
    exit 1
  fi
  gh api -X GET "repos/${REPO}/pulls" -f state=open -f per_page=100 --jq "[.[] | {number, title, body}]" > "$OPEN_PRS_FILE"
else
  echo "[]" > "$OPEN_PRS_FILE"
fi

REPORT_DATE="$(date -u +%Y-%m-%d)"

DASHBOARD=$(PA2_COMMIT_TEXT_FILE="$COMMIT_TEXT_FILE" PA2_OPEN_PRS_FILE="$OPEN_PRS_FILE" PA2_GIT_REF="$GIT_REF" python3 - "$CSV_PATH" "$ROADMAP_PATH" "$BACKLOG_PATH" "$REPORT_DATE" <<'PYEOF'
import csv
import json
import os
import re
import sys

csv_path, roadmap_path, backlog_path, report_date = sys.argv[1:5]

# --- 1. Charger tous les tickets du CSV canonique -------------------------
tickets = {}
order = []
with open(csv_path, newline='') as f:
    for row in csv.DictReader(f):
        m = re.match(r'^(PA2-[A-Z0-9]+-[0-9]{3})\s+(.*)$', row['Title'])
        if not m:
            continue
        pa2_id, label = m.group(1), m.group(2)
        tickets[pa2_id] = {
            "id": pa2_id,
            "label": label,
            "priority": row.get("Priority", ""),
            "area": row.get("Area", ""),
        }
        order.append(pa2_id)

# --- 2. Charger le mapping ticket -> release de la roadmap ----------------
release_of = {}
release_order = []
current_release = None
with open(roadmap_path, encoding='utf-8') as f:
    for line in f:
        h = re.match(r'^##\s+(.*)$', line.strip())
        if h:
            current_release = h.group(1)
            if current_release not in release_order:
                release_order.append(current_release)
            continue
        b = re.match(r'^-\s+(PA2-[A-Z0-9]+-[0-9]{3})\s*$', line.strip())
        if b and current_release:
            release_of[b.group(1)] = current_release

# --- 3. Charger les marqueurs "Fait"/"FAIT" du backlog detaille -----------
done_in_backlog = set()
with open(backlog_path, encoding='utf-8') as f:
    for line in f:
        if not line.startswith('|'):
            continue
        m = re.match(r'^\|\s*(PA2-[A-Z0-9]+-[0-9]{3})\s*\|', line)
        if not m:
            continue
        pa2_id = m.group(1)
        if re.search(r'\bFAIT\b|\*\*Fait\*\*|Fait le \d{4}', line):
            done_in_backlog.add(pa2_id)

# --- 4. Croiser avec l'historique git de la ref choisie (--ref) -----------
with open(os.environ["PA2_COMMIT_TEXT_FILE"], encoding='utf-8', errors='replace') as f:
    all_commit_text = f.read()
done_in_git = set()
for pa2_id in order:
    if re.search(re.escape(pa2_id) + r'(?![0-9])', all_commit_text):
        done_in_git.add(pa2_id)

# --- 5. Croiser avec les PR ouvertes (optionnel, --repo) -------------------
try:
    with open(os.environ["PA2_OPEN_PRS_FILE"], encoding='utf-8') as f:
        open_prs = json.load(f)
except (json.JSONDecodeError, FileNotFoundError):
    open_prs = []

in_progress = set()
for pr in open_prs:
    text = (pr.get('title') or '') + ' ' + (pr.get('body') or '')
    for pa2_id in re.findall(r'PA2-[A-Z0-9]+-[0-9]{3}', text):
        in_progress.add(pa2_id)

# --- 6. Construire le statut final par ticket ------------------------------
def status_of(pa2_id):
    if pa2_id in done_in_backlog or pa2_id in done_in_git:
        return "FAIT"
    if pa2_id in in_progress:
        return "EN COURS"
    return "A FAIRE"

lines = []
lines.append(f"# Dashboard readiness PLAN_ACTION2 — {report_date}")
lines.append("")
git_ref = os.environ.get("PA2_GIT_REF", "?")
lines.append(f"Mapping tickets `PA2-*` vers release pilote (`04_ROADMAP_RELEASES.md`), avec statut de livraison croise contre le backlog detaille (`02_BACKLOG_ATOMIQUE.md`) et l'historique git de `{git_ref}` (jamais `--all`, pour ne pas compter des branches de feature non fusionnees comme livrees).")
lines.append("")

lines.append("## Synthese par release")
lines.append("")
lines.append("| Release | Tickets | Fait | En cours | A faire | Readiness |")
lines.append("|---|---:|---:|---:|---:|---:|")

by_release = {}
for pa2_id in order:
    rel = release_of.get(pa2_id, "Hors roadmap")
    by_release.setdefault(rel, []).append(pa2_id)

ordered_releases = [r for r in release_order if r in by_release] + (["Hors roadmap"] if "Hors roadmap" in by_release else [])

for rel in ordered_releases:
    ids = by_release[rel]
    done_count = sum(1 for i in ids if status_of(i) == "FAIT")
    progress_count = sum(1 for i in ids if status_of(i) == "EN COURS")
    todo_count = len(ids) - done_count - progress_count
    pct = round(100 * done_count / len(ids)) if ids else 0
    lines.append(f"| {rel} | {len(ids)} | {done_count} | {progress_count} | {todo_count} | {pct}% |")

lines.append("")
lines.append("## Detail par release")
lines.append("")

for rel in ordered_releases:
    lines.append(f"### {rel}")
    lines.append("")
    for pa2_id in by_release[rel]:
        t = tickets[pa2_id]
        lines.append(f"- [{status_of(pa2_id)}] **{pa2_id}** ({t['priority']}, {t['area']}) — {t['label']}")
    lines.append("")

print('\n'.join(lines))
PYEOF
)

printf '%s\n' "$DASHBOARD"

if [[ -n "$OUTPUT_PATH" ]]; then
  printf '%s\n' "$DASHBOARD" > "$OUTPUT_PATH"
  echo "Dashboard ecrit dans ${OUTPUT_PATH}" >&2
fi
