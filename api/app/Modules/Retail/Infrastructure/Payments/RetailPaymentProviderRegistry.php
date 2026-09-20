<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Payments;

use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderInterface;
use App\Modules\Retail\Domain\Exceptions\RetailPaymentProviderException;

/**
 * Registre des providers de paiement du module Retail (BC-17 RETAIL,
 * #7812 — pattern PaymentGatewayRegistry RestaurantManager RESTO-406).
 *
 * Resolution par code (`chargily|mock`), fail-closed sur un code inconnu.
 * `default()` lit `config('retail.payments.provider')` — fallback env
 * global en attendant les profils de paiement tenant BC-21 (PR #7732, non
 * merge) : la resolution PAR TENANT se branchera ici sans changer les
 * appelants.
 */
final class RetailPaymentProviderRegistry
{
    /** @var array<string, RetailPaymentProviderInterface> */
    private array $providers = [];

    public function register(RetailPaymentProviderInterface $provider): void
    {
        $this->providers[$provider->providerCode()] = $provider;
    }

    public function has(string $providerCode): bool
    {
        return isset($this->providers[$providerCode]);
    }

    public function resolve(string $providerCode): RetailPaymentProviderInterface
    {
        if (! isset($this->providers[$providerCode])) {
            throw new RetailPaymentProviderException(
                sprintf('Unsupported retail payment provider "%s".', $providerCode),
            );
        }

        return $this->providers[$providerCode];
    }

    /**
     * Provider actif de la plateforme (config env — futur point d'entree
     * de la resolution par tenant BC-21).
     */
    public function default(): RetailPaymentProviderInterface
    {
        return $this->resolve((string) config('retail.payments.provider'));
    }
}
