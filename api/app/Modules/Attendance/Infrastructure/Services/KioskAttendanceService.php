<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Exceptions\MissingCheckInException;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Domain\Models\BiometricAuditLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class KioskAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly TenantManager $tenantManager,
        private readonly KioskManagerGuard $managerGuard,
        private readonly KioskOfflineSyncGuard $offlineSyncGuard,
    ) {}

    /**
     * Pointage kiosque (ATT-004 #6769 / BIO-006 #6767 / BIO-007 #6772).
     *
     * Règles serveur :
     *   - employé résolu dans le tenant du kiosque (email/matricule/zkteco_id/
     *     badge_number) ;
     *   - `biometric` (hérité) exige un flag biométrique employé ;
     *   - une méthode explicite doit être activée dans la matrice (BIO-006) ;
     *     badge/PIN/carte/manager n'exigent PAS de flag biométrique ;
     *   - `manager` exige un manager actif du même tenant ;
     *   - `device_event_id` rend le pointage idempotent (rejeu → même log).
     */
    public function punch(
        AttendanceKiosk $kiosk,
        string $identifier,
        string $action = 'check_in',
        string $workType = 'normal',
        ?string $method = null,
        ?int $managerEmployeeId = null,
        ?string $deviceEventId = null,
    ): AttendanceLog {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier, $action, $workType, $method, $managerEmployeeId, $deviceEventId): AttendanceLog {
            // BIO-007 (#6772) : rejeu d'un événement appareil déjà traité →
            // retour du log existant (aucune présence dupliquée).
            if ($deviceEventId !== null && $deviceEventId !== '') {
                $existing = AttendanceLog::query()
                    ->where('external_event_id', $deviceEventId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $employee = $this->resolveEmployee($kiosk, $identifier);

            if (! $employee) {
                throw (new ModelNotFoundException)->setModel(Employee::class);
            }

            // BIO-006 (#6767) : la méthode réellement utilisée est vérifiée
            // côté serveur — une méthode désactivée est refusée même si
            // l'interface l'envoie. `biometric` reste la valeur legacy quand
            // le kiosque n'indique pas de méthode précise.
            $resolvedMethod = $method ?: 'biometric';

            if ($resolvedMethod === 'biometric') {
                if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                    abort(403, 'BIOMETRIC_NOT_APPROVED');
                }
            } else {
                $this->assertKioskMethodAllowed($kiosk, $employee, $resolvedMethod, $managerEmployeeId);
            }

            // Mapping domaine → persistance (ATT-002) : `badge` est stocké
            // `card` (valeur historique du schéma attendance_logs.method).
            $verificationMethod = $resolvedMethod === 'biometric'
                ? null
                : (VerificationMethod::tryFrom($resolvedMethod) ?? VerificationMethod::fromAttendanceLogMethod($resolvedMethod));
            $persistedMethod = $verificationMethod !== null
                ? $verificationMethod->attendanceLogMethod()
                : $resolvedMethod;

            $kiosk->forceFill(['last_seen_at' => now()])->save();

            // PA2-ATT-010: kiosk punches feed the same multi-event work_type
            // model as mobile (normal/overtime/break/resume/mission/travel/
            // training/other) instead of being locked to plain check_in/out.
            $log = $action === 'check_out'
                ? $this->attendanceService->checkOut($employee, new CheckInDTO(
                    method: $persistedMethod,
                    work_type: $workType,
                    biometric_type: $resolvedMethod === 'biometric' ? $kiosk->biometric_mode : $persistedMethod,
                ))
                : $this->attendanceService->checkIn($employee, new CheckInDTO(
                    method: $persistedMethod,
                    work_type: $workType,
                    biometric_type: $resolvedMethod === 'biometric' ? $kiosk->biometric_mode : $persistedMethod,
                ));

            // BIO-007 (#6772) : persistance de l'identifiant d'événement
            // appareil pour les rejeux ultérieurs (contrainte unique
            // external_event_id). Course éventuelle → 23505 → retour du log
            // concurrent (idempotence, pas de 500).
            if ($deviceEventId !== null && $deviceEventId !== '' && $log->external_event_id === null) {
                try {
                    $log->forceFill(['external_event_id' => $deviceEventId])->save();
                } catch (QueryException $exception) {
                    if (str_contains($exception->getMessage(), '23505')) {
                        $concurrent = AttendanceLog::query()
                            ->where('external_event_id', $deviceEventId)
                            ->first();

                        if ($concurrent !== null) {
                            return $concurrent;
                        }
                    }

                    throw $exception;
                }
            }

            // BIO-008 (#6773) : audit dans le contexte tenant (le search_path
            // est déjà positionné ici — un insert hors contexte échouerait sur
            // le mauvais schéma et empoisonnerait la transaction).
            BiometricAuditLog::query()->create([
                'company_id' => $kiosk->company_id,
                'employee_id' => (int) $log->employee_id,
                'kiosk_id' => (int) $kiosk->id,
                'site_id' => $kiosk->site_id,
                'event' => 'kiosk.punch.recorded',
                'method' => (string) $log->method,
                'correlation_id' => (string) $log->external_event_id,
                'device_code_hash' => (string) $kiosk->device_code,
            ]);

            return $log;
        });
    }

    /**
     * BIO-006 (#6767) — enforcement serveur de la matrice de méthodes.
     *
     * Règles :
     *  1. la méthode doit être activée sur le kiosque (kiosque → entreprise
     *     → toutes) ;
     *  2. une méthode biométrique exige l'enrôlement/flags de l'employé ;
     *  3. `manager` exige un manager actif du même tenant (validation
     *     manager des cas exceptionnels).
     */
    private function assertKioskMethodAllowed(AttendanceKiosk $kiosk, Employee $employee, string $method, ?int $managerEmployeeId): void
    {
        $verificationMethod = VerificationMethod::fromAttendanceLogMethod($method)
            ?? VerificationMethod::tryFrom($method);

        // Méthode inconnue → rejetée (ATT-002).
        if ($verificationMethod === null) {
            abort(422, 'PUNCH_METHOD_NOT_CONFIGURED');
        }

        if (! $kiosk->isPunchMethodAllowed($verificationMethod->value)) {
            abort(422, 'PUNCH_METHOD_NOT_CONFIGURED');
        }

        if ($verificationMethod->isBiometric()) {
            $enabled = $verificationMethod === VerificationMethod::Fingerprint
                ? (bool) $employee->biometric_fingerprint_enabled
                : (bool) $employee->biometric_face_enabled;

            if (! $enabled) {
                abort(403, 'BIOMETRIC_NOT_ENABLED');
            }
        }

        if ($verificationMethod === VerificationMethod::Manager) {
            $this->managerGuard->assertManager($kiosk, $managerEmployeeId);
        }
    }

    /**
     * Synchronise un batch d'événements offline kiosque (BIO-007 #6772).
     *
     * #3587 — les événements non importables étaient skippés silencieusement
     * alors que le bridge marquait TOUT le batch comme synchronisé → pointages
     * définitivement perdus. Désormais chaque événement refusé est retourné
     * dans `skipped` (avec raison) ET journalisé.
     *
     * BIO-007 (additif, rétro-compatible) :
     *   - enveloppe `device_state` signée (HMAC) validée par
     *     KioskOfflineSyncGuard : falsification → 422, rejeu → 409 ;
     *   - événements porteurs de `device_event_id` (réconciliation unique) ;
     *   - méthode réellement utilisée préservée (fidélité BIO-006) ;
     *   - fenêtre offline bornée (`max_age_days`) appliquée aux batches
     *     signés — un événement expiré est isolé (EVENT_EXPIRED) ;
     *   - compteur monotone acquitté persisté sur le kiosque.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>|null  $deviceState
     * @return array{processed: array<int, int>, skipped: array<int, array{external_event_id: string|null, identifier: string, reason: string}>}
     */
    public function syncPunches(
        AttendanceKiosk $kiosk,
        array $events,
        string $deviceCode,
        ?array $deviceState = null,
        string $plainSyncToken = '',
    ): array {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $events, $deviceCode, $deviceState, $plainSyncToken): array {
            $validatedCounter = $this->offlineSyncGuard->validateBatch($kiosk, $deviceState, $plainSyncToken, $deviceCode);
            $expiryCutoff = $validatedCounter !== null
                ? Carbon::now('UTC')->subDays((int) config('attendance.kiosk.offline.max_age_days', 14))
                : null;

            $processed = [];
            $skipped = [];

            foreach ($events as $event) {
                $identifier = trim((string) ($event['identifier'] ?? ''));
                $externalEventId = $this->eventExternalId($event);

                $skip = static function (string $reason) use (&$skipped, $externalEventId, $identifier, $kiosk, $deviceCode): void {
                    $skipped[] = [
                        'external_event_id' => $externalEventId,
                        'identifier' => $identifier,
                        'reason' => $reason,
                    ];
                    Log::warning('kiosk.sync_event_skipped', [
                        'device_code' => $deviceCode,
                        'company_id' => $kiosk->company_id,
                        'external_event_id' => $externalEventId,
                        'identifier' => $identifier,
                        'reason' => $reason,
                    ]);
                };

                if ($identifier === '') {
                    $skip('IDENTIFIER_REQUIRED');

                    continue;
                }

                // BIO-007 : fenêtre offline bornée (batches signés uniquement).
                if ($expiryCutoff !== null && isset($event['occurred_at']) && is_string($event['occurred_at']) && $event['occurred_at'] !== '') {
                    $occurredAt = Carbon::parse($event['occurred_at'])->utc();
                    if ($occurredAt->lessThan($expiryCutoff)) {
                        $skip('EVENT_EXPIRED');

                        continue;
                    }
                }

                $employee = $this->resolveEmployee($kiosk, $identifier);

                if (! $employee) {
                    $skip('EMPLOYEE_NOT_FOUND');

                    continue;
                }

                // BIO-007 : méthode réellement utilisée (badge → card en
                // persistance). `biometric` (hérité) exige un flag employé ;
                // badge/PIN/carte/manager n'exigent pas de flag biométrique.
                $eventMethod = isset($event['method']) && is_string($event['method']) ? $event['method'] : 'biometric';
                $isLegacyBiometric = $eventMethod === 'biometric';

                if (! $isLegacyBiometric) {
                    $verificationMethod = VerificationMethod::tryFrom($eventMethod)
                        ?? VerificationMethod::fromAttendanceLogMethod($eventMethod);

                    if ($verificationMethod === null) {
                        $skip('PUNCH_METHOD_NOT_CONFIGURED');

                        continue;
                    }

                    $eventMethod = $verificationMethod->attendanceLogMethod();
                }

                if ($isLegacyBiometric && ! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                    $skip('BIOMETRIC_NOT_APPROVED');

                    continue;
                }

                try {
                    $log = $this->attendanceService->importExternalPunch($employee, new CheckInDTO(
                        method: $isLegacyBiometric ? 'biometric' : $eventMethod,
                        occurred_at: $event['occurred_at'] ?? null,
                        external_event_id: $externalEventId,
                        biometric_type: $event['biometric_type'] ?? $kiosk->biometric_mode,
                        synced_from_offline: true,
                        action: $event['action'] ?? 'check_in',
                        // #5588 (follow-up) : device_code présenté par la borne
                        // (en clair dans l'URL) — jamais la dérivation stockée
                        // (64 hex, colonne source_device_code limitée à 40).
                        source_device_code: $deviceCode,
                        // PA2-ATT-010: offline-synced kiosk events also carry the
                        // multi-event work_type, matching mobile's offline sync.
                        work_type: $event['work_type'] ?? 'normal',
                    ));
                } catch (MissingCheckInException) {
                    // Rejet métier borné à CET événement (#3588) : un check_out
                    // sans session ouverte ne doit plus faire échouer le batch.
                    $skip('NO_OPEN_SESSION');

                    continue;
                }

                $processed[] = $log->id;
            }

            $kiosk->forceFill([
                'last_seen_at' => now(),
                'last_sync_at' => now(),
                'acked_event_counter' => $validatedCounter ?? (int) $kiosk->acked_event_counter,
            ])->save();

            return [
                'processed' => $processed,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Identifiant de réconciliation d'un événement offline : `device_event_id`
     * (BIO-007) puis `external_event_id` (hérité).
     */
    /** @param array<string, mixed> $event */
    private function eventExternalId(array $event): ?string
    {
        $deviceEventId = $event['device_event_id'] ?? null;
        if (is_string($deviceEventId) && $deviceEventId !== '') {
            return $deviceEventId;
        }

        $externalEventId = $event['external_event_id'] ?? null;

        return is_string($externalEventId) && $externalEventId !== ''
            ? $externalEventId
            : null;
    }

    private function resolveEmployee(AttendanceKiosk $kiosk, string $identifier): ?Employee
    {
        return Employee::query()
            ->where('company_id', $kiosk->company_id)
            ->where(function ($query) use ($identifier): void {
                $query
                    ->where('email', $identifier)
                    ->orWhere('matricule', $identifier)
                    ->orWhere('zkteco_id', $identifier)
                    // #5122 — badge/carte de pointage
                    ->orWhere('badge_number', $identifier);
            })
            ->first();
    }
}
