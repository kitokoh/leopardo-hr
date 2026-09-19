<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Services;

use App\Modules\Retail\Domain\Contracts\RetailPaymentProviderContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Seam journalisé du port PSP marketplace (BC-17 RETAIL, #7812).
 *
 * Les profils de paiement tenant BC-21 (mobile money / carte, branche
 * bc/bc21-paiements-encaissement) ne sont pas encore mergés : cet adapter
 * génère une référence provider unique (`RPI-…`), journalise l'initiation
 * (PII minimale : téléphone haché) et laisse le webhook signé confirmer —
 * même pattern que `LoggingDeliveryAccountingAdapter` /
 * `LoggingRecipientMessageAdapter` (DELIVERY-205/206). Le remplacement par
 * l'adapter BC-21 réel se fait par le binding du contrat dans
 * `RetailServiceProvider` (aucun autre fichier à toucher).
 */
final class LoggingRetailPaymentProviderAdapter implements RetailPaymentProviderContract
{
    public function initiate(
        string $companyId,
        string $orderReference,
        int $amountMinor,
        string $currency,
        ?string $customerPhone,
    ): array {
        $providerReference = 'RPI-'.now()->format('Y').'-'.Str::upper(Str::random(20));

        $checkoutBaseUrl = config('services.retail_market.checkout_base_url');
        $checkoutUrl = is_string($checkoutBaseUrl) && $checkoutBaseUrl !== ''
            ? rtrim($checkoutBaseUrl, '/').'/'.$providerReference
            : null;

        Log::info('Retail marketplace payment intent initiated (BC-21 seam).', [
            'company_id' => $companyId,
            'order_reference' => $orderReference,
            'provider_reference' => $providerReference,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'customer_phone_hash' => $customerPhone !== null ? hash('sha256', $customerPhone) : null,
        ]);

        return [
            'provider' => 'mobile_money',
            'provider_reference' => $providerReference,
            'checkout_url' => $checkoutUrl,
        ];
    }
}
