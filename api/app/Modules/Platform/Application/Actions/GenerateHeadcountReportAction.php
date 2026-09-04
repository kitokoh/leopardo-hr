<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rapport RH headcount — effectif par statut, toutes tables tenant
 * (schéma shared_tenants). Logique extraite de PlatformHrReportController
 * (issue #6569, audit DDD M1).
 */
final class GenerateHeadcountReportAction
{
    private const TENANT_SCHEMA = 'shared_tenants';

    /**
     * @return array{columns: string[], rows: array<int, array<string, mixed>>}
     */
    public function execute(): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.employees')
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row): array => ['Statut' => $row->status, 'Effectif' => (int) $row->total])
                ->values()
                ->all();

            $total = array_sum(array_column($rows, 'Effectif'));
            $rows[] = ['Statut' => 'TOTAL', 'Effectif' => $total];

            return ['columns' => ['Statut', 'Effectif'], 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::error('Platform HR report data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new RuntimeException('Platform HR report data is temporarily unavailable.');
        }
    }
}
