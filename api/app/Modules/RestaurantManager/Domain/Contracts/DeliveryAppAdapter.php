<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

/**
 * RESTO-806 (#6227) — Contrat d'intégration des apps de livraison
 * (marketplaces : Uber Eats, Glovo, …).
 *
 * Un adaptateur par marketplace expose :
 * - `providerCode()` : identifiant stable de la marketplace (slug) ;
 * - `verifySignature()` : vérification HMAC-SHA256 fail-closed du webhook
 *   entrant (secret par tenant, jamais en dur) ;
 * - `normalizeItems()` : normalisation des articles marketplace → codes
 *   produits Leopardo (le référentiel produit reste la source de vérité des
 *   prix — aucun montant accepté tel quel depuis la marketplace).
 *
 * La commande marketplace entre dans le MÊME workflow interne
 * (RestaurantPublicOrderService / machine à états) : critère d'acceptation
 * « commande marketplace → même workflow interne ».
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

    public function verifySignature(string $payload, string $signature, string $companyId): bool;

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{product_code: string, quantity: float|string}>
     */
    public function normalizeItems(array $items): array;
    public function verifySignature(string $rawBody, string $signature, string $secret): bool;

    /**
     * @param  array<mixed>  $payload
     */
    public function parseInbound(array $payload): DeliveryAppOrderPayload;
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
