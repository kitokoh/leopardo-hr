<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelBookingResource;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTripResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TRAVEL-1001 (#6114) — Boutique publique (jeton signé par tenant).
 *
 * Recherche/détail/réservation/suivi exposés SANS auth utilisateur :
 * le tenant est résolu par le jeton (middleware `travel.public.shop`),
 * le scope BelongsToCompany s'applique → aucune donnée cross-tenant
 * (critère d'acceptation). Rate limiting renforcé (`throttle:shop-public`)
 * + hook anti-bot (CAPTCHA configurable).
 */
class TravelPublicShopController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $trips = TravelTrip::query()
            ->with(['prices', 'route.stops'])
            ->where('status', TripStatus::PUBLISHED)
            ->when($request->query('origin_city_id'), fn ($q, $cityId) => $q->whereHas('route', fn ($route) => $route->where('origin_city_id', $cityId)))
            ->when($request->query('destination_city_id'), fn ($q, $cityId) => $q->whereHas('route', fn ($route) => $route->where('destination_city_id', $cityId)))
            ->when($request->query('departure_date'), fn ($q, $date) => $q->whereDate('departure_date', (string) $date))
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->paginate($perPage);

        return TravelTripResource::collection($trips)->response();
    }

    public function show(Request $request, TravelTrip $travelTrip): JsonResponse
    {
        // Le binding implicite précède les middlewares de route : le scope
        // tenant ne filtre pas au binding → contrôle explicite (404 sûr).
        if ($travelTrip->company_id !== currentCompany()->id) {
            abort(404);
        }

        if ($travelTrip->status !== TripStatus::PUBLISHED) {
            abort(404);
        }

        $travelTrip->load(['prices', 'route.stops', 'seats' => fn ($q) => $q->where('status', SeatStatus::FREE)]);

        return (new TravelTripResource($travelTrip))->response();
    }

    /**
     * Réservation en ligne publique (source online, idempotente).
     */
    public function storeBooking(StoreTravelBookingRequest $request): JsonResponse
    {
        /** @var TravelTrip $trip */
        $trip = TravelTrip::query()->findOrFail($request->validated('trip_id'));

        if ($trip->status !== TripStatus::PUBLISHED) {
            abort(409, 'Ce trajet n\'est pas ouvert à la réservation en ligne.');
        }

        $booking = app(CreateBookingAction::class)->execute(
            trip: $trip,
            passengers: $request->validated('passengers'),
            source: BookingSource::ONLINE,
            actor: null,
            idempotencyKey: $request->validated('idempotency_key'),
            contactEmail: $request->validated('contact_email'),
            contactPhone: $request->validated('contact_phone'),
            notifyConsent: (bool) $request->validated('notify_consent', false),
        );

        return (new TravelBookingResource($booking))->response()->setStatusCode(201);
    }

    /**
     * Suivi public par référence + code de validation (jamais de données
     * sensibles : statut, trajet, passagers anonymisés).
     */
    public function track(Request $request, string $reference): JsonResponse
    {
        $code = (string) $request->query('code', '');

        if ($code === '') {
            abort(422, 'code requis.');
        }

        $booking = TravelBooking::query()
            ->where('reference', $reference)
            ->with('trip')
            ->first();

        if (! $booking instanceof TravelBooking) {
            abort(404);
        }

        // Le code de validation d'AU MOINS un billet doit correspondre
        // (hash — le code en clair n'est jamais exposé).
        $ticket = TravelTicket::query()
            ->where('booking_id', $booking->id)
            ->get()
            ->first(fn (TravelTicket $t): bool => $t->validationCodeMatches($code));

        if (! $ticket instanceof TravelTicket) {
            abort(404, 'Code de validation invalide.');
        }

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'payment_status' => $booking->payment_status->value,
                'trip' => $booking->trip ? [
                    'code' => $booking->trip->code,
                    'departure_date' => $booking->trip->departure_date?->toDateString(),
                    'departure_time' => $booking->trip->departure_time,
                ] : null,
                'passenger_count' => $booking->passenger_count,
            ],
        ]);
    }

    // ── Gestion du jeton (authentifié, travel.manage) ───────────────────────

    public function token(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $token = TravelPublicShopToken::query()
            ->where('company_id', $actor->company_id)
            ->first();

        if (! $token instanceof TravelPublicShopToken) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => $token->id,
            'name' => $token->name,
            'active' => $token->active,
            'token_prefix' => substr((string) $token->token_hash, 0, 8).'…',
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
        ]]);
    }

    /**
     * (Re)génère le jeton : l'ancien est invalidé immédiatement.
     */
    public function rotateToken(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $plain = 'tshop_'.Str::random(48);

        $token = TravelPublicShopToken::query()->updateOrCreate(
            ['company_id' => $actor->company_id],
            [
                'token_hash' => TravelPublicShopToken::hash($plain),
                'name' => 'Public shop',
                'active' => true,
            ],
        );

        // Le jeton en clair n'est retourné QU'à la rotation (jamais relu).
        return response()->json(['data' => [
            'id' => $token->id,
            'token' => $plain,
            'active' => true,
        ]]);
    }
}
