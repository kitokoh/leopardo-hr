<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
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
     * Mode public (défaut) : annonces VISIBLES uniquement (payée + validée
     * + non expirée). Mode gestion (TravelAdvertPolicy::moderate) : toutes
     * les annonces du tenant, filtrables par `?status=` — nécessaire à
     * l'UI admin pour modérer soumissions/paiements (TRAVEL-911/#6416,
     * TRAVEL-914/#6422).
     */
    /**
     * Liste des annonces.
     *
     * Mode public (défaut) : annonces VISIBLES uniquement (payée + validée
     * + non expirée). Mode gestion (TravelAdvertPolicy::moderate) : toutes
     * les annonces du tenant, filtrables par `?status=` — nécessaire à
     * l'UI admin pour modérer soumissions/paiements (TRAVEL-911/#6416,
     * TRAVEL-914/#6422).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = TravelAdvert::query()->where('company_id', $actor->company_id);

        $manage = $actor->can('moderate', TravelAdvert::class);

        if (! $manage) {
            $adverts = $query->get()->filter(fn (TravelAdvert $a) => $a->isVisible())->values();
        } else {
            $query->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));
            $adverts = $query->orderByDesc('id')->get();
        }

        $data = $adverts->map(fn (TravelAdvert $a) => [
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

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

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

    public function validate(Request $request, TravelAdvert $travelAdvert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

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
