<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services\DeliveryApps;

use App\Modules\RestaurantManager\Domain\Contracts\DeliveryAppAdapter;
use RuntimeException;

/**
 * RESTO-806 (#6227) — Registre des adaptateurs d'apps de livraison.
 *
 * Résolution par `providerCode` (fail-closed : provider inconnu → exception
 * normalisée, jamais de fallback silencieux).
 */
final class DeliveryAppRegistry
{
    /** @var array<string, DeliveryAppAdapter> */
    private array $adapters = [];

    public function register(DeliveryAppAdapter $adapter): void
    {
        $this->adapters[$adapter->providerCode()] = $adapter;
    }

    public function has(string $provider): bool
    {
        return isset($this->adapters[$provider]);
    }

    public function resolve(string $provider): DeliveryAppAdapter
    {
        if (! $this->has($provider)) {
            throw new RuntimeException(sprintf('Unsupported delivery app provider "%s".', $provider));
        }

        return $this->adapters[$provider];
    }

    /**
     * @return list<string>
     */
    public function availableProviders(): array
    {
        return array_keys($this->adapters);
    }
}
