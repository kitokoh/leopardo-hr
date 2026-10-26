<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\GeoCheckMethod;
use App\Modules\SearchIntelligence\Domain\Models\GeoCheck;
use App\Modules\SearchIntelligence\Domain\Models\GeoQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GSI-5 (#8516) — requêtes stratégiques GEO et checks de présence.
 *
 * ```
 * GET   /geo/queries                      — liste (+ métadonnées de méthode)
 * POST  /geo/queries                      — création
 * GET   /geo/queries/{id}/checks          — historique ORDONNÉ, immuable
 * POST  /geo/queries/{id}/checks          — ajout d'une observation
 * ```
 *
 * Le contrat central de cette API : la `method` est obligatoire, et elle est
 * TOUJOURS renvoyée avec son badge (`method_badge`). Un test ponctuel ne peut
 * donc pas être affiché comme un indicateur mesuré — l'information voyage avec
 * la donnée, elle ne dépend pas de la vigilance du front.
 *
 * L'historique est immuable : il n'existe pas de route PATCH ni DELETE sur un
 * check. Corriger une observation = ajouter une nouvelle observation.
 */
final class SearchIntelGeoController
{
    public function indexQueries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sector' => ['sometimes', 'string', 'max:40'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'active_only' => ['sometimes', 'boolean'],
        ]);

        $query = GeoQuery::query()->withCount('checks');

        if (isset($validated['sector'])) {
            $query->where('sector', $validated['sector']);
        }

        if (isset($validated['locale'])) {
            $query->where('locale', $validated['locale']);
        }

        if (($validated['active_only'] ?? false) === true) {
            $query->where('is_active', true);
        }

        $items = $query->orderBy('priority')->limit(200)->get();

        return new JsonResponse([
            'data' => $items->map(fn (GeoQuery $q): array => [
                'id' => $q->id,
                'query' => $q->query,
                'locale' => $q->locale,
                'sector' => $q->sector,
                'priority' => $q->priority,
                'is_active' => $q->is_active,
                'reference_pages' => $q->reference_pages,
                'checks_count' => $q->checks_count,
                // Niveau de preuve le plus faible : il plafonne ce qu'on peut
                // affirmer publiquement sur cette requête.
                'weakest_evidence' => $q->weakestEvidence()?->value,
            ])->values(),
            'meta' => [
                'methods' => array_map(
                    static fn (GeoCheckMethod $m): array => [
                        'value' => $m->value,
                        'badge' => $m->badge(),
                        'description' => $m->description(),
                        'supports_quantified_claim' => $m->supportsQuantifiedClaim(),
                    ],
                    GeoCheckMethod::cases(),
                ),
            ],
        ]);
    }

    public function storeQuery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:40'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'is_active' => ['sometimes', 'boolean'],
            'reference_pages' => ['sometimes', 'nullable', 'array'],
        ]);

        $query = GeoQuery::query()->create([
            'query' => $validated['query'],
            'locale' => $validated['locale'] ?? 'fr',
            'sector' => $validated['sector'] ?? null,
            'priority' => $validated['priority'] ?? 3,
            'is_active' => $validated['is_active'] ?? true,
            'reference_pages' => $validated['reference_pages'] ?? null,
        ]);

        return new JsonResponse([
            'data' => ['id' => $query->id, 'query' => $query->query, 'priority' => $query->priority],
        ], 201);
    }

    /** Historique ordonné du plus récent au plus ancien. */
    public function indexChecks(GeoQuery $geoQuery): JsonResponse
    {
        $checks = $geoQuery->checks()->orderByDesc('observed_at')->orderByDesc('id')->get();

        return new JsonResponse([
            'data' => $checks->map(fn (GeoCheck $c): array => $this->presentCheck($c))->values(),
            'meta' => [
                'total' => $checks->count(),
                'weakest_evidence' => $geoQuery->weakestEvidence()?->value,
            ],
        ]);
    }

    public function storeCheck(Request $request, GeoQuery $geoQuery): JsonResponse
    {
        $validated = $request->validate([
            // OBLIGATOIRE : c'est la garantie qu'aucune observation n'entre
            // sans dire à quel niveau de preuve elle se situe.
            'method' => ['required', Rule::in(GeoCheckMethod::values())],
            'engine' => ['required', Rule::in(['chatgpt', 'claude', 'gemini', 'perplexity', 'copilot', 'other'])],
            'is_present' => ['required', 'boolean'],
            'leopardo_mentioned' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'citation_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'observed_text' => ['sometimes', 'nullable', 'string'],
            'sources' => ['sometimes', 'nullable', 'array'],
            'evidence' => ['sometimes', 'nullable', 'string'],
            'observed_at' => ['sometimes', 'date'],
        ]);

        $method = GeoCheckMethod::from($validated['method']);

        // Un test ponctuel sans trace est invérifiable : refusé AVANT l'écriture
        // pour donner un message utile (la contrainte CHECK restant le filet).
        if ($method === GeoCheckMethod::SpotTest
            && empty($validated['observed_text'])
            && empty($validated['evidence'])) {
            return new JsonResponse([
                'error' => [
                    'code' => 'evidence_required',
                    'message' => 'Un test ponctuel doit porter une trace (observed_text ou evidence).',
                ],
            ], 422);
        }

        $actor = $request->user('super_admin_api');

        $check = GeoCheck::query()->create([
            'geo_query_id' => $geoQuery->id,
            'engine' => $validated['engine'],
            'method' => $method->value,
            'is_present' => $validated['is_present'],
            'leopardo_mentioned' => $validated['leopardo_mentioned'] ?? false,
            'position' => $validated['position'] ?? null,
            'citation_url' => $validated['citation_url'] ?? null,
            'observed_text' => $validated['observed_text'] ?? null,
            'sources' => $validated['sources'] ?? null,
            'evidence' => $validated['evidence'] ?? null,
            'checked_by' => $actor instanceof SuperAdmin ? $actor->id : null,
            'observed_at' => $validated['observed_at'] ?? now(),
        ]);

        return new JsonResponse(['data' => $this->presentCheck($check)], 201);
    }

    /** @return array<string, mixed> */
    private function presentCheck(GeoCheck $check): array
    {
        return [
            'id' => $check->id,
            'engine' => $check->engine,
            'method' => $check->method->value,
            // Le badge accompagne TOUJOURS la donnée : impossible d'afficher
            // un test ponctuel sans que son niveau de preuve soit visible.
            'method_badge' => $check->methodBadge(),
            'method_description' => $check->method->description(),
            'supports_quantified_claim' => $check->method->supportsQuantifiedClaim(),
            'is_present' => $check->is_present,
            'leopardo_mentioned' => $check->leopardo_mentioned,
            'position' => $check->position,
            'citation_url' => $check->citation_url,
            'observed_text' => $check->observed_text,
            'sources' => $check->sources,
            'evidence' => $check->evidence,
            'checked_by' => $check->checked_by,
            'observed_at' => $check->observed_at->toIso8601String(),
        ];
    }
}
