#!/usr/bin/env bash
# Issue #2011 — garde de la protection de la branche main.
#
# Incident 2026-08-14 : lors de la vague de merges, la protection de main
# s'est retrouvée vidée (required_status_checks supprimé + reviews passées à
# 0) — un outillage de merge fait un GET→PUT de la protection et le PUT
# écrase les champs non ré-injectés. Des merges SANS checks verts ont alors
# été possibles sur un repo public.
#
# Cette garde compare la configuration RÉELLE de la protection de main au
# canonique committé (branch-protection-canonical.json) et fait échouer le
# run CI si elle dévie.
#
# Usage : dev-hub/tools/check-branch-protection.sh [OWNER/REPO] [BRANCHE]
#   défauts : kitokoh/leopardo-hr, main
set -euo pipefail

REPO="${1:-kitokoh/leopardo-hr}"
BRANCH="${2:-main}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CANONICAL_FILE="${ROOT}/dev-hub/tools/branch-protection-canonical.json"

if [[ ! -f "${CANONICAL_FILE}" ]]; then
  echo "::error::Fichier canonique introuvable : ${CANONICAL_FILE}"
  exit 1
fi

# Issue #7480 — override de test : le self-test
# (dev-hub/tools/check-branch-protection-test.sh) rejoue la garde sur une
# protection simulée, sans dépendre de l'API ni d'un token admin. En CI la
# variable est absente et la protection est bien lue en direct.
FIXTURE="${BRANCH_PROTECTION_FIXTURE:-}"

if [[ -n "${FIXTURE}" ]]; then
  if [[ ! -f "${FIXTURE}" ]]; then
    echo "::error::Fixture de protection introuvable : ${FIXTURE}"
    exit 1
  fi
  cp "${FIXTURE}" /tmp/bp-real.json
else
  if [[ -z "${GITHUB_TOKEN:-}" ]]; then
    echo "::error::GITHUB_TOKEN requis (lecture de la protection de branche)."
    exit 1
  fi

  # GET la protection réelle. 404 = protection absente (déviation maximale).
  HTTP=$(curl -s -o /tmp/bp-real.json -w '%{http_code}' \
    -H "Authorization: Bearer ${GITHUB_TOKEN}" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/${REPO}/branches/${BRANCH}/protection" || true)

  if [[ "${HTTP}" == "404" ]]; then
    echo "::error::La branche ${BRANCH} n'a AUCUNE protection — déviation critique (issue #2011)."
    exit 1
  fi
  if [[ "${HTTP}" != "200" ]]; then
    echo "::error::GET protection impossible (HTTP ${HTTP})."
    exit 1
  fi
fi

# Extraire les champs contrôlés (normalisation : l'API renvoie des objets
# {enabled: bool} pour force-push/deletions, le canonique des booléens).
REAL=$(jq -c '{strict: .required_status_checks.strict, contexts: [.required_status_checks.contexts[]], enforce_admins: .enforce_admins.enabled, reviews: .required_pull_request_reviews.required_approving_review_count, allow_force_pushes: (.allow_force_pushes.enabled // false), allow_deletions: (.allow_deletions.enabled // false)}' /tmp/bp-real.json)
CANON=$(jq -c '{strict: .required_status_checks.strict, contexts: [.required_status_checks.contexts[]], enforce_admins: .enforce_admins, reviews: .required_pull_request_reviews.required_approving_review_count, allow_force_pushes: .allow_force_pushes, allow_deletions: .allow_deletions}' "${CANONICAL_FILE}")


# ── Invariant #7480 : un check tiers n'est JAMAIS requis au merge ─────────────
# Un check posé par une application tierce (Vercel, Cloudflare Workers/Pages,
# revue de sécurité externe) échoue sur un quota ou un abonnement, pas sur le
# code. Le rendre requis ferait qu'une PR saine ne peut plus merger — c'est le
# défaut que l'issue #7480 décrit. L'invariant est vérifié des deux côtés :
#   - le canonique ne doit pas le lister (sinon la remédiation le réinstalle) ;
#   - la protection réelle ne doit pas le lister (sinon une dérive admin passe).
real_contexts=$(jq -r '.contexts[]' <<<"${REAL}")
canon_contexts=$(jq -r '.contexts[]' <<<"${CANON}")
third_party_violations=0
while IFS= read -r tier; do
  [[ -z "${tier}" ]] && continue
  if grep -Fqi -- "${tier}" <<<"${canon_contexts}"; then
    echo "::error::Le canonique déclare le check tiers « ${tier} » comme requis — un quota tiers bloquerait alors une PR saine (issue #7480)."
    third_party_violations=$((third_party_violations + 1))
  fi
  if grep -Fqi -- "${tier}" <<<"${real_contexts}"; then
    echo "::error::La protection de ${BRANCH} exige le check tiers « ${tier} » — un échec de quota tiers bloquerait une PR saine (issue #7480). Le retirer de required_status_checks."
    third_party_violations=$((third_party_violations + 1))
  fi
done < <(jq -r '.third_party_checks_never_required[]? // empty' "${CANONICAL_FILE}")

if [[ "${third_party_violations}" -gt 0 ]]; then
  exit 1
fi

if [[ "${REAL}" != "${CANON}" ]]; then
  echo "::error::Protection de ${BRANCH} DEVIE du canonique (issue #2011) :"
  echo "  réel      : ${REAL}"
  echo "  canonique : ${CANON}"
  echo "  → AVANT toute restauration : vérifier que le canonique correspond bien à BRANCH_PROTECTION_REQUIRED.md"
  echo "    (le rejouer à l'aveugle annulerait une décision délibérée — c'est arrivé entre #7096 et #7480)."
  echo "  → Restaurer ensuite avec : curl -X PUT .../branches/main/protection -d @dev-hub/tools/branch-protection-canonical.json"
  exit 1
fi
N_CONTEXTS=$(echo "${CANON}" | jq '.contexts | length')
echo "✅ Protection de ${BRANCH} conforme au canonique (strict + ${N_CONTEXTS} contexts + reviews + enforce_admins)."
echo "✅ Aucun check tiers (quota/abonnement) n'est requis au merge (issue #7480)."
