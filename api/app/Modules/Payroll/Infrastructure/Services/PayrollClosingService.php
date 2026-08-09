<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

/**
 * Programme FOCUS — F-11 : clôture de paie en 2 étapes + verrouillage + audit.
 *
 * Flux : draft → calculated (calcul) → validated (RH) → locked (comptable).
 * Après verrouillage : tout recalcul est refusé (PayrollRunLockedException) ;
 * un déverrouillage motivé est nécessaire, et il est tracé.
 *
 * Invariants :
 * - validateRh() n'accepte qu'un run `calculated` (jamais draft/paid/cancelled).
 * - lock() est une mise à jour conditionnelle atomique (status = validated),
 *   aucune course (TOCTOU) ne permet de verrouiller deux fois ni de verrouiller
 *   un run dont l'état a changé entre la lecture et l'écriture.
 * - update + écriture d'audit sont dans la même transaction : aucune
 *   modification non tracée.
 * - les colonnes JSON d'audit reçoivent de vrais tableaux (casts `array`),
 *   jamais de json_encode() (double-encodage).
 */
class PayrollClosingService
{
    /**
     * Étape 1 — validation RH (contrôle des montants avant clôture comptable).
     *
     * @throws PayrollAlreadyValidatedException si le run est déjà validé
     * @throws PayrollRunLockedException        si le run est verrouillé
     * @throws \RuntimeException                si le run n'est pas en état calculé
     */
    public function validateRh(PayrollRun $run, Employee $validator): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunLockedException();
        }
        if ($run->status === PayrollRun::STATUS_VALIDATED) {
            throw new PayrollAlreadyValidatedException();
        }
        if ($run->status !== PayrollRun::STATUS_CALCULATED) {
            throw new \RuntimeException('Un run doit être calculé avant validation RH (statut actuel : '.$run->status.').');
        }

        return DB::transaction(function () use ($run, $validator): PayrollRun {
            $updated = PayrollRun::query()
                ->whereKey($run->id)
                ->where('status', PayrollRun::STATUS_CALCULATED)
                ->update([
                    'status' => PayrollRun::STATUS_VALIDATED,
                    'validated_by' => $validator->id,
                    'validated_at' => now(),
                ]);

            if ($updated === 0) {
                throw new PayrollRunLockedException('L\'état du run a changé entre la lecture et la validation.');
            }

            $fresh = $run->fresh() ?? $run;
            $this->writeAudit(
                $fresh,
                $validator,
                'payroll_run_validated',
                ['status' => PayrollRun::STATUS_CALCULATED],
                [
                    'status' => PayrollRun::STATUS_VALIDATED,
                    'validated_by' => $validator->id,
                    'validated_at' => $fresh->validated_at?->toISOString(),
                ]
            );

            return $fresh;
        });
    }

    /**
     * Étape 2 — clôture comptable : verrouille le run.
     *
     * @throws PayrollRunLockedException si le run est déjà verrouillé
     * @throws PayrollAlreadyValidatedException si le run n'est pas validé
     */
    public function lock(PayrollRun $run, Employee $validator): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunLockedException();
        }
        if ($run->status !== PayrollRun::STATUS_VALIDATED) {
            throw new PayrollAlreadyValidatedException('Un run doit être validé (étape RH) avant verrouillage comptable.');
        }

        return DB::transaction(function () use ($run, $validator): PayrollRun {
            $updated = PayrollRun::query()
                ->whereKey($run->id)
                ->where('status', PayrollRun::STATUS_VALIDATED)
                ->update([
                    'status' => PayrollRun::STATUS_LOCKED,
                    'locked_by' => $validator->id,
                    'locked_at' => now(),
                ]);

            if ($updated === 0) {
                $fresh = $run->fresh();
                throw $fresh?->status === PayrollRun::STATUS_LOCKED
                    ? new PayrollRunLockedException()
                    : new \RuntimeException('L\'état du run a changé entre la lecture et le verrouillage.');
            }

            $fresh = $run->fresh() ?? $run;
            $this->writeAudit(
                $fresh,
                $validator,
                'payroll_run_locked',
                ['status' => PayrollRun::STATUS_VALIDATED],
                [
                    'status' => PayrollRun::STATUS_LOCKED,
                    'locked_by' => $validator->id,
                    'locked_at' => $fresh->locked_at?->toISOString(),
                ]
            );

            return $fresh;
        });
    }

    /**
     * Déverrouillage motivé (retour à validated) — toute modification
     * post-clôture est tracée. Efface les métadonnées de verrouillage.
     *
     * @throws \RuntimeException si le run n'est pas verrouillé ou si la raison est vide
     */
    public function unlock(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException('Seul un run verrouillé peut être déverrouillé.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('Une raison de déverrouillage est obligatoire (audit trail).');
        }

        return DB::transaction(function () use ($run, $actor, $reason): PayrollRun {
            $updated = PayrollRun::query()
                ->whereKey($run->id)
                ->where('status', PayrollRun::STATUS_LOCKED)
                ->update([
                    'status' => PayrollRun::STATUS_VALIDATED,
                    'locked_by' => null,
                    'locked_at' => null,
                ]);

            if ($updated === 0) {
                throw new PayrollRunLockedException('L\'état du run a changé entre la lecture et le déverrouillage.');
            }

            $fresh = $run->fresh() ?? $run;
            $this->writeAudit(
                $fresh,
                $actor,
                'payroll_run_unlocked',
                ['status' => PayrollRun::STATUS_LOCKED],
                ['status' => PayrollRun::STATUS_VALIDATED],
                $reason
            );

            return $fresh;
        });
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
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => $reason !== null ? ['reason' => $reason] : null,
        ]);
    }
}
