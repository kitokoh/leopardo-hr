<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Infrastructure\Services\HealthAdmissionService;

/**
 * Cas d'usage « admettre un patient » (hospitalisation) — HC-006 (#7790, BC-31).
 *
 * Extrait de `HealthAdmissionController::store` (BOS-024b, #8213) :
 * patient et praticien référent du MÊME tenant (404 fail-closed), puis
 * admission sur lit libre via le service (transaction + verrous, 409
 * HEALTH_BED_OCCUPIED).
 */
final class AdmitHealthPatientAction
{
    public function __construct(private readonly HealthAdmissionService $service) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHealthAdmissionRequest).
     */
    public function execute(string $companyId, array $validated): HealthAdmission
    {
        // Patient et praticien référent du MÊME tenant (404 fail-closed).
        HealthPatient::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $validated['patient_id'])
            ->firstOrFail();
        HealthPractitioner::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $validated['practitioner_id'])
            ->firstOrFail();

        return $this->service->admit($companyId, $validated);
    }
}
