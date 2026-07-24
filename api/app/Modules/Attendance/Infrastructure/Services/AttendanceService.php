<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\AttendanceCheckedIn;
use App\Events\AttendanceCheckedOut;
use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\MissingCheckInException;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Exceptions\PunchPhotoRequiredException;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\AttendanceModeSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceService
{
    /** @var array<int, string> */
    private const NON_WORK_TYPES = ['break'];

    public function __construct(
        private readonly AttendanceGeofenceService $geofenceService,
        private readonly CommunicationService $communicationService,
    ) {}

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

        $this->ensurePunchPhotoProvided($company, $dto);
        $photoPath = $this->storePunchPhoto($company, $employee, $dto);

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
            'punch_photo_path' => $photoPath,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'gps_lat' => $dto->gps_lat,
            'gps_lng' => $dto->gps_lng,
        ]);

        AttendanceCheckedIn::dispatch($log);
        $this->alertManagersIfOutsideGeofence($employee, $log, 'check_in');

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

        $this->ensurePunchPhotoProvided($company, $dto);
        $photoPath = $this->storePunchPhoto($company, $employee, $dto);

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
        if ($photoPath !== null) {
            $log->punch_photo_path = $photoPath;
        }
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
        $this->alertManagersIfOutsideGeofence($employee, $log, 'check_out');

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
     * Verifie que la photo obligatoire (mode photo_required) a bien ete fournie
     * pour ce pointage mobile. Le mode kiosque physique (source_device_code) et
     * les imports externes ne sont jamais concernes par cette contrainte.
     */
    private function ensurePunchPhotoProvided(Company $company, CheckInDTO $dto): void
    {
        if ($dto->source_device_code !== null || $dto->synced_from_offline) {
            return;
        }

        if (! Schema::hasTable('attendance_mode_settings')) {
            return;
        }

        $settings = AttendanceModeSettings::where('company_id', $company->id)->first();

        if (! $settings || ! $settings->requiresPunchPhoto()) {
            return;
        }

        if ($dto->punch_photo === null) {
            throw new PunchPhotoRequiredException;
        }
    }

    /**
     * Stocke la photo de pointage envoyee (si presente) et retourne son chemin
     * de stockage relatif, ou null si aucune photo n'a ete fournie.
     */
    private function storePunchPhoto(Company $company, Employee $employee, CheckInDTO $dto): ?string
    {
        if ($dto->punch_photo === null) {
            return null;
        }

        $path = $dto->punch_photo->store(
            sprintf('attendance/punch-photos/%s/%d', $employee->company_id ?? $company->id, $employee->id),
            'local',
        );

        return $path === false ? null : $path;
    }

    /**
     * Notifies the employee's manager (or, absent a direct manager, every
     * company-wide principal/rh manager) when a check-in/check-out lands
     * outside the configured geofence. Purely informational: the punch
     * itself is never blocked by this (see buildPunchMeta()/
     * AttendanceGeofenceService, PA2-ATT-009's "pointage tolerant GPS
     * indisponible" requirement). No-op when geofencing isn't configured,
     * GPS was unavailable (inside === null), or the punch is inside the
     * zone.
     */
    private function alertManagersIfOutsideGeofence(Employee $employee, AttendanceLog $log, string $phase): void
    {
        $geofence = $log->punch_meta['geofence'] ?? null;

        if (! is_array($geofence) || $geofence['inside'] !== false) {
            return;
        }

        $recipients = $employee->resolveAlertRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) ?: $employee->email;
        $distance = $geofence['distance_meters'] ?? '?';
        $radius = $geofence['radius_meters'] ?? '?';

        foreach ($recipients as $manager) {
            $locale = $manager->preferred_language ?: config('app.fallback_locale', 'en');
            $phaseLabel = trans('notifications.attendance_geofence_alert_phase_'.$phase, [], $locale);

            $this->communicationService->notifyEmployee($manager, 'attendance_geofence_alert', [
                'title' => trans('notifications.attendance_geofence_alert_title', ['employee' => $employeeName], $locale),
                'body' => trans('notifications.attendance_geofence_alert_body', [
                    'employee' => $employeeName,
                    'phase' => $phaseLabel,
                    'distance' => $distance,
                    'radius' => $radius,
                ], $locale),
                'attendance_log_id' => $log->id,
                'employee_id' => $employee->id,
            ], ['app']);
        }
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
