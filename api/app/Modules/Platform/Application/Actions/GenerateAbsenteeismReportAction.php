<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rapport RH absentéisme — jours approuvés par type d'absence, toutes
 * tables tenant (schéma shared_tenants). Logique extraite de
 * PlatformHrReportController (issue #6569, audit DDD M1).
 *
 * @return array{columns: string[], rows: array<int, array<string, mixed>>}
 */
final class GenerateAbsenteeismReportAction
{
    private const TENANT_SCHEMA = 'shared_tenants';

    public function execute(string $start, string $end): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.absences as a')
                ->leftJoin(self::TENANT_SCHEMA.'.absence_types as t', 't.id', '=', 'a.absence_type_id')
                ->select('t.name as type', DB::raw('sum(a.days_count) as days'))
                ->where('a.status', 'approved')
                ->where('a.start_date', '<=', $end)
                ->where('a.end_date', '>=', $start)
                ->groupBy('t.name')
                ->orderByDesc('days')
                ->get()
                ->map(fn ($row): array => ['Type' => $row->type ?? 'Autre', 'Jours' => (float) $row->days])
                ->values()
                ->all();

            return ['columns' => ['Type', 'Jours'], 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::error('Platform HR report data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new RuntimeException('Platform HR report data is temporarily unavailable.');
        }
    }
}
