<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Infrastructure\Services\HealthAdmissionService;

/**
 * Cas d'usage « clôturer une admission » (sortie du patient) — HC-006
 * (#7790, BC-31).
 *
 * Extrait de `HealthAdmissionController::discharge` (BOS-024b, #8213) :
 * `discharged_at` + statut `discharged` + lit libéré ; double sortie →
 * 422 (service, transaction + verrou).
 */
final class DischargeHealthAdmissionAction
{
    public function __construct(private readonly HealthAdmissionService $service) {}

    public function execute(HealthAdmission $admission, ?string $dischargeNotes): HealthAdmission
    {
        return $this->service->discharge($admission, $dischargeNotes);
    }
}
