<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Modules\SearchIntelligence\Domain\Models\Competitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GSI-7 (#8518) — veille concurrentielle à sources datées obligatoires.
 *
 * ```
 * GET   /competitors                 — liste + comparaison côte à côte
 * POST  /competitors                 — création
 * PATCH /competitors/{id}            — mise à jour
 * POST  /competitors/{id}/review     — acte de revue (last_reviewed_at)
 * ```
 *
 * La règle structurante : **toute affirmation tarifaire exige une source
 * datée**. Une note de tarification sans `pricing_sources` (au moins une, avec
 * `url` ET `observed_at`) est refusée en 422. Une grille tarifaire fausse est
 * un argument commercial dangereux : on préfère ne rien dire que dire un prix
 * qu'on n'a pas lu.
 *
 * `GET /competitors` sert aussi de comparaison côte à côte : les champs
 * `positioning`, `value_proposition` et `public_features` sont alignés dans le
 * même tableau, ce qui évite un second endpoint dédié.
 */
final class SearchIntelCompetitorController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'market' => ['sometimes', 'string', 'max:80'],
            'fresh_only' => ['sometimes', 'boolean'],
        ]);

        $query = Competitor::query();

        if (isset($validated['market'])) {
            $query->where('market', $validated['market']);
        }

        if (($validated['fresh_only'] ?? false) === true) {
            $query->fresh();
        }

        $items = $query->orderByDesc('observed_at')->limit(100)->get();

        return new JsonResponse([
            'data' => $items->map(fn (Competitor $c): array => $this->present($c))->values(),
            'meta' => [
                'total' => $items->count(),
                // Une veille de plus de 90 jours est signalée comme ancienne,
                // pas silencieusement présentée comme actuelle.
                'stale_before' => now()->subDays(90)->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $competitor = new Competitor;
        $competitor->fill($validated);
        $competitor->last_reviewed_at = now();

        if (! $competitor->hasSourcedPricing()) {
            return $this->pricingRefusal();
        }

        $competitor->save();

        return new JsonResponse(['data' => $this->present($competitor)], 201);
    }

    public function update(Request $request, Competitor $competitor): JsonResponse
    {
        $validated = $this->validatePayload($request, partial: true);

        $competitor->fill($validated);

        if (! $competitor->hasSourcedPricing()) {
            return $this->pricingRefusal();
        }

        $competitor->save();

        return new JsonResponse(['data' => $this->present($competitor)]);
    }

    /**
     * Acte de revue : `last_reviewed_at` avance, `observed_at` NE BOUGE PAS.
     * La distinction est essentielle — relire une veille n'est pas la refaire,
     * et prétendre le contraire reviendrait à rajeunir une donnée périmée.
     */
    public function review(Competitor $competitor): JsonResponse
    {
        $competitor->markReviewed();
        $competitor->save();

        return new JsonResponse(['data' => $this->present($competitor)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'market' => ['sometimes', 'nullable', 'string', 'max:80'],
            'positioning' => ['sometimes', 'nullable', 'string'],
            'value_proposition' => ['sometimes', 'nullable', 'string'],
            'public_features' => ['sometimes', 'nullable', 'array'],
            'pricing_notes' => ['sometimes', 'nullable', 'string'],
            'pricing_sources' => ['sometimes', 'nullable', 'array'],
            'pricing_sources.*.url' => ['required_with:pricing_sources', 'string', 'max:2048'],
            'pricing_sources.*.label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'pricing_sources.*.observed_at' => ['required_with:pricing_sources', 'date'],
            'source_url' => [$required, 'string', 'max:2048'],
            'source_label' => [$required, 'string', 'max:120'],
            'observed_at' => [$required, 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    private function pricingRefusal(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'pricing_requires_sources',
                'message' => 'Toute note de tarification exige au moins une source datée (url + observed_at).',
            ],
        ], 422);
    }

    /** @return array<string, mixed> */
    private function present(Competitor $competitor): array
    {
        return [
            'id' => $competitor->id,
            'name' => $competitor->name,
            'website_url' => $competitor->website_url,
            'market' => $competitor->market,
            'positioning' => $competitor->positioning,
            'value_proposition' => $competitor->value_proposition,
            'public_features' => $competitor->public_features,
            'pricing_notes' => $competitor->pricing_notes,
            'pricing_sources' => $competitor->pricing_sources,
            'source_url' => $competitor->source_url,
            'source_label' => $competitor->source_label,
            'observed_at' => $competitor->observed_at->toIso8601String(),
            'last_reviewed_at' => $competitor->last_reviewed_at?->toIso8601String(),
            'notes' => $competitor->notes,
            // Fraîcheur calculée côté serveur : le front n'a pas à réimplémenter
            // la règle des 90 jours.
            'is_stale' => $competitor->observed_at->lessThan(now()->subDays(90)),
        ];
    }
}
