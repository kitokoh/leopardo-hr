<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TrainingEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingEnrollment
 */
class TrainingEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_session_id' => $this->training_session_id,
            'employee_id' => $this->employee_id,
            'status' => $this->status,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
