<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Carbon;

/**
 * BIO-007 (#6772) — garde d'intégrité des synchronisations offline kiosque.
 *
 * L'enveloppe `device_state` (optionnelle, rétro-compatible) signe le batch :
 *   - `counter` : compteur monotone du client (strictement croissant) ;
 *   - `nonce` : fraîcheur du batch (anti-rejeu d'un batch copié) ;
 *   - `signed_at` : horodatage de signature (anti-horloge future) ;
 *   - `integrity` : HMAC-SHA256 hex (64) de
 *     `device_code\ncounter\nnonce\nsigned_at` avec pour clé le sync_token
 *     EN CLAIR (header X-Kiosk-Token) — seul le kiosque légitime le connaît.
 *
 * Garanties (acceptance BIO-007) :
 *   - un batch offline falsifié est rejeté (HMAC invalide → 422) ;
 *   - un rejeu (même batch ou un ancien batch) est rejeté (compteur ≤
 *     compteur acquitté → 409) ;
 *   - un batch signé dans le futur est rejeté (horloge dérivée → 422).
 *   L'ancienneté des événements (fenêtre offline bornée) est contrôlée par
 *   `KioskAttendanceService::syncPunches()` (politique
 *   `attendance.kiosk.offline.max_age_days`).
 */
final class KioskOfflineSyncGuard
{
    private const MAX_SIGNATURE_CLOCK_SKEW_SECONDS = 300;

    /**
     * Valide l'enveloppe signée d'un batch offline.
     *
     * @param  array<string, mixed>|null  $deviceState
     * @return int|null  compteur validé du batch, ou null en mode hérité
     *                   (pas d'enveloppe → pas d'exigence d'intégrité)
     */
    public function validateBatch(AttendanceKiosk $kiosk, ?array $deviceState, string $plainSyncToken, string $plainDeviceCode): ?int
    {
        if ($deviceState === null) {
            return null;
        }

        $counter = $deviceState['counter'] ?? null;
        $nonce = $deviceState['nonce'] ?? null;
        $signedAt = $deviceState['signed_at'] ?? null;
        $integrity = $deviceState['integrity'] ?? null;

        if (! is_int($counter) || $counter < 1) {
            abort(422, 'INVALID_SYNC_COUNTER');
        }

        if (! is_string($nonce) || $nonce === '' || mb_strlen($nonce) > 64) {
            abort(422, 'INVALID_SYNC_NONCE');
        }

        if (! is_string($integrity) || strlen($integrity) !== 64) {
            abort(422, 'INVALID_SYNC_INTEGRITY');
        }

        $signedAtCarbon = is_string($signedAt) ? $this->parseSignedAt($signedAt) : null;
        if ($signedAtCarbon === null) {
            abort(422, 'INVALID_SYNC_SIGNED_AT');
        }

        if ($signedAtCarbon->greaterThan(Carbon::now('UTC')->addSeconds(self::MAX_SIGNATURE_CLOCK_SKEW_SECONDS))) {
            abort(422, 'SYNC_CLOCK_SKEW');
        }

        $expected = hash_hmac(
            'sha256',
            $this->canonicalMessage($plainDeviceCode, $counter, $nonce, $signedAtCarbon),
            $plainSyncToken,
        );

        if (! hash_equals($expected, mb_strtolower($integrity))) {
            abort(422, 'SYNC_INTEGRITY_MISMATCH');
        }

        $acked = (int) $kiosk->acked_event_counter;

        // Rejeu d'un batch déjà acquitté (ou plus ancien) → 409 : le client
        // doit resynchroniser son état, jamais rejouer.
        if ($counter <= $acked) {
            abort(409, 'SYNC_COUNTER_STALE');
        }

        return $counter;
    }

    private function canonicalMessage(string $plainDeviceCode, int $counter, string $nonce, Carbon $signedAt): string
    {
        return implode("\n", [
            mb_strtoupper($plainDeviceCode),
            (string) $counter,
            $nonce,
            $signedAt->toIso8601String(),
        ]);
    }

    private function parseSignedAt(string $signedAt): ?Carbon
    {
        try {
            return Carbon::parse($signedAt)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
