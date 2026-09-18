<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\Concerns\AuthorizesAdvertCatalogWrite;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelAdvertPriceRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelAdvertPriceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-906 (#6109) — Grille tarifaire des annonces (CRUD tenant-scoped).
 */
class TravelAdvertPriceController extends Controller
{
    use AuthorizesAdvertCatalogWrite;

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $prices = TravelAdvertPrice::query()
            ->where('company_id', $actor->company_id)
            ->with(['advertType:id,code,label', 'advertPosition:id,code,label'])
            ->orderBy('id')
            ->get()
            ->map(fn (TravelAdvertPrice $p) => [
                'id' => $p->id,
                'advert_type_id' => $p->advert_type_id,
                'advert_position_id' => $p->advert_position_id,
                'advert_type' => $p->advertType?->code,
                'advert_position' => $p->advertPosition?->code,
                'price_per_image_minor' => $p->price_per_image_minor,
                'price_per_character_minor' => $p->price_per_character_minor,
                'currency' => $p->currency,
            ]);

        return response()->json(['data' => $prices]);
    }

    public function store(StoreTravelAdvertPriceRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorizeAdvertCatalogWrite($actor);

        $price = TravelAdvertPrice::query()->create([
            'company_id' => $actor->company_id,
            'advert_type_id' => (int) $request->validated('advert_type_id'),
            'advert_position_id' => (int) $request->validated('advert_position_id'),
            'price_per_image_minor' => (int) $request->validated('price_per_image_minor'),
            'price_per_character_minor' => (int) $request->validated('price_per_character_minor'),
            'currency' => $this->resolveCurrency($request, $actor),
        ]);

        return response()->json(['data' => ['id' => $price->id]], 201);
    }

    /**
     * TRAVEL-914 (#6422) — Mise à jour d'une grille tarifaire.
     */
    public function update(UpdateTravelAdvertPriceRequest $request, TravelAdvertPrice $travelAdvertPrice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        if ($actor->company_id !== $travelAdvertPrice->company_id) {
            abort(404);
        }

        $travelAdvertPrice->forceFill([
            'advert_type_id' => (int) $request->validated('advert_type_id'),
            'advert_position_id' => (int) $request->validated('advert_position_id'),
            'price_per_image_minor' => (int) $request->validated('price_per_image_minor'),
            'price_per_character_minor' => (int) $request->validated('price_per_character_minor'),
            'currency' => $this->resolveCurrency($request, $actor),
        ])->save();

        return response()->json(['data' => ['id' => $travelAdvertPrice->id]]);
    }

    /**
     * #7420 — devise de la grille : celle fournie par l'appelant (déjà validée
     * cohérente avec le tenant) ou, à défaut, celle du tenant.
     */
    private function resolveCurrency(StoreTravelAdvertPriceRequest $request, Employee $actor): string
    {
        $requested = $request->validated('currency');

        if (is_string($requested) && $requested !== '') {
            return strtoupper($requested);
        }

        $company = $actor->company;

        if (! $company instanceof Company || $company->currency === '') {
            return 'XAF';
        }

        return strtoupper($company->currency);
    }

    public function destroy(Request $request, TravelAdvertPrice $travelAdvertPrice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorizeAdvertCatalogWrite($actor);

        if ($actor->company_id !== $travelAdvertPrice->company_id) {
            abort(404);
        }

        $travelAdvertPrice->delete();

        return response()->json(null, 204);
    }
}
