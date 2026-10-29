<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceTodayAggregator;

/**
 * #7962 — cas d'usage « pointage du jour du périmètre d'un manager » (mode
 * `collection`).
 *
 * Séparé de `GetAttendanceToday` parce que l'autorisation n'est pas la même :
 * ici `viewAny` sur le périmètre, là `viewForEmployee` sur une cible. Deux
 * décisions d'autorisation distinctes ne doivent pas partager une méthode
 * publique — sinon la règle devient une condition de données.
 *
 * ## Le périmètre est borné, pas filtré après coup
 *
 * `visibleToManager()` s'applique dans la requête quand l'acteur est
 * team-scoped : un manager d'équipe ne doit pas voir les pointages des autres
 * équipes. Filtrer en PHP après chargement aurait laissé les lignes d'un autre
 * tenant transiter par la mémoire du process — et le `total` de la pagination
 * aurait menti.
 */
final class GetManagerAttendanceToday
{
    public function __construct(
        private readonly AttendanceTodayAggregator $aggregator,
    ) {}

    /**
     * @return array{items: list<array{employee: Employee, log: AttendanceLog|null, estimation: array<string, mixed>}>, meta: array{current_page: int, per_page: int, total: int}}
     */
    public function execute(Employee $actor, string $date, int $perPage): array
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

            return [
                'employee' => $employee,
                'log' => $this->aggregator->currentLog($logs),
                'estimation' => $this->aggregator->estimation($employee, $logs, $date),
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
}
