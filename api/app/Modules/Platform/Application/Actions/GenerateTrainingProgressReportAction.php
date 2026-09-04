<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rapport RH progression formations — inscrits/complétés par formation,
 * toutes tables tenant (schéma shared_tenants). Logique extraite de
 * PlatformHrReportController (issue #6569, audit DDD M1).
 */
final class GenerateTrainingProgressReportAction
{
    private const TENANT_SCHEMA = 'shared_tenants';

    /**
     * @return array{columns: string[], rows: array<int, array<string, mixed>>}
     */
    public function execute(string $start, string $end): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.training_enrollments as e')
                // Le lien enrolment → cours passe par training_sessions
                // (training_enrollments.training_session_id → sessions.training_course_id).
                ->leftJoin(self::TENANT_SCHEMA.'.training_sessions as s', 's.id', '=', 'e.training_session_id')
                ->leftJoin(self::TENANT_SCHEMA.'.training_courses as c', 'c.id', '=', 's.training_course_id')
                ->select(
                    'c.title as formation',
                    DB::raw('count(*) as inscrits'),
                    DB::raw("count(*) filter (where e.status = 'completed') as completes")
                )
                // `enrolled_at` n'existe pas en base — la date d'inscription est
                // created_at (fixture et migration 2026_05_10_000006).
                ->whereBetween('e.created_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->groupBy('c.title')
                ->orderByDesc('inscrits')
                ->get()
                ->map(fn ($row): array => [
                    'Formation' => $row->formation ?? 'Inconnue',
                    'Inscrits' => (int) $row->inscrits,
                    'Completes' => (int) $row->completes,
                ])
                ->all();

            return ['columns' => ['Formation', 'Inscrits', 'Completes'], 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::error('Platform HR report data source failed', [
                'operation' => __FUNCTION__,
                'exception' => $e,
            ]);
            throw new RuntimeException('Platform HR report data is temporarily unavailable.');
        }
    }
}
