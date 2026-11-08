<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibrarySlug;
use App\Modules\Library\Interfaces\Api\V1\Requests\StoreLibraryResourceRequest;
use App\Modules\Library\Interfaces\Api\V1\Requests\UpdateLibraryResourceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des ressources de la bibliothèque par un tenant (surface privée).
 *
 * Routes tenant-scoped (auth Sanctum + middleware tenant) :
 *   GET    /library/resources                 → liste (toutes, y compris brouillons)
 *   POST   /library/resources                 → créer (brouillon)
 *   GET    /library/resources/{resource}      → détail
 *   PUT    /library/resources/{resource}      → modifier
 *   DELETE /library/resources/{resource}      → supprimer
 *   POST   /library/resources/{resource}/publish   → publier
 *   POST   /library/resources/{resource}/unpublish → dépublier
 *   POST   /library/resources/{resource}/archive   → archiver
 *
 * Workflow éditorial : brouillon → en attente → publié → archivé. Aucune
 * publication automatique. L'isolation tenant est garantie par le scope
 * `BelongsToCompany` et la policy `LibraryResourcePolicy`.
 */
class LibraryResourceController extends Controller
{
    /**
     * GET /library/resources — liste des ressources du tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LibraryResource::class);

        $query = LibraryResource::query()->orderByDesc('updated_at');

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->get()->map(fn (LibraryResource $resource): array => $this->payload($resource))->all(),
        ]);
    }

    /**
     * POST /library/resources — créer une ressource (statut brouillon).
     */
    public function store(StoreLibraryResourceRequest $request): JsonResponse
    {
        $this->authorize('create', LibraryResource::class);

        $data = $request->validated();
        $companyId = (string) $request->user()->company_id;

        $data['slug'] = LibrarySlug::uniqueFor(
            LibraryResource::class,
            $companyId,
            $data['slug'] ?? $this->slugify($data['title']),
        );
        $data['status'] = LibraryResourceStatus::Draft->value;
        $data['created_by'] = (int) $request->user()->id;

        $resource = LibraryResource::query()->create($data);

        return response()->json(['data' => $this->payload($resource)], 201);
    }

    /**
     * GET /library/resources/{resource} — détail d'une ressource du tenant.
     */
    public function show(LibraryResource $resource): JsonResponse
    {
        $this->authorize('view', $resource);

        return response()->json(['data' => $this->payload($resource)]);
    }

    /**
     * PUT /library/resources/{resource} — modifier une ressource.
     */
    public function update(UpdateLibraryResourceRequest $request, LibraryResource $resource): JsonResponse
    {
        $this->authorize('update', $resource);

        $data = $request->validated();

        if (isset($data['slug'])) {
            $data['slug'] = LibrarySlug::uniqueFor(
                LibraryResource::class,
                (string) $resource->company_id,
                $data['slug'],
                (int) $resource->id,
            );
        }

        $resource->update($data);

        return response()->json(['data' => $this->payload($resource->refresh())]);
    }

    /**
     * DELETE /library/resources/{resource} — supprimer une ressource.
     */
    public function destroy(LibraryResource $resource): JsonResponse
    {
        $this->authorize('delete', $resource);

        $resource->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * POST /library/resources/{resource}/publish — publier.
     */
    public function publish(Request $request, LibraryResource $resource): JsonResponse
    {
        $this->authorize('publish', $resource);

        $resource->update([
            'status' => LibraryResourceStatus::Published->value,
            'published_by' => (int) $request->user()->id,
            'published_at' => now(),
        ]);

        return response()->json(['data' => $this->payload($resource->refresh())]);
    }

    /**
     * POST /library/resources/{resource}/unpublish — dépublier (retour brouillon).
     */
    public function unpublish(LibraryResource $resource): JsonResponse
    {
        $this->authorize('publish', $resource);

        $resource->update(['status' => LibraryResourceStatus::Draft->value]);

        return response()->json(['data' => $this->payload($resource->refresh())]);
    }

    /**
     * POST /library/resources/{resource}/archive — archiver.
     */
    public function archive(LibraryResource $resource): JsonResponse
    {
        $this->authorize('publish', $resource);

        $resource->update(['status' => LibraryResourceStatus::Archived->value]);

        return response()->json(['data' => $this->payload($resource->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LibraryResource $resource): array
    {
        return [
            'id' => $resource->id,
            'slug' => $resource->slug,
            'title' => $resource->title,
            'description' => $resource->description,
            'document_type' => $resource->document_type->value,
            'language' => $resource->language,
            'access_model' => $resource->access_model->value,
            'status' => $resource->status->value,
            'price_minor' => $resource->price_minor,
            'currency' => $resource->currency,
            'license_id' => $resource->license_id,
            'rights_holder' => $resource->rights_holder,
            'allow_download' => $resource->allow_download,
            'featured' => $resource->featured,
            'is_new' => $resource->is_new,
            'published_at' => $resource->published_at?->toIso8601String(),
        ];
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '', '-'));

        return $slug === '' ? 'ressource' : $slug;
    }
}
