#!/usr/bin/env bash
# check-crm-branch-protocol.sh — Garde du protocole de branches CRM (issue #5746).
#
# Vérifie sur le dépôt les règles du protocole CRM-verrouillé
# (`docs/GOUVERNANCE/CRM_BRANCH_PROTOCOL.md`) :
#   - doublons de branches par issue (une issue = une branche = une PR, #2400) ;
#   - claim markers orphelins (branche « claim marker #N » sans PR, âgée) ;
#   - PRs ouvertes sans `Closes #N` / `Fixes #N` dans le body (#2512) ;
#   - PRs au-delà de la taille max (40 fichiers / +2 500 lignes) ;
#   - collisions de préfixes de migrations inter-PR (#1962, --remote) ;
#   - `main` rouge : dernier SHA de main avec un check requis en échec.
#
# Usage : check-crm-branch-protocol.sh <owner/repo> [--strict] [--max-age-days N]
#   --strict        : sortie 1 dès qu'une violation est détectée (défaut : rapport).
#   --max-age-days N: âge à partir duquel un claim marker orphelin est signalé
#                     (défaut 2, aligné sur cleanup-stale-branches.sh #5506).
#
# Nécessite `gh` authentifié (GITHUB_TOKEN : branches, pulls, commits,
# statuses) et `jq`. Rôle = rapport par défaut (comme les autres gardes de
# gouvernance : check-issues-closed-without-merge.sh, etc.).
set -uo pipefail

REPO="${1:?usage: check-crm-branch-protocol.sh <owner/repo> [--strict] [--max-age-days N]}"
STRICT=0
MAX_AGE=2
for opt in "${@:2}"; do
  case "$opt" in
    --strict) STRICT=1 ;;
    --max-age-days=*) MAX_AGE="${opt#*=}" ;;
  esac
done

VIOLATIONS=0
now=$(date +%s)

say()  { printf '%s\n' "$*"; }
warn() { printf '::warning::%s\n' "$*"; }
err()  { printf '::error::%s\n' "$*"; VIOLATIONS=$((VIOLATIONS + 1)); }

say "== Garde protocole branches CRM (#5746) — ${REPO} =="

# ── 1. doublons de branches par issue ─────────────────────────────────────────
say ""
say "── 1. Doublons de branches par issue (#2400) ──"
branch_list=$(gh api "repos/${REPO}/branches" --paginate --jq '.[].name' 2>/dev/null) || {
  err "impossible de lister les branches (gh api branches)"
  branch_list=""
}
open_heads=$(gh api "repos/${REPO}/pulls?state=open&per_page=100" --paginate --jq '.[].head.ref' 2>/dev/null)

# Associe chaque branche à son issue (pattern <prefix>/<issue>-<slug>).
issues=$(printf '%s\n' "$branch_list" | sed -nE 's#^[a-z]+/([0-9]+)-.*$#\1#p' | sort -u)

dup_found=0
for issue in $issues; do
  branches=$(printf '%s\n' "$branch_list" | grep -E "^[a-z]+/${issue}-" || true)
  count=$(printf '%s\n' "$branches" | sed '/^$/d' | wc -l | tr -d ' ')
  [ "$count" -le 1 ] && continue
  # Une PR ouverte pour au moins une de ces branches = doublon si une autre
  # branche de la même issue n'a PAS de PR ouverte (travail orphelin) OU si
  # deux branches ont du contenu réel.
  branches_with_pr=""
  for b in $branches; do
    if printf '%s\n' "$open_heads" | grep -qx "$b"; then
      branches_with_pr="$branches_with_pr $b"
    fi
  done
  orphans=""
  for b in $branches; do
    if ! printf '%s\n' "$open_heads" | grep -qx "$b"; then
      orphans="$orphans $b"
    fi
  done
  if [ -n "$branches_with_pr" ] && [ -n "$orphans" ]; then
    err "issue #${issue} : branche(s) canonique(s)$branches_with_pr ET branche(s) sans PR$orphans — doublon de travail (#2400)"
    dup_found=1
  elif [ "$count" -gt 1 ] && [ -z "$branches_with_pr" ]; then
    # Distinguer les locks de claim sans contenu (hygiène) des doublons réels.
    real_orphans=""
    for b in $orphans; do
      last_msg=$(gh api "repos/${REPO}/commits/${b}" --jq '.commit.message // ""' 2>/dev/null || true)
      case "$last_msg" in
        "claim marker #"*) ;;
        *) real_orphans="$real_orphans $b" ;;
      esac
    done
    if [ -n "$real_orphans" ]; then
      err "issue #${issue} : branches avec contenu sans PR$real_orphans — prendre/contribuer sur UNE seule branche (#2400)"
      dup_found=1
    else
      warn "issue #${issue} : $count branches de claim sans contenu ($branches) — conserver la première, purger les autres (cleanup #5506)"
    fi
  fi
done
[ "$dup_found" -eq 0 ] && say "  OK — aucune branche dupliquée par issue."

# ── 2. claim markers orphelins ────────────────────────────────────────────────
say ""
say "── 2. Claim markers orphelins (#2400, âge > ${MAX_AGE} j) ──"
orphan_claims=0
while IFS= read -r br; do
  [ -z "$br" ] && continue
  case "$br" in main|gh-pages) continue ;; esac
  if printf '%s\n' "$open_heads" | grep -qx "$br"; then
    continue  # PR ouverte : pas orphelin
  fi
  last_msg=$(gh api "repos/${REPO}/commits/${br}" --jq '.commit.message // ""' 2>/dev/null || true)
  case "$last_msg" in
    "claim marker #"*)
      last_date=$(gh api "repos/${REPO}/commits/${br}" --jq '.commit.committer.date // ""' 2>/dev/null || true)
      if [ -n "$last_date" ]; then
        last_ts=$(date -u -d "$last_date" +%s 2>/dev/null || date -u -j -f "%Y-%m-%dT%H:%M:%SZ" "$last_date" +%s 2>/dev/null || echo 0)
        age_days=$(( (now - last_ts) / 86400 ))
        if [ "$age_days" -ge "$MAX_AGE" ]; then
          err "claim marker orphelin : ${br} (dernier commit claim il y a ${age_days} j, aucune PR) — libérer ou matérialiser"
          orphan_claims=$((orphan_claims + 1))
        else
          warn "claim marker récent (< ${MAX_AGE} j) : ${br} — lock en cours probable"
        fi
      fi
      ;;
  esac
done <<EOF
$(printf '%s\n' "$branch_list")
EOF
[ "$orphan_claims" -eq 0 ] && say "  OK — aucun claim marker orphelin."

# ── 3. PRs ouvertes sans Closes/Fixes dans le body (#2512) ───────────────────
say ""
say "── 3. PRs ouvertes sans mot-clé Closes #N / Fixes #N (#2512) ──"
pr_missing_close=0
while IFS=$'\t' read -r num title body; do
  if ! printf '%s' "$body" | grep -qiE '(clos(es|ed|ing)|fix(es|ed|ing)|resolv(es|ed|ing))[[:space:]]*:?[[:space:]]*#[0-9]+'; then
    err "PR #${num} (« ${title} ») : aucun mot-clé Closes/Fixes/Resolves #N dans le body — l'issue restera ouverte au merge"
    pr_missing_close=$((pr_missing_close + 1))
  fi
done < <(gh api "repos/${REPO}/pulls?state=open&per_page=100" --paginate \
  --jq '.[] | [.number, .title, (.body // "")] | @tsv')
[ "$pr_missing_close" -eq 0 ] && say "  OK — toutes les PRs ouvertes déclarent une issue à fermer."

# ── 4. taille des PRs ouvertes ────────────────────────────────────────────────
say ""
say "── 4. Taille des PRs ouvertes (max 40 fichiers / +2 500 lignes) ──"
pr_too_big=0
while IFS=$'\t' read -r num title; do
  stats=$(gh api "repos/${REPO}/pulls/${num}" --jq '.additions, .deletions, .changed_files' 2>/dev/null || true)
  [ -z "$stats" ] && continue
  additions=$(printf '%s\n' "$stats" | sed -n 1p)
  deletions=$(printf '%s\n' "$stats" | sed -n 2p)
  files=$(printf '%s\n' "$stats" | sed -n 3p)
  if [ "${files:-0}" -gt 40 ] || [ "${additions:-0}" -gt 2500 ]; then
    warn "PR #${num} (« ${title} ») : ${files} fichiers, +${additions}/-${deletions} lignes — découper ou justifier sur l'issue"
    pr_too_big=$((pr_too_big + 1))
  fi
done < <(gh api "repos/${REPO}/pulls?state=open&per_page=100" --paginate \
  --jq '.[] | [.number, .title] | @tsv')
[ "$pr_too_big" -eq 0 ] && say "  OK — aucune PR au-delà de la taille max."

# ── 5. collisions de préfixes de migrations inter-PR (#1962) ─────────────────
say ""
say "── 5. Collisions de migrations inter-PR (#1962) ──"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
if [ -x "${ROOT}/dev-hub/tools/check-migration-basename-collisions.sh" ]; then
  if ! bash "${ROOT}/dev-hub/tools/check-migration-basename-collisions.sh" "${ROOT}/api/database/migrations" --remote >/tmp/crm-migration-guard.out 2>&1; then
    err "collision de préfixes/basenames de migrations inter-PR détectée — voir le log ci-dessous"
    sed 's/^/    /' /tmp/crm-migration-guard.out | tail -20
  else
    say "  OK — aucune collision de migrations."
  fi
else
  warn "check-migration-basename-collisions.sh absent — garde #1962 non exécutée"
fi

# ── 6. main rouge (checks requis) ─────────────────────────────────────────────
say ""
say "── 6. État de main (arrêt si rouge) ──"
required_checks="Backend Coverage (PHP 8.4 + PostgreSQL 16)
PHPStan — Strict (Core/Modules/Shared, level 8)
Module Structure Validator
Frontend — ESLint + TypeScript
actionlint (+ shellcheck)"
main_sha=$(gh api "repos/${REPO}/commits/main" --jq '.sha' 2>/dev/null || true)
if [ -n "$main_sha" ]; then
  main_red=0
  while IFS= read -r check; do
    [ -z "$check" ] && continue
    state=$(gh api "repos/${REPO}/commits/${main_sha}/check-runs" --jq \
      --arg name "$check" '.check_runs[] | select(.name == $name) | .conclusion' 2>/dev/null | sort -u | tr '\n' ' ')
    if [ -n "$state" ] && printf '%s' "$state" | grep -qE 'failure|timed_out|action_required|cancelled'; then
      err "MAIN ROUGE : check requis « ${check} » en échec sur ${main_sha:0:7} — ne pas merger tant que main n'est pas vert"
      main_red=1
    fi
  done <<EOF
${required_checks}
EOF
  [ "$main_red" -eq 0 ] && say "  OK — aucun check requis en échec sur main (${main_sha:0:7})."
else
  warn "impossible de lire main — étape 6 ignorée"
fi

# ── conclusion ────────────────────────────────────────────────────────────────
say ""
if [ "$VIOLATIONS" -eq 0 ]; then
  say "✅ Protocole branches CRM (#5746) : aucune violation détectée."
  exit 0
fi
say "⚠️  ${VIOLATIONS} violation(s) détectée(s)."
if [ "$STRICT" -eq 1 ]; then
  say "Mode --strict : sortie 1."
  exit 1
fi
say "Mode rapport (défaut) : sortie 0 — arbitrage manuel requis."
exit 0
