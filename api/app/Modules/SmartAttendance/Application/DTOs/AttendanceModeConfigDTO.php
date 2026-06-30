<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\DTOs;

final class AttendanceModeConfigDTO
{
    public function __construct(
        public readonly string $mode,           // gps_auto | qr | manual | mixed
        public readonly bool   $canOverride,    // true = l'employé peut changer son mode
        public readonly bool   $gpsEnabled,
        public readonly ?float $geofenceLat,
        public readonly ?float $geofenceLng,
        public readonly int    $geofenceRadius,
        public readonly bool   $requiresConsent,
    ) {}
}
