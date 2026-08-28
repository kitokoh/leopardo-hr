<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Resources;

use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'un relevé FuelStation (issue #5798).
 *
 * @mixin FuelMeterReading
 */
final class FuelMeterReadingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meter_id' => $this->meter_id,
            'pump_id' => $this->pump_id,
            'site_id' => $this->site_id,
            'station_id' => $this->station_id,
            'operator_id' => $this->operator_id,
            'shift_id' => $this->shift_id,
            'reading_value' => $this->reading_value,
            'reading_at' => $this->reading_at?->toIso8601String(),
            'reading_at_local' => $this->reading_at_local,
            'delta' => $this->delta,
            'rollover' => $this->rollover,
            'anomaly' => $this->anomaly,
            'source' => $this->source,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
