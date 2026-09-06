<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : octroi manuel d'une accumulation de congés (accrual,
 * adjustment ou carry_forward) par un responsable (principal/rh).
 *
 * Consommé par `POST /api/v1/leave-accruals`
 * (LeavePolicyController::storeAccrual). Crée l'accumulation puis crédite le
 * solde de l'année d'effet (`LeaveBalance` recréé si absent). Les gardes
 * d'isolation tenant (employé et politique du tenant courant) sont portées
 * ici : une référence hors tenant rend la validation invalide (422), comme
 * avant l'extraction.
 */
class CreateLeaveAccrual
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): LeaveAccrual
    {
        $employeeBelongsToCompany = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['employee_id'])
            ->exists();
        $policy = LeavePolicy::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['leave_policy_id'])
            ->first();

        if ($employeeBelongsToCompany === false) {
            throw ValidationException::withMessages([
                'employee_id' => ['The selected employee is invalid.'],
            ]);
        }

        if ($policy === null) {
            throw ValidationException::withMessages([
                'leave_policy_id' => ['The selected leave policy is invalid.'],
            ]);
        }

        $accrual = LeaveAccrual::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]);

        $balance = LeaveBalance::firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'employee_id' => $validated['employee_id'],
                'absence_type_id' => $policy->absence_type_id,
                'year' => (int) Carbon::parse($validated['effective_date'])->format('Y'),
            ],
            ['balance' => 0, 'used' => 0, 'pending' => 0]
        );

        $balance->increment('balance', (float) $validated['amount']);

        return $accrual;
    }
}
