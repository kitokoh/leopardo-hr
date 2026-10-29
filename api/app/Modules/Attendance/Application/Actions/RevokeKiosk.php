<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\BiometricAuditLogger;

/**
 * #7962 (sous-tache) — cas d'usage : revocation d'un kiosque de pointage.
 *
 * Extrait de `KioskController::revoke()`. Le controleur ne garde que
 * l'orchestration HTTP (autorisation manager, resolution tenant, code de
 * statut) ; la transition d'etat et son journal d'audit vivent ici.
 *
 * Invariant conserve depuis l'extraction : la revocation est IDEMPOTENTE.
 * Un kiosque deja revoque n'est ni reecrit ni re-audite — rejouer la
 * revocation (double clic manager, retry reseau) ne doit pas produire un
 * second evenement d'audit `device.revoked`, sinon le journal perd sa
 * valeur probante (BIO-008, #6773).
 */
class RevokeKiosk
{
    public function __construct(
        private readonly BiometricAuditLogger $biometricAudit,
    ) {}

    /**
     * @return AttendanceKiosk le kiosque relu apres transition (etat courant)
     */
    public function execute(AttendanceKiosk $kiosk, int $actorEmployeeId): AttendanceKiosk
    {
        if ($kiosk->isRevoked()) {
            // Idempotence : rien a faire, aucun audit supplementaire.
            return $kiosk;
        }

        $kiosk->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by_employee_id' => $actorEmployeeId,
        ])->save();

        $this->biometricAudit->log(
            companyId: (string) $kiosk->company_id,
            event: 'device.revoked',
            kioskId: (int) $kiosk->id,
            siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
            actorEmployeeId: $actorEmployeeId,
            correlationId: (string) $kiosk->device_code,
            deviceCodeHash: (string) $kiosk->device_code,
        );

        return $kiosk->fresh() ?? $kiosk;
    }
}
