<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\DeliveryApps;

/**
 * RESTO-806 (#6227) — payload normalisé d'une commande marketplace.
 *
 * DTO neutre produit par les adaptateurs Uber Eats / Glovo : la verticale
 * ne connaît que ce contrat — le même workflow interne traite la commande
 * (critère d'acceptation « commande marketplace → même workflow interne »).
 * Montants en minor units ; le total est TOUJOURS recalculé serveur.
 */
final class DeliveryAppOrderPayload
{
    /**
     * @param  list<array{product_id: int, quantity: float|string}>  $items
     */
    public function __construct(
        public readonly string $externalOrderId,
        public readonly string $externalRestaurantId,
        public readonly string $orderType,
        public readonly array $items,
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerAddress = null,
        public readonly ?string $note = null,
    ) {
    }
}
