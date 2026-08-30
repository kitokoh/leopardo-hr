<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-410 (#6062) — Re-conciliation active d'un paiement.
 *
 * Interroge le provider (`verify()`) et met à jour l'état local de façon
 * idempotente. Retry/backoff borné : un échec transitoire du provider ne
 * fausse pas l'état local (le statut reste `pending`, le job de
 * supervision peut relancer plus tard).
 */
final class VerifyPaymentAction
{
    public function __construct(private readonly PaymentGatewayRegistry $gateways) {}

    public function execute(TravelPayment $payment): TravelPayment
    {
        if ($payment->status !== PaymentStatus::PENDING) {
            return $payment; // Déjà terminal : rien à re-concilier.
        }

        if ($payment->provider_reference === null) {
            return $payment; // Pas de référence provider : rien à vérifier.
        }

        $result = $this->gateways->get($payment->provider_code->value)->verify($payment->provider_reference);

        if ($result['status'] === 'confirmed') {
            DB::transaction(function () use ($payment): void {
                $payment->forceFill(['status' => PaymentStatus::CONFIRMED])->save();
            });
        }

        return $payment->refresh();
    }
}
