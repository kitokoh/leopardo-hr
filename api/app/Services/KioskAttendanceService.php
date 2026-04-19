<?php

namespace App\Services;

use App\Models\AttendanceKiosk;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class KioskAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {
    }

    public function punch(AttendanceKiosk $kiosk, string $identifier, string $action = 'check_in'): AttendanceLog
    {
        $searchPath = $kiosk->company?->tenancy_type === 'schema'
            ? $kiosk->company->schema_name.',public'
            : 'shared_tenants,public';
        DB::statement('SET search_path TO '.$searchPath);

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
            throw (new ModelNotFoundException())->setModel(Employee::class);
        }

        if (! $employee->biometric_fingerprint_enabled && ! $employee->biometric_face_enabled) {
            abort(403, 'BIOMETRIC_NOT_APPROVED');
        }

        $kiosk->forceFill(['last_seen_at' => now()])->save();

        if ($action === 'check_out') {
            return $this->attendanceService->checkOut($employee, null, null, 'kiosk_'.$kiosk->biometric_mode);
        }

        return $this->attendanceService->checkIn($employee, null, null, 'kiosk_'.$kiosk->biometric_mode);
    }

    public function syncPunches(AttendanceKiosk $kiosk, array $events): array
    {
        $searchPath = $kiosk->company?->tenancy_type === 'schema'
            ? $kiosk->company->schema_name.',public'
            : 'shared_tenants,public';
        DB::statement('SET search_path TO '.$searchPath);

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

            $log = $this->attendanceService->importExternalPunch($employee, [
                'action' => $event['action'] ?? 'check_in',
                'occurred_at' => $event['occurred_at'] ?? null,
                'external_event_id' => $event['external_event_id'] ?? null,
                'source_device_code' => $kiosk->device_code,
                'method' => 'kiosk_offline',
                'biometric_type' => $event['biometric_type'] ?? $kiosk->biometric_mode,
                'synced_from_offline' => true,
            ]);

            $processed[] = $log->id;
        }

        $kiosk->forceFill([
            'last_seen_at' => now(),
            'last_sync_at' => now(),
        ])->save();

        return $processed;
    }
}
