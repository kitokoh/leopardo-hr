#!/usr/bin/env bash
# render-verify-services.sh — Vérifie que les services Render de Leopardo RH
# (web + workers de queue + scheduler) existent et que l'env est alignée.
#
# Contexte : issue #5172 — constat prod 2026-08-20 : le compte Render
# `africanovatech` ne contient que le web service `gestionemployerbackend`.
# `leopardo-queue-worker` et `leopardo-scheduler` (définis dans render.yaml)
# n'existaient pas → les jobs de file (invitations, trial provisioning,
# notifications, PDF, payroll) n'étaient JAMAIS exécutés → prospects bloqués
# en « pending » et aucun email d'invitation envoyé.
#
# Usage :
#   RENDER_API_KEY=<clé> dev-hub/tools/render-verify-services.sh
#
# Sortie : liste les services présents/manquants et l'alignement de l'env du
# web service (QUEUE_CONNECTION/CACHE_STORE/SESSION_DRIVER attendus = redis,
# conformément à render.yaml). Exit 1 si un service attendu manque.
set -euo pipefail

API_BASE="https://api.render.com/v1"
: "${RENDER_API_KEY:?RENDER_API_KEY doit être définie (dashboard Render → Account → API Keys)}"

# Services attendus (render.yaml) : nom → type
declare -A EXPECTED=(
  [gestionemployerbackend]=web
  [leopardo-queue-worker]=worker
  [leopardo-scheduler]=worker
)
# #5578 : source de vérité unique = database (garde CI check-queue-strategy-coherence.sh).
EXPECTED_QUEUE_VARS=("QUEUE_CONNECTION=database" "CACHE_STORE=redis" "SESSION_DRIVER=redis")

echo "== Récupération des services Render (pagination) =="
services_json=""
cursor=""
while :; do
  url="${API_BASE}/services?limit=100"
  [[ -n "${cursor}" ]] && url="${url}&cursor=${cursor}"
  resp=$(curl -sf "${url}" -H "Authorization: Bearer ${RENDER_API_KEY}" || {
    echo "::error::Échec API Render (${url}) — clé invalide ? quota ?"; exit 2; })
  services_json="${services_json}${resp}"
  cursor=$(echo "${resp}" | jq -r '.[-1].cursor // empty')
  [[ -z "${cursor}" ]] && break
done

found_missing=0
for name in "${!EXPECTED[@]}"; do
  type="${EXPECTED[$name]}"
  svc=$(echo "${services_json}" | jq -c --arg n "${name}" '[.[] | select(.service.name == $n)][0] // empty')
  if [[ -z "${svc}" || "${svc}" == "null" ]]; then
    echo "::error::SERVICE MANQUANT : ${name} (type ${type}) — voir RUNBOOK_RENDER_WORKERS.md §3"
    found_missing=1
  else
    svc_type=$(echo "${svc}" | jq -r '.service.type')
    echo "✓ ${name} présent (type=${svc_type})"
  fi
done

# Alignement de l'env du web service
web_id=$(echo "${services_json}" | jq -r '[.[] | select(.service.name == "gestionemployerbackend")][0].service.id // empty')
if [[ -n "${web_id}" ]]; then
  echo "== Env du web service (vars queue/cache/session) =="
  envs=$(curl -sf "${API_BASE}/services/${web_id}/env-vars" -H "Authorization: Bearer ${RENDER_API_KEY}" || echo '{}')
  for kv in "${EXPECTED_QUEUE_VARS[@]}"; do
    key="${kv%%=*}"
    expected="${kv##*=}"
    actual=$(echo "${envs}" | jq -r --arg k "${key}" '.envVars[]? | select(.envVar.key == $k) | .envVar.value // empty')
    if [[ -z "${actual}" ]]; then
      echo "::error::VARIABLE ABSENTE sur le web service : ${key} (attendu ${expected})"
      found_missing=1
    elif [[ "${actual}" != "${expected}" ]]; then
      echo "::error::DÉVIATION ${key}=${actual} (attendu ${expected}) — voir RUNBOOK_RENDER_WORKERS.md §4"
      found_missing=1
    else
      echo "✓ ${key}=${actual}"
    fi
  done
fi

if [[ "${found_missing}" -eq 1 ]]; then
  echo ""
  echo "== Action requise (issue #5172) =="
  echo "  Les workers manquent → provisionner via le blueprint render.yaml (dashboard) :"
  echo "  Render → Blueprints → New Blueprint Instance → repo → 'Apply' (sync: false pour les secrets),"
  echo "  ou via l'API : POST ${API_BASE}/services avec le payload du service (cf. render.yaml §worker)."
  echo "  Puis drainer les trial_provisionings pendants :"
  echo "  php artisan trial-provisionings:sweep --dry-run  (puis sans --dry-run)"
  exit 1
fi

echo ""
echo "✓ Infrastructure Render conforme à render.yaml (web + 2 workers + env)."
exit 0
