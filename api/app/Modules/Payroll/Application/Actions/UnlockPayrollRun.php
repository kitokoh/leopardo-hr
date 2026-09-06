<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;

/**
 * Cas d'usage : déverrouillage motivé d'un run clôturé (retour à `validated`).
 * La raison est obligatoire et tracée (audit `payroll_run_unlocked`) — un run
 * original porteur de régularisations actives ne peut pas être déverrouillé
 * (invariant #1818/#1942 : l'original n'est jamais modifié).
 *
 * Orchestration pure et nommable (ADR-0020, lot 1 cycle de paie — #6896) :
 * la politique métier reste dans `PayrollClosingService` (Infrastructure).
 *
 * L'autorisation RBAC (principal/comptable — réversion de clôture sensible)
 * et la validation HTTP de la raison restent au niveau interface (contrôleur).
 *
 * @throws \App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException
 * @throws \RuntimeException si le run n'est pas verrouillé ou si la raison est vide
 */
class UnlockPayrollRun
{
    public function __construct(
        private readonly PayrollClosingService $closing,
    ) {
    }

    public function execute(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        return $this->closing->unlock($run, $actor, $reason);
    }
}
