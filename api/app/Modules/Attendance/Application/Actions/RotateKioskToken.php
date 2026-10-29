<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\BiometricAuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * #7962 (sous-tache) — cas d'usage : rotation du token de synchronisation
 * d'un kiosque.
 *
 * Extrait de `KioskController::rotateToken()`. Deux responsabilites sont
 * deliberement laissees au controleur, parce qu'elles relevent de la
 * frontiere HTTP et non du metier :
 *   - la generation du token EN CLAIR et son exposition unique dans la
 *     reponse (aucune autre reponse ne doit jamais le porter) ;
 *   - la forme exacte de la reponse.
 *
 * L'Action garantit l'invariant de securite : seule l'empreinte du token est
 * persistee (`sync_token_hash`), et l'audit `device.token_rotated` est ecrit
 * sans jamais journaliser le secret (regles de redaction BIO-008, #6773).
 */
class RotateKioskToken
{
    /** Longueur du token en clair (compatibilite clients kiosque existants). */
    public const TOKEN_LENGTH = 48;

    public function __construct(
        private readonly BiometricAuditLogger $biometricAudit,
    ) {}

    /**
     * Genere, persiste (empreinte uniquement) et audite un nouveau token.
     *
     * @return array{kiosk: AttendanceKiosk, plain_token: string}
     *                                                            `plain_token` n'est destine qu'a la reponse de rotation.
     */
    public function execute(AttendanceKiosk $kiosk, int $actorEmployeeId): array
    {
        $plainToken = Str::random(self::TOKEN_LENGTH);

        $kiosk->forceFill([
            'sync_token_hash' => Hash::make($plainToken),
        ])->save();

        $this->biometricAudit->log(
            companyId: (string) $kiosk->company_id,
            event: 'device.token_rotated',
            kioskId: (int) $kiosk->id,
            siteId: $kiosk->site_id !== null ? (int) $kiosk->site_id : null,
            actorEmployeeId: $actorEmployeeId,
            deviceCodeHash: (string) $kiosk->device_code,
        );

        return [
            'kiosk' => $kiosk->fresh() ?? $kiosk,
            'plain_token' => $plainToken,
        ];
    }
}
