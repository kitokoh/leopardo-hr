<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7737 — Marketplace publique inter-agences (épic #7736).
 *
 * Critères d'acceptation couverts :
 * - la recherche agrège les trajets publiés de ≥ 2 agences distinctes SANS
 *   jeton d'agence ;
 * - les agences sans jeton boutique actif ou sans feature `travelagency`
 *   sont exclues ;
 * - la réservation est créée chez la BONNE agence (company_id du trajet,
 *   booking_source=marketplace) et reste invisible ailleurs ;
 * - détail par trajet (résolution tenant par trajet) fail-closed 404 pour
 *   un trajet non éligible ;
 * - villes dédupliquées par identité géographique (nom + pays).
 */
class TravelMarketplaceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function agency(string $name, bool $activeToken = true, bool $withFeature = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => $name,
            'country' => 'CM',
            'currency' => 'XAF',
        ]);

        if ($withFeature) {
            $company->setFeature('travelagency', true);
            $company->save();
        }

        TravelPublicShopToken::query()->create([
            'company_id' => $company->id,
            'token_hash' => TravelPublicShopToken::hash('tshop_'.$name.'_'.random_int(1000, 9999)),
            'name' => $name,
            'active' => $activeToken,
        ]);

        return $company;
    }

    /**
     * Publie un trajet ville→ville chez l'agence, avec sièges et tarif.
     *
     * @return array{trip: TravelTrip, origin: TravelCity, destination: TravelCity, class: int}
     */
    private function publishedTrip(Company $company, string $originName, string $destinationName): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($originName, $destinationName): array {
            $origin = TravelCity::factory()->create(['name' => $originName, 'country_iso2' => 'CM']);
            $destination = TravelCity::factory()->create(['name' => $destinationName, 'country_iso2' => 'CM']);

            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $destination->id,
            ]);

            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 12,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'currency' => 'XAF',
            ]);

            return ['trip' => $trip, 'origin' => $origin, 'destination' => $destination, 'class' => $class->id];
        });
    }

    public function test_search_aggregates_published_trips_from_multiple_agencies_without_token(): void
    {
        $agencyA = $this->agency('Agence Alpha');
        $agencyB = $this->agency('Agence Beta');

        $fxA = $this->publishedTrip($agencyA, 'Douala', 'Yaoundé');
        $this->publishedTrip($agencyB, 'Douala', 'Yaoundé');

        $response = $this->getJson(
            '/api/v1/public/travel/marketplace/trips'
            .'?origin_city_id='.$fxA['origin']->id
            .'&destination_city_id='.$fxA['destination']->id
        )->assertOk();

        $data = $response->json('data');

        $this->assertCount(2, $data);

        $agencies = array_unique(array_map(static fn (array $trip): string => $trip['agency']['name'], $data));
        $this->assertEqualsCanonicalizing(['Agence Alpha', 'Agence Beta'], $agencies);

        // Prix, sièges disponibles et villes exposés ; aucun identifiant tenant.
        $this->assertSame(15000, $data[0]['price_from_minor']);
        $this->assertSame(12, $data[0]['available_seats']);
        $this->assertSame('Douala', $data[0]['origin_city']['name']);
        $this->assertArrayNotHasKey('company_id', $data[0]);
    }

    public function test_agencies_without_active_token_or_feature_are_excluded(): void
    {
        $eligible = $this->agency('Agence Visible');
        $inactiveToken = $this->agency('Agence Jeton Inactif', activeToken: false);
        $noFeature = $this->agency('Agence Sans Feature', withFeature: false);

        $this->publishedTrip($eligible, 'Garoua', 'Maroua');
        $this->publishedTrip($inactiveToken, 'Garoua', 'Maroua');
        $this->publishedTrip($noFeature, 'Garoua', 'Maroua');

        $data = $this->getJson('/api/v1/public/travel/marketplace/trips')->assertOk()->json('data');

        $agencies = array_map(static fn (array $trip): string => $trip['agency']['name'], $data);

        $this->assertSame(['Agence Visible'], array_values(array_unique($agencies)));
    }

    public function test_cities_are_deduplicated_by_name_and_country(): void
    {
        $agencyA = $this->agency('Agence Alpha');
        $agencyB = $this->agency('Agence Beta');

        $this->publishedTrip($agencyA, 'Douala', 'Yaoundé');
        $this->publishedTrip($agencyB, 'Douala', 'Bafoussam');

        $data = $this->getJson('/api/v1/public/travel/marketplace/cities')->assertOk()->json('data');

        $names = array_map(static fn (array $city): string => $city['name'], $data);

        $this->assertEqualsCanonicalizing(['Bafoussam', 'Douala', 'Yaoundé'], $names);
        $this->assertCount(3, $names); // « Douala » n'apparaît qu'une fois.
    }

    public function test_trip_detail_resolves_tenant_by_trip_and_is_fail_closed(): void
    {
        $eligible = $this->agency('Agence Visible');
        $optOut = $this->agency('Agence Opt-out', activeToken: false);

        $fx = $this->publishedTrip($eligible, 'Kribi', 'Limbé');
        $hidden = $this->publishedTrip($optOut, 'Kribi', 'Limbé');

        $this->getJson('/api/v1/public/travel/marketplace/trips/'.$fx['trip']->id)
            ->assertOk()
            ->assertJsonPath('data.agency.name', 'Agence Visible')
            ->assertJsonPath('data.available_seats', 12)
            ->assertJsonCount(12, 'data.seats');

        // Trajet d'une agence non opt-in → 404 (aucune résolution de tenant).
        $this->getJson('/api/v1/public/travel/marketplace/trips/'.$hidden['trip']->id)
            ->assertStatus(404);
    }

    public function test_marketplace_booking_is_created_in_the_right_tenant_and_invisible_elsewhere(): void
    {
        $agencyA = $this->agency('Agence Alpha');
        $agencyB = $this->agency('Agence Beta');

        $this->publishedTrip($agencyA, 'Douala', 'Yaoundé');
        $fxB = $this->publishedTrip($agencyB, 'Douala', 'Yaoundé');

        $payload = [
            'trip_id' => $fxB['trip']->id,
            'idempotency_key' => 'marketplace-7737-1',
            'contact_email' => 'voyageur@example.test',
            'notify_consent' => true,
            'passengers' => [
                ['full_name' => 'Voyageur Marketplace', 'age_category' => 'adult', 'class_id' => $fxB['class']],
            ],
        ];

        $booking = $this->postJson('/api/v1/public/travel/marketplace/bookings', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('pending', $booking['status']);
        $this->assertSame('marketplace', $booking['booking_source']);
        $this->assertSame(15000, $booking['total_amount_minor']);

        // Routée chez la BONNE agence (company_id du trajet)…
        /** @var TravelBooking $stored */
        $stored = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('reference', $booking['reference'])
            ->firstOrFail();
        $this->assertSame((string) $agencyB->id, (string) $stored->company_id);

        // … et INVISIBLE chez l'autre agence (scope tenant).
        $countInA = app(TenantManager::class)->withinTenant(
            $agencyA,
            static fn (): int => TravelBooking::query()->count()
        );
        $this->assertSame(0, $countInA);

        // Idempotence : rejouer la même clé ne crée pas de doublon.
        $this->postJson('/api/v1/public/travel/marketplace/bookings', $payload)->assertStatus(201);
        $this->assertSame(1, TravelBooking::query()->withoutGlobalScope('company')->count());
    }

    public function test_marketplace_payment_initiation_resolves_tenant_by_reference(): void
    {
        $agency = $this->agency('Agence Alpha');
        $fx = $this->publishedTrip($agency, 'Douala', 'Yaoundé');

        $booking = $this->postJson('/api/v1/public/travel/marketplace/bookings', [
            'trip_id' => $fx['trip']->id,
            'idempotency_key' => 'marketplace-7737-pay',
            'passengers' => [
                ['full_name' => 'Voyageur Payeur', 'age_category' => 'adult', 'class_id' => $fx['class']],
            ],
        ])->assertStatus(201)->json('data');

        $payment = $this->postJson('/api/v1/public/travel/marketplace/payments/initiate', [
            'booking_reference' => $booking['reference'],
            'provider_code' => 'cash',
            'idempotency_key' => 'marketplace-7737-pay-1',
        ])->assertStatus(201)->json('data');

        $this->assertSame('pending', $payment['status']);

        // Rejeu idempotent : même paiement, pas de doublon (200).
        $this->postJson('/api/v1/public/travel/marketplace/payments/initiate', [
            'booking_reference' => $booking['reference'],
            'provider_code' => 'cash',
            'idempotency_key' => 'marketplace-7737-pay-1',
        ])->assertOk()->assertJsonPath('data.reference', $payment['reference']);

        // Référence inconnue → 404 fail-closed.
        $this->postJson('/api/v1/public/travel/marketplace/payments/initiate', [
            'booking_reference' => 'GV-INCONNUE',
            'provider_code' => 'cash',
            'idempotency_key' => 'marketplace-7737-pay-2',
        ])->assertStatus(404);
    }
}
