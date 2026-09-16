#!/usr/bin/env bash
# ============================================================
# report-external-checks.sh — rendre LISIBLE l'état des checks d'une PR
# (issue #7480).
#
# Constat : des checks de déploiement tiers rougissent en permanence pour des
# raisons **extérieures au code** (quota Vercel `api-deployments-free-per-day`,
# `Workers Builds: gestionemploye` côté Cloudflare, revue Strix expirée), et
# `Governance Gates` peut échouer avec un **résumé de check vide** — donc
# invisible. Résultat : un rouge permanent n'informe plus, et on apprend à
# l'ignorer (même classe de défaut que la leçon #3545 : « un skip silencieux
# ressemble à un succès »).
#
# Ce script classe les checks d'une PR en trois familles et dit quoi faire :
#   1. REQUIS   — les 4 checks bloquants du merge (source : BRANCH_PROTECTION_REQUIRED.md) ;
#   2. TIERS    — checks externes (Vercel / Cloudflare / Strix), non requis :
#                 un quota ou une revue expirée n'est PAS un défaut de code ;
#   3. REPO     — autres checks du dépôt en échec : ceux-là peuvent cacher une
#                 vraie régression → motif à lire dans le LOG (le résumé est
#                 parfois vide, cf. #7480).
#
# Usage :
#   dev-hub/tools/report-external-checks.sh <pr-number|commit-sha> [--comment] [--repo owner/name]
#
#   --comment : publie (ou met à jour) UN commentaire de PR marqué
#               `<!-- external-checks-report -->` plutôt que d'écrire sur stdout.
#
# Sortie : 0 toujours (c'est un rapport, pas un gate) — sauf erreur d'usage (2).
# ============================================================
set -uo pipefail

REPO="${REPO:-kitokoh/leopardo-hr}"
COMMENT=0
PR=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --comment) COMMENT=1; shift ;;
    --repo) REPO="${2:-}"; shift 2 ;;
    -h|--help) sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) PR="$1"; shift ;;
  esac
done

if [[ -z "${PR}" ]]; then
  echo "Usage: report-external-checks.sh <pr-number> [--comment] [--repo owner/name]" >&2
  exit 2
fi

if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "::error::GH_TOKEN requis (lecture des checks + commentaire)." >&2
  exit 2
fi

api() { curl -fsS -H "Authorization: Bearer ${GH_TOKEN}" -H "Accept: application/vnd.github+json" "$@"; }

# Les 4 checks requis (BRANCH_PROTECTION_REQUIRED.md) — un échec ici BLOQUE.
REQUIRED_RE='^(PHPStan — Strict \(Core/Modules/Shared, level 8\)|Module Structure Validator|Frontend — ESLint \+ TypeScript|actionlint \(\+ shellcheck\))$'

# Checks externes connus + la raison attendue (documentée dans docs/ops/EXTERNAL_CHECKS_TRIAGE.md).
external_reason() {
  case "$1" in
    Vercel|"Vercel Preview Comments") echo "quota de déploiements Vercel (hors code) — #4868/#7480" ;;
    "Workers Builds:"*) echo "déploiement Cloudflare Workers hors périmètre PR — #7480" ;;
    *trix*) echo "revue de sécurité Strix (essai expiré) — #7480" ;;
    *) echo "" ;;
  esac
}

# `workflow_run` ne connaît que le SHA : accepter un SHA et retrouver la PR.
if [[ "${PR}" =~ ^[0-9a-f]{7,40}$ ]]; then
  resolved="$(api "https://api.github.com/repos/${REPO}/commits/${PR}/pulls" \
    | jq -r '[.[] | select(.base.ref == "main")] | .[0].number // empty')"
  if [[ -z "${resolved}" ]]; then
    echo "SHA ${PR} sans PR ouverte sur main — rien à rapporter."
    exit 0
  fi
  PR="${resolved}"
fi

head_sha="$(api "https://api.github.com/repos/${REPO}/pulls/${PR}" | jq -r .head.sha)"
checks="$(api "https://api.github.com/repos/${REPO}/commits/${head_sha}/check-runs?per_page=100")"

declare -a req_bad=() ext_bad=() repo_bad=() pending=()
while IFS=$'\t' read -r name conclusion status; do
  [[ -z "${name}" ]] && continue
  if [[ "${status}" != "completed" ]]; then
    pending+=("${name}")
    continue
  fi
  # `cancelled` n'est pas un échec (run superseded/annulé) : ne pas le compter.
  [[ "${conclusion}" == "success" || "${conclusion}" == "skipped" || "${conclusion}" == "neutral" || "${conclusion}" == "cancelled" ]] && continue

  if [[ "${name}" =~ ${REQUIRED_RE} ]]; then
    req_bad+=("${name} (${conclusion})")
    continue
  fi

  reason="$(external_reason "${name}")"
  if [[ -n "${reason}" ]]; then
    ext_bad+=("${name} — ${reason}")
  else
    repo_bad+=("${name} (${conclusion})")
  fi
done < <(printf '%s' "${checks}" | jq -r '.check_runs[] | "\(.name)\t\(.conclusion // "-")\t\(.status)"')

verdict="✅ Aucun check en échec."
if [[ ${#req_bad[@]} -gt 0 ]]; then
  verdict="⛔ **Merge bloqué** : ${#req_bad[@]} check(s) REQUIS en échec."
elif [[ ${#repo_bad[@]} -gt 0 ]]; then
  verdict="⚠️ Aucun check requis en échec (merge possible), mais ${#repo_bad[@]} check(s) du dépôt sont rouges — à lire."
elif [[ ${#ext_bad[@]} -gt 0 ]]; then
  verdict="ℹ️ Aucun check requis en échec. Les rouges restants sont **externes** (quota/infra), pas du code."
fi

report="<!-- external-checks-report -->
### État des checks — PR #${PR}

${verdict}

*Applicabilité : les 4 checks requis sont la seule condition de merge (BRANCH_PROTECTION_REQUIRED.md). Un quota de déploiement tiers n'est pas un défaut de code.*

| Famille | Check | Motif / suite à donner |
|---|---|---|"

if [[ ${#req_bad[@]} -gt 0 ]]; then
  for item in "${req_bad[@]}"; do report+=$'\n'"| **REQUIS** | \`${item}\` | à corriger avant merge |"; done
fi
for item in "${repo_bad[@]}"; do
  report+=$'\n'"| REPO | \`${item}\` | lire le **log** du job : le résumé du check peut être vide (#7480) |"
done
for item in "${ext_bad[@]}"; do
  report+=$'\n'"| TIERS | \`${item%% — *}\` | ${item#*— } |"
done
if [[ ${#pending[@]} -gt 0 ]]; then
  report+=$'\n'"| en cours | ${#pending[@]} check(s) non terminé(s) | repasser plus tard |"
fi
if [[ ${#req_bad[@]} -eq 0 && ${#repo_bad[@]} -eq 0 && ${#ext_bad[@]} -eq 0 && ${#pending[@]} -eq 0 ]]; then
  report+=$'\n'"| — | aucun | — |"
fi

report+=$'\n\n'"<sub>Rapport généré par \`dev-hub/tools/report-external-checks.sh\` (issue #7480) — conduite à tenir : \`docs/ops/EXTERNAL_CHECKS_TRIAGE.md\`.</sub>"

if [[ "${COMMENT}" = "1" ]]; then
  existing="$(api "https://api.github.com/repos/${REPO}/issues/${PR}/comments?per_page=100" \
    | jq -r '.[] | select(.body | startswith("<!-- external-checks-report -->")) | .id' | head -1)"
  body="$(jq -n --arg b "${report}" '{body:$b}')"
  if [[ -n "${existing}" ]]; then
    api -X PATCH -H "Content-Type: application/json" -d "${body}" \
      "https://api.github.com/repos/${REPO}/issues/comments/${existing}" >/dev/null
    echo "Rapport mis à jour (commentaire ${existing}) sur #${PR}."
  else
    api -X POST -H "Content-Type: application/json" -d "${body}" \
      "https://api.github.com/repos/${REPO}/issues/${PR}/comments" >/dev/null
    echo "Rapport publié sur #${PR}."
  fi
else
  printf '%s\n' "${report}"
fi

exit 0
