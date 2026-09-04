<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services\Payment;

use App\Modules\TravelAgency\Domain\Contracts\PaymentGatewayInterface;

/**
 * TRAVEL-405 (#6057) — Registre des passerelles de paiement TravelAgency.
 *
 * Résout un adaptateur par code provider (`cash`, `pvit`, …). Les clés de
 * configuration des providers vivent dans `config/travel.php` / env —
 * jamais en dur.
 */
final class PaymentGatewayRegistry
{
    /**
     * @param  array<string, PaymentGatewayInterface>  $gateways
     */
    public function __construct(private readonly array $gateways) {}

    public function get(string $providerCode): PaymentGatewayInterface
    {
        return $this->gateways[$providerCode]
            ?? throw new \InvalidArgumentException("Passerelle de paiement inconnue : {$providerCode}");
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->gateways);
    }
}
