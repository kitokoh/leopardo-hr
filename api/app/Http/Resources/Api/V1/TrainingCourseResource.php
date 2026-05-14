<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TrainingCourse
 */
class TrainingCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'type' => $this->type,
            'provider' => $this->provider,
            'duration_hours' => $this->duration_hours,
            'max_participants' => $this->max_participants,
            'cost_per_participant' => $this->cost_per_participant,
            'currency' => $this->currency,
            'sessions' => $this->whenLoaded('sessions'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
