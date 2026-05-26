<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SocialContribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialContribution
 */
class SocialContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'country_code' => $this->country_code,
            'employee_rate' => $this->employee_rate,
            'employer_rate' => $this->employer_rate,
            'cap' => $this->cap,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
