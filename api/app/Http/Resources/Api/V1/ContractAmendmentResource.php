<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ContractAmendment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractAmendment
 */
class ContractAmendmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'amendment_type' => $this->amendment_type,
            'changes' => $this->changes,
            'effective_date' => $this->effective_date?->toDateString(),
            'reason' => $this->reason,
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
