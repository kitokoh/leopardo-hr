<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelPublicCustomer;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Infrastructure\Services\Payment\PaymentGatewayRegistry;
use App\Modules\TravelAgency\Infrastructure\Services\TravelMarketplaceService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelMarketplaceBookingRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelBookingResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Issue #7737 — API publique MARKETPLACE inter-agences (épic #7736).
 *
 * Étend la boutique mono-tenant (TRAVEL-1001/#6114, `X-Travel-Shop-Token`)
 * en place de marché : le grand public cherche parmi les trajets PUBLIÉS de
 * toutes les agences opt-in (jeton boutique actif + feature `travelagency`)
 * SANS jeton d'agence — throttling `shop-public` uniquement.
 *
 * Le tenant est résolu PAR TRAJET (détail, réservation) ou PAR RÉFÉRENCE de
 * réservation (paiement), puis posé via `TenantManager::withinTenant()` +
 * marqueur `tenant_scope_required` (fail-closed, pattern
 * `EnsurePublicShopAccess`) : toute écriture atterrit chez la BONNE agence,
 * le scope `BelongsToCompany` interdit la fuite cross-tenant. Suivi,
 * annulation et e-billet réutilisent la surface passager existante (#7395 :
 * référence + code de validation), aliasée sous `/public/travel/marketplace`.
 *
 * Aucune donnée sensible d'agence n'est exposée : uniquement le nom public.
 */
class TravelMarketplaceController extends Controller
{
    public function __construct(
        private readonly TravelMarketplaceService $marketplace,
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Villes desservies par les agences opt-in (autocomplete).
     */
    public function cities(): JsonResponse
    {
        return response()->json(['data' => $this->marketplace->cities()]);
    }

    /**
     * Recherche agrégée cross-tenant des trajets publiés.
     */
    public function search(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'origin_city_id' => ['nullable', 'integer'],
            'destination_city_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $trips = $this->marketplace->searchTrips(
            originCityId: isset($filters['origin_city_id']) ? (int) $filters['origin_city_id'] : null,
            destinationCityId: isset($filters['destination_city_id']) ? (int) $filters['destination_city_id'] : null,
            date: isset($filters['date']) ? (string) $filters['date'] : null,
            perPage: isset($filters['per_page']) ? (int) $filters['per_page'] : 20,
        );

        /** @var list<TravelTrip> $items */
        $items = $trips->items();

        /** @var array<string, Company> $agencies */
        $agencies = Company::query()
            ->whereIn('id', collect($items)->map(fn (TravelTrip $trip): string => (string) $trip->company_id)->unique()->values())
            ->get()
            ->keyBy(fn (Company $company): string => (string) $company->id)
            ->all();

        return response()->json([
            'data' => collect($items)
                ->map(fn (TravelTrip $trip): array => $this->tripPayload($trip, $agencies[(string) $trip->company_id] ?? null))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $trips->currentPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
                'last_page' => $trips->lastPage(),
            ],
        ]);
    }

    /**
     * Détail d'un trajet + plan de sièges libres (tenant résolu par trajet).
     */
    public function show(string $trip): JsonResponse
    {
        $model = ctype_digit($trip) ? $this->marketplace->findEligibleTrip((int) $trip) : null;

        if (! $model instanceof TravelTrip) {
            abort(404);
        }

        $model->load(['prices', 'route.originCity', 'route.destinationCity']);

        $freeSeats = TravelTripSeat::query()
            ->withoutGlobalScope('company')
            ->where('trip_id', $model->id)
            ->where('status', SeatStatus::FREE)
            ->orderBy('seat_number')
            ->get();

        $model->setAttribute('available_seats', $freeSeats->count());

        $payload = $this->tripPayload($model, $this->marketplace->companyFor($model));
        $payload['seats'] = $freeSeats
            ->map(fn (TravelTripSeat $seat): array => [
                'seat_number' => $seat->seat_number,
                'status' => 'free',
            ])
            ->values()
            ->all();

        return response()->json(['data' => $payload]);
    }

    /**
     * Réservation marketplace : tenant résolu depuis `trip_id`, puis
     * délégation au flux TRAVEL-1001 existant (`CreateBookingAction`,
     * idempotent) avec `booking_source=marketplace`.
     */
    public function storeBooking(StoreTravelMarketplaceBookingRequest $request): JsonResponse
    {
        $trip = $this->marketplace->findEligibleTrip((int) $request->validated('trip_id'));

        if (! $trip instanceof TravelTrip) {
            // Trajet inconnu, non publié ou agence non opt-in : fail-closed.
            abort(404);
        }

        $company = $this->marketplace->companyFor($trip);

        if (! $company instanceof Company) {
            abort(404);
        }

        /** @var list<array{full_name: string, birth_date?: string|null, document_type?: string|null, document_number?: string|null, age_category: string, class_id: int, seat_number?: int|null}> $passengers */
        $passengers = $request->validated('passengers');

        $booking = $this->withinTenantScoped($company, fn (): TravelBooking => app(CreateBookingAction::class)->execute(
            trip: $trip,
            passengers: $passengers,
            source: BookingSource::MARKETPLACE,
            actor: null,
            idempotencyKey: (string) $request->validated('idempotency_key'),
            contactEmail: $request->validated('contact_email'),
            contactPhone: $request->validated('contact_phone'),
            notifyConsent: (bool) $request->validated('notify_consent', false),
        ));

        // #7739 — rattachement À LA CRÉATION : token client grand public
        // présent → la réservation est liée au compte (le checkout invité,
        // sans token, reste inchangé). Écriture bornée à public_customer_id.
        $customer = $request->user('travel_customer_api');
        if ($customer instanceof TravelPublicCustomer && $booking->public_customer_id === null) {
            $this->withinTenantScoped($company, function () use ($booking, $customer): void {
                $booking->forceFill(['public_customer_id' => $customer->id])->save();
            });
        }

        return (new TravelBookingResource($booking))
            ->additional(['agency' => ['name' => $company->name]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Initiation de paiement d'une réservation MARKETPLACE : le tenant est
     * résolu par la référence (unique par tenant — une référence ambiguë
     * cross-tenant est un 404 fail-closed, pattern `EnsurePublicShopAccess`),
     * puis même contrat idempotent que la boutique (TRAVEL-1002/#6115).
     */
    public function initiatePayment(Request $request, PaymentGatewayRegistry $gateways): JsonResponse
    {
        $data = $request->validate([
            'booking_reference' => ['required', 'string', 'max:40'],
            'provider_code' => ['required', 'string', 'in:cash,pvit,momo,card'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        $eligible = $this->marketplace->eligibleCompanyIds();

        $matches = $eligible === [] ? collect() : TravelBooking::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('reference', $data['booking_reference'])
            ->where('booking_source', BookingSource::MARKETPLACE)
            ->limit(2)
            ->get();

        /** @var TravelBooking|null $booking */
        $booking = $matches->count() === 1 ? $matches->first() : null;

        if (! $booking instanceof TravelBooking) {
            abort(404, __('travel.marketplace.booking_not_found'));
        }

        $company = Company::query()->find($booking->company_id);

        if (! $company instanceof Company) {
            abort(404);
        }

        return $this->withinTenantScoped($company, function () use ($booking, $data, $gateways): JsonResponse {
            $existing = TravelPayment::query()
                ->where('booking_id', $booking->id)
                ->where('provider_code', $data['provider_code'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof TravelPayment) {
                return response()->json([
                    'data' => [
                        'reference' => $existing->reference,
                        'provider_reference' => $existing->provider_reference,
                        'status' => $existing->status->value,
                    ],
                ]);
            }

            $gateway = $gateways->get($data['provider_code']);

            $result = $gateway->initiate([
                'booking_reference' => $booking->reference,
                'amount_minor' => $booking->total_amount_minor,
                'currency' => $booking->currency,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $payment = DB::transaction(fn (): TravelPayment => TravelPayment::query()->create([
                'booking_id' => $booking->id,
                'provider_code' => $data['provider_code'],
                'amount_minor' => $booking->total_amount_minor,
                'currency' => $booking->currency,
                'status' => PaymentStatus::PENDING,
                'provider_reference' => $result['provider_reference'],
                'idempotency_key' => $data['idempotency_key'],
            ]));

            return response()->json([
                'data' => [
                    'reference' => $payment->reference,
                    'provider_reference' => $payment->provider_reference,
                    'status' => $payment->status->value,
                ],
            ])->setStatusCode(201);
        });
    }

    /**
     * Exécute `$callback` dans le contexte du tenant AVEC le marqueur
     * fail-closed `tenant_scope_required` (même sémantique que le middleware
     * `EnsurePublicShopAccess`) : le scope `BelongsToCompany` s'applique à
     * toutes les lectures/écritures — aucune fuite cross-tenant possible.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function withinTenantScoped(Company $company, Closure $callback)
    {
        app()->instance('tenant_scope_required', true);

        try {
            return $this->tenants->withinTenant($company, $callback);
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }

    /**
     * Charge utile publique d'un trajet — prix, sièges disponibles, villes et
     * NOM PUBLIC de l'agence uniquement (jamais d'identifiant tenant ni de
     * donnée interne).
     *
     * @return array<string, mixed>
     */
    private function tripPayload(TravelTrip $trip, ?Company $agency): array
    {
        $route = $trip->route;
        $prices = $trip->prices;

        $adultPrices = $prices
            ->map(fn (TravelTripPrice $price): int => (int) $price->adult_price_minor)
            ->filter(fn (int $amount): bool => $amount > 0);

        return [
            'id' => $trip->id,
            'code' => $trip->code,
            'departure_date' => $trip->departure_date->toDateString(),
            'departure_time' => $trip->departure_time,
            'arrival_date' => $trip->arrival_date->toDateString(),
            'arrival_time' => $trip->arrival_time,
            'means_of_transport' => $trip->means_of_transport,
            'total_seats' => $trip->total_seats,
            'available_seats' => (int) ($trip->getAttribute('available_seats') ?? 0),
            'origin_city' => $this->cityPayload($route?->originCity?->name, $route?->originCity?->country_iso2),
            'destination_city' => $this->cityPayload($route?->destinationCity?->name, $route?->destinationCity?->country_iso2),
            'prices' => $prices->map(fn (TravelTripPrice $price): array => [
                'class_id' => $price->class_id,
                'adult_price_minor' => $price->adult_price_minor,
                'child_price_minor' => $price->child_price_minor,
                'currency' => $price->currency,
            ])->values()->all(),
            'price_from_minor' => $adultPrices->isEmpty() ? null : $adultPrices->min(),
            'currency' => $prices->first()?->currency,
            'agency' => ['name' => $agency?->name],
        ];
    }

    /**
     * @return array{name: string, country_iso2: string}|null
     */
    private function cityPayload(?string $name, ?string $iso2): ?array
    {
        if ($name === null) {
            return null;
        }

        return ['name' => $name, 'country_iso2' => (string) $iso2];
    }
}
