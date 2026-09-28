<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Infrastructure\Services\HealthAdmissionService;

/**
 * Cas d'usage « transférer une admission vers un autre lit » — HC-006
 * (#7790, BC-31).
 *
 * Extrait de `HealthAdmissionController::transfer` (BOS-024b, #8213) :
 * ancien lit libéré + nouveau occupé atomiquement (service, transaction
 * + verrous anti-deadlock).
 */
final class TransferHealthAdmissionAction
{
    public function __construct(private readonly HealthAdmissionService $service) {}

    public function execute(HealthAdmission $admission, int $bedId): HealthAdmission
    {
        return $this->service->transfer($admission, $bedId);
    }
}
