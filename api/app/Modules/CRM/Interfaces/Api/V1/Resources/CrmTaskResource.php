<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5720 — Ressource tâche CRM (assignee + account eager-loadés).
 */
class CrmTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_at' => $this->due_at?->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee') && $this->assignee ? [
                'id' => $this->assignee->id,
                'first_name' => $this->assignee->first_name,
                'last_name' => $this->assignee->last_name,
            ] : null,
            'account' => $this->whenLoaded('account') && $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ] : null,
            'contact_id' => $this->contact_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
