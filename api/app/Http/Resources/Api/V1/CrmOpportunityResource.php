<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmOpportunity
 */
class CrmOpportunityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'pipeline_id' => $this->pipeline_id,
            'stage_id' => $this->stage_id,
            'name' => $this->name,
            'account_id' => $this->account_id,
            'converted_from_lead_id' => $this->converted_from_lead_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'owner_id' => $this->owner_id,
            'source' => $this->source,
            'description' => $this->description,
            // État dérivé de l'étape courante (is_won / is_lost).
            'is_won' => $this->isWon(),
            'is_lost' => $this->isLost(),
            'won_at' => $this->won_at?->toIso8601String(),
            'lost_at' => $this->lost_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
