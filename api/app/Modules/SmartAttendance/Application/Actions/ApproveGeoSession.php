<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cas d'usage : approbation d'une session GPS par un manager ou RH.
 *
 * À l'approbation :
 * 1. Crée un attendance_log avec method='geo_auto'
 * 2. Calcule les heures travaillées, le retard éventuel
 * 3. Met à jour la session GPS (status=approved, attendance_log_id)
 */
class ApproveGeoSession
{
    /**
     * @throws \Throwable
     */
    public function handle(
        GeoAttendanceSession $session,
        Employee $validator,
        ?string $note = null
    ): GeoAttendanceSession {
        return DB::transaction(function () use ($session, $validator, $note): GeoAttendanceSession {

            $company  = currentCompany();
            $employee = $session->employee;

            $startedAt = $session->started_at->copy()->setTimezone($company->timezone);
            $endedAt   = $session->ended_at?->copy()->setTimezone($company->timezone);
            $today     = $startedAt->toDateString();

            // Résoudre le planning de l'employé
            $schedule = $this->resolveSchedule($employee);

            // Calculer les heures travaillées
            $durationHours = $session->durationHours();

            // Calculer le retard
            $lateMinutes = 0;
            $status      = 'ontime';

            if ($schedule) {
                $startLocal   = Carbon::parse($today . ' ' . $schedule->start_time, $company->timezone);
                $diffMinutes  = $startLocal->diffInMinutes($startedAt, false);
                $tolerance    = (int) $schedule->late_tolerance_minutes;
                $lateMinutes  = max(0, (int) floor($diffMinutes - $tolerance));
                $status       = $lateMinutes > 0 ? 'late' : 'ontime';
            }

            // Calculer les heures supplémentaires
            $threshold     = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
            $overtimeHours = round(max(0.0, $durationHours - $threshold), 2);

            // Déterminer le session_number (éviter les collisions)
            $existingCount = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->count();

            $sessionNumber = $existingCount + 1;

            // Créer le attendance_log officiel
            $log = AttendanceLog::query()->create([
                'company_id'     => $employee->company_id,
                'employee_id'    => $employee->id,
                'schedule_id'    => $schedule?->id,
                'date'           => $today,
                'session_number' => $sessionNumber,
                'check_in'       => $session->started_at,
                'check_out'      => $session->ended_at,
                'method'         => 'geo_auto',
                'status'         => $status,
                'hours_worked'   => round($durationHours, 2),
                'overtime_hours' => $overtimeHours,
                'late_minutes'   => $lateMinutes,
                'gps_lat'        => $session->check_in_lat,
                'gps_lng'        => $session->check_in_lng,
                'corrected_by'   => $validator->id,
                'correction_note' => $note,
            ]);

            // Mettre à jour la session GPS
            $session->update([
                'status'            => GeoAttendanceSession::STATUS_APPROVED,
                'attendance_log_id' => $log->id,
                'validated_by'      => $validator->id,
                'validated_at'      => now(),
                'validation_note'   => $note,
            ]);

            return $session->fresh();
        });
    }

    private function resolveSchedule(Employee $employee): ?Schedule
    {
        if ($employee->schedule_id) {
            return Schedule::find($employee->schedule_id);
        }

        return null;
    }
}

