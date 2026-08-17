<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Attendance\Domain\Models\ApprovalDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin ApprovalDecision */
class ApprovalDecisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ApprovalDecision) {
            throw new LogicException('ApprovalDecisionResource requires an ApprovalDecision resource.');
        }

        $decision = $this->resource;

        return [
            'id' => $decision->id,
            'approval_request_id' => $decision->approval_request_id,
            'level' => $decision->level,
            'approver_id' => $decision->approver_id,
            'decision' => $decision->decision,
            'comment' => $decision->comment,
            'decided_at' => $decision->decided_at?->toIso8601String(),
            'request' => $this->whenLoaded('request', function () use ($decision): ?array {
                $approvalRequest = $decision->request;

                if ($approvalRequest === null) {
                    return null;
                }

                $requester = $approvalRequest->relationLoaded('requester')
                    ? $approvalRequest->requester
                    : null;

                return [
                    'id' => $approvalRequest->id,
                    'status' => $approvalRequest->status,
                    'approvable_type' => $approvalRequest->approvable_type,
                    'approvable_id' => $approvalRequest->approvable_id,
                    'requester_id' => $approvalRequest->requester_id,
                    'requester' => $requester === null ? null : [
                        'id' => $requester->id,
                        'first_name' => $requester->first_name,
                        'last_name' => $requester->last_name,
                    ],
                ];
            }),
            'created_at' => $decision->created_at->toIso8601String(),
        ];
    }
}
