<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use Illuminate\Support\Collection as SupportCollection;

/**
 * #7962 — agrégation partagée du pointage du jour.
 *
 * Extrait sous `Infrastructure/` et non sous `Application/` : la garde
 * `check-application-layer-placement.sh` interdit les `*Service.php` sous
 * `Application/`, et cette classe ne porte aucune décision métier — elle
 * calcule des totaux à partir de lignes déjà chargées.
 *
 * ## Deux agrégats DISTINCTS, et c'est volontaire
 *
 * - `estimation()` vient de `EstimationService` et alimente
 *   `AttendanceTodayResource` (champ `base_gain`, gain estimé du jour) ;
 * - `sessionSummary()` est l'agrégat de sessions exposé tel quel dans
 *   `data.summary`.
 *
 * Les confondre fait planter la ressource sur `base_gain` → 500 sur
 * `GET /attendance/today`. C'est le défaut type d'une extraction « à
 * l'identique » quand deux variables locales portent des noms proches : le
 * compilateur ne dit rien, le test dit tout.
 */
final class AttendanceTodayAggregator
{
    public function __construct(
        private readonly EstimationService $estimationService,
    ) {}

    /**
     * @param  iterable<int, AttendanceLog>  $sessions
     * @return array<string, mixed>
     */
    public function estimation(\App\Core\Auth\Domain\Models\Employee $employee, iterable $sessions, string $date): array
    {
        return $this->estimationService->dailySummaryFromLogs($employee, $sessions, $date);
    }

    /**
     * @param  iterable<int, AttendanceLog>  $sessions
     * @return array<string, mixed>
     */
    public function sessionSummary(iterable $sessions): array
    {
        $totalHours = 0.0;
        $overtimeHours = 0.0;
        $lateMinutes = 0;
        $breakMinutes = 0;
        $previousCheckout = null;
        $openSession = null;
        $count = 0;

        foreach ($sessions as $session) {
            $count++;
            $totalHours += (float) ($session->hours_worked ?? 0);
            $overtimeHours += (float) ($session->overtime_hours ?? 0);
            $lateMinutes += (int) ($session->late_minutes ?? 0);

            if ($previousCheckout !== null && $session->check_in !== null) {
                $breakMinutes += max(0, $previousCheckout->diffInMinutes($session->check_in, false));
            }

            if ($session->check_out !== null) {
                $previousCheckout = $session->check_out;
            } else {
                $openSession = $session;
            }
        }

        return [
            'sessions_count' => $count,
            'is_working' => $openSession !== null,
            'current_session_id' => $openSession?->id,
            'current_work_type' => $openSession?->work_type,
            'total_hours_worked' => round($totalHours, 2),
            'total_overtime_hours' => round($overtimeHours, 2),
            'break_minutes' => $breakMinutes,
            'late_minutes' => $lateMinutes,
        ];
    }

    /**
     * Session « courante » d'une journée : la session ouverte prime sur la
     * dernière clôturée. Sans ce tri, un employé en pause déjeuner verrait sa
     * session du matin au lieu de celle qu'il est en train de vivre.
     *
     * @param  SupportCollection<int, AttendanceLog>  $logs
     */
    public function currentLog(SupportCollection $logs): ?AttendanceLog
    {
        /** @var AttendanceLog|null $log */
        $log = $logs
            ->sortByDesc(fn (AttendanceLog $log) => ($log->check_out === null ? 100000 : 0) + (int) $log->session_number)
            ->first();

        return $log;
    }
}
