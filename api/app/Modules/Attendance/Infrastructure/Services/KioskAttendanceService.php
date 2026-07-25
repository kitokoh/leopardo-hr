<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\TenantManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
                        ->orWhere('zkteco_id', $identifier);
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
                    method: 'kiosk_'.$kiosk->biometric_mode,
                    work_type: $workType,
                ));
            }

            return $this->attendanceService->checkIn($employee, new CheckInDTO(
                method: 'kiosk_'.$kiosk->biometric_mode,
                work_type: $workType,
            ));
        });
    }

    public function syncPunches(AttendanceKiosk $kiosk, array $events): array
    {
        return $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $events) {
            $processed = [];

            foreach ($events as $event) {
                $identifier = trim((string) ($event['identifier'] ?? ''));
                if ($identifier === '') {
                    continue;
                }

                $employee = Employee::query()
                    ->where('company_id', $kiosk->company_id)
                    ->where(function ($query) use ($identifier): void {
                        $query
                            ->where('email', $identifier)
                            ->orWhere('matricule', $identifier)
                            ->orWhere('zkteco_id', $identifier);
                    })
                    ->first();

                if (! $employee) {
                    continue;
                }

                if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                    continue;
                }

                $log = $this->attendanceService->importExternalPunch($employee, new CheckInDTO(
                    method: 'kiosk_offline',
                    occurred_at: $event['occurred_at'] ?? null,
                    external_event_id: $event['external_event_id'] ?? null,
                    biometric_type: $event['biometric_type'] ?? $kiosk->biometric_mode,
                    synced_from_offline: true,
                    action: $event['action'] ?? 'check_in',
                    source_device_code: $kiosk->device_code,
                    // PA2-ATT-010: offline-synced kiosk events also carry the
                    // multi-event work_type, matching mobile's offline sync.
                    work_type: $event['work_type'] ?? 'normal',
                ));

                $processed[] = $log->id;
            }

            $kiosk->forceFill([
                'last_seen_at' => now(),
                'last_sync_at' => now(),
            ])->save();

            return $processed;
        });
    }
}

