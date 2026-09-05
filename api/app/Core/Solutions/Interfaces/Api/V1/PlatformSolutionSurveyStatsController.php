<?php

declare(strict_types=1);

namespace App\Core\Solutions\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * #6694 — Stats admin des surveys de pré-qualification des solutions
 * sectorielles (wizard vitrine « Je suis restaurateur »).
 *
 * Vue super-admin cross-tenant : volume de réponses par solution, packs les
 * plus suggérés, distribution des réponses clés et conversion survey →
 * inscription (compagnies ayant la solution activée vs réponses).
 *
 * GET /api/v1/admin/solutions/surveys (auth:super_admin_api)
 */
final class PlatformSolutionSurveyStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $days = min(max($request->integer('days', 30), 1), 365);

        $since = now()->subDays($days);

        $perSolution = DB::table('solution_survey_responses')
            ->where('created_at', '>=', $since)
            ->select('solution_code', DB::raw('count(*) as total'))
            ->groupBy('solution_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'code' => $row->solution_code,
                'responses' => (int) $row->total,
            ])
            ->values()
            ->all();

        // Packs les plus suggérés (toutes solutions confondues, fenêtre).
        $packageCounts = [];
        DB::table('solution_survey_responses')
            ->where('created_at', '>=', $since)
            ->select('suggested_packages')
            ->get()
            ->each(function (object $row) use (&$packageCounts): void {
                $packages = json_decode((string) $row->suggested_packages, true);
                foreach (is_array($packages) ? $packages : [] as $package) {
                    $key = (string) ($package['key'] ?? 'unknown');
                    $packageCounts[$key] = ($packageCounts[$key] ?? 0) + 1;
                }
            });
        arsort($packageCounts);
        $topPackages = array_map(
            static fn (string $key, int $count): array => ['key' => $key, 'suggestions' => $count],
            array_keys(array_slice($packageCounts, 0, 10, true)),
            array_values(array_slice($packageCounts, 0, 10, true)),
        );

        $totalResponses = (int) DB::table('solution_survey_responses')
            ->where('created_at', '>=', $since)
            ->count();

        // Conversion survey → inscription : compagnies ayant la solution
        // activée (feature flag) créées dans la fenêtre.
        $converted = (int) DB::table('companies')
            ->where('created_at', '>=', $since)
            ->where('features', 'like', '%"restaurant":true%')
            ->count();

        return new JsonResponse([
            'data' => [
                'window_days' => $days,
                'total_responses' => $totalResponses,
                'per_solution' => $perSolution,
                'top_packages' => $topPackages,
                'conversion' => [
                    'survey_responses' => $totalResponses,
                    'companies_with_solution' => $converted,
                    'rate' => $totalResponses > 0 ? round($converted / $totalResponses * 100, 1) : 0.0,
                ],
            ],
        ]);
    }
}
