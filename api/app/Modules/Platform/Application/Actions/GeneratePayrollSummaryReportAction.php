<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rapport RH synthèse paie — bulletins/brut/net par mois, toutes tables
 * tenant (schéma shared_tenants). Logique extraite de
 * PlatformHrReportController (issue #6569, audit DDD M1).
 */
final class GeneratePayrollSummaryReportAction
{
    private const TENANT_SCHEMA = 'shared_tenants';

    /**
     * @return array{columns: string[], rows: array<int, array<string, mixed>>}
     */
    public function execute(string $start, string $end): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.pay_slips')
                ->select(
                    DB::raw("to_char(period_start, 'YYYY-MM') as month"),
                    DB::raw('count(*) as bulletins'),
                    DB::raw('round(sum(gross_salary), 2) as brut'),
                    DB::raw('round(sum(net_salary), 2) as net')
                )
                ->whereBetween('period_start', [$start, $end])
                ->groupBy(DB::raw("to_char(period_start, 'YYYY-MM')"))
                ->orderBy('month')
                ->get()
                ->map(fn ($row): array => [
                    'Mois' => $row->month,
                    'Bulletins' => (int) $row->bulletins,
                    'Brut' => (float) $row->brut,
                    'Net' => (float) $row->net,
                ])
                ->all();

            return ['columns' => ['Mois', 'Bulletins', 'Brut', 'Net'], 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::error('Platform HR report data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new RuntimeException('Platform HR report data is temporarily unavailable.');
        }
    }
}
