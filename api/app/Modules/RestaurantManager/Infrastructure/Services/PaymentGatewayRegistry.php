<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\RestaurantManager\Domain\Exceptions\PaymentGatewayException;

/**
 * RESTO-406 (#6193) — Registre des passerelles de paiement de la verticale.
 *
 * Résolution par `provider_code` (cash|card|mobile_money) ; un fournisseur
 * inconnu produit une erreur normalisée (fail-closed). Les adapters sont
 * enregistrés par le service provider (singleton).
 */
final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->providerCode()] = $gateway;
    }

    public function has(string $providerCode): bool
    {
        return isset($this->gateways[$providerCode]);
    }

    public function resolve(string $providerCode): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$providerCode])) {
            throw new PaymentGatewayException(
                sprintf('Unsupported payment provider "%s".', $providerCode),
                'unsupported_provider',
            );
        }

        return $this->gateways[$providerCode];
    }

    /**
     * @return array<int, string>
     */
    public function availableProviders(): array
    {
        return array_keys($this->gateways);
    }
}
