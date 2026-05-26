<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaxSlab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxSlab
 */
class TaxSlabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_code' => $this->country_code,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'rate' => $this->rate,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
