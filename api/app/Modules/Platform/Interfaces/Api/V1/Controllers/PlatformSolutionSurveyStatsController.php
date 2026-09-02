<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Statistiques de pilotage des surveys de solutions (vitrine) — BC-25 #6694.
 *
 * Contrat SPA admin-dashboard : GET /admin/solutions/survey-stats (super-admin).
 *
 * Agrège les leads de type `solution_survey` (MarketingLead, table globale
 * public.marketing_leads) : volume par solution, distribution des réponses,
 * packs les plus suggérés et conversion survey → inscription.
 *
 * Borné (#6562) : `limit` par défaut 200, plafonné à 1000 — les agrégats sont
 * calculés en PHP sur l'échantillon (le payload des réponses est du JSON
 * arbitraire, pas agrégable en SQL de façon portable).
 */
final class PlatformSolutionSurveyStatsController extends Controller
{
    private const DEFAULT_LIMIT = 200;

    private const MAX_LIMIT = 1000;

    private const MAX_QUESTIONS = 12;

    private const MAX_VALUES_PER_QUESTION = 10;

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', self::DEFAULT_LIMIT);
        $limit = min(max($limit, 1), self::MAX_LIMIT);

        $leads = MarketingLead::query()
            ->where('type', MarketingLead::TYPE_SOLUTION_SURVEY)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $total = $leads->count();
        $converted = $leads->filter(fn (MarketingLead $lead): bool => $lead->converted_company_id !== null)->count();

        $bySolution = [];
        $packages = [];
        $answers = [];

        foreach ($leads as $lead) {
            $payload = $lead->payload ?? [];

            $solution = is_string($payload['solution'] ?? null) ? $payload['solution'] : ($lead->source ?? 'unknown');
            $bySolution[$solution] = ($bySolution[$solution] ?? 0) + 1;

            $leadPackages = is_array($payload['packages'] ?? null) ? $payload['packages'] : [];
            foreach ($leadPackages as $package) {
                if (is_string($package)) {
                    $packages[$package] = ($packages[$package] ?? 0) + 1;
                }
            }

            $leadAnswers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
            foreach ($leadAnswers as $question => $value) {
                if (! is_string($question) || $question === '') {
                    continue;
                }
                $normalized = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $answers[$question][$normalized] = ($answers[$question][$normalized] ?? 0) + 1;
            }
        }

        // Distribution des réponses : questions les plus répondues d'abord,
        // bornée (MAX_QUESTIONS), valeurs triées par fréquence décroissante.
        $answerDistribution = [];
        uasort($answers, static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
        foreach (array_slice($answers, 0, self::MAX_QUESTIONS, true) as $question => $values) {
            arsort($values);
            $answerDistribution[] = [
                'question' => $question,
                'total' => array_sum($values),
                'values' => array_map(
                    static fn (string $value, int $count): array => ['value' => $value, 'count' => $count],
                    array_keys(array_slice($values, 0, self::MAX_VALUES_PER_QUESTION, true)),
                    array_slice($values, 0, self::MAX_VALUES_PER_QUESTION, true),
                ),
            ];
        }

        arsort($packages);
        arsort($bySolution);

        return new JsonResponse([
            'data' => [
                'totals' => [
                    'responses' => $total,
                    'converted' => $converted,
                    'conversion_rate' => $total > 0 ? round($converted / $total, 4) : 0.0,
                ],
                'by_solution' => array_map(
                    static fn (string $code, int $count): array => ['solution' => $code, 'responses' => $count],
                    array_keys($bySolution),
                    $bySolution,
                ),
                'packages' => array_map(
                    static fn (string $key, int $count): array => ['key' => $key, 'count' => $count],
                    array_keys($packages),
                    $packages,
                ),
                'answers' => $answerDistribution,
                'window' => [
                    'limit' => $limit,
                    'type' => MarketingLead::TYPE_SOLUTION_SURVEY,
                ],
            ],
        ]);
    }
}
