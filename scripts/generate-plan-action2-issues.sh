#!/usr/bin/env bash
#
# PA2-AUTO-003: genere les issues GitHub manquantes a partir du CSV
# canonique docs/archive/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv (source de
# verite deja validee par dev-hub/tools/validate-plan-action2.ps1 /
# .github/workflows/plan-action2-project.yml).
#
# Pourquoi: le CSV et le backlog markdown (02_BACKLOG_ATOMIQUE.md) existent
# depuis PA2-AUTO-001, mais rien n'automatisait la creation des issues
# GitHub correspondantes — elles etaient creees a la main ticket par
# ticket, avec un risque de doublon, d'oubli, ou de titre/label
# divergent du CSV.
#
# Comportement:
#   - Dry-run par defaut (aucune ecriture GitHub) ; --apply pour creer
#     reellement les issues manquantes.
#   - Detection de doublon par recherche exacte du titre CSV
#     ("<ID> <Titre>" complet) via `gh issue list --search`, tous etats
#     confondus (open+closed) pour ne jamais recreer un ticket deja
#     traite/ferme.
#   - Labels: toujours "enhancement", plus un label de domaine derive
#     de la colonne Area du CSV (mapping ci-dessous, aligne sur les
#     labels deja utilises dans le repo pour les tickets PA2-* existants).
#   - --milestone <title> optionnel: assigne toutes les issues creees a
#     un milestone existant (doit deja exister sur le repo).
#   - --owner <login> optionnel: auto-assigne toutes les issues creees.
#   - --label-filter <id-prefix> optionnel: ne traite qu'un sous-ensemble
#     d'IDs PA2-<PREFIX>-*, ex: --label-filter PA2-I18N pour ne generer
#     que les tickets i18n restants.
#
# Usage:
#   scripts/generate-plan-action2-issues.sh [--repo owner/repo] [--apply]
#     [--milestone TITLE] [--owner LOGIN] [--label-filter PA2-XXX]
#     [--csv path]
#
# Necessite `gh` authentifie avec acces issues:write sur le repo cible
# (lecture seule suffit en mode dry-run, le mode par defaut).

set -euo pipefail

REPO=""
APPLY=0
MILESTONE=""
OWNER=""
LABEL_FILTER=""
CSV_PATH="docs/archive/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv"

usage() {
  cat <<'EOF'
Usage: scripts/generate-plan-action2-issues.sh [options]

Options:
  --repo owner/repo       Repo cible (defaut: origin git remote)
  --apply                 Cree reellement les issues manquantes (defaut: dry-run)
  --milestone TITLE       Milestone existant a assigner aux issues creees
  --owner LOGIN           Login GitHub a auto-assigner aux issues creees
  --label-filter PREFIX   Ne traiter que les IDs commencant par ce prefixe (ex: PA2-I18N)
  --csv PATH              Chemin du CSV (defaut: docs/archive/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv)
  -h, --help              Affiche cette aide
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo) REPO="$2"; shift 2 ;;
    --apply) APPLY=1; shift ;;
    --milestone) MILESTONE="$2"; shift 2 ;;
    --owner) OWNER="$2"; shift 2 ;;
    --label-filter) LABEL_FILTER="$2"; shift 2 ;;
    --csv) CSV_PATH="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Option inconnue: $1" >&2; usage >&2; exit 1 ;;
  esac
done

if [[ -z "$REPO" ]]; then
  origin_url="$(git remote get-url origin 2>/dev/null || true)"
  REPO="$(echo "$origin_url" | sed -E 's#^(https://github.com/|git@github.com:)##; s#\.git$##')"
fi

if [[ -z "$REPO" ]]; then
  echo "Impossible de determiner le repo cible. Utiliser --repo owner/repo." >&2
  exit 1
fi

if [[ ! -f "$CSV_PATH" ]]; then
  echo "CSV introuvable: $CSV_PATH" >&2
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "gh (GitHub CLI) est requis." >&2
  exit 1
fi

# Mapping Area (colonne CSV) -> label de domaine GitHub, aligne sur les
# labels deja poses a la main sur les tickets PA2-* existants du repo.
area_to_label() {
  case "$1" in
    Architecture|API|Security|Paie|Pays|Pointage) echo "backend" ;;
    Acquisition|Admin|Communication|Onboarding) echo "frontend" ;;
    Mobile) echo "mobile" ;;
    I18N) echo "i18n" ;;
    Kiosk) echo "" ;; # pas de label dedie constate sur les tickets Kiosk existants
    Automation|Ops|Strategy|Developer|Finance|Verification) echo "" ;;
    *) echo "" ;;
  esac
}

created=0
skipped_existing=0
skipped_filter=0

echo "Repo cible: $REPO"
if [[ "$APPLY" -eq 1 ]]; then
  echo "Mode: APPLY (creation reelle des issues manquantes)"
else
  echo "Mode: DRY-RUN (aucune ecriture ; utiliser --apply pour creer)"
fi
echo ""

# Lit le CSV avec python3 (deja utilise par check-plan-action2-claim.sh
# dans ce repo) pour un parsing CSV robuste (guillemets, virgules internes).
rows_json="$(python3 - "$CSV_PATH" <<'PYEOF'
import csv, json, sys
path = sys.argv[1]
with open(path, newline="", encoding="utf-8") as f:
    reader = csv.DictReader(f)
    rows = list(reader)
print(json.dumps(rows))
PYEOF
)"

row_count="$(echo "$rows_json" | python3 -c 'import json,sys; print(len(json.load(sys.stdin)))')"

for ((i = 0; i < row_count; i++)); do
  row="$(echo "$rows_json" | python3 -c "import json,sys; r=json.load(sys.stdin)[$i]; print(json.dumps(r))")"
  title="$(echo "$row" | python3 -c 'import json,sys; print(json.load(sys.stdin)["Title"])')"
  area="$(echo "$row" | python3 -c 'import json,sys; print(json.load(sys.stdin)["Area"])')"
  surface="$(echo "$row" | python3 -c 'import json,sys; print(json.load(sys.stdin)["Surface"])')"
  deps="$(echo "$row" | python3 -c 'import json,sys; print(json.load(sys.stdin)["Dependencies"])')"
  ac="$(echo "$row" | python3 -c 'import json,sys; print(json.load(sys.stdin)["Acceptance Criteria"])')"
  priority="$(echo "$row" | python3 -c 'import json,sys; print(json.load(sys.stdin)["Priority"])')"

  ticket_id="$(echo "$title" | grep -oE '^PA2-[A-Z0-9]+-[0-9]{3}' || true)"
  if [[ -z "$ticket_id" ]]; then
    echo "⚠️  Ligne ignoree (pas d'ID PA2-* en debut de titre): $title" >&2
    continue
  fi

  if [[ -n "$LABEL_FILTER" && "$ticket_id" != "$LABEL_FILTER"* ]]; then
    skipped_filter=$((skipped_filter + 1))
    continue
  fi

  # Doublon: recherche par titre exact, tous etats (open+closed) pour ne
  # jamais recreer un ticket deja traite. gh issue list --search fait une
  # recherche plein-texte GitHub (pas une egalite stricte) ; on filtre donc
  # cote client sur le titre exact retourne.
  existing="$(gh issue list --repo "$REPO" --search "\"$title\" in:title" --state all --json title \
    --jq "[.[] | select(.title == \"$title\")] | length" 2>/dev/null || echo 0)"

  if [[ "$existing" -gt 0 ]]; then
    skipped_existing=$((skipped_existing + 1))
    continue
  fi

  domain_label="$(area_to_label "$area")"
  labels="enhancement"
  if [[ -n "$domain_label" ]]; then
    labels="$labels,$domain_label"
  fi

  body="**Ticket:** ${ticket_id}
**Priority:** ${priority}
**Area:** ${area}
**Surface:** ${surface}
**Dependencies:** ${deps:-none}

## Acceptance Criteria
${ac}

---
Source: \`${CSV_PATH}\`"

  echo "+ Nouvelle issue: ${title}"
  echo "    labels: ${labels}"
  [[ -n "$MILESTONE" ]] && echo "    milestone: ${MILESTONE}"
  [[ -n "$OWNER" ]] && echo "    owner: ${OWNER}"

  if [[ "$APPLY" -eq 1 ]]; then
    create_args=(issue create --repo "$REPO" --title "$title" --body "$body" --label "$labels")
    if [[ -n "$MILESTONE" ]]; then
      create_args+=(--milestone "$MILESTONE")
    fi
    if [[ -n "$OWNER" ]]; then
      create_args+=(--assignee "$OWNER")
    fi
    issue_url="$(gh "${create_args[@]}")"
    echo "    -> ${issue_url}"
  fi

  created=$((created + 1))
done

echo ""
echo "Resume: ${row_count} ticket(s) dans le CSV, ${created} a creer, ${skipped_existing} deja existant(s), ${skipped_filter} filtre(s) par --label-filter."
if [[ "$APPLY" -eq 0 && "$created" -gt 0 ]]; then
  echo "Dry-run: relancer avec --apply pour creer reellement ces ${created} issue(s)."
fi
