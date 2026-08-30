<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyTransaction;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-811 (#6101) — Fidélité voyageur.
 *
 * Opt-in RGPD explicite requis ; points crédités une seule fois par billet ;
 * solde consultable ; récompenses (1 point = 10 unités mineures).
 */
class TravelLoyaltyTest extends TestCase
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
     * Réservation confirmée avec un passager, contact_id donné.
     *
     * @return array{booking: array<string, mixed>, passengerId: int}
     */
    private function confirmedBooking(Company $company, int $contactId): array
    {
        $trip = app(TenantManager::class)->withinTenant($company, function (): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return $trip->refresh();
        });

        $classId = app(TenantManager::class)->withinTenant($company, fn (): int => TravelClass::query()->firstOrFail()->id);

        $booking = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'loyalty-bk-'.uniqid(),
            'customer_contact_id' => $contactId,
            'passengers' => [
                ['full_name' => 'Passager Fidele', 'age_category' => 'adult', 'class_id' => $classId],
            ],
        ])->assertStatus(201);

        $bookingId = $booking->json('data.id');
        $this->postJson("/api/v1/travel/bookings/{$bookingId}/confirm")->assertOk();

        return [
            'booking' => $booking->json('data'),
            'passengerId' => (int) $booking->json('data.passengers.0.id'),
        ];
    }

    public function test_points_are_earned_once_per_ticket_with_opt_in(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Opt-in explicite.
        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_id' => 42])->assertStatus(201);

        $setup = $this->confirmedBooking($company, 42);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        // 15 000 minor → 150 points, crédités une seule fois par billet.
        $this->getJson('/api/v1/travel/loyalty/42')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 150)
            ->assertJsonPath('data.opted_in', true);

        // Rejeu de l'émission : aucun crédit supplémentaire.
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->getJson('/api/v1/travel/loyalty/42')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 150);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelLoyaltyTransaction::query()->where('type', 'earn')->count();
        }));
    }

    public function test_no_points_without_opt_in(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Pas d'opt-in : l'émission ne crédite rien.
        $setup = $this->confirmedBooking($company, 43);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->getJson('/api/v1/travel/loyalty/43')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 0)
            ->assertJsonPath('data.opted_in', false);
    }

    public function test_opt_out_freezes_credits(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_id' => 44])->assertStatus(201);
        $setup = $this->confirmedBooking($company, 44);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->postJson('/api/v1/travel/loyalty/opt-out', ['contact_id' => 44])->assertOk();

        // Nouvelle réservation + émission : plus aucun crédit (opt-out).
        $setup2 = $this->confirmedBooking($company, 44);
        $this->postJson('/api/v1/travel/bookings/'.$setup2['booking']['id'].'/issue-ticket')->assertOk();

        $this->getJson('/api/v1/travel/loyalty/44')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 150)
            ->assertJsonPath('data.opted_in', false);
    }

    public function test_redeem_converts_points_to_discount(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_id' => 45])->assertStatus(201);
        $setup = $this->confirmedBooking($company, 45);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        // 100 points → 1 000 unités mineures d'avoir.
        $this->postJson('/api/v1/travel/loyalty/45/redeem', [
            'points' => 100,
            'booking_id' => $setup['booking']['id'],
        ])->assertOk()
            ->assertJsonPath('data.discount_minor', 1000)
            ->assertJsonPath('data.points_burned', 100);

        $this->getJson('/api/v1/travel/loyalty/45')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 50);
    }

    public function test_redeem_rejects_insufficient_balance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_id' => 46])->assertStatus(201);

        $this->postJson('/api/v1/travel/loyalty/46/redeem', ['points' => 10])
            ->assertStatus(422);
    }
}
