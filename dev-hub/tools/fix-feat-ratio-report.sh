#!/usr/bin/env bash
#
# Rapport hebdomadaire — ratio fix/feat (KPI gouvernance, issue #5634).
#
# Anciennement la section "1bis" de plan-action2-weekly-report.sh. Les
# sections 1/2/3 de ce script (merges PA2-*, PR PA2-* bloquées, tickets P0 du
# CSV PLAN_ACTION2) ont été retirées avec le reste du workflow PLAN_ACTION2
# (backlog clos depuis le 2026-07-26, cf. PILOTAGE.md « Gouvernance
# documentaire ») — elles ne rapportaient plus rien d'utile sur un backlog
# gelé. Ce KPI, en revanche, porte sur TOUTES les PR mergées (pas seulement
# PA2-*) et reste suivi activement (AGENTS.md § Discipline merge, issue #5634).
#
# Rétro pilotes J6 : ratio 5.24 mesuré (1724 fix / 329 feat), cible <= 2.5.
# Compte les PR mergées par type conventional commit, alerte si le ratio
# dépasse RATIO_FIX_FEAT_ALERT (défaut 3).
#
# Usage:
#   dev-hub/tools/fix-feat-ratio-report.sh [--repo owner/repo]
#     [--since-days 7] [--output path]
#
# Nécessite `gh` authentifié (lecture pull-requests suffit).

set -euo pipefail

REPO=""
SINCE_DAYS=7
OUTPUT_PATH=""

usage() {
  cat <<'EOF'
Usage: dev-hub/tools/fix-feat-ratio-report.sh [options]

Options:
  --repo owner/repo     Repo cible (défaut: origin git remote)
  --since-days N        Fenêtre pour le calcul du ratio (défaut: 7)
  --output PATH         Écrit aussi le rapport dans ce fichier (en plus de stdout)
  -h, --help            Affiche cette aide
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) REPO="$2"; shift 2 ;;
    --since-days) SINCE_DAYS="$2"; shift 2 ;;
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

REPORT_DATE="$(date -u +%Y-%m-%d)"
SINCE_ISO="$(date -u -d "-${SINCE_DAYS} days" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -v-"${SINCE_DAYS}"d +%Y-%m-%dT%H:%M:%SZ)"

REPORT=""
append() { REPORT+="$1"$'\n'; }

append "# Rapport hebdomadaire — ratio fix/feat — ${REPORT_DATE}"
append ""
append "Repo: \`${REPO}\` | Fenetre: ${SINCE_DAYS}j"
append ""

ALL_MERGED_PRS_JSON=$(gh api --paginate -X GET "repos/${REPO}/pulls" -f state=closed -f per_page=100 \
  --jq '[.[] | select(.merged_at != null)]' | python3 -c "import json,sys; items=[json.loads(l) for l in sys.stdin if l.strip()]; flat=[x for sub in items for x in sub]; print(json.dumps(flat))")

# --- Ratio fix/feat (KPI gouvernance, issue #5634) -------------------------
append "## Ratio fix/feat (KPI hebdo — cible <= 2.5, alerte > ${RATIO_FIX_FEAT_ALERT:-3})"
append ""

RATIO_JSON=$(printf '%s' "$ALL_MERGED_PRS_JSON" | python3 -c "
import json, re, sys
since = '${SINCE_ISO}'
prs = json.load(sys.stdin)
prs = [pr for pr in prs if pr.get('merged_at') and pr['merged_at'] >= since]
fix_types = {'fix', 'hotfix', 'revert', 'security'}
feat_types = {'feat', 'feature', 'refactor'}
fix = feat = other = 0
for pr in prs:
    title = (pr.get('title') or '').strip().lower()
    m = re.match(r'^([a-z0-9_-]+)(\([^)]*\))?:', title)
    t = m.group(1) if m else ''
    if t in fix_types:
        fix += 1
    elif t in feat_types:
        feat += 1
    else:
        other += 1
ratio = (fix / feat) if feat > 0 else None
print(json.dumps({'total': len(prs), 'fix': fix, 'feat': feat, 'other': other, 'ratio': ratio}))
")

RATIO_LINE=$(echo "$RATIO_JSON" | python3 -c "
import json, sys
d = json.load(sys.stdin)
if d['total'] == 0:
    print('Aucun merge dans la fenetre.')
elif d['feat'] == 0:
    print(f\"{d['total']} merges — {d['fix']} fix / 0 feat (ratio infini — aucun merge feat)\")
else:
    print(f\"{d['total']} merges — {d['fix']} fix / {d['feat']} feat = ratio {d['ratio']:.2f}\")
")
append "$RATIO_LINE"

RATIO_WARN=$(echo "$RATIO_JSON" | python3 -c "
import json, sys, os
d = json.load(sys.stdin)
alert = float(os.environ.get('RATIO_FIX_FEAT_ALERT', '3'))
if d['feat'] > 0 and d['ratio'] is not None and d['ratio'] > alert:
    print(f\"⚠️ RATIO fix/feat {d['ratio']:.2f} > {alert:.1f} — cible <= 2.5 (issue #5634) : prioriser les features.\")
")
if [[ -n "$RATIO_WARN" ]]; then
  append "$RATIO_WARN"
fi

printf '%s' "$REPORT"

if [[ -n "$OUTPUT_PATH" ]]; then
  printf '%s' "$REPORT" > "$OUTPUT_PATH"
  echo "Rapport ecrit dans ${OUTPUT_PATH}" >&2
fi
