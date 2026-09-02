<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

/**
 * RESTO-806 (#6227) — Article de commande marketplace (données entrantes).
 *
 * `externalProductId` est l'identifiant produit côté marketplace ; le
 * rapprochement avec le référentiel interne se fait par `code` produit
 * (unique par tenant). `unitPriceMinor` est optionnel : le prix serveur fait
 * toujours foi (aucun montant client accepté tel quel).
 */
final class MarketplaceOrderItem
{
    public function __construct(
        public readonly string $externalProductId,
        public readonly string $name,
        public readonly float $quantity,
        public readonly ?int $unitPriceMinor = null,
    ) {
    }
}
