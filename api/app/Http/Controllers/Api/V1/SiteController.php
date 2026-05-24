<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Site\StoreSiteRequest;
use App\Http\Requests\Api\V1\Site\UpdateSiteRequest;
use App\Http\Resources\Api\V1\SiteResource;
use App\Models\Employee;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return SiteResource::collection(Site::query()
            ->select(['id', 'company_id', 'name', 'address', 'gps_lat', 'gps_lng', 'gps_radius_m', 'created_at'])
            ->orderBy('name')
            ->get());
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

        return response()->json(['message' => 'Site deleted successfully']);
    }
}
