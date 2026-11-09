#!/usr/bin/env bash
# check-mobile-stock-parity.sh — garde de PARITÉ WEB/MOBILE du stock (#9275).
#
# Règle produit : le mobile reste une OPTION. Toute opération de stock livrée
# en mobile doit exister en web ; l'inverse n'est pas requis.
#
# Ce garde vérifie la partie mécanique de la règle : un écran mobile de stock
# ne doit pas appeler un endpoint qui n'a pas de surface web documentée dans
# docs/mobile/STOCK_MOBILE.md.
#
# Usage : bash dev-hub/tools/check-mobile-stock-parity.sh
# Sortie : 0 = conforme, 1 = au moins un endpoint mobile sans surface web.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DOC="$REPO_ROOT/docs/mobile/STOCK_MOBILE.md"

[[ -f "$DOC" ]] || { echo "::error::docs/mobile/STOCK_MOBILE.md introuvable"; exit 1; }

# Endpoints de stock appelés par les écrans mobiles (leopardo_core + POS).
# Chaque endpoint doit être cité dans la doc de parité (colonne « Web » ✅ ou
# dérogation explicite).
MOBILE_STOCK_ENDPOINTS=(
  "/retail/stock/levels"
  "/retail/stock/movements"
  "/retail/stock/alerts"
  "/restaurant/stock-levels"
  "/restaurant/inventory-movements"
  "/restaurant/stock/alerts"
)

violations=0
for endpoint in "${MOBILE_STOCK_ENDPOINTS[@]}"; do
  if ! grep -qF "$endpoint" "$DOC"; then
    echo "::error::Endpoint mobile de stock '$endpoint' absent de docs/mobile/STOCK_MOBILE.md (parité web/mobile #9275)."
    violations=$((violations + 1))
  fi
done

if [[ "$violations" -gt 0 ]]; then
  echo "::error::$violations endpoint(s) mobile(s) sans surface web documentée."
  exit 1
fi

echo "Parité web/mobile du stock OK ($((${#MOBILE_STOCK_ENDPOINTS[@]})) endpoints documentés)."