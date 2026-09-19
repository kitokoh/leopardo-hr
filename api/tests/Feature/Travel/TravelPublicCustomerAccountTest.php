<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPublicCustomer;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7739 — Comptes clients GRAND PUBLIC de la marketplace voyage.
 *
 * Critères d'acceptation couverts :
 * - inscription/connexion avec mot de passe HASHÉ, email unique, throttling
 *   sur login + verrouillage progressif du compte ;
 * - tokens Sanctum DÉDIÉS (guard `travel_customer_api`) : un token employé
 *   ne donne accès à rien sur l'espace compte ;
 * - un client inscrit retrouve ses réservations passées (rattachées par
 *   compte via l'email de contact, pas seulement par référence) ;
 * - « mes réservations » est CROSS-AGENCES et STRICTEMENT isolé : jamais les
 *   réservations d'un autre client, aucun identifiant tenant exposé ;
 * - une réservation créée avec un token client est rattachée à la création ;
 * - le checkout INVITÉ reste possible (aucun compte requis).
 */
class TravelPublicCustomerAccountTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'voyage-secret-2026-42';

    /**
     * Entre deux requêtes du MÊME test, Laravel réutilise l'application :
     * le guard Sanctum mémorise l'utilisateur résolu et `withHeader` persiste
     * dans les defaults — on repart d'un état vierge (comportement HTTP réel,
     * une app par requête).
     */
    private function freshAuthState(): void
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();
    }

    private function agency(string $name): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => $name,
            'country' => 'CM',
            'currency' => 'XAF',
        ]);

        $company->setFeature('travelagency', true);
        $company->save();

        TravelPublicShopToken::query()->create([
            'company_id' => $company->id,
            'token_hash' => TravelPublicShopToken::hash('tshop_'.$name.'_'.random_int(1000, 9999)),
            'name' => $name,
            'active' => true,
        ]);

        return $company;
    }

    /**
     * @return array{trip: TravelTrip, class: int}
     */
    private function publishedTrip(Company $company, string $originName = 'Douala', string $destinationName = 'Yaoundé'): array
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

            return ['trip' => $trip, 'class' => $class->id];
        });
    }

    private function guestBooking(Company $company, TravelTrip $trip, string $email, string $idempotencyKey): TravelBooking
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($trip, $email, $idempotencyKey): TravelBooking {
            /** @var TravelBooking $booking */
            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'booking_source' => 'marketplace',
                'contact_email' => $email,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $booking;
        });
    }

    /**
     * @return array{customer: array<string, mixed>, token: string}
     */
    private function registerCustomer(string $email = 'client@example.test'): array
    {
        $response = $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'first_name' => 'Awa',
            'last_name' => 'Ndiaye',
            'email' => $email,
            'password' => self::PASSWORD,
        ])->assertStatus(201);

        return ['customer' => $response->json('data'), 'token' => (string) $response->json('token')];
    }

    public function test_register_hashes_password_enforces_unique_email_and_returns_dedicated_token(): void
    {
        $result = $this->registerCustomer();

        $this->assertNotEmpty($result['token']);
        $this->assertSame('client@example.test', $result['customer']['email']);

        /** @var TravelPublicCustomer $customer */
        $customer = TravelPublicCustomer::query()->where('email', 'client@example.test')->firstOrFail();

        // Mot de passe HASHÉ — jamais stocké en clair.
        $this->assertNotSame(self::PASSWORD, $customer->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $customer->password));

        // Email unique → 422.
        $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'first_name' => 'Awa',
            'last_name' => 'Doublon',
            'email' => 'client@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(422);

        // Mot de passe faible refusé.
        $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'first_name' => 'Awa',
            'last_name' => 'Faible',
            'email' => 'autre@example.test',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_login_returns_token_and_wrong_password_locks_after_five_attempts(): void
    {
        $this->registerCustomer();

        $this->postJson('/api/v1/public/travel/marketplace/account/login', [
            'email' => 'client@example.test',
            'password' => self::PASSWORD,
        ])->assertOk()->assertJsonStructure(['data', 'token']);

        // 5 échecs → verrouillage 15 min (423), même avec le BON mot de passe.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/public/travel/marketplace/account/login', [
                'email' => 'client@example.test',
                'password' => 'mauvais-mot-de-passe-1',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/public/travel/marketplace/account/login', [
            'email' => 'client@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(423);
    }

    public function test_login_is_throttled_by_rate_limiter(): void
    {
        // Bucket auth-sensitive réduit (email+IP) : l'inscription consomme la
        // 1re tentative du MÊME bucket — 4 au total = 3 logins ratés permis.
        config(['security.rate_limits.auth_per_minute' => 4]);

        $this->registerCustomer();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/public/travel/marketplace/account/login', [
                'email' => 'client@example.test',
                'password' => 'mauvais-mot-de-passe-1',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/public/travel/marketplace/account/login', [
            'email' => 'client@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(429);
    }

    public function test_registered_customer_finds_past_guest_bookings_across_agencies(): void
    {
        $agencyA = $this->agency('Agence Alpha');
        $agencyB = $this->agency('Agence Beta');

        $fxA = $this->publishedTrip($agencyA);
        $fxB = $this->publishedTrip($agencyB, 'Garoua', 'Maroua');

        // Réservations INVITÉES passées, chez DEUX agences, même email.
        $this->guestBooking($agencyA, $fxA['trip'], 'client@example.test', 'guest-a-1');
        $this->guestBooking($agencyB, $fxB['trip'], 'Client@Example.test', 'guest-b-1');
        // Réservation d'un AUTRE voyageur — ne doit jamais apparaître.
        $other = $this->guestBooking($agencyA, $fxA['trip'], 'autre@example.test', 'guest-a-2');

        $result = $this->registerCustomer();

        $response = $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->getJson('/api/v1/public/travel/marketplace/account/bookings')
            ->assertOk();

        $data = $response->json('data');

        // Les deux réservations invitées sont rattachées (email de contact,
        // insensible à la casse) — cross-agences, pas seulement par référence.
        $this->assertCount(2, $data);
        $agencies = array_map(static fn (array $booking): string => $booking['agency']['name'], $data);
        $this->assertEqualsCanonicalizing(['Agence Alpha', 'Agence Beta'], $agencies);

        // Charge utile minimisée : aucun identifiant tenant ni PII étrangère.
        $this->assertArrayNotHasKey('company_id', $data[0]);
        $this->assertArrayNotHasKey('contact_email', $data[0]);
        $this->assertArrayHasKey('reference', $data[0]);
        $this->assertArrayHasKey('trip', $data[0]);

        // La réservation de l'autre voyageur reste orpheline.
        $this->assertNull($other->fresh()?->public_customer_id);
    }

    public function test_customer_only_sees_their_own_bookings(): void
    {
        $agency = $this->agency('Agence Alpha');
        $fx = $this->publishedTrip($agency);

        $this->guestBooking($agency, $fx['trip'], 'client@example.test', 'own-1');
        $this->guestBooking($agency, $fx['trip'], 'intrus@example.test', 'intrus-1');

        $mine = $this->registerCustomer();
        $this->freshAuthState();
        $intruder = $this->registerCustomer('intrus@example.test');

        $this->freshAuthState();
        $mineRefs = collect($this->withHeader('Authorization', 'Bearer '.$mine['token'])
            ->getJson('/api/v1/public/travel/marketplace/account/bookings')
            ->assertOk()->json('data'))->pluck('reference');

        $this->freshAuthState();
        $intruderRefs = collect($this->withHeader('Authorization', 'Bearer '.$intruder['token'])
            ->getJson('/api/v1/public/travel/marketplace/account/bookings')
            ->assertOk()->json('data'))->pluck('reference');

        $this->assertCount(1, $mineRefs);
        $this->assertCount(1, $intruderRefs);
        $this->assertSame([], $mineRefs->intersect($intruderRefs)->all());
    }

    public function test_authenticated_marketplace_booking_is_attached_at_creation_and_guest_checkout_still_works(): void
    {
        $agency = $this->agency('Agence Alpha');
        $fx = $this->publishedTrip($agency);

        $result = $this->registerCustomer();

        // Réservation AVEC token client → rattachée à la création.
        $reference = $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->postJson('/api/v1/public/travel/marketplace/bookings', [
                'trip_id' => $fx['trip']->id,
                'idempotency_key' => 'account-checkout-1',
                'contact_email' => 'client@example.test',
                'passengers' => [
                    ['full_name' => 'Awa Ndiaye', 'age_category' => 'adult', 'class_id' => $fx['class']],
                ],
            ])->assertStatus(201)->json('data.reference');

        /** @var TravelPublicCustomer $customer */
        $customer = TravelPublicCustomer::query()->where('email', 'client@example.test')->firstOrFail();

        /** @var TravelBooking $attached */
        $attached = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('reference', $reference)
            ->firstOrFail();
        $this->assertSame($customer->id, $attached->public_customer_id);

        // Checkout INVITÉ (sans token) → toujours possible, non rattaché.
        $this->freshAuthState();
        $guestReference = $this->postJson('/api/v1/public/travel/marketplace/bookings', [
            'trip_id' => $fx['trip']->id,
            'idempotency_key' => 'guest-checkout-1',
            'contact_email' => 'invite@example.test',
            'passengers' => [
                ['full_name' => 'Invité Anonyme', 'age_category' => 'adult', 'class_id' => $fx['class']],
            ],
        ])->assertStatus(201)->json('data.reference');

        /** @var TravelBooking $guest */
        $guest = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('reference', $guestReference)
            ->firstOrFail();
        $this->assertNull($guest->public_customer_id);
    }

    public function test_account_endpoints_reject_missing_and_employee_tokens(): void
    {
        // Sans token → 401.
        $this->getJson('/api/v1/public/travel/marketplace/account/me')->assertStatus(401);
        $this->getJson('/api/v1/public/travel/marketplace/account/bookings')->assertStatus(401);

        // Token EMPLOYÉ (guard sanctum) → jamais accepté sur le guard client.
        $agency = $this->agency('Agence Alpha');
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $agency->id]);
        $employeeToken = $employee->createToken('employee')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$employeeToken)
            ->getJson('/api/v1/public/travel/marketplace/account/me')
            ->assertStatus(401);
    }

    public function test_logout_revokes_current_token(): void
    {
        $result = $this->registerCustomer();

        $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->postJson('/api/v1/public/travel/marketplace/account/logout')
            ->assertOk();

        $this->freshAuthState();

        $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->getJson('/api/v1/public/travel/marketplace/account/me')
            ->assertStatus(401);
    }
}
