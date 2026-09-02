<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskOfflineSyncGuard;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * QLT-001 (#6775) — garde d'intégrité des synchronisations offline kiosque
 * (BIO-007 #6772), testée en isolation unitaire.
 *
 * Miroir exact du harnais des tests unitaires du module (PunchRecordingPolicyTest,
 * BiometricEnrollmentStateMachineTest) : PHPUnit pur, aucun conteneur Laravel,
 * aucun accès base de données. Le kiosque est un modèle Eloquent non persisté
 * (forceFill sur `acked_event_counter`) — le garde ne lit que l'attribut.
 *
 * Scénarios : enveloppe absente (mode hérité) → null ; HMAC valide → compteur ;
 * intégrité falsifiée → 422 SYNC_INTEGRITY_MISMATCH ; compteur ≤ acquitté →
 * 409 SYNC_COUNTER_STALE ; horodatage futur → 422 SYNC_CLOCK_SKEW ; champs
 * malformés → codes 422 dédiés.
 *
 * Format canonique du message signé (BIO-007) :
 *   hash_hmac('sha256', "DEVICE_CODE\ncounter\nnonce\nsigned_at_iso8601", sync_token)
 */
final class KioskOfflineSyncGuardTest extends TestCase
{
    private const DEVICE_CODE = 'QLTK01AB12';

    private const SYNC_TOKEN = 'sync-token-plain-0123456789abcdef';

    private KioskOfflineSyncGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new KioskOfflineSyncGuard();
    }

    public function test_legacy_null_envelope_passes_without_integrity_requirement(): void
    {
        $kiosk = $this->kioskWithAckedCounter(0);

        // Batch hérité (pas d'enveloppe `device_state`) : aucune exigence
        // d'intégrité — la garde laisse passer et ne valide aucun compteur.
        $this->assertNull($this->guard->validateBatch($kiosk, null, self::SYNC_TOKEN, self::DEVICE_CODE));
    }

    public function test_valid_hmac_returns_the_batch_counter(): void
    {
        $kiosk = $this->kioskWithAckedCounter(3);
        $counter = 4;
        $nonce = 'nonce-valid-001';
        $signedAt = Carbon::now('UTC')->subSeconds(30);

        $deviceState = $this->signedBatch($counter, $nonce, $signedAt);

        $this->assertSame($counter, $this->guard->validateBatch($kiosk, $deviceState, self::SYNC_TOKEN, self::DEVICE_CODE));
    }

    public function test_device_code_casing_is_normalized_before_hmac(): void
    {
        $kiosk = $this->kioskWithAckedCounter(0);
        $counter = 1;
        $signedAt = Carbon::now('UTC')->subSeconds(30);

        // Le device_code est signé en MAJUSCULES : un client qui présenterait
        // une forme en minuscules dans l'URL reste authentifiable.
        $deviceState = $this->signedBatch($counter, 'nonce-case-001', $signedAt, mb_strtolower(self::DEVICE_CODE));

        $this->assertSame($counter, $this->guard->validateBatch($kiosk, $deviceState, self::SYNC_TOKEN, mb_strtolower(self::DEVICE_CODE)));
    }

    public function test_tampered_integrity_is_rejected_with_422(): void
    {
        $kiosk = $this->kioskWithAckedCounter(0);
        $deviceState = $this->signedBatch(1, 'nonce-tampered-001', Carbon::now('UTC')->subSeconds(30));

        // Falsification : le HMAC ne correspond plus au message canonique.
        $deviceState['integrity'] = str_repeat('0', 64);

        $this->assertRejected($deviceState, 422, 'SYNC_INTEGRITY_MISMATCH');
    }

    public function test_uppercased_integrity_is_still_rejected_when_it_does_not_match(): void
    {
        $kiosk = $this->kioskWithAckedCounter(0);
        $deviceState = $this->signedBatch(1, 'nonce-case-002', Carbon::now('UTC')->subSeconds(30));

        // La comparaison est insensible à la casse du HMAC fourni, mais une
        // valeur fausse reste refusée.
        $deviceState['integrity'] = mb_strtoupper(str_repeat('a', 64));

        $this->assertRejected($deviceState, 422, 'SYNC_INTEGRITY_MISMATCH');
    }

    public function test_stale_counter_at_or_below_acked_is_rejected_with_409(): void
    {
        foreach ([5, 4, 1] as $staleCounter) {
            $kiosk = $this->kioskWithAckedCounter(5);
            $deviceState = $this->signedBatch($staleCounter, 'nonce-stale-'.(string) $staleCounter, Carbon::now('UTC')->subSeconds(30));

            $this->assertRejected($deviceState, 409, 'SYNC_COUNTER_STALE', "compteur {$staleCounter} ≤ acquitté 5");
        }
    }

    public function test_future_signed_at_is_rejected_with_422_clock_skew(): void
    {
        $kiosk = $this->kioskWithAckedCounter(0);

        // Au-delà de la tolérance d'horloge (300 s) → horloge dérivée.
        $deviceState = $this->signedBatch(1, 'nonce-future-001', Carbon::now('UTC')->addSeconds(301));

        $this->assertRejected($deviceState, 422, 'SYNC_CLOCK_SKEW');
    }

    public function test_malformed_fields_are_rejected_with_dedicated_422_codes(): void
    {
        $scenarios = [
            'counter manquant' => $this->dropKey($this->signedBatch(1, 'nonce-mal-001', Carbon::now('UTC')->subSeconds(30)), 'counter'),
            'counter zéro' => $this->signedBatch(0, 'nonce-mal-002', Carbon::now('UTC')->subSeconds(30)),
            'counter négatif' => $this->signedBatch(-1, 'nonce-mal-003', Carbon::now('UTC')->subSeconds(30)),
            'counter non entier (chaîne)' => $this->replaceKey($this->signedBatch(1, 'nonce-mal-004', Carbon::now('UTC')->subSeconds(30)), 'counter', '1'),
            'nonce manquant' => $this->dropKey($this->signedBatch(1, 'nonce-mal-005', Carbon::now('UTC')->subSeconds(30)), 'nonce'),
            'nonce vide' => $this->replaceKey($this->signedBatch(1, 'nonce-mal-006', Carbon::now('UTC')->subSeconds(30)), 'nonce', ''),
            'nonce trop long (65)' => $this->replaceKey($this->signedBatch(1, 'nonce-mal-007', Carbon::now('UTC')->subSeconds(30)), 'nonce', str_repeat('n', 65)),
            'integrity manquant' => $this->dropKey($this->signedBatch(1, 'nonce-mal-008', Carbon::now('UTC')->subSeconds(30)), 'integrity'),
            'integrity trop court' => $this->replaceKey($this->signedBatch(1, 'nonce-mal-009', Carbon::now('UTC')->subSeconds(30)), 'integrity', 'abcd'),
            'signed_at manquant' => $this->dropKey($this->signedBatch(1, 'nonce-mal-010', Carbon::now('UTC')->subSeconds(30)), 'signed_at'),
            'signed_at illisible' => $this->replaceKey($this->signedBatch(1, 'nonce-mal-011', Carbon::now('UTC')->subSeconds(30)), 'signed_at', 'pas-une-date'),
        ];

        $expectedCodes = [
            'counter manquant' => 'INVALID_SYNC_COUNTER',
            'counter zéro' => 'INVALID_SYNC_COUNTER',
            'counter négatif' => 'INVALID_SYNC_COUNTER',
            'counter non entier (chaîne)' => 'INVALID_SYNC_COUNTER',
            'nonce manquant' => 'INVALID_SYNC_NONCE',
            'nonce vide' => 'INVALID_SYNC_NONCE',
            'nonce trop long (65)' => 'INVALID_SYNC_NONCE',
            'integrity manquant' => 'INVALID_SYNC_INTEGRITY',
            'integrity trop court' => 'INVALID_SYNC_INTEGRITY',
            'signed_at manquant' => 'INVALID_SYNC_SIGNED_AT',
            'signed_at illisible' => 'INVALID_SYNC_SIGNED_AT',
        ];

        foreach ($scenarios as $label => $deviceState) {
            $this->assertRejected($deviceState, 422, $expectedCodes[$label], $label);
        }
    }

    private function kioskWithAckedCounter(int $ackedEventCounter): AttendanceKiosk
    {
        // Modèle non persisté : la garde ne lit que `acked_event_counter`.
        $kiosk = new AttendanceKiosk();
        $kiosk->forceFill([
            'device_code' => AttendanceKiosk::hashDeviceCode(self::DEVICE_CODE),
            'acked_event_counter' => $ackedEventCounter,
        ]);

        return $kiosk;
    }

    /**
     * Construit une enveloppe `device_state` signée valide.
     *
     * @return array{counter: int, nonce: string, signed_at: string, integrity: string}
     */
    private function signedBatch(int $counter, string $nonce, Carbon $signedAt, ?string $deviceCode = null): array
    {
        $signedAtIso = $signedAt->toIso8601String();

        return [
            'counter' => $counter,
            'nonce' => $nonce,
            'signed_at' => $signedAtIso,
            'integrity' => hash_hmac(
                'sha256',
                implode("\n", [
                    strtoupper($deviceCode ?? self::DEVICE_CODE),
                    (string) $counter,
                    $nonce,
                    $signedAtIso,
                ]),
                self::SYNC_TOKEN,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $deviceState
     * @return array<string, mixed>
     */
    private function dropKey(array $deviceState, string $key): array
    {
        unset($deviceState[$key]);

        return $deviceState;
    }

    /**
     * @param  array<string, mixed>  $deviceState
     * @return array<string, mixed>
     */
    private function replaceKey(array $deviceState, string $key, mixed $value): array
    {
        $deviceState[$key] = $value;

        return $deviceState;
    }

    /**
     * @param  array<string, mixed>  $deviceState
     */
    private function assertRejected(array $deviceState, int $status, string $errorCode, ?string $message = null): void
    {
        try {
            $this->guard->validateBatch(
                $this->kioskWithAckedCounter(5),
                $deviceState,
                self::SYNC_TOKEN,
                self::DEVICE_CODE,
            );
            $this->fail(($message !== null ? $message.' : ' : '').'le batch aurait dû être rejeté ('.$errorCode.').');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
            $this->assertSame($errorCode, $exception->getMessage());
            $this->addToAssertionCount(1);
        }
    }
}
