<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmPipelineStage
 */
class CrmPipelineStageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'position' => $this->position,
            'color' => $this->color,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
