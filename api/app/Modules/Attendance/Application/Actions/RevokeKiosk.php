<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\BiometricAuditLogger;

/**
 * #7962 (suite de #8944) — cas d'usage : révoquer un kiosque (BIO-005 #6766).
 *
 * Extrait de `KioskController::revoke()`. La révocation est **idempotente** :
 * révoquer un appareil déjà révoqué ne réécrit pas `revoked_at` (la date de la
 * première décision est la seule qui compte probatoirement) et ne produit pas
 * un second événement d'audit — deux traces pour un seul geste feraient croire
 * à deux révocations.
 */
class RevokeKiosk
{
    public function __construct(private readonly BiometricAuditLogger $audit) {}

    public function execute(AttendanceKiosk $kiosk, Employee $actor): AttendanceKiosk
    {
        if ($kiosk->isRevoked()) {
            return $kiosk;
        }

        $kiosk->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by_employee_id' => $actor->id,
        ])->save();

        $this->audit->log(
            companyId: (string) $kiosk->company_id,
            event: 'device.revoked',
            kioskId: (int) $kiosk->id,
            siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
            actorEmployeeId: (int) $actor->id,
            correlationId: (string) $kiosk->device_code,
            deviceCodeHash: (string) $kiosk->device_code,
        );

        return $kiosk;
    }
}
