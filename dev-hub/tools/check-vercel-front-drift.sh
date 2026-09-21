#!/usr/bin/env bash
# ============================================================
# check-vercel-front-drift.sh — Garde anti-dérive des fronts Vercel
#                               (issue #7994 §5)
# ------------------------------------------------------------
# Constat (audit 2026-09-20, #7994) : deploy-drift-guard.yml ne couvre que
# l'API (health Render ↔ dernier commit api/). Les fronts Vercel n'avaient
# AUCUN comparateur « version servie ↔ main » — travel-web et marketplace
# sont restés figés des jours (déploiements git CANCELED, secrets CI
# absents) sans qu'aucun signal ne le rende visible.
#
# Ce script est le comparateur, SANS aucun secret Vercel : il s'appuie sur
# les GitHub Deployments enregistrés par les workflows de déploiement
# eux-mêmes (register-github-deployment.sh, appelé après healthcheck
# réussi — cf. travel-web-deploy.yml / marketplace-deploy.yml).
#
# Verdicts :
#   0 = à jour (dernier déploiement « success » = dernier commit main du
#       chemin du front, ou postérieur) ;
#   1 = DÉRIVE (déploiement en retard, ou dernier déploiement non sain) ;
#   2 = NON TRAÇABLE (aucun GitHub Deployment enregistré pour
#       l'environnement — la prod du front est invisible : intégration
#       muette, jamais déployée via un workflow enregistreur) ;
#   3 = erreur technique (API GitHub illisible, arguments manquants).
#
# Usage :
#   GH_TOKEN=<token> check-vercel-front-drift.sh \
#     --environment "Production – leopardo-marche" --path front/marketplace \
#     [--label marketplace] [--repo owner/repo] [--branch main]
# ============================================================
set -uo pipefail

ENVIRONMENT=""
FRONT_PATH=""
LABEL=""
REPO="${GITHUB_REPOSITORY:-}"
BRANCH="main"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --environment) ENVIRONMENT="${2:-}"; shift 2 ;;
    --path) FRONT_PATH="${2:-}"; shift 2 ;;
    --label) LABEL="${2:-}"; shift 2 ;;
    --repo) REPO="${2:-}"; shift 2 ;;
    --branch) BRANCH="${2:-}"; shift 2 ;;
    -h|--help) sed -n '2,32p' "$0"; exit 0 ;;
    *) echo "Argument inconnu : $1" >&2; exit 3 ;;
  esac
done

LABEL="${LABEL:-${FRONT_PATH}}"

if [[ -z "${ENVIRONMENT}" || -z "${FRONT_PATH}" || -z "${REPO}" ]]; then
  echo "::error::--environment, --path et --repo (ou GITHUB_REPOSITORY) sont requis." >&2
  exit 3
fi
if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "::error::GH_TOKEN absent (github.token suffit — permission deployments: read)." >&2
  exit 3
fi

API="https://api.github.com/repos/${REPO}"
auth=(-H "Authorization: Bearer ${GH_TOKEN}" -H "Accept: application/vnd.github+json")

# 1. Référentiel : dernier commit de la branche touchant le chemin du front.
expected_sha="$(curl -fsS "${auth[@]}" -G "${API}/commits" \
  --data-urlencode "sha=${BRANCH}" \
  --data-urlencode "path=${FRONT_PATH}" \
  --data-urlencode "per_page=1" \
  | jq -r '.[0].sha // empty' 2>/dev/null || true)"

if [[ -z "${expected_sha}" ]]; then
  echo "::error::dernier commit ${BRANCH} pour « ${FRONT_PATH} » introuvable via l'API GitHub (${LABEL})." >&2
  exit 3
fi

# 2. Dernier GitHub Deployment de l'environnement (tous statuts).
deployment_json="$(curl -fsS "${auth[@]}" -G "${API}/deployments" \
  --data-urlencode "environment=${ENVIRONMENT}" \
  --data-urlencode "per_page=1" 2>/dev/null || true)"

deployment_id="$(jq -r '.[0].id // empty' <<<"${deployment_json}" 2>/dev/null || true)"
deployed_sha="$(jq -r '.[0].sha // empty' <<<"${deployment_json}" 2>/dev/null || true)"
deployment_created="$(jq -r '.[0].created_at // empty' <<<"${deployment_json}" 2>/dev/null || true)"

if [[ -z "${deployment_id}" ]]; then
  echo "::error::front ${LABEL} NON TRAÇABLE : aucun GitHub Deployment en environnement « ${ENVIRONMENT} » — la production de ${FRONT_PATH} n'a jamais été enregistrée par un workflow enregistreur (intégration Vercel muette ou déploiements annulés, #7994). Réaligner via le workflow de déploiement du front (workflow_dispatch production) puis relancer la garde."
  exit 2
fi

# 3. Statut courant de ce déploiement.
status_state="$(curl -fsS "${auth[@]}" \
  "${API}/deployments/${deployment_id}/statuses?per_page=1" \
  | jq -r '.[0].state // empty' 2>/dev/null || true)"

echo "front ${LABEL} : attendu ${expected_sha:0:9} (dernier commit ${BRANCH} sur ${FRONT_PATH}) — déployé ${deployed_sha:0:9} (${deployment_created:-date inconnue}, statut ${status_state:-inconnu})."

if [[ "${status_state}" != "success" ]]; then
  echo "::error::front ${LABEL} : le dernier déploiement « ${ENVIRONMENT} » n'est PAS sain (statut « ${status_state:-inconnu} ») — la prod n'est pas prouvée vivante (#7994)."
  exit 1
fi

# 4. Comparaison sha déployé ↔ référentiel.
if [[ "${deployed_sha}" == "${expected_sha}" ]]; then
  echo "front ${LABEL} : à jour (sha identique)."
  exit 0
fi

compare_status="$(curl -fsS "${auth[@]}" \
  "${API}/compare/${expected_sha}...${deployed_sha}" \
  | jq -r '.status // empty' 2>/dev/null || true)"

if [[ "${compare_status}" == "ahead" || "${compare_status}" == "identical" ]]; then
  echo "front ${LABEL} : à jour (déploiement postérieur au dernier commit du chemin)."
  exit 0
fi

# Le déploiement est en retard : mesurer l'écart pour le rapport.
gap="$(curl -fsS "${auth[@]}" \
  "${API}/compare/${deployed_sha}...${expected_sha}" \
  | jq -r '.ahead_by // "?"' 2>/dev/null || echo "?")"

echo "::error::front ${LABEL} DÉRIVE : le déploiement « ${ENVIRONMENT} » sert ${deployed_sha:0:9} alors que ${BRANCH} attend ${expected_sha:0:9} pour ${FRONT_PATH} (~${gap} commit(s) de retard, déploiement du ${deployment_created:-?}). Déclencher le workflow de déploiement du front (production) — si les secrets manquent, action propriétaire (#7994 §1)."
exit 1
