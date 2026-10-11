<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Resources;

use App\Modules\Fundraising\Domain\Models\FundraisingPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation PRIVÉE d'un reversement (gestion tenant).
 *
 * @mixin FundraisingPayout
 */
final class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FundraisingPayout $payout */
        $payout = $this->resource;

        return [
            'id' => $payout->id,
            'reference' => $payout->reference,
            'fundraiser_id' => $payout->fundraiser_id,
            'amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'method' => $payout->method->value,
            'method_label' => $payout->method->label(),
            'recipient_name' => $payout->recipient_name,
            'recipient_account' => $payout->recipient_account,
            'status' => $payout->status->value,
            'status_label' => $payout->status->label(),
            'provider_reference' => $payout->provider_reference,
            'failure_reason' => $payout->failure_reason,
            'processed_at' => $payout->processed_at?->toIso8601String(),
            'created_at' => $payout->created_at?->toIso8601String(),
        ];
    }
}
