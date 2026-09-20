<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7739 — Comptes clients grand public de la marketplace (épic #7736).
 *
 * Critères d'acceptation couverts :
 * - inscription : mot de passe HASHÉ, token Sanctum du guard DÉDIÉ
 *   `travel_customer` (jamais le guard employés), rattachement des
 *   réservations marketplace existantes portant le même e-mail de contact ;
 * - connexion : identifiants invalides → 401 indifférencié, verrouillage
 *   après échecs répétés (423) ;
 * - « mes réservations » : cross-agences mais STRICTEMENT bornées au compte
 *   (isolation client), aucune donnée d'agence sensible (nom public
 *   uniquement, pas de company_id) ;
 * - réservation créée connecté → rattachée au compte à la création ;
 * - le checkout invité reste possible (booking sans compte).
 */
class TravelCustomerAccountApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'voyage-secret-2026';

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

            return ['trip' => $trip, 'class' => $class->id];
        });
    }

    /**
     * Réserve en invité (sans compte) un siège du trajet, avec cet e-mail.
     */
    private function guestBooking(int $tripId, int $classId, string $email, string $idempotencyKey): string
    {
        $data = $this->postJson('/api/v1/public/travel/marketplace/bookings', [
            'trip_id' => $tripId,
            'idempotency_key' => $idempotencyKey,
            'contact_email' => $email,
            'passengers' => [
                ['full_name' => 'Voyageur Invité', 'age_category' => 'adult', 'class_id' => $classId],
            ],
        ])->assertStatus(201)->json('data');

        return (string) $data['reference'];
    }

    /**
     * @return array{token: string, account: array{id: int, email: string}, claimed: int}
     */
    private function register(string $email, string $name = 'Client Public'): array
    {
        $data = $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
        ])->assertStatus(201)->json('data');

        return [
            'token' => (string) $data['token'],
            'account' => $data['account'],
            'claimed' => (int) $data['claimed_bookings'],
        ];
    }

    public function test_register_hashes_password_and_claims_past_bookings_by_email(): void
    {
        $agencyA = $this->agency('Agence Alpha');
        $agencyB = $this->agency('Agence Beta');

        $fxA = $this->publishedTrip($agencyA, 'Douala', 'Yaoundé');
        $fxB = $this->publishedTrip($agencyB, 'Garoua', 'Maroua');

        // Réservations invité passées, chez DEUX agences, même e-mail
        // (casse différente : le rattachement est insensible à la casse).
        $this->guestBooking($fxA['trip']->id, $fxA['class'], 'Client@Example.test', 'acc-7739-a');
        $this->guestBooking($fxB['trip']->id, $fxB['class'], 'client@example.test', 'acc-7739-b');
        // Réservation d'un AUTRE voyageur : jamais rattachée.
        $this->guestBooking($fxA['trip']->id, $fxA['class'], 'autre@example.test', 'acc-7739-c');

        $result = $this->register('client@example.test');

        $this->assertSame(2, $result['claimed']);
        $this->assertNotSame('', $result['token']);

        /** @var TravelCustomerAccount $account */
        $account = TravelCustomerAccount::query()->where('email', 'client@example.test')->firstOrFail();

        // Mot de passe HASHÉ, jamais en clair.
        $this->assertNotSame(self::PASSWORD, $account->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $account->password));

        // Les deux réservations (cross-agences) sont rattachées au compte.
        $attached = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('customer_account_id', $account->id)
            ->count();
        $this->assertSame(2, $attached);

        // E-mail déjà pris (même en changeant la casse) → 422.
        $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => 'Doublon',
            'email' => 'CLIENT@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(422);
    }

    public function test_login_returns_dedicated_token_and_locks_after_repeated_failures(): void
    {
        $this->register('login@example.test');

        // Mauvais mot de passe → 401 indifférencié.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/public/travel/marketplace/account/login', [
                'email' => 'login@example.test',
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(401);
        }

        // 5e échec → verrouillage…
        $this->postJson('/api/v1/public/travel/marketplace/account/login', [
            'email' => 'login@example.test',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(401);

        // … et le compte est verrouillé, même avec le BON mot de passe (423).
        $this->postJson('/api/v1/public/travel/marketplace/account/login', [
            'email' => 'login@example.test',
            'password' => self::PASSWORD,
        ])->assertStatus(423);

        // Un autre compte n'est pas affecté : connexion normale.
        $this->register('libre@example.test');
        $login = $this->postJson('/api/v1/public/travel/marketplace/account/login', [
            'email' => 'libre@example.test',
            'password' => self::PASSWORD,
        ])->assertOk()->json('data');

        $this->assertSame('libre@example.test', $login['account']['email']);
        $this->assertNotSame('', (string) $login['token']);
    }

    public function test_me_and_logout_require_the_dedicated_guard(): void
    {
        $result = $this->register('profil@example.test');

        // Sans token → 401.
        $this->getJson('/api/v1/public/travel/marketplace/account/me')->assertStatus(401);

        $headers = ['Authorization' => 'Bearer '.$result['token']];

        $this->getJson('/api/v1/public/travel/marketplace/account/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.account.email', 'profil@example.test');

        // Logout révoque le token courant : l'appel suivant est rejeté.
        $this->postJson('/api/v1/public/travel/marketplace/account/logout', [], $headers)->assertOk();

        // Le RequestGuard sanctum CACHE l'utilisateur résolu entre deux
        // requêtes d'un même test Feature : sans reset, le `me` suivant
        // réutilise le client déjà authentifié alors que son token est
        // révoqué en base (pattern repo, cf. TravelAdvertDestroyTest).
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/public/travel/marketplace/account/me', $headers)->assertStatus(401);
    }

    public function test_my_bookings_are_cross_agency_but_strictly_isolated_per_customer(): void
    {
        $agencyA = $this->agency('Agence Alpha');
        $agencyB = $this->agency('Agence Beta');

        $fxA = $this->publishedTrip($agencyA, 'Douala', 'Yaoundé');
        $fxB = $this->publishedTrip($agencyB, 'Kribi', 'Limbé');

        $this->guestBooking($fxA['trip']->id, $fxA['class'], 'moi@example.test', 'iso-7739-a');
        $this->guestBooking($fxB['trip']->id, $fxB['class'], 'moi@example.test', 'iso-7739-b');
        $this->guestBooking($fxA['trip']->id, $fxA['class'], 'voisin@example.test', 'iso-7739-c');

        $me = $this->register('moi@example.test');
        $neighbour = $this->register('voisin@example.test');

        $mine = $this->getJson('/api/v1/public/travel/marketplace/account/bookings', [
            'Authorization' => 'Bearer '.$me['token'],
        ])->assertOk()->json();

        $this->assertSame(2, $mine['meta']['total']);

        $agencies = array_map(static fn (array $b): ?string => $b['agency']['name'], $mine['data']);
        $this->assertEqualsCanonicalizing(['Agence Alpha', 'Agence Beta'], $agencies);

        // Charge utile publique : jamais d'identifiant tenant ni de PII tierce.
        $this->assertArrayNotHasKey('company_id', $mine['data'][0]);
        $this->assertArrayHasKey('reference', $mine['data'][0]);
        $this->assertSame('pending', $mine['data'][0]['status']);
        $this->assertNotNull($mine['data'][0]['trip']);

        // Le voisin ne voit QUE sa réservation — isolation stricte. Reset du
        // cache de guard : sans lui, cette requête réutiliserait le client
        // précédent malgré le bearer différent (cf. TravelAdvertDestroyTest).
        app('auth')->forgetGuards();

        $others = $this->getJson('/api/v1/public/travel/marketplace/account/bookings', [
            'Authorization' => 'Bearer '.$neighbour['token'],
        ])->assertOk()->json();

        $this->assertSame(1, $others['meta']['total']);

        // Sans token → 401 (reset du cache de guard, même raison).
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/public/travel/marketplace/account/bookings')->assertStatus(401);
    }

    public function test_booking_made_while_logged_in_is_attached_to_the_account(): void
    {
        $agency = $this->agency('Agence Alpha');
        $fx = $this->publishedTrip($agency, 'Douala', 'Yaoundé');

        $result = $this->register('connecte@example.test');

        $booking = $this->postJson('/api/v1/public/travel/marketplace/bookings', [
            'trip_id' => $fx['trip']->id,
            'idempotency_key' => 'logged-7739-1',
            'contact_email' => 'connecte@example.test',
            'passengers' => [
                ['full_name' => 'Client Connecté', 'age_category' => 'adult', 'class_id' => $fx['class']],
            ],
        ], ['Authorization' => 'Bearer '.$result['token']])->assertStatus(201)->json('data');

        /** @var TravelBooking $stored */
        $stored = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('reference', $booking['reference'])
            ->firstOrFail();

        $this->assertSame((int) $result['account']['id'], $stored->customer_account_id);

        // Checkout INVITÉ toujours possible : sans token, pas de rattachement.
        // Reset du cache de guard : sans lui, la requête invitée hériterait du
        // client authentifié précédent et la réservation serait rattachée à
        // tort (cf. TravelAdvertDestroyTest).
        app('auth')->forgetGuards();

        $guestRef = $this->guestBooking($fx['trip']->id, $fx['class'], 'passant@example.test', 'guest-7739-1');

        /** @var TravelBooking $guest */
        $guest = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('reference', $guestRef)
            ->firstOrFail();

        $this->assertNull($guest->customer_account_id);
    }

    public function test_register_rejects_weak_passwords(): void
    {
        $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => 'Client Faible',
            'email' => 'faible@example.test',
            'password' => 'password1234',
        ])->assertStatus(422);

        $this->postJson('/api/v1/public/travel/marketplace/account/register', [
            'name' => 'Client Court',
            'email' => 'court@example.test',
            'password' => 'court1',
        ])->assertStatus(422);
    }
}
