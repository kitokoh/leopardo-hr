<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Exceptions\MissingCheckInException;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Domain\Models\BiometricAuditLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class KioskAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly TenantManager $tenantManager,
    ) {}

    public function punch(
        AttendanceKiosk $kiosk,
        string $identifier,
        string $action = 'check_in',
        string $workType = 'normal',
        ?string $method = null,
        ?int $managerEmployeeId = null,
    ): AttendanceLog {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier, $action, $workType, $method, $managerEmployeeId): AttendanceLog {
            $employee = Employee::query()
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

            if (! $employee) {
                throw (new ModelNotFoundException)->setModel(Employee::class);
            }

            if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                abort(403, 'BIOMETRIC_NOT_APPROVED');
            }

            // BIO-006 (#6767) : la méthode réellement utilisée est vérifiée
            // côté serveur — une méthode désactivée est refusée même si
            // l'interface l'envoie. `biometric` reste la valeur legacy quand
            // le kiosque n'indique pas de méthode précise.
            $resolvedMethod = $method ?: 'biometric';
            if ($resolvedMethod !== 'biometric') {
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
                    // Persist the canonical method accepted by attendance_logs;
                    // biometric_mode remains available as biometric_type/metadata.
                    method: $persistedMethod,
                    work_type: $workType,
                    biometric_type: $resolvedMethod === 'biometric' ? $kiosk->biometric_mode : $persistedMethod,
                ))
                : $this->attendanceService->checkIn($employee, new CheckInDTO(
                    method: $persistedMethod,
                    work_type: $workType,
                    biometric_type: $resolvedMethod === 'biometric' ? $kiosk->biometric_mode : $persistedMethod,
                ));

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
            $this->assertManagerValidation($kiosk, $managerEmployeeId);
        }
    }

    /**
     * Validation manager (BIO-006) : le manager doit exister, être actif,
     * appartenir au même tenant et porter un rôle de manager.
     */
    private function assertManagerValidation(AttendanceKiosk $kiosk, ?int $managerEmployeeId): void
    {
        if ($managerEmployeeId === null) {
            abort(422, 'MANAGER_VALIDATION_REQUIRED');
        }

        $manager = Employee::query()
            ->where('company_id', $kiosk->company_id)
            ->whereKey($managerEmployeeId)
            ->where('status', 'active')
            ->first();

        if (! $manager || ! $manager->isManager()) {
            abort(403, 'MANAGER_VALIDATION_REQUIRED');
        }
    }

    /**
     * Synchronise un batch d'événements offline kiosk.
     *
     * Issue #3587 — les événements non importables n'étaient skippés
     * silencieusement (continue sans log) alors que le bridge marquait TOUT
     * le batch comme synchronisé → pointages définitivement perdus sans
     * alerte (erreurs de paie invisibles). Désormais chaque événement refusé
     * est retourné dans `skipped` (avec raison) ET journalisé, pour que le
     * bridge l'isole en dead-letter au lieu de le marquer synced.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array{processed: array<int, int>, skipped: array<int, array{external_event_id: string|null, identifier: string, reason: string}>}
     */
    public function syncPunches(AttendanceKiosk $kiosk, array $events, string $deviceCode): array
    {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $events, $deviceCode) {
            $processed = [];
            $skipped = [];

            foreach ($events as $event) {
                $identifier = trim((string) ($event['identifier'] ?? ''));
                $externalEventId = isset($event['external_event_id']) ? (string) $event['external_event_id'] : null;

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

                $employee = Employee::query()
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

                if (! $employee) {
                    $skip('EMPLOYEE_NOT_FOUND');

                    continue;
                }

                if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                    $skip('BIOMETRIC_NOT_APPROVED');

                    continue;
                }

                try {
                    $log = $this->attendanceService->importExternalPunch($employee, new CheckInDTO(
                        method: 'biometric',
                        occurred_at: $event['occurred_at'] ?? null,
                        external_event_id: $event['external_event_id'] ?? null,
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
            ])->save();

            return [
                'processed' => $processed,
                'skipped' => $skipped,
            ];
        });
    }
}
