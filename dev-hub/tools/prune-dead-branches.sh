#!/usr/bin/env bash
# =============================================================================
# prune-dead-branches.sh — Issue #2487
#
# Liste (et supprime en --apply) les branches distantes dont la PR est
# `closed` ET non `merged`, et dont l'issue référencée par la PR est fermée
# (résolue/supersédée). Ne touche JAMAIS :
#   - les branches avec une PR ouverte ;
#   - les branches dont la PR fermée référence une issue encore ouverte ;
#   - main.
#
# Usage :
#   GH_TOKEN=xxx ./prune-dead-branches.sh            # dry-run (défaut)
#   GH_TOKEN=xxx ./prune-dead-branches.sh --apply    # suppression réelle
#
# Prérequis : curl, jq, git (remote origin). Token avec scope repo.
# =============================================================================
set -euo pipefail

REPO="${PRUNE_REPO:-kitokoh/leopardo-hr}"
APPLY="${1:-}"
GH="${GH:-https://api.github.com}"

# --- 1. Branches avec PR ouverte (à protéger) ---------------------------------
echo "▶ Récupération des PRs ouvertes..." >&2
open_heads=$(curl -sS -H "Authorization: token ${GH_TOKEN}" -H 'Accept: application/vnd.github+json' \
  "$GH/repos/$REPO/pulls?state=open&per_page=100" | jq -r '.[].head.ref' | sort -u)

echo "▶ Récupération des PRs fermées (toutes pages)..." >&2
: > /tmp/prune_closed.jsonl
for page in 1 2 3 4 5 6 7 8; do
  page_json=$(curl -sS -H "Authorization: token ${GH_TOKEN}" -H 'Accept: application/vnd.github+json' \
    "$GH/repos/$REPO/pulls?state=closed&per_page=100&page=$page")
  count=$(echo "$page_json" | jq 'length')
  echo "$page_json" | jq -c '.[]' >> /tmp/prune_closed.jsonl
  [ "$count" -lt 100 ] && break
done

# --- 2. Branches distantes ----------------------------------------------------
echo "▶ Récupération des branches distantes..." >&2
remote_branches=$(git ls-remote --heads origin 2>/dev/null | awk '{print $2}' \
  | sed 's#^refs/heads/##' | grep -v '^HEAD$' | sort -u)

# --- 3. Décision par branche --------------------------------------------------
delete_candidates=()
kept_protected=0
kept_issue_open=0
kept_no_pr=0

for branch in $remote_branches; do
  [ "$branch" = "main" ] && continue

  # PR ouverte sur cette branche → protégée
  if echo "$open_heads" | grep -qxF "$branch"; then
    kept_protected=$((kept_protected + 1))
    continue
  fi

  # PRs fermées sur cette branche
  prs=$(jq -c --arg b "$branch" 'select(.head.ref == $b)' /tmp/prune_closed.jsonl || true)
  if [ -z "$prs" ]; then
    kept_no_pr=$((kept_no_pr + 1))
    continue
  fi

  # Une PR fermée ET mergée → travail déjà intégré, branche traitée par le flux
  # normal (suppression post-merge). Pas candidate.
  if echo "$prs" | jq -e 'any(.merged == true)' >/dev/null 2>&1; then
    kept_protected=$((kept_protected + 1))
    continue
  fi

  # PR fermée-sans-merge : supprimable uniquement si TOUTES les issues
  # référencées par son corps sont fermées.
  all_closed=1
  for pr_number in $(echo "$prs" | jq -r '.number'); do
    pr_body=$(echo "$prs" | jq -r --argjson n "$pr_number" 'select(.number == $n) | .body // ""')
    issues=$(echo "$pr_body" | grep -oE '(Closes|closes|Fixes|fixes|Resolves|resolves) #?[0-9]+' | grep -oE '[0-9]+' | sort -u || true)
    for issue in $issues; do
      state=$(curl -sS -H "Authorization: token ${GH_TOKEN}" -H 'Accept: application/vnd.github+json' \
        "$GH/repos/$REPO/issues/$issue" | jq -r '.state // "open"')
      if [ "$state" != "closed" ]; then
        all_closed=0
        echo "  ↳ branche $branch : issue #$issue encore ouverte → conservée" >&2
        break
      fi
    done
    [ "$all_closed" = 0 ] && break
  done

  if [ "$all_closed" = 1 ]; then
    delete_candidates+=("$branch")
  else
    kept_issue_open=$((kept_issue_open + 1))
  fi
done

# --- 4. Rapport ---------------------------------------------------------------
echo
echo "═══════════════════════════════════════════════════════════════"
echo "  Branches à supprimer (PR fermée-sans-merge + issues fermées) : ${#delete_candidates[@]}"
echo "  Conservées (PR ouverte / PR mergée)                          : $kept_protected"
echo "  Conservées (issue référencée encore ouverte)                 : $kept_issue_open"
echo "  Conservées (aucune PR connue)                                : $kept_no_pr"
echo "═══════════════════════════════════════════════════════════════"
for b in "${delete_candidates[@]}"; do
  echo "  - $b"
done

if [ "${APPLY}" != "--apply" ]; then
  echo
  echo "⏸ Dry-run : rien supprimé. Relancez avec --apply pour exécuter."
  exit 0
fi

# --- 5. Suppression -----------------------------------------------------------
echo
for b in "${delete_candidates[@]}"; do
  echo "🗑  git push origin --delete $b"
  git push origin --delete "$b" || echo "  ⚠ échec (branche déjà supprimée ?)"
done
echo "✅ Terminé."
