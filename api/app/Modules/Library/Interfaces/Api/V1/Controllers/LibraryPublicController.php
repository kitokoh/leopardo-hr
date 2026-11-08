<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryCategory;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibraryAccessPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Bibliothèque numérique PUBLIQUE (Leopardo Library).
 *
 * Routes isolées (`throttle:shop-public`, SANS auth) :
 *   GET /public/library                       → catalogue public
 *   GET /public/library/{slug}                → fiche publique
 *   GET /public/library/{slug}/download       → URL temporaire signée (ou 403)
 *
 * DTO public STRICT — jamais de modèles internes : seuls titre, slug,
 * description, auteur, catégorie, langue, type, modèle d'accès, licence et
 * métadonnées bibliographiques publiques sont exposés. Ni `company_id`, ni
 * `file_key`, ni `meta` interne, ni `created_by`.
 *
 * Seules les ressources `published` du catalogue public Leopardo
 * (`company_id` NULL) sont servies ici. Les contenus privés d'un tenant sont
 * servis par la surface tenant authentifiée, jamais par cette route.
 *
 * Cache Redis TTL (`library.public_cache_ttl`, 600 s), invalidé à la
 * publication/dépublication.
 */
class LibraryPublicController extends Controller
{
    /**
     * GET /public/library — catalogue public (catégories + ressources publiées).
     * Filtres optionnels : `?category=`, `?language=`, `?access=`, `?q=`.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $snapshot */
        // tenant-cache:shared — catalogue public GLOBAL Leopardo (company_id NULL),
        // agrégat cross-tenant voulu : aucune donnée d'un tenant privé n'y entre.
        $snapshot = Cache::remember(
            'library:public:snapshot',
            (int) config('library.public_cache_ttl', 600),
            fn (): array => $this->buildSnapshot()
        );

        $category = trim((string) $request->query('category', ''));
        $language = trim((string) $request->query('language', ''));
        $access = trim((string) $request->query('access', ''));
        $query = trim((string) $request->query('q', ''));

        $resources = $snapshot['resources'];

        if ($category !== '') {
            $resources = array_values(array_filter(
                $resources,
                fn (array $item): bool => ($item['category']['slug'] ?? null) === $category
            ));
        }
        if ($language !== '') {
            $resources = array_values(array_filter(
                $resources,
                fn (array $item): bool => $item['language'] === $language
            ));
        }
        if ($access !== '') {
            $resources = array_values(array_filter(
                $resources,
                fn (array $item): bool => $item['access_model'] === $access
            ));
        }
        if ($query !== '') {
            $needle = mb_strtolower($query);
            $resources = array_values(array_filter(
                $resources,
                fn (array $item): bool => str_contains(mb_strtolower($item['title'].' '.$item['author']), $needle)
            ));
        }

        return response()->json([
            'data' => [
                'categories' => $snapshot['categories'],
                'resources' => $resources,
            ],
        ]);
    }

    /**
     * GET /public/library/{slug} — fiche publique d'une ressource publiée
     * (404 si inconnue, non publiée ou privée d'un tenant).
     */
    public function show(string $slug): JsonResponse
    {
        $resource = LibraryResource::query()
            ->whereNull('company_id')
            ->where('slug', $slug)
            ->where('status', LibraryResourceStatus::Published->value)
            ->first();

        if (! $resource instanceof LibraryResource) {
            abort(404);
        }

        $policy = new LibraryAccessPolicy;

        return response()->json([
            'data' => [
                ...$this->resourcePayload($resource),
                'can_read' => $policy->canRead($resource),
                'can_download' => $policy->canDownload($resource),
                'related' => $this->relatedPayload($resource),
            ],
        ]);
    }

    /**
     * GET /public/library/{slug}/download — URL temporaire signée.
     *
     * ⚠️ GARDE CÔTÉ SERVEUR : le droit de téléchargement est vérifié ici. Le
     * fichier n'est JAMAIS exposé par une URL publique permanente. Si le
     * stockage objet n'est pas configuré, on renvoie 501 plutôt que de servir
     * un faux fichier.
     */
    public function download(string $slug): JsonResponse
    {
        $resource = LibraryResource::query()
            ->whereNull('company_id')
            ->where('slug', $slug)
            ->where('status', LibraryResourceStatus::Published->value)
            ->first();

        if (! $resource instanceof LibraryResource) {
            abort(404);
        }

        $policy = new LibraryAccessPolicy;

        if (! $policy->canDownload($resource)) {
            return response()->json(
                ['error' => 'download_forbidden', 'reason' => $resource->access_model->value],
                403
            );
        }

        if ($resource->file_key === null) {
            return response()->json(
                [
                    'error' => 'storage_not_configured',
                    'message' => 'Le stockage objet n’est pas encore configuré pour cette ressource.',
                ],
                501
            );
        }

        // URL temporaire signée (TTL `library.download_url_ttl`).
        $ttl = (int) config('library.download_url_ttl', 300);
        $expiresAt = now()->addSeconds($ttl);
        $signature = hash_hmac('sha256', $resource->slug.'|'.$expiresAt->timestamp, (string) config('app.key'));

        return response()->json([
            'data' => [
                'url' => '/public/library/'.$resource->slug.'/file?expires='.$expiresAt->timestamp.'&signature='.$signature,
                'expires_at' => $expiresAt->toIso8601String(),
                'format' => $resource->file_format,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(): array
    {
        $categories = LibraryCategory::query()
            ->whereNull('company_id')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $resources = LibraryResource::query()
            ->whereNull('company_id')
            ->where('status', LibraryResourceStatus::Published->value)
            ->orderBy('title')
            ->get();

        return [
            'categories' => $categories
                ->map(fn (LibraryCategory $category): array => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'icon' => $category->icon,
                ])
                ->values()
                ->all(),
            'resources' => $resources
                ->map(fn (LibraryResource $resource): array => $this->resourcePayload($resource))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resourcePayload(LibraryResource $resource): array
    {
        return [
            'slug' => $resource->slug,
            'title' => $resource->title,
            'description' => $resource->description,
            'excerpt' => $resource->excerpt,
            'author' => $resource->rights_holder,
            'publisher' => $resource->publisher,
            'published_year' => $resource->published_year,
            'pages' => $resource->pages,
            'language' => $resource->language,
            'document_type' => $resource->document_type->value,
            'access_model' => $resource->access_model->value,
            'price_minor' => $resource->price_minor,
            'currency' => $resource->currency,
            'license' => [
                'id' => $resource->license_id,
                'holder' => $resource->rights_holder,
                'allow_download' => $resource->allow_download,
                'allow_redistribution' => $resource->allow_redistribution,
            ],
            'featured' => $resource->featured,
            'is_new' => $resource->is_new,
            'category' => $resource->category !== null
                ? ['slug' => $resource->category->slug, 'name' => $resource->category->name]
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedPayload(LibraryResource $resource): array
    {
        if ($resource->category_id === null) {
            return [];
        }

        return LibraryResource::query()
            ->whereNull('company_id')
            ->where('status', LibraryResourceStatus::Published->value)
            ->where('category_id', $resource->category_id)
            ->where('id', '!=', $resource->id)
            ->orderBy('title')
            ->limit(3)
            ->get()
            ->map(fn (LibraryResource $item): array => [
                'slug' => $item->slug,
                'title' => $item->title,
                'access_model' => $item->access_model->value,
            ])
            ->values()
            ->all();
    }
}
