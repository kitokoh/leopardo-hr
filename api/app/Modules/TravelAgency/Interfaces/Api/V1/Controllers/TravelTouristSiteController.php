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

/**
 * TRAVEL-909 (#6112) — Sites touristiques (annuaire géolocalisé).
 *
 * CRUD + recherche par ville (critère d'acceptation). Écritures réservées
 * `travel.manage`, lecture ouverte aux employés du tenant.
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
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $sites = TravelTouristSite::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => $sites->map(fn (TravelTouristSite $site): array => $this->payload($site)),
            'meta' => ['total' => $sites->total()],
        ]);
    }

    public function search(Request $request): JsonResponse
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
        $cityId = (int) $request->query('city_id', 0);

        if ($cityId <= 0) {
            abort(422, 'city_id requis.');
        }

        $sites = TravelTouristSite::query()
            ->where('company_id', $actor->company_id)
            ->where('city_id', $cityId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $sites->map(fn (TravelTouristSite $site): array => $this->payload($site))]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'city_id' => ['required', 'integer', 'exists:travel_cities,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'string', 'max:500'],
        ]);

        $site = TravelTouristSite::query()->create([
            'company_id' => $actor->company_id,
            'name' => $data['name'],
            'description_redacted' => $data['description'] ?? null,
            'city_id' => (int) $data['city_id'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'images' => $data['images'] ?? [],
            'status' => 'active',
        ]);

        return response()->json(['data' => $this->payload($site)])->setStatusCode(201);
    }

    public function update(Request $request, TravelTouristSite $site): JsonResponse
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

        if ($actor->company_id !== $travelTouristSite->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $travelTouristSite)) {
            abort(403);
        }

        return new JsonResponse(['data' => $travelTouristSite]);
    }

    public function update(Request $request, TravelTouristSite $travelTouristSite): JsonResponse
        if ($site->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $site->update($request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'city_id' => ['sometimes', 'integer', 'exists:travel_cities,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'string', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,hidden'],
        ]));

        return response()->json(['data' => $this->payload($site->refresh())]);
    }

    public function destroy(Request $request, TravelTouristSite $site): JsonResponse
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

        if ($actor->company_id !== $travelTouristSite->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelTouristSite)) {
            abort(403);
        }

        $travelTouristSite->delete();

        return new JsonResponse(null, 204);
        if ($site->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $site->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TravelTouristSite $site): array
    {
        return [
            'id' => $site->id,
            'name' => $site->name,
            'description' => $site->description_redacted,
            'city_id' => $site->city_id,
            'latitude' => $site->latitude,
            'longitude' => $site->longitude,
            'images' => $site->images ?? [],
            'status' => $site->status,
            'created_at' => $site->created_at?->toIso8601String(),
        ];
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
        if ($actor->cannot('update', $travelTouristSite)) {
            abort(404);
        }

        $travelTouristSite->delete();

        return response()->json(null, 204);
    }
}
