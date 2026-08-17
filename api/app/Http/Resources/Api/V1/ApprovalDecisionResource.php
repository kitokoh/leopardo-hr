<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Attendance\Domain\Models\ApprovalDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalDecision */
class ApprovalDecisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'approval_request_id' => $this->approval_request_id,
            'level' => $this->level,
            'approver_id' => $this->approver_id,
            'decision' => $this->decision,
            'comment' => $this->comment,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'request' => $this->whenLoaded('request', fn () => [
                'id' => $this->request->id,
                'status' => $this->request->status,
                'approvable_type' => $this->request->approvable_type,
                'approvable_id' => $this->request->approvable_id,
                'requester_id' => $this->request->requester_id,
                'requester' => $this->request->relationLoaded('requester') ? [
                    'id' => $this->request->requester->id,
                    'first_name' => $this->request->requester->first_name,
                    'last_name' => $this->request->requester->last_name,
                ] : null,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
