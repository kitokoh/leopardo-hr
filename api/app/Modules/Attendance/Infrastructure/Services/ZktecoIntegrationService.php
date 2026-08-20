<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use App\Modules\Attendance\Domain\Models\ZktecoSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            // #5120 — méthodes de pointage configurables (null = toutes)
            'punch_methods' => isset($data['punch_methods']) && is_array($data['punch_methods']) && count($data['punch_methods']) > 0
                ? array_values($data['punch_methods'])
                : null,
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
                // #5121 — méthode de pointage (absent → fingerprint, rétro-compat)
                $method = isset($record['method']) && $record['method'] !== ''
                    ? (string) $record['method']
                    : ZktecoDevice::PUNCH_METHOD_FINGERPRINT;

                // #5121 — vérification méthode autorisée par le device
                if (! $device->isPunchMethodAllowed($method)) {
                    $errors++;
                    Log::channel('audit')->warning('zkteco.sync.method_not_allowed', [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'company_id' => $device->company_id,
                        'method' => $method,
                        'user_id' => $record['user_id'] ?? null,
                        'error_code' => 'PUNCH_METHOD_NOT_ALLOWED',
                    ]);

                    continue;
                }

                // #5122 — lookup étendu : zkteco_id, matricule, badge_number
                $employee = DB::table('employees')
                    ->where('company_id', $device->company_id)
                    ->where(function ($query) use ($record): void {
                        $query->where('zkteco_id', $record['user_id'] ?? null)
                            ->orWhere('matricule', $record['badge_number'] ?? null)
                            ->orWhere('badge_number', $record['badge_number'] ?? null);
                    })
                    ->first();

                if (! $employee) {
                    $errors++;

                    continue;
                }

                // #5121 — vérification enrôlement employé pour la méthode
                if (! $this->isEmployeeEnrolledForMethod($employee, $method)) {
                    $errors++;
                    Log::channel('audit')->warning('zkteco.sync.employee_method_not_enrolled', [
                        'device_id' => $device->id,
                        'serial_number' => $device->serial_number,
                        'company_id' => $device->company_id,
                        'method' => $method,
                        'employee_id' => $employee->id,
                        'error_code' => 'EMPLOYEE_METHOD_NOT_ENROLLED',
                    ]);

                    continue;
                }

                $timestamp = $record['timestamp'] ?? now()->toDateTimeString();
                $date = substr($timestamp, 0, 10);
                $time = substr($timestamp, 11, 8);
                $action = $this->resolveAction($record['punch_type'] ?? 0);

                // #2330 : `attendance_logs.status` est un enum PostgreSQL
                // (ontime/late/absent/leave/holiday/incomplete) — 'present'
                // levait 22P02 et faisait échouer CHAQUE punch (jamais
                // persisté). Statut calculé comme AttendanceService::checkIn :
                // retard vs horaire planifié (start_time − tolérance).
                $status = 'ontime';
                $lateMinutes = 0;
                if ($action === 'check_in' && isset($employee->schedule_id)) {
                    $schedule = DB::table('schedules')
                        ->where('id', $employee->schedule_id)
                        ->where('company_id', $device->company_id)
                        ->first();
                    if ($schedule !== null && ! empty($schedule->start_time)) {
                        $checkIn = Carbon::parse($timestamp);
                        $start = Carbon::parse($date.' '.$schedule->start_time);
                        $diffMinutes = $start->diffInMinutes($checkIn, false);
                        $tolerance = (int) ($schedule->late_tolerance_minutes ?? 0);
                        $lateMinutes = max(0, (int) floor($diffMinutes - $tolerance));
                        $status = $lateMinutes > 0 ? 'late' : 'ontime';
                    }
                }

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
                        'status' => $status,
                        'late_minutes' => $lateMinutes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $processed++;
            } catch (Throwable $e) {
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

    public function getSyncHistory(ZktecoDevice $device, int $limit = 20): Collection
    {
        return $device->syncLogs()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Vérifie si l'employé est enrôlé pour la méthode demandée (#5121).
     *
     * Mapping :
     *  - fingerprint → biometric_fingerprint_enabled
     *  - face        → biometric_face_enabled
     *  - card        → badge_number non vide
     *
     * @param  object  $employee  Résultat DB::table (stdClass ou Employee)
     */
    private function isEmployeeEnrolledForMethod(object $employee, string $method): bool
    {
        return match ($method) {
            ZktecoDevice::PUNCH_METHOD_FINGERPRINT => (bool) ($employee->biometric_fingerprint_enabled ?? false),
            ZktecoDevice::PUNCH_METHOD_FACE => (bool) ($employee->biometric_face_enabled ?? false),
            ZktecoDevice::PUNCH_METHOD_CARD => ! empty($employee->badge_number),
            default => false,
        };
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

