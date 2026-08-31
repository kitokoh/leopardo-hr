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
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-417 (#6069) — synthèse Accounting des ventes confirmées.
 *
 * La verticale N'ÉCRIT JAMAIS dans les tables Accounting : elle émet un
 * événement de synthèse validé et rejouable `travel.sales.settled.v1`
 * (période, montants minor units, devise, nb de paiements confirmés/
 * remboursés) que le BC Accounting consomme pour construire ses écritures.
 *
 * Idempotence : clé dérivée (période, devise) → rejouer la même période
 * produit le MÊME événement (aucun doublon) et les MÊMES montants
 * (critère d'acceptation : « même période = même montant »).
 */
final class TravelSalesSettlementService
{
    public const EVENT_SALES_SETTLED = 'travel.sales.settled.v1';

    public function __construct(private readonly TravelOutboxPublisher $outbox)
    {
    }

    /**
     * Calcule et publie la synthèse des ventes confirmées d'une période.
     *
     * @return array<string, int|string>|null payload publié, ou null si aucun paiement
     */
    public function settle(string $companyId, string $from, string $to): ?array
    {
        $rows = TravelPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween(DB::raw('created_at::date'), [$from, $to])
            ->select('currency', 'status', DB::raw('count(*) as total'), DB::raw('sum(amount_minor) as amount'))
            ->groupBy('currency', 'status')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $payloads = [];

        foreach ($rows->groupBy('currency') as $currency => $currencyRows) {
            /** @var string $currency */
            $confirmed = $currencyRows->firstWhere('status', PaymentStatus::CONFIRMED->value);
            $refunded = $currencyRows->firstWhere('status', PaymentStatus::REFUNDED->value);

            $payloads[] = [
                'company_id' => $companyId,
                'period_from' => $from,
                'period_to' => $to,
                'currency' => $currency,
                'confirmed_amount_minor' => (int) ($confirmed['amount'] ?? 0),
                'confirmed_count' => (int) ($confirmed['total'] ?? 0),
                'refunded_amount_minor' => (int) ($refunded['amount'] ?? 0),
                'refunded_count' => (int) ($refunded['total'] ?? 0),
            ];
        }

        foreach ($payloads as $payload) {
            $this->outbox->publish(
                $companyId,
                self::EVENT_SALES_SETTLED,
                $payload,
                'sales-settle-'.$from.'-'.$to.'-'.$payload['currency'],
            );
        }

        return $payloads[0];
    }
}
