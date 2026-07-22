#!/usr/bin/env bash
#
# PA2-AUTO-005: rapport hebdomadaire d'avancement pour le backlog
# PLAN_ACTION2 (docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv).
#
# Pourquoi: aucune vue consolidee n'existait pour repondre rapidement a
# "qu'est-ce qui a ete merge cette semaine ?", "qu'est-ce qui est bloque
# (PR ouverte depuis longtemps, CI rouge) ?" et "quels sont les prochains
# tickets P0 non demarres ?" sans parcourir manuellement des dizaines de
# PR et d'issues. Ce script assemble ces trois vues a partir de l'API
# GitHub (`gh`) et du CSV canonique, sans ecrire nulle part par defaut
# (rapport imprime sur stdout ; --output ecrit dans un fichier).
#
# Sections produites:
#   1. MERGES: PR PA2-* mergees dans les --since derniers jours (defaut 7).
#   2. BLOQUES: PR PA2-* ouvertes depuis plus de --stale-days jours
#      (defaut 5) OU dont au moins un check CI requis est en echec.
#   3. PROCHAINS P0: tickets P0 du CSV qui n'ont ni PR ouverte ni PR deja
#      mergee (toute la duree de vie du repo) les referencant, et dont
#      l'issue GitHub (si elle existe) n'est pas assignee (candidats
#      naturels a prioriser la semaine suivante).
#
# Usage:
#   dev-hub/tools/plan-action2-weekly-report.sh [--repo owner/repo]
#     [--since-days 7] [--stale-days 5] [--csv path] [--output path]
#
# Necessite `gh` authentifie (lecture pull-requests/issues suffit).

set -euo pipefail

REPO=""
SINCE_DAYS=7
STALE_DAYS=5
CSV_PATH="docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv"
OUTPUT_PATH=""

usage() {
  cat <<'EOF'
Usage: dev-hub/tools/plan-action2-weekly-report.sh [options]

Options:
  --repo owner/repo     Repo cible (defaut: origin git remote)
  --since-days N        Fenetre pour la section MERGES (defaut: 7)
  --stale-days N        Seuil d'age pour la section BLOQUES (defaut: 5)
  --csv PATH            Chemin du CSV canonique (defaut: docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv)
  --output PATH         Ecrit aussi le rapport dans ce fichier (en plus de stdout)
  -h, --help            Affiche cette aide
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) REPO="$2"; shift 2 ;;
    --since-days) SINCE_DAYS="$2"; shift 2 ;;
    --stale-days) STALE_DAYS="$2"; shift 2 ;;
    --csv) CSV_PATH="$2"; shift 2 ;;
    --output) OUTPUT_PATH="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Option inconnue: $1" >&2; usage >&2; exit 1 ;;
  esac
done

if [[ -z "$REPO" ]]; then
  origin_url="$(git remote get-url origin 2>/dev/null || true)"
  REPO="$(echo "$origin_url" | sed -E 's#^(https://github.com/|git@github.com:)##; s#\.git$##')"
fi

if [[ -z "$REPO" ]]; then
  echo "Impossible de determiner le repo cible ; utilisez --repo owner/repo." >&2
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "gh CLI requis." >&2
  exit 1
fi

if [[ ! -f "$CSV_PATH" ]]; then
  echo "CSV introuvable: $CSV_PATH" >&2
  exit 1
fi

REPORT_DATE="$(date -u +%Y-%m-%d)"
SINCE_ISO="$(date -u -d "-${SINCE_DAYS} days" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -v-"${SINCE_DAYS}"d +%Y-%m-%dT%H:%M:%SZ)"
STALE_BEFORE_ISO="$(date -u -d "-${STALE_DAYS} days" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -v-"${STALE_DAYS}"d +%Y-%m-%dT%H:%M:%SZ)"

REPORT=""
append() { REPORT+="$1"$'\n'; }

append "# Rapport hebdomadaire PLAN_ACTION2 — ${REPORT_DATE}"
append ""
append "Repo: \`${REPO}\` | Fenetre merges: ${SINCE_DAYS}j | Seuil bloque: ${STALE_DAYS}j sans mise a jour"
append ""

# --- 1. MERGES recents (PR PA2-*) -----------------------------------------
append "## 1. Merges recents (PA2-*, derniers ${SINCE_DAYS}j)"
append ""

ALL_MERGED_PRS_JSON=$(gh api --paginate -X GET "repos/${REPO}/pulls" -f state=closed -f per_page=100 \
  --jq '[.[] | select(.merged_at != null)]' | python3 -c "import json,sys; items=[json.loads(l) for l in sys.stdin if l.strip()]; flat=[x for sub in items for x in sub]; print(json.dumps(flat))")

MERGED_PRS_JSON=$(printf '%s' "$ALL_MERGED_PRS_JSON" | python3 -c "
import json, sys
since = '${SINCE_ISO}'
prs = json.load(sys.stdin)
print(json.dumps([pr for pr in prs if pr.get('merged_at') and pr['merged_at'] >= since]))
")

MERGED_LINES=$(echo "$MERGED_PRS_JSON" | python3 -c "
import json, re, sys
prs = json.load(sys.stdin)
out = []
for pr in prs:
    text = (pr.get('title') or '') + ' ' + (pr.get('body') or '')
    ids = sorted(set(re.findall(r'PA2-[A-Z0-9]+-[0-9]{3}', text)))
    if not ids:
        continue
    out.append(f\"- #{pr['number']} {pr['title']} — {', '.join(ids)} (merge {pr['merged_at'][:10]})\")
print('\n'.join(out))
")

if [[ -n "$MERGED_LINES" ]]; then
  append "$MERGED_LINES"
else
  append "Aucune PR PA2-* mergee dans la fenetre."
fi
append ""

# --- 2. BLOQUES (PR ouvertes stale ou CI en echec) -------------------------
append "## 2. PR bloquees ou stale (ouvertes depuis plus de ${STALE_DAYS}j, ou CI en echec)"
append ""

OPEN_PRS_JSON=$(gh api -X GET "repos/${REPO}/pulls" -f state=open -f per_page=100 \
  --jq ".")

BLOCKED_CANDIDATES=$(echo "$OPEN_PRS_JSON" | python3 -c "
import json, re, sys
prs = json.load(sys.stdin)
stale_before = '${STALE_BEFORE_ISO}'
out = []
for pr in prs:
    text = (pr.get('title') or '') + ' ' + (pr.get('body') or '')
    ids = sorted(set(re.findall(r'PA2-[A-Z0-9]+-[0-9]{3}', text)))
    if not ids:
        continue
    updated = pr.get('updated_at') or ''
    is_stale = updated < stale_before
    out.append(f\"{pr['number']}\t{pr['head']['sha']}\t{is_stale}\t{', '.join(ids)}\t{pr['title']}\t{updated}\")
print('\n'.join(out))
")

BLOCKED_LINES=""
if [[ -n "$BLOCKED_CANDIDATES" ]]; then
  while IFS=$'\t' read -r num sha is_stale ids title updated; do
    [[ -z "$num" ]] && continue
    CI_STATE=$(gh api "repos/${REPO}/commits/${sha}/status" --jq '.state' 2>/dev/null || echo "unknown")
    CI_FAILED=0
    [[ "$CI_STATE" == "failure" || "$CI_STATE" == "error" ]] && CI_FAILED=1
    if [[ "$is_stale" == "True" || "$CI_FAILED" == "1" ]]; then
      reason=""
      [[ "$is_stale" == "True" ]] && reason="stale depuis ${updated:0:10}"
      if [[ "$CI_FAILED" == "1" ]]; then
        [[ -n "$reason" ]] && reason="${reason}, "
        reason="${reason}CI: ${CI_STATE}"
      fi
      BLOCKED_LINES+="- #${num} ${title} — ${ids} (${reason})"$'\n'
    fi
  done <<< "$BLOCKED_CANDIDATES"
fi

if [[ -n "$BLOCKED_LINES" ]]; then
  append "$BLOCKED_LINES"
else
  append "Aucune PR PA2-* bloquee ou stale detectee."
fi
append ""

# --- 3. Prochains P0 non demarres ------------------------------------------
append "## 3. Prochains tickets P0 non demarres"
append ""

OPEN_ISSUES_JSON=$(gh api -X GET "repos/${REPO}/issues" -f state=open -f per_page=100 \
  --jq "[.[] | select(.pull_request == null)]")

ISSUES_TMP=$(mktemp)
OPEN_PRS_TMP=$(mktemp)
MERGED_PRS_TMP=$(mktemp)
trap 'rm -f "$ISSUES_TMP" "$OPEN_PRS_TMP" "$MERGED_PRS_TMP"' EXIT
printf '%s' "$OPEN_ISSUES_JSON" > "$ISSUES_TMP"
printf '%s' "$OPEN_PRS_JSON" > "$OPEN_PRS_TMP"
printf '%s' "$ALL_MERGED_PRS_JSON" > "$MERGED_PRS_TMP"

P0_NOT_STARTED=$(PA2_CSV_PATH="$CSV_PATH" PA2_ISSUES_PATH="$ISSUES_TMP" PA2_OPEN_PRS_PATH="$OPEN_PRS_TMP" PA2_MERGED_PRS_PATH="$MERGED_PRS_TMP" python3 -c "
import csv, json, os, re

csv_path = os.environ['PA2_CSV_PATH']
with open(os.environ['PA2_ISSUES_PATH']) as f:
    issues = json.load(f)
with open(os.environ['PA2_OPEN_PRS_PATH']) as f:
    open_prs = json.load(f)
with open(os.environ['PA2_MERGED_PRS_PATH']) as f:
    merged_prs = json.load(f)

done_ids = set()
for pr in open_prs + merged_prs:
    text = (pr.get('title') or '') + ' ' + (pr.get('body') or '')
    done_ids.update(re.findall(r'PA2-[A-Z0-9]+-[0-9]{3}', text))

issue_by_id = {}
for issue in issues:
    m = re.match(r'^(PA2-[A-Z0-9]+-[0-9]{3})', issue.get('title') or '')
    if m:
        issue_by_id[m.group(1)] = issue

rows = list(csv.DictReader(open(csv_path)))
out = []
for row in rows:
    title = row['Title']
    m = re.match(r'^(PA2-[A-Z0-9]+-[0-9]{3})', title)
    if not m:
        continue
    pa2_id = m.group(1)
    if row['Priority'] != 'P0':
        continue
    if pa2_id in done_ids:
        continue
    issue = issue_by_id.get(pa2_id)
    has_assignee = bool(issue and issue.get('assignees'))
    if has_assignee:
        continue
    status = 'sans issue ouverte' if not issue else 'issue ouverte sans assignee'
    out.append(f'- {pa2_id}: {title} ({row[\"Area\"]}) — {status}')

print('\n'.join(out))
")

if [[ -n "$P0_NOT_STARTED" ]]; then
  append "$P0_NOT_STARTED"
else
  append "Aucun ticket P0 non demarre detecte."
fi

printf '%s' "$REPORT"

if [[ -n "$OUTPUT_PATH" ]]; then
  printf '%s' "$REPORT" > "$OUTPUT_PATH"
  echo "Rapport ecrit dans ${OUTPUT_PATH}" >&2
fi
