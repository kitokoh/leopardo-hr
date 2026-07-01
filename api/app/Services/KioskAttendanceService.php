<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CheckInDTO;
use App\Models\AttendanceKiosk;
use App\Models\AttendanceLog;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class KioskAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly TenantManager $tenantManager,
    ) {}

    public function punch(AttendanceKiosk $kiosk, string $identifier, string $action = 'check_in'): AttendanceLog
    {
        /** @var AttendanceLog $result */
        $result = $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $identifier, $action): AttendanceLog {
            /** @var Employee|null $employee */
            $employee = Employee::query()
                ->where('company_id', $kiosk->company_id)
                ->where(function ($query) use ($identifier): void {
                    $query
                        ->where('email', $identifier)
                        ->orWhere('matricule', $identifier)
                        ->orWhere('zkteco_id', $identifier);
                })
                ->first();

            if (! $employee instanceof Employee) {
                throw (new ModelNotFoundException)->setModel(Employee::class);
            }

            if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                abort(403, 'BIOMETRIC_NOT_APPROVED');
            }

            $kiosk->forceFill(['last_seen_at' => now()])->save();

            $biometricMode = (string) $kiosk->biometric_mode;

            if ($action === 'check_out') {
                return $this->attendanceService->checkOut($employee, new CheckInDTO(method: 'kiosk_'.$biometricMode));
            }

            return $this->attendanceService->checkIn($employee, new CheckInDTO(method: 'kiosk_'.$biometricMode));
        });

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, int>
     */
    public function syncPunches(AttendanceKiosk $kiosk, array $events): array
    {
        /** @var array<int, int> $result */
        $result = $this->tenantManager->withinTenant($kiosk->company, function () use ($kiosk, $events): array {
            /** @var array<int, int> $processed */
            $processed = [];
            $biometricMode = (string) $kiosk->biometric_mode;
            $deviceCode = (string) $kiosk->device_code;

            foreach ($events as $event) {
                $identifier = trim((string) ($event['identifier'] ?? ''));
                if ($identifier === '') {
                    continue;
                }

                /** @var Employee|null $employee */
                $employee = Employee::query()
                    ->where('company_id', $kiosk->company_id)
                    ->where(function ($query) use ($identifier): void {
                        $query
                            ->where('email', $identifier)
                            ->orWhere('matricule', $identifier)
                            ->orWhere('zkteco_id', $identifier);
                    })
                    ->first();

                if (! $employee instanceof Employee) {
                    continue;
                }

                if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
                    continue;
                }

                $log = $this->attendanceService->importExternalPunch($employee, new CheckInDTO(
                    method: 'kiosk_offline',
                    occurred_at: isset($event['occurred_at']) ? (string) $event['occurred_at'] : null,
                    external_event_id: isset($event['external_event_id']) ? (string) $event['external_event_id'] : null,
                    biometric_type: isset($event['biometric_type']) ? (string) $event['biometric_type'] : $biometricMode,
                    synced_from_offline: true,
                    action: isset($event['action']) ? (string) $event['action'] : 'check_in',
                    source_device_code: $deviceCode
                ));

                $processed[] = (int) $log->id;
            }

            $kiosk->forceFill([
                'last_seen_at' => now(),
                'last_sync_at' => now(),
            ])->save();

            return $processed;
        });

        return $result;
    }
}
