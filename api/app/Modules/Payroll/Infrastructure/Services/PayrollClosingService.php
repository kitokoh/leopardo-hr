<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * Programme FOCUS — F-11 : clôture de paie en 2 étapes + verrouillage + audit.
 *
 * Flux : draft → calculated (calcul) → validated (RH) → locked (comptable).
 * Après verrouillage : tout recalcul est refusé (PayrollRunLockedException) ;
 * un déverrouillage motivé est nécessaire, et il est tracé.
 */
class PayrollClosingService
{
    /**
     * Étape 1 — validation RH (contrôle des montants avant clôture comptable).
     *
     * @throws PayrollAlreadyValidatedException
     */
    public function validateRh(PayrollRun $run, Employee $validator): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollAlreadyValidatedException('Run already locked (comptable closing done).');
        }
        if ($run->status === PayrollRun::STATUS_VALIDATED) {
            throw new PayrollAlreadyValidatedException('Run already validated.');
        }

        $old = ['status' => $run->status];
        $run->update([
            'status' => PayrollRun::STATUS_VALIDATED,
            'validated_by' => $validator->id,
            'validated_at' => now(),
        ]);
        $this->writeAudit($run, $validator, 'payroll_run_validated', $old, ['status' => PayrollRun::STATUS_VALIDATED]);

        return $run->fresh();
    }

    /**
     * Étape 2 — clôture comptable : verrouille le run.
     *
     * @throws PayrollAlreadyValidatedException
     */
    public function lock(PayrollRun $run, Employee $validator): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollAlreadyValidatedException('Run already locked.');
        }
        if ($run->status !== PayrollRun::STATUS_VALIDATED) {
            throw new PayrollAlreadyValidatedException('Run must be validated (RH step) before locking.');
        }

        $old = ['status' => $run->status];
        $run->update([
            'status' => PayrollRun::STATUS_LOCKED,
            'locked_by' => $validator->id,
            'locked_at' => now(),
        ]);
        $this->writeAudit($run, $validator, 'payroll_run_locked', $old, ['status' => PayrollRun::STATUS_LOCKED]);

        return $run->fresh();
    }

    /**
     * Déverrouillage motivé (retour à validated) — toute modification
     * post-clôture est tracée.
     *
     * @throws \RuntimeException
     */
    public function unlock(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException('Only a locked run can be unlocked.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('An unlock reason is mandatory (audit trail).');
        }

        $old = ['status' => $run->status];
        $run->update(['status' => PayrollRun::STATUS_VALIDATED]);
        $this->writeAudit($run, $actor, 'payroll_run_unlocked', $old, ['status' => PayrollRun::STATUS_VALIDATED], $reason);

        return $run->fresh();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function writeAudit(
        PayrollRun $run,
        Employee $actor,
        string $action,
        array $old,
        array $new,
        ?string $reason = null
    ): void {
        AuditLog::create([
            'company_id' => $run->company_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $run->getMorphClass(),
            'auditable_id' => $run->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($new),
            'metadata' => $reason !== null ? json_encode(['reason' => $reason]) : null,
        ]);
    }
}
