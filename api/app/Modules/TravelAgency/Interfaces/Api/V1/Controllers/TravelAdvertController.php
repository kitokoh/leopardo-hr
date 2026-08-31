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
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use App\Modules\TravelAgency\Infrastructure\Services\TravelAdvertPricingService;
use App\Modules\TravelAgency\Application\Actions\ModerateTravelAdvertAction;
use App\Modules\TravelAgency\Application\Actions\PayTravelAdvertAction;
use App\Modules\TravelAgency\Application\Actions\RenewTravelAdvertAction;
use App\Modules\TravelAgency\Application\Actions\SubmitTravelAdvertAction;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\ModerateTravelAdvertRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelAdvertRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-905..908 (#6108..#6111) — Annonces : référentiels, tarifs, cycle
 * de vie (submit → paid → validated → published → expired|archived).
 *
 * Une annonce n'est VISIBLE (liste publique) qu'une fois payée ET validée
 * ET non expirée (critère d'acceptation). Prix calculé serveur en unités
 * mineures, devise du tenant.
 */
class TravelAdvertController extends Controller
{
    // ── Référentiels (types / positions) ────────────────────────────────────

    public function indexTypes(Request $request): JsonResponse
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
        return response()->json(['data' => TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get()]);
    }

    public function storeType(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['data' => TravelAdvertType::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        )])->setStatusCode(201);
    }

    public function indexPositions(Request $request): JsonResponse
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
        return response()->json(['data' => TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get()]);
    }

    public function storePosition(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['data' => TravelAdvertPosition::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        )])->setStatusCode(201);
    }

    // ── Tarifs ──────────────────────────────────────────────────────────────

    public function indexPrices(Request $request): JsonResponse
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
        return response()->json(['data' => TravelAdvertPrice::query()
            ->where('company_id', $actor->company_id)
            ->with(['type', 'position'])
            ->get()]);
    }

    public function storePrice(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'type_id' => ['required', 'integer', 'exists:travel_advert_types,id'],
            'position_id' => ['required', 'integer', 'exists:travel_advert_positions,id'],
            'price_per_image_minor' => ['sometimes', 'integer', 'min:0'],
            'price_per_character_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        // Cohérence devise tenant (critère d'acceptation).
        $data['currency'] = $data['currency'] ?? $actor->company?->currency ?? 'XAF';

        return response()->json(['data' => TravelAdvertPrice::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        )])->setStatusCode(201);
    }

    // ── Annonces ────────────────────────────────────────────────────────────

    /**
     * Soumission d'une annonce : prix calculé SERVEUR (jamais du client).
     */
    public function submit(Request $request, TravelAdvertPricingService $pricing): JsonResponse
 * TRAVEL-907/908 (#6110/#6111) — Annonces payantes.
 *
 * Cycle : soumission (prix serveur) → paiement → validation (travel.manage)
 * → publication. Une annonce n'est visible qu'une fois payée ET validée ET
 * non expirée (`isVisible()`). Renouvellement = nouveau paiement qui
 * prolonge `expires_at`.
 */
class TravelAdvertController extends Controller
{
    public function __construct(
        private readonly SubmitTravelAdvertAction $submit,
        private readonly PayTravelAdvertAction $pay,
        private readonly ModerateTravelAdvertAction $moderate,
        private readonly RenewTravelAdvertAction $renew,
    ) {}

    /**
     * Liste publique tenant : annonces VISIBLES uniquement.
     */
    public function index(Request $request): JsonResponse
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
        $data = $request->validate([
            'type_id' => ['required', 'integer', 'exists:travel_advert_types,id'],
            'position_id' => ['required', 'integer', 'exists:travel_advert_positions,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var TravelAdvertType $type */
        $type = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['type_id']);

        $quote = $pricing->computePrice(
            companyId: $actor->company_id,
            type: $type,
            positionId: (int) $data['position_id'],
            characterCount: mb_strlen($data['body']),
            hasImage: ! empty($data['image_path']),
        );

        $advert = TravelAdvert::query()->create([
            'company_id' => $actor->company_id,
            'type_id' => $type->id,
            'position_id' => (int) $data['position_id'],
            'title' => $data['title'],
            'body_redacted' => $data['body'],
            'image_path' => $data['image_path'] ?? null,
            'character_count' => $quote['character_count'],
            'price_minor' => $quote['price_minor'],
            'currency' => $quote['currency'],
            'status' => 'submitted',
        ]);

        return response()->json(['data' => $this->payload($advert)])->setStatusCode(201);
    }

    /**
     * Paiement (cash guichet) : submitted → paid. Idempotent.
     */
    public function pay(Request $request, TravelAdvert $advert): JsonResponse
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
        if ($advert->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! in_array($advert->status, ['submitted', 'paid'], true)) {
            abort(422, 'Cette annonce ne peut pas être payée (statut '.$advert->status.').');
        }

        if ($advert->status !== 'paid') {
            $advert->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();
        }

        return response()->json(['data' => $this->payload($advert->refresh())]);
    }

    /**
     * Validation par travel.manage : paid → validated (et publiée si payée).
     */
    public function validateAd(Request $request, TravelAdvert $advert): JsonResponse
        $adverts = TravelAdvert::query()
            ->where('company_id', $actor->company_id)
            ->get()
            ->filter(fn (TravelAdvert $a) => $a->isVisible())
            ->values()
            ->map(fn (TravelAdvert $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content_redacted,
                'price_minor' => $a->price_minor,
                'currency' => $a->currency,
                'expires_at' => $a->expires_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $adverts]);
    }

    /**
     * TRAVEL-914 (#6422) — Liste admin des annonces (toutes, quel que soit
     * le statut) pour l'écran de modération. Réservé aux rôles gestion
     * (TravelAdvertPolicy::moderate) — l'index public reste limité aux
     * annonces visibles. Filtre optionnel `?status=` (draft|submitted|paid|
     * validated|rejected|expired|archived).
     */
    public function manageIndex(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $adverts = TravelAdvert::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get()
            ->map(fn (TravelAdvert $a) => [
                'id' => $a->id,
                'advert_type_id' => $a->advert_type_id,
                'advert_position_id' => $a->advert_position_id,
                'title' => $a->title,
                'content' => $a->content_redacted,
                'status' => $a->status->value,
                'price_minor' => $a->price_minor,
                'currency' => $a->currency,
                'payment_reference' => $a->payment_reference,
                'paid_at' => $a->paid_at?->toIso8601String(),
                'validated_at' => $a->validated_at?->toIso8601String(),
                'rejected_reason' => $a->rejected_reason,
                'validity_days' => $a->validity_days,
                'expires_at' => $a->expires_at?->toIso8601String(),
                'visible' => $a->isVisible(),
            ]);

        return response()->json(['data' => $adverts]);
    }

    public function show(Request $request, TravelAdvert $travelAdvert): JsonResponse
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
        if ($advert->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        if ($advert->status !== 'paid') {
            abort(422, 'Seule une annonce payée peut être validée.');
        }

        $days = (int) $request->validate(['valid_days' => ['sometimes', 'integer', 'min:1', 'max:90']])['valid_days'] ?? 30;

        $advert->forceFill([
            'status' => 'published',
            'validated_at' => now(),
            'validated_by_user_id' => $actor->id,
            'published_at' => now(),
            'valid_until' => now()->addDays($days),
        ])->save();

        return response()->json(['data' => $this->payload($advert->refresh())]);
    }

    /**
     * Annonces VISIBLES (payées + validées + non expirées) — lecture publique tenant.
     */
    public function indexVisible(Request $request): JsonResponse
        if ($actor->company_id !== $travelAdvert->company_id) {
            abort(404);
        }

        return response()->json(['data' => [
            'id' => $travelAdvert->id,
            'title' => $travelAdvert->title,
            'content' => $travelAdvert->content_redacted,
            'status' => $travelAdvert->status->value,
            'price_minor' => $travelAdvert->price_minor,
            'currency' => $travelAdvert->currency,
            'paid_at' => $travelAdvert->paid_at?->toIso8601String(),
            'validated_at' => $travelAdvert->validated_at?->toIso8601String(),
            'expires_at' => $travelAdvert->expires_at?->toIso8601String(),
            'visible' => $travelAdvert->isVisible(),
        ]]);
    }

    public function store(StoreTravelAdvertRequest $request): JsonResponse
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
        $adverts = TravelAdvert::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'published')
            ->where(function ($q): void {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $adverts->map(fn (TravelAdvert $a): array => $this->payload($a))]);
    }

    /**
     * Toutes les annonces (gestion) — travel.manage.
     */
    public function indexManage(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $adverts = TravelAdvert::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $adverts->map(fn (TravelAdvert $a): array => $this->payload($a))]);
    }

    /**
     * Renouvellement : nouvelle soumission payée (requalifie published).
     */
    public function renew(Request $request, TravelAdvert $advert): JsonResponse
        $advert = $this->submit->execute(
            $actor,
            (int) $request->validated('advert_type_id'),
            (int) $request->validated('advert_position_id'),
            (string) $request->validated('title'),
            (string) $request->validated('content'),
            $request->validated('image_asset_id') !== null ? (int) $request->validated('image_asset_id') : null,
            (int) ($request->validated('validity_days') ?? 30),
        );

        return response()->json(['data' => [
            'id' => $advert->id,
            'status' => $advert->status->value,
            'price_minor' => $advert->price_minor,
            'currency' => $advert->currency,
        ]], 201);
    }

    public function pay(Request $request, TravelAdvert $travelAdvert): JsonResponse
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
        if ($actor->cannot('pay', $travelAdvert)) {
            abort(404);
        }

        $advert = $this->pay->execute($travelAdvert);

        return response()->json(['data' => [
            'id' => $advert->id,
            'status' => $advert->status->value,
            'payment_reference' => $advert->payment_reference,
        ]]);
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
        if ($actor->cannot('moderate', $travelAdvert)) {
            abort(403);
        }

        $advert = $this->moderate->validate($travelAdvert, $actor);

        return response()->json(['data' => [
            'id' => $advert->id,
            'status' => $advert->status->value,
            'expires_at' => $advert->expires_at?->toIso8601String(),
        ]]);
    }

    public function reject(ModerateTravelAdvertRequest $request, TravelAdvert $travelAdvert): JsonResponse
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
        if ($actor->cannot('moderate', $travelAdvert)) {
            abort(403);
        }

        $advert = $this->moderate->reject($travelAdvert, $actor, (string) $request->validated('reason'));

        return response()->json(['data' => [
            'id' => $advert->id,
            'status' => $advert->status->value,
        ]]);
    }

    public function renew(Request $request, TravelAdvert $travelAdvert): JsonResponse
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

        if ($advert->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate(['valid_days' => ['sometimes', 'integer', 'min:1', 'max:90']]);
        $days = (int) ($data['valid_days'] ?? 30);

        if (! in_array($advert->status, ['published', 'expired'], true)) {
            abort(422, 'Seule une annonce publiée ou expirée peut être renouvelée.');
        }

        // Renouvellement = nouveau paiement + re-validation (requalifie).
        $advert->forceFill([
            'status' => 'published',
            'paid_at' => now(),
            'validated_at' => now(),
            'published_at' => now(),
            'valid_until' => now()->addDays($days),
        ])->save();

        return response()->json(['data' => $this->payload($advert->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TravelAdvert $advert): array
    {
        return [
            'id' => $advert->id,
            'type_id' => $advert->type_id,
            'position_id' => $advert->position_id,
            'title' => $advert->title,
            'body' => $advert->body_redacted,
            'character_count' => $advert->character_count,
            'price_minor' => $advert->price_minor,
            'currency' => $advert->currency,
            'status' => $advert->status,
            'paid_at' => $advert->paid_at?->toIso8601String(),
            'validated_at' => $advert->validated_at?->toIso8601String(),
            'published_at' => $advert->published_at?->toIso8601String(),
            'valid_until' => $advert->valid_until?->toIso8601String(),
            'moderation_note' => $advert->moderation_note,
        ];
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
        if ($actor->cannot('renew', $travelAdvert)) {
            abort(404);
        }

        $advert = $this->renew->execute($travelAdvert);

        return response()->json(['data' => [
            'id' => $advert->id,
            'status' => $advert->status->value,
            'payment_reference' => $advert->payment_reference,
            'expires_at' => $advert->expires_at?->toIso8601String(),
        ]]);
    }
}
