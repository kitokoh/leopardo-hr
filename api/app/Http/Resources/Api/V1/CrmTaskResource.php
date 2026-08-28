<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5712 — Contrat de sortie d'une tâche CRM client.
 */
class CrmTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'account_id' => $this->account_id,
            'contact_id' => $this->contact_id,
            'lead_id' => $this->lead_id,
            'opportunity_id' => $this->opportunity_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_at' => $this->due_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'assigned_to' => $this->assigned_to,
            'completed_by' => $this->completed_by,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
