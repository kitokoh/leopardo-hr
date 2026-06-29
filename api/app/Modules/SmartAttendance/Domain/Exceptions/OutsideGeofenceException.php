<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Exceptions;

use RuntimeException;

class OutsideGeofenceException extends RuntimeException
{
    public function __construct(float $distanceMeters, float $radiusMeters)
    {
        parent::__construct(
            sprintf(
                'Position is outside the geofence (distance: %.0fm, radius: %.0fm).',
                $distanceMeters,
                $radiusMeters
            )
        );
    }
}
