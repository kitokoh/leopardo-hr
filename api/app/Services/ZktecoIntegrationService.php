<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ZktecoDevice;
use App\Models\ZktecoSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZktecoIntegrationService
{
    public function registerDevice(string $companyId, array $data): ZktecoDevice
    {
        return ZktecoDevice::query()->create([
            'company_id' => $companyId,
            'serial_number' => $data['serial_number'],
            'name' => $data['name'],
            'ip_address' => $data['ip_address'] ?? null,
            'port' => $data['port'] ?? 4370,
            'protocol' => $data['protocol'] ?? 'tcp',
            'location_label' => $data['location_label'] ?? null,
            'model' => $data['model'] ?? null,
            'firmware_version' => $data['firmware_version'] ?? null,
            'employee_capacity' => $data['employee_capacity'] ?? 1000,
            'fingerprint_capacity' => $data['fingerprint_capacity'] ?? 3000,
            'face_capacity' => $data['face_capacity'] ?? 500,
            'capabilities' => $data['capabilities'] ?? null,
            'status' => 'offline',
        ]);
    }

    public function heartbeat(ZktecoDevice $device): ZktecoDevice
    {
        $device->update([
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]);

        return $device;
    }

    public function pullAttendance(ZktecoDevice $device, array $records): ZktecoSyncLog
    {
        $syncLog = ZktecoSyncLog::query()->create([
            'zkteco_device_id' => $device->id,
            'direction' => 'pull',
            'sync_type' => 'attendance',
            'records_count' => 0,
            'errors_count' => 0,
            'status' => 'started',
            'started_at' => now(),
        ]);

        $processed = 0;
        $errors = 0;

        foreach ($records as $record) {
            try {
                $employee = DB::table('employees')
                    ->where('company_id', $device->company_id)
                    ->where(function ($query) use ($record): void {
                        $query->where('zkteco_id', $record['user_id'] ?? null)
                            ->orWhere('matricule', $record['badge_number'] ?? null);
                    })
                    ->first();

                if (!$employee) {
                    $errors++;

                    continue;
                }

                $timestamp = $record['timestamp'] ?? now()->toDateTimeString();
                $date = substr($timestamp, 0, 10);
                $time = substr($timestamp, 11, 8);
                $action = $this->resolveAction($record['punch_type'] ?? 0);

                $existing = DB::table('attendance_logs')
                    ->where('employee_id', $employee->id)
                    ->where('date', $date)
                    ->first();

                if ($existing) {
                    if ($action === 'check_out' && empty($existing->check_out)) {
                        DB::table('attendance_logs')
                            ->where('id', $existing->id)
                            ->update([
                                'check_out' => $timestamp,
                                'method' => 'zkteco',
                                'updated_at' => now(),
                            ]);
                    }
                } else {
                    DB::table('attendance_logs')->insert([
                        'employee_id' => $employee->id,
                        'company_id' => $device->company_id,
                        'date' => $date,
                        'check_in' => $action === 'check_in' ? $timestamp : null,
                        'check_out' => $action === 'check_out' ? $timestamp : null,
                        'method' => 'zkteco',
                        'status' => 'present',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('ZKTeco attendance record failed', [
                    'device_id' => $device->id,
                    'record' => $record,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $syncLog->update([
            'records_count' => $processed,
            'errors_count' => $errors,
            'status' => $errors > 0 && $processed === 0 ? 'failed' : 'completed',
            'completed_at' => now(),
        ]);

        $device->update(['last_sync_at' => now()]);

        return $syncLog;
    }

    public function pushUsers(ZktecoDevice $device): ZktecoSyncLog
    {
        $syncLog = ZktecoSyncLog::query()->create([
            'zkteco_device_id' => $device->id,
            'direction' => 'push',
            'sync_type' => 'users',
            'status' => 'started',
            'started_at' => now(),
        ]);

        $employees = DB::table('employees')
            ->where('company_id', $device->company_id)
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name', 'matricule', 'zkteco_id')
            ->get();

        $syncLog->update([
            'records_count' => $employees->count(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $syncLog;
    }

    public function getSyncHistory(ZktecoDevice $device, int $limit = 20): \Illuminate\Support\Collection
    {
        return $device->syncLogs()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function resolveAction(int $punchType): string
    {
        return match ($punchType) {
            0, 4 => 'check_in',
            1, 5 => 'check_out',
            2 => 'break_out',
            3 => 'break_in',
            default => 'check_in',
        };
    }
}
