<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingSession
 */
class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_course_id' => $this->training_course_id,
            'trainer_id' => $this->trainer_id,
            'external_trainer' => $this->external_trainer,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'trainer' => $this->whenLoaded('trainer'),
            'enrollments' => TrainingEnrollmentResource::collection($this->whenLoaded('enrollments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
