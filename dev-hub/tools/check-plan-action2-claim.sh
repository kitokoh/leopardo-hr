#!/usr/bin/env bash
# PA2-AUTO-011: refuse un PR PLAN_ACTION2 qui entre en collision de "claim"
# avec un autre agent, conformement au protocole documente dans
# docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md (issue auto-assignee +
# PR draft = signal officiel de prise de tache).
#
# Usage: check-plan-action2-claim.sh <owner/repo> <pr_number>
#
# Requiert `gh` authentifie (GITHUB_TOKEN suffit: lecture issues/PRs).
set -euo pipefail

REPO="${1:?usage: check-plan-action2-claim.sh <owner/repo> <pr_number>}"
PR_NUMBER="${2:?usage: check-plan-action2-claim.sh <owner/repo> <pr_number>}"

PR_JSON=$(gh api "repos/${REPO}/pulls/${PR_NUMBER}")
PR_TITLE=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('title') or '')")
PR_BODY=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('body') or '')")
PR_AUTHOR=$(echo "$PR_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('user', {}).get('login') or '')")

# Un PA2-ID: PA2-<AREA>-<3 chiffres>, ex: PA2-OPS-011, PA2-I18N-006.
PA2_ID=$(printf '%s\n%s' "$PR_TITLE" "$PR_BODY" | grep -oE 'PA2-[A-Z0-9]+-[0-9]{3}' | head -n1 || true)

if [[ -z "$PA2_ID" ]]; then
  echo "::notice::Aucun ID PA2-* trouve dans le titre/la description de la PR #${PR_NUMBER} — hors perimetre de ce garde (voir PA2-AUTO-004 pour l'exigence generale d'ID PA2)."
  exit 0
fi

echo "PA2 ticket detecte: ${PA2_ID} (PR #${PR_NUMBER}, auteur ${PR_AUTHOR})"

FAIL=0

# --- 1. Collision avec une autre PR ouverte sur le meme ticket -------------
OTHER_PRS=$(gh api -X GET "repos/${REPO}/pulls" -f state=open -f per_page=100 \
  --jq ".[] | select(.number != ${PR_NUMBER}) | {number, title, user: .user.login}")

if [[ -n "$OTHER_PRS" ]]; then
  COLLISION=$(PA2_OTHER_PRS_JSON="$OTHER_PRS" python3 - "$PA2_ID" <<'PYEOF'
import json, os, re, sys
pa2_id = sys.argv[1]
raw = os.environ.get("PA2_OTHER_PRS_JSON", "").strip()
if not raw:
    sys.exit(0)
# gh --jq on multiple objects prints one JSON object per line (not an array).
found = []
for line in raw.splitlines():
    line = line.strip()
    if not line:
        continue
    try:
        obj = json.loads(line)
    except json.JSONDecodeError:
        continue
    title = obj.get("title") or ""
    if re.search(re.escape(pa2_id) + r"(?![0-9])", title):
        found.append(obj)
for obj in found:
    print(f"{obj['number']}\t{obj['user']}\t{obj['title']}")
PYEOF
)

  if [[ -n "$COLLISION" ]]; then
    echo "::error::Collision de claim detectee pour ${PA2_ID} — une autre PR ouverte reference deja ce ticket:"
    while IFS=$'\t' read -r num user title; do
      [[ -z "$num" ]] && continue
      echo "::error::  PR #${num} (@${user}) — ${title}"
    done <<< "$COLLISION"
    echo "::error::Conformement a docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md, un seul agent doit travailler un ticket PA2-* a la fois. Verifier 'gh pr list --search \"${PA2_ID}\"' avant de reprendre, puis fermer/rebaser la PR en trop ou coordonner avec l'autre agent."
    FAIL=1
  fi
fi

# --- 2. Coherence issue <-> claim ------------------------------------------
# Chercher l'issue GitHub dont le titre commence par ce PA2-ID (convention
# du generateur d'issues PA2-AUTO-003). Utilise l'API de recherche (pas de
# pagination manuelle necessaire meme sur un repo avec 1000+ issues).
ISSUE_JSON=$(gh api -X GET "search/issues" \
  -f "q=repo:${REPO} is:issue \"${PA2_ID}\" in:title" \
  --jq "[.items[] | select(.title | test(\"^${PA2_ID}\\\\b\"))] | .[0]")

if [[ -n "$ISSUE_JSON" && "$ISSUE_JSON" != "null" ]]; then
  ISSUE_NUMBER=$(echo "$ISSUE_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('number'))")
  ASSIGNEES=$(echo "$ISSUE_JSON" | python3 -c "import json,sys; d=json.load(sys.stdin); print(','.join(a['login'] for a in d.get('assignees', [])))")
  ISSUE_STATE=$(echo "$ISSUE_JSON" | python3 -c "import json,sys; print(json.load(sys.stdin).get('state'))")

  echo "Issue correspondante: #${ISSUE_NUMBER} (etat: ${ISSUE_STATE}, assignee(s): ${ASSIGNEES:-aucun})"

  if [[ -n "$ASSIGNEES" ]]; then
    IFS=',' read -ra ASSIGNEE_ARR <<< "$ASSIGNEES"
    MATCH=0
    for a in "${ASSIGNEE_ARR[@]}"; do
      if [[ "$a" == "$PR_AUTHOR" ]]; then
        MATCH=1
        break
      fi
    done
    if [[ "$MATCH" -eq 0 ]]; then
      echo "::error::L'issue #${ISSUE_NUMBER} (${PA2_ID}) est assignee a [${ASSIGNEES}], pas a l'auteur de cette PR (@${PR_AUTHOR}). Deux agents semblent travailler le meme ticket sans coordination — voir docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md section 'Signal de prise de tache'."
      FAIL=1
    fi
  else
    echo "::warning::L'issue #${ISSUE_NUMBER} (${PA2_ID}) n'a aucun assignee. L'auteur de cette PR devrait s'auto-assigner l'issue (gh issue edit ${ISSUE_NUMBER} --add-assignee @me) pour signaler officiellement la prise de tache avant merge."
  fi
else
  echo "::warning::Aucune issue GitHub trouvee pour ${PA2_ID} (titre attendu: '${PA2_ID} ...'). Le protocole de claim (issue assignee) ne peut pas etre verifie pour ce ticket."
fi

if [[ "$FAIL" -eq 1 ]]; then
  exit 1
fi

echo "OK: pas de collision de claim detectee pour ${PA2_ID}."
