<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Application\Services\PayrollRegularizationService;
use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * Cas d'usage : création d'un run de régularisation pour un run verrouillé
 * (DZ-DEPTH #1818). Le run original n'est jamais modifié ; le motif est
 * obligatoire et tracé. Un run de régularisation ne peut pas être lui-même
 * régularisé (#1818) et le verrou pessimiste anti-course (TOCTOU #1942) est
 * posé par le service.
 *
 * Orchestration pure et nommable (ADR-0020, lot 1 cycle de paie — #6896) :
 * la politique métier (statut `locked` requis, transaction, audit) reste dans
 * `PayrollRegularizationService` (Application/Services — existant).
 *
 * L'autorisation RBAC et la validation HTTP du motif restent au niveau
 * interface (contrôleur).
 *
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunNotLockedException
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunHasRegularizationsException
 * @throws \RuntimeException si le motif est vide
 */
class CreatePayrollRegularization
{
    public function __construct(
        private readonly PayrollRegularizationService $regularization,
    ) {
    }

    public function execute(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        return $this->regularization->createRegularization($run, $actor, $reason);
    }
}
