<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * #7962 — cas d'usage « pointage du jour » extrait de `AttendanceController`.
 *
 * Le controller garde ce qui lui appartient : l'autorisation (`viewAny` /
 * `viewForEmployee`) et la sérialisation HTTP. Cette Action porte la
 * résolution des trois modes de lecture et l'agrégation des sessions.
 *
 * ## Les trois modes, et pourquoi ils ne peuvent pas fusionner
 *
 * 1. **`single` sur un employé ciblé** — un manager consulte un collaborateur
 *    précis (`?employee_id=`). Autorisation `viewForEmployee`.
 * 2. **`collection`** — un manager consulte son périmètre. La requête est
 *    bornée par `visibleToManager()` quand l'acteur est team-scoped : un
 *    manager d'équipe ne doit pas voir les pointages des autres équipes.
 * 3. **`single` sur soi** — un employé consulte son propre pointage.
 *
 * Les modes 1 et 3 produisent la même forme de réponse mais par des chemins
 * d'autorisation DIFFÉRENTS. Les fusionner ferait dépendre l'autorisation
 * d'une condition de données (`$employeeId` présent ou non) au lieu d'une
 * décision explicite — c'est exactement le genre de raccourci qui produit une
 * élévation de privilège silencieuse.
 *
 * ## Le tri « session ouverte d'abord »
 *
 * `orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')` puis
 * `session_number DESC` : la session en cours prime sur la dernière session
 * clôturée. Sans ce tri, un employé en pause déjeuner verrait sa session du
 * matin au lieu de celle qu'il est en train de vivre.
 */
final class GetAttendanceToday
{
    public function __construct(
        private readonly EstimationService $estimationService,
    ) {}

    /**
     * Pointage du jour d'un employé ciblé (mode `single`).
     *
     * Deux agrégats DISTINCTS, et c'est volontaire :
     *  - `estimation` vient de `EstimationService` et alimente la ressource
     *    (`base_gain`, gain estimé du jour) ;
     *  - `summary` est l'agrégat de sessions exposé tel quel dans `data.summary`.
     * Les fusionner fait planter la ressource sur `base_gain` — c'est
     * exactement ce que la première version de cette extraction a produit.
     *
     * @return array{log: AttendanceLog|null, sessions: EloquentCollection<int, AttendanceLog>, estimation: array<string, mixed>, summary: array<string, mixed>}
     */
    public function forEmployee(Employee $employee, string $date): array
    {
        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('session_number')
            ->first();

        $sessions = $this->dailySessions($employee, $date);

        return [
            'log' => $log,
            'sessions' => $sessions,
            'estimation' => $this->estimationService->dailySummaryFromLogs($employee, $sessions, $date),
            'summary' => $this->dailySessionSummary($sessions),
        ];
    }

    /**
     * Pointage du jour du périmètre d'un manager (mode `collection`).
     *
     * @return array{items: list<array{employee: Employee, log: AttendanceLog|null, summary: array<string, mixed>}>, meta: array{current_page: int, per_page: int, total: int}}
     */
    public function forManagerScope(Employee $actor, string $date, int $perPage): array
    {
        $paginator = Employee::query()
            ->select(['id', 'company_id', 'department_id', 'first_name', 'last_name', 'email', 'role', 'status'])
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->when(
                $actor->isTeamScoped(),
                fn ($query) => $query->visibleToManager($actor)
            )
            ->orderBy('id')
            ->paginate($perPage);

        $employees = collect($paginator->items());
        $employeeIds = $employees->pluck('id')->all();

        $logsByEmployee = AttendanceLog::query()
            ->select(['id', 'employee_id', 'date', 'session_number', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'method', 'work_type', 'punch_note', 'punch_meta', 'late_minutes'])
            ->where('date', $date)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->groupBy('employee_id');

        $items = $employees->map(function (Employee $employee) use ($logsByEmployee, $date): array {
            $logs = $logsByEmployee->get($employee->id, collect());
            /** @var AttendanceLog|null $log */
            $log = $logs
                ->sortByDesc(fn (AttendanceLog $log) => ($log->check_out === null ? 100000 : 0) + (int) $log->session_number)
                ->first();

            return [
                'employee' => $employee,
                'log' => $log,
                'summary' => $this->estimationService->dailySummaryFromLogs($employee, $logs, $date),
            ];
        })->values()->all();

        return [
            'items' => array_values($items),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return EloquentCollection<int, AttendanceLog>
     */
    private function dailySessions(Employee $employee, string $date): EloquentCollection
    {
        return AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('session_number')
            ->get();
    }

    /**
     * @param  iterable<int, AttendanceLog>  $sessions
     * @return array<string, mixed>
     */
    private function dailySessionSummary(iterable $sessions): array
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
}
