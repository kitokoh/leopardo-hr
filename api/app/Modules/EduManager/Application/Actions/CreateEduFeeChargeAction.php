<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;

/**
 * Cas d'usage : création d'une charge de frais scolaires (EDU-016, issue #5832).
 *
 * Consommé par `POST .../edu-manager/fee-charges` (EduFeeChargeController::store).
 * Direction uniquement (garde `EDU_FEE_ADMIN_ONLY` au controller) ; la règle
 * métier reste dans EduFeeService (Infrastructure).
 */
class CreateEduFeeChargeAction
{
    public function __construct(
        private readonly EduFeeService $fees,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): EduFeeCharge
    {
        return $this->fees->createCharge($actor, $validated);
    }
}
