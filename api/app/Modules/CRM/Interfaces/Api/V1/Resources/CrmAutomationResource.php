<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmAutomation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'une automatisation CRM (issue #5728).
 *
 * @mixin CrmAutomation
 */
final class CrmAutomationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trigger_event' => $this->trigger_event,
            'conditions' => $this->conditions ?? [],
            'actions' => $this->actions,
            'status' => $this->status,
            'version' => $this->version,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
