<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issue #5832 (EDU-016) — règles métier des frais scolaires.
 *
 * - Idempotence : `external_id` déjà enregistré (même tenant) renvoie la
 *   ressource existante (rejeu sûr des intégrations) ;
 * - Non-surdébit : Σ encaissements ≤ montant de la charge
 *   (EDU_FEE_OVERPAYMENT) ; devise de l'encaissement = devise de la charge
 *   (EDU_FEE_CURRENCY_MISMATCH) ;
 * - Transitions : pending → partial → paid ; pending/partial → waived ou
 *   cancelled ; états terminaux verrouillés (EDU_FEE_TERMINAL) ;
 * - Contrat Accounting : lignes équilibrées régénérées idempotemment
 *   (EduAccountingEntryService) à chaque facturation/encaissement/abandon ;
 * - Événements versionnés : publiés dans l'outbox APRÈS le commit
 *   (EduOutboxPublisher) — edu.fee.charge.created.v1,
 *   edu.fee.payment.recorded.v1, edu.fee.charge.waived.v1,
 *   edu.fee.charge.cancelled.v1.
 */
final class EduFeeService
{
    public const EVENT_CHARGE_CREATED = 'edu.fee.charge.created.v1';

    public const EVENT_PAYMENT_RECORDED = 'edu.fee.payment.recorded.v1';

    public const EVENT_CHARGE_WAIVED = 'edu.fee.charge.waived.v1';

    public const EVENT_CHARGE_CANCELLED = 'edu.fee.charge.cancelled.v1';

    public function __construct(
        private readonly EduAccountingEntryService $entries,
        private readonly EduOutboxPublisher $outbox,
    ) {
    }

    /**
     * Création d'un type de frais (code unique par tenant).
     *
     * @param  array<string, mixed>  $data
     */
    public function createFeeType(Employee $actor, array $data): EduFeeType
    {
        $payload = array_merge($data, [
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]);

        try {
            /** @var EduFeeType $feeType */
            $feeType = EduFeeType::query()->create($payload);

            return $feeType;
        } catch (UniqueConstraintViolationException) {
            abort(422, 'EDU_FEE_TYPE_CODE_TAKEN');
        }
    }

    /**
     * Facturation idempotente d'un frais à un élève.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCharge(Employee $actor, array $data): EduFeeCharge
    {
        $payload = array_merge($data, [
            'company_id' => $actor->company_id,
            'charged_by' => $actor->id,
            'status' => EduFeeCharge::STATUS_PENDING,
        ]);

        if (! empty($data['external_id'])) {
            $existing = EduFeeCharge::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $data['external_id'])
                ->first();

            if ($existing instanceof EduFeeCharge) {
                return $existing;
            }
        }

        /** @var EduFeeCharge $charge */
        $charge = DB::transaction(function () use ($payload): EduFeeCharge {
            /** @var EduFeeCharge $charge */
            $charge = EduFeeCharge::query()->create($payload);

            $this->entries->generateForCharge($charge);

            return $charge;
        });

        $this->outbox->publish($actor->company_id, self::EVENT_CHARGE_CREATED, [
            'charge_id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->getAttribute('student_id'),
            'fee_type_id' => (int) $charge->getAttribute('fee_type_id'),
            'amount' => $this->amount($charge->amount),
            'currency' => $charge->currency,
        ], 'charge-'.(int) $charge->getAttribute('id'));

        return $charge;
    }

    /**
     * Encaissement idempotent sur une charge (non-surdébit garanti).
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Employee $actor, EduFeeCharge $charge, array $data): EduFeePayment
    {
        if ($charge->company_id !== $actor->company_id) {
            throw new RuntimeException('Charge does not belong to tenant.');
        }

        abort_if($charge->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        $currency = (string) ($data['currency'] ?? $charge->currency);
        abort_if($currency !== $charge->currency, 422, 'EDU_FEE_CURRENCY_MISMATCH');

        if (! empty($data['external_id'])) {
            $existing = EduFeePayment::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $data['external_id'])
                ->first();

            if ($existing instanceof EduFeePayment) {
                return $existing;
            }
        }

        $amount = $this->amount((string) $data['amount']);
        $paid = $this->amount((string) $charge->payments()->sum('amount'));

        abort_if($amount <= 0, 422, 'EDU_FEE_AMOUNT_POSITIVE');
        abort_if($paid + $amount > $this->amount($charge->amount) + 0.004, 422, 'EDU_FEE_OVERPAYMENT');

        /** @var EduFeePayment $payment */
        $payment = DB::transaction(function () use ($actor, $charge, $data, $amount, $paid): EduFeePayment {
            /** @var EduFeePayment $payment */
            $payment = EduFeePayment::query()->create([
                'company_id' => $actor->company_id,
                'fee_charge_id' => (int) $charge->getAttribute('id'),
                'amount' => $amount,
                'currency' => $charge->currency,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'external_id' => $data['external_id'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'recorded_by' => $actor->id,
            ]);

            $total = $paid + $amount;
            $charge->update([
                'status' => $total >= $this->amount($charge->amount) - 0.004
                    ? EduFeeCharge::STATUS_PAID
                    : EduFeeCharge::STATUS_PARTIAL,
            ]);

            $this->entries->generateForPayment($payment);

            return $payment;
        });

        $this->outbox->publish($actor->company_id, self::EVENT_PAYMENT_RECORDED, [
            'payment_id' => (int) $payment->getAttribute('id'),
            'charge_id' => (int) $charge->getAttribute('id'),
            'amount' => $amount,
            'currency' => $charge->currency,
            'method' => $payment->method,
        ], 'payment-'.(int) $payment->getAttribute('id'));

        return $payment;
    }

    /**
     * Abandon d'une charge (annulation de la créance restante).
     */
    public function waive(Employee $actor, EduFeeCharge $charge): EduFeeCharge
    {
        if ($charge->company_id !== $actor->company_id) {
            throw new RuntimeException('Charge does not belong to tenant.');
        }

        abort_if($charge->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        DB::transaction(function () use ($charge, $actor): void {
            $charge->update(['status' => EduFeeCharge::STATUS_WAIVED]);
            $this->entries->generateForWaiver($charge, $actor);
        });

        $this->outbox->publish($actor->company_id, self::EVENT_CHARGE_WAIVED, [
            'charge_id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->getAttribute('student_id'),
            'amount' => $this->amount($charge->amount),
            'currency' => $charge->currency,
        ], 'waive-'.(int) $charge->getAttribute('id'));

        return $charge->refresh();
    }

    /**
     * Annulation d'une charge (aucun encaissement préalable exigé).
     */
    public function cancel(Employee $actor, EduFeeCharge $charge): EduFeeCharge
    {
        if ($charge->company_id !== $actor->company_id) {
            throw new RuntimeException('Charge does not belong to tenant.');
        }

        abort_if($charge->isTerminal(), 422, 'EDU_FEE_TERMINAL');

        DB::transaction(function () use ($charge): void {
            $charge->update(['status' => EduFeeCharge::STATUS_CANCELLED]);
        });

        $this->outbox->publish($actor->company_id, self::EVENT_CHARGE_CANCELLED, [
            'charge_id' => (int) $charge->getAttribute('id'),
            'student_id' => (int) $charge->getAttribute('student_id'),
        ], 'cancel-'.(int) $charge->getAttribute('id'));

        return $charge->refresh();
    }

    private function amount(string $amount): float
    {
        return round((float) $amount, 2);
    }
}
