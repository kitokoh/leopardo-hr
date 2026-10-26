<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\SearchIntelligence\Domain\Enums\ContentStatus;
use App\Modules\SearchIntelligence\Domain\Models\ContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GSI-6 (#8517) — gestion éditoriale du contenu marketing.
 *
 * ```
 * GET   /content                            — liste filtrable
 * POST  /content                            — création (idée)
 * PATCH /content/{id}                        — édition (idée / brouillon seulement)
 * POST  /content/{id}/transition             — transition du workflow
 * GET   /content/export                     — export CSV
 * ```
 *
 * Rien n'est publié automatiquement : `published` n'est atteignable que depuis
 * `approved`, et la transition exige une URL cible. Le contenu publié reste
 * consultable mais ne s'édite plus sur place — il repasse par `to_update`.
 */
final class SearchIntelContentController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(array_map(
                static fn (ContentStatus $s): string => $s->value,
                ContentStatus::cases(),
            ))],
            'kind' => ['sometimes', 'string', 'max:40'],
            'sector' => ['sometimes', 'string', 'max:40'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ]);

        $query = ContentItem::query();

        foreach (['status', 'kind', 'sector', 'locale'] as $filter) {
            if (isset($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $items = $query->orderByDesc('id')->limit(200)->get();

        return new JsonResponse([
            'data' => $items->map(fn (ContentItem $i): array => $this->present($i))->values(),
            'meta' => ['total' => $items->count()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['landing', 'blog', 'guide', 'faq', 'comparison', 'social', 'video_script', 'cta'])],
            'body' => ['sometimes', 'nullable', 'string'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:40'],
            'target_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'keyword_id' => ['sometimes', 'nullable', 'integer'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $item = ContentItem::query()->create([
            'title' => $validated['title'],
            'kind' => $validated['kind'],
            'body' => $validated['body'] ?? null,
            'locale' => $validated['locale'] ?? 'fr',
            'sector' => $validated['sector'] ?? null,
            'target_url' => $validated['target_url'] ?? null,
            'keyword_id' => $validated['keyword_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            // Tout contenu naît « idée » : la suite est un acte éditorial.
            'status' => ContentStatus::Idea->value,
        ]);

        return new JsonResponse(['data' => $this->present($item)], 201);
    }

    public function update(Request $request, ContentItem $content): JsonResponse
    {
        if (! $content->status->isEditable()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'content_not_editable',
                    'message' => 'Seuls une idée ou un brouillon sont éditables ; repassez le contenu par « à mettre à jour ».',
                    'current_status' => $content->status->value,
                ],
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(['landing', 'blog', 'guide', 'faq', 'comparison', 'social', 'video_script', 'cta'])],
            'body' => ['sometimes', 'nullable', 'string'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:40'],
            'target_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'keyword_id' => ['sometimes', 'nullable', 'integer'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $content->fill($validated)->save();

        return new JsonResponse(['data' => $this->present($content)]);
    }

    public function transition(Request $request, ContentItem $content): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(
                static fn (ContentStatus $s): string => $s->value,
                ContentStatus::cases(),
            ))],
            'target_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        if (isset($validated['target_url'])) {
            $content->target_url = $validated['target_url'];
        }

        $from = $content->status->value;
        $target = ContentStatus::from($validated['status']);

        // `transitionTo` renvoie false sur transition illégale OU publication
        // sans URL — les deux refus sont distingués dans la réponse pour que
        // l'utilisateur sache laquelle des deux règles il a heurtée.
        if (! $content->transitionTo($target)) {
            $missingUrl = $target === ContentStatus::Published
                && ($content->target_url === null || trim((string) $content->target_url) === '');

            return new JsonResponse([
                'error' => [
                    'code' => $missingUrl ? 'publish_requires_url' : 'invalid_transition',
                    'message' => $missingUrl
                        ? 'La publication exige une URL cible.'
                        : 'Transition non autorisée depuis le statut courant.',
                    'current_status' => $from,
                    'allowed_transitions' => array_map(
                        static fn (ContentStatus $s): string => $s->value,
                        $content->status->allowedTransitions(),
                    ),
                ],
            ], 422);
        }

        $content->save();

        AuditLog::query()->create([
            'company_id' => null,
            'user_id' => null,
            'action' => 'search_intel.content_item.'.$target->value,
            'module' => 'search_intel',
            'auditable_type' => ContentItem::class,
            'auditable_id' => $content->id,
            'old_values' => ['status' => $from],
            'new_values' => ['status' => $target->value],
            'metadata' => null,
        ]);

        return new JsonResponse(['data' => $this->present($content)]);
    }

    /**
     * Export CSV. Champs volontairement plats et stables : c'est un livrable,
     * pas un dump technique.
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(array_map(
                static fn (ContentStatus $s): string => $s->value,
                ContentStatus::cases(),
            ))],
        ]);

        $query = ContentItem::query();

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $items = $query->orderBy('id')->get();

        return new StreamedResponse(function () use ($items): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['id', 'title', 'kind', 'status', 'locale', 'sector', 'target_url', 'published_at']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->title,
                    $item->kind,
                    $item->status->value,
                    $item->locale,
                    $item->sector,
                    $item->target_url,
                    $item->published_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="search-intel-content.csv"',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(ContentItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'kind' => $item->kind,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'locale' => $item->locale,
            'sector' => $item->sector,
            'target_url' => $item->target_url,
            'keyword_id' => $item->keyword_id,
            'scheduled_at' => $item->scheduled_at?->toIso8601String(),
            'published_at' => $item->published_at?->toIso8601String(),
            'allowed_transitions' => array_map(
                static fn (ContentStatus $s): string => $s->value,
                $item->status->allowedTransitions(),
            ),
        ];
    }
}
