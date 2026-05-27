<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'applicant_id' => $this->applicant_id,
            'interviewer_id' => $this->interviewer_id,
            'type' => $this->type,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'feedback' => $this->feedback,
            'rating' => $this->rating,
            'interviewer' => $this->whenLoaded('interviewer'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
