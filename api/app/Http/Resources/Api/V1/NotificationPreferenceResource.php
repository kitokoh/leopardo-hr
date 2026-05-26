<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationPreference
 */
class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'app_enabled' => $this->app_enabled,
            'email_enabled' => $this->email_enabled,
            'push_enabled' => $this->push_enabled,
            'sms_enabled' => $this->sms_enabled,
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'categories' => $this->categories,
            'quiet_hours' => $this->quiet_hours,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
