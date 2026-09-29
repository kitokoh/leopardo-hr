<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Modules\EduManager\Domain\Models\EduStudent;

/**
 * Cas d'usage : mise à jour d'un élève (EDU-010, issue #5826).
 *
 * Consommé par `PUT/PATCH .../edu-manager/students/{student}`
 * (EduStudentController::update). Même bascule PII `birth_date` → colonne
 * chiffrée qu'à la création ; l'appartenance au tenant est vérifiée en amont
 * par le controller (assertSameTenant + Policy).
 */
class UpdateEduStudentAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(EduStudent $student, array $validated): EduStudent
    {
        if (isset($validated['birth_date'])) {
            $validated['birth_date_encrypted'] = $validated['birth_date'];
            unset($validated['birth_date']);
        }

        $student->update($validated);

        return $student->refresh();
    }
}
