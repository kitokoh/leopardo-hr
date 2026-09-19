#!/usr/bin/env bash
#
# check-vertical-controller-policies.sh — Garde CI « tout contrôleur vertical
# appelle une policy » (issue #7599, R2 de l'épique #7597 ; étendue par R3
# #7600 aux autres verticales au fur et à mesure de leur migration).
#
# Trou n°4 de l'épique : 13/52 contrôleurs Restaurant n'appelaient aucune
# policy (dont RestaurantCogsController — coûts, sensible — et les
# contrôleurs mobiles). Cette garde empêche le motif de revenir : chaque
# contrôleur d'une verticale surveillée doit contenir au moins un appel
# d'autorisation (authorize/can/cannot/Gate, ou les helpers ressource-scopés
# hasResourceAccess/accessibleResourceIds/can*BranchResource).
#
# Allowlist : surfaces volontairement SANS policy d'acteur tenant —
# webhooks signés, callbacks PSP, santé, surfaces publiques (vitrine/commande
# en ligne), kiosque (auth dédiée). Les contrôleurs mobiles dont
# l'autorisation vit dans leur service Infrastructure sont allowlistés avec
# le service porteur en commentaire — si l'autorisation quitte le service, la
# garde doit être réévaluée, pas élargie.
#
# Usage : dev-hub/tools/check-vertical-controller-policies.sh [api_dir]
# Exit 1 si une violation est détectée.

set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

# Verticales surveillées (R2 : pilote Restaurant ; R3+ : ajouter ici).
MODULES=(RestaurantManager)

# Motifs qui prouvent un appel d'autorisation dans le contrôleur.
POLICY_PATTERN='authorize\(|->cannot\(|->can\(|Gate::|hasResourceAccess\(|accessibleResourceIds\(|can(View|Operate|Manage)BranchResource\(|::authorize\('

# Basenames allowlistés (raison en commentaire).
ALLOWLIST=(
  RestaurantDeliveryAppWebhookController.php   # webhook signé (pas d'acteur tenant)
  RestaurantMarketplaceWebhookController.php   # webhook signé (pas d'acteur tenant)
  RestaurantPaymentCallbackController.php      # callback PSP signé
  RestaurantHealthController.php               # sonde de santé
  RestaurantPublicOrderController.php          # surface publique (token boutique)
  RestaurantPublicShopController.php           # surface publique (token boutique)
  RestaurantPublicDirectoryController.php      # annuaire public par slug (#7746) : lecture seule, opt-in + publiés uniquement, 404 fail-closed
  RestaurantPublicReviewController.php         # avis publics par slug (#7747) : lecture publiés only ; dépôt gated par référence de commande servie/livrée + throttle dédié
  RestaurantPublicSlugOrderController.php      # commande publique par slug (#7747) : même pipeline que RestaurantPublicOrderController (RESTO-805), réf RST- non énumérable
  RestaurantKioskController.php                # kiosque : auth locale dédiée
  RestaurantMobileServerController.php         # autorisation dans RestaurantMobileServerService
  RestaurantMobileManagerController.php        # autorisation dans RestaurantMobileManagerService
  RestaurantMobileRiderController.php          # autorisation dans RestaurantMobileRiderService (livreur lié par employee_id)
  RestaurantMobileSyncController.php           # autorisation par opération dans RestaurantMobileSyncService
)

violations=0

report() {
  echo "::error::check-vertical-controller-policies: $1"
  violations=$((violations + 1))
}

is_allowlisted() {
  local base="$1"
  for entry in "${ALLOWLIST[@]}"; do
    if [[ "$entry" == "$base" ]]; then
      return 0
    fi
  done
  return 1
}

for module in "${MODULES[@]}"; do
  dir="$API_DIR/app/Modules/$module/Interfaces/Api/V1/Controllers"
  if [[ ! -d "$dir" ]]; then
    report "dossier introuvable: $dir"
    continue
  fi

  while IFS= read -r file; do
    base="$(basename "$file")"
    if is_allowlisted "$base"; then
      continue
    fi
    if ! grep -qE "$POLICY_PATTERN" "$file"; then
      report "$file n'appelle aucune policy (authorize/can/cannot/Gate/hasResourceAccess). Ajouter l'appel d'autorisation, ou allowlister avec justification si la surface est volontairement publique."
    fi
  done < <(find "$dir" -maxdepth 1 -name '*Controller.php' | sort)
done

if [[ "$violations" -gt 0 ]]; then
  echo "check-vertical-controller-policies: $violations violation(s)."
  exit 1
fi

echo "check-vertical-controller-policies: OK"
