#!/usr/bin/env bash
# check-pr-runs-missing.sh — Détecte les PRs sans checks (leçon #3545, issue #5507).
#
# Pourquoi : sous rafale de pushes (300+ runs queued), GitHub Actions ne crée
# parfois AUCUN run pour un push — les agents perdent des heures en « nudges »
# à l'aveugle. Ce script liste les PRs ouvertes dont le head SHA n'a aucun
# check-run depuis > MIN_AGE_MIN minutes et publie l'état de la file.
#
# Usage : check-pr-runs-missing.sh <owner/repo> [--min-age-min N]
# Requiert `gh` (GITHUB_TOKEN : lecture pulls/commits/actions).
set -uo pipefail

REPO="${1:?usage: check-pr-runs-missing.sh <owner/repo> [--min-age-min N]}"
MIN_AGE_MIN="${2:-10}"

now=$(date +%s)
queued=$(gh api "repos/${REPO}/actions/runs" -f status=queued -f per_page=1 --jq '.total_count // 0' 2>/dev/null || echo 0)
inprog=$(gh api "repos/${REPO}/actions/runs" -f status=in_progress -f per_page=1 --jq '.total_count // 0' 2>/dev/null || echo 0)
echo "::notice::File Actions : ${queued} queued, ${inprog} in_progress"

missing=0
prs=$(gh api "repos/${REPO}/pulls" -f state=open -f per_page=100 --paginate --jq '.[] | {number, head: .head.sha, updated: .updated_at, title: .title}')
while IFS=$'\t' read -r num sha updated title; do
  [ -z "$num" ] && continue
  count=$(gh api "repos/${REPO}/commits/${sha}/check-runs" --jq '.total_count // 0' 2>/dev/null || echo 0)
  if [ "$count" -eq 0 ]; then
    ts=$(date -d "$updated" +%s 2>/dev/null || echo 0)
    age_min=$(( (now - ts) / 60 ))
    if [ "$age_min" -ge "$MIN_AGE_MIN" ]; then
      echo "::warning::PR #${num} (${sha:0:8}) : AUCUN check-run depuis ~${age_min} min — « ci: nudge » (commit vide) ou vérifier la file (#3545). ${title:0:60}"
      missing=$((missing + 1))
    fi
  fi
done < <(echo "$prs" | jq -r '.[] | [.number, .head, .updated, (.title | gsub("\t"; " "))] | @tsv')

echo "PRs sans checks (> ${MIN_AGE_MIN} min) : ${missing}"
exit 0
