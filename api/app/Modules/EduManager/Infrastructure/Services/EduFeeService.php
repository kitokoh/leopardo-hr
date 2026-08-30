<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFee;

/**
 * Règles métier des frais scolaires — EDU-016 (issue #5832).
 *
 * - Création idempotente : `external_reference` unique par tenant (rejeu).
 * - Règlement idempotent : un frais déjà payé ne change pas (traçage
 *   `payment_reference` + `paid_at`), terminal (paid/waived/cancelled) → 422.
 * - CONTRAT ACCOUNTING : EduManager ne crée AUCUNE écriture comptable ;
 *   `EduFee` est le read model consommé par Accounting (contrat documenté
 *   `docs/architecture/EDUMANAGER_ACCOUNTING_CONTRAT.md`).
 * - Isolation tenant : frais d'une autre compagnie → 404.
 */
final class EduFeeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $actor, array $data): EduFee
    {
        $payload = array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'status' => $data['status'] ?? EduFee::STATUS_PENDING,
        ]);

        if (! empty($data['external_reference'])) {
            $existing = EduFee::query()
                ->where('company_id', $actor->company_id)
                ->where('external_reference', $data['external_reference'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        /** @var EduFee $fee */
        $fee = EduFee::query()->create($payload);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.fee.created',
            'module' => 'edu',
            'auditable_type' => $fee->getMorphClass(),
            'auditable_id' => $fee->getAttribute('id'),
            'new_values' => ['label' => $fee->label, 'amount' => $fee->amount],
        ]);

        return $fee;
    }

    /**
     * Règlement idempotent d'un frais (aucune écriture comptable ici).
     *
     * @param  array<string, mixed>  $data
     */
    public function markPaid(Employee $actor, EduFee $fee, array $data): EduFee
    {
        abort_if($fee->company_id !== $actor->company_id, 404);
        abort_if($fee->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        $fee->update([
            'status' => EduFee::STATUS_PAID,
            'payment_reference' => $data['payment_reference'] ?? null,
            'paid_at' => now(),
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.fee.paid',
            'module' => 'edu',
            'auditable_type' => $fee->getMorphClass(),
            'auditable_id' => $fee->getAttribute('id'),
            'new_values' => ['payment_reference' => $fee->payment_reference],
        ]);

        return $fee->refresh();
    }

    /**
     * Annulation/remise (idempotente, terminale).
     */
    public function cancel(Employee $actor, EduFee $fee): EduFee
    {
        abort_if($fee->company_id !== $actor->company_id, 404);
        abort_if($fee->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        $fee->update(['status' => EduFee::STATUS_CANCELLED]);

        return $fee->refresh();
    }
}
