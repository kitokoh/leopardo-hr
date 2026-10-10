<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\PayoutMethod;
use App\Modules\Fundraising\Domain\Enums\PayoutStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingPayout;
use App\Modules\Fundraising\Domain\Support\ReferenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Demande de reversement au bénéficiaire (verticale FUNDRAISING — spec
 * §3.3) : la règle de solde (Σ payouts requested|processing|paid ≤
 * collected_amount) est vérifiée DANS une transaction avec verrou ligne
 * sur la cagnotte — deux demandes concurrentes ne peuvent pas se
 * chevaucher.
 */
final class RequestPayoutAction
{
    /**
     * @param  array<string, mixed>  $data  payload validé (RequestPayoutRequest)
     */
    public function handle(Fundraiser $fundraiser, array $data, ?string $requestedBy = null): FundraisingPayout
    {
        if (! $fundraiser->status->allowsPayout()) {
            throw FundraisingException::invalidStatusTransition($fundraiser->status->value, 'payout');
        }

        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw FundraisingException::payoutExceedsBalance();
        }

        return DB::transaction(function () use ($fundraiser, $data, $amount, $requestedBy): FundraisingPayout {
            /** @var Fundraiser $locked */
            $locked = Fundraiser::query()->whereKey($fundraiser->id)->lockForUpdate()->firstOrFail();

            if ($amount > $locked->availableBalance() + 0.0001) {
                throw FundraisingException::payoutExceedsBalance();
            }

            /** @var FundraisingPayout $payout */
            $payout = FundraisingPayout::query()->create([
                'fundraiser_id' => $locked->id,
                'reference' => ReferenceGenerator::payout(),
                'amount' => $amount,
                'currency' => (string) $locked->currency,
                'method' => PayoutMethod::from((string) $data['method']),
                'recipient_name' => (string) $data['recipient_name'],
                'recipient_account' => (string) $data['recipient_account'],
                'status' => PayoutStatus::REQUESTED,
                'requested_by' => $requestedBy,
            ]);

            return $payout;
        });
    }
}
