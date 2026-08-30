<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelSalesSettlement;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * TRAVEL-417 (#6069) — Synthèse périodique des ventes pour Accounting.
 *
 * Agrège les paiements confirmés/remboursés d'une période (devise par
 * devise) dans `travel_sales_settlements` puis publie l'événement
 * `travel.sales.settled.v1` APRÈS commit (pattern outbox). Idempotence :
 * la contrainte unique (tenant, période, devise) garantit un upsert
 * déterministe — rejouer la même période produit exactement les mêmes
 * montants (critère d'acceptation). La verticale n'écrit jamais dans les
 * tables Accounting : elle fournit le contrat de synthèse validé.
 */
final class TravelSalesSettlementService
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @return array{settlement: TravelSalesSettlement, replayed: bool}
     */
    public function settle(
        string $companyId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        bool $dryRun = false,
    ): array {
        $start = CarbonImmutable::parse($periodStart)->startOfDay();
        $end = CarbonImmutable::parse($periodEnd)->endOfDay();

        $currencies = TravelPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [PaymentStatus::CONFIRMED, PaymentStatus::REFUNDED])
            ->distinct()
            ->pluck('currency');

        if ($currencies->isEmpty()) {
            // Période vide : aucune synthèse à produire (rien à publier).
            $existing = TravelSalesSettlement::query()
                ->where('company_id', $companyId)
                ->where('period_start', $start->toDateString())
                ->where('period_end', $end->toDateString())
                ->first();

            if ($existing instanceof TravelSalesSettlement) {
                return ['settlement' => $existing, 'replayed' => true];
            }

            if ($dryRun) {
                throw new \RuntimeException('Aucun paiement sur la période — dry-run sans création.');
            }

            $settlement = TravelSalesSettlement::query()->create([
                'company_id' => $companyId,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'currency' => (string) currentCompany()->currency,
                'confirmed_payments_count' => 0,
                'confirmed_amount_minor' => 0,
                'refunded_count' => 0,
                'refunded_amount_minor' => 0,
                'net_amount_minor' => 0,
                'status' => TravelSalesSettlement::STATUS_SETTLED,
                'settled_at' => now(),
            ]);

            return ['settlement' => $settlement, 'replayed' => false];
        }

        $results = [];

        foreach ($currencies as $currency) {
            $results[$currency] = $this->aggregateForCurrency($companyId, $start, $end, (string) $currency);
        }

        if ($dryRun) {
            return [
                'settlement' => $this->dryRunSettlement($companyId, $start, $end, $results),
                'replayed' => false,
            ];
        }

        $settlements = [];

        foreach ($results as $currency => $aggregate) {
            $settlement = TravelSalesSettlement::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'currency' => $currency,
                ],
                array_merge($aggregate, [
                    'status' => TravelSalesSettlement::STATUS_SETTLED,
                    'settled_at' => now(),
                ]),
            );

            $settlements[] = $settlement;

            $this->publishSettled($settlement);
        }

        return ['settlement' => $settlements[0], 'replayed' => false];
    }

    /**
     * @return array{confirmed_payments_count: int, confirmed_amount_minor: int, refunded_count: int, refunded_amount_minor: int, net_amount_minor: int}
     */
    private function aggregateForCurrency(
        string $companyId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $currency,
    ): array {
        $confirmed = TravelPayment::query()
            ->where('company_id', $companyId)
            ->where('currency', $currency)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', PaymentStatus::CONFIRMED)
            ->get();

        $refunded = TravelPayment::query()
            ->where('company_id', $companyId)
            ->where('currency', $currency)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', PaymentStatus::REFUNDED)
            ->get();

        $confirmedAmount = (int) $confirmed->sum('amount_minor');
        $refundedAmount = (int) $refunded->sum('amount_minor');

        return [
            'confirmed_payments_count' => $confirmed->count(),
            'confirmed_amount_minor' => $confirmedAmount,
            'refunded_count' => $refunded->count(),
            'refunded_amount_minor' => $refundedAmount,
            'net_amount_minor' => $confirmedAmount - $refundedAmount,
        ];
    }

    /**
     * @param  array<string, array{confirmed_payments_count: int, confirmed_amount_minor: int, refunded_count: int, refunded_amount_minor: int, net_amount_minor: int}>  $results
     */
    private function dryRunSettlement(
        string $companyId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $results,
    ): TravelSalesSettlement {
        $first = reset($results);

        return new TravelSalesSettlement([
            'company_id' => $companyId,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'currency' => (string) array_key_first($results),
            'confirmed_payments_count' => $first['confirmed_payments_count'],
            'confirmed_amount_minor' => $first['confirmed_amount_minor'],
            'refunded_count' => $first['refunded_count'],
            'refunded_amount_minor' => $first['refunded_amount_minor'],
            'net_amount_minor' => $first['net_amount_minor'],
            'status' => TravelSalesSettlement::STATUS_SETTLED,
            'settled_at' => now(),
        ]);
    }

    private function publishSettled(TravelSalesSettlement $settlement): void
    {
        $this->outbox->publish(
            $settlement->company_id,
            'travel.sales.settled.v1',
            [
                'period_start' => $settlement->period_start->toDateString(),
                'period_end' => $settlement->period_end->toDateString(),
                'currency' => $settlement->currency,
                'confirmed_payments_count' => $settlement->confirmed_payments_count,
                'confirmed_amount_minor' => $settlement->confirmed_amount_minor,
                'refunded_count' => $settlement->refunded_count,
                'refunded_amount_minor' => $settlement->refunded_amount_minor,
                'net_amount_minor' => $settlement->net_amount_minor,
                'settled_at' => $settlement->settled_at->toIso8601String(),
            ],
            // Clé d'idempotence STABLE par période : un rejeu de la même
            // période ne republie jamais l'événement (ni doublon chez les
            // consommateurs Accounting).
            idempotencyKey: sprintf(
                'sales-settled:%s:%s:%s',
                $settlement->period_start->toDateString(),
                $settlement->period_end->toDateString(),
                $settlement->currency,
            ),
        );
    }
}
