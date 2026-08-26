<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use App\Exceptions\MissingCheckInException;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class KioskAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly TenantManager $tenantManager,
    ) {}

    public function punch(AttendanceKiosk $kiosk, string $identifier, string $action = 'check_in', string $workType = 'normal'): AttendanceLog
    {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier, $action, $workType) {
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

            $kiosk->forceFill(['last_seen_at' => now()])->save();

            // PA2-ATT-010: kiosk punches feed the same multi-event work_type
            // model as mobile (normal/overtime/break/resume/mission/travel/
            // training/other) instead of being locked to plain check_in/out.
            if ($action === 'check_out') {
                return $this->attendanceService->checkOut($employee, new CheckInDTO(
                    // Persist the canonical method accepted by attendance_logs;
                    // biometric_mode remains available as biometric_type/metadata.
                    method: 'biometric',
                    work_type: $workType,
                ));
            }

            return $this->attendanceService->checkIn($employee, new CheckInDTO(
                // Persist the canonical method accepted by attendance_logs;
                // biometric_mode remains available as biometric_type/metadata.
                method: 'biometric',
                work_type: $workType,
            ));
        });
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
