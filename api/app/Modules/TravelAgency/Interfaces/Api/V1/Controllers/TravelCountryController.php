<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\ListTravelCountriesRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelCountryResource;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-301 (#6031) — Référentiel géographique en lecture (listes déroulantes).
 *
 * Route : GET /api/v1/travel/countries — tenant-scoped (`BelongsToCompany`),
 * filtres pays/recherche/pagination, aucune écriture.
 */
class TravelCountryController extends Controller
{
    public function index(ListTravelCountriesRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = TravelCountry::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'ilike', '%'.$filters['search'].'%');
        }

        $countries = $query->orderBy('name')
            ->paginate(max(1, min(1000, (int) ($filters['per_page'] ?? 50))));

        return TravelCountryResource::collection($countries)->response();
    }
}
