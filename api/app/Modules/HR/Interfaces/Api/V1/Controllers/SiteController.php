<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SiteResource;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreSiteRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateSiteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * SiteController — CRUD for company work sites.
 *
 * Migrated from App\Http\Controllers\Api\V1\SiteController.
 * Read/write restricted to managers; all authenticated employees can read their site.
 */
class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return SiteResource::collection(
            Site::query()
                // BOS-T-01 / #8624 — le sélecteur d'établissement a besoin du
                // code et du statut : sans eux, l'exploitant ne peut pas
                // distinguer deux lieux homonymes ni exclure un lieu fermé.
                ->select([
                    'id', 'company_id', 'name', 'code', 'site_type', 'status',
                    'address', 'gps_lat', 'gps_lng', 'gps_radius_m',
                    'timezone', 'currency', 'created_at', 'updated_at',
                ])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $site = Site::create(['company_id' => $user->company_id, ...$request->validated()]);

        // `status` et `gps_radius_m` ont un DEFAULT côté PostgreSQL : l'instance
        // Eloquent ne les connaît pas encore. Sans ce refresh, l'API renverrait
        // `status: null` pour un établissement créé actif.
        return (new SiteResource($site->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Site $site): SiteResource
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return new SiteResource($site);
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $site->update($request->validated());

        return new SiteResource($site->fresh());
    }

    public function destroy(Request $request, Site $site): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $site->delete();

        return new JsonResponse(['message' => __('errors.SITE_DELETED')]);
    }
}
