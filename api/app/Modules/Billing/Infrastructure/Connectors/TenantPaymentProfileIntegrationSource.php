<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Connectors;

use App\Modules\Billing\Domain\Models\TenantPaymentProfile;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/**
 * #9846 — comptes d'encaissement du TENANT.
 *
 * `TenantPaymentProfile` porte `BelongsToCompany` : le comptage est donc
 * automatiquement borné au tenant courant, sans filtre à maintenir ici.
 */
final class TenantPaymentProfileIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'payment_profiles';
    }

    public function label(): string
    {
        return 'Encaissements';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function href(): string
    {
        return '/settings/encaissements';
    }

    public function detail(): string
    {
        return 'Vos comptes d\'encaissement : Stripe, IBAN, mobile money, espèces.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['tenant'];
    }

    public function count(): int
    {
        return TenantPaymentProfile::query()->count();
    }
}
