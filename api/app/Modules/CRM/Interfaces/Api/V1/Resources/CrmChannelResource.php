<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'un canal CRM — les secrets n'existent pas dans `settings`
 * (refusés à l'écriture, cf. ConfigureChannelRequest).
 *
 * @mixin CrmChannel
 */
final class CrmChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'provider' => $this->provider,
            'status' => $this->status,
            'is_configured' => $this->is_configured,
            'monthly_quota' => $this->monthly_quota,
            'used_this_month' => $this->used_this_month,
            'quota_period' => $this->quota_period,
            'settings' => $this->settings ?? [],
            'last_error_message' => $this->last_error_message,
            'last_error_at' => $this->last_error_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
