<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PaymentItem;
use Carbon\CarbonInterface;

/**
 * PA2-PAY-016 - Simple digital signature.
 *
 * Produces a timestamped, verifiable consent hash for payment confirmations
 * without introducing a premature PKI/certificate infrastructure. The hash
 * binds the confirmation to the exact payment item, amount, currency and
 * confirmation instant, so any tampering with those facts after the fact is
 * detectable by recomputing the hash and comparing it.
 */
class PaymentConsentSignatureService
{
    /**
     * Builds the canonical payload used to compute the document hash.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(PaymentItem $item, CarbonInterface $confirmedAt, string $documentVersion): array
    {
        return [
            'payment_item_id' => $item->id,
            'payment_batch_id' => $item->payment_batch_id,
            'employee_id' => $item->employee_id,
            'company_id' => $item->company_id,
            'amount' => number_format((float) $item->amount, 2, '.', ''),
            'currency' => $item->currency,
            'document_version' => $documentVersion,
            'confirmed_at' => $confirmedAt->toIso8601String(),
        ];
    }

    /**
     * Computes a stable SHA-256 hash for the given consent payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recomputes the expected hash for a payment item/confirmation instant
     * and returns whether it matches the stored hash (tamper detection).
     */
    public function verify(PaymentItem $item, CarbonInterface $confirmedAt, string $documentVersion, ?string $storedHash): bool
    {
        if ($storedHash === null || $storedHash === '') {
            return false;
        }

        return hash_equals($this->hash($this->buildPayload($item, $confirmedAt, $documentVersion)), $storedHash);
    }
}
