<?php

declare(strict_types=1);

namespace App\Core\Solutions\Interfaces\Api\V1;

use App\Core\Solutions\SolutionCatalogue;
use App\Core\Solutions\Survey\SolutionSurveyEngine;
use App\Core\Solutions\Survey\SolutionSurveyRegistry;
use App\Core\Solutions\Infrastructure\Services\SolutionPackPdfGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * API publique des solutions sectorielles (vitrine, sans auth).
 *
 * Endpoints :
 *   GET  /api/v1/solutions              → catalogue (codes + noms)
 *   GET  /api/v1/solutions/{code}/survey    → questions + catalogue de packages
 *   POST /api/v1/solutions/{code}/survey    → { answers: {...} } → packages suggérés
 *
 * 100 % public et déterministe : aucun secret, aucune donnée tenant.
 * Throttle strict côté routes (voir routes/modules/solutions.php).
 */
final class SolutionSurveyController extends Controller
{
    public function __construct(
        private readonly SolutionCatalogue $catalogue,
        private readonly SolutionSurveyRegistry $surveyRegistry,
        private readonly SolutionSurveyEngine $engine,
        private readonly SolutionPackPdfGenerator $pdfGenerator,
    ) {}

    /** GET /solutions — liste les solutions disponibles (allowlist). */
    public function index(): JsonResponse
    {
        $solutions = [];

        foreach ($this->catalogue->codes() as $code) {
            $manifest = $this->catalogue->resolve($code);
            $solutions[] = [
                'code' => $manifest->code(),
                'name' => $manifest->name(),
                'description' => $manifest->description(),
                'maturity' => $manifest->maturity(),
            ];
        }

        return new JsonResponse(['data' => $solutions]);
    }

    /** GET /solutions/{code}/survey — questions + packages de la solution. */
    public function questions(Request $request, string $code): JsonResponse
    {
        try {
            $survey = $this->surveyRegistry->resolve($code);
        } catch (Throwable $e) {
            return $this->errorResponse('SOLUTION_SURVEY_NOT_FOUND', 404);
        }

        return new JsonResponse([
            'data' => [
                'code' => $survey->code(),
                'questions' => $survey->questions(),
                'packages' => array_values($survey->packages()),
            ],
        ]);
    }

    /**
     * POST /solutions/{code}/survey — évalue les réponses et retourne le
     * pack suggéré (packages triés par priorité, chacun avec sa raison).
     */
    public function suggest(Request $request, string $code): JsonResponse
    {
        try {
            $survey = $this->surveyRegistry->resolve($code);
        } catch (Throwable $e) {
            return $this->errorResponse('SOLUTION_SURVEY_NOT_FOUND', 404);
        }

        try {
            $validated = $request->validate([
                'answers' => ['required', 'array'],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('INVALID_ANSWERS', 422);
        }

        $answers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];

        $result = $this->engine->suggest($survey, $answers);

        return new JsonResponse([
            'data' => [
                'code' => $survey->code(),
                'packages' => $result['packages'],
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * GET /solutions/{code}/pack?packages=key1,key2 — guide PDF personnalisé.
     *
     * Clés validées contre le catalogue de la solution (fail-closed) ;
     * document localisé via les fichiers lang (`solutions.*`), généré avec
     * dompdf (déjà en deps — zéro dépendance payante).
     */
    public function downloadPack(Request $request, string $code): JsonResponse|Response
    {
        try {
            $survey = $this->surveyRegistry->resolve($code);
        } catch (Throwable $e) {
            return $this->errorResponse('SOLUTION_SURVEY_NOT_FOUND', 404);
        }

        $raw = (string) $request->query('packages', '');
        $keys = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($keys === []) {
            return $this->errorResponse('INVALID_PACKAGES', 422);
        }

        $catalog = $survey->packages();
        $unknown = array_diff($keys, array_keys($catalog));
        if ($unknown !== []) {
            return $this->errorResponse('INVALID_PACKAGES', 422);
        }

        $locale = (string) $request->query('locale', 'fr');
        $locale = in_array($locale, ['fr', 'en', 'tr', 'ar'], true) ? $locale : 'fr';

        $pdf = $this->pdfGenerator->generate($survey, $keys, $locale);

        return $pdf->download('leopardo_'.$code.'_pack.pdf');
    }

    private function errorResponse(string $code, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code]], $status);
    }
}
