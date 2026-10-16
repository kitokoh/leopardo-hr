<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Infrastructure\Services\MarketingAiService;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\SuggestPostRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\SuggestProductionBriefRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * IA marketing — Issue #7753.
 *
 * Deux endpoints tenant (middleware api.manager:marketing,principal — même
 * empilement que le reste de routes/modules/marketing.php) :
 *   - POST /marketing/ai/suggest-post : brief → proposition de texte de
 *     post (jamais publiée automatiquement — humain dans la boucle) ;
 *   - GET  /marketing/reports/weekly : bilan des 7 derniers jours (agrégats
 *     + synthèse IA, fallback déterministe si le LLM est indisponible).
 *
 * L'IA indisponible pour une suggestion → 503 explicite (jamais de 500).
 */
class MarketingAiController extends Controller
{
    public function __construct(private readonly MarketingAiService $ai) {}

    public function suggestPost(SuggestPostRequest $request): JsonResponse
    {
        $platforms = [];
        $requested = $request->input('platforms');
        if (is_array($requested)) {
            foreach ($requested as $platform) {
                if (is_string($platform) && $platform !== '') {
                    $platforms[] = $platform;
                }
            }
        }

        $tone = $request->filled('tone') ? $request->string('tone')->toString() : 'professionnel et engageant';
        $locale = $request->filled('locale') ? $request->string('locale')->toString() : 'fr';

        try {
            $suggestion = $this->ai->suggestPost(
                $request->string('brief')->toString(),
                $platforms,
                $tone,
                $locale,
            );
        } catch (Throwable) {
            return new JsonResponse([
                'error' => 'MARKETING_AI_UNAVAILABLE',
                'message' => __('marketing.ai.suggestion_unavailable'),
            ], 503);
        }

        return new JsonResponse([
            'data' => [
                'suggestion' => $suggestion,
                'brief' => $request->string('brief')->toString(),
                'platforms' => $platforms,
            ],
        ]);
    }

    /**
     * POST /marketing/ai/suggest-production-brief (#8533)
     *
     * L'IA propose script, description, legende, hashtags et prompts. Elle ne
     * PRODUIT rien : `media_generated` vaut toujours `false`, et rien n'est
     * persiste — un humain valide et decide de ce qu'il conserve. Aucun statut
     * de publication n'est modifie par cet appel.
     */
    public function suggestProductionBrief(SuggestProductionBriefRequest $request): JsonResponse
    {
        $format = $request->filled('format') ? $request->string('format')->toString() : 'video_short';
        $locale = $request->filled('locale') ? $request->string('locale')->toString() : 'fr';

        /** @var array<string, mixed> $brief */
        $brief = (array) $request->input('brief', []);

        try {
            $proposal = $this->ai->suggestProductionBrief($brief, $format, $locale);
        } catch (Throwable) {
            return new JsonResponse([
                'error' => 'MARKETING_AI_UNAVAILABLE',
                'message' => __('marketing.ai.suggestion_unavailable'),
            ], 503);
        }

        return new JsonResponse([
            'data' => $proposal,
            'meta' => [
                'format' => $format,
                // Rappel explicite : une proposition n'est pas un livrable.
                'media_generated' => false,
                'requires_human_validation' => true,
            ],
        ]);
    }

    public function weeklyReport(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return new JsonResponse([
            'data' => $this->ai->weeklyReport((string) $actor->company_id),
        ]);
    }
}
