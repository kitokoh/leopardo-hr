<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Billing\Domain\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebhookDelivery */
class WebhookDeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'event' => $this->event,
            'response_code' => $this->response_code,
            'response_body' => $this->response_body,
            'duration_ms' => $this->duration_ms,
            'dead_lettered_at' => $this->dead_lettered_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
