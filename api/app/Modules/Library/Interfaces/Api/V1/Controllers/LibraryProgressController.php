<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Models\LibraryReadingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Progression de lecture persistée côté serveur (#9242).
 *
 * Routes tenant-scoped (auth Sanctum + middleware tenant) :
 *   GET  /library/progress            → toutes les progressions de l'employé
 *   GET  /library/progress/{slug}     → progression d'une ressource (ou null)
 *   PUT  /library/progress/{slug}     → enregistrer une position
 *   DELETE /library/progress/{slug}   → oublier une ressource
 *
 * Contrat :
 * - **une ligne par (employé, ressource)** : un `PUT` répété met à jour la
 *   même ligne (upsert), il ne crée pas d'historique ;
 * - **page bornée** : la position est ramenée dans `[1, total_pages]` avant
 *   écriture (même règle que `clampProgress` côté frontend) — la contrainte
 *   CHECK de la table est la seconde barrière ;
 * - **isolation** : la requête est scopée par `company_id` (trait
 *   `BelongsToCompany`) ET par `employee_id` — un employé ne voit jamais la
 *   progression d'un collègue ;
 * - **anonymes** : ces routes exigent l'authentification ; le `localStorage`
 *   reste la source de vérité pour les visiteurs anonymes.
 */
class LibraryProgressController extends Controller
{
    /**
     * GET /library/progress — progressions de l'employé, les plus récentes d'abord.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $items = LibraryReadingProgress::query()
            ->where('employee_id', $actor->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (LibraryReadingProgress $row): array => $this->payload($row))
            ->values()
            ->all();

        return response()->json(['data' => $items]);
    }

    /**
     * GET /library/progress/{slug} — progression d'une ressource, ou `null`.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $actor = $this->actor($request);

        $row = LibraryReadingProgress::query()
            ->where('employee_id', $actor->id)
            ->where('resource_slug', $slug)
            ->first();

        return response()->json(['data' => $row instanceof LibraryReadingProgress ? $this->payload($row) : null]);
    }

    /**
     * PUT /library/progress/{slug} — enregistre (ou met à jour) une position.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $actor = $this->actor($request);

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'total_pages' => ['sometimes', 'integer', 'min:0'],
        ]);

        $totalPages = (int) ($validated['total_pages'] ?? 0);
        $page = $this->clamp((int) $validated['page'], $totalPages);

        $row = LibraryReadingProgress::query()->updateOrCreate(
            [
                'company_id' => $actor->company_id,
                'employee_id' => $actor->id,
                'resource_slug' => $slug,
            ],
            [
                'page' => $page,
                'total_pages' => $totalPages,
            ],
        );

        return response()->json(['data' => $this->payload($row)]);
    }

    /**
     * DELETE /library/progress/{slug} — oublie la progression d'une ressource.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $actor = $this->actor($request);

        LibraryReadingProgress::query()
            ->where('employee_id', $actor->id)
            ->where('resource_slug', $slug)
            ->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Ramène une page dans `[1, totalPages]` (ou `>= 1` si le total est inconnu).
     *
     * Miroir exact de `clampProgress` côté frontend : la même position doit
     * produire la même valeur des deux côtés.
     */
    private function clamp(int $page, int $totalPages): int
    {
        if ($page < 1) {
            return 1;
        }

        if ($totalPages > 0 && $page > $totalPages) {
            return $totalPages;
        }

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LibraryReadingProgress $row): array
    {
        return [
            'slug' => $row->resource_slug,
            'page' => $row->page,
            'total_pages' => $row->total_pages,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    private function actor(Request $request): Employee
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return $actor;
    }
}
