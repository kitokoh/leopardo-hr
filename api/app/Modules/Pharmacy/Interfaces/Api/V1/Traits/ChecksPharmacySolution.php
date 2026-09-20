<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Traits;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacySolutionInactiveException;
use Illuminate\Database\Eloquent\Model;

/**
 * Gardes communes des contrôleurs API Pharmacy — PHARMA-001 (#7798).
 *
 * - `assertSolutionActive()` : feature flag `pharmacy` fail-closed (403
 *   PHARMACY_SOLUTION_INACTIVE si le tenant n'a pas activé la solution).
 * - `assertSameTenant()` : ressource d'un autre tenant → 404 (isolation
 *   fail-closed, aucune fuite cross-tenant).
 */
trait ChecksPharmacySolution
{
    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('pharmacy', currentCompany())) {
            throw new PharmacySolutionInactiveException;
        }
    }

    private function assertSameTenant(Model $model, ?string $companyId): void
    {
        if ($companyId === null || $model->getAttribute('company_id') !== $companyId) {
            abort(404);
        }
    }
}
