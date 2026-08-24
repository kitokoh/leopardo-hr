<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\ArchivePaySlipsToCabinetJob;
use App\Modules\Payroll\Application\Services\PayrollRegularizationService;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunHasRegularizationsException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNoSlipsException;
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
     * @throws PayrollRunLockedException si le run est verrouillé
     * @throws \RuntimeException si le run n'est pas en état calculé
     */
    /**
     * @throws PayrollRunLockedException
     * @throws PayrollAlreadyValidatedException
     * @throws PayrollRunNoSlipsException
     * @throws \RuntimeException statut inattendu
     */
    public function validateRh(PayrollRun $run, Employee $validator): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunLockedException;
        }
        if ($run->status === PayrollRun::STATUS_VALIDATED) {
            throw new PayrollAlreadyValidatedException;
        }
        if ($run->status !== PayrollRun::STATUS_CALCULATED) {
            throw new \RuntimeException('Un run doit être calculé avant validation RH (statut actuel : '.$run->status.').');
        }

        // Issue #1767 : interdire la validation d'un run à 0 bulletin (une
        // clôture comptable à zéro sans avertissement est une erreur RH).
        $this->assertHasPaySlips($run);

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
                $fresh = $run->fresh();
                throw $fresh?->status === PayrollRun::STATUS_LOCKED
                    ? new PayrollRunLockedException
                    : new PayrollAlreadyValidatedException('L\'état du run a changé entre la lecture et la validation.');
            }

            // Le run, ses bulletins et l’audit doivent être validés atomiquement.
            // Un échec de mise à jour des bulletins ne doit pas laisser un run
            // dans l’état `validated` avec des bulletins encore `calculated`.
            $run->paySlips()->update(['status' => 'validated']);

            $run->refresh();
            $this->writeAudit(
                $run,
                $validator,
                'payroll_run_validated',
                ['status' => PayrollRun::STATUS_CALCULATED],
                [
                    'status' => PayrollRun::STATUS_VALIDATED,
                    'validated_by' => $validator->id,
                    'validated_at' => $run->validated_at?->toISOString(),
                ]
            );

            return $run;
        });
    }

    /**
     * Étape 2 — clôture comptable : verrouille le run.
     *
     * @throws PayrollRunLockedException si le run est déjà verrouillé
     * @throws PayrollAlreadyValidatedException si le run n'est pas validé
     * @throws PayrollRunNoSlipsException si le run est vide
     * @throws \RuntimeException statut inattendu
     */
    public function lock(PayrollRun $run, Employee $validator): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunLockedException;
        }
        if ($run->status !== PayrollRun::STATUS_VALIDATED) {
            // #4310 : même classe pour un état distinct (« pas encore validé ») —
            // code d'erreur dédié pour un message localisé précis.
            throw new PayrollAlreadyValidatedException(
                'Un run doit être validé (étape RH) avant verrouillage comptable.',
                'PAYROLL_RUN_NOT_VALIDATED'
            );
        }

        // Issue #1767 : interdire la clôture comptable d'un run à 0 bulletin.
        $this->assertHasPaySlips($run);

        $lockedRun = DB::transaction(function () use ($run, $validator): PayrollRun {
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
                    ? new PayrollRunLockedException
                    : new \RuntimeException('L\'état du run a changé entre la lecture et le verrouillage.');
            }

            $run->refresh();
            $this->writeAudit(
                $run,
                $validator,
                'payroll_run_locked',
                ['status' => PayrollRun::STATUS_VALIDATED],
                [
                    'status' => PayrollRun::STATUS_LOCKED,
                    'locked_by' => $validator->id,
                    'locked_at' => $run->locked_at?->toISOString(),
                ]
            );

            return $run;
        });

        // Issue #1817 : archivage automatique des bulletins PDF dans le
        // Cabinet employé — dispatch APRÈS commit (le job lit l'état verrouillé).
        ArchivePaySlipsToCabinetJob::dispatch($lockedRun->id);

        return $lockedRun;
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

        // Issue #1942 : un original avec des régularisations actives ne peut
        // PAS être déverrouillé — l'invariant « l'original n'est jamais
        // modifié » (#1818) tomberait (le delta serait calculé sur un
        // original en cours de mutation).
        if ((new PayrollRegularizationService)->hasActiveRegularizations($run)) {
            throw new PayrollRunHasRegularizationsException;
        }

        return DB::transaction(function () use ($run, $actor, $reason): PayrollRun {
            // TOCTOU (#1942, revue lead) : le check « régularisation active »
            // doit vivre dans la MÊME transaction que l'update, APRÈS avoir
            // pris le verrou pessimiste sur la ligne — `createRegularization`
            // verrouille l'original via lockForUpdate, les deux chemins se
            // sérialisent ainsi sur la même ligne. Sans ce verrou, un
            // déverrouillage concurrent pouvait passer le check puis laisser
            // une régularisation active s'écrire sur un original « validated ».
            /** @var PayrollRun|null $lockedRun */
            $lockedRun = PayrollRun::query()
                ->lockForUpdate()
                ->find($run->id);

            if ($lockedRun === null) {
                throw new \RuntimeException('Le run original n\'existe plus.');
            }

            if ($lockedRun->status !== PayrollRun::STATUS_LOCKED) {
                throw $lockedRun->status === PayrollRun::STATUS_VALIDATED
                    ? new PayrollRunLockedException('Le run a déjà été déverrouillé.')
                    : new \RuntimeException('L\'état du run a changé entre la lecture et le déverrouillage.');
            }

            // Invariant « l'original n'est jamais modifié » (#1818) : un
            // original avec des régularisations actives ne peut PAS être
            // déverrouillé (le delta serait calculé sur un original en
            // cours de mutation).
            if ((new PayrollRegularizationService)->hasActiveRegularizations($lockedRun)) {
                throw new PayrollRunHasRegularizationsException;
            }

            // Capturer les valeurs pré-déverrouillage AVANT l'update : après
            // update/refresh, locked_by/locked_at sont null (l'audit trail
            // avant/après serait faux).
            $lockedBy = $lockedRun->locked_by;
            $lockedAt = $lockedRun->locked_at;

            $updated = PayrollRun::query()
                ->whereKey($run->id)
                ->where('status', PayrollRun::STATUS_LOCKED)
                ->update([
                    'status' => PayrollRun::STATUS_VALIDATED,
                    'locked_by' => null,
                    'locked_at' => null,
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('L\'état du run a changé entre la lecture et le déverrouillage.');
            }

            $run->refresh();
            $this->writeAudit(
                $run,
                $actor,
                'payroll_run_unlocked',
                ['status' => PayrollRun::STATUS_LOCKED, 'locked_by' => $lockedBy, 'locked_at' => $lockedAt?->toISOString()],
                ['status' => PayrollRun::STATUS_VALIDATED, 'locked_by' => null, 'locked_at' => null],
                $reason
            );

            return $run;
        });
    }

    /**
     * Issue #1767 : un run sans aucun bulletin ne doit jamais être validé ni
     * verrouillé (clôture comptable à zéro sans avertissement).
     */
    private function assertHasPaySlips(PayrollRun $run): void
    {
        if ($run->paySlips()->count() === 0) {
            throw new PayrollRunNoSlipsException;
        }
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
