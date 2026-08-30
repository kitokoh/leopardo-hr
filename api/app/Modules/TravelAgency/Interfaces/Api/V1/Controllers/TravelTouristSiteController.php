<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelTouristSiteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-909 (#6112) — Annuaire des sites touristiques.
 *
 * CRUD tenant-scoped + recherche par ville (`?city_id=`) et par nom
 * (`?search=`). La description est redigée (pas de PII inutile).
 */
class TravelTouristSiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $sites = TravelTouristSite::query()
            ->where('company_id', $actor->company_id)
            ->when($request->has('city_id'), fn ($query) => $query->where('city_id', (int) $request->query('city_id')))
            ->when($request->has('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->query('search').'%'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->get()
            ->map(fn (TravelTouristSite $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'city_id' => $site->city_id,
                'status' => $site->status->value,
            ]);

        return response()->json(['data' => $sites]);
    }

    public function show(Request $request, TravelTouristSite $travelTouristSite): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTouristSite->company_id) {
            abort(404);
        }

        return response()->json(['data' => [
            'id' => $travelTouristSite->id,
            'name' => $travelTouristSite->name,
            'description' => $travelTouristSite->description_redacted,
            'city_id' => $travelTouristSite->city_id,
            'latitude' => $travelTouristSite->latitude,
            'longitude' => $travelTouristSite->longitude,
            'image_asset_id' => $travelTouristSite->image_asset_id,
            'status' => $travelTouristSite->status->value,
        ]]);
    }

    public function store(StoreTravelTouristSiteRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelTouristSite::class)) {
            abort(403);
        }

        $site = TravelTouristSite::query()->create([
            'company_id' => $actor->company_id,
            'name' => trim((string) $request->validated('name')),
            'description_redacted' => $request->validated('description'),
            'city_id' => $request->validated('city_id'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'image_asset_id' => $request->validated('image_asset_id'),
            'status' => $request->validated('status') ?? TravelRecordStatus::ACTIVE->value,
        ]);

        return response()->json(['data' => ['id' => $site->id]], 201);
    }

    public function update(StoreTravelTouristSiteRequest $request, TravelTouristSite $travelTouristSite): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelTouristSite)) {
            abort(404);
        }

        $travelTouristSite->forceFill([
            'name' => trim((string) $request->validated('name')),
            'description_redacted' => $request->validated('description'),
            'city_id' => $request->validated('city_id'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'image_asset_id' => $request->validated('image_asset_id'),
            'status' => $request->validated('status') ?? $travelTouristSite->status->value,
        ])->save();

        return response()->json(['data' => ['id' => $travelTouristSite->id]]);
    }

    public function destroy(Request $request, TravelTouristSite $travelTouristSite): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelTouristSite)) {
            abort(404);
        }

        $travelTouristSite->delete();

        return response()->json(null, 204);
    }
}
