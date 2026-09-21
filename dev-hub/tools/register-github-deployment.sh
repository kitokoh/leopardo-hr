#!/usr/bin/env bash
# ============================================================
# register-github-deployment.sh — Enregistre un déploiement réussi comme
#                                 GitHub Deployment + statut « success »
#                                 (issue #7994 §5)
# ------------------------------------------------------------
# Pourquoi : les fronts Vercel (travel-web, marketplace) se déploient hors
# de l'intégration git Vercel (CLI `vercel deploy --prebuilt` en CI, ou
# redéploiements manuels par API) — aucun GitHub Deployment n'est alors
# enregistré par Vercel, et la garde anti-dérive des fronts
# (check-vercel-front-drift.sh) n'a AUCUNE donnée « version servie » à
# comparer à main. Ce script comble le trou : le workflow qui vient de
# déployer ET de prouver la santé de l'instance enregistre lui-même le
# déploiement (github.token, permission `deployments: write`).
#
# Usage :
#   GH_TOKEN=<token> register-github-deployment.sh \
#     --environment "Production – leopardo-marche" \
#     --sha <sha-git> [--url <url-publique>] [--description <texte>]
#
#   --environment NOM   environnement GitHub (convention Vercel :
#                       « Production – <projet> » / « Preview – <projet> »)
#   --sha SHA           référence déployée (défaut : $GITHUB_SHA)
#   --url URL           URL publique du déploiement (environment_url)
#   --description TXT   description portée par le statut (défaut générique)
#   --repo OWNER/REPO   dépôt cible (défaut : $GITHUB_REPOSITORY)
#
# Sortie : 0 = enregistré, 1 = erreur API (message GitHub affiché).
# ============================================================
set -euo pipefail

ENVIRONMENT=""
SHA="${GITHUB_SHA:-}"
URL=""
DESCRIPTION=""
REPO="${GITHUB_REPOSITORY:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --environment) ENVIRONMENT="${2:-}"; shift 2 ;;
    --sha) SHA="${2:-}"; shift 2 ;;
    --url) URL="${2:-}"; shift 2 ;;
    --description) DESCRIPTION="${2:-}"; shift 2 ;;
    --repo) REPO="${2:-}"; shift 2 ;;
    -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
    *) echo "Argument inconnu : $1" >&2; exit 2 ;;
  esac
done

if [[ -z "${ENVIRONMENT}" || -z "${SHA}" || -z "${REPO}" ]]; then
  echo "::error::--environment, --sha et --repo (ou GITHUB_REPOSITORY) sont requis." >&2
  exit 2
fi
if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "::error::GH_TOKEN absent — passer github.token au step (permission deployments: write)." >&2
  exit 2
fi

API="https://api.github.com/repos/${REPO}"
DESCRIPTION="${DESCRIPTION:-Déploiement enregistré par register-github-deployment.sh}"

# 1. Créer le déploiement (required_contexts: [] — l'enregistrement a lieu
#    APRÈS le déploiement effectif, aucun check à attendre).
deployment_payload="$(jq -n \
  --arg ref "${SHA}" \
  --arg env "${ENVIRONMENT}" \
  --arg desc "${DESCRIPTION}" \
  '{ref: $ref, environment: $env, description: $desc, auto_merge: false, required_contexts: []}')"

deployment_id="$(curl -fsS -X POST \
  -H "Authorization: Bearer ${GH_TOKEN}" \
  -H "Accept: application/vnd.github+json" \
  "${API}/deployments" \
  -d "${deployment_payload}" | jq -r '.id // empty')"

if [[ -z "${deployment_id}" ]]; then
  echo "::error::création du GitHub Deployment impossible (environnement « ${ENVIRONMENT} », sha ${SHA:0:9})." >&2
  exit 1
fi

# 2. Statut « success » (log_url = run courant quand disponible).
log_url=""
if [[ -n "${GITHUB_RUN_ID:-}" && -n "${GITHUB_SERVER_URL:-}" ]]; then
  log_url="${GITHUB_SERVER_URL}/${REPO}/actions/runs/${GITHUB_RUN_ID}"
fi

status_payload="$(jq -n \
  --arg state "success" \
  --arg url "${URL}" \
  --arg log "${log_url}" \
  --arg desc "${DESCRIPTION}" \
  '{state: $state, description: $desc}
   + (if $url != "" then {environment_url: $url} else {} end)
   + (if $log != "" then {log_url: $log} else {} end)')"

curl -fsS -X POST \
  -H "Authorization: Bearer ${GH_TOKEN}" \
  -H "Accept: application/vnd.github+json" \
  "${API}/deployments/${deployment_id}/statuses" \
  -d "${status_payload}" > /dev/null

echo "::notice::GitHub Deployment enregistré — environnement « ${ENVIRONMENT} », sha ${SHA:0:9}, url ${URL:-n/a} (deployment #${deployment_id})."
