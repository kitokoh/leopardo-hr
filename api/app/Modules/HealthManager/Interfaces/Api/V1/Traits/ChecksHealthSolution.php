<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Traits;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\HealthManager\Domain\Exceptions\HealthSolutionInactiveException;
use Illuminate\Database\Eloquent\Model;

/**
 * Gardes communes des contrôleurs API HealthManager — HC-001 (#7785).
 *
 * - `assertSolutionActive()` : feature flag `healthmanager` fail-closed
 *   (403 HEALTH_SOLUTION_INACTIVE si le tenant n'a pas activé la solution).
 * - `assertSameTenant()` : ressource d'un autre tenant → 404 (isolation
 *   fail-closed, aucune fuite cross-tenant — pattern ChecksEduSolution).
 */
// Contrôleurs consommateurs livrés par HC-002+ (#7786–#7792).
// @phpstan-ignore trait.unused
trait ChecksHealthSolution
{
    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('healthmanager', currentCompany())) {
            throw new HealthSolutionInactiveException;
        }
    }

    private function assertSameTenant(Model $model, ?string $companyId): void
    {
        if ($companyId === null || $model->getAttribute('company_id') !== $companyId) {
            abort(404);
        }
    }
}
