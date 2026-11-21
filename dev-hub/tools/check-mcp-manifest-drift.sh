#!/usr/bin/env bash
# Garde anti-drift du manifeste MCP (MCP-4, issue #9738, EPIC #9739).
#
# Le serveur MCP externe (dev-hub/mcp-server) ne connaît AUCUN nom d'outil en
# dur : il découvre les outils via dev-hub/mcp-server/manifest/tools.json,
# produit par `php artisan ai:tools:manifest` depuis AIToolDefinitionRegistry
# (contrat A3, ADR-0019 option 3 « pont hybride »).
#
# Si un BC ajoute, retire ou modifie une AIToolDefinition sans régénérer le
# manifeste, le serveur MCP servirait un contrat périmé — silencieusement.
# Cette garde rend la divergence BRUYANTE et bloquante.
#
# Usage : bash dev-hub/tools/check-mcp-manifest-drift.sh [racine]
set -euo pipefail

ROOT="${1:-.}"
API_DIR="${ROOT}/api"
MANIFEST="${ROOT}/dev-hub/mcp-server/manifest/tools.json"

if [[ ! -f "${MANIFEST}" ]]; then
  echo "ERREUR : manifeste MCP absent (${MANIFEST})." >&2
  echo "Régénérer : cd api && php artisan ai:tools:manifest --output=../dev-hub/mcp-server/manifest/tools.json" >&2
  exit 1
fi

if [[ ! -d "${API_DIR}/vendor" ]]; then
  echo "ERREUR : ${API_DIR}/vendor absent — composer install requis pour exécuter la commande artisan." >&2
  exit 1
fi

echo "→ Vérification de la parité manifeste MCP ↔ AIToolDefinitionRegistry…"
if ! (cd "${API_DIR}" && php artisan ai:tools:manifest --check); then
  echo "" >&2
  echo "ERREUR : le manifeste MCP est désynchronisé du registre d'outils (MCP-1 #9735)." >&2
  echo "Régénérer puis committer :" >&2
  echo "  cd api && php artisan ai:tools:manifest --output=../dev-hub/mcp-server/manifest/tools.json" >&2
  exit 1
fi

echo "OK : manifeste MCP à jour."
