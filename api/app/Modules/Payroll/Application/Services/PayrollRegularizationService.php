<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\PayrollRegularizationAlreadyExistsException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunHasRegularizationsException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNotLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DZ-DEPTH (#1818) — bulletins rétroactifs et régularisations.
 *
 * Permet de corriger une erreur de saisie sur un run déjà clôturé
 * (`locked`) ou déjà payé (`paid`) en créant un run de régularisation lié
 * (`type = regularization`, `original_run_id` renseigné), sans JAMAIS
 * modifier le run original. Le run de régularisation suit ensuite le
 * workflow standard (draft → calculated → validated → locked → archivage
 * Cabinet).
 *
 * Invariants (#1942) :
 * - seuls `locked` et `paid` sont régularisables (sinon
 *   PayrollRunNotLockedException) — le cas d'usage est « déjà payé » ;
 * - un run de régularisation n'est PAS régularisable (pas de chaîne) ;
 * - UNE SEULE régularisation ACTIVE par original : verrou pessimiste
 *   (`lockForUpdate`) + garde explicite + index unique partiel
 *   (`payroll_runs_original_active_unique`) en backstop — double-clic ou
 *   requêtes concurrentes → 422, jamais 2 runs ;
 * - la création + l'écriture d'audit sont dans la même transaction ;
 * - chaque régularisation porte un motif obligatoire, tracé dans l'audit
 *   (`payroll_run_regularization_created` avec reason + original_run_id).
 */
class PayrollRegularizationService
{
    public const AUDIT_ACTION_REGULARIZATION_CREATED = 'payroll_run_regularization_created';

    /**
     * Statuts d'un run original acceptant une régularisation.
     */
    private const REGULARIZABLE_STATUSES = [
        PayrollRun::STATUS_LOCKED,
        PayrollRun::STATUS_PAID,
    ];

    /**
     * Crée un run de régularisation pour un run verrouillé ou payé.
     *
     * @throws PayrollRunNotLockedException si le run n'est pas locked/paid
     * @throws PayrollRegularizationAlreadyExistsException si une régularisation active existe
     * @throws PayrollRunHasRegularizationsException si le run est lui-même une régularisation
     */
    public function createRegularization(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        if ($run->type === PayrollRun::TYPE_REGULARIZATION) {
            throw new PayrollRunHasRegularizationsException;
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new \RuntimeException('Un motif de régularisation est obligatoire (audit trail).');
        }

        return DB::transaction(function () use ($run, $actor, $trimmedReason): PayrollRun {
            // Verrou pessimiste : le run original ne doit pas changer d'état
            // (unlock/cancel concurrent) pendant la création (TOCTOU #1942).
            /** @var PayrollRun|null $lockedRun */
            $lockedRun = PayrollRun::query()
                ->lockForUpdate()
                ->find($run->id);

            if ($lockedRun === null) {
                throw new \RuntimeException('Le run original n\'existe plus.');
            }

            if (! in_array($lockedRun->status, self::REGULARIZABLE_STATUSES, true)) {
                throw new PayrollRunNotLockedException;
            }

            // Garde explicite (message propre) — l'index unique partiel reste
            // le backstop pour les requêtes concurrentes.
            $activeExists = PayrollRun::query()
                ->where('original_run_id', $lockedRun->id)
                ->where('type', PayrollRun::TYPE_REGULARIZATION)
                ->whereNotIn('status', [PayrollRun::STATUS_CANCELLED, PayrollRun::STATUS_ERROR])
                ->exists();

            if ($activeExists) {
                throw new PayrollRegularizationAlreadyExistsException;
            }

            $regularization = PayrollRun::query()->create([
                'company_id' => $lockedRun->company_id,
                'period_start' => $lockedRun->period_start,
                'period_end' => $lockedRun->period_end,
                'country_code' => $lockedRun->country_code,
                'status' => PayrollRun::STATUS_DRAFT,
                'type' => PayrollRun::TYPE_REGULARIZATION,
                'original_run_id' => $lockedRun->id,
                'reason' => $trimmedReason,
            ]);

            AuditLog::create([
                'company_id' => $lockedRun->company_id,
                'user_id' => $actor->id,
                'action' => self::AUDIT_ACTION_REGULARIZATION_CREATED,
                'auditable_type' => $lockedRun->getMorphClass(),
                'auditable_id' => $lockedRun->id,
                'old_values' => ['status' => $lockedRun->status],
                'new_values' => [
                    'regularization_run_id' => $regularization->id,
                    'original_run_id' => $lockedRun->id,
                    'reason' => $trimmedReason,
                    'type' => PayrollRun::TYPE_REGULARIZATION,
                ],
                'metadata' => ['reason' => $trimmedReason, 'original_run_id' => $lockedRun->id],
            ]);

            return $regularization;
        });
    }

    /**
     * Liste les runs de régularisation liés à un run.
     *
     * @return Collection<int, PayrollRun>
     */
    public function regularizations(PayrollRun $run): Collection
    {
        return PayrollRun::query()
            ->where('original_run_id', $run->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * True si le run porte au moins une régularisation ACTIVE.
     */
    public function hasActiveRegularizations(PayrollRun $run): bool
    {
        return PayrollRun::query()
            ->where('original_run_id', $run->id)
            ->where('type', PayrollRun::TYPE_REGULARIZATION)
            ->whereNotIn('status', [PayrollRun::STATUS_CANCELLED, PayrollRun::STATUS_ERROR])
            ->exists();
    }
}
