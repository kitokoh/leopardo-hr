<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Library\Application\Actions\RecordLibraryReadingProgress;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryReadingProgress;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Interfaces\Api\V1\Requests\StoreLibraryReadingProgressRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Progression de lecture persistée côté serveur (BC-40 LIBRARY).
 *
 * Routes tenant-scoped (auth Sanctum + middleware tenant) :
 *   GET  /library/progress            → toutes mes progressions (reprise de lecture)
 *   GET  /library/progress/{slug}     → ma progression sur une ressource
 *   PUT  /library/progress/{slug}     → enregistrer ma position
 *
 * ⚠️ ISOLATION STRICTE :
 *  - `employee_id` vient TOUJOURS de la session (`$request->user()`), jamais du
 *    payload — un employé ne peut pas écrire la progression d'un autre ;
 *  - le scope `BelongsToCompany` du modèle borne chaque requête au tenant
 *    actif : aucune progression d'un autre tenant n'est lisible ;
 *  - la ressource est résolue dans le périmètre VISIBLE par le tenant
 *    (catalogue public `company_id` NULL publié, ou ressource propre au
 *    tenant) — un slug d'un autre tenant renvoie 404, jamais une fuite.
 *
 * Le client (vitrine) synchronise ici quand un utilisateur est connecté et
 * retombe sur `localStorage` si l'API est indisponible : la progression n'est
 * jamais perdue, seulement non partagée entre appareils.
 */
class LibraryReadingProgressController extends Controller
{
    public function __construct(
        private readonly RecordLibraryReadingProgress $record,
    ) {}

    /**
     * GET /library/progress — mes progressions, les plus récentes d'abord.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LibraryReadingProgress::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $rows = LibraryReadingProgress::query()
            ->where('employee_id', (int) $actor->id)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (LibraryReadingProgress $row): array => $this->payload($row))->all(),
        ]);
    }

    /**
     * GET /library/progress/{slug} — ma progression sur une ressource.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $this->authorize('viewAny', LibraryReadingProgress::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $resource = $this->findVisibleResource($slug);

        $row = LibraryReadingProgress::query()
            ->where('employee_id', (int) $actor->id)
            ->where('resource_id', (int) $resource->id)
            ->first();

        return response()->json([
            'data' => $row instanceof LibraryReadingProgress ? $this->payload($row) : null,
        ]);
    }

    /**
     * PUT /library/progress/{slug} — enregistrer ma position de lecture.
     */
    public function update(StoreLibraryReadingProgressRequest $request, string $slug): JsonResponse
    {
        $this->authorize('create', LibraryReadingProgress::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $resource = $this->findVisibleResource($slug);

        $data = $request->validated();

        $progress = $this->record->execute(
            $resource,
            (int) $actor->id,
            (int) $data['page'],
            isset($data['total_pages']) ? (int) $data['total_pages'] : null,
        );

        return response()->json(['data' => $this->payload($progress)]);
    }

    /**
     * Résout une ressource VISIBLE par le tenant : catalogue public publié
     * (`company_id` NULL) ou ressource propre au tenant. 404 sinon.
     */
    private function findVisibleResource(string $slug): LibraryResource
    {
        $resource = LibraryResource::query()
            ->where('slug', $slug)
            ->where(function ($query): void {
                $query->where(function ($public): void {
                    $public->whereNull('company_id')
                        ->where('status', LibraryResourceStatus::Published->value);
                })->orWhereNotNull('company_id');
            })
            ->first();

        if (! $resource instanceof LibraryResource) {
            abort(404);
        }

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LibraryReadingProgress $row): array
    {
        return [
            'resource_id' => $row->resource_id,
            'page' => $row->page,
            'total_pages' => $row->total_pages,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
