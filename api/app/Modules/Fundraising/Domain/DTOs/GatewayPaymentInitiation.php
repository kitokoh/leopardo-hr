<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\DTOs;

/**
 * Résultat de l'initiation d'un paiement de contribution (verticale
 * FUNDRAISING — spec §4.1).
 *
 * - `redirect_url` : page de paiement hébergée (Stripe Checkout — carte) ;
 * - `ussd_code` / `instructions` : paiement mobile money (push ou code à
 *   composer par le contributeur) ;
 * - `status` : état de la contribution après initiation (`pending`
 *   attendu ; `completed` possible en sandbox manuel immédiat — jamais
 *   crédité deux fois, cf. ApplyPaymentUpdate).
 */
final readonly class GatewayPaymentInitiation
{
    public function __construct(
        public string $providerReference,
        public ?string $redirectUrl,
        public ?string $ussdCode,
        public ?string $instructions,
        public string $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_reference' => $this->providerReference,
            'redirect_url' => $this->redirectUrl,
            'ussd_code' => $this->ussdCode,
            'instructions' => $this->instructions,
            'status' => $this->status,
        ];
    }
}
