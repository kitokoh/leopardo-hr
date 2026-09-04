<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques de pilotage des surveys de solutions (vitrine) — BC-25 #6694.
 *
 * Contrat SPA admin-dashboard : GET /admin/solutions/survey-stats (super-admin).
 *
 * Agrège les leads de type `solution_survey` (table globale `marketing_leads`,
 * schéma public) : volume par solution, distribution des réponses, packs les
 * plus suggérés et conversion survey → inscription.
 *
 * Isolation modules (ARCHITECTURE.md §2, garde #5584) : la lecture se fait via
 * `DB::table('marketing_leads')` (aucun import du module Marketing — même
 * pattern que PlatformAdminAiConversationController pour les lectures
 * cross-module en lecture seule).
 *
 * Borné (#6562) : `limit` par défaut 200, plafonné à 1000 — les agrégats sont
 * calculés en PHP sur l'échantillon (le payload des réponses est du JSON
 * arbitraire, pas agrégable en SQL de façon portable).
 */
final class PlatformSolutionSurveyStatsController extends Controller
{
    private const LEADS_TABLE = 'marketing_leads';

    private const SURVEY_LEAD_TYPE = 'solution_survey';

    private const DEFAULT_LIMIT = 200;

    private const MAX_LIMIT = 1000;

    private const MAX_QUESTIONS = 12;

    private const MAX_VALUES_PER_QUESTION = 10;

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', self::DEFAULT_LIMIT);
        $limit = min(max($limit, 1), self::MAX_LIMIT);

        $rows = DB::table(self::LEADS_TABLE)
            ->where('type', self::SURVEY_LEAD_TYPE)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $total = $rows->count();
        $converted = $rows->filter(
            fn (object $row): bool => filled($row->converted_company_id ?? null)
        )->count();

        $bySolution = [];
        $packages = [];
        $answers = [];

        foreach ($rows as $row) {
            $payload = is_string($row->payload ?? null) ? json_decode($row->payload, true) : [];
            $payload = is_array($payload) ? $payload : [];

            $solution = is_string($payload['solution'] ?? null) ? $payload['solution'] : (string) ($row->source ?? 'unknown');
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
                    'type' => self::SURVEY_LEAD_TYPE,
                ],
            ],
        ]);
    }
}
