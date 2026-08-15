<?php

declare(strict_types=1);

namespace App\Modules\HR\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
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
 */
final class ContractLifecycleAction
{
    public function activate(Contract $contract): Contract
    {
        if ($contract->status !== 'draft') {
            throw new InvalidContractTransitionException('Only draft contracts can be activated.');
        }

        $contract->update(['status' => 'active', 'signed_at' => now()]);

        return $contract->fresh() ?? $contract;
    }

    public function suspend(Contract $contract): Contract
    {
        if ($contract->status !== 'active') {
            throw new InvalidContractTransitionException('Only active contracts can be suspended.');
        }

        $contract->update(['status' => 'suspended']);

        return $contract->fresh() ?? $contract;
    }

    public function terminate(Contract $contract, string $reason): Contract
    {
        if (! in_array($contract->status, ['active', 'suspended'], true)) {
            throw new InvalidContractTransitionException('Contract must be active or suspended to terminate.');
        }

        $contract->update([
            'status' => 'terminated',
            'termination_reason' => $reason,
            'terminated_at' => now(),
        ]);

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
}
