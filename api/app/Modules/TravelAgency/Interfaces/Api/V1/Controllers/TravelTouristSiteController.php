<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-909 (#6112) — Sites touristiques (annuaire géolocalisé).
 *
 * CRUD + recherche par ville (critère d'acceptation). Écritures réservées
 * `travel.manage`, lecture ouverte aux employés du tenant.
 */
class TravelTouristSiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

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
    {
        /** @var Employee $actor */
        $actor = $request->user();

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
    {
        /** @var Employee $actor */
        $actor = $request->user();

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
    }
}
