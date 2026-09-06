#!/usr/bin/env bash
# check-app-version-sync.sh — Garde anti-drift APP_VERSION (issue #3528).
#
# Constat : api/.env.example épinglait APP_VERSION=4.23.5 alors que le défaut
# de api/config/app.php était 4.24.0 — /api/v1/health rapportait alors une
# version périmée (observabilité faussée, impossible de confirmer qu'un
# déploiement a livré la bonne version).
#
# Vérifie que la valeur APP_VERSION de api/.env.example correspond au défaut
# déclaré dans api/config/app.php ('version' => env('APP_VERSION', 'X')).
#
# Usage : dev-hub/tools/check-app-version-sync.sh [api_dir]
set -euo pipefail

API_DIR="${1:-api}"
ENV_FILE="${API_DIR}/.env.example"
APP_CFG="${API_DIR}/config/app.php"

if [[ ! -f "${ENV_FILE}" || ! -f "${APP_CFG}" ]]; then
  echo "::error::${ENV_FILE} ou ${APP_CFG} introuvable"
  exit 1
fi

example_version=$(grep -E '^APP_VERSION=' "${ENV_FILE}" | head -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs)
config_default=$(sed -n "s/.*env('APP_VERSION',[[:space:]]*'\([^']*\)').*/\1/p" "${APP_CFG}" | head -n 1)

# Issue #6945 : depuis #6835/#6905 (« version /api/v1/health honnête »), la
# config peut ne plus porter de défaut statique — la résolution est alors
# `env('APP_VERSION') ?: RENDER_GIT_COMMIT ?: 'unversioned'`. L'absence de
# défaut est le comportement VOULU (ne jamais retomber sur une release
# passée) : on vérifie alors seulement que `.env.example` déclare bien le
# bouton d'injection APP_VERSION (même contrat que check-release-compat.sh).
if [[ -z "${config_default}" ]]; then
  if ! grep -qE '^APP_VERSION=.+' "${ENV_FILE}"; then
    echo "::error::APP_VERSION absent de ${ENV_FILE} (issue #6945 — config sans défaut statique : le bouton d'injection doit exister dans .env.example)."
    exit 1
  fi

  echo "✓ APP_VERSION déclaré dans ${ENV_FILE} (config sans défaut statique — résolution honnête #6835, rien à synchroniser)."
  exit 0
fi

if [[ -z "${example_version}" ]]; then
  echo "::error::APP_VERSION absent de ${ENV_FILE} (issue #3528 — aligner .env.example sur le défaut ${config_default})."
  exit 1
fi

if [[ "${example_version}" != "${config_default}" ]]; then
  echo "::error::APP_VERSION divergent : ${ENV_FILE}=${example_version} vs ${APP_CFG} défaut=${config_default} (issue #3528 — aligner les deux)."
  exit 1
fi

echo "✓ APP_VERSION synchronisé (${example_version}) entre ${ENV_FILE} et ${APP_CFG}."
exit 0
