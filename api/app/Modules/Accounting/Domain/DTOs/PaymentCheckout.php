<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\DTOs;

use Carbon\CarbonImmutable;

/**
 * #5272 — Session de checkout initiée auprès d'une passerelle de paiement
 * (Chargily DZ / Stripe). Valeur retournée par PaymentGatewayInterface.
 */
final readonly class PaymentCheckout
{
    public function __construct(
        /** URL publique de paiement hébergée par la passerelle. */
        public string $url,
        /** Identifiant externe de la session (checkout id côté passerelle). */
        public string $gatewayCheckoutId,
        /** Nom canonique de la passerelle (chargily | stripe). */
        public string $gateway,
        /** Expiration de la session de paiement. */
        public CarbonImmutable $expiresAt,
    ) {}
}
