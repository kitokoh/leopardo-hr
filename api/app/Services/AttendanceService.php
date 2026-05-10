<?php

namespace App\Services;

use App\DTOs\CheckInDTO;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\MissingCheckInException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function checkIn(Employee $employee, CheckInDTO|float|null $dto = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        $dto = $this->normalizeDto($dto, $gpsLng, $method);
        $company = app('current_company');

        $nowUtc = now('UTC');
        $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

        $open = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereNull('check_out')
            ->first();

        if ($open) {
            throw new AlreadyCheckedInException;
        }

        $schedule = $this->resolveSchedule($employee);

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
            'employee_id' => $employee->id,
            'schedule_id' => $schedule?->id,
            'date' => $today,
            'session_number' => 1,
            'check_in' => $nowUtc,
            'method' => $dto->method,
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
        $company = app('current_company');

        $nowUtc = now('UTC');
        $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereNull('check_out')
            ->first();

        if (! $log) {
            throw new MissingCheckInException;
        }

        $schedule = $log->schedule_id
            ? $log->schedule
            : $this->resolveSchedule($employee);

        $seconds = $log->check_in?->diffInSeconds($nowUtc) ?? 0;
        $breakMinutes = (int) ($schedule?->break_minutes ?? 0);
        $grossHours = $seconds / 3600;
        $hours = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);

        $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
        $overtime = max(0.0, round($hours - $threshold, 2));

        $log->check_out = $nowUtc;
        $log->hours_worked = $hours;
        $log->overtime_hours = $overtime;
        $log->gps_lat = $dto->gps_lat ?? $log->gps_lat;
        $log->gps_lng = $dto->gps_lng ?? $log->gps_lng;
        $log->method = $dto->method;

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
        $company = app('current_company');
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
                ->where('session_number', 1)
                ->whereNull('check_out')
                ->first();

            if (! $log) {
                throw new MissingCheckInException;
            }

            $schedule = $log->schedule_id
                ? $log->schedule
                : $this->resolveSchedule($employee);

            $seconds = $log->check_in?->diffInSeconds($occurredAt) ?? 0;
            $breakMinutes = (int) ($schedule?->break_minutes ?? 0);
            $grossHours = $seconds / 3600;
            $hours = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);
            $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
            $overtime = max(0.0, round($hours - $threshold, 2));

            $log->forceFill([
                'check_out' => $occurredAt,
                'hours_worked' => $hours,
                'overtime_hours' => $overtime,
                'method' => $dto->method,
                'source_device_code' => $dto->source_device_code ?? null,
                'external_event_id' => $externalEventId,
                'biometric_type' => $dto->biometric_type,
                'synced_from_offline' => $dto->synced_from_offline,
            ])->save();

            return $log;
        }

        $open = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereNull('check_out')
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
            'session_number' => 1,
            'check_in' => $occurredAt,
            'method' => $dto->method,
            'source_device_code' => $dto->source_device_code ?? null,
            'external_event_id' => $externalEventId,
            'biometric_type' => $dto->biometric_type,
            'synced_from_offline' => $dto->synced_from_offline,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ]);
    }

    public function recalculateLog(AttendanceLog $log): AttendanceLog
    {
        $company = app('current_company');
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
            $breakMinutes = (int) ($schedule?->break_minutes ?? 0);
            $grossHours = $seconds / 3600;
            $log->hours_worked = round(max(0.0, $grossHours - ($breakMinutes / 60)), 2);

            $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
            $log->overtime_hours = max(0.0, round(((float) $log->hours_worked) - $threshold, 2));
        }

        $log->save();

        return $log->fresh();
    }

    private function resolveSchedule(Employee $employee): ?Schedule
    {
        return $employee->schedule;
    }

    private function normalizeDto(CheckInDTO|float|null $dto, ?float $gpsLng, string $method): CheckInDTO
    {
        if ($dto instanceof CheckInDTO) {
            return $dto;
        }

        return new CheckInDTO(
            gps_lat: is_float($dto) ? $dto : null,
            gps_lng: $gpsLng,
            method: $method,
        );
    }
}
