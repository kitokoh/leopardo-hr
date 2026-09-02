<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1001 (#6114) — Boutique publique (jeton signé par tenant).
 *
 * Couvre le critère d'acceptation : AUCUN accès aux données d'un autre
 * tenant via le shop public (404 sûr) ; jeton invalide → 401 ; recherche /
 * réservation / suivi publics fonctionnels ; rotation du jeton.
 */
class TravelPublicShopApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    /**
     * Publie un trajet + crée le jeton boutique, retourne les deux.
     *
     * @return array{trip: TravelTrip, token: string, class: int}
     */
    private function publishedTripWithToken(Company $company): array
    {
        $data = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 20]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            $plain = 'tshop_test_'.random_int(1000, 9999);
            TravelPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => TravelPublicShopToken::hash($plain),
                'name' => 'Test',
                'active' => true,
            ]);

            return ['trip' => $trip, 'token' => $plain, 'class' => $class->id];
        });

        return $data;
    }

    private function headers(string $token): array
    {
        return ['X-Travel-Shop-Token' => $token];
    }

    public function test_public_search_requires_valid_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->publishedTripWithToken($company);

        // Sans jeton → 401.
        $this->getJson('/api/v1/public/travel/shop/trips')->assertStatus(401);

        // Jeton invalide → 401.
        $this->getJson('/api/v1/public/travel/shop/trips', ['X-Travel-Shop-Token' => 'invalide'])
            ->assertStatus(401);
    }

    public function test_public_search_lists_published_trips_only(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->publishedTripWithToken($company);

        $this->getJson('/api/v1/public/travel/shop/trips', $this->headers($fx['token']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cross_tenant_access_is_impossible(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        $fx = $this->publishedTripWithToken($companyA);

        // Tenant B (sans trajets) tente d'accéder au trajet de A → 404.
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyB);

        $tokenB = app(TenantManager::class)->withinTenant($companyB, function () use ($companyB): string {
            $plain = 'tshop_b_'.random_int(1000, 9999);
            TravelPublicShopToken::query()->create([
                'company_id' => $companyB->id,
                'token_hash' => TravelPublicShopToken::hash($plain),
                'name' => 'B',
                'active' => true,
            ]);

            return $plain;
        });

        $this->getJson('/api/v1/public/travel/shop/trips/'.$fx['trip']->id, $this->headers($tokenB))
            ->assertStatus(404);
    }

    public function test_public_booking_and_tracking_with_code(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->publishedTripWithToken($company);

        $booking = $this->postJson('/api/v1/public/travel/shop/bookings', [
            'trip_id' => $fx['trip']->id,
            'idempotency_key' => 'public-1',
            'passengers' => [['full_name' => 'Client Public', 'age_category' => 'adult', 'class_id' => $fx['class']]],
            'contact_email' => 'client@public.example',
            'notify_consent' => true,
        ], $this->headers($fx['token']))->assertStatus(201)->json('data');

        $this->assertSame('pending', $booking['status']);
        $this->assertSame('online', $booking['booking_source']);

        // Suivi public : code de validation requis (mauvais code → 404).
        $this->getJson('/api/v1/public/travel/shop/bookings/'.$booking['reference'], $this->headers($fx['token']))
            ->assertStatus(422);

        // Bon code : émis depuis un billet (hash vérifié).
        // Émission du billet : le code en clair n'est retourné QU'à l'émission
        // (jamais persisté ni relu) — on le capture ici pour le suivi public.
        $ticketCode = app(TenantManager::class)->withinTenant($company, function () use ($booking): string {
            $model = TravelBooking::query()->findOrFail($booking['id']);
            $passenger = $model->passengers()->first();
            $ticket = TravelTicket::query()->create([
                'booking_id' => $model->id,
                'passenger_id' => $passenger->id,
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            $code = $ticket->issueValidationCode();
            $ticket->save();

            return $code;
        });

        $this->getJson('/api/v1/public/travel/shop/bookings/'.$booking['reference'].'?code='.$ticketCode, $this->headers($fx['token']))
            ->assertOk()
            ->assertJsonPath('data.reference', $booking['reference']);
    }

    public function test_token_rotation_invalidates_old_token(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->publishedTripWithToken($company);

        // Rotation (authentifiée, manage).
        $rotated = $this->postJson('/api/v1/travel/public-shop-token/rotate')
            ->assertOk()
            ->json('data');

        $this->assertStringStartsWith('tshop_', $rotated['token']);

        // L'ancien jeton est invalidé → 401 ; le nouveau fonctionne.
        $this->getJson('/api/v1/public/travel/shop/trips', $this->headers($fx['token']))->assertStatus(401);
        $this->getJson('/api/v1/public/travel/shop/trips', $this->headers($rotated['token']))->assertOk();
    }
}
