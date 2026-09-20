<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Traits;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\HospitalityManager\Domain\Exceptions\HospitalitySolutionInactiveException;
use Illuminate\Database\Eloquent\Model;

/**
 * Gardes communes des contrôleurs API HospitalityManager — HOSP-001 (#7943).
 *
 * - `assertSolutionActive()` : feature flag `hospitality` fail-closed
 *   (403 HOSPITALITY_SOLUTION_INACTIVE si le tenant n'a pas activé la solution).
 * - `assertSameTenant()` : ressource d'un autre tenant → 404 (isolation
 *   fail-closed, aucune fuite cross-tenant — pattern ChecksHealthSolution).
 */
trait ChecksHospitalitySolution
{
    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('hospitality', currentCompany())) {
            throw new HospitalitySolutionInactiveException;
        }
    }

    private function assertSameTenant(Model $model, ?string $companyId): void
    {
        if ($companyId === null || $model->getAttribute('company_id') !== $companyId) {
            abort(404);
        }
    }
}
