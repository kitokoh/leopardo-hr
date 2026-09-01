<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;

/**
 * RESTO-806 (#6227) — Registre des adaptateurs d'apps de livraison.
 *
 * Résolution par `providerCode()` ; fournisseur inconnu → null (l'appelant
 * répond 422 fail-closed). Un adaptateur par marketplace (pattern
 * PaymentGatewayRegistry, RESTO-406/#6193).
 */
final class DeliveryAppAdapterRegistry
{
    /**
     * @param  iterable<DeliveryAppAdapter>  $adapters
     */
    public function __construct(private readonly iterable $adapters)
    {
    }

    public function resolve(string $providerCode): ?DeliveryAppAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->providerCode() === $providerCode) {
                return $adapter;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function availableProviders(): array
    {
        $providers = [];

        foreach ($this->adapters as $adapter) {
            $providers[] = $adapter->providerCode();
        }

        sort($providers);

        return $providers;
    }
}
