<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\ValueObjects\MarketplaceInboundOrder;

/**
 * RESTO-806 (#6227) — Contrat d'adaptateur d'app de livraison.
 *
 * Webhooks entrants (commande marketplace → workflow interne) et sortants
 * (statuts internes → marketplace). Chaque adaptateur connaît le format
 * propriétaire du provider (Uber Eats, Glovo) et sa méthode de signature
 * (HMAC-SHA256 en v1, fail-closed). Aucun secret en dur : tout passe par la
 * config/env (`restaurantmanager.marketplace.<provider>.*`).
 */
interface DeliveryAppAdapter
{
    public function providerCode(): string;

    /**
     * Vérifie la signature HMAC-SHA256 d'un webhook entrant (comparaison
     * constante, fail-closed si signature vide ou secret absent).
     */
    public function verifySignature(string $rawBody, string $signature, ?string $secret): bool;

    /**
     * Traduit le payload brut du provider en commande interne normalisée.
     */
    public function parseInboundOrder(string $rawBody): MarketplaceInboundOrder;

    /**
     * Payload de statut sortant (statut interne → statut provider).
     *
     * @return array<string, mixed>
     */
    public function outboundStatusPayload(RestaurantOrder $order): array;

    /**
     * Nom du header de signature entrante (ex. `X-Uber-Signature`).
     */
    public function inboundSignatureHeader(): string;
}
