<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionService;

/**
 * Cas d'usage : création d'une admission (EDU-010, issue #5826, EDU-004).
 *
 * Consommé par `POST .../edu-manager/admissions` (EduAdmissionController::store).
 * Création idempotente par `external_id` ; la validation métier reste dans
 * EduAdmissionService (Infrastructure), l'Action porte le cas d'usage nommable.
 */
class CreateEduAdmissionAction
{
    public function __construct(
        private readonly EduAdmissionService $admissions,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): EduAdmission
    {
        return $this->admissions->create($actor, $validated);
    }
}
