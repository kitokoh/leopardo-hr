<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryCategory;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Domain\Support\LibraryAccessPolicy;
use App\Modules\Library\Infrastructure\Services\LibraryFileService;
use App\Modules\Library\Infrastructure\Services\LibraryPublicCache;
use App\Modules\Library\Infrastructure\Services\LibrarySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bibliothèque numérique PUBLIQUE (Leopardo Library).
 *
 * Routes isolées (`throttle:shop-public`, SANS auth) :
 *   GET /public/library                       → catalogue public
 *   GET /public/library/{slug}                → fiche publique
 *   GET /public/library/{slug}/download       → URL temporaire signée (ou 403)
 *   GET /public/library/{slug}/file           → flux protégé (disque local)
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
    public function __construct(
        private readonly LibraryFileService $files,
        private readonly LibrarySearchService $search,
    ) {}

    /**
     * GET /public/library — catalogue public (catégories + ressources publiées).
     * Filtres optionnels : `?category=`, `?language=`, `?access=`, `?q=`.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = LibraryPublicCache::remember(fn (): array => $this->buildSnapshot());

        $filters = [
            'query' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
            'language' => trim((string) $request->query('language', '')),
            'access' => trim((string) $request->query('access', '')),
            'type' => trim((string) $request->query('type', '')),
            'free_only' => $request->boolean('free_only'),
        ];

        // Recherche PLEIN TEXTE côté serveur (titre/description/extrait/éditeur/
        // détenteur des droits) + filtres, sur le catalogue public uniquement.
        // Le snapshot en cache ne sert qu'aux catégories : la recherche doit
        // rester exacte et ne pas dépendre d'un snapshot périmé.
        $resources = array_map(
            fn (LibraryResource $resource): array => $this->resourcePayload($resource),
            $this->search->search($filters),
        );

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
        $resource = $this->findPublished($slug);

        $policy = new LibraryAccessPolicy;

        return response()->json([
            'data' => [
                ...$this->resourcePayload($resource),
                'can_read' => $policy->canRead($resource),
                'can_download' => $policy->canDownload($resource),
                'related' => array_map(
                    fn (LibraryResource $item): array => [
                        'slug' => $item->slug,
                        'title' => $item->title,
                        'access_model' => $item->access_model->value,
                    ],
                    $this->search->related($resource),
                ),
            ],
        ]);
    }

    /**
     * GET /public/library/{slug}/download — URL temporaire signée.
     *
     * ⚠️ GARDE CÔTÉ SERVEUR : le droit de téléchargement est vérifié ici. Le
     * fichier n'est JAMAIS exposé par une URL publique permanente.
     *
     * Deux voies selon le driver de stockage :
     *  - S3-compatible → URL présignée native (TTL `library.download_url_ttl`) ;
     *  - disque local privé → URL interne signée HMAC vers `/file`, servie par
     *    un flux protégé (le fichier n'est jamais dans le webroot).
     *
     * Si le stockage n'est pas configuré, on renvoie 501 plutôt que de servir
     * un faux fichier.
     */
    public function download(string $slug): JsonResponse
    {
        $resource = $this->findPublished($slug);

        $policy = new LibraryAccessPolicy;

        if (! $policy->canDownload($resource)) {
            return response()->json(
                ['error' => 'download_forbidden', 'reason' => $resource->access_model->value],
                403
            );
        }

        if ($resource->file_key === null || ! $this->files->isConfigured()) {
            return response()->json(
                [
                    'error' => 'storage_not_configured',
                    'message' => __('library.storage_not_configured'),
                ],
                501
            );
        }

        $ttl = (int) config('library.download_url_ttl', 300);
        $resolved = $this->files->resolve($resource, $ttl);

        // Voie 1 : URL présignée native du stockage objet.
        if (is_string($resolved['url']) && $resolved['url'] !== '') {
            return response()->json([
                'data' => [
                    'url' => $resolved['url'],
                    'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
                    'format' => $resolved['format'],
                    'size' => $resolved['size'],
                ],
            ]);
        }

        // Voie 2 : disque local privé → URL interne signée (HMAC).
        $expiresAt = now()->addSeconds($ttl);
        $signature = hash_hmac(
            'sha256',
            $resource->slug.'|'.$expiresAt->timestamp,
            (string) config('app.key')
        );

        return response()->json([
            'data' => [
                'url' => '/public/library/'.$resource->slug.'/file?expires='.$expiresAt->timestamp.'&signature='.$signature,
                'expires_at' => $expiresAt->toIso8601String(),
                'format' => $resolved['format'],
                'size' => $resolved['size'],
            ],
        ]);
    }

    /**
     * GET /public/library/{slug}/file — flux protégé du fichier.
     *
     * Réservé au driver local (le stockage objet sert directement l'URL
     * présignée). Double garde : signature HMAC valide ET non expirée, puis
     * `LibraryAccessPolicy` revérifiée. Le fichier est streamé depuis un disque
     * hors webroot — jamais d'URL publique permanente.
     */
    public function file(Request $request, string $slug): StreamedResponse|JsonResponse
    {
        $resource = $this->findPublished($slug);

        $expires = (int) $request->query('expires', 0);
        $signature = (string) $request->query('signature', '');

        $expected = hash_hmac(
            'sha256',
            $resource->slug.'|'.$expires,
            (string) config('app.key')
        );

        if ($signature === '' || ! hash_equals($expected, $signature) || $expires < now()->timestamp) {
            return response()->json(['error' => 'invalid_or_expired_signature'], 403);
        }

        $policy = new LibraryAccessPolicy;

        if (! $policy->canDownload($resource)) {
            return response()->json(
                ['error' => 'download_forbidden', 'reason' => $resource->access_model->value],
                403
            );
        }

        $resolved = $this->files->resolve($resource, 60);
        $stream = $resolved['stream'];

        if (! is_resource($stream)) {
            return response()->json(
                [
                    'error' => 'storage_not_configured',
                    'message' => __('library.storage_not_configured'),
                ],
                501
            );
        }

        $format = (string) ($resolved['format'] ?? 'bin');
        $filename = $resource->slug.'.'.$format;

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => $this->mimeFor($format),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Résout une ressource publiée du catalogue public, ou 404.
     */
    private function findPublished(string $slug): LibraryResource
    {
        $resource = LibraryResource::query()
            ->whereNull('company_id')
            ->where('slug', $slug)
            ->where('status', LibraryResourceStatus::Published->value)
            ->first();

        if (! $resource instanceof LibraryResource) {
            abort(404);
        }

        return $resource;
    }

    private function mimeFor(string $format): string
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'epub' => 'application/epub+zip',
            'txt' => 'text/plain; charset=UTF-8',
            'html' => 'text/html; charset=UTF-8',
            default => 'application/octet-stream',
        };
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
}
