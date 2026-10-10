<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Application\DTOs\GatewayPaymentInitiation;
use App\Modules\Fundraising\Application\DTOs\GatewayPaymentUpdate;
use App\Modules\Fundraising\Domain\Contracts\FundraisingGatewayInterface;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;

/**
 * Passerelle « manuelle » des contributions hors ligne — méthodes `cash`
 * et `bank_transfer` (verticale FUNDRAISING, spec §4.2).
 *
 * La contribution est enregistrée `pending` et **confirmée par le
 * responsable du tenant** (espèces reçues / virement constaté) via
 * `ConfirmManualContribution` — pas de webhook, pas de vérification active.
 * Toujours configurée (aucun secret).
 */
final class ManualGateway implements FundraisingGatewayInterface
{
    public function gatewayName(): string
    {
        return 'manual';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function initiate(FundraisingContribution $contribution): GatewayPaymentInitiation
    {
        return new GatewayPaymentInitiation(
            providerReference: $contribution->reference,
            redirectUrl: null,
            ussdCode: null,
            instructions: 'Votre contribution sera confirmée par l\'organisateur dès réception '
                .($contribution->payment_method->value === 'cash' ? 'des espèces.' : 'du virement.'),
            status: 'pending',
        );
    }

    /**
     * Pas de webhook pour le manuel — toujours null (fail-closed).
     *
     * @return array<string, mixed>|null
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractPayment(array $payload): ?GatewayPaymentUpdate
    {
        return null;
    }

    public function verify(string $providerReference): ?GatewayPaymentUpdate
    {
        return null;
    }
}
