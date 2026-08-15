#!/usr/bin/env bash
# ============================================================
# OPS-2413 : annule les runs GitHub Actions queued/in_progress
# dont la branche n'a plus de PR ouverte (branche mergee,
# supersedee ou renommee). Objectif : eviter la saturation de la
# file Actions (issue #2413) qui laisse les checks requis a
# "queued" a vie et bloque tous les merges.
#
# Usage: cancel-stale-action-runs.sh <owner/repo>
#
# Requiert `gh` authentifie (GITHUB_TOKEN avec actions:write et
# pull-requests:read suffisent). Idempotent et non destructif :
#   - ne touche JAMAIS les runs de la branche `main` ;
#   - ne touche JAMAIS une branche portant une PR ouverte ;
#   - n'effectue que des POST /actions/runs/{id}/cancel, tous
#     re-lancables a la demande.
# ============================================================
set -euo pipefail

REPO="${1:?usage: cancel-stale-action-runs.sh <owner/repo>}"

# 1) Branches porteuses d'une PR ouverte (protection absolue).
OPEN_BRANCHES=$(gh api --paginate "repos/${REPO}/pulls" -f state=open -f per_page=100 \
  --jq '.[].head.ref' | sort -u)
if [[ -z "$OPEN_BRANCHES" ]]; then
  echo "::notice::Aucune PR ouverte — toutes les branches hors main sont candidates."
fi

# 2) Runs queued/in_progress a examiner (pagination gh native).
RUNS=$(gh api --paginate "repos/${REPO}/actions/runs" -f status=queued -f per_page=100 \
  --jq '.workflow_runs[] | [.id, .head_branch, .name] | @tsv')

CANCELED=0
SKIPPED=0
while IFS=$'\t' read -r RUN_ID BRANCH WF_NAME; do
  [[ -z "$RUN_ID" ]] && continue
  if [[ "$BRANCH" == "main" ]]; then
    SKIPPED=$((SKIPPED + 1))
    continue
  fi
  if grep -qxF "$BRANCH" <<<"$OPEN_BRANCHES"; then
    SKIPPED=$((SKIPPED + 1))
    continue
  fi
  if gh api -X POST "repos/${REPO}/actions/runs/${RUN_ID}/cancel" >/dev/null 2>&1; then
    echo "::notice::Run ${RUN_ID} (${WF_NAME}, branche ${BRANCH}) annule."
    CANCELED=$((CANCELED + 1))
  else
    # Deja complete entre la liste et l'annulation — cas normal.
    SKIPPED=$((SKIPPED + 1))
  fi
done <<<"$RUNS"

echo "::notice::Queue cleanup ${REPO} : ${CANCELED} run(s) annule(s), ${SKIPPED} conserve(s) (main/PR ouvertes/deja termines)."
