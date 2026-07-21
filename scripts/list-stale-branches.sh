#!/usr/bin/env bash
#
# PA2-AUTO-009: liste les branches distantes fusionnees dans la branche de
# base (par defaut `main`) et/ou stale (aucun commit depuis N jours), pour
# aider a la suppression manuelle controlee. N'effectue AUCUNE suppression
# automatique par design (protocole multi-agent, docs/PLAN_ACTION2/01_MODE_EXECUTION_MULTI_AGENT.md,
# n'autorise que le merge de PR verifiees comme preuve de livraison ;
# supprimer une branche sans confirmation humaine pourrait detruire le
# travail en cours d'un agent qui n'a pas encore ouvert de PR).
#
# Deux categories rapportees separement:
#   - MERGED: branches dont tous les commits sont deja dans la branche de
#     base (safe a supprimer, aucune perte de travail possible).
#   - STALE (non fusionnee): branches non fusionnees sans commit depuis
#     --stale-days jours (defaut 30) — a examiner manuellement (peut etre
#     un travail abandonne, ou une PR draft toujours active mais lente).
#
# Usage:
#   scripts/list-stale-branches.sh [--remote origin] [--base main]
#     [--stale-days 30] [--delete-merged]
#
# --delete-merged: apres confirmation interactive (y/N) par branche,
# supprime les branches MERGED sur le remote (git push <remote> --delete).
# Ne touche jamais aux branches STALE non fusionnees (necessite une
# decision humaine explicite hors de ce script — cf. protocole d'abandon
# de tache dans 01_MODE_EXECUTION_MULTI_AGENT.md).

set -euo pipefail

REMOTE="origin"
BASE_BRANCH="main"
STALE_DAYS=30
DELETE_MERGED=0

usage() {
  cat <<'EOF'
Usage: scripts/list-stale-branches.sh [options]

Options:
  --remote NAME        Remote git a inspecter (defaut: origin)
  --base BRANCH        Branche de base pour le test de fusion (defaut: main)
  --stale-days N        Seuil d'inactivite en jours pour les branches non fusionnees (defaut: 30)
  --delete-merged       Propose interactivement (y/N par branche) de supprimer les branches fusionnees sur le remote
  -h, --help             Affiche cette aide
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --remote) REMOTE="$2"; shift 2 ;;
    --base) BASE_BRANCH="$2"; shift 2 ;;
    --stale-days) STALE_DAYS="$2"; shift 2 ;;
    --delete-merged) DELETE_MERGED=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Option inconnue: $1" >&2; usage >&2; exit 1 ;;
  esac
done

git fetch --prune "$REMOTE" >/dev/null 2>&1 || true

base_ref="${REMOTE}/${BASE_BRANCH}"
if ! git rev-parse --verify "$base_ref" >/dev/null 2>&1; then
  echo "Branche de base introuvable: $base_ref" >&2
  exit 1
fi

now_epoch="$(date +%s)"
stale_seconds=$((STALE_DAYS * 86400))

mapfile -t remote_branches < <(
  git for-each-ref --format='%(refname:short)' "refs/remotes/${REMOTE}" \
    | grep -v -E "^${REMOTE}(/HEAD)?\$" \
    | grep -v -E "^${REMOTE}/${BASE_BRANCH}\$" \
    | sort
)

if [[ ${#remote_branches[@]} -eq 0 ]]; then
  echo "Aucune branche distante (hors ${BASE_BRANCH}/HEAD) sur ${REMOTE}."
  exit 0
fi

merged_branches=()
stale_unmerged=()
active_unmerged=()

for branch in "${remote_branches[@]}"; do
  short_name="${branch#"${REMOTE}"/}"
  last_commit_epoch="$(git log -1 --format=%ct "$branch" 2>/dev/null || echo 0)"
  age_seconds=$((now_epoch - last_commit_epoch))
  age_days=$((age_seconds / 86400))

  if git merge-base --is-ancestor "$branch" "$base_ref" 2>/dev/null; then
    merged_branches+=("$short_name (${age_days}j)")
  elif [[ "$age_seconds" -ge "$stale_seconds" ]]; then
    stale_unmerged+=("$short_name (${age_days}j sans commit, non fusionnee)")
  else
    active_unmerged+=("$short_name (${age_days}j, non fusionnee, active)")
  fi
done

echo "=== Branches fusionnees dans ${base_ref} (safe a supprimer) ==="
if [[ ${#merged_branches[@]} -eq 0 ]]; then
  echo "(aucune)"
else
  printf '  %s\n' "${merged_branches[@]}"
fi

echo ""
echo "=== Branches non fusionnees stale (>= ${STALE_DAYS}j sans commit, a examiner manuellement) ==="
if [[ ${#stale_unmerged[@]} -eq 0 ]]; then
  echo "(aucune)"
else
  printf '  %s\n' "${stale_unmerged[@]}"
fi

echo ""
echo "=== Branches non fusionnees actives (< ${STALE_DAYS}j, laissees intactes) ==="
if [[ ${#active_unmerged[@]} -eq 0 ]]; then
  echo "(aucune)"
else
  printf '  %s\n' "${active_unmerged[@]}"
fi

if [[ "$DELETE_MERGED" -eq 1 && ${#merged_branches[@]} -gt 0 ]]; then
  echo ""
  echo "=== Suppression interactive des branches fusionnees ==="
  for entry in "${merged_branches[@]}"; do
    branch_name="${entry%% *}"
    read -r -p "Supprimer ${REMOTE}/${branch_name} ? [y/N] " answer
    if [[ "$answer" =~ ^[Yy]$ ]]; then
      git push "$REMOTE" --delete "$branch_name"
    else
      echo "  Ignoree: ${branch_name}"
    fi
  done
fi
