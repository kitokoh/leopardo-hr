<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;

/**
 * Cas d'usage : validation RH d'un run de paie (étape 1 du workflow F-11,
 * #5246) — vérification/vise par un manager principal/comptable.
 *
 * Orchestration pure et nommable (ADR-0020, lot 1 cycle de paie — #6896) :
 * la politique métier (statuts acceptés, run à 0 bulletin, séparation des
 * tâches, audit trail `payroll_run_validated`) reste dans
 * `PayrollClosingService` (Infrastructure) — cette Action ne fait que porter
 * le cas d'usage, sans dupliquer la règle.
 *
 * L'autorisation RBAC (hasManagerRole) et le mapping HTTP des erreurs
 * restent au niveau interface (contrôleur).
 *
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunNoSlipsException
 * @throws \RuntimeException si le run n'est pas au statut `calculated`
 */
class ValidatePayrollRun
{
    public function __construct(
        private readonly PayrollClosingService $closing,
    ) {
    }

    public function execute(PayrollRun $run, Employee $validator): PayrollRun
    {
        return $this->closing->validateRh($run, $validator);
    }
}
