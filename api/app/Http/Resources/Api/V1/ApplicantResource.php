<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Applicant
 */
class ApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_posting_id' => $this->job_posting_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'status' => $this->status,
            'stage' => $this->status,
            'rating' => $this->rating,
            'resume_url' => $this->resume_path,
            'notes' => $this->notes,
            'interviews' => $this->whenLoaded('interviews'),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($event) => [
                'id' => $event->id,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'note' => $event->note,
                'actor_type' => $event->actor_type,
                'changed_at' => $event->changed_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

