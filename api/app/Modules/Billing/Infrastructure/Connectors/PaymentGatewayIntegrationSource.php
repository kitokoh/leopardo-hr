<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Connectors;

use App\Modules\Billing\Domain\Models\PaymentGatewaySetting;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/**
 * #9846 — passerelles de paiement de la PLATEFORME (abonnements des tenants).
 *
 * Déclarée par Billing, pas par Connecteurs : c'est Billing qui possède la
 * table et l'écran natif `/billing/gateways`.
 */
final class PaymentGatewayIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'payment_gateways';
    }

    public function label(): string
    {
        return 'Passerelles de paiement (abonnements)';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function href(): string
    {
        return '/billing/gateways';
    }

    public function detail(): string
    {
        return 'Stripe et Chargily : encaissement des abonnements de la plateforme.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['platform'];
    }

    public function count(): int
    {
        return PaymentGatewaySetting::query()->where('is_active', true)->count();
    }
}
