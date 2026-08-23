<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\EmployeeLastContractTerminated;
use App\Modules\HR\Domain\Exceptions\InvalidContractTransitionException;
use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Support\Facades\DB;

/**
 * #3891 — machine à états du cycle de vie d'un contrat.
 *
 * Extraite du controller (écritures Eloquent directes + validation inline) :
 * chaque transition valide l'état courant, applique la mutation et retourne
 * le contrat rafraîchi. Les autorisations (rôle manager/principal, tenant)
 * restent à la charge du controller via ContractPolicy — cette classe ne
 * fait QUE la règle métier.
 *
 * Issue #5327 (G4) — ORCHESTRATION contrat ↔ employé : la transition de
 * contrat synchronise le statut de l'employé (activate → active,
 * suspend → suspended) et, quand le DERNIER contrat en cours est terminé,
 * émet `EmployeeLastContractTerminated` (hook du workflow de départ #5324).
 * Invariant garanti : JAMAIS de contrat actif/suspendu sur un employé
 * archivé (transition refusée). Aucun changement Payroll (constitution §III).
 */
final class ContractLifecycleAction
{
    public function activate(Contract $contract): Contract
    {
        if ($contract->status !== 'draft') {
            throw new InvalidContractTransitionException('Only draft contracts can be activated.');
        }

        $employee = $this->employeeOrFail($contract);
        $this->assertEmployeeNotArchived($employee);

        $contract->update(['status' => 'active', 'signed_at' => now()]);
        // `status` n'est pas mass-assignable (Employee::$fillable) → assignation
        // directe, même pattern que EmployeeService::archive (#5327).
        $employee->status = 'active';
        $employee->save();

        return $contract->fresh() ?? $contract;
    }

    public function suspend(Contract $contract): Contract
    {
        if ($contract->status !== 'active') {
            throw new InvalidContractTransitionException('Only active contracts can be suspended.');
        }

        $employee = $this->employeeOrFail($contract);
        $this->assertEmployeeNotArchived($employee);

        $contract->update(['status' => 'suspended']);
        $employee->status = 'suspended';
        $employee->save();

        return $contract->fresh() ?? $contract;
    }

    public function terminate(Contract $contract, string $reason): Contract
    {
        if (! in_array($contract->status, ['active', 'suspended'], true)) {
            throw new InvalidContractTransitionException('Contract must be active or suspended to terminate.');
        }

        $employee = $this->employeeOrFail($contract);
        $this->assertEmployeeNotArchived($employee);

        $contract->update([
            'status' => 'terminated',
            'termination_reason' => $reason,
            'terminated_at' => now(),
        ]);

        // G4 (#5327) : si c'était le dernier contrat en cours, l'employé n'a
        // plus de contrat actif → hook du workflow de départ (#5324). Le
        // statut `departed` est posé par ce workflow (migration 5324) ; ici
        // on ne fait QUE signaler l'événement (résilient avant son merge).
        if (! $this->hasActiveContract($employee)) {
            EmployeeLastContractTerminated::dispatch($employee, $contract);
        }

        return $contract->fresh() ?? $contract;
    }

    /**
     * Renouvellement : crée un contrat draft reprenant les caractéristiques
     * du contrat courant et expire l'ancien s'il était actif/suspendu.
     *
     * @param  array{start_date: string, end_date?: string|null, base_salary?: float|int|null}  $validated
     */
    public function renew(Contract $contract, Employee $actor, array $validated): Contract
    {
        return DB::transaction(function () use ($contract, $actor, $validated): Contract {
            $newContract = Contract::create([
                'company_id' => $contract->company_id,
                'employee_id' => $contract->employee_id,
                'contract_type' => $contract->contract_type,
                'reference' => null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'job_title' => $contract->job_title,
                'department_id' => $contract->department_id,
                'position_id' => $contract->position_id,
                'base_salary' => $validated['base_salary'] ?? $contract->base_salary,
                'currency' => $contract->currency,
                'salary_frequency' => $contract->salary_frequency,
                'work_hours_per_week' => $contract->work_hours_per_week,
                'benefits' => $contract->benefits,
                'clauses' => $contract->clauses,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            if (in_array($contract->status, ['active', 'suspended'], true)) {
                $contract->update(['status' => 'expired']);
            }

            return $newContract;
        });
    }

    private function employeeOrFail(Contract $contract): Employee
    {
        $employee = $contract->employee;
        if ($employee === null) {
            throw new InvalidContractTransitionException('Contract has no employee.');
        }

        return $employee;
    }

    /**
     * Invariant G4 (#5327) : jamais de contrat actif/suspendu sur un employé
     * archivé (un employé `departed` sera couvert par le workflow #5324).
     */
    private function assertEmployeeNotArchived(Employee $employee): void
    {
        if ($employee->status === 'archived') {
            throw new InvalidContractTransitionException('Cannot change contract status of an archived employee.');
        }
    }

    private function hasActiveContract(Employee $employee): bool
    {
        return Contract::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'suspended'])
            ->exists();
    }
}
