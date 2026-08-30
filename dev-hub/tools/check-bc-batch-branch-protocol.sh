#!/usr/bin/env bash
# check-bc-batch-branch-protocol.sh — Garde du protocole de branches par lot BC.
#
# Vérifie sur le dépôt les règles de `docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md` :
#   - une seule branche bc/<code-bc>-* active par BC (pas deux lots parallèles sur le même BC) ;
#   - claim markers orphelins (branche « claim marker BC-<code> » sans PR, âgée) ;
#   - PRs ouvertes sur bc/* sans aucun mot-clé Closes/Fixes/Resolves #N ;
#   - cohérence de label : toute issue fermée par une PR bc/<code-bc>-* porte le label BC-<code> ;
#   - PRs au-delà du budget proportionnel (~15 fichiers / ~1000 lignes par issue fermée) ;
#   - collisions de préfixes de migrations inter-PR (#1962, --remote) ;
#   - main rouge : dernier SHA de main avec un check requis en échec.
#
# Usage : check-bc-batch-branch-protocol.sh <owner/repo> [--strict] [--max-age-days N]
#   --strict        : sortie 1 dès qu'une violation est détectée (défaut : rapport).
#   --max-age-days N: âge à partir duquel un claim marker orphelin est signalé (défaut 2).
#
# Nécessite `gh` authentifié (GITHUB_TOKEN : branches, pulls, issues, commits, statuses) et `jq`.
# Rôle = rapport par défaut, comme les autres gardes de gouvernance
# (check-crm-branch-protocol.sh, check-issues-closed-without-merge.sh).
set -uo pipefail

REPO="${1:?usage: check-bc-batch-branch-protocol.sh <owner/repo> [--strict] [--max-age-days N]}"
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

say "== Garde protocole branches par lot BC — ${REPO} =="

branch_list=$(gh api "repos/${REPO}/branches" --paginate --jq '.[].name' 2>/dev/null) || {
  err "impossible de lister les branches (gh api branches)"
  branch_list=""
}
open_prs_json=$(gh api "repos/${REPO}/pulls?state=open&per_page=100" --paginate \
  --jq '[.[] | {number, title, body: (.body // ""), head: .head.ref}]' 2>/dev/null)
[ -z "$open_prs_json" ] && open_prs_json="[]"
open_heads=$(printf '%s' "$open_prs_json" | jq -r '.[].head' 2>/dev/null)

# Seules les branches bc/<code>-<slug> relèvent de ce protocole (pas fix/<issue>-*, feat/*, etc.).
bc_branches=$(printf '%s\n' "$branch_list" | grep -E '^bc/[a-z0-9]+-' || true)

# ── 1. une seule branche bc/* active par BC ───────────────────────────────────
say ""
say "── 1. Une seule branche active par BC ──"
bc_codes=$(printf '%s\n' "$bc_branches" | sed -nE 's#^bc/([a-z0-9]+)-.*$#\1#p' | sort -u)
dup_found=0
for code in $bc_codes; do
  [ -z "$code" ] && continue
  branches=$(printf '%s\n' "$bc_branches" | grep -E "^bc/${code}-" || true)
  count=$(printf '%s\n' "$branches" | sed '/^$/d' | wc -l | tr -d ' ')
  [ "$count" -le 1 ] && continue
  branches_with_pr=""
  orphans=""
  for b in $branches; do
    if printf '%s\n' "$open_heads" | grep -qx "$b"; then
      branches_with_pr="$branches_with_pr $b"
    else
      orphans="$orphans $b"
    fi
  done
  if [ -n "$branches_with_pr" ] && [ -n "$orphans" ]; then
    err "BC ${code} : branche(s) canonique(s)$branches_with_pr ET branche(s) sans PR$orphans — un seul lot actif par BC"
    dup_found=1
  elif [ "$count" -gt 1 ] && [ -z "$branches_with_pr" ]; then
    err "BC ${code} : plusieurs branches sans PR ($branches) — conserver un seul lot, purger les autres"
    dup_found=1
  fi
done
[ "$dup_found" -eq 0 ] && say "  OK — au plus une branche active par BC."

# ── 2. claim markers orphelins ────────────────────────────────────────────────
say ""
say "── 2. Claim markers orphelins (âge > ${MAX_AGE} j) ──"
orphan_claims=0
while IFS= read -r br; do
  [ -z "$br" ] && continue
  if printf '%s\n' "$open_heads" | grep -qx "$br"; then
    continue
  fi
  last_msg=$(gh api "repos/${REPO}/commits/${br}" --jq '.commit.message // ""' 2>/dev/null || true)
  case "$last_msg" in
    "claim marker BC-"*)
      last_date=$(gh api "repos/${REPO}/commits/${br}" --jq '.commit.committer.date // ""' 2>/dev/null || true)
      if [ -n "$last_date" ]; then
        last_ts=$(date -u -d "$last_date" +%s 2>/dev/null || date -u -j -f "%Y-%m-%dT%H:%M:%SZ" "$last_date" +%s 2>/dev/null || echo 0)
        age_days=$(( (now - last_ts) / 86400 ))
        if [ "$age_days" -ge "$MAX_AGE" ]; then
          err "claim marker orphelin : ${br} (dernier commit claim il y a ${age_days} j, aucune PR) — libérer ou matérialiser"
          orphan_claims=$((orphan_claims + 1))
        else
          warn "claim marker récent (< ${MAX_AGE} j) : ${br} — lot en cours probable"
        fi
      fi
      ;;
  esac
done <<EOF
$(printf '%s\n' "$bc_branches")
EOF
[ "$orphan_claims" -eq 0 ] && say "  OK — aucun claim marker orphelin."

# ── 3. PRs bc/* sans Closes/Fixes/Resolves ────────────────────────────────────
say ""
say "── 3. PRs bc/* sans mot-clé Closes/Fixes/Resolves #N ──"
pr_missing_close=0
bc_prs_json=$(printf '%s' "$open_prs_json" | jq -c '[.[] | select(.head | test("^bc/[a-z0-9]+-"))]')
while IFS=$'\t' read -r num title body; do
  [ -z "$num" ] && continue
  if ! printf '%s' "$body" | grep -qiE '(clos(es|ed|ing)|fix(es|ed|ing)|resolv(es|ed|ing))[[:space:]]*:?[[:space:]]*#[0-9]+'; then
    err "PR #${num} (« ${title} ») : aucun mot-clé Closes/Fixes/Resolves #N dans le body"
    pr_missing_close=$((pr_missing_close + 1))
  fi
done < <(printf '%s' "$bc_prs_json" | jq -r '.[] | [.number, .title, .body] | @tsv')
[ "$pr_missing_close" -eq 0 ] && say "  OK — toutes les PRs bc/* déclarent au moins une issue à fermer."

# ── 4. cohérence de label BC ───────────────────────────────────────────────────
say ""
say "── 4. Cohérence de label BC (une PR bc/<code>-* ne ferme que des issues BC-<code>) ──"
label_mismatch=0
while IFS=$'\t' read -r num head body; do
  [ -z "$num" ] && continue
  code=$(printf '%s' "$head" | sed -nE 's#^bc/([a-z0-9]+)-.*$#\1#p')
  [ -z "$code" ] && continue
  bc_num=$(printf '%s' "$code" | sed -nE 's#^bc([0-9]+)$#\1#p')
  [ -z "$bc_num" ] && continue
  expected_label="BC-${bc_num}"
  closed_issues=$(printf '%s' "$body" | grep -oiE '(clos(es|ed|ing)|fix(es|ed|ing)|resolv(es|ed|ing))[[:space:]]*:?[[:space:]]*#[0-9]+' \
    | grep -oE '[0-9]+')
  for issue in $closed_issues; do
    labels=$(gh api "repos/${REPO}/issues/${issue}" --jq '[.labels[].name] | join(",")' 2>/dev/null || true)
    if [ -n "$labels" ] && ! printf '%s' "$labels" | grep -q "$expected_label"; then
      err "PR #${num} (branche ${head}) ferme #${issue} mais son label (${labels:-aucun}) ne contient pas ${expected_label} attendu"
      label_mismatch=$((label_mismatch + 1))
    fi
  done
done < <(printf '%s' "$bc_prs_json" | jq -r '.[] | [.number, .head, .body] | @tsv')
[ "$label_mismatch" -eq 0 ] && say "  OK — toutes les issues fermées portent le label BC attendu."

# ── 5. budget de taille proportionnel (~15 fichiers / ~1000 lignes par issue) ─
say ""
say "── 5. Taille des PRs bc/* (budget ~15 fichiers / ~1000 lignes par issue fermée) ──"
pr_too_big=0
while IFS=$'\t' read -r num title body; do
  [ -z "$num" ] && continue
  n_issues=$(printf '%s' "$body" | grep -oiE '(clos(es|ed|ing)|fix(es|ed|ing)|resolv(es|ed|ing))[[:space:]]*:?[[:space:]]*#[0-9]+' | wc -l | tr -d ' ')
  [ "${n_issues:-0}" -eq 0 ] && n_issues=1
  budget_files=$((n_issues * 15))
  budget_lines=$((n_issues * 1000))
  stats=$(gh api "repos/${REPO}/pulls/${num}" --jq '.additions, .deletions, .changed_files' 2>/dev/null || true)
  [ -z "$stats" ] && continue
  additions=$(printf '%s\n' "$stats" | sed -n 1p)
  files=$(printf '%s\n' "$stats" | sed -n 3p)
  if [ "${files:-0}" -gt "$budget_files" ] || [ "${additions:-0}" -gt "$budget_lines" ]; then
    warn "PR #${num} (« ${title} ») : ${files} fichiers/+${additions} lignes pour ${n_issues} issue(s) — budget ${budget_files}f/${budget_lines}l dépassé, envisager de découper"
    pr_too_big=$((pr_too_big + 1))
  fi
done < <(printf '%s' "$bc_prs_json" | jq -r '.[] | [.number, .title, .body] | @tsv')
[ "$pr_too_big" -eq 0 ] && say "  OK — aucune PR bc/* au-delà de son budget proportionnel."

# ── 6. collisions de préfixes de migrations inter-PR (#1962) ─────────────────
say ""
say "── 6. Collisions de migrations inter-PR (#1962) ──"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
if [ -x "${ROOT}/dev-hub/tools/check-migration-basename-collisions.sh" ]; then
  if ! bash "${ROOT}/dev-hub/tools/check-migration-basename-collisions.sh" "${ROOT}/api/database/migrations" --remote >/tmp/bc-batch-migration-guard.out 2>&1; then
    err "collision de préfixes/basenames de migrations inter-PR détectée — voir le log ci-dessous"
    sed 's/^/    /' /tmp/bc-batch-migration-guard.out | tail -20
  else
    say "  OK — aucune collision de migrations."
  fi
else
  warn "check-migration-basename-collisions.sh absent — garde #1962 non exécutée"
fi

# ── 7. main rouge (checks requis) ─────────────────────────────────────────────
say ""
say "── 7. État de main (arrêt si rouge) ──"
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
  warn "impossible de lire main — étape 7 ignorée"
fi

# ── conclusion ────────────────────────────────────────────────────────────────
say ""
if [ "$VIOLATIONS" -eq 0 ]; then
  say "✅ Protocole branches par lot BC : aucune violation détectée."
  exit 0
fi
say "⚠️  ${VIOLATIONS} violation(s) détectée(s)."
if [ "$STRICT" -eq 1 ]; then
  say "Mode --strict : sortie 1."
  exit 1
fi
say "Mode rapport (défaut) : sortie 0 — arbitrage manuel requis."
exit 0
