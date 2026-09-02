<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rapport RH turnover — embauches/départs par mois, toutes tables tenant
 * (schéma shared_tenants). Logique extraite de PlatformHrReportController
 * (issue #6569, audit DDD M1).
 *
 * @return array{columns: string[], rows: array<int, array<string, mixed>>}
 */
final class GenerateTurnoverReportAction
{
    private const TENANT_SCHEMA = 'shared_tenants';

    public function execute(string $start, string $end): array
    {
        try {
            $hires = DB::table(self::TENANT_SCHEMA.'.employees')
                // `hire_date` n'existe pas en base : la date d'embauche est
                // `contract_start` (même mapping que EmployeeResource::hire_date).
                ->select(DB::raw("to_char(contract_start, 'YYYY-MM') as month"), DB::raw('count(*) as hires'))
                ->whereBetween('contract_start', [$start, $end])
                ->groupBy(DB::raw("to_char(contract_start, 'YYYY-MM')"))
                ->pluck('hires', 'month');

            $departures = DB::table(self::TENANT_SCHEMA.'.contracts')
                ->select(DB::raw("to_char(end_date, 'YYYY-MM') as month"), DB::raw('count(*) as departures'))
                ->whereBetween('end_date', [$start, $end])
                ->groupBy(DB::raw("to_char(end_date, 'YYYY-MM')"))
                ->pluck('departures', 'month');

            $months = array_unique(array_merge($hires->keys()->all(), $departures->keys()->all()));
            sort($months);

            $rows = [];
            $running = 0;
            foreach ($months as $month) {
                $h = (int) ($hires[$month] ?? 0);
                $d = (int) ($departures[$month] ?? 0);
                $running += $h - $d;
                $rows[] = ['Mois' => $month, 'Embauches' => $h, 'Departs' => $d, 'Effectif net' => $running];
            }

            return ['columns' => ['Mois', 'Embauches', 'Departs', 'Effectif net'], 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::error('Platform HR report data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new RuntimeException('Platform HR report data is temporarily unavailable.');
        }
    }
}
