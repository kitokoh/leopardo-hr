#!/usr/bin/env bash
# cleanup-stale-branches.sh — Hygiène des branches distantes (issue #5506).
#
# Pourquoi : les branches des PRs mergées/fermées et les « locks de claim »
# orphelins (#2400 : branche avec uniquement un commit vide « claim marker #N »,
# jamais matérialisée en PR) s'accumulent — 57 branches supprimées manuellement
# le 2026-08-25. GitHub n'a pas l'auto-delete activé sur `main`.
#
# Règles (sécuritaires) :
#   - SUPPRIME les branches dont la PR est CLOSED (merged ou non) ;
#   - SUPPRIME les branches ORPHELINES (aucune PR ouverte) dont le dernier
#     commit est un commit de claim vide (message =~ /claim marker #/) ET dont
#     l'âge > MAX_AGE jours (un lock récent peut être un agent en cours
#     d'installation) ;
#   - PRÉSERVE : main, gh-pages, toutes les branches des PRs ouvertes, et toute
#     branche orpheline avec un contenu réel (dernier commit ≠ claim marker).
#
# Usage : cleanup-stale-branches.sh <owner/repo> [--dry-run] [--max-age-days N]
#   --dry-run (défaut dans le workflow) : rapporte sans supprimer.
# Requiert `gh` authentifié (GITHUB_TOKEN : lecture pulls/branches/commits +
# écriture refs).
set -uo pipefail

REPO="${1:?usage: cleanup-stale-branches.sh <owner/repo> [--dry-run] [--max-age-days N]}"
DRY_RUN=0
MAX_AGE=2
for opt in "${@:2}"; do
  case "$opt" in
    --dry-run) DRY_RUN=1 ;;
    --max-age-days=*) MAX_AGE="${opt#*=}" ;;
  esac
done

now=$(date +%s)
deleted=0
skipped=0

# ── 1. branches du remote ─────────────────────────────────────────────────────
branches_json=$(gh api "repos/${REPO}/branches" --paginate --jq '[.[] | {name, sha: .commit.sha}]')
branch_names=$(echo "$branches_json" | jq -r '.[].name')

# ── 2. heads des PRs ouvertes (à préserver) ───────────────────────────────────
open_heads=$(gh api "repos/${REPO}/pulls" -f state=open -f per_page=100 --paginate \
  --jq '.[].head.ref')

# ── 3. branches dont la PR est closed ─────────────────────────────────────────
closed_heads=""
for page in 1 2 3 4 5 6 7 8; do
  page_json=$(gh api "repos/${REPO}/pulls" -f state=closed -f per_page=100 -f page=$page 2>/dev/null \
    --jq '.[] | .head.ref' 2>/dev/null) || break
  [ -z "$page_json" ] && break
  closed_heads+=$page_json$'\n'
done
closed_heads=$(printf '%s' "$closed_heads" | sort -u)

# ── 4. suppression ────────────────────────────────────────────────────────────
while IFS= read -r br; do
  [ -z "$br" ] && continue
  case "$br" in main|gh-pages) continue ;; esac
  if printf '%s\n' "$open_heads" | grep -qx "$br"; then
    skipped=$((skipped + 1)); continue
  fi
  # PR closed ?
  if printf '%s\n' "$closed_heads" | grep -qx "$br"; then
    if [ "$DRY_RUN" -eq 1 ]; then
      echo "  [dry-run] supprimerait ${br} (PR closed)"
    else
      gh api -X DELETE "repos/${REPO}/git/refs/heads/${br}" >/dev/null 2>&1 \
        && echo "  supprimée: ${br} (PR closed)" && deleted=$((deleted + 1)) \
        || echo "  !! échec suppression ${br}"
    fi
    continue
  fi
  # orpheline : dernier commit = claim marker vide + âge > MAX_AGE ?
  sha=$(printf '%s\n' "$branches_json" | jq -r --arg b "$br" '.[] | select(.name == $b) | .sha')
  [ -z "$sha" ] && continue
  commit_json=$(gh api "repos/${REPO}/commits/${sha}" --jq '{message: .commit.message, date: .commit.author.date}' 2>/dev/null) || continue
  msg=$(echo "$commit_json" | jq -r '.message // ""')
  date=$(echo "$commit_json" | jq -r '.date // ""')
  case "$msg" in
    "claim marker #"*) ;;
    *) skipped=$((skipped + 1)); continue ;;  # contenu réel → préserver
  esac
  # âge
  ts=$(date -d "$date" +%s 2>/dev/null || echo 0)
  [ "$ts" -eq 0 ] && continue
  age_days=$(( (now - ts) / 86400 ))
  if [ "$age_days" -lt "$MAX_AGE" ]; then
    skipped=$((skipped + 1)); continue
  fi
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "  [dry-run] supprimerait ${br} (lock de claim orphelin, ${age_days} j)"
  else
    gh api -X DELETE "repos/${REPO}/git/refs/heads/${br}" >/dev/null 2>&1 \
      && echo "  supprimée: ${br} (lock de claim orphelin)" && deleted=$((deleted + 1)) \
      || echo "  !! échec suppression ${br}"
  fi
done < <(printf '%s\n' "$branch_names")

echo "Branches : ${deleted} supprimée(s), ${skipped} préservée(s) (mode $([ $DRY_RUN -eq 1 ] && echo dry-run || echo réel))."
exit 0
