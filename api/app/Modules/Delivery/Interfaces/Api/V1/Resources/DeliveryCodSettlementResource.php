<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Resources;

use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Règlement COD (DELIVERY-205, issue #6289) — allowlisté.
 *
 * @mixin DeliveryCodSettlement
 */
final class DeliveryCodSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'route_id' => $this->route_id,
            'driver_id' => $this->driver_id,
            'expected_minor' => (int) $this->expected_minor,
            'collected_minor' => (int) $this->collected_minor,
            'commission_minor' => (int) $this->commission_minor,
            'status' => $this->status,
            'accounting_ref' => $this->accounting_ref,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
        ];
    }
}
