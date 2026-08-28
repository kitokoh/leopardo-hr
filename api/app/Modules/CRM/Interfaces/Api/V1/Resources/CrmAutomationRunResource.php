<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmAutomationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'une exécution d'automatisation CRM (issue #5728).
 *
 * @mixin CrmAutomationRun
 */
final class CrmAutomationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'automation_id' => $this->automation_id,
            'trigger_event' => $this->trigger_event,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'dry_run' => $this->dry_run,
            'error' => $this->error,
            'ran_at' => $this->ran_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
