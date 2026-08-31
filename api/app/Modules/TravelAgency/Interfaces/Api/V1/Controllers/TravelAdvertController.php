<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Application\Actions\PayTravelAdvertAction;
use App\Modules\TravelAgency\Application\Actions\RenewTravelAdvertAction;
use App\Modules\TravelAgency\Application\Actions\ValidateTravelAdvertAction;
use App\Modules\TravelAgency\Application\Services\TravelAdvertPricingService;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-905..908 (#6108..#6111) — Annonces payantes (legacy gv-back,
 * spec §3) : référentiels (types, positions, tarifs) + cycle de vie
 * (soumission, paiement serveur, validation, publication, expiration,
 * renouvellement). Cross-tenant → 404 sûr.
 */
class TravelAdvertController extends Controller
{
    // ── Types d'annonces (TRAVEL-905/#6108) ──────────────────────────────

    public function indexAdvertTypes(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelAdvertType::class)) {
            abort(403);
        }

        $types = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);

        return new JsonResponse(['data' => $types]);
    }

    public function storeAdvertType(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelAdvertType::class)) {
            abort(403);
        }

        $code = trim((string) $request->json('code'));
        $name = trim((string) $request->json('name'));

        if ($code === '' || $name === '') {
            abort(422, 'Advert type code and name are required.');
        }

        $exists = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            abort(422, 'Advert type code already exists for this tenant.');
        }

        $type = TravelAdvertType::query()->create([
            'company_id' => $actor->company_id,
            'code' => $code,
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $type], 201);
    }

    public function updateAdvertType(Request $request, TravelAdvertType $travelAdvertType): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertType->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelAdvertType)) {
            abort(403);
        }

        $name = trim((string) $request->json('name'));

        if ($name === '') {
            abort(422, 'Advert type name is required.');
        }

        $travelAdvertType->update([
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $travelAdvertType->refresh()]);
    }

    public function destroyAdvertType(Request $request, TravelAdvertType $travelAdvertType): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertType->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelAdvertType)) {
            abort(403);
        }

        $travelAdvertType->delete();

        return new JsonResponse(null, 204);
    }

    // ── Positions de publication (TRAVEL-905/#6108) ───────────────────────

    public function indexAdvertPositions(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelAdvertPosition::class)) {
            abort(403);
        }

        $positions = TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);

        return new JsonResponse(['data' => $positions]);
    }

    public function storeAdvertPosition(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelAdvertPosition::class)) {
            abort(403);
        }

        $code = trim((string) $request->json('code'));
        $name = trim((string) $request->json('name'));

        if ($code === '' || $name === '') {
            abort(422, 'Advert position code and name are required.');
        }

        $exists = TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            abort(422, 'Advert position code already exists for this tenant.');
        }

        $position = TravelAdvertPosition::query()->create([
            'company_id' => $actor->company_id,
            'code' => $code,
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $position], 201);
    }

    public function updateAdvertPosition(Request $request, TravelAdvertPosition $travelAdvertPosition): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPosition->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelAdvertPosition)) {
            abort(403);
        }

        $name = trim((string) $request->json('name'));

        if ($name === '') {
            abort(422, 'Advert position name is required.');
        }

        $travelAdvertPosition->update([
            'name' => $name,
            'description' => $request->json('description'),
        ]);

        return new JsonResponse(['data' => $travelAdvertPosition->refresh()]);
    }

    public function destroyAdvertPosition(Request $request, TravelAdvertPosition $travelAdvertPosition): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPosition->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelAdvertPosition)) {
            abort(403);
        }

        $travelAdvertPosition->delete();

        return new JsonResponse(null, 204);
    }

    // ── Grille tarifaire (TRAVEL-906/#6109) ──────────────────────────────

    public function indexAdvertPrices(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelAdvertPrice::class)) {
            abort(403);
        }

        $prices = TravelAdvertPrice::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('id')
            ->get();

        return new JsonResponse(['data' => $prices]);
    }

    public function storeAdvertPrice(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelAdvertPrice::class)) {
            abort(403);
        }

        $typeId = (int) $request->json('advert_type_id');
        $positionId = (int) $request->json('advert_position_id');
        $priceImage = (int) $request->json('price_image_minor', 0);
        $priceCharacter = (int) $request->json('price_character_minor', 0);

        if ($priceImage < 0 || $priceCharacter < 0) {
            abort(422, 'Prices must be non-negative minor units.');
        }

        // Références du même tenant (jamais cross-tenant) + devise du tenant.
        $type = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->where('id', $typeId)
            ->first();

        if (! $type instanceof TravelAdvertType) {
            abort(422, 'Unknown advert type for this tenant.');
        }

        $position = TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->where('id', $positionId)
            ->first();

        if (! $position instanceof TravelAdvertPosition) {
            abort(422, 'Unknown advert position for this tenant.');
        }

        $currency = strtoupper((string) $request->json('currency', $actor->company->currency));

        if ($currency !== strtoupper((string) $actor->company->currency)) {
            abort(422, 'Currency must match the tenant currency.');
        }

        $exists = TravelAdvertPrice::query()
            ->where('company_id', $actor->company_id)
            ->where('advert_type_id', $typeId)
            ->where('advert_position_id', $positionId)
            ->where('currency', $currency)
            ->exists();

        if ($exists) {
            abort(422, 'A price already exists for this type/position/currency.');
        }

        $price = TravelAdvertPrice::query()->create([
            'company_id' => $actor->company_id,
            'advert_type_id' => $typeId,
            'advert_position_id' => $positionId,
            'price_image_minor' => $priceImage,
            'price_character_minor' => $priceCharacter,
            'currency' => $currency,
        ]);

        return new JsonResponse(['data' => $price], 201);
    }

    public function updateAdvertPrice(Request $request, TravelAdvertPrice $travelAdvertPrice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPrice->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelAdvertPrice)) {
            abort(403);
        }

        $priceImage = (int) $request->json('price_image_minor', $travelAdvertPrice->price_image_minor);
        $priceCharacter = (int) $request->json('price_character_minor', $travelAdvertPrice->price_character_minor);

        if ($priceImage < 0 || $priceCharacter < 0) {
            abort(422, 'Prices must be non-negative minor units.');
        }

        $travelAdvertPrice->update([
            'price_image_minor' => $priceImage,
            'price_character_minor' => $priceCharacter,
        ]);

        return new JsonResponse(['data' => $travelAdvertPrice->refresh()]);
    }

    public function destroyAdvertPrice(Request $request, TravelAdvertPrice $travelAdvertPrice): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvertPrice->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelAdvertPrice)) {
            abort(403);
        }

        $travelAdvertPrice->delete();

        return new JsonResponse(null, 204);
    }


    // ── Annonces — cycle de vie (TRAVEL-907/#6110) ───────────────────────

    public function indexAdverts(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelAdvert::class)) {
            abort(403);
        }

        $query = TravelAdvert::query()->where('company_id', $actor->company_id);

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $typeId = (int) $request->query('advert_type_id', 0);
        if ($typeId > 0) {
            $query->where('advert_type_id', $typeId);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $adverts = $query->orderByDesc('id')->paginate($perPage);

        return new JsonResponse(['data' => $adverts->items(), 'meta' => [
            'total' => $adverts->total(),
            'per_page' => $adverts->perPage(),
            'current_page' => $adverts->currentPage(),
        ]]);
    }

    public function storeAdvert(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelAdvert::class)) {
            abort(403);
        }

        $typeId = (int) $request->json('advert_type_id');
        $positionId = (int) $request->json('advert_position_id');
        $title = trim((string) $request->json('title'));
        $body = trim((string) $request->json('body_redacted'));

        if ($typeId <= 0 || $positionId <= 0) {
            abort(422, 'advert_type_id and advert_position_id are required.');
        }

        if ($title === '' || mb_strlen($title) > 200) {
            abort(422, 'Title is required (max 200 characters).');
        }

        if ($body === '' || mb_strlen($body) > 5000) {
            abort(422, 'Body is required (max 5000 characters).');
        }

        // Références du même tenant.
        $typeExists = DB::table('travel_advert_types')
            ->where('company_id', $actor->company_id)
            ->where('id', $typeId)
            ->exists();
        $positionExists = DB::table('travel_advert_positions')
            ->where('company_id', $actor->company_id)
            ->where('id', $positionId)
            ->exists();

        if (! $typeExists || ! $positionExists) {
            abort(422, 'Advert type or position unknown for this tenant.');
        }

        // Prix calculé SERVEUR (jamais accepté du client).
        $quote = app(TravelAdvertPricingService::class)->quote(
            (string) $actor->company_id,
            $typeId,
            $positionId,
            $body,
            (string) $actor->company->currency,
        );

        $advert = TravelAdvert::query()->create([
            'company_id' => $actor->company_id,
            'advert_type_id' => $typeId,
            'advert_position_id' => $positionId,
            'title' => $title,
            'body_redacted' => $body,
            'image_path' => $request->json('image_path'),
            ...$quote,
            'status' => TravelAdvert::STATUS_DRAFT,
        ]);

        return new JsonResponse(['data' => $advert], 201);
    }

    public function showAdvert(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvert->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $travelAdvert)) {
            abort(403);
        }

        return new JsonResponse(['data' => $travelAdvert]);
    }

    public function payAdvert(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvert->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelAdvert)) {
            abort(403);
        }

        $provider = (string) $request->json('provider', 'cash');

        $advert = app(PayTravelAdvertAction::class)->execute(
            $travelAdvert,
            $actor,
            $provider,
            $request->json('provider_reference') !== null ? (string) $request->json('provider_reference') : null,
        );

        return new JsonResponse(['data' => $advert]);
    }

    public function validateAdvert(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvert->company_id) {
            abort(404);
        }

        if ($actor->cannot('manage', $travelAdvert)) {
            abort(403);
        }

        $approved = (bool) $request->json('approved', true);
        $note = $request->json('note') !== null ? (string) $request->json('note') : null;

        $advert = app(ValidateTravelAdvertAction::class)->execute($travelAdvert, $actor, $approved, $note);

        return new JsonResponse(['data' => $advert]);
    }

    public function destroyAdvert(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvert->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelAdvert)) {
            abort(403);
        }

        $travelAdvert->delete();

        return new JsonResponse(null, 204);
    }


    public function renewAdvert(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelAdvert->company_id) {
            abort(404);
        }

        if ($actor->cannot('manage', $travelAdvert)) {
            abort(403);
        }

        $provider = (string) $request->json('provider', 'cash');

        $advert = app(RenewTravelAdvertAction::class)->execute(
            $travelAdvert,
            $actor,
            $provider,
            $request->json('provider_reference') !== null ? (string) $request->json('provider_reference') : null,
        );

        return new JsonResponse(['data' => $advert]);
    }

}
