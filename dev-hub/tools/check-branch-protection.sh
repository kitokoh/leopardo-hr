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
# ── Deux modes de lecture (issue #7657) ──────────────────────────────────────
# Historiquement la garde exigeait un PAT admin (`BRANCH_PROTECTION_TOKEN`,
# permission administration:read) : sans lui elle échouait en fail-loud, donc
# elle était ROUGE EN PERMANENCE sur main — un check rouge permanent ne
# signale plus rien (leçon #3545).
#
#   1. MODE COMPLET — `GET /repos/{repo}/branches/{branch}/protection`
#      (token admin). Vérifie strict + contexts + enforce_admins + reviews +
#      force-push + deletions. C'est le seul mode qui couvre TOUT le canonique.
#   2. MODE PARTIEL — `GET /repos/{repo}/branches/{branch}`
#      (GITHUB_TOKEN par défaut, aucune permission spéciale). GitHub expose
#      `protected` et `protection.required_status_checks.contexts` : la garde
#      vérifie donc l'invariant central de #2011 (la liste des checks requis
#      n'a pas été vidée) + l'invariant #7480 (aucun check tiers requis).
#      Les champs non exposés (strict, enforce_admins, reviews, force-push,
#      deletions) sont ANNONCÉS comme non vérifiés — jamais présentés comme
#      conformes.
#
# Le mode partiel est un filet, pas un substitut : il détecte la panne réelle
# (protection vidée) sans dépendre d'un secret propriétaire. Dès que
# `BRANCH_PROTECTION_TOKEN` est posé, le mode complet reprend automatiquement.
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
MODE="complet"

if [[ -n "${FIXTURE}" ]]; then
  if [[ ! -f "${FIXTURE}" ]]; then
    echo "::error::Fixture de protection introuvable : ${FIXTURE}"
    exit 1
  fi
  cp "${FIXTURE}" /tmp/bp-real.json
  # Le self-test rejoue les DEUX formes : la protection complète (API
  # /protection) et la forme partielle (API /branches/{branch}). On déduit le
  # mode de la forme reçue — `enforce_admins` n'existe que dans la première.
  if ! jq -e 'has("enforce_admins")' /tmp/bp-real.json >/dev/null 2>&1; then
    MODE="partiel"
    if [[ "$(jq -r '.protected // false' /tmp/bp-real.json)" != "true" ]]; then
      echo "::error::La branche ${BRANCH} n'est PAS protégée — déviation critique (issue #2011)."
      exit 1
    fi
    jq '{required_status_checks: {contexts: (.protection.required_status_checks.contexts // [])}}' \
      /tmp/bp-real.json > /tmp/bp-real-normalized.json
    mv /tmp/bp-real-normalized.json /tmp/bp-real.json
  fi
else
  if [[ -z "${GITHUB_TOKEN:-}" ]]; then
    echo "::error::GITHUB_TOKEN requis (lecture de la protection de branche)."
    exit 1
  fi

  # 1) Tentative en mode COMPLET (token admin).
  HTTP=$(curl -s -o /tmp/bp-real.json -w '%{http_code}' \
    -H "Authorization: Bearer ${GITHUB_TOKEN}" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/${REPO}/branches/${BRANCH}/protection" || true)

  if [[ "${HTTP}" == "404" ]]; then
    echo "::error::La branche ${BRANCH} n'a AUCUNE protection — déviation critique (issue #2011)."
    exit 1
  fi

  if [[ "${HTTP}" != "200" ]]; then
    # 2) Repli en mode PARTIEL : l'endpoint de branche est lisible avec le
    #    GITHUB_TOKEN par défaut (403/404 sur /protection = pas de droit admin).
    MODE="partiel"
    HTTP_BRANCH=$(curl -s -o /tmp/bp-branch.json -w '%{http_code}' \
      -H "Authorization: Bearer ${GITHUB_TOKEN}" \
      -H "Accept: application/vnd.github+json" \
      "https://api.github.com/repos/${REPO}/branches/${BRANCH}" || true)

    if [[ "${HTTP_BRANCH}" != "200" ]]; then
      echo "::error::Lecture de la branche ${BRANCH} impossible (HTTP ${HTTP_BRANCH}) — protection NON vérifiée."
      exit 1
    fi

    if [[ "$(jq -r '.protected // false' /tmp/bp-branch.json)" != "true" ]]; then
      echo "::error::La branche ${BRANCH} n'est PAS protégée — déviation critique (issue #2011)."
      exit 1
    fi

    # Normalisation vers la forme attendue par la comparaison partielle.
    jq '{required_status_checks: {contexts: (.protection.required_status_checks.contexts // [])}}' \
      /tmp/bp-branch.json > /tmp/bp-real.json

    echo "::notice::Mode PARTIEL (issue #7657) : /protection a répondu HTTP ${HTTP} (pas de droit administration)."
    echo "::notice::Vérifiés : branche protégée + liste des checks requis + invariant « aucun check tiers requis »."
    echo "::notice::NON vérifiés dans ce mode : strict, enforce_admins, reviews, force-push, deletions — poser BRANCH_PROTECTION_TOKEN pour le mode complet."
  fi
fi

# ── Invariant #7480 : un check tiers n'est JAMAIS requis au merge ─────────────
# Un check posé par une application tierce (Vercel, Cloudflare Workers/Pages,
# revue de sécurité externe) échoue sur un quota ou un abonnement, pas sur le
# code. Le rendre requis ferait qu'une PR saine ne peut plus merger — c'est le
# défaut que l'issue #7480 décrit. L'invariant est vérifié des deux côtés :
#   - le canonique ne doit pas le lister (sinon la remédiation le réinstalle) ;
#   - la protection réelle ne doit pas le lister (sinon une dérive admin passe).
real_contexts=$(jq -r '.required_status_checks.contexts[]? // empty' /tmp/bp-real.json)
canon_contexts=$(jq -r '.required_status_checks.contexts[]' "${CANONICAL_FILE}")
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

if [[ "${MODE}" == "partiel" ]]; then
  # Comparaison limitée aux contexts (seul champ exposé sans droit admin).
  REAL_CTX=$(jq -c '[.required_status_checks.contexts[]?] | sort' /tmp/bp-real.json)
  CANON_CTX=$(jq -c '[.required_status_checks.contexts[]] | sort' "${CANONICAL_FILE}")

  if [[ "${REAL_CTX}" != "${CANON_CTX}" ]]; then
    echo "::error::Protection de ${BRANCH} DÉVIE du canonique sur les checks requis (issue #2011) :"
    echo "  réel      : ${REAL_CTX}"
    echo "  canonique : ${CANON_CTX}"
    echo "  → AVANT toute restauration : vérifier que le canonique correspond bien à BRANCH_PROTECTION_REQUIRED.md"
    echo "    (le rejouer à l'aveugle annulerait une décision délibérée — c'est arrivé entre #7096 et #7480)."
    exit 1
  fi

  N_CONTEXTS=$(jq '.required_status_checks.contexts | length' "${CANONICAL_FILE}")
  echo "✅ Protection de ${BRANCH} : branche protégée + ${N_CONTEXTS} checks requis conformes au canonique (mode partiel)."
  echo "✅ Aucun check tiers (quota/abonnement) n'est requis au merge (issue #7480)."
  exit 0
fi

# ── Mode COMPLET : comparaison intégrale ─────────────────────────────────────
# Extraire les champs contrôlés (normalisation : l'API renvoie des objets
# {enabled: bool} pour force-push/deletions, le canonique des booléens).
REAL=$(jq -c '{strict: .required_status_checks.strict, contexts: [.required_status_checks.contexts[]], enforce_admins: .enforce_admins.enabled, reviews: .required_pull_request_reviews.required_approving_review_count, allow_force_pushes: (.allow_force_pushes.enabled // false), allow_deletions: (.allow_deletions.enabled // false)}' /tmp/bp-real.json)
CANON=$(jq -c '{strict: .required_status_checks.strict, contexts: [.required_status_checks.contexts[]], enforce_admins: .enforce_admins, reviews: .required_pull_request_reviews.required_approving_review_count, allow_force_pushes: .allow_force_pushes, allow_deletions: .allow_deletions}' "${CANONICAL_FILE}")

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
