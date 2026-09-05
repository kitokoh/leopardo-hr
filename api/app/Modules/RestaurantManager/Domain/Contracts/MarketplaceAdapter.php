<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\DeliveryApps\DeliveryAppOrderPayload;
use App\Modules\RestaurantManager\Domain\ValueObjects\MarketplaceInboundOrder;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-105/106 (#6162/6163) — contrat d'adaptateur marketplace (flux webhook
 * entrant signature-secret + notification de statut sortante).
 *
 * Génération distincte du flux « delivery-app » (DeliveryAppAdapter) : la
 * signature se vérifie avec un secret explicite (config tenant), pas par
 * companyId.
 */
interface MarketplaceAdapter
{
    public function providerCode(): string;

    public function inboundSignatureHeader(): string;

    public function verifySignature(string $rawBody, string $signature, ?string $secret): bool;

    public function parseInboundOrder(string $rawBody): MarketplaceInboundOrder;

    public function outboundStatusPayload(RestaurantOrder $order): array;

    /**
     * @param  array<mixed>  $payload
     */
    public function parseInbound(array $payload): DeliveryAppOrderPayload;
}
