<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use App\Modules\Attendance\Infrastructure\Services\AttendanceDayClosureService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceHoursCalculator;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cas d'usage : approbation d'une session GPS par un manager ou RH.
 *
 * À l'approbation :
 * 1. Vérifie que la journée n'est pas fermée (attendance_day_closures, #5265)
 * 2. Crée un attendance_log avec method='geo_auto'
 * 3. Calcule les heures travaillées, le retard éventuel via le calculateur
 *    unique AttendanceHoursCalculator (#5265 — mêmes règles que les modes
 *    mobile/kiosque/ZKTeco, pauses du planning déduites)
 * 4. Met à jour la session GPS (status=approved, attendance_log_id)
 */
class ApproveGeoSession
{
    public function __construct(
        private readonly AttendanceHoursCalculator $calculator,
        private readonly AttendanceDayClosureService $dayClosureService,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(
        GeoAttendanceSession $session,
        Employee $validator,
        ?string $note = null
    ): GeoAttendanceSession {
        return DB::transaction(function () use ($session, $validator, $note): GeoAttendanceSession {

            $company = currentCompany();
            $employee = $session->employee;

            if (! $employee instanceof Employee) {
                throw new \RuntimeException('Session GPS sans employé rattaché.');
            }

            $timezone = (string) ($company->timezone ?: config('app.timezone', 'UTC'));
            $startedAt = $session->started_at->copy()->setTimezone($timezone);
            $today = $startedAt->toDateString();

            // Fermeture de journée (#5265) : pas de log géo sur un jour clos.
            $this->dayClosureService->assertDayOpen($employee->id, $today);

            // Résoudre le planning de l'employé
            $schedule = $this->resolveSchedule($employee);

            // Règles de calcul unifiées (#5265) : même source de vérité que le
            // check-in/check-out mobile — retards (tolérance), heures (pauses
            // déduites), heures supplémentaires (seuil + types non travaillés).
            $lateMinutes = 0;
            $status = 'ontime';

            if ($schedule) {
                $startLocal = Carbon::parse($today.' '.$schedule->start_time, $timezone);
                $assessment = $this->calculator->lateAssessment($startedAt, $startLocal, (int) $schedule->late_tolerance_minutes);
                $lateMinutes = $assessment->late_minutes;
                $status = $assessment->status;
            }

            $worked = $this->calculator->workedHours(
                $session->started_at,
                $session->ended_at ?? $session->started_at,
                'normal',
                $this->calculator->effectiveBreakMinutes(1, 'normal', $schedule?->break_minutes),
                (float) ($schedule?->overtime_threshold_daily ?? 8.0),
            );

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
                'hours_worked'   => $worked->hours_worked,
                'overtime_hours' => $worked->overtime_hours,
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
