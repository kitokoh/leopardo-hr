<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function checkIn(Employee $employee, ?float $gpsLat = null, ?float $gpsLng = null): AttendanceLog
    {
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
            abort(409, 'ALREADY_CHECKED_IN');
        }

        $schedule = $this->resolveSchedule($employee);

        $status = 'incomplete';
        $lateMinutes = 0;

        if ($schedule) {
            $checkInLocal = $nowUtc->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $lateMinutes = max(0, $diffMinutes);
            $status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
        }

        return AttendanceLog::query()->create([
            'employee_id' => $employee->id,
            'schedule_id' => $schedule?->id,
            'date' => $today,
            'session_number' => 1,
            'check_in' => $nowUtc,
            'method' => 'mobile',
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'gps_lat' => $gpsLat,
            'gps_lng' => $gpsLng,
        ]);
    }

    public function checkOut(Employee $employee, ?float $gpsLat = null, ?float $gpsLng = null): AttendanceLog
    {
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
            abort(409, 'MISSING_CHECK_IN');
        }

        $schedule = $log->schedule_id
            ? Schedule::query()->find($log->schedule_id)
            : $this->resolveSchedule($employee);

        $seconds = $log->check_in?->diffInSeconds($nowUtc) ?? 0;
        $hours = round($seconds / 3600, 2);

        $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
        $overtime = max(0.0, round($hours - $threshold, 2));

        $log->check_out = $nowUtc;
        $log->hours_worked = $hours;
        $log->overtime_hours = $overtime;
        $log->gps_lat = $gpsLat ?? $log->gps_lat;
        $log->gps_lng = $gpsLng ?? $log->gps_lng;

        if ($log->status === 'incomplete' && $schedule) {
            $checkInLocal = $log->check_in->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $log->late_minutes = max(0, $diffMinutes);
            $log->status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
        }

        $log->save();

        return $log;
    }

    private function resolveSchedule(Employee $employee): ?Schedule
    {
        if ($employee->schedule_id) {
            return Schedule::query()->find($employee->schedule_id);
        }

        return null;
    }
}

