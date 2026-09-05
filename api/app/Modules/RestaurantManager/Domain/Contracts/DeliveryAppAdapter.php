<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\DeliveryApps\DeliveryAppOrderPayload;

/**
 * RESTO-806 (#6227) — contrat d'adaptateur d'app de livraison.
 *
 * Chaque marketplace (Uber Eats, Glovo…) implémente ce contrat :
 *  - `providerCode()` : identifiant de l'adaptateur ;
 *  - `verifySignature()` : vérifie la signature HMAC du webhook entrant
 *    (secret par tenant, jamais en clair) ;
 *  - `parseInbound()` : normalise le payload marketplace en DTO neutre —
 *    le reste de la verticale ignore le format propriétaire.
 */
interface DeliveryAppAdapter
{
    public function providerCode(): string;

    public function verifySignature(string $rawBody, string $signature, string $secret): bool;

    /**
     * @param  array<mixed>  $payload
     */
    /**
     * @param  array<mixed>  $items
     * @return list<array{product_id: int, quantity: float}>
     */
    public function normalizeItems(array $items): array;

    public function parseInbound(array $payload): DeliveryAppOrderPayload;
}
