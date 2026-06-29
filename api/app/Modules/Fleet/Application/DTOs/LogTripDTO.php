<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\DTOs;

use Carbon\Carbon;

final class LogTripDTO
{
    public function __construct(
        public readonly int    $vehicleId,
        public readonly int    $driverId,
        public readonly int    $companyId,
        public readonly Carbon $startedAt,
        public readonly float  $startMileage,
        public readonly ?Carbon $endedAt = null,
        public readonly ?float  $endMileage = null,
        public readonly ?string $purpose = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            vehicleId:   (int) $data['vehicle_id'],
            driverId:    (int) $data['driver_id'],
            companyId:   (int) $data['company_id'],
            startedAt:   Carbon::parse($data['started_at']),
            startMileage:(float) $data['start_mileage'],
            endedAt:     isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : null,
            endMileage:  isset($data['end_mileage']) ? (float) $data['end_mileage'] : null,
            purpose:     $data['purpose'] ?? null,
        );
    }
}
