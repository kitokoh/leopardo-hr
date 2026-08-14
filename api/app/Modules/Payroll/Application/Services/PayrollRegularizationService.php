<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunNotLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DZ-DEPTH (#1818) — bulletins rétroactifs et régularisations.
 *
 * Permet de corriger une erreur de saisie sur un run déjà clôturé
 * (`locked`) en créant un run de régularisation lié (`type =
 * regularization`, `original_run_id` renseigné), sans JAMAIS modifier le
 * run original. Le run de régularisation suit ensuite le workflow standard
 * (draft → calculated → validated → locked → archivage Cabinet).
 *
 * Invariants :
 * - seul un run `locked` est régularisable (sinon PayrollRunNotLockedException) ;
 * - la création + l'écriture d'audit sont dans la même transaction ;
 * - chaque régularisation porte un motif obligatoire, tracé dans l'audit
 *   (`payroll_run_regularization_created` avec reason + original_run_id).
 */
class PayrollRegularizationService
{
    public const AUDIT_ACTION_REGULARIZATION_CREATED = 'payroll_run_regularization_created';

    /**
     * Crée un run de régularisation pour un run verrouillé.
     *
     * @throws PayrollRunNotLockedException si le run n'est pas verrouillé
     */
    public function createRegularization(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunNotLockedException;
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw new \RuntimeException('Un motif de régularisation est obligatoire (audit trail).');
        }

        return DB::transaction(function () use ($run, $actor, $trimmedReason): PayrollRun {
            $regularization = PayrollRun::query()->create([
                'company_id' => $run->company_id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'country_code' => $run->country_code,
                'status' => PayrollRun::STATUS_DRAFT,
                'type' => PayrollRun::TYPE_REGULARIZATION,
                'original_run_id' => $run->id,
                'reason' => $trimmedReason,
            ]);

            AuditLog::create([
                'company_id' => $run->company_id,
                'user_id' => $actor->id,
                'action' => self::AUDIT_ACTION_REGULARIZATION_CREATED,
                'auditable_type' => $run->getMorphClass(),
                'auditable_id' => $run->id,
                'old_values' => ['status' => PayrollRun::STATUS_LOCKED],
                'new_values' => [
                    'regularization_run_id' => $regularization->id,
                    'original_run_id' => $run->id,
                    'reason' => $trimmedReason,
                    'type' => PayrollRun::TYPE_REGULARIZATION,
                ],
                'metadata' => ['reason' => $trimmedReason, 'original_run_id' => $run->id],
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
}
