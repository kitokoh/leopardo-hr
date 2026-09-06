<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Actions;

use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;

/**
 * DELIVERY-205 (#6289) — synthèse des règlements COD par statut (attendus vs
 * collectés, écarts signalés). Extraite de
 * DeliveryCodSettlementController::report (couche Application vide, #6898).
 */
final class SummarizeDeliveryCodSettlementsAction
{
    /**
     * @return array{
     *     totals: array{expected_minor: int, collected_minor: int, gap_minor: int},
     *     by_status: list<array{
     *         status: string,
     *         settlements: int,
     *         expected_minor: int,
     *         collected_minor: int,
     *         commission_minor: int
     *     }>
     * }
     */
    public function execute(string $companyId): array
    {
        $rows = DeliveryCodSettlement::query()
            ->where('company_id', $companyId)
            ->selectRaw(
                'status,
                 COUNT(*) AS settlements,
                 COALESCE(SUM(expected_minor), 0) AS expected_minor,
                 COALESCE(SUM(collected_minor), 0) AS collected_minor,
                 COALESCE(SUM(commission_minor), 0) AS commission_minor',
            )
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $expected = $rows->sum('expected_minor');
        $collected = $rows->sum('collected_minor');

        /** @var list<array{status: string, settlements: int, expected_minor: int, collected_minor: int, commission_minor: int}> $byStatus */
        $byStatus = $rows
            ->map(fn ($row): array => [
                'status' => (string) $row->status,
                'settlements' => (int) $row->getAttribute('settlements'),
                'expected_minor' => (int) $row->expected_minor,
                'collected_minor' => (int) $row->collected_minor,
                'commission_minor' => (int) $row->commission_minor,
            ])
            ->values()
            ->all();

        return [
            'totals' => [
                'expected_minor' => (int) $expected,
                'collected_minor' => (int) $collected,
                'gap_minor' => (int) ($expected - $collected),
            ],
            'by_status' => $byStatus,
        ];
    }
}
