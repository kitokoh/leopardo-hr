<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmLead
 */
class CrmLeadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'source' => $this->source,
            'status' => $this->status,
            'priority' => $this->priority,
            'owner_id' => $this->owner_id,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at?->toIso8601String(),
            'converted_opportunity_id' => $this->converted_opportunity_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
