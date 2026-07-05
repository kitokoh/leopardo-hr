<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreSiteRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateSiteRequest;
use App\Http\Resources\Api\V1\SiteResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Site;
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
                ->select(['id', 'company_id', 'name', 'address', 'gps_lat', 'gps_lng', 'gps_radius_m', 'created_at'])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $site = Site::create(['company_id' => $user->company_id, ...$request->validated()]);

        return (new SiteResource($site))
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

        return new JsonResponse(['message' => 'Site deleted successfully']);
    }
}

