<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Site;
use App\Http\Controllers\Controller;
use App\Modules\Pages\Application\Actions\CreatePage;
use App\Modules\Pages\Application\Actions\PublishPage;
use App\Modules\Pages\Application\Actions\ReplacePageBlocks;
use App\Modules\Pages\Application\Actions\UnpublishPage;
use App\Modules\Pages\Application\Actions\UpdatePage;
use App\Modules\Pages\Domain\Exceptions\PageBlockValidationException;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Interfaces\Api\V1\Requests\StorePageRequest;
use App\Modules\Pages\Interfaces\Api\V1\Requests\UpdatePageRequest;
use App\Modules\Pages\Interfaces\Api\V1\Resources\PageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion tenant des Pages d'établissement (BC PAGES, #8728).
 *
 * ## Isolation : 404, jamais 403
 *
 * Une Page d'un autre tenant répond **404**, pas 403. Un 403 confirmerait
 * l'existence de la ressource et permettrait d'énumérer le parc client — même
 * règle que la surface publique (`ADR-publication-publique.md` §2).
 *
 * ## Autorisation : la policy décide, le contrôleur ne devine pas
 *
 * `PagePolicy` (#8723) porte les règles : lecture = membre du tenant,
 * édition = `principal`/`rh`, publication = manager ou `principal`/`rh`. Le
 * contrôleur ne fait qu'appeler `authorize()`. Recopier la règle ici
 * produirait deux vérités qui divergent à la première évolution.
 *
 * ## Aperçu non indexable
 *
 * `GET /pages/{page}/preview` renvoie le rendu de la Page **quelle que soit sa
 * publication**, avec `X-Robots-Tag: noindex`. C'est le seul moyen de relire
 * un brouillon sans le publier — et le seul endroit où un brouillon est
 * lisible, donc il ne doit jamais être mis en cache par un intermédiaire.
 */
final class PageController extends Controller
{
    public function __construct(
        private readonly CreatePage $createPage,
        private readonly UpdatePage $updatePage,
        private readonly PublishPage $publishPage,
        private readonly UnpublishPage $unpublishPage,
        private readonly ReplacePageBlocks $replaceBlocks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $query = Page::query();

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', (string) $request->input('verification_status'));
        }

        if ($request->filled('q')) {
            $q = (string) $request->input('q');
            $query->where('slug', 'ilike', '%'.$q.'%');
        }

        $pages = $query
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => PageResource::collection($pages->items())->resolve($request),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'total' => $pages->total(),
            ],
        ]);
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        /** @var Employee $actor */
        $actor = $request->user();

        /** @var Site $site */
        $site = Site::query()->findOrFail($request->integer('site_id'));

        $page = $this->createPage->execute((string) $actor->company_id, $site, $request->validated());

        return (new PageResource($page->load('blocks')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, Page $page): JsonResponse
    {
        $this->assertSameTenant($request, $page);
        $this->authorize('view', $page);

        return (new PageResource($page->load('blocks')))->response();
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $this->assertSameTenant($request, $page);
        $this->authorize('update', $page);

        $updated = $this->updatePage->execute($page, $request->validated());

        return (new PageResource($updated->load('blocks')))->response();
    }

    public function publish(Request $request, Page $page): JsonResponse
    {
        $this->assertSameTenant($request, $page);
        $this->authorize('publish', $page);

        $published = $this->publishPage->execute($page);

        return (new PageResource($published->load('blocks')))->response();
    }

    public function unpublish(Request $request, Page $page): JsonResponse
    {
        $this->assertSameTenant($request, $page);
        $this->authorize('publish', $page);

        $unpublished = $this->unpublishPage->execute($page);

        return (new PageResource($unpublished->load('blocks')))->response();
    }

    /**
     * Remplace l'intégralité des blocs de la Page.
     *
     * Le payload est `{"blocks": [...]}`. Les erreurs de validation sont
     * renvoyées en 422 avec la liste complète indexée par position de bloc —
     * l'éditeur les affiche ensemble, pas une par une.
     */
    public function replaceBlocks(Request $request, Page $page): JsonResponse
    {
        $this->assertSameTenant($request, $page);
        $this->authorize('update', $page);

        $validated = $request->validate([
            'blocks' => ['present', 'array', 'max:200'],
            'blocks.*.id' => ['required', 'string', 'max:64'],
            'blocks.*.type' => ['required', 'string', 'max:80'],
            'blocks.*.schema_version' => ['nullable', 'integer', 'min:1'],
            'blocks.*.position' => ['nullable', 'integer', 'min:0'],
            'blocks.*.content' => ['required', 'array'],
            'blocks.*.locales' => ['nullable', 'array'],
        ]);

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $validated['blocks'];

        try {
            $this->replaceBlocks->execute($page, $blocks);
        } catch (PageBlockValidationException $exception) {
            return response()->json([
                'error' => 'PAGE_BLOCKS_INVALID',
                'message' => 'PAGE_BLOCKS_INVALID',
                'localized_message' => __('errors.PAGE_BLOCKS_INVALID'),
                'errors' => $exception->errors,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new PageResource($page->refresh()->load('blocks')))->response();
    }

    /**
     * Aperçu privé, non indexable, d'une Page en brouillon.
     *
     * `X-Robots-Tag: noindex` + `Cache-Control: no-store` : un aperçu ne doit
     * jamais être indexé ni servi depuis un cache partagé, sinon un brouillon
     * deviendrait public par un intermédiaire.
     */
    public function preview(Request $request, Page $page): JsonResponse
    {
        $this->assertSameTenant($request, $page);
        $this->authorize('view', $page);

        return (new PageResource($page->load('blocks')))
            ->response()
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * 404 pour une Page d'un autre tenant — jamais 403.
     *
     * Le binding implicite de Laravel résout `{page}` AVANT que le scope global
     * `BelongsToCompany` ne soit appliqué au contexte de la requête : la Page
     * d'un autre tenant est donc bien chargée, et c'est la policy qui la
     * refuserait — en 403. Un 403 confirmerait l'existence de la ressource et
     * permettrait d'énumérer le parc client. On tranche donc ici, avant la
     * policy, avec un 404 indiscernable d'un identifiant inexistant.
     */
    private function assertSameTenant(Request $request, Page $page): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ((string) $page->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }
}
