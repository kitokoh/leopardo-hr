<?php

declare(strict_types=1);

namespace App\Modules\Branding\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branding\Application\Actions\CreateBrandAction;
use App\Modules\Branding\Application\Actions\PublishBrandAction;
use App\Modules\Branding\Application\Actions\SuspendBrandAction;
use App\Modules\Branding\Application\Actions\UpdateBrandAction;
use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Interfaces\Api\V1\Requests\StoreBrandRequest;
use App\Modules\Branding\Interfaces\Api\V1\Requests\UpdateBrandRequest;
use App\Modules\Branding\Interfaces\Api\V1\Resources\BrandResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestion des Brands du tenant courant (BC-41, #9578).
 *
 * ## Isolation tenant
 *
 * Toutes les lectures passent par le scope global `BelongsToCompany` : une
 * Brand d'un autre tenant est invisible, et un `find()` sur un ID étranger
 * renvoie 404 — pas 403, qui confirmerait l'existence de la ressource.
 *
 * ## Ce que le contrôleur ne fait pas
 *
 * Aucune règle métier : le slug est validé par le domaine, la palette est
 * construite par le domaine, la publication est une transition du domaine.
 * Le contrôleur traduit HTTP ↔ cas d'usage, rien de plus.
 */
final class BrandController extends Controller
{
    public function __construct(
        private readonly CreateBrandAction $creator,
        private readonly UpdateBrandAction $updater,
        private readonly PublishBrandAction $publisher,
        private readonly SuspendBrandAction $suspender,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Brand::query()->orderByDesc('is_default')->orderBy('name');

        $vertical = $request->query('vertical');
        if (is_string($vertical) && $vertical !== '') {
            $query->where('vertical', $vertical);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return BrandResource::collection($query->get());
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        /** @var array{slug: string, name: string, vertical?: string, palette?: array{primary?: string, accent?: string, surface?: string}, identity?: array<string, mixed>, surface?: array<string, mixed>, host?: string|null} $input */
        $input = $request->validated();

        $brand = $this->creator->execute($input);

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        return new BrandResource($this->updater->execute($brand, $request->validated()));
    }

    public function publish(Brand $brand): BrandResource
    {
        return new BrandResource($this->publisher->execute($brand));
    }

    public function unpublish(Brand $brand): BrandResource
    {
        $brand->unpublish();
        $brand->save();

        $this->publisher->forget($brand);

        return new BrandResource($brand);
    }

    public function suspend(Request $request, Brand $brand): BrandResource
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', Brand::SUSPENSION_REASONS)],
        ]);

        return new BrandResource($this->suspender->execute($brand, $validated['reason']));
    }

    public function unsuspend(Brand $brand): BrandResource
    {
        $brand->unsuspend();
        $brand->save();

        $this->publisher->forget($brand);

        return new BrandResource($brand);
    }
}
