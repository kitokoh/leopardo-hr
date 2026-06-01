<?php

namespace App\Services;

use App\DTOs\CheckInDTO;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\MissingCheckInException;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;

class AttendanceService
{
    /** @var array<int, string> */
    private const NON_WORK_TYPES = ['break'];

    public function __construct(private readonly AttendanceGeofenceService $geofenceService) {}

    public function checkIn(Employee $employee, CheckInDTO|float|null $dto = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        $dto = $this->normalizeDto($dto, $gpsLng, $method);
        $company = currentCompany();

        $nowUtc = now('UTC');
        $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

        $open = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNull('check_out')
            ->orderByDesc('session_number')
            ->first();

        if ($open) {
            throw new AlreadyCheckedInException;
        }

        $sessionNumber = $this->nextSessionNumber($employee, $today);

        $schedule = $this->resolveSchedule($employee);
        $punchMeta = $this->buildPunchMeta($company, $employee, $dto, 'check_in');

        $status = 'incomplete';
        $lateMinutes = 0;

        if ($schedule) {
            $checkInLocal = $nowUtc->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $tolerance = (int) $schedule->late_tolerance_minutes;
            $lateMinutes = max(0, (int) floor($diffMinutes - $tolerance));
            $status = $lateMinutes > 0 ? 'late' : 'ontime';
        }

        $log = AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule?->id,
            'date' => $today,
            'session_number' => $sessionNumber,
            'check_in' => $nowUtc,
            'method' => $dto->method,
            'work_type' => $dto->work_type,
            'punch_note' => $dto->punch_note,
            'punch_meta' => $punchMeta,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'gps_lat' => $dto->gps_lat,
            'gps_lng' => $dto->gps_lng,
        ]);

        AttendanceCheckedIn::dispatch($log);

        return $log;
    }

    public function checkOut(Employee $employee, CheckInDTO|float|null $dto = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        $dto = $this->normalizeDto($dto, $gpsLng, $method);
        $company = currentCompany();

        $nowUtc = now('UTC');
        $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNull('check_out')
            ->orderByDesc('session_number')
            ->first();

        if (! $log) {
            throw new MissingCheckInException;
        }

        $schedule = $log->schedule_id
            ? $log->schedule
            : $this->resolveSchedule($employee);
        $punchMeta = $this->buildPunchMeta($company, $employee, $dto, 'check_out');

        $seconds = $log->check_in?->diffInSeconds($nowUtc) ?? 0;
        $breakMinutes = $this->breakMinutesForLog($log, $dto, $schedule);
        $grossHours = $seconds / 3600;
        $hours = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);

        $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
        $overtime = $log->work_type === 'overtime'
            ? $hours
            : max(0.0, round($hours - $threshold, 2));

        if (in_array($log->work_type, self::NON_WORK_TYPES, true)) {
            $hours = 0.0;
            $overtime = 0.0;
        }

        $log->check_out = $nowUtc;
        $log->hours_worked = $hours;
        $log->overtime_hours = $overtime;
        $log->gps_lat = $dto->gps_lat ?? $log->gps_lat;
        $log->gps_lng = $dto->gps_lng ?? $log->gps_lng;
        $log->method = $dto->method;
        $log->punch_note = $dto->punch_note ?? $log->punch_note;
        if ($dto->work_type !== 'normal') {
            $log->punch_meta = array_merge($log->punch_meta ?? [], [
                'closed_with' => $dto->work_type,
            ]);
        }
        $log->punch_meta = array_merge($log->punch_meta ?? [], $punchMeta);

        if ($log->status === 'incomplete' && $schedule) {
            $checkInLocal = $log->check_in->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $tolerance = (int) $schedule->late_tolerance_minutes;
            $log->late_minutes = max(0, (int) floor($diffMinutes - $tolerance));
            $log->status = $log->late_minutes > 0 ? 'late' : 'ontime';
        }

        $log->save();

        AttendanceCheckedOut::dispatch($log);

        return $log;
    }

    public function importExternalPunch(
        Employee $employee,
        CheckInDTO $dto,
    ): AttendanceLog {
        $company = currentCompany();
        $occurredAt = Carbon::parse($dto->occurred_at ?? now('UTC'))->utc();
        $today = $occurredAt->copy()->setTimezone($company->timezone)->toDateString();
        $action = $dto->action ?? 'check_in';
        $externalEventId = $dto->external_event_id;

        if ($externalEventId) {
            $existing = AttendanceLog::query()->where('external_event_id', $externalEventId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($action === 'check_out') {
            $log = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->whereNull('check_out')
                ->orderByDesc('session_number')
                ->first();

            if (! $log) {
                throw new MissingCheckInException;
            }

            $schedule = $log->schedule_id
                ? $log->schedule
                : $this->resolveSchedule($employee);

            $seconds = $log->check_in?->diffInSeconds($occurredAt) ?? 0;
            $breakMinutes = $this->breakMinutesForLog($log, $dto, $schedule);
            $grossHours = $seconds / 3600;
            $hours = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);
            $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
            $overtime = $log->work_type === 'overtime'
                ? $hours
                : max(0.0, round($hours - $threshold, 2));

            if (in_array($log->work_type, self::NON_WORK_TYPES, true)) {
                $hours = 0.0;
                $overtime = 0.0;
            }

            $log->forceFill([
                'check_out' => $occurredAt,
                'hours_worked' => $hours,
                'overtime_hours' => $overtime,
                'method' => $dto->method,
                'source_device_code' => $dto->source_device_code ?? null,
                'external_event_id' => $externalEventId,
                'biometric_type' => $dto->biometric_type,
                'synced_from_offline' => $dto->synced_from_offline,
                'punch_note' => $dto->punch_note ?? $log->punch_note,
                'punch_meta' => array_merge($log->punch_meta ?? [], [
                    'closed_with' => $dto->work_type,
                ], $this->buildPunchMeta($company, $employee, $dto, 'external_check_out')),
            ])->save();

            return $log;
        }

        $open = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNull('check_out')
            ->orderByDesc('session_number')
            ->first();

        if ($open) {
            return $open;
        }

        $schedule = $this->resolveSchedule($employee);
        $status = 'incomplete';
        $lateMinutes = 0;

        if ($schedule) {
            $checkInLocal = $occurredAt->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $tolerance = (int) $schedule->late_tolerance_minutes;
            $lateMinutes = max(0, (int) floor($diffMinutes - $tolerance));
            $status = $lateMinutes > 0 ? 'late' : 'ontime';
        }

        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule?->id,
            'date' => $today,
            'session_number' => $this->nextSessionNumber($employee, $today),
            'check_in' => $occurredAt,
            'method' => $dto->method,
            'work_type' => $dto->work_type,
            'punch_note' => $dto->punch_note,
            'source_device_code' => $dto->source_device_code ?? null,
            'external_event_id' => $externalEventId,
            'biometric_type' => $dto->biometric_type,
            'synced_from_offline' => $dto->synced_from_offline,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'gps_lat' => $dto->gps_lat,
            'gps_lng' => $dto->gps_lng,
            'punch_meta' => $this->buildPunchMeta($company, $employee, $dto, 'external_check_in'),
        ]);
    }

    public function recalculateLog(AttendanceLog $log): AttendanceLog
    {
        $company = currentCompany();
        $schedule = $log->schedule_id ? $log->schedule : $log->employee?->schedule;

        if ($log->schedule_id === null && $schedule) {
            $log->schedule_id = $schedule->id;
        }

        $today = $log->date?->format('Y-m-d')
            ?? $log->check_in?->copy()->setTimezone($company->timezone)->toDateString();

        $log->hours_worked = null;
        $log->overtime_hours = 0;
        $log->late_minutes = 0;
        $log->status = 'incomplete';

        if ($log->check_in && $schedule && $today) {
            $checkInLocal = $log->check_in->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $tolerance = (int) $schedule->late_tolerance_minutes;
            $log->late_minutes = max(0, (int) floor($diffMinutes - $tolerance));
            $log->status = $log->late_minutes > 0 ? 'late' : 'ontime';
        }

        if ($log->check_in && $log->check_out) {
            $seconds = $log->check_in->diffInSeconds($log->check_out);
            $breakMinutes = $this->breakMinutesForLog($log, null, $schedule);
            $grossHours = $seconds / 3600;
            $log->hours_worked = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);

            $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
            $log->overtime_hours = $log->work_type === 'overtime'
                ? (float) $log->hours_worked
                : max(0.0, round(((float) $log->hours_worked) - $threshold, 2));

            if (in_array($log->work_type, self::NON_WORK_TYPES, true)) {
                $log->hours_worked = 0;
                $log->overtime_hours = 0;
            }
        }

        $log->save();

        return $log->fresh();
    }

    private function resolveSchedule(Employee $employee): ?Schedule
    {
        return $employee->schedule;
    }

    private function nextSessionNumber(Employee $employee, string $date): int
    {
        $lastSession = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->max('session_number');

        return ((int) $lastSession) + 1;
    }

    private function breakMinutesForLog(AttendanceLog $log, ?CheckInDTO $dto, ?Schedule $schedule): int
    {
        if ($log->session_number > 1 || $log->work_type !== 'normal') {
            return 0;
        }

        if ($dto?->work_type === 'break') {
            return 0;
        }

        return (int) ($schedule?->break_minutes ?? 0);
    }

    private function normalizeDto(CheckInDTO|float|null $dto, ?float $gpsLng, string $method): CheckInDTO
    {
        if ($dto instanceof CheckInDTO) {
            return $dto;
        }

        return new CheckInDTO(
            gps_lat: is_float($dto) ? $dto : null,
            gps_lng: $gpsLng,
            gps_accuracy: null,
            method: $method,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPunchMeta(Company $company, Employee $employee, CheckInDTO $dto, string $phase): array
    {
        $timezone = $dto->device_timezone ?: $company->timezone;
        $geofence = $this->geofenceService->evaluate($company, $employee, $dto->gps_lat, $dto->gps_lng);

        return [
            'phase' => $phase,
            'device_timezone' => $timezone,
            'server_timezone' => $company->timezone,
            'gps_accuracy' => $dto->gps_accuracy,
            'geofence' => $geofence,
        ];
    }
}
