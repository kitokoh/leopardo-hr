<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturePlanMatrixResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feature_key' => $this->feature_key,
            'plan' => $this->plan,
            'enabled' => $this->enabled,
            'limit_value' => $this->limit_value,
        ];
    }
}
