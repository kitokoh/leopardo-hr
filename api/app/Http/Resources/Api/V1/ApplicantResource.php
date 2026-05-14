<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Applicant
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
            'stage' => $this->stage,
            'rating' => $this->rating,
            'resume_url' => $this->resume_url,
            'interviews' => $this->whenLoaded('interviews'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
