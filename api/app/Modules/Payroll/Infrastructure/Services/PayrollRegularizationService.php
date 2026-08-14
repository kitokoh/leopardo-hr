<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DZ-DEPTH (issue #1818) — bulletins rétroactifs et régularisations.
 *
 * Permet de corriger une erreur de saisie découverte APRÈS clôture (prime
 * oubliée, absence mal encodée…) sans toucher au run original (`locked`) :
 * un nouveau run `type = regularization` est créé sur la même période, en
 * `draft`, lié à l'original via `original_run_id` et motivé par une `reason`
 * obligatoire (audit `payroll_run_regularization_created`). Le run de
 * régularisation suit ensuite le workflow standard (draft → calculated →
 * validated → locked → archivage Cabinet) ; le PDF porte la mention
 * « BULLETIN DE RÉGULARISATION ».
 */
class PayrollRegularizationService
{
    /**
     * Crée un run de régularisation pour un run CLÔTURÉ (locked).
     *
     * @throws RuntimeException si le run n'est pas verrouillé ou sans motif
     */
    public function createRegularization(PayrollRun $run, Employee $actor, string $reason): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_LOCKED) {
            throw new RuntimeException('Seul un run clôturé (locked) peut être régularisé.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Un motif de régularisation est obligatoire (audit trail).');
        }

        return DB::transaction(function () use ($run, $actor, $reason): PayrollRun {
            $regularization = PayrollRun::create([
                'company_id' => $run->company_id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'country_code' => $run->country_code,
                'status' => PayrollRun::STATUS_DRAFT,
                'type' => PayrollRun::TYPE_REGULARIZATION,
                'original_run_id' => $run->id,
                'reason' => $reason,
            ]);

            AuditLog::create([
                'company_id' => $run->company_id,
                'user_id' => $actor->id,
                'action' => 'payroll_run_regularization_created',
                'auditable_type' => $run->getMorphClass(),
                'auditable_id' => $run->id,
                'old_values' => [],
                'new_values' => [
                    'regularization_run_id' => $regularization->id,
                    'reason' => $reason,
                ],
                'metadata' => [
                    'original_run_id' => $run->id,
                    'period_start' => $run->period_start->toDateString(),
                    'period_end' => $run->period_end->toDateString(),
                ],
            ]);

            return $regularization;
        });
    }

    /**
     * Liste les régularisations liées à un run.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PayrollRun>
     */
    public function regularizationsFor(PayrollRun $run)
    {
        return PayrollRun::query()
            ->where('original_run_id', $run->id)
            ->orderByDesc('id')
            ->get();
    }
}
