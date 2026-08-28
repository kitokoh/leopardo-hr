<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\CRM\Domain\Models\CrmActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmActivity
 */
class CrmActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'subject' => $this->subject,
            'activity_type' => $this->activity_type,
            'description' => $this->description,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'owner_id' => $this->owner_id,
            'happened_at' => $this->happened_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
