#!/usr/bin/env bash
# Issue #2413 — hygiène de la file GitHub Actions.
#
# Constat (session senior 2026-08-15) : la file atteignait 470+ runs queued,
# dont une grande part pour des branches de PR déjà mergées/fermées et
# supprimées (runs orphelins). Chaque PR fermée laisse ~10 runs qui ne
# serviront jamais ; sans nettoyage, le quota de runners est consommé par du
# travail mort et les checks requis (branch protection main) ne démarrent pas.
#
# Ce script annule les runs `queued`/`in_progress` dont la branche head n'existe
# plus sur le remote et n'est pas la tête d'une PR ouverte ni la branche par
# défaut. Une branche vivante = runs préservés, même si le run est ancien.
#
# Usage :
#   cancel-orphan-runs.sh <owner/repo> [--dry-run]
# Nécessite `gh` authentifié avec permissions `actions: write` (GITHUB_TOKEN).
# `--dry-run` affiche ce qui serait annulé sans rien modifier.
#
# Durable : exécuté en cron + workflow_dispatch + à la fermeture de PR par
# .github/workflows/cleanup-orphan-runs.yml.

set -euo pipefail

REPO="${1:?usage: cancel-orphan-runs.sh <owner/repo> [--dry-run]}"
DRY_RUN=0
if [[ "${2:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "::error::GH_TOKEN requis (lecture branches/runs + annulation)."
  exit 1
fi

# Branche par défaut (main) : ses runs sont les checks de la branche protégée,
# jamais annulés ici.
DEFAULT_BRANCH=$(gh api "repos/${REPO}" | jq -r '.default_branch')

# 1. Branches vivantes : existent encore sur le remote.
mapfile -t LIVE_BRANCHES < <(
  gh api "repos/${REPO}/branches?per_page=100" --paginate | jq -r '.[].name'
)
# 2. Têtes de branches des PRs ouvertes (une PR ouverte = travail en cours,
# ses runs doivent tourner même si la branche a été supprimée par erreur).
mapfile -t OPEN_PR_HEADS < <(
  gh api "repos/${REPO}/pulls?state=open&per_page=100" --paginate | jq -r '.[].head.ref'
)

PROTECTED_BRANCHES=$(printf '%s\n%s\n%s\n' "${DEFAULT_BRANCH}" "${LIVE_BRANCHES[@]}" "${OPEN_PR_HEADS[@]}" | sort -u)

is_protected() {
  local branch="$1"
  while IFS= read -r b; do
    [[ "${b}" == "${branch}" ]] && return 0
  done <<< "${PROTECTED_BRANCHES}"
  return 1
}

CANCELLED=0
CANCELLED_IDS=""

# 3. Runs `queued` + `pending` + `in_progress` (queued/pending d'abord : ils
# libèrent la file sans interrompre un job en cours ; in_progress sur branche
# morte = runner gâché, annulé aussi — rien ne pourra merger ces résultats).
# `pending` (runs créés mais pas encore affectés à un runner) était omis par la
# version initiale : c'est pourtant l'état majoritaire pendant une saturation.
for STATUS in queued pending in_progress; do
  while IFS=$'\t' read -r run_id branch; do
    if ! is_protected "${branch}"; then
      CANCELLED=$((CANCELLED + 1))
      if [[ -n "${CANCELLED_IDS}" ]]; then
        CANCELLED_IDS+=" "
      fi
      CANCELLED_IDS+="${run_id}"
      echo "orphan run ${run_id} (branch '${branch}', ${STATUS})"
    fi
  done < <(
    gh api "repos/${REPO}/actions/runs?status=${STATUS}&per_page=100" --paginate \
      | jq -r '.workflow_runs[] | [.id, .head_branch] | @tsv'
  )
done

if [[ "${CANCELLED}" -eq 0 ]]; then
  echo "OK: aucun run orphelin (runs queued + in_progress vérifiés)."
  exit 0
fi

echo "---"
if [[ "${DRY_RUN}" -eq 1 ]]; then
  echo "DRY-RUN: ${CANCELLED} run(s) orphelin(s) identifié(s) — rien annulé."
  exit 0
fi

for run_id in ${CANCELLED_IDS}; do
  if gh api -X POST "repos/${REPO}/actions/runs/${run_id}/cancel" --silent; then
    echo "cancelled run ${run_id}"
  else
    # 409 = run déjà dans un état terminal (completed/cancelled) — bénin.
    echo "warning: run ${run_id} non annulable (déjà terminé ?)"
  fi
done

echo "Résumé : ${CANCELLED} run(s) orphelin(s) annulé(s) sur ${REPO}."
