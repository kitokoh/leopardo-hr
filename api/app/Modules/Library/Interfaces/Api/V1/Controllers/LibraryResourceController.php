<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Library\Application\Actions\TransitionLibraryResourceStatus;
use App\Modules\Library\Application\Actions\UploadLibraryResourceFile;
use App\Modules\Library\Application\Queries\ListLibraryResourcesQuery;
use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Enums\LibraryDocumentType;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibrarySlug;
use App\Modules\Library\Infrastructure\Services\LibraryFileService;
use App\Modules\Library\Interfaces\Api\V1\Requests\StoreLibraryResourceRequest;
use App\Modules\Library\Interfaces\Api\V1\Requests\UpdateLibraryResourceRequest;
use App\Modules\Library\Interfaces\Api\V1\Requests\UploadLibraryResourceFileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

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
 *   POST   /library/resources/{resource}/file      → téléverser le fichier
 *
 * Workflow éditorial : brouillon → en attente → publié → archivé. Aucune
 * publication automatique. L'isolation tenant est garantie par le scope
 * `BelongsToCompany` et la policy `LibraryResourcePolicy`.
 */
class LibraryResourceController extends Controller
{
    public function __construct(
        private readonly TransitionLibraryResourceStatus $transition,
        private readonly UploadLibraryResourceFile $uploadFile,
        private readonly LibraryFileService $files,
        private readonly ListLibraryResourcesQuery $listResources,
    ) {}

    /**
     * GET /library/resources — liste paginée et filtrée des ressources du tenant.
     *
     * Filtres allowlistés : `status`, `access_model`, `document_type`,
     * `language`, `q` (recherche titre/slug/éditeur/détenteur). Pagination
     * `per_page` (1–100, défaut 20) + `page`. L'isolation tenant est portée par
     * le scope `BelongsToCompany` : aucune ressource d'un autre tenant n'est
     * listée, et le catalogue public (`company_id` NULL) n'apparaît pas ici.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LibraryResource::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in($this->enumValues(LibraryResourceStatus::cases()))],
            'access_model' => ['nullable', 'string', Rule::in($this->enumValues(LibraryAccessModel::cases()))],
            'document_type' => ['nullable', 'string', Rule::in($this->enumValues(LibraryDocumentType::cases()))],
            'language' => ['nullable', 'string', 'size:2'],
            'q' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->listResources->execute($validated);

        return response()->json([
            'data' => array_map(
                fn (LibraryResource $resource): array => $this->payload($resource),
                $paginator->items(),
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /library/resources — créer une ressource (statut brouillon).
     */
    public function store(StoreLibraryResourceRequest $request): JsonResponse
    {
        $this->authorize('create', LibraryResource::class);

        /** @var Employee $actor */
        $actor = $request->user();
        $data = $request->validated();
        $companyId = (string) $actor->company_id;

        $data['slug'] = LibrarySlug::uniqueFor(
            LibraryResource::class,
            $companyId,
            $data['slug'] ?? $this->slugify($data['title']),
        );
        $data['status'] = LibraryResourceStatus::Draft->value;
        $data['created_by'] = (int) $actor->id;

        $resource = LibraryResource::query()->create($data);

        return response()->json(['data' => $this->payload($resource)], 201);
    }

    /**
     * GET /library/resources/{resource} — détail d'une ressource du tenant.
     */
    public function show(LibraryResource $resource): JsonResponse
    {
        $this->ensureSameTenant($resource);
        $this->authorize('view', $resource);

        return response()->json(['data' => $this->payload($resource)]);
    }

    /**
     * PUT /library/resources/{resource} — modifier une ressource.
     */
    public function update(UpdateLibraryResourceRequest $request, LibraryResource $resource): JsonResponse
    {
        $this->ensureSameTenant($resource);
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
        $this->ensureSameTenant($resource);
        $this->authorize('delete', $resource);

        $resource->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * POST /library/resources/{resource}/publish — publier.
     */
    public function publish(Request $request, LibraryResource $resource): JsonResponse
    {
        $this->ensureSameTenant($resource);
        $this->authorize('publish', $resource);

        /** @var Employee $actor */
        $actor = $request->user();
        $resource = $this->transition->execute($resource, LibraryResourceStatus::Published, (int) $actor->id);

        return response()->json(['data' => $this->payload($resource)]);
    }

    /**
     * POST /library/resources/{resource}/unpublish — dépublier (retour brouillon).
     */
    public function unpublish(LibraryResource $resource): JsonResponse
    {
        $this->ensureSameTenant($resource);
        $this->authorize('publish', $resource);

        $resource = $this->transition->execute($resource, LibraryResourceStatus::Draft);

        return response()->json(['data' => $this->payload($resource)]);
    }

    /**
     * POST /library/resources/{resource}/archive — archiver.
     */
    public function archive(LibraryResource $resource): JsonResponse
    {
        $this->ensureSameTenant($resource);
        $this->authorize('publish', $resource);

        $resource = $this->transition->execute($resource, LibraryResourceStatus::Archived);

        return response()->json(['data' => $this->payload($resource)]);
    }

    /**
     * POST /library/resources/{resource}/file — téléverser le fichier.
     *
     * Validation MIME réelle + taille + clé de stockage isolée par tenant
     * (voir `LibraryFileService`). Si le stockage n'est pas configuré, on
     * renvoie 501 avec un message clair — jamais de faux fichier.
     */
    public function uploadFile(UploadLibraryResourceFileRequest $request, LibraryResource $resource): JsonResponse
    {
        $this->ensureSameTenant($resource);
        $this->authorize('update', $resource);

        if (! $this->files->isConfigured()) {
            return response()->json(
                [
                    'error' => 'storage_not_configured',
                    'message' => __('library.storage_not_configured'),
                ],
                501
            );
        }

        $file = $request->file('file');

        if ($file === null) {
            return response()->json(['error' => 'file_required'], 422);
        }

        try {
            $resource = $this->uploadFile->execute($resource, $file);
        } catch (RuntimeException $exception) {
            $code = $exception->getMessage();
            $status = $code === 'library_file_too_large' ? 413 : 422;

            return response()->json(
                [
                    'error' => $code,
                    'message' => __('library.'.$code),
                ],
                $status
            );
        }

        return response()->json(['data' => $this->payload($resource)]);
    }

    /**
     * Garde #5585 — une ressource d'un AUTRE tenant est « introuvable » (404),
     * jamais « interdite » (403) : un 403 confirmerait son existence et
     * permettrait d'énumérer les identifiants des autres sociétés.
     *
     * Le scope `BelongsToCompany` ne s'applique pas au route-model binding
     * (aucun contexte tenant n'est encore posé à ce stade) : la garde est donc
     * explicite ici, avant toute policy.
     */
    private function ensureSameTenant(LibraryResource $resource): void
    {
        $actor = request()->user();

        if (! $actor instanceof Employee) {
            abort(404);
        }

        if ((string) $resource->company_id !== (string) $actor->company_id) {
            abort(404);
        }
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
            'file_format' => $resource->file_format,
            'file_size' => $resource->file_size,
            'has_file' => $resource->file_key !== null,
        ];
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '', '-'));

        return $slug === '' ? 'ressource' : $slug;
    }

    /**
     * Valeurs d'un enum, pour les règles `Rule::in` (allowlist fail-closed).
     *
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(
            static fn (\BackedEnum $case): string => (string) $case->value,
            $cases,
        );
    }
}
