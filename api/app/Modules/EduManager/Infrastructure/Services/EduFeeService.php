<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Exceptions\EduFeeException;
use App\Modules\EduManager\Domain\Models\EduFee;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduFeeType;

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
     * Événements d'outbox du flux v2 (facturation au guichet).
     */
    public const EVENT_CHARGE_CREATED = 'edu.fee.charge.created.v1';

    public const EVENT_PAYMENT_RECORDED = 'edu.fee.payment.recorded.v1';

    public const EVENT_CHARGE_WAIVED = 'edu.fee.charge.waived.v1';

    public function __construct(
        private readonly EduAccountingEntryService $entries,
        private readonly EduOutboxPublisher $outbox,
    ) {}

    /**
     * Facture un frais à un élève (idempotent sur `external_id` par tenant).
     *
     * Le montant est figé à la création (copie du tarif du type de frais) pour
     * une traçabilité comptable stable : modifier le catalogue ensuite ne
     * réécrit pas l'historique. Écrit les deux lignes équilibrées
     * (411 Clients / 706 Prestations) puis publie l'événement d'outbox APRÈS
     * le commit.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCharge(Employee $actor, array $data): EduFeeCharge
    {
        $companyId = (string) $actor->company_id;
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;

        if ($externalId !== null && $externalId !== '') {
            $existing = EduFeeCharge::query()
                ->where('company_id', $companyId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof EduFeeCharge) {
                return $existing; // rejeu → même charge, aucune double facturation
            }
        }

        /** @var EduFeeType $feeType */
        $feeType = EduFeeType::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $data['fee_type_id']);

        /** @var EduFeeCharge $charge */
        $charge = EduFeeCharge::query()->create([
            'company_id' => $companyId,
            'student_id' => (int) $data['student_id'],
            'fee_type_id' => (int) $feeType->getAttribute('id'),
            'academic_year_id' => (int) $data['academic_year_id'],
            'amount' => $data['amount'] ?? $feeType->amount,
            'currency' => $data['currency'] ?? $feeType->currency,
            'status' => EduFeeCharge::STATUS_PENDING,
            'due_date' => $data['due_date'] ?? null,
            'external_id' => $externalId,
            'charged_by' => $actor->id,
        ]);

        $this->entries->generateForCharge($charge, $actor);

        $this->outbox->publish($companyId, self::EVENT_CHARGE_CREATED, [
            'charge_id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->student_id,
            'amount' => (float) $charge->amount,
            'currency' => (string) $charge->currency,
        ]);

        return $charge;
    }

    /**
     * Encaisse un paiement sur une charge.
     *
     * Idempotent sur `external_id` (rejeu → même paiement, AUCUN doublon) ;
     * refuse le surdébit (EDU_FEE_OVERPAYMENT) et toute écriture sur une
     * charge terminale (EDU_FEE_TERMINAL). Le statut suit le cumul encaissé :
     * `pending` → `partial` → `paid`.
     *
     * @param  array<string, mixed>  $data
     * @return array{payment: EduFeePayment, charge: EduFeeCharge}
     */
    public function recordPayment(Employee $actor, EduFeeCharge $charge, array $data): array
    {
        abort_if($charge->company_id !== $actor->company_id, 404);

        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;

        // Rejeu AVANT le contrôle terminal : un encaissement déjà enregistré
        // reste consultable/idempotent même une fois la charge soldée.
        if ($externalId !== null && $externalId !== '') {
            $existing = EduFeePayment::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof EduFeePayment) {
                return ['payment' => $existing, 'charge' => $charge->refresh()];
            }
        }

        if ($charge->isTerminal()) {
            throw EduFeeException::terminal();
        }

        $amount = round((float) $data['amount'], 2);
        $due = round((float) $charge->amount, 2);
        $alreadyPaid = round((float) $charge->payments()->sum('amount'), 2);
        $remaining = round($due - $alreadyPaid, 2);

        if ($amount > $remaining + 0.004) {
            throw EduFeeException::overpayment();
        }

        $currency = (string) ($data['currency'] ?? $charge->currency);

        if (strtoupper($currency) !== strtoupper((string) $charge->currency)) {
            throw EduFeeException::currencyMismatch();
        }

        /** @var EduFeePayment $payment */
        $payment = EduFeePayment::query()->create([
            'company_id' => $actor->company_id,
            'fee_charge_id' => (int) $charge->getAttribute('id'),
            'amount' => $amount,
            'currency' => $currency,
            'method' => (string) $data['method'],
            'reference' => $data['reference'] ?? null,
            'external_id' => $externalId,
            'paid_at' => $data['paid_at'] ?? now(),
            'recorded_by' => $actor->id,
        ]);

        $settled = $alreadyPaid + $amount >= $due - 0.004;

        $charge->update([
            'status' => $settled ? EduFeeCharge::STATUS_PAID : EduFeeCharge::STATUS_PARTIAL,
        ]);

        $this->entries->generateForPayment($payment, $actor);

        $this->outbox->publish((string) $actor->company_id, self::EVENT_PAYMENT_RECORDED, [
            'charge_id' => (int) $charge->getAttribute('id'),
            'payment_id' => (int) $payment->getAttribute('id'),
            'amount' => $amount,
            'currency' => $currency,
            'method' => (string) $data['method'],
        ]);

        return ['payment' => $payment, 'charge' => $charge->refresh()];
    }

    /**
     * Abandonne le solde restant d'une charge (créance irrécouvrable).
     *
     * Terminale : une charge déjà soldée/abandonnée/annulée est refusée.
     */
    public function waiveCharge(Employee $actor, EduFeeCharge $charge): EduFeeCharge
    {
        abort_if($charge->company_id !== $actor->company_id, 404);

        if ($charge->isTerminal()) {
            throw EduFeeException::terminal();
        }

        $charge->update(['status' => EduFeeCharge::STATUS_WAIVED]);

        $this->entries->generateForWaiver($charge->refresh(), $actor);

        $this->outbox->publish((string) $actor->company_id, self::EVENT_CHARGE_WAIVED, [
            'charge_id' => (int) $charge->getAttribute('id'),
            'amount' => (float) $charge->amount,
            'currency' => (string) $charge->currency,
        ]);

        return $charge->refresh();
    }

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
     * Annulation (idempotente, terminale).
     */
    public function cancel(Employee $actor, EduFee $fee): EduFee
    {
        abort_if($fee->company_id !== $actor->company_id, 404);
        abort_if($fee->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        $fee->update(['status' => EduFee::STATUS_CANCELLED]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.fee.cancelled',
            'module' => 'edu',
            'auditable_type' => $fee->getMorphClass(),
            'auditable_id' => $fee->getAttribute('id'),
            'new_values' => ['label' => $fee->label],
        ]);

        return $fee->refresh();
    }

    /**
     * Remise (idempotente, terminale) — direction uniquement.
     */
    public function waive(Employee $actor, EduFee $fee): EduFee
    {
        abort_if($fee->company_id !== $actor->company_id, 404);
        abort_if($fee->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        $fee->update(['status' => EduFee::STATUS_WAIVED]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.fee.waived',
            'module' => 'edu',
            'auditable_type' => $fee->getMorphClass(),
            'auditable_id' => $fee->getAttribute('id'),
            'new_values' => ['label' => $fee->label, 'amount' => $fee->amount],
        ]);

        return $fee->refresh();
    }
}
