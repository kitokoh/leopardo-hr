<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;

/**
 * Cas d'usage : rejet d'une session GPS par un manager ou RH.
 */
class RejectGeoSession
{
    public function handle(
        GeoAttendanceSession $session,
        Employee $validator,
        string $reason
    ): GeoAttendanceSession {
        $session->update([
            'status'          => GeoAttendanceSession::STATUS_REJECTED,
            'validated_by'    => $validator->id,
            'validated_at'    => now(),
            'validation_note' => $reason,
        ]);

        return $session->fresh();
    }
}
