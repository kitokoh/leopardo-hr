<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-411 (#6063) — Workflow de remboursement d'un paiement.
 *
 * Interroge le provider (`refund()`), met à jour l'état local et publie
 * l'événement `travel.payment.refunded.v1` après commit. Réservé
 * `travel.manage` (Policy). Idempotent : un paiement déjà remboursé ne
 * déclenche pas de second appel provider.
 */
final class RefundPaymentAction
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly TravelOutboxPublisher $outbox,
    ) {}

    public function execute(TravelPayment $payment, int $actorId, string $reason): TravelPayment
    {
        if ($payment->status === PaymentStatus::REFUNDED) {
            return $payment;
        }

        if ($payment->status !== PaymentStatus::CONFIRMED) {
            abort(422, 'Seul un paiement confirmé peut être remboursé.');
        }

        $this->gateways->get($payment->provider_code->value)->refund($payment->provider_reference);

        DB::transaction(function () use ($payment): void {
            $payment->forceFill(['status' => PaymentStatus::REFUNDED])->save();
        });

        $this->outbox->publish($payment->company_id, 'travel.payment.refunded.v1', [
            'payment_reference' => $payment->reference,
            'booking_id' => $payment->booking_id,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'refunded_by' => $actorId,
            'refunded_at' => now()->toIso8601String(),
            'reason' => $reason,
        ]);

        return $payment->refresh();
    }
}
