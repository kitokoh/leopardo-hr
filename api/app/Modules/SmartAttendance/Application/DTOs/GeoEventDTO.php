<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\DTOs;

use Illuminate\Support\Carbon;

final class GeoEventDTO
{
    public function __construct(
        public readonly int    $employeeId,
        public readonly string $companyId,
        public readonly string $eventType,     // zone_enter | zone_exit
        public readonly float  $latitude,
        public readonly float  $longitude,
        public readonly ?int   $accuracyMeters,
        public readonly ?Carbon $deviceTimestamp,
        public readonly array  $metadata = [],
    ) {}

    public static function fromRequest(
        int    $employeeId,
        string $companyId,
        array  $data
    ): self {
        return new self(
            employeeId:      $employeeId,
            companyId:       $companyId,
            eventType:       $data['event_type'],
            latitude:        (float) $data['latitude'],
            longitude:       (float) $data['longitude'],
            accuracyMeters:  isset($data['accuracy_meters']) ? (int) $data['accuracy_meters'] : null,
            deviceTimestamp: isset($data['device_timestamp'])
                ? Carbon::parse($data['device_timestamp'])
                : null,
            metadata:        $data['metadata'] ?? [],
        );
    }
}
