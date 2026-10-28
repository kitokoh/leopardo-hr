<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\BiometricAuditLogger;
use App\Modules\Attendance\Infrastructure\Services\KioskCredentialFactory;

/**
 * #7962 (suite de #8944) — cas d'usage : rotation du token de synchronisation.
 *
 * Extrait de `KioskController::rotateToken()`. La rotation invalide l'ancien
 * token **immédiatement** (remplacement du hash) — c'est le but du geste : si
 * un terminal est soupçonné compromis, l'ancien secret ne doit plus passer dans
 * la seconde qui suit, pas après expiration.
 *
 * L'audit est écrit dans la même transaction implicite que la sauvegarde : une
 * rotation non tracée rendrait impossible de savoir quand un token a fuité.
 */
class RotateKioskToken
{
    public function __construct(
        private readonly BiometricAuditLogger $audit,
        private readonly KioskCredentialFactory $credentials,
    ) {}

    /**
     * @return string Le token en clair — rendu une seule fois, au terminal.
     */
    public function execute(AttendanceKiosk $kiosk, Employee $actor): string
    {
        $plainToken = $this->credentials->newSyncToken();

        $kiosk->forceFill([
            'sync_token_hash' => $this->credentials->storeSyncToken($plainToken),
        ])->save();

        $this->audit->log(
            companyId: (string) $kiosk->company_id,
            event: 'device.token_rotated',
            kioskId: (int) $kiosk->id,
            siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
            actorEmployeeId: (int) $actor->id,
            deviceCodeHash: (string) $kiosk->device_code,
        );

        return $plainToken;
    }
}
