<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelSalesSettlement;
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

    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

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
            // #9741 — le règlement doit être PERSISTÉ, pas seulement publié.
            // `TravelSalesSettlement` est la trace interrogeable par le
            // back-office (« qu'a-t-on réglé pour la période X ? ») et par la
            // réconciliation Accounting ; sans elle, la synthèse n'existait
            // que dans l'outbox et disparaissait une fois l'événement consommé.
            // `updateOrCreate` sur (company, période, devise) rend le rejeu
            // idempotent : même période = même ligne, mêmes montants.
            TravelSalesSettlement::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'period_start' => $from,
                    'period_end' => $to,
                    'currency' => $payload['currency'],
                ],
                [
                    'confirmed_payments_count' => $payload['confirmed_count'],
                    'confirmed_amount_minor' => $payload['confirmed_amount_minor'],
                    'refunded_count' => $payload['refunded_count'],
                    'refunded_amount_minor' => $payload['refunded_amount_minor'],
                    'net_amount_minor' => $payload['confirmed_amount_minor'] - $payload['refunded_amount_minor'],
                    'status' => 'settled',
                    'settled_at' => now(),
                ],
            );

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
