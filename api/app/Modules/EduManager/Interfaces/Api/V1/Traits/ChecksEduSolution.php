<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Traits;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\EduManager\Domain\Exceptions\EduSolutionInactiveException;
use Illuminate\Database\Eloquent\Model;

/**
 * Gardes communes des contrôleurs API EduManager (EDU-010, #5826).
 *
 * - `assertSolutionActive()` : feature flag `edumanager` fail-closed (403
 *   EDU_SOLUTION_INACTIVE si le tenant n'a pas activé la solution).
 * - `assertSameTenant()` : ressource d'un autre tenant → 404 (isolation
 *   fail-closed, aucune fuite cross-tenant).
 */
trait ChecksEduSolution
{
    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('edumanager', currentCompany())) {
            throw new EduSolutionInactiveException;
        }
    }

    private function assertSameTenant(Model $model, string $companyId): void
    {
        if ($model->getAttribute('company_id') !== $companyId) {
            abort(404);
        }
    }
}
