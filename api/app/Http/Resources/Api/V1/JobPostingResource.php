<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\JobPosting
 */
class JobPostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'department' => $this->whenLoaded('department'),
            'location' => $this->location,
            'remote_policy' => $this->remote_policy,
            'contract_type' => $this->contract_type,
            'salary_range_min' => $this->salary_range_min,
            'salary_range_max' => $this->salary_range_max,
            'currency' => $this->currency,
            'skills_required' => $this->skills_required,
            'status' => $this->status,
            'closes_at' => $this->closes_at?->toDateString(),
            'applicants_count' => $this->whenCounted('applicants'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
