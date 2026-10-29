<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceTodayAggregator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * #7962 — cas d'usage « pointage du jour d'un employé » (modes `single`).
 *
 * Couvre les deux lectures qui produisent la même forme de réponse :
 * un manager qui consulte un collaborateur (`?employee_id=`) et un employé
 * qui consulte son propre pointage.
 *
 * ## Pourquoi une Action distincte de `GetManagerAttendanceToday`
 *
 * Les deux modes produisent la même forme de réponse mais par des chemins
 * d'autorisation DIFFÉRENTS (`viewForEmployee` vs `viewAny`). Les fusionner
 * ferait dépendre l'autorisation d'une condition de données (`$employeeId`
 * présent ou non) au lieu d'une décision explicite — c'est le raccourci qui
 * produit une élévation de privilège silencieuse : le jour où quelqu'un ajoute
 * un `?employee_id=` sur un autre chemin, l'autorisation suit la donnée au
 * lieu de la règle.
 *
 * La garde `check-actions-convention.sh` impose par ailleurs « 1 action =
 * 1 execute() » : deux cas d'usage, deux classes. Ce n'est pas une contrainte
 * subie — c'est la même frontière que celle de l'autorisation.
 */
final class GetAttendanceToday
{
    public function __construct(
        private readonly AttendanceTodayAggregator $aggregator,
    ) {}

    /**
     * @return array{log: AttendanceLog|null, sessions: EloquentCollection<int, AttendanceLog>, estimation: array<string, mixed>, summary: array<string, mixed>}
     */
    public function execute(Employee $employee, string $date): array
    {
        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('session_number')
            ->first();

        $sessions = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('session_number')
            ->get();

        return [
            'log' => $log,
            'sessions' => $sessions,
            'estimation' => $this->aggregator->estimation($employee, $sessions, $date),
            'summary' => $this->aggregator->sessionSummary($sessions),
        ];
    }
}
