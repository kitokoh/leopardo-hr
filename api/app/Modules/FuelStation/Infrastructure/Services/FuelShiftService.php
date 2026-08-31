<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Exceptions\ShiftOverlapException;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Règles métier des shifts FuelStation (FUEL-005, issue #5799).
 *
 * - Tenant-scope : tout accès passe par `company_id` (le trait
 *   `BelongsToCompany` scope les queries sur la surface API tenant) ;
 *   une affectation référence un employé DU MÊME tenant.
 * - Chevauchement : un employé ne peut pas être affecté, le même jour, à
 *   deux shifts dont les plages [start_time, end_time) se recouvrent.
 * - Suppression : un shift avec affectations ne peut pas être supprimé
 *   (état cohérent des plannings historiques).
 */
final class FuelShiftService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $actor, array $data): FuelShift
    {
        /** @var array<string, mixed> $payload */
        $payload = array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]);

        return FuelShift::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FuelShift $shift, array $data): FuelShift
    {
        $shift->update($data);

        return $shift->refresh();
    }

    /**
     * Supprime un shift — refusé s'il porte des affectations.
     */
    public function delete(FuelShift $shift): void
    {
        // Les affectations annulées ne bloquent pas la suppression (elles ne
        // lient plus personne) ; les autres (scheduled/confirmed/completed) oui.
        $hasAssignments = FuelShiftAssignment::query()
            ->where('shift_id', $shift->id)
            ->where('status', '!=', FuelShiftAssignment::STATUS_CANCELLED)
            ->exists();

        abort_if($hasAssignments, 422, 'SHIFT_HAS_ASSIGNMENTS');

        $shift->delete();
    }

    /**
     * Affecte un employé à un shift pour une date, après contrôle
     * tenant + chevauchement.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(FuelShift $shift, Employee $actor, array $data): FuelShiftAssignment
    {
        abort_if($shift->status !== FuelShift::STATUS_ACTIVE, 422, 'SHIFT_INACTIVE');

        $rawEmployeeId = $data['employee_id'] ?? null;
        $employeeId = is_numeric($rawEmployeeId) ? (int) $rawEmployeeId : 0;

        // Un employé d'un autre tenant ne peut pas être affecté ici.
        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($employeeId)
            ->first();

        abort_if($employee === null, 422, 'EMPLOYEE_OUTSIDE_TENANT');

        /** @var string $assignmentDate */
        $assignmentDate = $data['assignment_date'];

        $this->assertNoOverlap((string) $actor->company_id, $employeeId, $shift, $assignmentDate);

        return FuelShiftAssignment::query()->create([
            'company_id' => $actor->company_id,
            'shift_id' => $shift->id,
            'employee_id' => $employeeId,
            'assignment_date' => $data['assignment_date'],
            'status' => $data['status'] ?? FuelShiftAssignment::STATUS_SCHEDULED,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Empêche deux affectations chevauchantes du même employé le même jour.
     * Plages [start, end) : chevauchement si A.start < B.end ET B.start < A.end.
     */
    private function assertNoOverlap(string $companyId, int $employeeId, FuelShift $shift, string $date): void
    {
        $overlap = FuelShiftAssignment::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('assignment_date', $date)
            ->whereHas('shift', function ($query) use ($shift): void {
                $query->where('status', FuelShift::STATUS_ACTIVE)
                    ->where('end_time', '>', $shift->start_time)
                    ->where('start_time', '<', $shift->end_time);
            })
            ->exists();

        if ($overlap) {
            throw new ShiftOverlapException;
        }
    }

    /**
     * Affectations d'un employé sur une période (self-service pompiste).
     *
     * @return Collection<int, FuelShiftAssignment>
     */
    public function assignmentsForEmployee(Employee $employee, ?Carbon $from, ?Carbon $to)
    {
        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        return FuelShiftAssignment::query()
            ->with('shift')
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->when($fromDate !== null, fn ($query) => $query->where('assignment_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->where('assignment_date', '<=', $toDate))
            ->orderBy('assignment_date')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Affectations d'un shift (vue manager).
     *
     * @return Collection<int, FuelShiftAssignment>
     */
    public function assignmentsForShift(FuelShift $shift, ?Carbon $from, ?Carbon $to)
    {
        $fromDate = $from?->toDateString();
        $toDate = $to?->toDateString();

        return FuelShiftAssignment::query()
            ->with(['employee:id,first_name,last_name,email,company_id'])
            ->where('shift_id', $shift->id)
            ->when($fromDate !== null, fn ($query) => $query->where('assignment_date', '>=', $fromDate))
            ->when($toDate !== null, fn ($query) => $query->where('assignment_date', '<=', $toDate))
            ->orderBy('assignment_date')
            ->get();
    }

    /**
     * Annule une affectation (statut terminal cancelled, jamais supprimée —
     * traçabilité planning).
     */
    public function cancelAssignment(FuelShiftAssignment $assignment, Employee $actor): FuelShiftAssignment
    {
        $assignment->update([
            'status' => FuelShiftAssignment::STATUS_CANCELLED,
            'notes' => trim(($assignment->notes ?? '')." — annulé par {$actor->id}"),
        ]);

        return $assignment->refresh();
    }
}
