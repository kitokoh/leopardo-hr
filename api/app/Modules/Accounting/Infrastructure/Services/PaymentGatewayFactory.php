<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\PaymentGatewayInterface;
use App\Modules\Accounting\Domain\Exceptions\PaymentGatewayNotConfiguredException;

/**
 * #5272 — Routage des passerelles par pays de l'entreprise (ADR-0017,
 * option A) : DZ → Chargily ; FR/UK/US/CI → Stripe. Tout autre pays refuse
 * le checkout (fail-closed, PAYMENT_GATEWAY_NOT_CONFIGURED).
 */
final class PaymentGatewayFactory
{
    /** @var array<string, PaymentGatewayInterface> pays → passerelle */
    private array $gateways;

    public function __construct(ChargilyPaymentGateway $chargily, StripePaymentGateway $stripe)
    {
        $this->gateways = [
            'DZ' => $chargily,
            'FR' => $stripe,
            'GB' => $stripe,
            'US' => $stripe,
            'CI' => $stripe,
        ];
    }

    public function forCountry(string $country): PaymentGatewayInterface
    {
        $gateway = $this->gateways[strtoupper($country)] ?? null;

        if ($gateway === null) {
            throw new PaymentGatewayNotConfiguredException($country);
        }

        return $gateway;
    }

    public function byName(string $name): ?PaymentGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->gatewayName() === $name) {
                return $gateway;
            }
        }

        return null;
    }
}
