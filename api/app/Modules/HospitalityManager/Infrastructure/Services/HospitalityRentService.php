<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Modules\HospitalityManager\Domain\Exceptions\HospitalityInvalidLeaseTransitionException;
use App\Modules\HospitalityManager\Domain\Models\HospitalityLease;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRentPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HOSP-005 (#7947, BC-32) — Gestion locative : baux, génération d'échéances,
 * encaissement, calcul du retard.
 *
 * Décisions de comportement (D-7947, docs/decisions/7947-hospitality-rentals.md) :
 *
 *  1. **`billing_day` est ramené au dernier jour du mois**, jamais reporté au
 *     mois suivant. Un bail « au 31 » échu en février tombe le 28 (ou 29) —
 *     sinon l'échéance de février glisserait au 3 mars et le locataire
 *     apparaîtrait en retard sur un mois qui n'a jamais eu de 31.
 *
 *  2. **La génération est idempotente par construction**, pas par précaution :
 *     UNIQUE (lease_id, period) en base + `firstOrCreate`. Rejouer
 *     `generate-periods` ne crée aucun doublon et ne **réécrit pas** une
 *     échéance déjà partiellement payée (le montant dû ne bouge plus après
 *     le premier encaissement).
 *
 *  3. **`late` est DÉRIVÉ, jamais saisi.** Une échéance devient `late` quand
 *     `due_on` est dépassée et qu'elle n'est pas soldée. C'est recalculé à
 *     chaque lecture/encaissement (`refreshLateStatus`), pas seulement par la
 *     commande planifiée — sinon une échéance lue juste après l'échéance
 *     afficherait encore `due` alors que le calcul est déterministe.
 *
 *  4. **Un encaissement ne peut pas dépasser le dû.** Le surplus est refusé
 *     (422) plutôt que tronqué : accepter 1000 sur une échéance de 800
 *     créerait un crédit invisible que rien ne restituerait.
 *
 *  5. **Un bail résilié ne génère plus d'échéances** au-delà de sa date de
 *     fin : `generatePeriods` s'arrête à `ends_on` quand elle existe.
 */
final class HospitalityRentService
{
    /**
     * Termine un bail (`ended` = arrivé à son terme, `terminated` =
     * résiliation anticipée). Transition gardée : un bail déjà terminal
     * refuse toute nouvelle transition (409 INVALID_LEASE_TRANSITION).
     */
    public function terminate(HospitalityLease $lease, string $status, ?string $terminatedOn = null): HospitalityLease
    {
        if (! $lease->canTransitionTo($status)) {
            throw new HospitalityInvalidLeaseTransitionException($lease->status, $status);
        }

        $lease->forceFill([
            'status' => $status,
            'terminated_on' => $terminatedOn ?? Carbon::now()->toDateString(),
            'version' => $lease->version + 1,
        ])->save();

        return $lease->refresh();
    }

    /**
     * Génère les échéances `due` de `$from` (par défaut : début du bail) à
     * `$until` (période YYYY-MM incluse).
     *
     * Idempotent : les périodes déjà présentes sont laissées INTACTES.
     *
     * @return array{created: int, skipped: int}
     */
    public function generatePeriods(HospitalityLease $lease, string $until): array
    {
        $untilDate = Carbon::parse($until.'-01')->endOfMonth();

        // On ne génère jamais au-delà de la fin du bail : un bail résilié au
        // 30/06 ne doit pas produire d'échéance de juillet.
        $ceiling = $lease->ends_on !== null && $lease->ends_on->lt($untilDate)
            ? $lease->ends_on->copy()
            : $untilDate;

        // Un bail qui n'a pas encore commencé ne génère rien.
        if ($lease->starts_on->gt($ceiling)) {
            return ['created' => 0, 'skipped' => 0];
        }

        $cursor = $lease->starts_on->copy()->startOfMonth();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($lease, $cursor, $ceiling, &$created, &$skipped): void {
            while ($cursor->lte($ceiling)) {
                $period = $cursor->format('Y-m');

                $existing = HospitalityRentPayment::query()
                    ->where('lease_id', $lease->getKey())
                    ->where('period', $period)
                    ->exists();

                if ($existing) {
                    $skipped++;
                } else {
                    HospitalityRentPayment::query()->create([
                        'company_id' => $lease->company_id,
                        'lease_id' => $lease->getKey(),
                        'property_id' => $lease->property_id,
                        'unit_id' => $lease->unit_id,
                        'period' => $period,
                        'due_on' => $this->dueDateFor($period, $lease->billing_day),
                        'amount_minor' => $lease->monthly_rent_minor,
                        'paid_amount_minor' => 0,
                        'currency' => $lease->currency,
                        'status' => HospitalityRentPayment::STATUS_DUE,
                    ]);

                    $created++;
                }

                // ⚠ L'avancement du curseur est INCONDITIONNEL, et se fait ICI —
                // jamais dans une branche. Première écriture de ce service : un
                // `continue` sur la branche « période déjà présente » sautait
                // l'incrément, donc le SECOND passage de `generate-periods`
                // bouclait indéfiniment sur la première période existante (le
                // test d'idempotence ne rendait jamais la main). Le bug était
                // dans le chemin exact que l'issue demande de garantir.
                //
                // `copy()` + réaffectation : `addMonthNoOverflow()` mute en
                // place sur `Carbon` mais renvoie une NOUVELLE instance sur
                // `CarbonImmutable` — sans réaffectation le curseur resterait
                // figé si le cast `date` devenait immutable.
                $cursor = $cursor->copy()->addMonthNoOverflow();
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Enregistre un encaissement (partiel ou total).
     *
     * Le montant encaissé s'AJOUTE au cumul : deux encaissements de 300 sur
     * 1000 donnent bien 600, pas 300.
     *
     * @throws \App\Exceptions\DomainException 422 quand le montant dépasse le dû
     */
    public function recordPayment(
        HospitalityRentPayment $payment,
        int $amountMinor,
        ?string $paidAt = null,
        ?string $method = null,
        ?string $reference = null,
        ?string $notes = null,
    ): HospitalityRentPayment {
        if ($amountMinor <= 0) {
            throw new \App\Exceptions\DomainException(
                (string) __('hospitality.rent_amount_must_be_positive'),
                422,
                'RENT_AMOUNT_INVALID'
            );
        }

        if (! $payment->isPayable()) {
            throw new \App\Exceptions\DomainException(
                (string) __('hospitality.rent_already_settled'),
                422,
                'RENT_ALREADY_SETTLED'
            );
        }

        $outstanding = $payment->outstandingMinor();
        if ($amountMinor > $outstanding) {
            throw new \App\Exceptions\DomainException(
                (string) __('hospitality.rent_amount_exceeds_outstanding', [
                    'amount' => $amountMinor,
                    'outstanding' => $outstanding,
                ]),
                422,
                'RENT_AMOUNT_EXCEEDS_OUTSTANDING'
            );
        }

        return DB::transaction(function () use ($payment, $amountMinor, $paidAt, $method, $reference, $notes): HospitalityRentPayment {
            $newPaid = $payment->paid_amount_minor + $amountMinor;
            $settled = $newPaid >= $payment->amount_minor;

            $payment->forceFill([
                'paid_amount_minor' => $newPaid,
                'status' => $settled ? HospitalityRentPayment::STATUS_PAID : HospitalityRentPayment::STATUS_PARTIAL,
                'paid_at' => $settled ? ($paidAt !== null ? Carbon::parse($paidAt) : Carbon::now()) : $payment->paid_at,
                'payment_method' => $method ?? $payment->payment_method,
                'reference' => $reference ?? $payment->reference,
                'notes' => $notes ?? $payment->notes,
            ])->save();

            return $payment->refresh();
        });
    }

    /**
     * Recalcule le statut `late` d'une échéance (dérivé, jamais saisi).
     *
     * Une échéance soldée reste `paid` ; une échéance partiellement payée
     * dont la date est dépassée devient `late` (le reste dû est en retard).
     */
    public function refreshLateStatus(HospitalityRentPayment $payment, ?Carbon $now = null): HospitalityRentPayment
    {
        if ($payment->status === HospitalityRentPayment::STATUS_PAID) {
            return $payment;
        }

        $now ??= Carbon::now();
        $overdue = $payment->due_on->copy()->endOfDay()->lt($now);

        $target = $overdue
            ? HospitalityRentPayment::STATUS_LATE
            : ($payment->paid_amount_minor > 0 ? HospitalityRentPayment::STATUS_PARTIAL : HospitalityRentPayment::STATUS_DUE);

        if ($payment->status !== $target) {
            $payment->forceFill(['status' => $target])->save();
            $payment->refresh();
        }

        return $payment;
    }

    /**
     * Date d'échéance d'une période, `billing_day` ramené au dernier jour du
     * mois quand il n'existe pas (février pour un jour 31).
     */
    public function dueDateFor(string $period, int $billingDay): string
    {
        $start = Carbon::parse($period.'-01')->startOfMonth();
        $daysInMonth = (int) $start->copy()->endOfMonth()->day;
        $day = min(max(1, $billingDay), $daysInMonth);

        return $start->copy()->day($day)->toDateString();
    }
}
