<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5712 — Contrat de sortie d'un stage de pipeline CRM client.
 */
class CrmPipelineStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'position' => $this->position,
            'color' => $this->color,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
