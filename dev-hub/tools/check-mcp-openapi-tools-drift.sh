#!/usr/bin/env bash
# Garde anti-drift des outils MCP générés depuis OpenAPI (issue #9746, EPIC #9739).
#
# `dev-hub/mcp-server/manifest/openapi-tools.json` est GÉNÉRÉ depuis
# `api/openapi.yaml` par `dev-hub/tools/generate-mcp-openapi-tools.py`.
#
# Si une route est ajoutée, renommée ou retirée de la spec sans régénérer le
# manifeste, le serveur MCP servirait un contrat périmé — silencieusement. Cette
# garde rend la divergence BRUYANTE et bloquante, exactement comme
# `check-mcp-manifest-drift.sh` le fait pour le contrat A3.
#
# Usage : bash dev-hub/tools/check-mcp-openapi-tools-drift.sh [racine]
set -euo pipefail

ROOT="${1:-.}"
MANIFEST="${ROOT}/dev-hub/mcp-server/manifest/openapi-tools.json"
GENERATOR="${ROOT}/dev-hub/tools/generate-mcp-openapi-tools.py"

if [[ ! -f "${MANIFEST}" ]]; then
  echo "ERREUR : manifeste OpenAPI MCP absent (${MANIFEST})." >&2
  echo "Régénérer : python3 dev-hub/tools/generate-mcp-openapi-tools.py" >&2
  exit 1
fi

if [[ ! -f "${GENERATOR}" ]]; then
  echo "ERREUR : générateur absent (${GENERATOR})." >&2
  exit 1
fi

echo "→ Vérification de la parité manifeste MCP OpenAPI ↔ api/openapi.yaml…"
if ! python3 "${GENERATOR}" --check; then
  echo "" >&2
  echo "ERREUR : les outils MCP générés sont désynchronisés de api/openapi.yaml (issue #9746)." >&2
  echo "Régénérer puis committer :" >&2
  echo "  python3 dev-hub/tools/generate-mcp-openapi-tools.py" >&2
  exit 1
fi

echo "OK : outils MCP générés à jour."
