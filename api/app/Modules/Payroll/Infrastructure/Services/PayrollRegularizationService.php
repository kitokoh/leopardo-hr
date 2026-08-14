<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * Issue #1818 — bulletins rétroactifs et régularisations.
 *
 * Un run clôturé (locked) peut être corrigé en créant un run de
 * régularisation : même période, même pays, `type = regularization`,
 * `original_run_id` renseigné, motif tracé — SANS modifier le run original.
 *
 * Le run de régularisation suit le workflow standard (draft → calculated →
 * validated → locked → archivage Cabinet #1817). Les structures salariales
 * étant scopées à l'entreprise (company_id + country_code), le correctif se
 * fait en ajustant structures/composants avant le calcul du run de
 * régularisation.
 */
class PayrollRegularizationService
{
    /**
     * @throws \RuntimeException si le run n'est pas verrouillé (clôture faite)
     */
    public function createRegularization(PayrollRun $original, Employee $actor, ?string $reason = null): PayrollRun
    {
        if ($original->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException('Seul un run verrouillé (clôture comptable) est régularisable.');
        }

        $regularization = PayrollRun::query()->create([
            'company_id' => $original->company_id,
            'period_start' => $original->period_start,
            'period_end' => $original->period_end,
            'country_code' => $original->country_code,
            'status' => PayrollRun::STATUS_DRAFT,
            'type' => PayrollRun::TYPE_REGULARIZATION,
            'original_run_id' => $original->id,
            'reason' => $reason,
            'notes' => $reason !== null ? "Régularisation du run #{$original->id} : {$reason}" : null,
        ]);

        AuditLog::create([
            'company_id' => $original->company_id,
            'user_id' => $actor->id,
            'action' => 'payroll_run_regularization_created',
            'auditable_type' => $regularization->getMorphClass(),
            'auditable_id' => $regularization->id,
            'old_values' => null,
            'new_values' => [
                'original_run_id' => $original->id,
                'period_start' => $original->period_start->toDateString(),
                'period_end' => $original->period_end->toDateString(),
            ],
            'metadata' => [
                'reason' => $reason,
                'original_run_id' => $original->id,
                'actor_id' => $actor->id,
            ],
        ]);

        return $regularization;
    }
}
