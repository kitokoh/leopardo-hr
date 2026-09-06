<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : création d'une politique de congés par un responsable
 * (principal/rh).
 *
 * Consommé par `POST /api/v1/leave-policies`
 * (LeavePolicyController::store). La garde d'isolation tenant sur le type
 * d'absence référencé (`absence_type_id` du tenant courant) est portée ici :
 * un type d'un autre tenant rend la validation invalide (422), comme avant
 * l'extraction.
 */
class CreateLeavePolicy
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): LeavePolicy
    {
        $absenceTypeBelongsToCompany = AbsenceType::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['absence_type_id'])
            ->exists();

        if ($absenceTypeBelongsToCompany === false) {
            throw ValidationException::withMessages([
                'absence_type_id' => ['The selected absence type is invalid.'],
            ]);
        }

        return LeavePolicy::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);
    }
}
