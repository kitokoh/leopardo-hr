#!/usr/bin/env bash
# Compare les routes API déclarées (artisan route:list) avec les chemins
# documentés dans api/openapi.yaml. Sortie : liste des chemins non documentés.
# Usage : bash dev-hub/tools/check-openapi-coverage.sh [chemin_openapi] [json_routes]
#         (json_routes optionnel : si absent, exécute php artisan route:list)
# Voir issues #1473 / #1409 et docs/security/OPENAPI_COVERAGE_GAP_2026-07-19.md.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OPENAPI="${1:-$REPO_ROOT/api/openapi.yaml}"
ROUTES_JSON="${2:-}"

if [[ -z "$ROUTES_JSON" ]]; then
  ROUTES_JSON="$(mktemp)"
  (cd "$REPO_ROOT/api" && php artisan route:list --json > "$ROUTES_JSON")
  trap 'rm -f "$ROUTES_JSON"' EXIT
fi

normalize() { sed -E 's#\{[^}]+\}#{param}#g'; }

# Chemins réels : routes api/v1, préfixe retiré, paramètres normalisés
jq -r '.[] | .uri' "$ROUTES_JSON" \
  | grep '^api/v1' \
  | sed 's#^api/v1##' \
  | normalize | sort -u > /tmp/lp_routes.txt

# Chemins documentés : clés sous `paths:` de l'openapi
awk '/^paths:/{inpaths=1; next} inpaths && /^  \//{print $1} inpaths && /^[a-zA-Z]/{exit}' "$OPENAPI" \
  | sed 's/:$//' | normalize | sort -u > /tmp/lp_openapi.txt

echo "Routes API réelles : $(wc -l < /tmp/lp_routes.txt)"
echo "Chemins OpenAPI      : $(wc -l < /tmp/lp_openapi.txt)"
echo
echo "=== Chemins NON documentés (routes sans entrée OpenAPI) ==="
comm -23 /tmp/lp_routes.txt /tmp/lp_openapi.txt | tee /tmp/lp_missing.txt
echo
echo "Total manquant : $(wc -l < /tmp/lp_missing.txt)"
