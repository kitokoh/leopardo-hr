<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Issue #5720 — Ressource activité CRM (timeline d'account, append-only).
 */
/**
 * @property int $id
 * @property string $type
 * @property string $subject
 * @property string|null $description
 * @property string $created_at
 */
class CrmActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject' => $this->subject,
            'description' => $this->description,
            'contact_id' => $this->contact_id,
            'lead_id' => $this->lead_id,
            'opportunity_id' => $this->opportunity_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'actor' => $this->whenLoaded('actor') && $this->actor ? [
                'id' => $this->actor->id,
                'first_name' => $this->actor->first_name,
                'last_name' => $this->actor->last_name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
