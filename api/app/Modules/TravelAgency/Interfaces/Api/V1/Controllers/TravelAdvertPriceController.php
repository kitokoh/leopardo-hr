<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelAdvertPriceRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-906 (#6109) — Grille tarifaire des annonces (CRUD tenant-scoped).
 */
class TravelAdvertPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prices = TravelAdvertPrice::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['advertType:id,code,label', 'advertPosition:id,code,label'])
            ->orderBy('id')
            ->get()
            ->map(fn (TravelAdvertPrice $p) => [
                'id' => $p->id,
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

        $price = TravelAdvertPrice::query()->create([
            'company_id' => $actor->company_id,
            'advert_type_id' => (int) $request->validated('advert_type_id'),
            'advert_position_id' => (int) $request->validated('advert_position_id'),
            'price_per_image_minor' => (int) $request->validated('price_per_image_minor'),
            'price_per_character_minor' => (int) $request->validated('price_per_character_minor'),
            'currency' => strtoupper((string) $request->validated('currency')),
        ]);

        return response()->json(['data' => ['id' => $price->id]], 201);
    }

    public function destroy(Request $request, TravelAdvertPrice $travelAdvertPrice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPrice->company_id) {
            abort(404);
        }

        $travelAdvertPrice->delete();

        return response()->json(null, 204);
    }
}
