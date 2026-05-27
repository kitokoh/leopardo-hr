<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SalaryStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalaryStructure
 */
class SalaryStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'base_salary' => $this->base_salary,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'components' => SalaryComponentResource::collection($this->whenLoaded('components')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
