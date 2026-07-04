<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application\Actions;

use App\Models\Partner;
use App\Models\PartnerPayoutRequest;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Partner requests a commission payout.
 */
final class RequestPayout
{
    public function execute(Partner $partner, float $amount, string $method = 'bank_transfer'): PartnerPayoutRequest
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payout amount must be positive.');
        }

        return DB::transaction(function () use ($partner, $amount, $method): PartnerPayoutRequest {
            /** @var PartnerPayoutRequest $request */
            $request = PartnerPayoutRequest::create([
                'partner_id' => $partner->id,
                'amount'     => $amount,
                'method'     => $method,
                'status'     => 'pending',
            ]);

            return $request;
        });
    }
}
