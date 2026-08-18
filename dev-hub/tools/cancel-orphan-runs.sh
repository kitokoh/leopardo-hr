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
# Issue #2540 : option `--superseded` — en plus des orphelins, annule les runs
# `queued` SUPERSÉDÉS : pour chaque couple (branche, workflow), tous les runs
# queued sauf le plus récent sont annulés. Complète le `concurrency` natif des
# workflows (qui n'annule les runs que lorsque le NOUVEAU run démarre — les
# runs queued d'un push précédent restent sinon dans la file).
#
# Usage :
#   cancel-orphan-runs.sh <owner/repo> [--dry-run] [--superseded]
# Nécessite `gh` authentifié avec permissions `actions: write` (GITHUB_TOKEN).
# `--dry-run` affiche ce qui serait annulé sans rien modifier.
#
# Durable : exécuté en cron + workflow_dispatch + à la fermeture de PR par
# .github/workflows/cleanup-orphan-runs.yml.

set -euo pipefail

REPO="${1:?usage: cancel-orphan-runs.sh <owner/repo> [--dry-run] [--superseded]}"
DRY_RUN=0
SUPERSEDED=0
for OPT in "${@:2}"; do
  case "${OPT}" in
    --dry-run) DRY_RUN=1 ;;
    --superseded) SUPERSEDED=1 ;;
  esac
done

if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "::error::GH_TOKEN requis (lecture branches/runs + annulation)."
  exit 1
fi

# GitHub CLI peut colorer les réponses JSON même lorsqu’elles sont redirigées
# dans certains runners/agents. jq refuse alors les octets ANSI. Centraliser le
# nettoyage protège toutes les lectures API du script sans toucher aux payloads.
strip_ansi() {
  sed $'s/\033\[[0-9;]*m//g'
}

# Branche par défaut (main) : ses runs sont les checks de la branche protégée,
# jamais annulés ici.
DEFAULT_BRANCH=$(gh api "repos/${REPO}" | strip_ansi | jq -r '.default_branch')

# Branches protégées : branche par défaut + TOUTES les branches vivantes du
# remote + têtes de PR ouvertes. Une branche distante sans PR peut être
# abandonnée, mais tant qu'elle existe sur le remote son travail en file n'est
# pas considéré comme orphelin (issue #5032) : seules les branches SUPPRIMÉES
# (plus sur le remote) et sans PR ouverte voient leurs runs annulés.
mapfile -t REMOTE_BRANCHES < <(
    gh api "repos/${REPO}/branches?per_page=100" --paginate | strip_ansi | jq -s -r 'add | .[].name'
)
mapfile -t OPEN_PR_HEADS < <(
    gh api "repos/${REPO}/pulls?state=open&per_page=100" --paginate | strip_ansi | jq -s -r 'add | .[].head.ref'
)

PROTECTED_BRANCHES=$(printf '%s\n%s\n%s\n' "${DEFAULT_BRANCH}" "${REMOTE_BRANCHES[@]}" "${OPEN_PR_HEADS[@]}" | sort -u)

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
      | strip_ansi \
      | jq -s -r 'map(.workflow_runs[])[] | [.id, .head_branch] | @tsv'
  )
done

# Issue #2540 — runs QUEUED supersédés : pour chaque (branche, workflow),
# tous les runs queued sauf le plus récent. Issue #5032 : la même garde que le
# mode orphelin s’applique — les branches protégées (main, branches vivantes du
# remote, têtes de PR ouvertes) ne perdent JAMAIS de runs ici ; seules les
# branches supprimées sans PR ouverte voient leurs runs queued supersédés
# annulés (le run le plus récent de chaque couple reste toujours conservé).
SUPERSEDED_CANCELLED=0
if [[ "${SUPERSEDED}" -eq 1 ]]; then
  # [run_id, branch, workflow_name, created_at] pour les runs queued.
  declare -A NEWEST_TS
  declare -A RUN_BRANCH
  declare -A RUN_WF
  declare -A RUN_TS
  while IFS=$'\t' read -r run_id branch wf ts; do
    RUN_BRANCH["${run_id}"]="${branch}"
    RUN_WF["${run_id}"]="${wf}"
    RUN_TS["${run_id}"]="${ts}"
    key="${branch}\u0001${wf}"
    if [[ -z "${NEWEST_TS["${key}"]:-}" || "${ts}" > "${NEWEST_TS["${key}"]}" ]]; then
      NEWEST_TS["${key}"]="${ts}"
    fi
  done < <(
    gh api "repos/${REPO}/actions/runs?status=queued&per_page=100" --paginate \
      | strip_ansi \
      | jq -s -r 'map(.workflow_runs[])[] | [.id, .head_branch, .name, .created_at] | @tsv'
  )
  for run_id in "${!RUN_BRANCH[@]}"; do
    branch="${RUN_BRANCH["${run_id}"]}"
    wf="${RUN_WF["${run_id}"]}"
    key="${branch}\u0001${wf}"
    # Issue #5032 : ne jamais annuler les runs supersédés d'une branche
    # protégée — le nettoyage ne touche que le travail réellement orphelin.
    if is_protected "${branch}"; then
      continue
    fi
    if [[ "${RUN_TS["${run_id}"]}" != "${NEWEST_TS["${key}"]}" ]]; then
      # Garde : le run le plus récent de chaque couple branche/workflow reste actif,
      # y compris sur main ; seuls les doublons plus anciens sont annulés.
      SUPERSEDED_CANCELLED=$((SUPERSEDED_CANCELLED + 1))
      echo "superseded queued run ${run_id} (branch '${branch}', workflow '${wf}')"
      if [[ "${DRY_RUN}" -eq 0 ]]; then
        gh api -X POST "repos/${REPO}/actions/runs/${run_id}/cancel" --silent || true
      fi
    fi
  done
  if [[ "${SUPERSEDED_CANCELLED}" -gt 0 ]]; then
    echo "Résumé supersédés : ${SUPERSEDED_CANCELLED} run(s) queued supersédé(s) traité(s)."
  fi
fi

if [[ "${CANCELLED}" -eq 0 && "${SUPERSEDED_CANCELLED}" -eq 0 ]]; then
  echo "OK: aucun run orphelin ni supersédé (runs queued + in_progress vérifiés)."
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
