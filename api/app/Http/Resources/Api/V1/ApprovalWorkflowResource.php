<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ApprovalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalWorkflow
 */
class ApprovalWorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'model_type' => $this->model_type,
            'levels' => $this->levels,
            'auto_approve_below' => $this->auto_approve_below,
            'escalation_hours' => $this->escalation_hours,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
