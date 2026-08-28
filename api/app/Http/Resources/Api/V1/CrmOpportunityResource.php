<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5712 — Contrat de sortie d'une opportunité CRM client.
 */
class CrmOpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'currency' => $this->currency,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'owner_id' => $this->owner_id,
            'source' => $this->source,
            'description' => $this->description,
            'won_at' => $this->won_at?->toISOString(),
            'lost_at' => $this->lost_at?->toISOString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
