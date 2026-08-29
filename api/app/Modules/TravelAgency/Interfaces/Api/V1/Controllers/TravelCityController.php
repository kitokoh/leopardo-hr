<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\ListTravelCitiesRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCityResource;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-301 (#6031) — Référentiel géographique en lecture (listes déroulantes).
 *
 * Route : GET /api/v1/travel/cities — tenant-scoped (`BelongsToCompany`),
 * filtres pays/recherche/pagination, aucune écriture.
 */
class TravelCityController extends Controller
{
    public function index(ListTravelCitiesRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = TravelCity::query();

        if (isset($filters['country_iso2'])) {
            $query->where('country_iso2', strtoupper((string) $filters['country_iso2']));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'ilike', '%'.$filters['search'].'%');
        }

        $cities = $query->orderBy('name')
            ->paginate(max(1, min(1000, (int) ($filters['per_page'] ?? 50))));

        return TravelCityResource::collection($cities)->response();
    }
}
