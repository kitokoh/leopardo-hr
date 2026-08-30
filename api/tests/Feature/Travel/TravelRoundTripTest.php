<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-802 (#6093) — Billets aller-retour (round-trip).
 *
 * Couvre : la création des deux jambes liées (même groupe, `return_booking_id`),
 * le tarif combiné optionnel (remise serveur sur la jambe retour), et
 * l'annulation PAR SENS (l'aller peut être annulé sans toucher au retour).
 */
class TravelRoundTripTest extends TestCase
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
     * @return array{trip: int, class: int}
     */
    private function publishedTrip(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 30]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            return ['trip' => $trip->id, 'class' => $class->id];
        });
    }

    public function test_round_trip_creates_two_linked_bookings(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $outbound = $this->publishedTrip($company);
        $return = $this->publishedTrip($company);

        $response = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $outbound['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'round-trip-1',
            'passengers' => [['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $outbound['class']]],
            'return_trip_id' => $return['trip'],
            'return_passengers' => [['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $return['class']]],
        ]);
        if ($response->status() !== 201) {
            fwrite(STDERR, json_encode($response->json()).PHP_EOL);
        }
        $response->assertStatus(201);

        $outbound = $response->json('data');
        $this->assertNotNull($outbound['round_trip_group_id']);
        $this->assertSame('outbound', $outbound['leg']);
        $this->assertNotNull($outbound['return_booking_id']);

        $returnBooking = TravelBooking::query()->findOrFail($outbound['return_booking_id']);
        $this->assertSame($outbound['round_trip_group_id'], $returnBooking->round_trip_group_id);
        $this->assertSame('return', $returnBooking->leg);

        // 2 réservations au total (aller + retour), pas de doublon.
        $this->assertSame(2, TravelBooking::query()->count());
    }

    public function test_round_trip_applies_combined_tariff_discount_on_return_leg(): void
    {
        config()->set('travel.pricing.round_trip_discount_percent', 10);

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $outbound = $this->publishedTrip($company);
        $return = $this->publishedTrip($company);

        $response = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $outbound['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'round-trip-2',
            'passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $outbound['class']]],
            'return_trip_id' => $return['trip'],
            'return_passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $return['class']]],
        ])->assertStatus(201);

        $outbound = $response->json('data');
        $returnBooking = TravelBooking::query()->findOrFail($outbound['return_booking_id']);

        // Aller : 10 000 ; retour : 10 000 − 10 % = 9 000.
        $this->assertSame(10000, $outbound['total_amount_minor']);
        $this->assertSame(9000, $returnBooking->total_amount_minor);
    }

    public function test_round_trip_can_be_cancelled_per_leg(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $outbound = $this->publishedTrip($company);
        $return = $this->publishedTrip($company);

        $response = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $outbound['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'round-trip-3',
            'passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $outbound['class']]],
            'return_trip_id' => $return['trip'],
            'return_passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $return['class']]],
        ])->assertStatus(201);

        $outboundId = $response->json('data.id');
        $returnId = $response->json('data.return_booking_id');

        $this->postJson("/api/v1/travel/bookings/{$outboundId}/cancel", [
            'reason' => 'Changement de programme',
        ])->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        // La jambe retour reste intacte.
        $returnBooking = TravelBooking::query()->findOrFail($returnId);
        $this->assertSame(BookingStatus::PENDING, $returnBooking->status);
    }
}
