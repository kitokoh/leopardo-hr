<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduStudent;

/**
 * Cas d'usage : création d'un élève (EDU-010, issue #5826).
 *
 * Consommé par `POST .../edu-manager/students` (EduStudentController::store).
 * La PII `birth_date` est basculée vers la colonne chiffrée au repos et le
 * tenant vient de la session de l'acteur, jamais du payload.
 */
class CreateEduStudentAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): EduStudent
    {
        if (isset($validated['birth_date'])) {
            $validated['birth_date_encrypted'] = $validated['birth_date'];
            unset($validated['birth_date']);
        }

        /** @var EduStudent $student */
        $student = EduStudent::query()->create(array_merge($validated, [
            'company_id' => $actor->company_id,
        ]));

        return $student;
    }
}
