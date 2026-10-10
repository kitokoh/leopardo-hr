<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Infrastructure\Services;

use App\Modules\Fundraising\Domain\Enums\PayoutMethod;
use App\Modules\Fundraising\Domain\Enums\PayoutStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingPayout;
use App\Modules\Fundraising\Domain\Support\ReferenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Placement transactionnel d'un reversement (verticale FUNDRAISING —
 * spec §3.3) : la règle de solde (Σ payouts requested|processing|paid ≤
 * collected_amount) est vérifiée DANS une transaction avec verrou ligne
 * sur la cagnotte — deux demandes concurrentes ne peuvent pas se
 * chevaucher. Couche Infrastructure (facade DB autorisée ici) ; la
 * validation d'entrée reste dans `RequestPayoutAction` (Application).
 */
final class PayoutPlacement
{
    /**
     * @param  array{amount: float, method: PayoutMethod, recipient_name: string, recipient_account: string}  $attributes
     */
    public function place(Fundraiser $fundraiser, array $attributes, ?int $requestedBy = null): FundraisingPayout
    {
        return DB::transaction(function () use ($fundraiser, $attributes, $requestedBy): FundraisingPayout {
            /** @var Fundraiser $locked */
            $locked = Fundraiser::query()->whereKey($fundraiser->id)->lockForUpdate()->firstOrFail();

            if ($attributes['amount'] > $locked->availableBalance() + 0.0001) {
                throw FundraisingException::payoutExceedsBalance();
            }

            /** @var FundraisingPayout $payout */
            $payout = FundraisingPayout::query()->create([
                'fundraiser_id' => $locked->id,
                'reference' => ReferenceGenerator::payout(),
                'amount' => $attributes['amount'],
                'currency' => (string) $locked->currency,
                'method' => $attributes['method'],
                'recipient_name' => $attributes['recipient_name'],
                'recipient_account' => $attributes['recipient_account'],
                'status' => PayoutStatus::REQUESTED,
                'requested_by' => $requestedBy,
            ]);

            return $payout;
        });
    }
}
