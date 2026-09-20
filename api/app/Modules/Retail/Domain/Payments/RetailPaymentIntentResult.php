<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Payments;

/**
 * Resultat de la creation d'un intent de paiement chez le provider
 * (BC-17 RETAIL, #7812).
 *
 * `checkoutUrl` est l'URL de paiement hebergee (null pour les providers
 * sans redirection). `providerReference` est l'identifiant cote PSP
 * (ex. id de checkout Chargily). `payload` est conserve tel quel sur
 * l'intent comme trace auditable.
 */
final readonly class RetailPaymentIntentResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?string $checkoutUrl,
        public ?string $providerReference,
        public array $payload = [],
    ) {}
}
