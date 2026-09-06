<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;

/**
 * Cas d'usage : verrouillage comptable d'un run validé (étape 2 du workflow
 * F-11, #5246) — toute modification ultérieure (recalcul, annulation) est
 * refusée tant que le run est verrouillé ; l'opération est tracée (audit
 * `payroll_run_locked`).
 *
 * Orchestration pure et nommable (ADR-0020, lot 1 cycle de paie — #6896) :
 * la politique métier (run validé requis, statuts exclus, assertHasPaySlips,
 * audit trail) reste dans `PayrollClosingService` (Infrastructure).
 *
 * L'autorisation RBAC (principal/comptable — séparation des tâches) et le
 * mapping HTTP des erreurs restent au niveau interface (contrôleur).
 *
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunNoSlipsException
 * @throws \RuntimeException si le run n'est pas au statut `validated`
 */
class LockPayrollRun
{
    public function __construct(
        private readonly PayrollClosingService $closing,
    ) {
    }

    public function execute(PayrollRun $run, Employee $validator): PayrollRun
    {
        return $this->closing->lock($run, $validator);
    }
}
