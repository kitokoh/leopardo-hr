<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalRequest */
class ApprovalRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'workflow_id' => $this->workflow_id,
            'approvable_type' => $this->approvable_type,
            'approvable_id' => $this->approvable_id,
            'requester_id' => $this->requester_id,
            'current_level' => $this->current_level,
            'status' => $this->status,
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'first_name' => $this->requester->first_name,
                'last_name' => $this->requester->last_name,
            ]),
            'decisions' => $this->whenLoaded('decisions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
