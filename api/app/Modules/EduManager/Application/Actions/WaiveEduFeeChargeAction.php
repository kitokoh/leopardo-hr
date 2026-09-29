<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;

/**
 * Cas d'usage : abandon du solde d'une charge (EDU-016, issue #5832).
 *
 * Consommé par `POST .../edu-manager/fee-charges/{charge}/waive`
 * (EduFeeChargeController::waive). La règle métier reste dans EduFeeService
 * (Infrastructure).
 */
class WaiveEduFeeChargeAction
{
    public function __construct(
        private readonly EduFeeService $fees,
    ) {}

    public function execute(Employee $actor, EduFeeCharge $charge): EduFeeCharge
    {
        return $this->fees->waiveCharge($actor, $charge);
    }
}
