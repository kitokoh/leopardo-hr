<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-909 (#6112) — Annuaire des sites touristiques : CRUD tenant-scoped,
 * recherche par ville et par nom, géo bornée, statuts. Cross-tenant → 404.
 */
class TravelTouristSiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelTouristSite::class)) {
            abort(403);
        }

        $query = TravelTouristSite::query()->where('company_id', $actor->company_id);

        $cityId = (int) $request->query('city_id', 0);
        if ($cityId > 0) {
            // La ville doit appartenir au tenant (jamais cross-tenant).
            $cityExists = DB::table('travel_cities')
                ->where('company_id', $actor->company_id)
                ->where('id', $cityId)
                ->exists();

            if (! $cityExists) {
                abort(422, 'Unknown city for this tenant.');
            }

            $query->where('city_id', $cityId);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('name', 'ilike', '%'.$search.'%');
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $sites = $query->orderBy('name')->paginate($perPage);

        return new JsonResponse(['data' => $sites->items(), 'meta' => [
            'total' => $sites->total(),
            'per_page' => $sites->perPage(),
            'current_page' => $sites->currentPage(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelTouristSite::class)) {
            abort(403);
        }

        $name = trim((string) $request->json('name'));

        if ($name === '' || mb_strlen($name) > 200) {
            abort(422, 'Name is required (max 200 characters).');
        }

        $cityId = $request->json('city_id') !== null ? (int) $request->json('city_id') : null;
        if ($cityId !== null) {
            $cityExists = DB::table('travel_cities')
                ->where('company_id', $actor->company_id)
                ->where('id', $cityId)
                ->exists();

            if (! $cityExists) {
                abort(422, 'Unknown city for this tenant.');
            }
        }

        $latitude = $request->json('latitude') !== null ? (float) $request->json('latitude') : null;
        $longitude = $request->json('longitude') !== null ? (float) $request->json('longitude') : null;

        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            abort(422, 'Latitude out of range.');
        }

        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            abort(422, 'Longitude out of range.');
        }

        $images = $request->json('images');
        if ($images !== null && ! is_array($images)) {
            abort(422, 'Images must be an array of paths.');
        }

        $site = TravelTouristSite::query()->create([
            'company_id' => $actor->company_id,
            'name' => $name,
            'description_redacted' => $request->json('description_redacted'),
            'city_id' => $cityId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'images' => $images,
            'status' => (string) $request->json('status', TravelTouristSite::STATUS_PUBLISHED),
        ]);

        return new JsonResponse(['data' => $site], 201);
    }

    public function show(Request $request, TravelTouristSite $travelTouristSite): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTouristSite->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $travelTouristSite)) {
            abort(403);
        }

        return new JsonResponse(['data' => $travelTouristSite]);
    }

    public function update(Request $request, TravelTouristSite $travelTouristSite): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTouristSite->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelTouristSite)) {
            abort(403);
        }

        $data = [];

        if ($request->json('name') !== null) {
            $name = trim((string) $request->json('name'));
            if ($name === '' || mb_strlen($name) > 200) {
                abort(422, 'Name is required (max 200 characters).');
            }
            $data['name'] = $name;
        }

        if ($request->json('description_redacted') !== null) {
            $data['description_redacted'] = $request->json('description_redacted');
        }

        if ($request->json('city_id') !== null) {
            $cityId = (int) $request->json('city_id');
            $cityExists = DB::table('travel_cities')
                ->where('company_id', $actor->company_id)
                ->where('id', $cityId)
                ->exists();

            if (! $cityExists) {
                abort(422, 'Unknown city for this tenant.');
            }
            $data['city_id'] = $cityId;
        }

        if ($request->json('latitude') !== null) {
            $latitude = (float) $request->json('latitude');
            if ($latitude < -90 || $latitude > 90) {
                abort(422, 'Latitude out of range.');
            }
            $data['latitude'] = $latitude;
        }

        if ($request->json('longitude') !== null) {
            $longitude = (float) $request->json('longitude');
            if ($longitude < -180 || $longitude > 180) {
                abort(422, 'Longitude out of range.');
            }
            $data['longitude'] = $longitude;
        }

        if ($request->json('images') !== null) {
            $images = $request->json('images');
            if (! is_array($images)) {
                abort(422, 'Images must be an array of paths.');
            }
            $data['images'] = $images;
        }

        if ($request->json('status') !== null) {
            $status = (string) $request->json('status');
            if (! in_array($status, [TravelTouristSite::STATUS_DRAFT, TravelTouristSite::STATUS_PUBLISHED, TravelTouristSite::STATUS_ARCHIVED], true)) {
                abort(422, 'Invalid site status.');
            }
            $data['status'] = $status;
        }

        $travelTouristSite->update($data);

        return new JsonResponse(['data' => $travelTouristSite->refresh()]);
    }

    public function destroy(Request $request, TravelTouristSite $travelTouristSite): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTouristSite->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelTouristSite)) {
            abort(403);
        }

        $travelTouristSite->delete();

        return new JsonResponse(null, 204);
    }
}
