<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;

/**
 * Cas d'usage : encaissement d'un paiement sur une charge (EDU-016, issue #5832).
 *
 * Consommé par `POST .../edu-manager/fee-charges/{charge}/payments`
 * (EduFeeChargeController::payment). Paiement partiel ou soldant ; la charge
 * retournée est rafraîchie (statut pending → partial/paid). La règle métier
 * reste dans EduFeeService (Infrastructure).
 */
class RecordEduFeePaymentAction
{
    public function __construct(
        private readonly EduFeeService $fees,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{payment: EduFeePayment, charge: EduFeeCharge}
     */
    public function execute(Employee $actor, EduFeeCharge $charge, array $validated): array
    {
        /** @var array{payment: EduFeePayment, charge: EduFeeCharge} $result */
        $result = $this->fees->recordPayment($actor, $charge, $validated);

        return $result;
    }
}
