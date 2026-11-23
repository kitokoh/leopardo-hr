<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Connectors;

use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/** #9846 — instances de fournisseur du socle de paiement interne (BC-38). */
final class PaymentProviderInstanceIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'payment_provider_instances';
    }

    public function label(): string
    {
        return 'Instances de fournisseur de paiement';
    }

    public function category(): string
    {
        return 'payment';
    }

    public function href(): string
    {
        return '/payments/provider-instances';
    }

    public function detail(): string
    {
        return 'Socle de paiement interne (BC-38) : bascule d\'un marché d\'une instance à une autre.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['platform'];
    }

    public function count(): int
    {
        return PaymentProviderInstance::query()->count();
    }
}
