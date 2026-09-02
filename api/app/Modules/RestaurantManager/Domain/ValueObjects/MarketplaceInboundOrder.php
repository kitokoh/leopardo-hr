<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

/**
 * RESTO-806 (#6227) — Commande entrante marketplace (payload normalisé).
 *
 * Produit par `DeliveryAppAdapter::parseInboundOrder()` — les adapters
 * traduisent le format propriétaire (Uber Eats / Glovo) vers ce VO commun ;
 * le workflow interne reste identique (CreateMarketplaceOrderAction → même
 * machine à états que le POS).
 */
final class MarketplaceInboundOrder
{
    /**
     * @param  list<MarketplaceOrderItem>  $items
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $provider,
        public readonly string $customerName,
        public readonly ?string $customerPhone,
        public readonly array $items,
        public readonly string $currency,
        public readonly ?string $note = null,
        public readonly ?string $branchCode = null,
    ) {
    }
}
